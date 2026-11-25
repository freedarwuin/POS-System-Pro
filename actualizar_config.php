<?php
include 'conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tasa = $_POST['tasa_dolar'];
    $nombre = $_POST['nombre_negocio'];
    $rif = $_POST['rif'];

    $sql = "UPDATE configuracion SET tasa_dolar = '$tasa', nombre_negocio = '$nombre', rif = '$rif' WHERE id = 1";
    
    if (mysqli_query($conexion, $sql)) {
        header("Location: configuracion.php?msj=Configuración actualizada");
    } else {
        header("Location: configuracion.php?msj=Error al actualizar");
    }
    exit();
}