<?php
$host = "localhost";
$user = "root";     // Usuario por defecto
$pass = "27283989";         // Contraseña por defecto (vacía)
$db   = "puntodeventa"; // CAMBIA ESTO por el nombre que le diste en phpMyAdmin

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>