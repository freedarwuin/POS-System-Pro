<?php
include 'conexion.php';
session_start();

// Validar que sea Admin
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Admin') {
    die("Acceso no autorizado");
}

if (isset($_GET['accion']) && $_GET['accion'] === 'borrado_masivo') {
    try {
        // 1. Contar cuántos productos hay para el registro
        $res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM productos");
        $data = mysqli_fetch_assoc($res);
        $total = $data['total'];

        // 2. Vaciar la tabla (TRUNCATE reinicia los IDs a 1)
        mysqli_query($conexion, "TRUNCATE TABLE productos");

        // 3. Registrar en el historial [cite: 2026-02-08]
        $usuario = $_SESSION['usuario'] ?? 'Admin';
        $detalle = "LIMPIEZA TOTAL: Se eliminaron $total productos para reiniciar inventario.";
        
        $stmt = $conexion->prepare("INSERT INTO historial (accion, detalle, usuario, fecha) VALUES ('ELIMINACION MASIVA', ?, ?, NOW())");
        $stmt->bind_param("ss", $detalle, $usuario);
        $stmt->execute();

        // 4. Redirigir con mensaje de éxito
        header("Location: configuracion.php?msj=Inventario reiniciado con éxito");
        exit();

    } catch (Exception $e) {
        header("Location: configuracion.php?msj=Error al reiniciar: " . $e->getMessage());
        exit();
    }
}