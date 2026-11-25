<?php
include 'conexion.php';

$queries = [
    "SET FOREIGN_KEY_CHECKS = 0",
    "TRUNCATE TABLE detalle_ventas",
    "TRUNCATE TABLE ventas",
    "TRUNCATE TABLE historial_abonos",
    "TRUNCATE TABLE clientes_credito",
    "TRUNCATE TABLE historial_movimientos",
    "SET FOREIGN_KEY_CHECKS = 1"
];

foreach ($queries as $sql) {
    mysqli_query($conexion, $sql);
}

header("Location: configuracion.php?msj=Sistema reiniciado correctamente");
?>