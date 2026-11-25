<?php
include 'conexion.php';
$fecha_test = '2026-06-07'; // Cambia por la fecha del problema

$query = "SELECT id, total, metodo_pago, estado FROM ventas WHERE DATE(fecha) = '$fecha_test'";
$res = mysqli_query($conexion, $query);

echo "<h3>Comparativa de Ventas para $fecha_test</h3>";
echo "<table border='1'><tr><th>ID Venta</th><th>Total DB</th><th>Método</th><th>Estado</th></tr>";
$suma_total = 0;
while($row = mysqli_fetch_assoc($res)){
    echo "<tr><td>{$row['id']}</td><td>{$row['total']}</td><td>{$row['metodo_pago']}</td><td>{$row['estado']}</td></tr>";
    $suma_total += $row['total'];
}
echo "</table>";
echo "<h3>SUMA TOTAL EN BASE DE DATOS: $suma_total</h3>";
?>