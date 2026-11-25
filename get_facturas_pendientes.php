<?php
include 'conexion.php';
$id_cliente = intval($_GET['id_cliente']);

$query = "SELECT v.id, v.total, IFNULL(SUM(p.monto), 0) as total_abonado
          FROM ventas v
          LEFT JOIN pagos_credito p ON v.id = p.id_venta
          WHERE v.id_cliente = $id_cliente AND v.pagada = 0
          GROUP BY v.id
          HAVING (v.total - total_abonado) > 0.01";

$res = mysqli_query($conexion, $query);
$facturas = [];

while($row = mysqli_fetch_assoc($res)) {
    $facturas[] = $row;
}

header('Content-Type: application/json');
echo json_encode($facturas);