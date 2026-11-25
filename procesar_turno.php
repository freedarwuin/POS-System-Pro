<?php
session_start();
include 'conexion.php';

// Validar sesión general
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// Capturar el nombre de usuario activo
$usuario_actual = $_SESSION['usuario'];

// Obtener el ID del usuario de forma segura basándonos en su sesión activa
$query_user_id = mysqli_query($conexion, "SELECT id FROM usuarios WHERE username = '$usuario_actual' LIMIT 1");
$row_u = mysqli_fetch_assoc($query_user_id);
$id_usuario = $row_u['id'] ?? 0;

if ($id_usuario === 0) {
    die("Error: No se pudo verificar el ID del usuario actual.");
}

$accion = $_POST['accion'] ?? '';

// ==========================================
// NUEVA ACCIÓN: ABRIR TURNO DIARIO
// ==========================================
if ($accion === 'abrir_turno') {
    // Sanitizar y validar las entradas numéricas
    $monto_usd = isset($_POST['monto_usd']) ? floatval($_POST['monto_usd']) : 0.00;
    $monto_bs  = isset($_POST['monto_bs'])  ? floatval($_POST['monto_bs'])  : 0.00;
    
    // Usamos NOW() para registrar la fecha y hora exacta del servidor en tiempo real
    $query_apertura = "INSERT INTO aperturas_caja (id_usuario, monto_usd, monto_bs, fecha_apertura, estatus) 
                       VALUES ('$id_usuario', '$monto_usd', '$monto_bs', NOW(), 'Abierta')";

    if (mysqli_query($conexion, $query_apertura)) {
        header("Location: cajero_dashboard.php?msj=Turno abierto con éxito. ¡Buen día de trabajo!");
        exit();
    } else {
        die("Error al registrar la apertura de caja: " . mysqli_error($conexion));
    }
}

// ==========================================
// ACCIÓN: CERRAR TURNO E IR AL CORTE
// ==========================================
if ($accion === 'cerrar_turno') {
    $id_turno = intval($_POST['id_turno']);

    // Cambiamos el estatus a 'Cerrada' para que el sistema le pida fondos de nuevo la próxima vez
    $query_cierre = "UPDATE aperturas_caja 
                     SET estatus = 'Cerrada', fecha_cierre = NOW() 
                     WHERE id = '$id_turno' AND id_usuario = '$id_usuario'";

    if (mysqli_query($conexion, $query_cierre)) {
        // Enviamos al cajero a corte.php pasándole el ID del turno por la URL
        header("Location: corte.php?id_turno=" . $id_turno);
        exit();
    } else {
        die("Error al cerrar el turno: " . mysqli_error($conexion));
    }
}

// ==========================================
// ACCIÓN: ACTUALIZAR PERFIL
// ==========================================
if ($accion === 'actualizar_perfil') {
    $nuevo_usuario = mysqli_real_escape_string($conexion, $_POST['cambiar_usuario']);
    $nueva_clave   = mysqli_real_escape_string($conexion, $_POST['cambiar_clave']);

    if (!empty($nueva_clave)) {
        // Nota: Idealmente deberías usar password_hash aquí, pero mantengo tu lógica actual
        $query_perfil = "UPDATE usuarios SET username = '$nuevo_usuario', password = '$nueva_clave' WHERE id = '$id_usuario'";
    } else {
        $query_perfil = "UPDATE usuarios SET username = '$nuevo_usuario' WHERE id = '$id_usuario'";
    }

    if (mysqli_query($conexion, $query_perfil)) {
        $_SESSION['usuario'] = $nuevo_usuario;
        header("Location: cajero_dashboard.php?msj=Perfil actualizado con éxito");
        exit();
    } else {
        header("Location: cajero_dashboard.php?msj=Error al actualizar el perfil");
        exit();
    }
}

// Redirección por defecto si no coincide ninguna acción válida
header("Location: cajero_dashboard.php");
exit();
?>