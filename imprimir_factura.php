<?php
session_start();
include 'conexion.php';

if (!isset($_GET['id'])) {
    exit("ID de venta no proporcionado");
}

$id_venta = (int)$_GET['id'];

// 1. OBTENER DATOS DE LA VENTA
$sql_venta = "SELECT * FROM ventas WHERE id = $id_venta";
$res_venta = mysqli_query($conexion, $sql_venta);
$venta = mysqli_fetch_assoc($res_venta);

if (!$venta) { exit("Venta no encontrada"); }

// 2. OBTENER DATOS DE CONFIGURACIÓN Y PARÁMETROS FISCALES
$query_config = mysqli_query($conexion, "SELECT * FROM configuracion WHERE id = 1");
$config = mysqli_fetch_assoc($query_config);

$nombre_empresa = $config['nombre_negocio'] ?? "Mi Empresa C.A.";
$rif_empresa = $config['rif'] ?? "J-00000000-0";
$direccion_empresa = $config['direccion'] ?? "Dirección Fiscal del Negocio";
$mensaje_pie = $config['mensaje_ticket'] ?? "*** GRACIAS POR SU COMPRA ***";

// Parámetros globales de impuestos de la configuración
$usar_iva_global = $config['usar_iva'] ?? 1;
$porcentaje_iva = (float)($config['impuesto_iva'] ?? 16.00);
$usar_igtf_global = $config['usar_igtf'] ?? 0;
$porcentaje_igtf = (float)($config['impuesto_igtf'] ?? 3.00);

// Tasa de cambio BCV
$tasa = (float)($venta['tasa_momento'] ?? $venta['tasa'] ?? $config['tasa_dolar'] ?? 1);
if ($tasa <= 0) { $tasa = 1; }

// Número de Control fiscal (Si la BD no lo tiene, se genera un formato estándar basado en el ID)
$nro_control = $venta['nro_control'] ?? str_pad($id_venta, 8, "0", STR_PAD_LEFT);
$nro_control_formateado = "00-" . $nro_control;

// 3. DETALLES DE PRODUCTOS
$sql_detalles = "SELECT * FROM facturas_estaticas_items WHERE id_venta = $id_venta";
$res_detalles = mysqli_query($conexion, $sql_detalles);

if (!$res_detalles || mysqli_num_rows($res_detalles) == 0) {
    $sql_detalles = "SELECT dv.*, p.nombre as descripcion, p.usa_parametros_fiscales FROM detalle_ventas dv 
                     LEFT JOIN productos p ON dv.id_producto = p.id 
                     WHERE dv.id_venta = $id_venta";
    $res_detalles = mysqli_query($conexion, $sql_detalles);
}

// 4. CÁLCULOS FISCALES (BASE, IVA, IGTF)
$subtotal_general_usd = 0;
$base_imponible_usd = 0;
$monto_iva_usd = 0;
$items_array = [];

while ($item = mysqli_fetch_assoc($res_detalles)) {
    $nombre_p = $item['descripcion'] ?? $item['nombre'] ?? "Producto";
    $cantidad = (float)$item['cantidad'];
    
    // Precio unitario en USD
    $precio_unit_usd = isset($item['precio']) ? (float)$item['precio'] : 0;
    if ($precio_unit_usd == 0 && isset($item['total_linea_bs'])) {
        $precio_unit_usd = ((float)$item['total_linea_bs'] / $tasa) / $cantidad;
    }

    $total_linea_usd = $precio_unit_usd * $cantidad;
    $subtotal_general_usd += $total_linea_usd;

    // Verificar si el producto aplica parámetros fiscales (IVA)
    $aplica_iva_producto = isset($item['usa_parametros_fiscales']) ? (int)$item['usa_parametros_fiscales'] : 1;

    if ($usar_iva_global == 1 && $aplica_iva_producto == 1) {
        $base_imponible_usd += $total_linea_usd;
    }

    $items_array[] = [
        'cantidad' => $cantidad,
        'descripcion' => $nombre_p,
        'precio_unit' => $precio_unit_usd,
        'total_linea' => $total_linea_usd,
        'aplica_iva' => $aplica_iva_producto
    ];
}

// Cálculo del IVA
if ($usar_iva_global == 1) {
    $monto_iva_usd = $base_imponible_usd * ($porcentaje_iva / 100);
}

$total_con_iva_usd = $subtotal_general_usd + ($monto_iva_usd > 0 ? $monto_iva_usd : 0);

// Cálculo del IGTF (Aplica si el método de pago involucra divisas / efectivo USD / Zelle / etc.)
$metodo_pago = strtolower($venta['metodo_pago'] ?? '');
$es_divisa = (strpos($metodo_pago, 'divisa') !== false || strpos($metodo_pago, 'zelle') !== false || strpos($metodo_pago, 'efectivo $') !== false || strpos($metodo_pago, 'dolar') !== false);

$monto_igtf_usd = 0;
if (($usar_igtf_global == 1 || $es_divisa) && $es_divisa) {
    $monto_igtf_usd = $total_con_iva_usd * ($porcentaje_igtf / 100);
}

$total_final_usd = $total_con_iva_usd + $monto_igtf_usd;

