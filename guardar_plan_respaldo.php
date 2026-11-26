<?php
session_start();

if (empty($_SESSION['usuario']) || ($_SESSION['rol'] ?? '') !== 'Admin') {
    http_response_code(403);
    exit('Acceso denegado. Solo un administrador puede modificar el plan de respaldos.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: configuracion.php#plan-respaldos');
    exit();
}

$tokenSesion = (string) ($_SESSION['csrf_plan_respaldo'] ?? '');
$tokenFormulario = (string) ($_POST['csrf_token'] ?? '');
if ($tokenSesion === '' || !hash_equals($tokenSesion, $tokenFormulario)) {
    http_response_code(403);
    exit('La solicitud expiró. Recarga la página y vuelve a intentarlo.');
}

$frecuencia = (string) ($_POST['frecuencia'] ?? '');
if (!in_array($frecuencia, ['diario', 'semanal', 'mensual'], true)) {
    http_response_code(400);
    exit('La frecuencia seleccionada no es válida.');
}
$activo = isset($_POST['activo']) ? 1 : 0;

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/plan_respaldos_helper.php';

try {
    posAsegurarTablaPlanRespaldos($conexion);
    $stmt = mysqli_prepare($conexion, 'UPDATE plan_respaldos SET activo = ?, frecuencia = ? WHERE id = 1');
    if (!$stmt) {
        throw new RuntimeException(mysqli_error($conexion));
    }
    mysqli_stmt_bind_param($stmt, 'is', $activo, $frecuencia);
    if (!mysqli_stmt_execute($stmt)) {
        throw new RuntimeException(mysqli_stmt_error($stmt));
    }
    mysqli_stmt_close($stmt);
    $_SESSION['mensaje_plan_respaldo'] = $activo
        ? 'Plan de recordatorios guardado correctamente.'
        : 'Recordatorios desactivados. Puedes reactivarlos cuando quieras.';
} catch (Throwable $e) {
    error_log('[guardar_plan_respaldo] ' . $e->getMessage());
    $_SESSION['mensaje_plan_respaldo'] = 'No se pudo guardar el plan. Revisa el registro de errores del servidor.';
}

header('Location: configuracion.php#plan-respaldos');
exit();
