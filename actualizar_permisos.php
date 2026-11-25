<?php
include 'conexion.php';

if ($_POST) {
    $id = $_POST['id_usuario'];
    $rol = $_POST['rol'];
    
    // Si no se marca el check, PHP no envía la variable, por eso validamos con isset
    $p_inventario = isset($_POST['p_inventario']) ? 1 : 0;
    $p_facturas   = isset($_POST['p_facturas'])   ? 1 : 0;
    $p_corte      = isset($_POST['p_corte'])      ? 1 : 0;
    $p_config     = isset($_POST['p_config'])     ? 1 : 0;

    $sql = "UPDATE usuarios SET 
            rol = '$rol', 
            p_inventario = $p_inventario, 
            p_facturas = $p_facturas, 
            p_corte = $p_corte, 
            p_config = $p_config 
            WHERE id = $id";

    mysqli_query($conexion, $sql);
    header("Location: configuracion.php");
}
?>