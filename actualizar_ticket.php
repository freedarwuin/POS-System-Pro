<?php
include 'conexion.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre_negocio']);
    $rif = mysqli_real_escape_string($conexion, $_POST['rif']);
    $direccion = mysqli_real_escape_string($conexion, $_POST['direccion']);
    $mensaje = mysqli_real_escape_string($conexion, $_POST['mensaje_ticket']);

    $query = "UPDATE configuracion SET 
              nombre_negocio = '$nombre', 
              rif = '$rif', 
              direccion = '$direccion', 
              mensaje_ticket = '$mensaje' 
              WHERE id = 1";

    if (mysqli_query($conexion, $query)) {
        header("Location: configuracion.php?msj=Ticket actualizado correctamente");
    } else {
        echo "Error actualizando: " . mysqli_error($conexion);
    }
}
?>