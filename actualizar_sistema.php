<?php
/**
 * Actualizador manual del POS. Requiere una sesión de administrador.
 * Conserva conexion.php, .htaccess y los datos de facturas_adjuntas.
 */
session_start();

if (empty($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión con una cuenta de administrador.');
}

const POS_UPDATE_REPO = 'freedarwuin/POS-System-Pro';
const POS_UPDATE_BRANCH = 'main';
const POS_UPDATE_VERSION_URL = 'https://raw.githubusercontent.com/freedarwuin/POS-System-Pro/main/version.json';
const POS_UPDATE_ARCHIVE_URL = 'https://codeload.github.com/freedarwuin/POS-System-Pro/zip/refs/heads/main';

$root = __DIR__;
$versionFile = $root . DIRECTORY_SEPARATOR . 'version.json';
$localData = is_file($versionFile) ? json_decode((string) file_get_contents($versionFile), true) : null;
$localVersion = is_array($localData) ? (string) ($localData['version'] ?? '0.0.0') : '0.0.0';
$message = '';
$messageType = 'info';
$remoteData = null;

function posUpdateFetch(string $url, int $maxBytes): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 20,
            'follow_location' => 1,
            'max_redirects' => 3,
            'header' => "User-Agent: POS-System-Pro-Updater\r\nAccept: application/json, application/zip\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
    if ($data === false || strlen($data) > $maxBytes) {
        throw new RuntimeException('No se pudo descargar la actualización o el archivo excede el límite permitido.');
    }
    return $data;
}

function posUpdateSkip(string $relativePath): bool
{
    $path = str_replace('\\', '/', $relativePath);
    return $path === 'conexion.php'
        || $path === '.htaccess'
        || str_starts_with($path, 'facturas_adjuntas/')
        || str_starts_with($path, '.git/');
}

