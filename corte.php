<?php
session_start();
include 'conexion.php';

// PERMITIR TANTO A ADMIN COMO A CAJERO VER EL CORTE
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

// 1. CAPTURAR FILTROS
$fecha_filtro = $_GET['fecha'] ?? date('Y-m-d');
$usuario_filtro = $_GET['usuario_nombre'] ?? ''; 

// 2. CONSTRUIR CONDICIÓN SQL
$where = "WHERE DATE(v.fecha) = '$fecha_filtro' AND v.estado = 'PAGADA'";
if ($usuario_filtro !== '') {
    $where .= " AND v.usuario = '$usuario_filtro'";
}

// 3. OBTENER TASA ACTUAL
$res_config = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion LIMIT 1");
$tasa_actual = mysqli_fetch_assoc($res_config)['tasa_dolar'] ?? 1;

// 4. FONDO DE INICIO REAL (USD y BS)
$fondo_inicial_bs = 0;
$fondo_inicial_usd = 0;

if ($usuario_filtro !== '') {
    $res_u = mysqli_query($conexion, "SELECT id FROM usuarios WHERE username = '$usuario_filtro'");
    $user_data = mysqli_fetch_assoc($res_u);
    if($user_data){
        $id_u = $user_data['id'];
        $res_f = mysqli_query($conexion, "SELECT monto_bs, monto_usd FROM aperturas_caja WHERE id_usuario = '$id_u' AND DATE(fecha_apertura) = '$fecha_filtro' ORDER BY id DESC LIMIT 1");
        $fondos = mysqli_fetch_assoc($res_f);
        if ($fondos) {
            $fondo_inicial_bs = (float)$fondos['monto_bs'];
            $fondo_inicial_usd = (float)$fondos['monto_usd'];
        }
    }
}

// 5. INICIALIZACIÓN DE VARIABLES
$usd_efec = 0;
$bs_efec = 0;
$usd_credito = 0;
$bs_credito = 0;
$digitales_data = [];
$total_usd_bruto = 0;
$total_transacciones = 0;

// VARIABLES EXCLUSIVAS PARA EL DESGLOSE DE CASHEA
$total_cashea_financiado_usd = 0;
$total_cashea_financiado_bs = 0;
$iniciales_cashea_data = [
    'Efectivo $' => 0,
    'Pago Móvil' => 0,
    'Punto' => 0,
    'Efectivo Bs.' => 0
];

// 6. PROCESAMIENTO CON LEFT JOIN HACIA LA TABLA DE CASHEA
$query_v = "SELECT v.*, vc.monto_financiado, vc.monto_inicial, vc.monto_recargo 
            FROM ventas v 
            LEFT JOIN ventas_cashea vc ON v.id = vc.id_venta 
            $where";
$res_v = mysqli_query($conexion, $query_v);

