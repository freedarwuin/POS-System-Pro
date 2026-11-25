<?php
include 'conexion.php';

// Consulta avanzada que une la venta con sus productos
$sql = "SELECT v.*, GROUP_CONCAT(CONCAT(vd.cantidad, 'x ', p.nombre) SEPARATOR '<br>') as detalle_productos
        FROM ventas v
        JOIN venta_detalles vd ON v.id = vd.venta_id
        JOIN productos p ON vd.producto_id = p.id
        GROUP BY v.id
        ORDER BY v.fecha DESC";
$resultado = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 30px; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #1e293b; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; font-size: 14px; }
        .tag-pago { background: #e2e8f0; padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 11px; }
        .total-usd { color: #10b981; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <div style="display:flex; justify-content:space-between; align-items:center;">
        <h2>📋 Historial de Ventas</h2>
        <a href="ventas.php" style="text-decoration:none; color:#3b82f6;">⬅ Volver a Caja</a>
    </div>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Fecha/Hora</th>
                <th>Productos</th>
                <th>Método de Pago</th>
                <th>Total $</th>
                <th>Total Bs.</th>
            </tr>
        </thead>
        <tbody>
            <?php while($v = mysqli_fetch_assoc($resultado)): ?>
            <tr>
                <td>#<?= $v['id'] ?></td>
                <td><?= date('d/m/Y H:i', strtotime($v['fecha'])) ?></td>
                <td><?= $v['detalle_productos'] ?></td>
                <td><span class="tag-pago"><?= $v['metodo_pago'] ?></span></td>
                <td class="total-usd">$<?= number_format($v['total_usd'], 2) ?></td>
                <td>Bs. <?= number_format($v['total_bs'], 2, ',', '.') ?></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>

</body>
</html>