function posUpdateSafePath(string $relativePath): bool
{
    if ($relativePath === '' || str_contains($relativePath, "\0")) {
        return false;
    }
    $path = str_replace('\\', '/', $relativePath);
    if (str_starts_with($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
        return false;
    }
    foreach (explode('/', $path) as $part) {
        if ($part === '..' || $part === '.') {
            return false;
        }
    }
    return true;
}

function posUpdateCheck(): array
{
    $json = json_decode(posUpdateFetch(POS_UPDATE_VERSION_URL, 65536), true);
    if (!is_array($json) || empty($json['version']) || !preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', (string) $json['version'])) {
        throw new RuntimeException('El archivo version.json remoto no tiene un formato de versión válido.');
    }
    return $json;
}

try {
    $remoteData = posUpdateCheck();
} catch (Throwable $e) {
    $message = 'No fue posible consultar las actualizaciones: ' . $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instalar'])) {
    if (!hash_equals((string) ($_SESSION['pos_update_csrf'] ?? ''), (string) ($_POST['csrf'] ?? ''))) {
        http_response_code(400);
        exit('Solicitud no válida. Recarga la página e inténtalo nuevamente.');
    }

    try {
        $remoteData = posUpdateCheck();
        $remoteVersion = (string) $remoteData['version'];
        if (version_compare($remoteVersion, $localVersion, '<=')) {
            throw new RuntimeException('No hay una versión más reciente para instalar.');
        }
        if (!class_exists('ZipArchive')) {
            throw new RuntimeException('El servidor no tiene habilitada la extensión PHP ZipArchive.');
        }

        $archiveBytes = posUpdateFetch(POS_UPDATE_ARCHIVE_URL, 50 * 1024 * 1024);
        $tempBase = tempnam(sys_get_temp_dir(), 'pos-update-');
        if ($tempBase === false) {
            throw new RuntimeException('No se pudo crear un archivo temporal.');
        }
        $zipPath = $tempBase . '.zip';
        @unlink($tempBase);
        if (file_put_contents($zipPath, $archiveBytes, LOCK_EX) === false) {
            throw new RuntimeException('No se pudo guardar el paquete descargado.');
        }
        @chmod($zipPath, 0600);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('El paquete descargado no es un ZIP válido.');
        }

        $stage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pos-stage-' . bin2hex(random_bytes(8));
        $backup = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pos-backup-' . bin2hex(random_bytes(8));
        if (!mkdir($stage, 0700) || !mkdir($backup, 0700)) {
            $zip->close();
            @unlink($zipPath);
            throw new RuntimeException('No se pudieron crear las carpetas temporales de instalación y respaldo.');
        }

        $stagedFiles = [];
        $totalBytes = 0;
        $archiveRoot = null;
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entry = $zip->getNameIndex($i);
                if ($entry === false) {
                    throw new RuntimeException('El ZIP contiene una entrada ilegible.');
                }
                $entry = str_replace('\\', '/', $entry);
                if (!posUpdateSafePath($entry)) {
                    throw new RuntimeException('El ZIP contiene una ruta no permitida.');
                }
                $parts = explode('/', trim($entry, '/'));
                if (count($parts) < 2) {
                    continue;
                }
                if ($archiveRoot === null) {
                    $archiveRoot = $parts[0];
                }
                if ($parts[0] !== $archiveRoot) {
                    throw new RuntimeException('El ZIP tiene una estructura inesperada.');
                }
                $relative = implode('/', array_slice($parts, 1));
                if ($relative === '' || str_ends_with($entry, '/') || posUpdateSkip($relative)) {
                    continue;
                }
                $stat = $zip->statIndex($i);
                $size = (int) ($stat['size'] ?? 0);
                $totalBytes += $size;
                if ($size > 20 * 1024 * 1024 || $totalBytes > 150 * 1024 * 1024) {
                    throw new RuntimeException('El paquete excede el tamaño máximo de instalación.');
                }
                $stream = $zip->getStream($entry);
                if ($stream === false) {
                    throw new RuntimeException('No se pudo leer un archivo del paquete.');
                }
                $destination = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                $directory = dirname($destination);
                if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
                    fclose($stream);
                    throw new RuntimeException('No se pudo preparar la carpeta temporal.');
                }
                $output = fopen($destination, 'wb');
                if ($output === false) {
                    fclose($stream);
                    throw new RuntimeException('No se pudo preparar un archivo de actualización.');
                }
                stream_copy_to_stream($stream, $output);
                fclose($stream);
                fclose($output);
                $stagedFiles[] = $relative;
            }
            $zip->close();
            @unlink($zipPath);
            if (!$stagedFiles) {
                throw new RuntimeException('El paquete no contiene archivos instalables.');
            }

            // Respalda todos los archivos que serán reemplazados antes de modificar el proyecto.
            foreach ($stagedFiles as $relative) {
                $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                if (is_link($target)) {
                    throw new RuntimeException('Se canceló la actualización: hay un enlace simbólico en el destino.');
                }
                if (is_file($target)) {
                    $backupFile = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (!is_dir(dirname($backupFile))) {
                        mkdir(dirname($backupFile), 0700, true);
                    }
                    if (!copy($target, $backupFile)) {
                        throw new RuntimeException('No se pudo respaldar un archivo existente; no se instaló ningún cambio.');
                    }
                }
            }

            $installed = [];
            try {
                foreach ($stagedFiles as $relative) {
                    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $directory = dirname($target);
                    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
                        throw new RuntimeException('No se pudo crear una carpeta del proyecto.');
                    }
                    if (is_link($target) || (file_exists($target) && !is_file($target))) {
                        throw new RuntimeException('El destino de un archivo no es seguro.');
                    }
                    $incoming = $stage . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $temporaryTarget = $target . '.update-' . bin2hex(random_bytes(4));
                    if (!copy($incoming, $temporaryTarget) || !rename($temporaryTarget, $target)) {
                        @unlink($temporaryTarget);
                        throw new RuntimeException('No se pudo reemplazar ' . $relative . '.');
                    }
                    $installed[] = $relative;
                }
            } catch (Throwable $installError) {
                foreach (array_reverse($installed) as $relative) {
                    $target = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    $backupFile = $backup . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
                    if (is_file($backupFile)) {
                        @copy($backupFile, $target);
                    } else {
                        @unlink($target);
                    }
                }
                throw $installError;
            }

            $message = 'Actualización instalada correctamente (versión ' . $remoteVersion . '). Se conservaron la conexión a la base de datos, .htaccess y los archivos adjuntos.';
            $messageType = 'success';
            $localVersion = $remoteVersion;
        } finally {
            if ($zip->status === ZipArchive::ER_OPEN) {
                $zip->close();
            }
            @unlink($zipPath);
            $removeTree = static function (string $path) use (&$removeTree): void {
                if (!is_dir($path) || is_link($path)) {
                    @unlink($path);
                    return;
                }
                foreach (scandir($path) ?: [] as $item) {
                    if ($item !== '.' && $item !== '..') {
                        $removeTree($path . DIRECTORY_SEPARATOR . $item);
                    }
                }
                @rmdir($path);
            };
            $removeTree($stage);
            $removeTree($backup);
        }
    } catch (Throwable $e) {
        $message = 'No se pudo completar la actualización: ' . $e->getMessage();
        $messageType = 'error';
    }
}

