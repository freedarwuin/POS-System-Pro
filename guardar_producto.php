<?php
include 'conexion.php';

$codigo = $_POST['codigo'];
$nombre = $_POST['nombre'];
$precio = $_POST['precio'];
$stock  = $_POST['stock'];

$sql = "INSERT INTO productos (codigo_barras, nombre, precio, stock) 
        VALUES ('$codigo', '$nombre', '$precio', '$stock')";

if (mysqli_query($conexion, $sql)) {
    header("Location: index.php"); // Regresa a la página principal
} else {
    echo "Error: " . $sql . "<br>" . mysqli_error($conexion);
}
?>