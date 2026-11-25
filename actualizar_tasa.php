<?php
include 'conexion.php';

if (isset($_POST['nueva_tasa'])) {
    $nueva_tasa = $_POST['nueva_tasa'];
    $sql = "UPDATE configuracion SET tasa_dolar = '$nueva_tasa' WHERE id = 1";
    
    if (mysqli_query($conexion, $sql)) {
        header("Location: index.php?mensaje=tasa_actualizada");
    } else {
        echo "Error actualizando: " . mysqli_error($conexion);
    }
}
?>