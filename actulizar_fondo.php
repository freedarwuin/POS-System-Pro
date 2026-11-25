<?php
include 'conexion.php';
$fondo = $_POST['fondo_caja'];
mysqli_query($conexion, "UPDATE configuracion SET fondo_caja = $fondo WHERE id = 1");
header("Location: configuracion.php?success=1");
?>