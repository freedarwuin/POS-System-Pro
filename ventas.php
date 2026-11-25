<?php
session_start();
include 'validar_licencia.php'; 
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}
include 'conexion.php'; 
date_default_timezone_set('America/Caracas');

// 1. Configuración, Tasa e Impuestos Globales
$res_config = mysqli_query($conexion, "SELECT * FROM configuracion WHERE id = 1");
$config = mysqli_fetch_assoc($res_config);
$tasa = (float)($config['tasa_dolar'] ?? 1); 

$usar_iva_global = (int)($config['usar_iva'] ?? 0);
$porcentaje_iva = (float)($config['impuesto_iva'] ?? 16.00);
$usar_igtf_global = (int)($config['usar_igtf'] ?? 0);
$porcentaje_igtf = (float)($config['impuesto_igtf'] ?? 3.00);

// Cargar métodos de pago activos
$res_metodos = mysqli_query($conexion, "SELECT * FROM metodos_pago WHERE activo = 1 ORDER BY id ASC");

// Cargar datos receptor de Pago Móvil
$res_pm_config = mysqli_query($conexion, "SELECT * FROM configuracion_pagomovil LIMIT 1");
$datos_pm = mysqli_fetch_assoc($res_pm_config);
if (!$datos_pm) {
    $datos_pm = ['banco' => 'No Configurado', 'telefono' => 'N/A', 'cedula_rif' => 'N/A'];
}

// 2. Cargar Productos (Incluye indicador de parámetros fiscales)
$query_productos = "SELECT id, nombre, precio, stock, codigo_barras, unidad_medida, moneda, usa_parametros_fiscales FROM productos WHERE stock > 0 ORDER BY nombre ASC";
$res_prods = mysqli_query($conexion, $query_productos);
$productos_lista = [];

if($res_prods){
    while($p = mysqli_fetch_assoc($res_prods)){
        $moneda = isset($p['moneda']) ? $p['moneda'] : 'USD';
        $precio_bd = (float)$p['precio'];
        
        if ($moneda === 'VES') {
            $precio_usd_calculado = $precio_bd / $tasa;
            $precio_bs_fijo = $precio_bd;
        } else {
            $precio_usd_calculado = $precio_bd;
            $precio_bs_fijo = $precio_bd * $tasa; 
        }

        $productos_lista[] = [
            'id' => $p['id'],
            'nombre' => preg_replace('/[^a-zA-Z0-9\s.\-\/]/', '', $p['nombre']),
            'precio' => $precio_usd_calculado, 
            'moneda' => $moneda, 
            'stock' => (float)$p['stock'],
            'codigo' => (string)$p['codigo_barras'],
            'unidad' => strtolower($p['unidad_medida'] ?? ''),
            'usa_fiscal' => (int)($p['usa_parametros_fiscales'] ?? 1)
        ];
    }
}

// 3. Cargar Clientes
$res_clientes = mysqli_query($conexion, "SELECT id, nombre, limite_credito, deuda_actual FROM clientes_credito ORDER BY nombre ASC");

