<?php
include 'conexion.php';
session_start();

if (isset($_POST['id']) && isset($_SESSION['usuario'])) {
    $id = (int)$_POST['id'];
    $usuario_activo = $_SESSION['usuario'];

    // Obtener nombre para el historial antes de borrar
    $res_p = mysqli_query($conexion, "SELECT nombre FROM productos WHERE id = $id");
    if ($p_del = mysqli_fetch_assoc($res_p)) {
        $nombre = $p_del['nombre'];
        
        // Registrar en historial
        mysqli_query($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario) 
                     VALUES ('$id', 'ELIMINACIÓN', '0', 'ELIMINADO: $nombre', '$usuario_activo')");
        
        // Eliminar producto
        if (mysqli_query($conexion, "DELETE FROM productos WHERE id = $id")) {
            echo "success";
        }
    }
}
?>