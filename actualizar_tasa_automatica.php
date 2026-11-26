<?php
session_start();

header('Content-Type: application/json; charset=utf-8');

function responderJsonTasa($status, array $payload)
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();
}

if (empty($_SESSION['usuario']) || (($_SESSION['rol'] ?? '') !== 'Admin')) {
    responderJsonTasa(403, ['ok' => false, 'mensaje' => 'Acceso denegado.']);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Allow: POST');
    responderJsonTasa(405, ['ok' => false, 'mensaje' => 'Método no permitido.']);
}

$tokenSesion = (string) ($_SESSION['csrf_tasa_bcv'] ?? '');
$tokenSolicitud = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if ($tokenSesion === '' || $tokenSolicitud === '' || !hash_equals($tokenSesion, $tokenSolicitud)) {
    responderJsonTasa(403, ['ok' => false, 'mensaje' => 'La sesión expiró. Recarga la página e inténtalo nuevamente.']);
}

require __DIR__ . '/conexion.php';

try {
    $resultado = @file_get_contents(
        'https://ve.dolarapi.com/v1/dolares/oficial',
        false,
        stream_context_create([
            'http' => ['method' => 'GET', 'timeout' => 15, 'header' => "Accept: application/json\r\nUser-Agent: POS-System-Pro/1.0\r\n"],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ])
    );

    if ($resultado === false) {
        throw new RuntimeException('No fue posible consultar la tasa oficial.');
    }

    $datos = json_decode($resultado, true);
    $tasa = isset($datos['promedio']) ? (float) $datos['promedio'] : (float) ($datos['precio'] ?? 0);

    if (!is_finite($tasa) || $tasa <= 0) {
        throw new RuntimeException('La tasa recibida no es válida.');
    }

    mysqli_begin_transaction($conexion);

    $stmt = mysqli_prepare($conexion, 'UPDATE configuracion SET tasa_dolar = ?, tasa_dolar_api = ?, tasa_fecha_actualizacion = NOW(), tasa_fuente = ?, tasa_automatica = 1 WHERE id = 1');
    if (!$stmt) {
        throw new RuntimeException('No se pudo preparar la actualización.');
    }

    $fuente = 'DolarAPI oficial';
    mysqli_stmt_bind_param($stmt, 'dds', $tasa, $tasa, $fuente);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException('No se pudo guardar la tasa automática.');
    }
    mysqli_stmt_close($stmt);

    $usuario = (string) $_SESSION['usuario'];
    $accion = 'ACTUALIZACIÓN TASA AUTOMÁTICA';
    $detalles = sprintf('Tasa automática aplicada: %.8F Bs/USD. Fuente: %s.', $tasa, $fuente);
    $auditoria = mysqli_prepare($conexion, 'INSERT INTO auditoria (usuario, accion, detalles) VALUES (?, ?, ?)');
    mysqli_stmt_bind_param($auditoria, 'sss', $usuario, $accion, $detalles);
    if (!mysqli_stmt_execute($auditoria)) {
        throw new RuntimeException('No se pudo registrar la auditoría.');
    }
    mysqli_stmt_close($auditoria);

    mysqli_commit($conexion);

    responderJsonTasa(200, [
        'ok' => true,
        'tasa' => $tasa,
        'mensaje' => 'Tasa automática aplicada correctamente.',
        'fuente' => $fuente,
    ]);
} catch (Throwable $e) {
    if (isset($conexion) && $conexion instanceof mysqli) {
        @mysqli_rollback($conexion);
    }
    error_log('[actualizar_tasa_automatica] ' . $e->getMessage());
    responderJsonTasa(502, ['ok' => false, 'mensaje' => 'No fue posible actualizar la tasa automática.']);
}
