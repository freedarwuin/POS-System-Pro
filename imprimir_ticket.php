<?php
// Asegúrate de haber instalado la librería: composer require mike42/escpos-php
require __DIR__ . '/vendor/autoload.php';
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsPrintConnector;

// 1. Obtener datos del negocio desde la base de datos
$res_c = mysqli_query($conexion, "SELECT * FROM configuracion WHERE id = 1");
$c = mysqli_fetch_assoc($res_c);
$puerto = $c['puerto_impresora'] ?? 'POS-58';

try {
    $connector = new WindowsPrintConnector($puerto);
    $printer = new Printer($connector);

    /* --- ENCABEZADO --- */
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->selectPrintMode(Printer::MODE_DOUBLE_WIDTH);
    $printer->text($c['nombre_negocio'] . "\n");
    $printer->selectPrintMode();
    $printer->text("RIF: " . ($c['rif'] ?? "J-00000000-0") . "\n");
    $printer->text($c['direccion'] . "\n");
    $printer->text("--------------------------------\n");

    /* --- DATOS VENTA --- */
    $printer->setJustification(Printer::JUSTIFY_LEFT);
    $printer->text("FACTURA: #" . str_pad($id_venta, 6, "0", STR_PAD_LEFT) . "\n");
    $printer->text("FECHA: " . date("d/m/Y h:i A") . "\n");
    $printer->text("CLIENTE: " . substr($nombre_cliente, 0, 20) . "\n");
    $printer->text("RIF/CI: " . $rif_cliente . "\n");
    $printer->text("--------------------------------\n");

    /* --- PRODUCTOS --- */
    $printer->text("CANT DESCRIPCION         TOTAL\n");
    foreach ($carrito as $p) {
        $cant = str_pad($p['cantidad'], 4);
        $name = str_pad(substr($p['nombre'], 0, 18), 18);
        $subt = number_format($p['precio'] * $p['cantidad'], 2);
        $printer->text("$cant $name $$subt\n");
    }

    if ($avance_usd > 0) {
        $printer->text("1    AVANCE EFECTIVO      $" . number_format($avance_usd, 2) . "\n");
    }
    $printer->text("--------------------------------\n");

    /* --- CÁLCULOS FISCALES (Normativa 00071) --- */
    $printer->setJustification(Printer::JUSTIFY_RIGHT);
    
    // Suponiendo que el precio ya tiene el IVA incluido (16%)
    $base_imponible = $total_final / 1.16;
    $iva = $total_final - $base_imponible;

    $printer->text("BASE IMPONIBLE: $" . number_format($base_imponible, 2) . "\n");
    $printer->text("IVA (16%): $" . number_format($iva, 2) . "\n");

    // IGTF (3%) si el pago fue en dólares efectivo
    $total_con_impuestos = $total_final;
    if ($metodo_pago === 'Efectivo USD') {
        $igtf = $total_final * 0.03;
        $printer->text("IGTF (3%): $" . number_format($igtf, 2) . "\n");
        $total_con_impuestos += $igtf;
    }

    /* --- TOTALES DUALES --- */
    $printer->selectPrintMode(Printer::MODE_EMPHASIZED);
    $printer->text("TOTAL USD: $" . number_format($total_con_impuestos, 2) . "\n");
    
    $total_bs = $total_con_impuestos * $tasa;
    $printer->text("TOTAL BS: " . number_format($total_bs, 2, ',', '.') . "\n");
    $printer->selectPrintMode();

    /* --- PIE DE TICKET --- */
    $printer->setJustification(Printer::JUSTIFY_CENTER);
    $printer->text("\nTasa BCV: Bs. " . number_format($tasa, 2, ',', '.') . "\n");
    $printer->text("¡Gracias por su compra!\n");

    $printer->cut();
    $printer->close();

} catch (Exception $e) {
    throw new Exception("Error en ticket: " . $e->getMessage());
}