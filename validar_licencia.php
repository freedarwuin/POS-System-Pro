<?php
// validar_licencia.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Requerir la conexión a la base de datos si no existe
if (!isset($conexion)) {
    require_once 'conexion.php';
}

// ==========================================
// 1. MANEJO DE ACTIVACIÓN VÍA AJAX (POST)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activar_licencia'])) {
    header('Content-Type: application/json');
    $key_ingresada = trim($_POST['key_cliente'] ?? '');

    // Buscar si existe la licencia ingresada
    $stmt = mysqli_prepare($conexion, "SELECT id, fecha_vencimiento FROM licencia WHERE codigo_licencia = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $key_ingresada);
    mysqli_stmt_execute($stmt);
    $res = mysqli_stmt_get_result($stmt);
    $lic = mysqli_fetch_assoc($res);
    mysqli_stmt_close($stmt);

    if ($lic) {
        $hoy = date('Y-m-d');
        
        // Si la fecha grabada en la BD ya venció, le sumamos 1 año a partir de hoy (o ajusta según tu modelo comercial)
        $nueva_fecha = ($lic['fecha_vencimiento'] < $hoy) 
            ? date('Y-m-d', strtotime('+1 year')) 
            : $lic['fecha_vencimiento'];

        // Reactivar licencia: cambiar estado a 'Activo', actualizar ultimo_uso y renovar fecha si aplicaba
        $update = mysqli_prepare($conexion, "UPDATE licencia SET estado = 'Activo', fecha_vencimiento = ?, ultimo_uso = ? WHERE id = ?");
        mysqli_stmt_bind_param($update, "ssi", $nueva_fecha, $hoy, $lic['id']);
        
        if (mysqli_stmt_execute($update)) {
            echo json_encode([
                'status' => 'success', 
                'message' => 'Licencia reactivada correctamente hasta el ' . $nueva_fecha
            ]);
        } else {
            echo json_encode([
                'status' => 'error', 
                'message' => 'Ocurrió un error al intentar actualizar la base de datos.'
            ]);
        }
        mysqli_stmt_close($update);

    } else {
        echo json_encode([
            'status' => 'error', 
            'message' => 'La clave de activación ingresada no es válida o no existe.'
        ]);
    }
    exit();
}

// ==========================================
// 2. VERIFICACIÓN DE ESTADO Y CADUCIDAD
// ==========================================
$queryLic = mysqli_query($conexion, "SELECT fecha_vencimiento, estado FROM licencia LIMIT 1");
$licencia = mysqli_fetch_assoc($queryLic);

$hoy = date('Y-m-d');
$bloqueado = false;
$mensajeError = '';

if (!$licencia) {
    $bloqueado = true;
    $mensajeError = 'Estimado usuario, no se encontró un registro de licencia válido en el sistema. Consulte con el desarrollador.';
} elseif (strtolower(trim($licencia['estado'])) !== 'activo') {
    $bloqueado = true;
    $mensajeError = 'Estimado cliente, la licencia de uso se encuentra inactiva o suspendida. Ingrese una clave válida o consulte con el desarrollador.';
} elseif ($licencia['fecha_vencimiento'] < $hoy) {
    $bloqueado = true;
    $mensajeError = 'Estimado usuario, su licencia de uso expiró el ' . date('d/m/Y', strtotime($licencia['fecha_vencimiento'])) . '. Para continuar utilizando el sistema, efectúe la renovación con el desarrollador.';
}

// ==========================================
// 3. REDIRECCIÓN A PANTALLA DE BLOQUEO
// ==========================================
$pagina_actual = basename($_SERVER['PHP_SELF']);

if ($bloqueado && $pagina_actual !== 'licencia_bloqueada.php') {
    header("Location: licencia_bloqueada.php?msj=" . urlencode($mensajeError));
    exit();
}
?>