// 4. Verificación de Venta Exitosa (Para SweetAlert)
$venta_ok = isset($_GET['venta_ok']) ? true : false;
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>POS Sistema - Versión Full</title>
    <style>
        :root { --dark: #1e293b; --green: #10b981; --blue: #3b82f6; --red: #ef4444; --slate: #64748b; --amber: #f59e0b; --indigo: #6366f1; }
        body { font-family: 'Segoe UI', sans-serif; display: flex; flex-direction: column; background: #f1f5f9; margin: 0; height: 100vh; overflow: hidden; }
        
        .top-bar { background: white; padding: 10px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .nav-buttons { display: flex; gap: 10px; align-items: center; }
        .btn-nav { padding: 8px 15px; border-radius: 8px; border: 1px solid #cbd5e1; cursor: pointer; text-decoration: none; color: var(--dark); font-weight: bold; font-size: 13px; }
        
        .badge-tasa { background: #dcfce7; color: #15803d; padding: 8px 15px; border-radius: 8px; font-weight: bold; font-size: 14px; border: 1px solid #bbf7d0; display: flex; align-items: center; gap: 5px;}

        .tabs-wrapper { display: flex; align-items: center; gap: 10px; background: #cbd5e1; padding: 10px 20px; border-bottom: 1px solid #94a3b8; }
        .tab { padding: 8px 20px; background: #f8fafc; border-radius: 8px 8px 0 0; cursor: pointer; font-weight: bold; display: flex; align-items: center; gap: 8px; border: 1px solid #94a3b8; border-bottom: none; color: var(--slate); }
        .tab.active { background: var(--blue); color: white; border-color: var(--blue); }
        .tab-close { color: var(--red); font-size: 16px; cursor: pointer; }

        .main-container { display: flex; flex: 1; overflow: hidden; }
        .panel-factura { flex: 1; background: white; padding: 20px; display: flex; flex-direction: column; position: relative; }
        .panel-control { width: 440px; background: var(--dark); color: white; padding: 20px; display: flex; flex-direction: column; overflow-y: auto; }
        
        .search-container { position: relative; margin-bottom: 20px; }
        .main-input { width: 100%; padding: 15px; border: 2px solid var(--blue); border-radius: 12px; font-size: 18px; outline: none; box-sizing: border-box; text-align: center; }
        #resultados-busqueda { position: absolute; top: 100%; left: 0; width: 100%; background: white; border-radius: 0 0 12px 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.3); display: none; max-height: 350px; overflow-y: auto; color: #333; z-index: 5000; border: 1px solid #cbd5e1; }
        .item-resultado { padding: 12px; display: flex; justify-content: space-between; border-bottom: 1px solid #eee; cursor: pointer; }
        .item-resultado:hover { background: #eff6ff; color: var(--blue); }

        .tabla-factura { width: 100%; border-collapse: collapse; }
        .tabla-factura th { text-align: left; padding: 12px; background: #f8fafc; border-bottom: 2px solid #e2e8f0; color: var(--slate); font-size: 12px; }
        .tabla-factura td { padding: 12px; border-bottom: 1px solid #f1f5f9; vertical-align: middle; }

        .modal { display: none; position: fixed; z-index: 9000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); justify-content: center; align-items: center; }
        .modal-content { background: white; margin: 2% auto; width: 450px; border-radius: 15px; padding: 25px; color: #333; position: relative; box-sizing: border-box; }
        .in-pago { width: 100%; padding: 12px; margin: 8px 0; border: 2px solid #cbd5e1; border-radius: 10px; font-size: 20px; font-weight: bold; text-align: center; box-sizing: border-box; }
        
        .btn-avance-trigger { background: #78350f; color: #fcd34d; border: 1px solid #f59e0b; padding: 12px; border-radius: 10px; width: 100%; font-weight: bold; cursor: pointer; margin-bottom: 12px; display: flex; justify-content: center; align-items: center; gap: 10px; transition: 0.3s; }
        .btn-avance-trigger:hover { background: #92400e; }
        
        .caja-vuelto { margin-top: 15px; padding: 20px; border-radius: 12px; text-align: center; background: #fffbeb; border: 1px solid #fde68a; }
        .vuelto-monto { font-size: 32px; font-weight: 800; color: #92400e; display: block; }
        
        .status-msg { padding: 10px; border-radius: 8px; text-align: center; font-weight: bold; margin-bottom: 10px; font-size: 13px; }
        .btn-procesar { background: var(--green); color: white; border: none; padding: 18px; width: 100%; border-radius: 15px; font-size: 20px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-procesar:disabled { background: #475569; opacity: 0.5; cursor: not-allowed; }
        .badge-peso { background: var(--amber); color: white; font-size: 10px; padding: 2px 5px; border-radius: 4px; margin-left: 5px; }

        .calc-container { background: #f8fafc; border-radius: 12px; padding: 15px; margin-top: 15px; border: 1px solid #e2e8f0; }
        .calc-row { display: flex; justify-content: space-between; margin-bottom: 5px; font-size: 14px; }
        .calc-total { font-size: 20px; font-weight: 800; border-top: 1px solid #cbd5e1; padding-top: 10px; margin-top: 10px; }

        .desglose-box { background: #334155; padding: 12px; border-radius: 10px; margin-top: 10px; font-size: 13px; border: 1px solid #475569; }
        .desglose-line { display: flex; justify-content: space-between; margin-bottom: 4px; color: #cbd5e1; }
        .desglose-line.total { border-top: 1px solid #64748b; padding-top: 6px; margin-top: 6px; font-weight: bold; color: #fff; font-size: 15px; }

        .cliente-box { background: #1e293b; border: 1px solid #475569; padding: 12px; border-radius: 10px; margin-bottom: 10px; }
        .badge-fiscal-tag { background: #dcfce7; color: #166534; font-size: 9px; padding: 2px 5px; border-radius: 4px; font-weight: bold; }
        .badge-nofiscal-tag { background: #f1f5f9; color: #64748b; font-size: 9px; padding: 2px 5px; border-radius: 4px; font-weight: bold; }

        .row-cashea { display: flex; gap: 10px; margin-bottom: 10px; }
        .col-cashea { flex: 1; }
        .text-center { text-align: center; }
        .text-success { color: var(--green); }
        .text-danger { color: var(--red); }
        .font-weight-bold { font-weight: bold; }
        .small { font-size: 12px; }
        .alert-info { background: #e0f2fe; color: #0369a1; padding: 10px; border-radius: 8px; margin-top: 10px; }
    </style>
</head>
<body>

<nav class="top-bar">
    <div style="font-weight: bold; font-size: 18px; color: var(--blue);">🛒 POS SYSTEM PRO</div>
    <div class="nav-buttons">
        <div class="badge-tasa">
            📈 Tasa Referencia: <?php echo number_format($tasa, 2, ',', '.'); ?> Bs.
        </div>
        <button type="button" onclick="abrirBuscadorModal()" class="btn-nav" style="background: var(--blue); color: white; border:none;">🔍 Buscar (F10)</button>
        <a href="historial_pagomovil.php" class="btn-nav" style="background: #e0e7ff; color: #3730a3; border-color: #c7d2fe;">📱 Historial PM</a>
        <a href="historial_facturas.php" class="btn-nav">📄 Facturas</a>
        <a href="corte.php" class="btn-nav">📊 Corte</a>
        <a href="logout.php" class="btn-nav" style="background:#fee2e2; color:#b91c1c;">🚪 Salir</a>
    </div>
</nav>

<div class="tabs-wrapper">
    <div id="tabs-container" style="display:flex; gap:5px;"></div>
    <button type="button" class="btn-nav" style="background:var(--green); color:white; border:none;" onclick="crearNuevaFactura()">+ Nueva (F9)</button>
</div>

<div class="main-container">
    <div class="panel-factura">
        <div class="search-container">
            <input type="text" id="lector" class="main-input" placeholder="Nombre o código de barras..." onkeyup="buscarProducto(this.value)" autocomplete="off">
            <div id="resultados-busqueda"></div>
        </div>
        <div style="flex:1; overflow-y:auto;">
            <table class="tabla-factura">
                <thead>
                    <tr>
                        <th width="80">CANT.</th>
                        <th>PRODUCTO</th>
                        <th style="text-align:right;">PRECIO ($ / Bs)</th>
                        <th style="text-align:right;">TOTAL ($ / Bs)</th>
                        <th width="50"></th>
                    </tr>
                </thead>
                <tbody id="cuerpo-factura"></tbody>
            </table>
        </div>
    </div>

    <div class="panel-control">
        <h3 style="color:var(--blue); margin-top:0;">RESUMEN DE VENTA</h3>
        <div id="pago-status" class="status-msg">ESPERANDO PRODUCTOS</div>
        
        <button type="button" class="btn-avance-trigger" onclick="abrirModalAvance()">
            <span>💵 Registrar Avance (Bs.)</span>
        </button>

        <form action="procesar_venta_multiple.php" method="POST" id="form-venta" onsubmit="return finalizarVenta(event)">
            
            <input type="hidden" name="avance_entregado_bs" id="avance_entregado_hidden" value="0">
            <input type="hidden" name="avance_comision_porcentaje" id="avance_porcentaje_hidden" value="0">
            <input type="hidden" name="avance_total_cobrado_usd" id="avance_total_usd_hidden" value="0">

            <!-- SECCIÓN COMPLETA DE DATOS DEL CLIENTE PARA LA FACTURA -->
            <div class="cliente-box">
                <label style="font-size:11px; font-weight:bold; color:#cbd5e1; display:block; margin-bottom:5px;">👤 DATOS DEL CLIENTE EN FACTURA</label>
                <div style="margin-bottom:8px;">
                    <select id="tipo_cliente_select" onchange="toggleTipoCliente(this.value)" style="width:100%; padding:8px; border-radius:6px; font-size:12px; font-weight:bold;">
                        <option value="general">Público General</option>
                        <option value="personalizado">Personalizado / RIF / Cédula</option>
                    </select>
                </div>

                <div id="box_datos_cliente" style="display:none; flex-direction:column; gap:6px;">
                    <input type="text" name="cliente_nombre" id="nombre_cliente_input" placeholder="Nombre / Razón Social" style="padding:8px; border-radius:6px; border:1px solid #475569; font-size:12px;" value="Público General">
                    <input type="text" name="cliente_rif" id="rif_cliente_input" placeholder="RIF / Cédula (Ej: V-12345678)" style="padding:8px; border-radius:6px; border:1px solid #475569; font-size:12px;">
                    <input type="text" name="cliente_direccion" id="direccion_cliente_input" placeholder="Dirección Fiscal (Ej: Calle Principal #10)" style="padding:8px; border-radius:6px; border:1px solid #475569; font-size:12px;">
                    <input type="text" name="cliente_telefono" id="telefono_cliente_input" placeholder="Teléfono de Contacto" style="padding:8px; border-radius:6px; border:1px solid #475569; font-size:12px;">
                </div>
            </div>

            <label style="font-size:11px; font-weight:bold; opacity:0.7;">MÉTODO DE PAGO</label>
            <select name="metodo_pago" id="metodo_pago" onchange="verificarMetodo(this.value)" style="width:100%; padding:12px; border-radius:10px; margin:6px 0; font-weight:bold; font-size:15px;">
                <option value="" disabled selected>Seleccione método</option>
                <?php while($mp = mysqli_fetch_assoc($res_metodos)): ?>
                    <option value="<?php echo $mp['valor_option']; ?>">
                        <?php echo $mp['icono'] . ' ' . htmlspecialchars($mp['nombre']); ?>
                    </option>
                <?php endwhile; ?>
            </select>

            <!-- RESUMEN DESGLOSADO DE PRECIOS E IMPUESTOS FISCALES -->
            <div class="desglose-box">
                <div class="desglose-line">
                    <span>Subtotal Base:</span>
                    <span>$ <span id="desglose-subtotal">0.00</span></span>
                </div>
                <?php if($usar_iva_global == 1): ?>
                <div class="desglose-line">
                    <span>IVA (<?php echo number_format($porcentaje_iva, 0); ?>%):</span>
                    <span>$ <span id="desglose-iva">0.00</span></span>
                </div>
                <?php endif; ?>
                <div class="desglose-line total">
                    <span>TOTAL FACTURA ($):</span>
                    <span style="color:var(--green);">$ <span id="total-usd">0.00</span></span>
                </div>
                <div class="desglose-line" style="font-size:14px; font-weight:bold; color:#cbd5e1; margin-top:2px;">
                    <span>TOTAL EN BS:</span>
                    <span>Bs. <span id="total-bs">0.00</span></span>
                </div>
            </div>

            <input type="hidden" name="items_carrito" id="input_items">
            <input type="hidden" name="detalle_pagos_multiples" id="detalle_pagos_multiples">
            <input type="hidden" name="id_cliente_credito" id="id_cliente_credito">
            <input type="hidden" name="cliente" id="cliente_hidden_final" value="Público General">
            <input type="hidden" name="referencia_pago" id="pago_referencia_form">
            <input type="hidden" name="tasa" value="<?php echo $tasa; ?>">
            <input type="hidden" id="pago_validado" value="0">
            
            <div style="margin-top: 15px; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; background: #ffffff; padding: 10px; border-radius: 6px; border: 1px solid #121212;">
                <input type="checkbox" name="imprimir_ticket" id="imprimir_ticket" value="1" checked style="width: 20px; height: 20px; cursor: pointer; accent-color: #c62828;">
                <label for="imprimir_ticket" style="margin: 0; cursor: pointer; font-weight: 700; color: #121212; text-transform: uppercase; font-size: 0.8rem;">
                    🖨️ Imprimir Factura 
                </label>
            </div>
            <button type="submit" id="btn-procesar" class="btn-procesar" disabled>PROCESAR COBRO</button>
        </form>
    </div>
</div>

<!-- MODAL PAGO MÓVIL -->
<div id="modalPagoMovil" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--blue); width: 450px;">
        <h3 style="text-align:center; color: var(--blue); margin-top:0;">📱 Pago Móvil</h3>
        
        <div style="background: #eff6ff; padding: 12px; border-radius: 8px; font-size: 13px; margin-bottom: 15px; border: 1px solid #bfdbfe;">
            <b>Datos Receptor:</b><br>
            <b>Banco:</b> <?php echo htmlspecialchars($datos_pm['banco']); ?><br>
            <b>Teléfono:</b> <?php echo htmlspecialchars($datos_pm['telefono']); ?><br>
            <b>CI/RIF:</b> <?php echo htmlspecialchars($datos_pm['cedula_rif']); ?>
        </div>

        <div style="text-align:center; padding:10px; background:#f1f5f9; border-radius:10px; margin-bottom:15px;">
            <small>MONTO A PAGAR:</small><br>
            <b id="pm_total_display" style="font-size:22px; color:var(--blue);">0.00 Bs.</b>
        </div>

        <div style="display: flex; flex-direction: column; gap: 10px;">
            <div>
                <select id="pm_banco_emisor" name="pm_banco_emisor" class="in-pago">
                    <option value="" disabled selected>Seleccione el banco</option>
                    <option value="0102 - Banco de Venezuela">0102 - Banco de Venezuela</option>
                    <option value="0104 - Venezolano de Crédito">0104 - Venezolano de Crédito</option>
                    <option value="0105 - Banco Mercantil">0105 - Banco Mercantil</option>
                    <option value="0108 - Banco Provincial">0108 - Banco Provincial</option>
                    <option value="0114 - Banco del Caribe (Bancaribe)">0114 - Banco del Caribe (Bancaribe)</option>
                    <option value="0115 - Banco Exterior">0115 - Banco Exterior</option>
                    <option value="0128 - Banco Caroní">0128 - Banco Caroní</option>
                    <option value="0134 - Banesco">0134 - Banesco</option>
                    <option value="0137 - Sofitasa">0137 - Sofitasa</option>
                    <option value="0138 - Banco Plaza">0138 - Banco Plaza</option>
                    <option value="0151 - BNC Banco Nacional de Crédito">0151 - BNC Banco Nacional de Crédito</option>
                    <option value="0156 - 100% Banco">0156 - 100% Banco</option>
                    <option value="0157 - DelSur Banco Universal">0157 - DelSur Banco Universal</option>
                    <option value="0163 - Banco del Tesoro">0163 - Banco del Tesoro</option>
                    <option value="0166 - Banco Agrícola de Venezuela">0166 - Banco Agrícola de Venezuela</option>
                    <option value="0168 - Bancrecer">0168 - Bancrecer</option>
                    <option value="0169 - Mi Banco">0169 - Mi Banco</option>
                    <option value="0171 - Banco Activo">0171 - Banco Activo</option>
                    <option value="0172 - Bancamiga">0172 - Bancamiga</option>
                    <option value="0174 - Banplus">0174 - Banplus</option>
                    <option value="0175 - Bicentenario Banco del Pueblo">0175 - Bicentenario Banco del Pueblo</option>
                    <option value="0177 - BANFANB">0177 - BANFANB</option>
                    <option value="0191 - Banco Digital N368">0191 - Banco Digital N368</option>
                </select>
            </div>
            <div>
                <label style="font-size:12px; font-weight:bold;">Número de Referencia (4 a 6 dígitos):</label>
                <input type="text" id="pm_referencia" name="pm_referencia" class="in-pago" placeholder="Ej: 123456">
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1; padding:12px; border-radius:8px; border:1px solid #ccc; cursor:pointer;">CANCELAR</button>
            <button type="button" onclick="confirmarPagoMovil()" style="flex:1; background:var(--green); color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">CONFIRMAR PAGO</button>
        </div>
    </div>
</div>

<div id="modalAvance" class="modal">
    <div class="modal-content" style="border-top: 5px solid #f59e0b;">
        <h3 style="text-align:center; color:#92400e; margin-top:0;">💵 Avance de Efectivo</h3>
        
        <div style="display:grid; grid-template-columns: 2fr 1fr; gap:10px;">
            <div>
                <label style="font-size:12px; font-weight:bold; color:var(--slate);">A Entregar Físico (Bs)</label>
                <input type="number" step="0.01" id="monto_avance_bs_input" class="in-pago" placeholder="Ej: 1000" oninput="previsualizarAvance()">
            </div>
            <div>
                <label style="font-size:12px; font-weight:bold; color:var(--slate);">Comisión (%)</label>
                <input type="number" step="0.1" id="monto_avance_porcentaje" class="in-pago" value="12" oninput="previsualizarAvance()">
            </div>
        </div>
        
        <div style="text-align:center; margin-top:15px; padding:15px; background:#fffbeb; border-radius:8px; border:1px solid #fde68a;">
            <small style="color:#92400e;">Total a cobrarle al cliente:</small><br>
            <b id="avance_previa_bs" style="color:#b45309; font-size:24px;">0.00 Bs.</b><br>
            <small id="avance_previa_usd" style="color:var(--slate); font-weight:bold;">$ 0.00</small>
        </div>

        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1; padding:12px; border-radius:8px; border: 1px solid #ccc; background:#f1f5f9; cursor:pointer;">CANCELAR</button>
            <button type="button" onclick="aplicarAvance()" style="flex:1; background:#f59e0b; color:white; border:none; border-radius:8px; font-weight:bold; cursor:pointer;">AGREGAR A FACTURA</button>
        </div>
    </div>
</div>

<div id="modalDolarEfectivo" class="modal">
    <div class="modal-content">
        <h3 style="text-align:center; color:var(--green);">💵 Efectivo $</h3>
        <div style="text-align:center; padding:10px; background:#f1f5f9; border-radius:10px; margin-bottom:15px;">
            <small>A PAGAR:</small> <b id="d_total_display" style="font-size:20px;">$ 0.00</b>
        </div>
        <input type="number" step="0.01" id="d_recibido" class="in-pago" placeholder="Recibido $" oninput="calcularVueltoDolar()">
        <div class="caja-vuelto" id="vuelto_box_dolar" style="display:none;">
            <small>VUELTO:</small>
            <span class="vuelto-monto" id="v_usd">$ 0.00</span>
            <span style="font-size:18px; color:#b45309; font-weight:bold;" id="v_bs">0.00 Bs.</span>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1; padding:12px; border-radius:8px;">CANCELAR</button>
            <button type="button" id="btn-confirmar-d" onclick="confirmarPago()" disabled style="flex:1; background:var(--green); color:white; border:none; border-radius:8px; font-weight:bold; opacity:0.5;">LISTO</button>
        </div>
    </div>
</div>

<div id="modalBsEfectivo" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--amber);">
        <h3 style="text-align:center; color:var(--amber);">💸 Efectivo Bs.</h3>
        <div style="text-align:center; padding:10px; background:#fffbeb; border-radius:10px; margin-bottom:15px;">
            <small>A PAGAR EN BS:</small> <br>
            <b id="bs_total_display" style="font-size:24px; color:#92400e;">0.00 Bs.</b>
        </div>
        <input type="number" step="0.01" id="bs_recibido" class="in-pago" placeholder="Recibido en Bs." oninput="calcularVueltoBs()">
        <div class="caja-vuelto" id="vuelto_box_bs" style="display:none; background: #ecfdf5; border-color: #10b981;">
            <small>VUELTO:</small>
            <span class="vuelto-monto" id="v_bs_final" style="color: #047857;">0.00 Bs.</span>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1; padding:12px; border-radius:8px;">CANCELAR</button>
            <button type="button" id="btn-confirmar-bs" onclick="confirmarPago()" disabled style="flex:1; background:var(--green); color:white; border:none; border-radius:8px; font-weight:bold; opacity:0.5;">LISTO</button>
        </div>
    </div>
</div>

<div id="modalMultiple" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--blue); width: 500px;">
        <h3 style="text-align:center;">📑 Calculadora de Pago Múltiple</h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <div>
                <label style="font-size:12px; font-weight:bold;">Efectivo Bs.</label>
                <input type="number" id="p_bs_efec" class="in-pago" placeholder="0.00" oninput="calcularRestante()">
            </div>
            <div>
                <label style="font-size:12px; font-weight:bold;">Dólares $</label>
                <input type="number" id="p_dolar" class="in-pago" placeholder="0.00" oninput="calcularRestante()">
            </div>
        </div>
        <div style="margin-top:10px; display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <div>
                <label style="font-size:12px; font-weight:bold;">Punto de Venta (Bs)</label>
                <input type="number" id="p_punto" class="in-pago" placeholder="0.00" oninput="calcularRestante()"> 
            </div>
            <div>
                <label style="font-size:12px; font-weight:bold;">Pago Móvil (Bs)</label>
                <input type="number" id="p_pagomovil" class="in-pago" placeholder="0.00" oninput="calcularRestante()"> 
            </div>
        </div>
        <div style="margin-top:10px;">
            <label style="font-size:12px; font-weight:bold; color: #15803d;">BioPago (Bs)</label>
            <input type="number" id="p_biopago" class="in-pago" placeholder="0.00" oninput="calcularRestante()" style="border-color: #10b981;"> 
        </div>
        <div class="calc-container">
            <div class="calc-row"><span>Total a Pagar:</span> <div><b id="calc_total_fijo">$ 0.00</b> <small style="color:var(--slate);">/ <span id="calc_total_fijo_bs">0.00 Bs.</span></small></div></div>
            <div class="calc-row" style="color:var(--blue);"><span>Total Recibido:</span> <div><b id="calc_pagado">$ 0.00</b> <small>/ <span id="calc_pagado_bs">0.00 Bs.</span></small></div></div>
            <div id="box-resultado-pago" class="calc-total">
                <div class="calc-row"><span id="lbl_status">Falta:</span> <b id="calc_diferencia_usd">$ 0.00</b></div>
                <div class="calc-row" style="font-size:14px; opacity:0.8;"><span id="lbl_status_bs">Falta en Bs:</span> <b id="calc_diferencia_bs">0.00 Bs.</b></div>
            </div>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1; padding:12px; border-radius:8px; border:1px solid #ccc;">CANCELAR</button>
            <button type="button" id="btn-confirmar-m" onclick="confirmarMultiple()" disabled style="flex:1; background:var(--green); color:white; border:none; border-radius:8px; font-weight:bold; opacity:0.5;">CONFIRMAR</button>
        </div>
    </div>
</div>

<div id="modalCashea" class="modal">
    <div class="modal-content" style="max-width: 480px; border-top: 5px solid var(--blue); border-radius: 16px;">
        <h3 class="font-weight-bold text-center" style="color: var(--blue); margin-top: 0;">✨ Estructura Plan Cashea</h3>
        
        <div class="row-cashea text-center" style="background: var(--dark); padding: 15px; border-radius: 10px; color: white;">
            <div class="col-cashea">
                <small style="font-size: 11px; opacity: 0.7; display: block;">COMPRA BASE</small>
                <span id="cashea_total_display" class="font-weight-bold" style="font-size: 1.2rem;">$0.00</span>
            </div>
            <div class="col-cashea">
                <small style="font-size: 11px; opacity: 0.7; display: block;">TOTAL + RECARGO</small>
                <span id="cashea_total_final_display" class="font-weight-bold text-success" style="font-size: 1.2rem;">$0.00</span>
            </div>
        </div>

        <div style="background: #f8fafc; padding: 15px; border-radius: 10px; margin-top: 15px; border: 1px solid #e2e8f0;">
            <label class="small font-weight-bold" style="color: var(--slate);">📈 Recargo Operacional Cashea</label>
            <div class="row-cashea" style="margin-top: 5px;">
                <div class="col-cashea">
                    <small style="opacity: 0.7; display: block; margin-bottom: 2px;">Monto Fijo ($)</small>
                    <input type="number" step="0.01" id="cashea_extra_manual" name="monto_recargo" class="in-pago" style="font-size:16px; padding:8px; margin:0;" placeholder="0.00" oninput="calcularEstructuraCashea('manual')">
                </div>
                <div class="col-cashea">
                    <small style="opacity: 0.7; display: block; margin-bottom: 2px;">Porcentaje (%)</small>
                    <input type="number" step="0.1" id="cashea_extra_porcentaje" class="in-pago" style="font-size:16px; padding:8px; margin:0;" placeholder="0" oninput="calcularEstructuraCashea('percentage')">
                </div>
            </div>
        </div>

        <div style="background: #fffbeb; padding: 15px; border-radius: 10px; margin-top: 15px; border: 1px solid #fde68a;">
            <div class="row-cashea" style="align-items: center;">
                <div class="col-cashea">
                    <label class="small font-weight-bold" style="color: #b45309; display: block; margin-bottom: 2px;">💵 Inicial a Recibir ($)</label>
                    <input type="number" step="0.01" id="cashea_inicial_manual" name="monto_inicial" class="in-pago" style="font-size:18px; padding:8px; margin:0; border-color: #f59e0b;" placeholder="0.00" oninput="actualizarRestanteCashea()">
                </div>
                <div class="col-cashea text-center">
                    <label class="small font-weight-bold" style="color: var(--slate); display: block;">Queda por Cobrar Cashea</label>
                    <div id="cashea_restante_display" class="font-weight-bold text-danger" style="font-size: 1.4rem; margin-top: 5px;">$0.00</div>
                    
                    <input type="hidden" id="cashea_financiado_hidden" name="monto_financiado" value="0.00">
                </div>
            </div>
            
            <div style="margin-top: 10px;">
                <label class="small font-weight-bold" style="color: var(--slate); display: block; margin-bottom: 4px;">¿Cómo entra la Inicial al Corte?</label>
                <select id="cashea_metodo_inicial" class="in-pago" style="font-size:14px; padding:10px; margin:0;" onchange="actualizarRestanteCashea()">
                    <option value="Efectivo $">💵 Dólares en Efectivo ($)</option>
                    <option value="Pago Móvil">📱 Pago Móvil (Bs.)</option>
                    <option value="BioPago">🧬 BioPago (Bs.)</option>
                    <option value="Punto">💳 Punto de Venta (Bs.)</option>
                    <option value="Efectivo Bs.">💸 Efectivo en Bolívares (Bs.)</option>
                </select>
            </div>
        </div>

        <div id="cashea_desglose_monedas" class="alert-info text-center font-weight-bold small" style="display: none;"></div>
        
        <div class="row-cashea" style="margin-top: 20px;">
            <div class="col-cashea">
                <button type="button" class="btn-nav" style="width: 100%; padding: 12px; background: #f1f5f9;" onclick="cerrarModales()">CANCELAR</button>
            </div>
            <div class="col-cashea">
                <button type="button" class="btn-nav" style="width: 100%; padding: 12px; background: var(--green); color: white; border: none;" onclick="confirmarCashea()">VALIDAR REGISTRO</button>
            </div>
        </div>
    </div>
</div>

<div id="modalSeleccionarCliente" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--indigo); width: 100%; max-width: 450px; margin: auto; padding: 30px; border-radius: 20px;">
        <h3 style="color: var(--indigo); margin-top: 0;">Seleccionar Cliente a Crédito</h3>
        <div style="display: flex; flex-direction: column; gap: 15px;">
            <input type="text" id="buscar_cliente_modal" 
                   placeholder="🔍 Escribe el nombre del cliente..." 
                   onkeyup="filtrarClientesModal()"
                   style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; box-sizing: border-box;">

            <select id="select_cliente_credito" class="in-pago" 
                    style="width: 100%; padding: 12px; border: 1px solid #e2e8f0; border-radius: 10px; cursor: pointer; background: white;">
                <option value="">-- Selecciona un cliente --</option>
                <?php
                mysqli_data_seek($res_clientes, 0);
                while($cl = mysqli_fetch_assoc($res_clientes)) {
                    $disponible = $cl['limite_credito'] - $cl['deuda_actual'];
                    echo "<option value='{$cl['id']}' data-nombre='{$cl['nombre']}' data-disponible='{$disponible}'>{$cl['nombre']} (Disp: $ ".number_format($disponible, 2).")</option>";
                }
                ?>
            </select>

            <button type="button" onclick="confirmarVentaCredito()" 
                    style="width: 100%; background: var(--indigo); color: white; border: none; padding: 15px; border-radius: 12px; font-weight: bold; cursor: pointer; font-size: 1rem;">
                ASIGNAR CRÉDITO
            </button>
            <p onclick="cerrarModales()" style="cursor: pointer; text-align: center; color: #64748b; margin: 0; font-size: 0.9rem;">Cancelar</p>
        </div>
    </div>
</div>

<div id="modalBascula" class="modal">
    <div class="modal-content" style="border-top: 5px solid var(--amber);">
        <h3 id="b_nombre_prod" style="text-align:center;"></h3>
        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
            <div><label>GRAMOS</label><input type="number" id="b_gramos" class="in-pago" oninput="calcularPeso('g')"></div>
            <div><label>KILOS</label><input type="number" step="0.001" id="b_kilos" class="in-pago" oninput="calcularPeso('k')"></div>
        </div>
        <div class="caja-vuelto">
            <span id="b_monto_calc" style="font-size:24px; color:var(--blue);">$ 0.00</span>
        </div>
        <div style="display:flex; gap:10px; margin-top:20px;">
            <button type="button" onclick="cerrarModales()" style="flex:1;">CANCELAR</button>
            <button type="button" onclick="confirmarBascula()" style="flex:1; background:var(--green); color:white; border-radius:8px;">AÑADIR</button>
        </div>
    </div>
</div>

<div id="modalBuscador" class="modal">
    <div class="modal-content" style="width: 700px;">
        <h3 style="margin:0 0 15px 0; color:var(--blue);">🔍 Buscador General</h3>
        <input type="text" id="inputBuscadorModal" class="in-pago" placeholder="Escriba para buscar..." onkeyup="filtrarProductosModal(this.value)" autocomplete="off">
        <div id="listaBuscadorModal" style="max-height: 400px; overflow-y: auto; border: 1px solid #eee; border-radius: 8px; margin-top:10px;"></div>
        <button type="button" onclick="cerrarModales()" style="width:100%; margin-top:15px; padding:12px; cursor:pointer;">CERRAR</button>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// --- CORE DATA Y PARÁMETROS FISCALES ---
const productos = <?php echo json_encode($productos_lista); ?>;
const tasa = <?php echo $tasa; ?>;
const USAR_IVA_GLOBAL = <?php echo $usar_iva_global; ?>;
const PORCENTAJE_IVA = <?php echo $porcentaje_iva; ?>;

let facturas = JSON.parse(localStorage.getItem('pos_facturas')) || {
    "1": { items: [], metodo: "Pago Móvil", cliente: "Público General", cliente_rif: "", cliente_dir: "", cliente_tel: "", id_cliente: "", validado: "0", avance_entregado: 0, avance_porcentaje: 12 }
};
let facturaActiva = localStorage.getItem('pos_factura_activa') || "1";
if (!facturas[facturaActiva]) facturaActiva = Object.keys(facturas)[0];

let carrito = facturas[facturaActiva].items;
let montoAvanceEntregado = facturas[facturaActiva].avance_entregado || 0;
let porcentajeAvance = facturas[facturaActiva].avance_porcentaje || 12;
let pTemporal = null;

document.addEventListener('DOMContentLoaded', () => {
    renderizarTabs();
    actualizarVista();
    
    <?php if($venta_ok): ?>
    Swal.fire({
        icon: 'success',
        title: '¡Venta Exitosa!',
        text: 'La factura ha sido procesada correctamente.',
        timer: 3000,
        showConfirmButton: false
    });
    <?php endif; ?>
});

function toggleTipoCliente(val) {
    const box = document.getElementById('box_datos_cliente');
    const inputNombre = document.getElementById('nombre_cliente_input');
    const inputRif = document.getElementById('rif_cliente_input');
    const inputDir = document.getElementById('direccion_cliente_input');
    const inputTel = document.getElementById('telefono_cliente_input');

    if(val === 'personalizado') {
        box.style.display = 'flex';
        if(inputNombre.value === "Público General") inputNombre.value = "";
        inputNombre.focus();
    } else {
        box.style.display = 'none';
        inputNombre.value = "Público General";
        inputRif.value = "";
        inputDir.value = "";
        inputTel.value = "";
    }
    guardarStorage();
}

function guardarStorage() {
    if (!facturas[facturaActiva]) return;

    facturas[facturaActiva].items = carrito;
    facturas[facturaActiva].metodo = document.getElementById('metodo_pago').value;
    facturas[facturaActiva].cliente = document.getElementById('nombre_cliente_input').value;
    facturas[facturaActiva].cliente_rif = document.getElementById('rif_cliente_input').value;
    facturas[facturaActiva].cliente_dir = document.getElementById('direccion_cliente_input').value;
    facturas[facturaActiva].cliente_tel = document.getElementById('telefono_cliente_input').value;
    facturas[facturaActiva].id_cliente = document.getElementById('id_cliente_credito').value;
    facturas[facturaActiva].validado = document.getElementById('pago_validado').value;
    facturas[facturaActiva].avance_entregado = montoAvanceEntregado;
    facturas[facturaActiva].avance_porcentaje = porcentajeAvance;

    localStorage.setItem('pos_facturas', JSON.stringify(facturas));
    localStorage.setItem('pos_factura_activa', facturaActiva);
}

// --- PESTAÑAS ---
function renderizarTabs() {
    const container = document.getElementById('tabs-container');
    container.innerHTML = "";
    Object.keys(facturas).forEach(id => {
        const div = document.createElement('div');
        div.className = `tab ${id == facturaActiva ? 'active' : ''}`;
        div.onclick = () => cambiarFactura(id);
        div.innerHTML = `Factura ${id} ${Object.keys(facturas).length > 1 ? `<span class="tab-close" onclick="cerrarFactura(event, '${id}')">&times;</span>` : ''}`;
        container.appendChild(div);
    });
}

function crearNuevaFactura() {
    const ids = Object.keys(facturas).map(Number);
    const nuevoId = Math.max(...ids) + 1;
    facturas[nuevoId] = { items: [], metodo: "Pago Móvil", cliente: "Público General", cliente_rif: "", cliente_dir: "", cliente_tel: "", id_cliente: "", validado: "0", avance_entregado: 0, avance_porcentaje: 12 };
    cambiarFactura(nuevoId.toString());
}

function cambiarFactura(id) {
    guardarStorage();
    facturaActiva = id;
    carrito = facturas[id].items;
    document.getElementById('metodo_pago').value = facturas[id].metodo || "Pago Móvil";
    document.getElementById('nombre_cliente_input').value = facturas[id].cliente || "Público General";
    document.getElementById('rif_cliente_input').value = facturas[id].cliente_rif || "";
    document.getElementById('direccion_cliente_input').value = facturas[id].cliente_dir || "";
    document.getElementById('telefono_cliente_input').value = facturas[id].cliente_tel || "";
    document.getElementById('id_cliente_credito').value = facturas[id].id_cliente || "";
    document.getElementById('pago_validado').value = facturas[id].validado || "0";
    
    if(facturas[id].cliente && facturas[id].cliente !== "Público General") {
        document.getElementById('tipo_cliente_select').value = "personalizado";
        document.getElementById('box_datos_cliente').style.display = "flex";
    } else {
        document.getElementById('tipo_cliente_select').value = "general";
        document.getElementById('box_datos_cliente').style.display = "none";
    }

    montoAvanceEntregado = facturas[id].avance_entregado || 0;
    porcentajeAvance = facturas[id].avance_porcentaje || 12;
    
    actualizarVista();
    renderizarTabs();
}

function cerrarFactura(e, id) {
    e.stopPropagation();
    if(confirm("¿Eliminar factura " + id + "?")) {
        delete facturas[id];
        if(facturaActiva == id) facturaActiva = Object.keys(facturas)[0];
        cambiarFactura(facturaActiva);
    }
}

// --- BUSCADOR Y SELECCIÓN DE PRODUCTOS ---
function buscarProducto(texto) {
    const listado = document.getElementById('resultados-busqueda');
    const busqueda = texto.toLowerCase().trim();
    if (busqueda.length === 0) { listado.style.display = "none"; return; }
    
    const coincidenciaExacta = productos.find(p => (p.codigo || "").toLowerCase() === busqueda);
    if (coincidenciaExacta) { seleccionarProducto(coincidenciaExacta.id); return; }

    const filtrados = productos.filter(p => (p.nombre || "").toLowerCase().includes(busqueda) || (p.codigo || "").toLowerCase().includes(busqueda)).slice(0, 10);
    if (filtrados.length > 0) {
        listado.style.display = "block";
        listado.innerHTML = filtrados.map(p => `
            <div class="item-resultado" onclick="seleccionarProducto(${p.id})">
                <span>${p.nombre} ${esProductoPesado(p) ? '<span class="badge-peso">BÁSCULA</span>' : ''}</span>
                <b>$${p.precio.toFixed(2)}</b>
            </div>
        `).join('');
    } else { listado.style.display = "none"; }
}

function seleccionarProducto(id) {
    const p = productos.find(x => x.id == id);
    if (!p) return;
    
    if (esProductoPesado(p)) { 
        pTemporal = p; 
        abrirBascula(); 
    } else { 
        agregarItem(p.id, p.nombre, p.precio, p.stock, 1, p.usa_fiscal); 
    }
    
    const lector = document.getElementById('lector');
    lector.value = "";
    lector.focus();
    document.getElementById('resultados-busqueda').style.display = "none";
}

function agregarItem(id, nombre, precio, stock, cantidad, usa_fiscal = 1) {
    let item = carrito.find(x => x.id == id);
    if(item) {
        if((item.cantidad + cantidad) <= stock) {
            item.cantidad += cantidad;
        } else {
            Swal.fire("Stock insuficiente", `Solo quedan ${stock} disponibles.`, "warning");
            return;
        }
    } else {
        if(cantidad <= stock) {
            carrito.push({id, nombre, precio: parseFloat(precio), cantidad, stock_max: stock, descuento: 0, usa_fiscal});
        } else {
            Swal.fire("Stock insuficiente", `Intentas agregar ${cantidad} pero solo hay ${stock} en inventario.`, "warning");
            return;
        }
    }
    document.getElementById('pago_validado').value = "0";
    actualizarVista();
    guardarStorage();
}

function ajustarCantidadUltimoItem(delta) {
    if (carrito.length === 0) return;
    let index = carrito.length - 1;
    let item = carrito[index];
    let paso = esProductoPesado(productos.find(p => p.id == item.id)) ? 0.1 : 1;
    let nuevaCant = item.cantidad + (delta * paso);

    if (nuevaCant > item.stock_max) {
        const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2000 });
        Toast.fire({ icon: 'warning', title: 'Sin stock suficiente' });
        return;
    }

    if (nuevaCant <= 0) { eliminarItem(index); } 
    else {
        item.cantidad = nuevaCant;
        document.getElementById('pago_validado').value = "0";
        actualizarVista();
        guardarStorage();
    }
}

function eliminarItem(idx) {
    carrito.splice(idx, 1);
    document.getElementById('pago_validado').value = "0";
    actualizarVista();
    guardarStorage();
}

function aplicarDescuento(idx) {
    let descuentoActual = carrito[idx].descuento || 0;
    
    Swal.fire({
        title: 'Aplicar Descuento',
        text: `Producto: ${carrito[idx].nombre}`,
        input: 'number',
        inputLabel: 'Porcentaje de descuento (%)',
        inputValue: descuentoActual,
        showCancelButton: true,
        confirmButtonText: 'Aplicar',
        cancelButtonText: 'Cancelar',
        inputValidator: (value) => {
            if (value < 0 || value > 100) {
                return 'El descuento debe estar entre 0 y 100';
            }
        }
    }).then((result) => {
        if (result.isConfirmed) {
            carrito[idx].descuento = parseFloat(result.value) || 0;
            document.getElementById('pago_validado').value = "0"; 
            actualizarVista();
            guardarStorage();
            
            Swal.fire({
                icon: 'success',
                title: 'Descuento aplicado',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 1500
            });
        }
    });
}

// --- AVANCE CON PORCENTAJE Y COMISIÓN ---
function abrirModalAvance() {
    document.getElementById('modalAvance').style.display = 'flex';
    document.getElementById('monto_avance_bs_input').value = montoAvanceEntregado > 0 ? montoAvanceEntregado : "";
    document.getElementById('monto_avance_porcentaje').value = porcentajeAvance;
    previsualizarAvance();
    document.getElementById('monto_avance_bs_input').focus();
}

function previsualizarAvance() {
    let inputPorcentaje = document.getElementById('monto_avance_porcentaje');
    if (!inputPorcentaje.value || inputPorcentaje.value == "10") {
        inputPorcentaje.value = 12;
    }

    let entregadoBs = parseFloat(document.getElementById('monto_avance_bs_input').value) || 0;
    let porc = parseFloat(inputPorcentaje.value) || 0;
    
    let comisionBs = entregadoBs * (porc / 100);
    let totalCobroBs = entregadoBs + comisionBs;
    let totalCobroUsd = totalCobroBs / tasa;
    
    document.getElementById('avance_previa_bs').innerText = totalCobroBs.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
    document.getElementById('avance_previa_usd').innerText = "$ " + totalCobroUsd.toFixed(2);
}

function aplicarAvance() {
    let entregadoBs = parseFloat(document.getElementById('monto_avance_bs_input').value) || 0;
    let porc = parseFloat(document.getElementById('monto_avance_porcentaje').value) || 0;
    
    if(entregadoBs <= 0) return Swal.fire("Error", "Ingrese un monto a entregar mayor a 0", "error");
    
    montoAvanceEntregado = entregadoBs;
    porcentajeAvance = porc;
    
    document.getElementById('pago_validado').value = "0"; 
    cerrarModales();
    actualizarVista();
    guardarStorage();
}

function quitarAvance() {
    montoAvanceEntregado = 0;
    document.getElementById('pago_validado').value = "0";
    actualizarVista();
    guardarStorage();
}

// --- ACTUALIZACIÓN GLOBAL DE VISTA ---
function actualizarVista() {
    let html = ""; 
    let subtotalBaseUSD = 0;
    let baseImponibleUSD = 0;
    
    carrito.forEach((p, idx) => {
        let descuentoPorcentaje = p.descuento || 0;
        let precioConDescuento = p.precio * (1 - (descuentoPorcentaje / 100));
        let sub = p.cantidad * precioConDescuento; 
        subtotalBaseUSD += sub;

        if(p.usa_fiscal === 1) {
            baseImponibleUSD += sub;
        }
        
        let precioBs = (precioConDescuento * tasa).toLocaleString('es-VE', {minimumFractionDigits:2});
        let subBs = (sub * tasa).toLocaleString('es-VE', {minimumFractionDigits:2});
        let tagFiscal = (p.usa_fiscal === 1) ? '<span class="badge-fiscal-tag">IVA</span>' : '<span class="badge-nofiscal-tag">EXENTO</span>';

        let precioDisplay = `$${precioConDescuento.toFixed(2)}`;
        if(descuentoPorcentaje > 0) {
            precioDisplay = `
                <del style="color:var(--red); font-size:11px;">$${p.precio.toFixed(2)}</del> 
                <span style="color:var(--green); font-weight:bold; font-size:11px;">(-${descuentoPorcentaje}%)</span><br>
                $${precioConDescuento.toFixed(2)}
            `;
        }

        html += `<tr>
            <td><b>${Number.isInteger(p.cantidad) ? p.cantidad : p.cantidad.toFixed(3)}</b></td>
            <td>${p.nombre} ${tagFiscal}</td>
            <td style="text-align:right;">${precioDisplay}<br><small style="color:var(--slate);">Bs. ${precioBs}</small></td>
            <td style="text-align:right;">$${sub.toFixed(2)}<br><small style="color:var(--slate);">Bs. ${subBs}</small></td>
            <td style="text-align:center;">
                <button type="button" onclick="aplicarDescuento(${idx})" title="Descuento" style="color:var(--amber); border:none; background:none; cursor:pointer; font-size:18px; margin-right: 5px;">%</button>
                <button type="button" onclick="eliminarItem(${idx})" style="color:red; border:none; background:none; cursor:pointer; font-size:18px;">&times;</button>
            </td>
        </tr>`;
    });

    let montoIvaUSD = 0;
    if (USAR_IVA_GLOBAL === 1) {
        montoIvaUSD = baseImponibleUSD * (PORCENTAJE_IVA / 100);
    }

    let totalAvanceUSD = 0;
    if(montoAvanceEntregado > 0) {
        let comisionBs = montoAvanceEntregado * (porcentajeAvance / 100);
        let totalAvanceBs = montoAvanceEntregado + comisionBs;
        totalAvanceUSD = totalAvanceBs / tasa;
        subtotalBaseUSD += totalAvanceUSD;

        html += `<tr style="background:#fffbeb;">
            <td><b>1</b></td>
            <td>Avance de Efectivo <br><small style="color:#92400e;">(Físico: ${montoAvanceEntregado} Bs. | Com: ${porcentajeAvance}%)</small></td>
            <td style="text-align:right;">$${totalAvanceUSD.toFixed(2)}<br><small style="color:var(--slate);">Bs. ${totalAvanceBs.toLocaleString('es-VE', {minimumFractionDigits:2})}</small></td>
            <td style="text-align:right; font-weight:bold; color:#92400e;">$${totalAvanceUSD.toFixed(2)}<br><small>Bs. ${totalAvanceBs.toLocaleString('es-VE', {minimumFractionDigits:2})}</small></td>
            <td style="text-align:center;"><button type="button" onclick="quitarAvance()" title="Eliminar Avance" style="color:var(--red); border:none; background:none; cursor:pointer; font-size:18px;">&times;</button></td>
        </tr>`;
    }

    let totalFinalUSD = subtotalBaseUSD + montoIvaUSD;
    let totalFinalBs = totalFinalUSD * tasa;

    document.getElementById('cuerpo-factura').innerHTML = html;

    document.getElementById('desglose-subtotal').innerText = subtotalBaseUSD.toFixed(2);
    if(document.getElementById('desglose-iva')) {
        document.getElementById('desglose-iva').innerText = montoIvaUSD.toFixed(2);
    }

    const metodoSeleccionado = document.getElementById('metodo_pago').value;
    const validado = document.getElementById('pago_validado').value === "1";
    const datosMultiplesStr = document.getElementById('detalle_pagos_multiples').value;

    if (metodoSeleccionado === "Cashea" && validado && datosMultiplesStr) {
        try {
            const datosCashea = JSON.parse(datosMultiplesStr);
            const inicialUSD = parseFloat(datosCashea.inicial_usd) || 0;
            const inicialBs = inicialUSD * tasa;

            document.getElementById('total-usd').innerText = inicialUSD.toFixed(2);
            document.getElementById('total-bs').innerText = inicialBs.toLocaleString('es-VE', {minimumFractionDigits:2});
        } catch (e) {
            document.getElementById('total-usd').innerText = totalFinalUSD.toFixed(2);
            document.getElementById('total-bs').innerText = totalFinalBs.toLocaleString('es-VE', {minimumFractionDigits:2});
        }
    } else {
        document.getElementById('total-usd').innerText = totalFinalUSD.toFixed(2);
        document.getElementById('total-bs').innerText = totalFinalBs.toLocaleString('es-VE', {minimumFractionDigits:2});
    }
    
    document.getElementById('input_items').value = JSON.stringify(carrito);
    document.getElementById('avance_entregado_hidden').value = montoAvanceEntregado;
    document.getElementById('avance_porcentaje_hidden').value = porcentajeAvance;
    document.getElementById('avance_total_usd_hidden').value = totalAvanceUSD.toFixed(2);

    const status = document.getElementById('pago-status');
    const btn = document.getElementById('btn-procesar');
    
    if(carrito.length === 0 && montoAvanceEntregado === 0) {
        status.innerText = "ESPERANDO PRODUCTOS";
        status.style.background = "#f1f5f9"; status.style.color = "var(--slate)";
        btn.disabled = true;
    } else if(validado) {
        status.innerText = "✅ PAGO VALIDADO";
        status.style.background = "#dcfce7"; status.style.color = "#15803d";
        btn.disabled = false;
        btn.innerText = "PROCESAR COBRO";
        btn.type = "submit";
        btn.onclick = null;
    } else {
        status.innerText = "⚠️ PAGO PENDIENTE";
        status.style.background = "#fee2e2"; status.style.color = "#b91c1c";
        btn.disabled = false; 
        btn.innerText = "VALIDAR PAGO";
        btn.type = "button"; 
        btn.onclick = function() { verificarMetodo(document.getElementById('metodo_pago').value); };
    }
}

// --- VERIFICACIÓN DE MÉTODOS Y MODALES ---
function verificarMetodo(v) {
    if(carrito.length === 0 && montoAvanceEntregado === 0) return;
    
    const totalUSD = parseFloat(document.getElementById('total-usd').innerText);
    const totalBS_exacto = document.getElementById('total-bs').innerText;

    if(v === "Pago Móvil") {
        document.getElementById('pm_total_display').innerText = totalBS_exacto + " Bs.";
        document.getElementById('pm_banco_emisor').value = "";
        document.getElementById('pm_referencia').value = "";
        document.getElementById('modalPagoMovil').style.display = "flex";
        document.getElementById('pm_referencia').focus();
    }
    else if(v === "Efectivo Bs.") {
        document.getElementById('bs_total_display').innerText = totalBS_exacto + " Bs.";
        document.getElementById('bs_recibido').value = "";
        document.getElementById('vuelto_box_bs').style.display = "none";
        if(document.getElementById('btn-confirmar-bs')) {
            document.getElementById('btn-confirmar-bs').disabled = true;
            document.getElementById('btn-confirmar-bs').style.opacity = "0.5";
        }
        document.getElementById('modalBsEfectivo').style.display = "flex";
        document.getElementById('bs_recibido').focus();
    } 
    else if(v === "Efectivo $") {
        document.getElementById('d_total_display').innerText = "$ " + totalUSD.toFixed(2);
        document.getElementById('d_recibido').value = "";
        document.getElementById('vuelto_box_dolar').style.display = "none";
        if(document.getElementById('btn-confirmar-d')) {
            document.getElementById('btn-confirmar-d').disabled = true;
            document.getElementById('btn-confirmar-d').style.opacity = "0.5";
        }
        document.getElementById('modalDolarEfectivo').style.display = "flex";
        document.getElementById('d_recibido').focus();
    } 
    else if(v === "Cashea") {
        document.getElementById('modalCashea').style.display = "flex";
        calcularEstructuraCashea('manual');
    }
    else if(v === "Multiple") {
        const totalBS_num = parseFloat(totalBS_exacto.replace(/\./g, '').replace(',', '.'));
        
        document.getElementById('calc_total_fijo').innerText = "$ " + totalUSD.toFixed(2);
        document.getElementById('calc_total_fijo_bs').innerText = totalBS_num.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
        document.getElementById('modalMultiple').style.display = "flex";
        
        document.getElementById('p_bs_efec').value = "";
        document.getElementById('p_dolar').value = "";
        document.getElementById('p_punto').value = "";
        document.getElementById('p_pagomovil').value = "";
        document.getElementById('p_biopago').value = ""; 
        
        calcularRestante(); 
    }
    else if(v === "Crédito") {
        document.getElementById('modalSeleccionarCliente').style.display = "flex";
    } 
    else if (v === "Punto" || v === "BioPago") { 
        document.getElementById('pago_referencia_form').value = "N/A";
        document.getElementById('pago_validado').value = "1";
        actualizarVista();
        guardarStorage();
    }
}

function confirmarPagoMovil() {
    const banco = document.getElementById('pm_banco_emisor').value.trim();
    const ref = document.getElementById('pm_referencia').value.trim();
    const totalUSD = parseFloat(document.getElementById('total-usd').innerText) || 0;
    const totalBS = parseFloat(document.getElementById('total-bs').innerText.replace(/\./g, '').replace(',', '.')) || 0;

    if (!banco || !ref) {
        Swal.fire("Atención", "Debe ingresar el banco emisor y el número de referencia.", "warning");
        return;
    }

    const detallePM = {
        metodo_pago: "Pago Móvil",
        pm_banco_emisor: banco,
        pm_referencia: ref,
        monto_bs: totalBS,
        monto_usd: totalUSD
    };

    document.getElementById('detalle_pagos_multiples').value = JSON.stringify(detallePM);
    
    if (document.getElementById('pago_referencia_form')) {
        document.getElementById('pago_referencia_form').value = ref;
    }
    
    confirmarPago();
}

// --- CASHEA Y OTROS MÉTODOS ---
function calcularEstructuraCashea(origen) {
    const totalBase = parseFloat(document.getElementById('total-usd').innerText) || 0;
    if (totalBase <= 0) return;

    let extraManual = parseFloat(document.getElementById('cashea_extra_manual').value) || 0;
    let extraPorcentaje = parseFloat(document.getElementById('cashea_extra_porcentaje').value) || 0;

    if (origen === 'manual') {
        extraPorcentaje = (extraManual / totalBase) * 100;
        document.getElementById('cashea_extra_porcentaje').value = extraPorcentaje ? extraPorcentaje.toFixed(1) : "";
    } else if (origen === 'percentage') {
        extraManual = totalBase * (extraPorcentaje / 100);
        document.getElementById('cashea_extra_manual').value = extraManual ? extraManual.toFixed(2) : "";
    }

    const totalFinalUSD = totalBase + extraManual;

    document.getElementById('cashea_total_display').innerText = `$ ${totalBase.toFixed(2)}`;
    document.getElementById('cashea_total_final_display').innerText = `$ ${totalFinalUSD.toFixed(2)}`;

    if (!document.getElementById('cashea_inicial_manual').value) {
        document.getElementById('cashea_inicial_manual').value = (totalFinalUSD / 2).toFixed(2);
    }

    actualizarRestanteCashea();
}

function actualizarRestanteCashea() {
    const totalBase = parseFloat(document.getElementById('total-usd').innerText) || 0;
    const extraManual = parseFloat(document.getElementById('cashea_extra_manual').value) || 0;
    const totalFinalUSD = totalBase + extraManual;

    const inicialRecibidaUSD = parseFloat(document.getElementById('cashea_inicial_manual').value) || 0;
    const quedaPorCobrarUSD = totalFinalUSD - inicialRecibidaUSD;

    const displayRestante = document.getElementById('cashea_restante_display');
    displayRestante.innerText = `$ ${quedaPorCobrarUSD.toFixed(2)}`;

    if (quedaPorCobrarUSD < 0) {
        displayRestante.style.color = "var(--amber)";
    } else {
        displayRestante.style.color = "var(--red)";
    }

    const metodoInicial = document.getElementById('cashea_metodo_inicial').value;
    const desgloseBox = document.getElementById('cashea_desglose_monedas');

    if (metodoInicial !== 'Efectivo $') {
        const inicialBs = inicialRecibidaUSD * tasa;
        desgloseBox.style.display = 'block';
        desgloseBox.innerText = `Equivalente de la Inicial a registrar: Bs. ${inicialBs.toLocaleString('es-VE', {minimumFractionDigits: 2, maximumFractionDigits: 2})}`;
    } else {
        desgloseBox.style.display = 'none';
    }
}

function confirmarCashea() {
    const totalBase = parseFloat(document.getElementById('total-usd').innerText) || 0;
    const extraManual = parseFloat(document.getElementById('cashea_extra_manual').value) || 0;
    const totalFinalUSD = totalBase + extraManual;
    const inicialRecibidaUSD = parseFloat(document.getElementById('cashea_inicial_manual').value) || 0;
    const metodoInicial = document.getElementById('cashea_metodo_inicial').value;

    if (inicialRecibidaUSD <= 0) {
        Swal.fire("Atención", "Debe ingresar un monto válido para la inicial de Cashea.", "warning");
        return;
    }

    const datosCashea = {
        metodo_pago: "Cashea",
        recargo_usd: extraManual,
        inicial_usd: inicialRecibidaUSD,
        financiamiento_cashea_usd: (totalFinalUSD - inicialRecibidaUSD),
        metodo_inicial: metodoInicial
    };
    
    document.getElementById('detalle_pagos_multiples').value = JSON.stringify(datosCashea);
    document.getElementById('pago_referencia_form').value = "Cashea Plan";
    document.getElementById('pago_validado').value = "1";

    cerrarModales();
    actualizarVista();
    
    const tasaSistema = parseFloat(document.getElementsByName('tasa')[0]?.value) || 1;
    const inicialEnBs = inicialRecibidaUSD * tasaSistema;

    document.getElementById('total-usd').innerText = inicialRecibidaUSD.toFixed(2);
    document.getElementById('total-bs').innerText = inicialEnBs.toLocaleString('es-VE', {minimumFractionDigits: 2});

    guardarStorage();
    Swal.fire("¡Registro Exitoso!", "Estructura de Plan Cashea vinculada.", "success");
}

function calcularVueltoDolar() {
    const total = parseFloat(document.getElementById('total-usd').innerText);
    const rec = parseFloat(document.getElementById('d_recibido').value) || 0;
    const box = document.getElementById('vuelto_box_dolar');
    const btn = document.getElementById('btn-confirmar-d');
    
    if(rec >= total) {
        let v_usd = rec - total;
        let v_bs = v_usd * tasa;
        box.style.display = "block";
        document.getElementById('v_usd').innerText = "$ " + v_usd.toFixed(2);
        document.getElementById('v_bs').innerText = v_bs.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
        btn.disabled = false; 
        btn.style.opacity = "1";
    } else { 
        box.style.display = "none"; 
        btn.disabled = true; 
        btn.style.opacity = "0.5"; 
    }
}

function calcularVueltoBs() {
    const totalPantallaStr = document.getElementById('total-bs').innerText.replace(/\./g, '').replace(',', '.');
    const totalBS = parseFloat(totalPantallaStr) || 0;
    const recibido = parseFloat(document.getElementById('bs_recibido').value) || 0;
    
    const btn = document.getElementById('btn-confirmar-bs');
    const vueltoBox = document.getElementById('vuelto_box_bs');
    const vueltoTexto = document.getElementById('v_bs_final');

    if (recibido >= totalBS) {
        let vuelto = recibido - totalBS;
        vueltoBox.style.display = "block";
        vueltoTexto.innerText = vuelto.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
        btn.disabled = false;
        btn.style.opacity = "1";
    } else {
        vueltoBox.style.display = "none";
        btn.disabled = true;
        btn.style.opacity = "0.5";
    }
}

function calcularRestante() {
    const totalVentaUSD = parseFloat(document.getElementById('total-usd').innerText) || 0;
    const totalVentaBS_str = document.getElementById('total-bs').innerText.replace(/\./g, '').replace(',', '.');
    const totalVentaBS = parseFloat(totalVentaBS_str) || 0;

    const bsEfec = parseFloat(document.getElementById('p_bs_efec').value) || 0;
    const usdEfec = parseFloat(document.getElementById('p_dolar').value) || 0;
    const bsPunto = parseFloat(document.getElementById('p_punto').value) || 0; 
    const bsPMovil = parseFloat(document.getElementById('p_pagomovil').value) || 0; 
    const bsBioPago = parseFloat(document.getElementById('p_biopago').value) || 0;

    const totalPagadoBS = bsEfec + (usdEfec * tasa) + bsPunto + bsPMovil + bsBioPago;
    const diferenciaBS = parseFloat((totalVentaBS - totalPagadoBS).toFixed(2));

    const totalPagadoUSD = parseFloat((totalPagadoBS / tasa).toFixed(2));
    
    document.getElementById('calc_pagado').innerText = "$ " + totalPagadoUSD.toFixed(2);
    document.getElementById('calc_pagado_bs').innerText = totalPagadoBS.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
    
    const lblStatus = document.getElementById('lbl_status');
    const lblStatusBs = document.getElementById('lbl_status_bs');
    const resBox = document.getElementById('box-resultado-pago');
    const btn = document.getElementById('btn-confirmar-m');

    if (diferenciaBS > 0.01) { 
        const diferenciaUSD = parseFloat((diferenciaBS / tasa).toFixed(2));
        lblStatus.innerText = "Falta en $:";
        lblStatusBs.innerText = "Falta en Bs:";
        document.getElementById('calc_diferencia_usd').innerText = "$ " + diferenciaUSD.toFixed(2);
        document.getElementById('calc_diferencia_bs').innerText = diferenciaBS.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
        resBox.style.color = "red";
        btn.disabled = true; 
        btn.style.opacity = "0.5";
    } else if (diferenciaBS < -0.01) { 
        const vueltoBS = Math.abs(diferenciaBS);
        const vueltoUSD = parseFloat((vueltoBS / tasa).toFixed(2));
        lblStatus.innerText = "Vuelto en $:";
        lblStatusBs.innerText = "Vuelto en Bs:";
        document.getElementById('calc_diferencia_usd').innerText = "$ " + vueltoUSD.toFixed(2);
        document.getElementById('calc_diferencia_bs').innerText = vueltoBS.toLocaleString('es-VE', {minimumFractionDigits: 2}) + " Bs.";
        resBox.style.color = "green";
        btn.disabled = false; 
        btn.style.opacity = "1";
    } else { 
        lblStatus.innerText = "Pago Exacto en $:";
        lblStatusBs.innerText = "Saldo en Bs:";
        document.getElementById('calc_diferencia_usd').innerText = "$ 0.00";
        document.getElementById('calc_diferencia_bs').innerText = "0,00 Bs.";
        resBox.style.color = "blue";
        btn.disabled = false; 
        btn.style.opacity = "1";
    }
}

function confirmarPago() { 
    document.getElementById('pago_validado').value = "1";
    cerrarModales(); 
    actualizarVista();
    guardarStorage();
}

function confirmarMultiple() {
    const detalle = { 
        efectivo_bs: parseFloat(document.getElementById('p_bs_efec').value) || 0, 
        efectivo_usd: parseFloat(document.getElementById('p_dolar').value) || 0, 
        punto_bs: parseFloat(document.getElementById('p_punto').value) || 0, 
        pagomovil_bs: parseFloat(document.getElementById('p_pagomovil').value) || 0,
        biopago_bs: parseFloat(document.getElementById('p_biopago').value) || 0 
    };
    document.getElementById('detalle_pagos_multiples').value = JSON.stringify(detalle);
    confirmarPago();
}

function confirmarVentaCredito() {
    let sel = document.getElementById('select_cliente_credito');
    if(!sel.value) return;
    let disp = parseFloat(sel.options[sel.selectedIndex].getAttribute('data-disponible'));
    let tot = parseFloat(document.getElementById('total-usd').innerText);
    if(tot > disp) { Swal.fire("Error", "Crédito insuficiente", "error"); return; }
    const nombreSeleccionado = sel.options[sel.selectedIndex].text.split(' (')[0];
    document.getElementById('id_cliente_credito').value = sel.value; 
    document.getElementById('nombre_cliente_input').value = nombreSeleccionado;
    const Toast = Swal.mixin({ toast: true, position: 'top-end', showConfirmButton: false, timer: 2500, timerProgressBar: true });
    Toast.fire({ icon: 'success', title: `Cliente ${nombreSeleccionado} asignado correctamente` });
    confirmarPago();
}

function filtrarClientesModal() {
    let input = document.getElementById('buscar_cliente_modal').value.toLowerCase();
    let select = document.getElementById('select_cliente_credito');
    let opciones = select.getElementsByTagName('option');
    if (input === "") {
        for (let i = 0; i < opciones.length; i++) { opciones[i].style.display = ""; }
        select.selectedIndex = 0; return;
    }
    let encontrada = false;
    for (let i = 1; i < opciones.length; i++) {
        let texto = opciones[i].text.toLowerCase();
        if (texto.includes(input)) {
            opciones[i].style.display = "";
            if (!encontrada) { select.selectedIndex = i; encontrada = true; }
        } else { opciones[i].style.display = "none"; }
    }
}

// --- UTILIDADES ---
function esProductoPesado(p) {
    if(!p) return false;
    const u = (p.unidad || "").toLowerCase();
    return u.includes('kg') || u.includes('gr') || u.includes('kilo');
}

function cerrarModales() { 
    document.querySelectorAll('.modal').forEach(m => m.style.display = "none"); 
}

function abrirBascula() {
    document.getElementById('modalBascula').style.display = "flex";
    document.getElementById('b_nombre_prod').innerText = pTemporal.nombre;
    document.getElementById('b_gramos').value = ""; document.getElementById('b_kilos').value = "";
    document.getElementById('b_monto_calc').innerText = "$ 0.00";
}

function calcularPeso(origen) {
    let k = 0;
    if(origen === 'g') {
        k = (parseFloat(document.getElementById('b_gramos').value) || 0) / 1000;
        document.getElementById('b_kilos').value = k.toFixed(3);
    } else {
        k = parseFloat(document.getElementById('b_kilos').value) || 0;
        document.getElementById('b_gramos').value = (k * 1000).toFixed(0);
    }
    document.getElementById('b_monto_calc').innerText = "$ " + (k * pTemporal.precio).toFixed(2);
}

function confirmarBascula() {
    const k = parseFloat(document.getElementById('b_kilos').value) || 0;
    if(k > 0) { agregarItem(pTemporal.id, pTemporal.nombre, pTemporal.precio, pTemporal.stock, k, pTemporal.usa_fiscal); cerrarModales(); }
}

function abrirBuscadorModal() {
    document.getElementById('modalBuscador').style.display = "flex";
    const inp = document.getElementById('inputBuscadorModal');
    inp.value = ""; inp.focus();
    document.getElementById('listaBuscadorModal').innerHTML = "";
}

function filtrarProductosModal(texto) {
    const lista = document.getElementById('listaBuscadorModal');
    const busqueda = texto.toLowerCase().trim();
    const filtrados = productos.filter(p => 
        p.nombre.toLowerCase().includes(busqueda) || 
        p.codigo.toLowerCase().includes(busqueda)
    ).slice(0, 20);

    if (filtrados.length === 0) {
        lista.innerHTML = '<div style="padding:20px; text-align:center; color:gray;">No se encontraron productos</div>';
        return;
    }

    lista.innerHTML = filtrados.map(p => `
        <div class="item-resultado" onclick="seleccionarProducto(${p.id}); cerrarModales();" style="display:grid; grid-template-columns: 1fr 100px; padding:15px; border-bottom:1px solid #eee;">
            <div>
                <b style="color:var(--blue);">${p.nombre}</b><br>
                <small>Código: ${p.codigo} | <b>Stock: ${p.stock}</b></small>
            </div>
            <div style="text-align:right;">
                <b style="font-size:1.1rem;">$${p.precio.toFixed(2)}</b><br>
                <small style="color:var(--slate);">Bs. ${(p.precio * tasa).toLocaleString('es-VE')}</small>
            </div>
        </div>
    `).join('');
}

function finalizarVenta(e) {
    const validado = document.getElementById('pago_validado').value === "1";
    if(!validado) {
        e.preventDefault(); 
        Swal.fire("Atención", "Debe validar el pago antes de procesar el cobro", "warning"); 
        return false;
    }

    // Concatenar todos los datos del cliente para enviarlo al backend
    const clienteNombre = document.getElementById('nombre_cliente_input').value.trim();
    const clienteRif = document.getElementById('rif_cliente_input').value.trim();
    const clienteDir = document.getElementById('direccion_cliente_input').value.trim();
    const clienteTel = document.getElementById('telefono_cliente_input').value.trim();
    
    let clienteFormateado = clienteNombre || "Público General";
    
    if(clienteRif !== "") clienteFormateado += ` | RIF/CI: ${clienteRif}`;
    if(clienteDir !== "") clienteFormateado += ` | Dir: ${clienteDir}`;
    if(clienteTel !== "") clienteFormateado += ` | Tel: ${clienteTel}`;

    document.getElementById('cliente_hidden_final').value = clienteFormateado;

    facturas[facturaActiva].items = []; 
    facturas[facturaActiva].validado = "0";
    facturas[facturaActiva].avance_entregado = 0;
    localStorage.setItem('pos_facturas', JSON.stringify(facturas));
    return true; 
}

// --- ATAJOS DE TECLADO ---
document.addEventListener('keydown', function(e) {
    const enInput = e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT';
    if (e.key === 'F9') { e.preventDefault(); crearNuevaFactura(); }
    if (e.key === 'F10') { e.preventDefault(); abrirBuscadorModal(); }
    if (e.key === 'Escape') { cerrarModales(); }
    if (e.target.id === 'lector' && e.key === 'Enter') {
        e.preventDefault();
        const valor = e.target.value.trim();
        if(valor !== "") buscarProducto(valor);
    }
    if (!enInput || e.target.id === 'lector') {
        if (e.key === '+' || e.key === 'Add') { e.preventDefault(); ajustarCantidadUltimoItem(1); }
        if (e.key === '-' || e.key === 'Subtract') { e.preventDefault(); ajustarCantidadUltimoItem(-1); }
    }
});
</script>
</body>
    <?php include_once __DIR__ . '/sidebar.php'; ?>
</html>
