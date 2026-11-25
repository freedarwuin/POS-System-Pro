<?php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}

function posUpdateDestination(): string
{
    $role = strtolower(trim((string) ($_SESSION['rol'] ?? '')));
    if ($role === 'admin') {
        return 'configuracion.php';
    }
    if ($role === 'cajero') {
        return 'cajero_dashboard.php';
    }
    return 'ventas.php';
}

function posUpdateEscape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$destination = posUpdateDestination();
$localData = is_file(__DIR__ . '/version.json')
    ? json_decode((string) file_get_contents(__DIR__ . '/version.json'), true)
    : null;
$localVersion = is_array($localData) ? (string) ($localData['version'] ?? '0.0.0') : '0.0.0';
$release = null;

try {
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => 5,
            'header' => "User-Agent: POS-System-Pro-Updater\r\nAccept: application/vnd.github+json\r\nX-GitHub-Api-Version: 2022-11-28\r\n",
        ],
        'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
    ]);
    $response = @file_get_contents('https://api.github.com/repos/freedarwuin/POS-System-Pro/releases/latest', false, $context);
    $candidate = $response !== false ? json_decode($response, true) : null;
    if (is_array($candidate) && empty($candidate['draft']) && empty($candidate['prerelease']) && !empty($candidate['tag_name'])) {
        $tag = (string) $candidate['tag_name'];
        $version = ltrim($tag, 'vV');
        if (preg_match('/^\d+(?:\.\d+){1,3}(?:[-+][A-Za-z0-9.-]+)?$/', $version)) {
            $candidate['version'] = $version;
            $release = $candidate;
        }
    }
} catch (Throwable $e) {
    // Si no se puede consultar GitHub, no se interrumpe el inicio de sesión.
}

$hasUpdate = is_array($release)
    && version_compare($release['version'], $localVersion, '>');

// El instalador solo está permitido para administradores.
$isAdmin = strtolower(trim((string) ($_SESSION['rol'] ?? ''))) === 'admin';
if (!$hasUpdate || !$isAdmin) {
    header('Location: ' . $destination);
    exit();
}

$tag = (string) $release['tag_name'];
$notes = trim((string) ($release['body'] ?? ''));
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Actualización disponible | POS System Pro</title>
    <style>
        *{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;place-items:center;padding:20px;background:#0f172a;font-family:Arial,sans-serif;color:#1f2937}.overlay{position:fixed;inset:0;background:rgba(15,23,42,.78);display:grid;place-items:center;padding:20px}.modal{width:min(100%,520px);background:#fff;border-radius:18px;padding:30px;box-shadow:0 24px 80px rgba(0,0,0,.35)}.icon{width:52px;height:52px;border-radius:15px;display:grid;place-items:center;background:#eff6ff;color:#2563eb;font-size:26px}.modal h1{font-size:23px;margin:18px 0 8px}.muted{color:#64748b;line-height:1.55}.version{display:inline-block;margin:8px 0 12px;padding:7px 11px;background:#eff6ff;color:#1d4ed8;border-radius:999px;font-weight:700}.notes{max-height:180px;overflow:auto;white-space:pre-wrap;background:#f8fafc;padding:12px;border-radius:10px;font-size:14px;line-height:1.5}.actions{display:flex;justify-content:flex-end;gap:10px;margin-top:22px;flex-wrap:wrap}.button{display:inline-block;text-decoration:none;border:0;border-radius:9px;padding:12px 17px;font-size:15px;font-weight:700;cursor:pointer}.later{background:#e5e7eb;color:#374151}.update{background:#2563eb;color:#fff}.update:hover{background:#1d4ed8}@media(max-width:480px){.modal{padding:22px}.actions{flex-direction:column-reverse}.button{text-align:center;width:100%}}
    </style>
</head>
<body>
<div class="overlay" role="presentation">
    <section class="modal" role="dialog" aria-modal="true" aria-labelledby="update-title">
        <div class="icon" aria-hidden="true">↻</div>
        <h1 id="update-title">Hay una actualización disponible</h1>
        <p class="muted">La versión instalada es <strong><?= posUpdateEscape($localVersion) ?></strong>. Ya está publicado un nuevo release estable:</p>
        <div class="version"><?= posUpdateEscape($tag) ?></div>
        <?php if ($notes !== ''): ?>
            <div class="notes"><?= posUpdateEscape($notes) ?></div>
        <?php endif; ?>
        <p class="muted">Antes de actualizar, realiza un respaldo de los archivos y de la base de datos.</p>
        <div class="actions">
            <a class="button later" href="<?= posUpdateEscape($destination) ?>">Más tarde</a>
            <a class="button update" href="actualizar_sistema.php">Actualizar</a>
        </div>
    </section>
</div>
</body>
</html>
