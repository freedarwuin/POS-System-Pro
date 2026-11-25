<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario']) || !isset($_GET['id_proveedor'])) {
    exit(json_encode([]));
}

$id_proveedor = (int)$_GET['id_proveedor'];
$busqueda = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($busqueda === '') {
    exit(json_encode([]));
}

// Usamos los nombres reales de tu base de datos (codigo_barras y nombre)
$buscar_like = "%$busqueda%";
$sql = "SELECT id, codigo_barras AS codigo, nombre AS descripcion, stock 
        FROM productos 
        WHERE id_proveedor = ? AND (codigo_barras LIKE ? OR nombre LIKE ?) 
        LIMIT 10";

$stmt = mysqli_prepare($conexion, $sql);
mysqli_stmt_bind_param($stmt, "iss", $id_proveedor, $buscar_like, $buscar_like);
mysqli_stmt_execute($stmt);
$resultado = mysqli_stmt_get_result($stmt);

$productos = [];
while ($row = mysqli_fetch_assoc($resultado)) {
    $productos[] = $row;
}

echo json_encode($productos);