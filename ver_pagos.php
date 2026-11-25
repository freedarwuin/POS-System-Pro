<?php
include 'conexion.php';
session_start();

// Consulta para traer solo ventas con referencia
$sql = "SELECT v.fecha, v.numero_factura, v.cliente, v.metodo_pago, v.referencia, v.total_bs, v.total_usd 
        FROM ventas v 
        WHERE v.referencia != '' 
        ORDER BY v.fecha DESC";
$res = mysqli_query($conexion, $sql);
?>

<div class="container">
    <h2>Control de Pagos Digitales</h2>
    <table class="tabla">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Factura</th>
                <th>Cliente</th>
                <th>Método</th>
                <th>Referencia</th>
                <th>Monto Bs / $</th>
            </tr>
        </thead>
        <tbody>
            <?php while($f = mysqli_fetch_assoc($res)): ?>
            <tr>
                <td><?= date('d/m/Y H:i', strtotime($f['fecha'])) ?></td>
                <td><b>#<?= $f['numero_factura'] ?></b></td>
                <td><?= $f['cliente'] ?></td>
                <td><span class="badge-tipo"><?= $f['metodo_pago'] ?></span></td>
                <td style="color:var(--blue); font-weight:bold; font-size:1.1em;">
                    <?= $f['referencia'] ?>
                </td>
                <td>
                    <b>Bs <?= number_format($f['total_bs'], 2) ?></b><br>
                    <small style="color:green;">$<?= number_format($f['total_usd'], 2) ?></small>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>