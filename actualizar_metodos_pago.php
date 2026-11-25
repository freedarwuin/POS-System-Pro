<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $activos = $_POST['metodos_activos'] ?? []; // Array con IDs seleccionados

    // 1. Desactivar todos los métodos
    mysqli_query($conexion, "UPDATE metodos_pago SET activo = 0");

    // 2. Activar únicamente los seleccionados
    if (!empty($activos)) {
        $ids = implode(',', array_map('intval', $activos));
        mysqli_query($conexion, "UPDATE metodos_pago SET activo = 1 WHERE id IN ($ids)");
    }

    header("Location: configuracion.php?status=metodos_ok");
    exit();
}