while ($v = mysqli_fetch_assoc($res_v)) {
    $total_transacciones++;
    
    $metodo = trim($v['metodo_pago']);
    $tasa_vta = (float)$v['tasa_momento'] > 0 ? (float)$v['tasa_momento'] : $tasa_actual;
    
    // Total bruto de la venta facturada
    $total_vta_usd = (float)$v['total'];

    // Captura de montos
    $p_efec_usd = (float)$v['pago_efectivo_usd'];
    $p_efec_bs  = (float)$v['pago_efectivo_bs'];
    $p_punto    = (float)$v['pago_punto_bs'];
    $p_movil    = (float)$v['pago_movil_bs'];
    $p_biopago  = (float)($v['pago_biopago_bs'] ?? 0);
    
    $vuelto_bs  = (float)$v['cambio_bs'];
    $vuelto_usd = (float)$v['cambio_usd'];

    // ¿Es una venta registrada bajo el Plan Cashea?
    $es_cashea = ($metodo === 'Cashea' || (isset($v['monto_financiado']) && (float)$v['monto_financiado'] > 0));
    
    if ($es_cashea) {
        $monto_finan_usd = (float)($v['monto_financiado'] ?? 0);
        
        // Acumular lo pendiente (Financiado)
        $total_cashea_financiado_usd += $monto_finan_usd;
        $total_cashea_financiado_bs += ($monto_finan_usd * $tasa_vta);

        // AISLAMIENTO CONTABLE: Restamos del cómputo total lo financiado
        $total_vta_usd = $total_vta_usd - $monto_finan_usd;

        // Clasificar qué método se usó para pagar la inicial
        if ($p_efec_usd > 0) $iniciales_cashea_data['Efectivo $'] += $p_efec_usd;
        if ($p_efec_bs > 0)  $iniciales_cashea_data['Efectivo Bs.'] += $p_efec_bs;
        if ($p_punto > 0)    $iniciales_cashea_data['Punto'] += ($p_punto / $tasa_vta);
        if ($p_movil > 0)    $iniciales_cashea_data['Pago Móvil'] += ($p_movil / $tasa_vta);
    }

    // --- REPARACIONES DE SEGURIDAD ---
    if (!$es_cashea && $p_efec_usd == 0 && $p_efec_bs == 0 && $p_punto == 0 && $p_movil == 0 && $p_biopago == 0) {
        if ($metodo == 'Dolares $' || $metodo == 'Efectivo $') {
            $p_efec_usd = $total_vta_usd;
        } elseif ($metodo == 'Efectivo Bs.') {
            $p_efec_bs = $total_vta_usd * $tasa_vta;
        } elseif ($metodo == 'Punto' || $metodo == 'Punto de Venta') { 
            $p_punto = $total_vta_usd * $tasa_vta; 
        } elseif ($metodo == 'Pago Móvil') { 
            $p_movil = $total_vta_usd * $tasa_vta; 
        } elseif ($metodo == 'BioPago') {
            $p_biopago = $total_vta_usd * $tasa_vta;
        }
    }

    // DESCUENTO DE VUELTOS
    if ($vuelto_bs > 0) {
        if ($p_efec_bs >= $vuelto_bs) {
            $p_efec_bs -= $vuelto_bs;
        } else {
            $restante_vuelto = $vuelto_bs - $p_efec_bs;
            $p_efec_bs = 0;
            if ($p_movil >= $restante_vuelto) {
                $p_movil -= $restante_vuelto;
            }
        }
    }

    if ($vuelto_usd > 0) {
        $p_efec_usd = max(0, $p_efec_usd - $vuelto_usd);
    }

    // SUMAS NETAS DIRECTAS AL FLUJO REAL DEL DÍA (CADA MÉTODO EN SU RESPECTIVA CUENTA)
    $usd_efec += $p_efec_usd;
    $bs_efec  += $p_efec_bs; 

    if ($p_punto > 0) {
        $digitales_data['Punto de Venta']['monto'] = ($digitales_data['Punto de Venta']['monto'] ?? 0) + $p_punto;
        $digitales_data['Punto de Venta']['cant'] = ($digitales_data['Punto de Venta']['cant'] ?? 0) + 1;
    }
    if ($p_movil > 0) {
        $digitales_data['Pago Móvil']['monto'] = ($digitales_data['Pago Móvil']['monto'] ?? 0) + $p_movil;
        $digitales_data['Pago Móvil']['cant'] = ($digitales_data['Pago Móvil']['cant'] ?? 0) + 1;
    }
    if ($p_biopago > 0) {
        $digitales_data['BioPago']['monto'] = ($digitales_data['BioPago']['monto'] ?? 0) + $p_biopago;
        $digitales_data['BioPago']['cant'] = ($digitales_data['BioPago']['cant'] ?? 0) + 1;
    }

    if ($metodo == 'Crédito') {
        $usd_credito += $total_vta_usd;
        $bs_credito += ($total_vta_usd * $tasa_vta);
    } 
    // IGNORAMOS 'Multiple' Y 'Pago Múltiple' PARA EVITAR MOSTRAR ESA CATEGORÍA
    elseif (!in_array($metodo, ['Dolares $', 'Efectivo $', 'Efectivo Bs.', 'Múltiple', 'Multiple', 'Pago Múltiple', 'Pago Multiple', 'Pago Móvil', 'Punto', 'Punto de Venta', 'Cashea', 'BioPago'])) {
        $digitales_data[$metodo]['monto'] = ($digitales_data[$metodo]['monto'] ?? 0) + ($total_vta_usd * $tasa_vta);
        $digitales_data[$metodo]['cant'] = ($digitales_data[$metodo]['cant'] ?? 0) + 1;
    }

    // Suma neta final percibida EN VENTAS (No incluye los fondos de apertura)
    $total_vta_neta_usd = $p_efec_usd + (($p_efec_bs + $p_punto + $p_movil + $p_biopago) / $tasa_vta);
    $total_usd_bruto += $total_vta_neta_usd;
}

