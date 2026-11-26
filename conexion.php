<?php
$host = "localhost";
$user = "pos_app";
$pass = "p0o9i8u7";
$db   = "puntodeventa";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
?>
