<?php
/**
 * Actualizador manual del POS. Requiere una sesión de administrador.
 * Conserva conexion.php, .htaccess, los datos de facturas_adjuntas y android-app.
 */
session_start();

if (empty($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Acceso denegado. Inicia sesión con una cuenta de administrador.');
}

const POS_UPDATE_REPO = 'freedarwuin/POS-System-Pro';
const POS_UPDATE_API_URL = 'https://api.github.com/repos/freedarwuin/POS-System-Pro/releases/latest';

// Compatibilidad independiente de str_contains, str_starts_with y str_ends_with.
function posUpdateContains(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) !== false;
}

function posUpdateStartsWith(string $haystack, string $needle): bool
{
    return $needle === '' || strpos($haystack, $needle) === 0;
}

function posUpdateEndsWith(string $haystack, string $needle): bool
{
    if ($needle === '') {
        return true;
    }
    $length = strlen($needle);
    return $length <= strlen($haystack) && substr($haystack, -$length) === $needle;
}

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
            'header' => "User-Agent: POS-System-Pro-Updater\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $data = @file_get_contents($url, false, $context, 0, $maxBytes + 1);
    if ($data === false || strlen($data) > $maxBytes) {
        throw new RuntimeException('No se pudo descargar la información o el archivo excede el límite permitido.');
    }
    return $data;
}

function posUpdateSkip(string $relativePath): bool
{
    $path = str_replace('\\', '/', $relativePath);
    return $path === 'conexion.php'
        || $path === '.htaccess'
        || posUpdateStartsWith($path, 'facturas_adjuntas/')
        || $path === 'android-app'
        || posUpdateStartsWith($path, 'android-app/')
        || posUpdateStartsWith($path, '.git/');
}

function posUpdateSafePath(string $relativePath): bool
{
    if ($relativePath === '' || posUpdateContains($relativePath, "\0")) {
        return false;
    }
    $path = str_replace('\\', '/', $relativePath);
    if (posUpdateStartsWith($path, '/') || preg_match('/^[A-Za-z]:/', $path)) {
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
    $release = json_decode(posUpdateFetch(POS_UPDATE_API_URL, 65536), true);
    if (!is_array($release) || empty($release['tag_name'])) {
        throw new RuntimeException('No se pudo obtener el release estable más reciente.');
    }

    $tag = (string) $release['tag_name'];
    $version = ltrim($tag, 'vV');
    if (!preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
        throw new RuntimeException('El tag del release no tiene un formato de versión válido.');
    }

    return [
        'version' => $version,
        'tag' => $tag,
        'release_notes' => (string) ($release['body'] ?? ''),
        'archive_url' => 'https://codeload.github.com/' . POS_UPDATE_REPO . '/zip/refs/tags/' . rawurlencode($tag),
    ];
}

try {
    $remoteData = posUpdateCheck();
} catch (Throwable $e) {
    $message = 'No fue posible consultar las actualizaciones: ' . $e->getMessage();
    $messageType = 'error';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['instalar'])) {
    set_time_limit(300);

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

        $archiveBytes = posUpdateFetch($remoteData['archive_url'], 50 * 1024 * 1024);
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
                if ($relative === '' || posUpdateEndsWith($entry, '/') || posUpdateSkip($relative)) {
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

            $message = 'Actualización instalada correctamente desde el release ' . $remoteData['tag'] . ' (versión ' . $remoteVersion . '). Se conservaron la conexión a la base de datos, .htaccess, facturas_adjuntas y android-app.';
            $messageType = 'success';
            $localVersion = $remoteVersion;
        } finally {
            if (isset($zip) && $zip->status === ZipArchive::ER_OPEN) {
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
        body{font-family:Arial,sans-serif;background:#f5f6fa;color:#202633;margin:0;padding:24px}
        .card{max-width:680px;margin:6vh auto;background:#fff;padding:30px;border-radius:16px;box-shadow:0 14px 40px rgba(32,38,51,.1)}
        h1{margin-top:0}
        .muted{color:#667085}
        .status{padding:16px;border-radius:10px;background:#eef4ff;margin:20px 0}
        .status.success{background:#e8f7ed;color:#176b35}
        .status.error{background:#fff0f0;color:#a12626}
        .warning{padding:14px;background:#fff8e6;border-radius:10px;line-height:1.5}
        .button{display:inline-block;border:0;border-radius:8px;padding:12px 18px;background:#2457d6;color:#fff;font-size:15px;text-decoration:none;cursor:pointer}
        .button:hover{background:#1946b5}
        .button-back{margin-top:10px;background:#586174}
        .button-back:hover{background:#424a59}
        @media(max-width:600px){body{padding:12px}.card{padding:22px}}
    </style>
</head>
<body>
<main class="card">
    <h1>Actualizaciones del sistema</h1>
    <p class="muted">Versión instalada: <strong><?= htmlspecialchars($localVersion, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <?php if ($message !== ''): ?>
        <div class="status <?= htmlspecialchars($messageType, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?></div>
        <?php if ($messageType === 'success'): ?>
            <a class="button button-back" href="index.php">Volver al sistema</a>
        <?php endif; ?>
    <?php elseif ($available): ?>
        <div class="status"><strong>Actualización disponible: <?= htmlspecialchars((string) $remoteData['tag'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?php if ($releaseNotes !== ''): ?><p><?= nl2br(htmlspecialchars($releaseNotes, ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
        </div>
    <?php else: ?>
        <div class="status">Tu sistema está actualizado<?= is_array($remoteData) ? ' (release ' . htmlspecialchars((string) ($remoteData['tag'] ?? ''), ENT_QUOTES, 'UTF-8') . ')' : '' ?>.</div>
    <?php endif; ?>
    <p class="warning"><strong>Antes de continuar:</strong> realiza un respaldo de la base de datos y de los archivos del servidor. La actualización reemplaza los archivos de la aplicación, pero conserva la conexión, .htaccess, facturas_adjuntas y android-app.</p>
    <?php if ($available): ?>
        <form method="post" onsubmit="return confirm('¿Instalar la actualización desde el último release estable? Asegúrate de tener un respaldo reciente de archivos y base de datos.');">
            <input type="hidden" name="csrf" value="<?= htmlspecialchars($_SESSION['pos_update_csrf'], ENT_QUOTES, 'UTF-8') ?>">
            <button class="button" type="submit" name="instalar" value="1">Descargar e instalar actualización</button>
        </form>
    <?php endif; ?>
    <p class="muted"><small>Las versiones se toman del último GitHub Release estable y el paquete se descarga desde el ZIP asociado a su tag.</small></p>
</main>
</body>
</html>