if (empty($_SESSION['pos_update_csrf'])) {
    $_SESSION['pos_update_csrf'] = bin2hex(random_bytes(32));
}
$available = is_array($remoteData) && !empty($remoteData['version']) && version_compare((string) $remoteData['version'], $localVersion, '>');
$releaseNotes = is_array($remoteData) ? (string) ($remoteData['release_notes'] ?? '') : '';
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actualizaciones | POS System Pro</title>
    <style>
        body{font-family:Arial,sans-serif;background:#f5f6fa;color:#202633;margin:0;padding:24px}.card{max-width:680px;margin:6vh auto;background:#fff;padding:30px;border-radius:16px;box-shadow:0 8px 30px #17203312}h1{margin-top:0}.status{padding:14px;border-radius:10px;background:#eef4fe;margin:18px 0}.success{background:#e8f7ed}.error{background:#fdeaea}.warning{font-size:.92rem;color:#555;line-height:1.55}.button{background:#d81b60;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-size:1rem;font-weight:bold;cursor:pointer}.button:disabled{opacity:.55;cursor:wait}.muted{color:#667085}
    </style>
</head>
<body>
<main class="card">
    <h1>Actualizaciones del sistema</h1>
    <p class="muted">Versión instalada: <strong><?= htmlspecialchars($localVersion, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php if ($message !== ''): ?>
        <div class="status <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
    <?php elseif ($available): ?>
        <div class="status"><strong>Actualización disponible: <?= htmlspecialchars((string) $remoteData['version'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($releaseNotes !== ''): ?><p><?= nl2br(htmlspecialchars($releaseNotes, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="status">Tu sistema está actualizado<?= is_array($remoteData) ? ' (versión ' . htmlspecialchars((string) ($remoteData['version'] ?? ''), ENT_QUOTES, 'UTF-8') . ')' : '' ?>.</div>
    <?php endif; ?>
    <p class="warning"><strong>Antes de continuar:</strong> realiza un respaldo de la base de datos y de los archivos del servidor. La actualización reemplaza los archivos de la aplicación, pero conserva <code>conexion.php</code>, <code>.htaccess</code> y <code>facturas_adjuntas/</code>. No ejecuta migraciones de base de datos; cualquier cambio SQL deberá aplicarse por separado.</p>
    <?php if ($available): ?>
        <form method="post" onsubmit="return confirm('¿Instalar la actualización? Asegúrate de tener un respaldo reciente de archivos y base de datos.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['pos_update_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="button" type="submit" name="instalar" value="1">Descargar e instalar actualización</button>
        </form>
    <?php endif; ?>
    <p class="muted"><small>La versión publicada debe actualizarse en <code>version.json</code> para que los clientes detecten una nueva versión.</small></p>
</main>
</body>
</html>
