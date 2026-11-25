<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['ultimo_abono'])) {
    die("No hay datos de abono para mostrar.");
}

$datos = $_SESSION['ultimo_abono'];
$id_cliente = $datos['cliente'];

// Buscamos los datos del cliente para el recibo
$res = mysqli_query($conexion, "SELECT * FROM clientes_credito WHERE id = '$id_cliente'");
$c = mysqli_fetch_assoc($res);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Recibo de Abono</title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; width: 80mm; margin: 0; padding: 10px; font-size: 12px; }
        .text-center { text-align: center; }
        .divider { border-top: 1px dashed #000; margin: 10px 0; }
        .bold { font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body onload="window.print()">
    <div class="text-center">
        <h3 style="margin:0;">COMPROBANTE DE ABONO</h3>
        <p>Fecha: <?php echo $datos['fecha']; ?></p>
    </div>

    <div class="divider"></div>

    <p><span class="bold">CLIENTE:</span> <?php echo strtoupper($c['nombre']); ?></p>
    <p><span class="bold">CEDULA/RIF:</span> <?php echo $c['cedula_rif']; ?></p>
    <?php if(!empty($c['telefono'])) echo "<p><span class='bold'>TELÉFONO:</span> ".$c['telefono']."</p>"; ?>

    <div class="divider"></div>

    <div class="text-center">
        <p style="font-size: 16px;"><span class="bold">MONTO ABONADO:</span></p>
        <h2 style="margin:5px;">$ <?php echo number_format($datos['monto'], 2); ?></h2>
    </div>

    <div class="divider"></div>

    <p><span class="bold">DEUDA RESTANTE:</span> $ <?php echo number_format($c['deuda_actual'], 2); ?></p>

    <div class="text-center" style="margin-top: 30px;">
        <p>_______________________</p>
        <p>Firma / Sello</p>
        <p style="font-size: 10px;">¡Gracias por su pago!</p>
    </div>

    <button class="no-print" onclick="window.close()" style="margin-top:20px; width:100%; padding:10px; cursor:pointer;">Cerrar Ventana</button>
</body>
</html>