// Conversión a Bolívares (VES) a la tasa BCV
$base_imponible_bs = $base_imponible_usd * $tasa;
$monto_iva_bs = $monto_iva_usd * $tasa;
$monto_igtf_bs = $monto_igtf_usd * $tasa;
$total_final_bs = $total_final_usd * $tasa;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Factura Fiscal #<?php echo $id_venta; ?></title>
    <style>
        @page { size: auto; margin: 0; }
        body { 
            width: 72mm; margin: 0 auto; padding: 5px;
            font-family: 'Courier New', Courier, monospace; 
            font-size: 11px; color: #000; line-height: 1.2;
        }
        .header { text-align: center; margin-bottom: 5px; }
        .header h1 { font-size: 13px; margin: 2px 0; text-transform: uppercase; }
        .header p { margin: 2px 0; font-size: 9px; }
        .info-fiscal { font-size: 9px; margin-bottom: 5px; border-bottom: 1px dashed #000; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-top: 5px; }
        th { border-bottom: 1px dashed #000; text-align: left; font-size: 9px; padding-bottom: 2px; }
        td { padding: 2px 0; vertical-align: top; font-size: 9px; } 
        .text-right { text-align: right; }
        .totales-section { margin-top: 5px; border-top: 1px dashed #000; padding-top: 5px; font-size: 10px; }
        .total-row { display: flex; justify-content: space-between; margin-bottom: 2px; }
        .total-final { font-weight: bold; font-size: 12px; border-top: 1px solid #000; border-bottom: 1px solid #000; padding: 4px 0; margin-top: 4px; }
        hr { border: none; border-top: 1px dashed #000; margin: 5px 0; }
        .footer { text-align: center; margin-top: 8px; font-size: 9px; padding-bottom: 5px; }
        @media print { .no-print { display: none; } body { width: 72mm; } }
    </style>
</head>
<body>

    <div class="no-print" style="background: #f1f5f9; padding: 10px; text-align: center; margin-bottom: 15px; border-radius: 8px; font-family: sans-serif;">
        <button onclick="window.print()" style="padding: 8px 15px; cursor: pointer; background: #10b981; color: white; border: none; border-radius: 4px; font-weight: bold;">Imprimir Factura (F12)</button>
        <button onclick="window.close()" style="padding: 8px 15px; cursor: pointer; background: #64748b; color: white; border: none; border-radius: 4px; font-weight: bold;">Cerrar</button>
    </div>

    <div class="header">
        <h1><?php echo htmlspecialchars($nombre_empresa); ?></h1>
        <p><b>RIF:</b> <?php echo htmlspecialchars($rif_empresa); ?></p>
        <p><?php echo htmlspecialchars($direccion_empresa); ?></p>
    </div>

    <div class="info-fiscal">
        <b>FACTURA NRO:</b> #<?php echo str_pad($id_venta, 8, "0", STR_PAD_LEFT); ?><br>
        <b>NRO. CONTROL:</b> <?php echo $nro_control_formateado; ?><br>
        <b>FECHA/HORA:</b> <?php echo date('d/m/Y h:i A', strtotime($venta['fecha'])); ?><br>
        <b>CLIENTE:</b> <?php echo htmlspecialchars($venta['cliente'] ?? 'CONTRIBUYENTE / CONSUMIDOR FINAL'); ?><br>
        <b>MÉTODO PAGO:</b> <?php echo htmlspecialchars($venta['metodo_pago']); ?><br>
        <!--<b>TASA BCV:</b> Bs. <?php echo number_format($tasa, 2, ',', '.'); ?>!-->
    </div>

    <table>
        <thead>
            <tr>
                <th width="12%">CANT</th>
                <th width="48%">DESCRIPCIÓN</th>
                <th width="15%">IVA</th>
                <th width="25%" class="text-right">TOTAL BS</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items_array as $item): 
                $t_linea_bs = $item['total_linea'] * $tasa;
                $alicuota_txt = ($usar_iva_global == 1 && $item['aplica_iva'] == 1) ? "16%" : "E";
            ?>
            <tr>
                <td><?php echo number_format($item['cantidad'], 2, ',', '.'); ?></td>
                <td><?php echo htmlspecialchars($item['descripcion']); ?></td>
                <td><?php echo $alicuota_txt; ?></td>
                <td class="text-right"><?php echo number_format($t_linea_bs, 2, ',', '.'); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="totales-section">
        <div class="total-row">
            <span>SUBTOTAL:</span>
            <span>Bs. <?php echo number_format($subtotal_general_usd * $tasa, 2, ',', '.'); ?></span>
        </div>
       <!-- <div class="total-row">
            <span>BASE IMPONIBLE (16%):</span>
            <span>Bs. <?php echo number_format($base_imponible_bs, 2, ',', '.'); ?></span>
        </div>!-->
        <div class="total-row">
            <span>IVA (<?php echo number_format($porcentaje_iva, 0); ?>%):</span>
            <span>Bs. <?php echo number_format($monto_iva_bs, 2, ',', '.'); ?></span>
        </div>
        
        <?php if ($monto_igtf_bs > 0): ?>
        <div class="total-row" style="color: #b91c1c; font-weight: bold;">
            <span>IGTF (<?php echo number_format($porcentaje_igtf, 1); ?>% Divisas):</span>
            <span>Bs. <?php echo number_format($monto_igtf_bs, 2, ',', '.'); ?></span>
        </div>
        <?php endif; ?>

        <div class="total-row total-final">
            <span>TOTAL A PAGAR:</span>
            <span>Bs. <?php echo number_format($total_final_bs, 2, ',', '.'); ?></span>
        </div>
        <!--<div style="text-align: right; font-size: 9px; margin-top: 2px; color: #555;">
            Equivalente: $ <?php echo number_format($total_final_usd, 2); ?> USD
        </div>!-->
    </div>

    <div class="footer">
        <p><?php echo htmlspecialchars($mensaje_pie); ?></p>
        <p>Vendedor(a): <?php echo htmlspecialchars($venta['usuario'] ?? 'Admin'); ?></p>
        <p style="font-size: 8px; margin-top: 5px;">¡Cúmplase con la Providencia Administrativa del SENIAT!</p>
    </div>

    <script>
        window.onload = function() {
            window.print();
            setTimeout(function() { window.close(); }, 1000);
        };
    </script>
</body>
</html>