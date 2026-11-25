<?php
session_start();

// 1. Validar que el usuario realmente tenga una sesión activa
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php?error=SesionNoIniciada");
    exit();
}

include 'conexion.php';

// 2. Capturar las variables del formulario y de la sesión
$usuario_actual = $_SESSION['usuario'];
$fondo_usd = floatval($_POST['fondo_usd']);
$fondo_bs = floatval($_POST['fondo_bs']);
$fecha_actual = date('Y-m-d H:i:s');

// 3. Buscar el ID del usuario en la tabla de usuarios (ya que en la sesión guardas el nombre)
// NOTA: Ajusta 'usuarios' y 'usuario' si tu tabla o columna de login se llaman diferente.
$query_user = mysqli_query($conexion, "SELECT id FROM usuarios WHERE usuario = '$usuario_actual' LIMIT 1");

if (mysqli_num_rows($query_user) > 0) {
    $row_user = mysqli_fetch_assoc($query_user);
    $id_usuario = $row_user['id'];
} else {
    die("Error crítico: El usuario activo no coincide con la base de datos.");
}

// 4. INSERT PERFECTO adaptado exactamente a tu estructura real de 'aperturas_caja'
$sql_insert = "INSERT INTO aperturas_caja (id_usuario, monto_bs, monto_usd, fecha_apertura, estatus) 
               VALUES ('$id_usuario', '$fondo_bs', '$fondo_usd', '$fecha_actual', 'Abierta')";

if (mysqli_query($conexion, $sql_insert)) {
    // Redirigir al panel con un mensaje de éxito
    header("Location: control_turno.php?msj=¡Caja y turno abiertos con éxito!");
    exit();
} else {
    // Mostrar error amigable si algo falla
    die("Error de Base de Datos al abrir caja: " . mysqli_error($conexion));
}
?>