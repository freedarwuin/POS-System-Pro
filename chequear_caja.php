<?php
session_start();
include 'conexion.php';

// Limpieza total de salida
ob_clean();
header('Content-Type: application/json');

// 1. ¿Existe la sesión?
if (!isset($_SESSION['usuario']) || empty($_SESSION['usuario'])) {
    echo json_encode(['cajaAbierta' => false, 'error' => 'Sesion vacia']);
    exit;
}

$user_session = $_SESSION['usuario'];

// 2. Buscar ID (usando nombres de columna de tus capturas: id, usuario, username)
$sql_user = "SELECT id FROM usuarios WHERE usuario = '$user_session' OR username = '$user_session' LIMIT 1";
$res_user = mysqli_query($conexion, $sql_user);
$user_data = mysqli_fetch_assoc($res_user);

if (!$user_data) {
    echo json_encode(['cajaAbierta' => false, 'error' => "Usuario '$user_session' no hallado"]);
    exit;
}

$id_usuario = $user_data['id'];

// 3. Verificar apertura (Nombres exactos: id_usuarios, estatus, fecha_apertura)
$sql_caja = "SELECT id FROM aperturas_caja 
             WHERE id_usuarios = '$id_usuario' 
             AND estatus = 'Abierta' 
             AND DATE(fecha_apertura) = CURDATE() 
             LIMIT 1";

$res_caja = mysqli_query($conexion, $sql_caja);

if ($res_caja && mysqli_num_rows($res_caja) > 0) {
    echo json_encode(['cajaAbierta' => true]);
} else {
    echo json_encode(['cajaAbierta' => false, 'mensaje' => 'Requiere apertura']);
}