$total_digitales_sum = 0;
foreach($digitales_data as $key => $d) { 
    $total_digitales_sum += $d['monto']; 
}

// Ventas netas totales expresadas en bolívares
$total_bs_bruto = $bs_efec + ($usd_efec * $tasa_actual) + $total_digitales_sum;

/// ==========================================
// AUTOMATIZACIÓN DE NOTIFICACIÓN WHATSAPP
// ==========================================
$config_q = mysqli_query($conexion, "SELECT whatsapp_telefono, whatsapp_api_key, whatsapp_instancia FROM configuracion WHERE id = 1");
$cfg = mysqli_fetch_assoc($config_q);
$instancia = !empty($cfg['whatsapp_instancia']) ? $cfg['whatsapp_instancia'] : 'instance183682';

if ($total_transacciones > 0 && !empty($cfg['whatsapp_telefono']) && !empty($cfg['whatsapp_api_key'])) {
    
    $usuario_cierre = $usuario_filtro ?: 'General (Todos)';
    
    $mensaje_whatsapp = "📊 *RESUMEN DE CORTE DE CAJA* 📊\n\n";
    $mensaje_whatsapp .= "📅 *Fecha:* " . date('d/m/Y h:i A') . "\n";
    $mensaje_whatsapp .= "👤 *Cajero:* " . $usuario_cierre . "\n";
    $mensaje_whatsapp .= "📈 *Tasa del Día:* " . number_format($tasa_actual, 2, ',', '.') . " Bs\n";
    $mensaje_whatsapp .= "🔢 *Ventas Totales:* " . $total_transacciones . " transacciones\n";
    $mensaje_whatsapp .= "----------------------------------\n";
    
    if ($fondo_inicial_usd > 0 || $fondo_inicial_bs > 0) {
        $mensaje_whatsapp .= "🏁 *FONDOS DE APERTURA (INICIAL):*\n";
        $mensaje_whatsapp .= "• Inicial USD: $" . number_format($fondo_inicial_usd, 2, ',', '.') . "\n";
        $mensaje_whatsapp .= "• Inicial Bs:  Bs. " . number_format($fondo_inicial_bs, 2, ',', '.') . "\n";
        $mensaje_whatsapp .= "----------------------------------\n";
    }

    $mensaje_whatsapp .= "📥 *INGRESOS NETOS POR VENTAS:*\n";
    $mensaje_whatsapp .= "💵 *Efectivo Dólares:* $" . number_format($usd_efec, 2, ',', '.') . "\n";
    $mensaje_whatsapp .= "🇻🇪 *Efectivo Bolívares:* Bs. " . number_format($bs_efec, 2, ',', '.') . "\n";
    
    if (!empty($digitales_data)) {
        $mensaje_whatsapp .= "\n💳 *Ingresos Digitales:* \n";
        foreach($digitales_data as $m_nombre => $info) {
            $mensaje_whatsapp .= "• " . $m_nombre . ": Bs. " . number_format($info['monto'], 2, ',', '.') . " (" . $info['cant'] . ")\n";
        }
    }
    
    if ($total_cashea_financiado_usd > 0) {
        $mensaje_whatsapp .= "\n⚡ *Plan Cashea (Financiado):* \n";
        $mensaje_whatsapp .= "• Por Cobrar: $" . number_format($total_cashea_financiado_usd, 2, ',', '.') . " / Bs. " . number_format($total_cashea_financiado_bs, 2, ',', '.') . "\n";
    }

    if ($usd_credito > 0) {
        $mensaje_whatsapp .= "\n⏳ *Cuentas por Cobrar (Créditos):* \n";
        $mensaje_whatsapp .= "• Total: $" . number_format($usd_credito, 2, ',', '.') . "\n";
    }
    
    $mensaje_whatsapp .= "----------------------------------\n";
    
    if ($fondo_inicial_usd > 0 || $fondo_inicial_bs > 0) {
        $mensaje_whatsapp .= "🧮 *TOTAL ESPERADO EN FÍSICO (Ventas + Fondo):*\n";
        $mensaje_whatsapp .= "👉 *En efectivo USD:* $" . number_format($usd_efec + $fondo_inicial_usd, 2, ',', '.') . "\n";
        $mensaje_whatsapp .= "👉 *En efectivo Bs:* Bs. " . number_format($bs_efec + $fondo_inicial_bs, 2, ',', '.') . "\n";
        $mensaje_whatsapp .= "----------------------------------\n";
    }

    $mensaje_whatsapp .= "💰 *TOTAL DE VENTAS NETAS REALES*\n";
    $mensaje_whatsapp .= "👉 *En USD:* $" . number_format($total_usd_bruto, 2, ',', '.') . "\n";
    $mensaje_whatsapp .= "👉 *En Bs:* Bs. " . number_format($total_bs_bruto, 2, ',', '.') . "\n";
    $mensaje_whatsapp .= "----------------------------------\n";
    $mensaje_whatsapp .= "🔒 _Corte de caja procesado con éxito._";

    $telefono_limpio = ltrim($cfg['whatsapp_telefono'], '+');
    $params = array(
        'token' => $cfg['whatsapp_api_key'],
        'to' => '+' . $telefono_limpio,
        'body' => $mensaje_whatsapp
    );

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => "https://api.ultramsg.com/" . $instancia . "/messages/chat",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => "",
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => "POST",
        CURLOPT_POSTFIELDS => http_build_query($params),
        CURLOPT_HTTPHEADER => array("content-type: application/x-www-form-urlencoded"),
    ));
    $response = curl_exec($curl);
    curl_close($curl);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Corte de Caja | POS</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&family=JetBrains+Mono:wght@600&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #1e293b; --accent: #10b981; --bg: #f8fafc; --border: #e2e8f0; --warning: #f59e0b; --cashea: #8b5cf6; }
        body { font-family: 'Inter', sans-serif; background: var(--bg); color: #334155; margin: 0; padding: 0; font-size: 15px; }
        .wrapper-layout { display: flex; min-height: 100vh; }
        .sidebar { width: 260px; background-color: #1e293b; color: #f8fafc; padding: 25px 20px; flex-shrink: 0; display: flex; flex-direction: column; }
        .sidebar h2 { font-size: 18px; font-weight: 700; color: #f1f5f9; text-align: center; margin-bottom: 25px; letter-spacing: 0.5px; }
        .sidebar nav { display: flex; flex-direction: column; gap: 4px; }
        .sidebar .nav-link { display: flex; align-items: center; color: #94a3b8; padding: 10px 14px; border-radius: 8px; text-decoration: none; font-size: 14px; font-weight: 500; transition: all 0.2s ease; }
        .sidebar .nav-link:hover { background-color: #334155; color: #f8fafc; }
        .sidebar .nav-link.active { background-color: #3b82f6; color: #ffffff; font-weight: 600; }
        .main-content { flex-grow: 1; overflow-x: hidden; padding: 30px 20px; }
        .container { max-width: 1000px; margin: auto; }
        .no-print { display: flex; gap: 10px; margin-bottom: 20px; align-items: center; }
        .btn { padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; font-size: 13px; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 6px; transition: 0.2s; }
        .btn-main { background: var(--primary); color: white; }
        .report-card { background: white; border-radius: 16px; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); overflow: hidden; }
        .report-header { padding: 25px 35px; border-bottom: 1px solid var(--bg); display: flex; justify-content: space-between; align-items: center; }
        .filter-section { padding: 20px 35px; background: #fafafa; display: flex; gap: 20px; align-items: flex-end; border-bottom: 1px solid var(--border); }
        .form-control { padding: 10px; border-radius: 8px; border: 1.5px solid var(--border); font-size: 14px; width: 100%; }
        .main-panel-body { padding: 30px 35px; }
        .stats-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px; margin-bottom: 30px; }
        .stat-box { padding: 20px; border-radius: 12px; border: 1px solid var(--border); background: #fff; }
        .stat-box h3 { margin: 0 0 15px 0; font-size: 11px; font-weight: 800; color: #94a3b8; text-transform: uppercase; }
        .amount { font-family: 'JetBrains Mono'; font-size: 24px; font-weight: 700; color: var(--primary); display: block; }
        .fondo-box { margin-top: 10px; padding: 10px; background: #f8fafc; border-radius: 8px; font-size: 12px; border-left: 4px solid var(--accent); }
        .cashea-box { border-top: 4px solid var(--cashea); background: #faf9ff; }
        .cashea-box h3 { color: var(--cashea); }
        .total-banner { background: var(--primary); color: white; padding: 30px; border-radius: 16px; display: flex; justify-content: space-between; align-items: center; }
        .big-amount { font-family: 'JetBrains Mono'; font-size: 40px; font-weight: 700; color: var(--accent); }
        @media print { 
            .no-print, .filter-section, .sidebar { display: none !important; } 
            body { padding: 0; background: white; } 
            .main-content { padding: 0; width: 100%; }
            .report-card { border: none; box-shadow: none; } 
        }
    </style>
</head>
<body>

<div class="wrapper-layout">
    <aside class="sidebar no-print">
        <h2>POS SYSTEM PRO</h2>
        <nav>
            <a href="ventas.php" class="nav-link">🛒 Ventas</a>
            <a href="inventario.php" class="nav-link">📦 Inventario</a>
            <a href="corte.php" class="nav-link active">📊 Corte</a>
            <a href="estadisticas.php" class="nav-link">📊 Estadisticas</a>
            <a href="historial.php" class="nav-link">📋 HISTORIAL</a>
            <a href="historial_facturas.php" class="nav-link">📋 FACTURAS</a>
            <a href="modulo_reporte_stock.php" class="nav-link">📋 Reporte_stock</a>
            <a href="creditos.php" class="nav-link">💳 Créditos</a>
            <a href="mercancia_departamentos.php" class="nav-link">📁 Departamentos</a>
            <a href="reporte_inventario.php" class="nav-link">📁 Reporte Inventario</a>
            <a href="configuracion.php" class="nav-link">⚙️ Ajustes</a>
            <a href="logout.php" class="nav-link" style="margin-top: 20px; color: #ff5252; font-weight: 600; border-top: 1px solid #333; padding-top: 15px; border-radius: 0;">🚪 Salir</a>
        </nav>
    </aside>

    <main class="main-content">
        <div class="container">
            <div class="no-print">
                <button onclick="window.print()" class="btn btn-main" style="margin-left: auto;">🖨️ Imprimir Corte</button>
            </div>

            <div class="report-card">
                <div class="report-header">
                    <div>
                        <h1 style="margin: 0;">Reporte de Corte</h1>
                        <p>Cajero: <b><?php echo $usuario_filtro ?: 'General'; ?></b> | Fecha: <b><?php echo date('d/m/Y', strtotime($fecha_filtro)); ?></b></p>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 11px; font-weight: 800; color: #94a3b8;">TASA ACTUAL</span><br>
                        <b style="font-size: 1.5rem; color: var(--accent);"><?php echo number_format($tasa_actual, 2, ',', '.'); ?> Bs</b>
                    </div>
                </div>

                <form class="filter-section no-print" method="GET">
                    <div style="flex: 1.5;">
                        <select name="usuario_nombre" class="form-control">
                            <option value="">-- Todos los Usuarios --</option>
                            <?php 
                            $u_query = mysqli_query($conexion, "SELECT username FROM usuarios");
                            while($u = mysqli_fetch_assoc($u_query)) {
                                $selected = ($u['username'] == $usuario_filtro) ? 'selected' : '';
                                echo "<option value='{$u['username']}' $selected>{$u['username']}</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div style="flex: 1;">
                        <input type="date" name="fecha" value="<?php echo $fecha_filtro; ?>" class="form-control">
                    </div>
                    <button type="submit" class="btn btn-main">FILTRAR</button>
                </form>

                <div class="main-panel-body">
                    <?php if ($total_transacciones > 0): ?>
                        <div class="stats-grid">
                            <div class="stat-box">
                                <h3>💵 Efectivo Neto (Vendido)</h3>
                                <small>DÓLARES EXP. ($)</small>
                                <span class="amount" style="color: var(--accent);">$ <?php echo number_format($usd_efec, 2, ',', '.'); ?></span>
                                
                                <?php if($fondo_inicial_usd > 0): ?>
                                <div class="fondo-box">
                                    Fondo Inicial: <b>$ <?php echo number_format($fondo_inicial_usd, 2, ',', '.'); ?></b><br>
                                    Total Esperado: <b style="color:var(--accent);">$ <?php echo number_format($fondo_inicial_usd + $usd_efec, 2, ',', '.'); ?></b>
                                </div>
                                <?php endif; ?>
                                <br>
                                <small>BOLÍVARES EXP. (BS)</small>
                                <span class="amount" style="font-size: 20px;">Bs. <?php echo number_format($bs_efec, 2, ',', '.'); ?></span>
                                 
                                <?php if($fondo_inicial_bs > 0): ?>
                                <div class="fondo-box">
                                    Fondo Inicial: <b>Bs. <?php echo number_format($fondo_inicial_bs, 2, ',', '.'); ?></b><br>
                                    Total Esperado: <b>Bs. <?php echo number_format($fondo_inicial_bs + $bs_efec, 2, ',', '.'); ?></b>
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="stat-box">
                                <h3>💳 Bancos / Digital</h3>
                                <?php 
                                ksort($digitales_data);
                                if (!empty($digitales_data)):
                                    foreach($digitales_data as $metodo_nombre => $info): 
                                    ?>
                                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding: 5px 0;">
                                            <span style="font-size: 14px;"><?php echo $metodo_nombre; ?> (<?php echo $info['cant']; ?>)</span>
                                            <b style="font-family: 'JetBrains Mono';">Bs. <?php echo number_format($info['monto'], 2, ',', '.'); ?></b>
                                        </div>
                                    <?php 
                                    endforeach;
                                else:
                                    echo "<p style='color: #94a3b8; font-size: 13px;'>No hay ingresos digitales.</p>";
                                endif;
                                ?>
                            </div>

                            <div class="stat-box" style="border-left: 4px solid var(--warning);">
                                <h3>⏳ Cuentas por Cobrar</h3>
                                <small>CRÉDITOS EN $</small>
                                <span class="amount" style="color: var(--warning);">$ <?php echo number_format($usd_credito, 2, ',', '.'); ?></span>
                                <br>
                                <small>EQUIVALENTE EN BS</small>
                                <span class="amount" style="font-size: 20px;">Bs. <?php echo number_format($bs_credito, 2, ',', '.'); ?></span>
                            </div>
                        </div>

                        <div class="stat-box cashea-box" style="margin-bottom: 30px;">
                            <h3>⚡ Resumen General Plan Cashea</h3>
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px;">
                                <div>
                                    <small style="font-weight: bold; color: #6b7280;">MONTO POR COBRAR FINANCIADO (NO SUMADO AL TOTAL)</small>
                                    <span class="amount" style="color: var(--cashea); font-size: 28px;">$ <?php echo number_format($total_cashea_financiado_usd, 2, ',', '.'); ?></span>
                                    <span style="font-size: 14px; color: #7c3aed; font-family: 'JetBrains Mono'; font-weight: bold;">Bs. <?php echo number_format($total_cashea_financiado_bs, 2, ',', '.'); ?></span>
                                </div>
                                <div>
                                    <small style="font-weight: bold; color: #6b7280;">MÉTODOS DE PAGO USADOS EN LAS INICIALES</small>
                                    <?php foreach ($iniciales_cashea_data as $m_inicial => $monto_i): ?>
                                        <div style="display: flex; justify-content: space-between; border-bottom: 1px solid #eef2f6; padding: 4px 0; font-size: 13px;">
                                            <span>Inicial vía <?php echo $m_inicial; ?></span>
                                            <b style="font-family: 'JetBrains Mono'; color: #34d399;">
                                                $ <?php echo number_format($monto_i, 2, ',', '.'); ?>
                                            </b>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                        <div class="total-banner">
                            <div>
                                <small>TOTAL LÍQUIDO REAL EN CAJA ($)</small>
                                <div class="big-amount">$ <?php echo number_format($total_usd_bruto, 2, ',', '.'); ?></div>
                            </div>
                            <div style="text-align: right;">
                                <small>TOTAL LÍQUIDO REAL EN BOLÍVARES</small>
                                <div style="font-family: 'JetBrains Mono'; font-size: 28px; font-weight: 700;">
                                    Bs. <?php echo number_format($total_bs_bruto, 2, ',', '.'); ?>
                                </div>
                                <p style="margin: 5px 0 0 0; opacity: 0.8; font-size: 12px;">
                                    <?php echo $total_transacciones; ?> ventas reales registradas
                                </p>
                            </div>
                        </div>
                    <?php else: ?>
                        <div style="text-align: center; padding: 50px; color: #cbd5e1;">
                            <h2>No hay movimientos en esta fecha</h2>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>
</div>
</body>
</html>