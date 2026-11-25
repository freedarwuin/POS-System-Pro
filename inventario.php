<?php
session_start();
include 'conexion.php';
@include_once 'validar_licencia.php'; 

// 1. SEGURIDAD
if (!isset($_SESSION['usuario'])) { 
    header("Location: login.php"); 
    exit(); 
}
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}
$usuario_activo = $_SESSION['usuario'];

// --- CONFIGURACIÓN, TASA Y PARÁMETROS FISCALES ---
$res_config = mysqli_query($conexion, "SELECT * FROM configuracion WHERE id = 1");
$config_data = mysqli_fetch_assoc($res_config);
$tasa_actual = $config_data['tasa_dolar'] ?? 1;

// Configuración de impuestos globales
$usar_iva = $config_data['usar_iva'] ?? 0;
$porcentaje_iva = $config_data['impuesto_iva'] ?? 0;
$usar_igtf = $config_data['usar_igtf'] ?? 0;
$porcentaje_igtf = $config_data['impuesto_igtf'] ?? 0;

// --- PAGINACIÓN ---
$prod_por_pagina = 10; 
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$inicio_limit = ($pagina_actual - 1) * $prod_por_pagina;

// --- PROCESAMIENTO PHP ---

// AÑADIR DEPARTAMENTO
if (isset($_POST['add_depto'])) {
    $nombre_depto = trim($_POST['nombre_depto']);
    $stmt = mysqli_prepare($conexion, "INSERT INTO departamentos (nombre) VALUES (?)");
    mysqli_stmt_bind_param($stmt, "s", $nombre_depto);
    mysqli_stmt_execute($stmt);
    header("Location: inventario.php"); exit();
}

// EDITAR DEPARTAMENTO
if (isset($_POST['edit_depto'])) {
    $id_dep = (int)$_POST['id_depto_edit'];
    $nuevo_n = trim($_POST['nombre_edit']);
    $stmt = mysqli_prepare($conexion, "UPDATE departamentos SET nombre = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $nuevo_n, $id_dep);
    mysqli_stmt_execute($stmt);
    header("Location: inventario.php"); exit();
}

// ELIMINAR DEPARTAMENTO
if (isset($_GET['del_depto'])) {
    $id_del = (int)$_GET['del_depto'];
    
    $stmt1 = mysqli_prepare($conexion, "UPDATE productos SET id_departamento = NULL WHERE id_departamento = ?");
    mysqli_stmt_bind_param($stmt1, "i", $id_del);
    mysqli_stmt_execute($stmt1);
    
    $stmt2 = mysqli_prepare($conexion, "DELETE FROM departamentos WHERE id = ?");
    mysqli_stmt_bind_param($stmt2, "i", $id_del);
    mysqli_stmt_execute($stmt2);
    
    header("Location: inventario.php"); exit();
}

// ACTUALIZAR TASA
if (isset($_POST['actualizar_tasa'])) {
    $nt = str_replace(',', '.', $_POST['nueva_tasa']);
    $stmt = mysqli_prepare($conexion, "UPDATE configuracion SET tasa_dolar = ? WHERE id = 1");
    mysqli_stmt_bind_param($stmt, "s", $nt);
    mysqli_stmt_execute($stmt);
    header("Location: inventario.php"); exit();
}

// REGISTRAR PRODUCTO
if (isset($_POST['add_prod'])) {
    $c = trim($_POST['codigo']);
    
    $stmt_check = mysqli_prepare($conexion, "SELECT id FROM productos WHERE codigo_barras = ?");
    mysqli_stmt_bind_param($stmt_check, "s", $c);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $_SESSION['error_duplicado'] = "ESTE PRODUCTO YA SE ENCUENTRA REGISTRADO [$c].";
        header("Location: inventario.php"); exit();
    }
    mysqli_stmt_close($stmt_check);

    $n = trim($_POST['nombre']);
    $pc = (float)str_replace(',', '.', $_POST['costo']); 
    $pv = (float)str_replace(',', '.', $_POST['venta']);
    $usa_inv = isset($_POST['usa_inventario']) ? 1 : 0;
    $usa_fiscal = isset($_POST['usa_parametros_fiscales']) ? 1 : 0;
    $s = ($usa_inv == 1) ? (float)str_replace(',', '.', $_POST['stock']) : 0;
    $tipo_v = $_POST['tipo_venta'];
    $unidad_m = $_POST['unidad_medida'];
    $depto = $_POST['departamento'] != "" ? (int)$_POST['departamento'] : null;
    $id_prov = $_POST['proveedor'] != "" ? (int)$_POST['proveedor'] : null; 
    $moneda = $_POST['moneda'];

    $sql = "INSERT INTO productos (codigo_barras, nombre, precio_costo, precio, stock, tipo_venta, unidad_medida, id_departamento, id_proveedor, usa_inventario, usa_parametros_fiscales, moneda) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt_ins, "ssdddssiiiis", $c, $n, $pc, $pv, $s, $tipo_v, $unidad_m, $depto, $id_prov, $usa_inv, $usa_fiscal, $moneda);
    
    if (mysqli_stmt_execute($stmt_ins)) {
        $id_nuevo = mysqli_insert_id($conexion);
        $detalle_hist = ($usa_inv == 1) ? "Registro Inicial ($unidad_m)" : "Registro como Servicio (Sin Stock)";
        
        $stmt_hist = mysqli_prepare($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario) VALUES (?, 'ENTRADA', ?, ?, ?)");
        mysqli_stmt_bind_param($stmt_hist, "idss", $id_nuevo, $s, $detalle_hist, $usuario_activo);
        mysqli_stmt_execute($stmt_hist);
    }
    header("Location: inventario.php"); exit();
}

// ACTUALIZAR PRODUCTO
if (isset($_POST['update_prod'])) {
    $id = (int)$_POST['id_edit']; 
    $n = trim($_POST['n_edit']);
    $pc_nuevo = (float)str_replace(',', '.', $_POST['pc_edit']);
    $pv_nuevo = (float)str_replace(',', '.', $_POST['pv_edit']);
    $s_input  = (float)str_replace(',', '.', $_POST['s_edit']);
    $tipo_v = $_POST['tipo_v_edit']; 
    $unidad_m = $_POST['unidad_m_edit'];
    $depto = $_POST['depto_edit'] != "" ? (int)$_POST['depto_edit'] : null;
    $id_prov = $_POST['proveedor_edit'] != "" ? (int)$_POST['proveedor_edit'] : null; 
    $usa_fiscal = isset($_POST['usa_parametros_fiscales_edit']) ? 1 : 0;
    $moneda = $_POST['moneda_edit'];
    $p_retorno = isset($_POST['pagina_retorno']) ? (int)$_POST['pagina_retorno'] : 1;

    $stmt_ant = mysqli_prepare($conexion, "SELECT stock, precio, moneda FROM productos WHERE id = ?");
    mysqli_stmt_bind_param($stmt_ant, "i", $id);
    mysqli_stmt_execute($stmt_ant);
    $res_ant = mysqli_stmt_get_result($stmt_ant);
    $p_ant = mysqli_fetch_assoc($res_ant);
    $stock_actual_db = (float)$p_ant['stock'];

    $diferencia_stock = $s_input - $stock_actual_db;

    $sql = "UPDATE productos SET nombre=?, precio_costo=?, precio=?, stock = stock + ?, tipo_venta=?, unidad_medida=?, id_departamento=?, id_proveedor=?, usa_parametros_fiscales=?, moneda=? WHERE id=?";
    $stmt_upd = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt_upd, "sdddssiisii", $n, $pc_nuevo, $pv_nuevo, $diferencia_stock, $tipo_v, $unidad_m, $depto, $id_prov, $usa_fiscal, $moneda, $id);

    if (mysqli_stmt_execute($stmt_upd)) {
        header("Location: inventario.php?p=$p_retorno&msj=OK");
    } else {
        header("Location: inventario.php?p=$p_retorno&error=db_error");
    }
    exit();
}

// ELIMINAR PRODUCTO
if (isset($_GET['eliminar'])) {
    $id_del = (int)$_GET['eliminar'];
    $p_retorno = isset($_GET['p_actual']) ? (int)$_GET['p_actual'] : 1;
    
    $stmt_p = mysqli_prepare($conexion, "SELECT nombre FROM productos WHERE id = ?");
    mysqli_stmt_bind_param($stmt_p, "i", $id_del);
    mysqli_stmt_execute($stmt_p);
    $res_p = mysqli_stmt_get_result($stmt_p);
    
    if ($p_del = mysqli_fetch_assoc($res_p)) {
        $detalle_el = "ELIMINADO: " . $p_del['nombre'];
        $stmt_h3 = mysqli_prepare($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario) VALUES (?, 'ELIMINACIÓN', '0', ?, ?)");
        mysqli_stmt_bind_param($stmt_h3, "iss", $id_del, $detalle_el, $usuario_activo);
        mysqli_stmt_execute($stmt_h3);
        
        $stmt_del = mysqli_prepare($conexion, "DELETE FROM productos WHERE id = ?");
        mysqli_stmt_bind_param($stmt_del, "i", $id_del);
        mysqli_stmt_execute($stmt_del);

        $res_conteo_post = mysqli_query($conexion, "SELECT COUNT(*) as total FROM productos");
        $total_post = mysqli_fetch_assoc($res_conteo_post)['total'];
        $total_paginas_post = ceil($total_post / $prod_por_pagina);

        if ($p_retorno > $total_paginas_post && $total_paginas_post > 0) {
            $p_retorno = $total_paginas_post;
        }
    }
    header("Location: inventario.php?p=$p_retorno"); 
    exit();
}

// EXPORTAR STOCK BAJO
if (isset($_GET['exportar_bajo_stock'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Reporte_Stock_Bajo_' . date('d-m-Y') . '.csv');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['Código', 'Producto', 'Departamento', 'Stock', 'Costo', 'Venta USD', 'Venta BS'], ';');
    
    $query_export = "SELECT p.*, d.nombre as nombre_depto FROM productos p 
                     LEFT JOIN departamentos d ON p.id_departamento = d.id 
                     WHERE p.usa_inventario = 1 AND p.stock <= 5 ORDER BY p.stock ASC";
    $res_export = mysqli_query($conexion, $query_export);
    
    while ($row = mysqli_fetch_assoc($res_export)) {
        $p_usd = ($row['moneda'] === 'BS') ? $row['precio'] / $tasa_actual : $row['precio'];
        $p_bs = ($row['moneda'] === 'BS') ? $row['precio'] : $row['precio'] * $tasa_actual;
        fputcsv($output, [$row['codigo_barras'], $row['nombre'], $row['nombre_depto'] ?? 'Gral', $row['stock'], $row['precio_costo'], number_format($p_usd,2), number_format($p_bs,2)], ';');
    }
    fclose($output); exit();
}

// --- DATOS PARA LA VISTA ---
$res_conteo = mysqli_query($conexion, "SELECT COUNT(*) as total FROM productos");
$total_productos = mysqli_fetch_assoc($res_conteo)['total'];
$total_paginas = ceil($total_productos / $prod_por_pagina);

$deptos_query = mysqli_query($conexion, "SELECT * FROM departamentos ORDER BY nombre ASC");
$deptos_array = []; 
while($d = mysqli_fetch_assoc($deptos_query)) { $deptos_array[] = $d; }

$prov_query = mysqli_query($conexion, "SELECT id, nombre_empresa FROM proveedores ORDER BY nombre_empresa ASC");
$proveedores_array = [];
while($pr = mysqli_fetch_assoc($prov_query)) { $proveedores_array[] = $pr; }

$productos = mysqli_query($conexion, "SELECT p.*, d.nombre as nombre_depto 
    FROM productos p LEFT JOIN departamentos d ON p.id_departamento = d.id 
    ORDER BY p.id DESC LIMIT $inicio_limit, $prod_por_pagina");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventario Pro</title>
    <style>
        :root { 
            --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #334155; --text-sub: #64748b; 
            --border: #e2e8f0; --table-head: #f1f5f9; --dark: #0f172a; --blue: #3b82f6; 
            --blue-hover: #1d4ed8; --red: #ef4444; --green: #10b981; --amber: #f59e0b; --slate: #475569; 
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 24px; -webkit-font-smoothing: antialiased; }
        .toast-error { position: fixed; top: 20px; right: 20px; background: #fee2e2; color: #b91c1c; padding: 16px 24px; border-radius: 12px; border-left: 5px solid #ef4444; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 9999; display: flex; align-items: center; gap: 12px; font-weight: 600; animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .header-container { background: linear-gradient(135deg, var(--dark) 0%, #1e293b 100%); padding: 20px 30px; color: white; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .toolbar { display: flex; justify-content: space-between; background: var(--bg-card); padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 1px solid var(--border); align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.02); }
        .tasa-box { display: flex; align-items: center; background: var(--table-head); padding: 8px 16px; border-radius: 10px; border: 1px solid var(--border); font-weight: 600; }
        .tabla-container { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.02); }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: var(--table-head); padding: 16px 20px; font-size: 12px; color: var(--text-sub); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border); font-weight: 700; }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        tr.fila:hover { background-color: #f8fafc; transition: background 0.2s ease; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; color: white; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 13px; transition: all 0.2s ease; }
        .btn:hover { filter: brightness(1.1); transform: translateY(-1px); }
        .btn-action { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; color: white; display: inline-flex; align-items: center; justify-content: center; transition: all 0.2s ease; }
        .btn-action:hover { transform: scale(1.08); }
        input, select { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); background: #f8fafc; color: var(--text-main); box-sizing: border-box; font-size: 14px; transition: all 0.2s ease; }
        input:focus, select:focus { background: #fff; border-color: var(--blue); outline: none; box-shadow: 0 0 0 3px rgba(59, 131, 246, 0.15); }
        .badge-stock { background: #fef2f2; color: #ef4444; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #fee2e2; display: inline-block; margin-top: 4px; }
        .badge-tipo { background: #f1f5f9; color: #475569; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 600; text-transform: uppercase; }
        .badge-servicio { background: #e0e7ff; color: #4338ca; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #c7d2fe; }
        .badge-moneda { background: #fef9c3; color: #854d0e; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #fef08a; }
        .badge-fiscal { background: #dcfce7; color: #166534; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #bbf7d0; display: inline-block; margin-top: 4px; }
        .badge-nofiscal { background: #f1f5f9; color: #64748b; padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; border: 1px solid #e2e8f0; display: inline-block; margin-top: 4px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(8px); animation: fadeIn 0.2s ease; }
        .modal-content { background: var(--bg-card); margin: 60px auto; padding: 32px; width: 550px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 80vh; overflow-y: auto; position: relative; }
        @keyframes fadeIn { from { opacity: 0; } to { transform: translateX(0); opacity: 1; } }
        .triple-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; background: #f8fafc; padding: 16px; border-radius: 14px; margin-bottom: 16px; border: 1px solid var(--border); }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin: 30px 0; }
        .page-link { padding: 10px 18px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text-main); text-decoration: none; font-weight: 600; transition: 0.2s; font-size: 13px; }
        .page-link:hover { background: var(--table-head); border-color: var(--blue); color: var(--blue); }
        .page-link.active { background: var(--blue); color: white; border-color: var(--blue); }
        .page-info { font-size: 13px; color: var(--text-sub); margin-right: 16px; font-weight: 500; }
        .switch-container { display: flex; align-items: center; gap: 12px; background: #f8fafc; padding: 14px; border-radius: 12px; border: 1px solid var(--border); margin-bottom: 20px; }
        .switch { position: relative; display: inline-block; width: 40px; height: 22px; }
        .switch input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; background-color: #cbd5e1; transition: .3s; border-radius: 22px; }
        .slider:before { position: absolute; content: ""; height: 16px; width: 16px; left: 3px; bottom: 3px; background-color: white; transition: .3s; border-radius: 50%; }
        input:checked + .slider { background-color: var(--blue); }
        input:checked + .slider:before { transform: translateX(18px); }
    </style>
</head>
<body>

    <?php 
    function format_stock($value, $unidad) {
        $val = (float)$value;
        $unidades_con_decimales = ['KG', 'G', 'KILOS', 'GRAMOS'];
        if (in_array(strtoupper($unidad), $unidades_con_decimales)) {
            return rtrim(rtrim(number_format($val, 3, ',', '.'), '0'), ',');
        } else {
            return number_format($val, 0, ',', '.');
        }
    }
    ?>

    <?php if (isset($_SESSION['error_duplicado'])): ?>
        <div class="toast-error" id="errorToast">⚠️ <div><?= htmlspecialchars($_SESSION['error_duplicado']) ?></div></div>
        <script>setTimeout(() => { document.getElementById('errorToast').remove(); }, 4000);</script>
        <?php unset($_SESSION['error_duplicado']); ?>
    <?php endif; ?>

    <div class="header-container">
        <h2 style="margin:0; font-weight: 800; letter-spacing: -0.02em;">📦 Panel de Inventario</h2>
        <div style="display:flex; gap:12px;">
            <button onclick="abrirModal('modalDepto')" class="btn" style="background:var(--slate)">🏢 DEPARTAMENTOS</button>
            <a href="configuracion.php" class="btn" style="background: transparent; border: 1px solid rgba(255,255,255,0.2)">⬅️ Configuración</a>
            <a href="ventas.php" class="btn" style="background:var(--blue)">🛒 IR A CAJA</a>
            <a href="logout.php" class="btn" style="background:var(--red)">🚪 SALIR </a>
        </div>
    </div>

    <div class="toolbar">
        <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
            <button onclick="abrirModal('modalNuevo')" class="btn" style="background:var(--green)">➕ NUEVO PRODUCTO</button>
            <a href="?exportar_bajo_stock=1" class="btn" style="background:#fef08a; color: #854d0e; border: 1px solid #fde047;">📥 EXCEL STOCK BAJO</a>
            
            <form method="POST" class="tasa-box">
                <span style="font-size:13px; color:var(--text-sub); margin-right:8px;">1$ REF =</span>
                <input type="number" step="0.01" name="nueva_tasa" value="<?= $tasa_actual ?>" style="width:70px; border:none; background:transparent; font-weight:700; color:var(--blue); padding:0; font-size:14px;">
                <button type="submit" name="actualizar_tasa" style="background:none; border:none; cursor:pointer; font-size:11px; font-weight:700; color:var(--slate); margin-left:8px; text-decoration: underline;">[OK]</button>
            </form>
        </div>
        <div>
            <input type="text" id="busq_global" style="width:280px;" placeholder="🔍 Buscar en todo el inventario..." onkeyup="buscarGlobal(this.value)">
        </div>
    </div>

    <div class="tabla-container">
        <table>
            <thead>
                <tr>
                    <th>Producto / Departamento</th><th>Tipo</th><th>Precio $</th><th>Precio Bs</th><th>Precio Venta + Impuestos</th><th>Stock</th><th style="text-align:center;">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php while($p = mysqli_fetch_assoc($productos)): 
                    $moneda_p = $p['moneda'] ?? 'USD';
                    if ($moneda_p === 'VES') {
                        $precio_usd_calc = $p['precio'] / $tasa_actual;
                        $precio_bs_calc = $p['precio'];
                        $badge_moneda = '<span class="badge-moneda">FIJO BS</span>';
                    } else {
                        $precio_usd_calc = $p['precio'];
                        $precio_bs_calc = $p['precio'] * $tasa_actual;
                        $badge_moneda = '';
                    }

                    // Cálculo de impuestos (IVA e IGTF si aplica el producto)
                    $aplica_fiscal = ($p['usa_parametros_fiscales'] == 1);
                    $subtotal_usd = $precio_usd_calc;
                    $subtotal_bs = $precio_bs_calc;

                    $monto_iva_usd = 0;
                    $monto_iva_bs = 0;
                    if ($usar_iva == 1 && $aplica_fiscal) {
                        $monto_iva_usd = $subtotal_usd * ($porcentaje_iva / 100);
                        $monto_iva_bs = $subtotal_bs * ($porcentaje_iva / 100);
                    }

                    $total_con_iva_usd = $subtotal_usd + $monto_iva_usd;
                    $total_con_iva_bs = $subtotal_bs + $monto_iva_bs;

                    $monto_igtf_usd = 0;
                    $monto_igtf_bs = 0;
                    if ($usar_igtf == 1 && $aplica_fiscal) {
                        $monto_igtf_usd = $total_con_iva_usd * ($porcentaje_igtf / 100);
                        $monto_igtf_bs = $total_con_iva_bs * ($porcentaje_igtf / 100);
                    }

                    $precio_final_usd = $total_con_iva_usd + $monto_igtf_usd;
                    $precio_final_bs = $total_con_iva_bs + $monto_igtf_bs;
                ?>
                <tr class="fila">
                    <td>
                        <b style="font-size:15px; color: var(--dark);"><?= htmlspecialchars($p['nombre']) ?></b> 
                        <?php if($p['usa_inventario'] == 0): ?> <span class="badge-servicio">SERVICIO</span> <?php endif; ?> 
                        <?= $badge_moneda ?> <br>
                        <span style="color:var(--blue); font-weight:600; font-size:12px;"><?= htmlspecialchars($p['nombre_depto'] ?? 'General') ?></span>
                        <span style="color:var(--text-sub); font-size:12px; margin-left:6px;">• Cod: <?= htmlspecialchars($p['codigo_barras']) ?></span><br>
                        <?php if($aplica_fiscal): ?>
                            <span class="badge-fiscal">✓ CON PARÁMETROS FISCALES</span>
                        <?php else: ?>
                            <span class="badge-nofiscal">✕ SIN PARÁMETROS FISCALES</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge-tipo"><?= htmlspecialchars($p['tipo_venta']) ?></span></td>
                    <td style="color:var(--green); font-weight:700;">
                        <?php if($moneda_p === 'VES'): ?>
                            <span style="color:gray; font-weight:500;">~$<?= number_format($precio_usd_calc, 2) ?></span>
                        <?php else: ?>
                            $<?= number_format($precio_usd_calc, 2) ?>
                        <?php endif; ?>
                    </td>
                    <td style="color:var(--blue); font-weight:700;">
                        Bs <?= number_format($precio_bs_calc, 2, ',', '.') ?>
                    </td>
                    <td style="color:#7c3aed; font-weight:700;">
                        $<?= number_format($precio_final_usd, 2) ?><br>
                        <small style="color:var(--text-sub);">Bs <?= number_format($precio_final_bs, 2, ',', '.') ?></small>
                    </td>
                    <td>
                        <?php if($p['usa_inventario'] == 1): ?>
                            <span style="font-weight:700; font-size:15px;"><?= format_stock($p['stock'], $p['unidad_medida']) ?></span> <small style="color:var(--text-sub)"><?= htmlspecialchars($p['unidad_medida']) ?></small>
                            <?php if($p['stock'] <= 5): ?> <br><span class="badge-stock">STOCK BAJO</span> <?php endif; ?>
                        <?php else: ?>
                            <span style="font-size: 18px; color: var(--slate); font-weight:700;">∞</span> <small style="color:var(--text-sub)">Ilimitado</small>
                        <?php endif; ?>
                    </td>
                    <td style="display:flex; justify-content:center; gap:8px;">
                        <button onclick="verDetalles('<?= $p['codigo_barras'] ?>','<?= addslashes(htmlspecialchars($p['nombre'])) ?>','<?= $p['precio_costo'] ?>','<?= number_format($precio_usd_calc,2) ?>','<?= $p['stock'] ?>','<?= $p['unidad_medida'] ?>','<?= $p['tipo_venta'] ?>','<?= number_format($precio_bs_calc,2,',','.') ?>', '<?= $moneda_p ?>', '<?= number_format($precio_final_usd,2) ?>', '<?= number_format($precio_final_bs,2,',','.') ?>')" class="btn-action" style="background:var(--blue)">👁️</button>
                        <button onclick="abrirE('<?= $p['id'] ?>','<?= $p['codigo_barras'] ?>','<?= addslashes(htmlspecialchars($p['nombre'])) ?>','<?= $p['precio_costo'] ?>','<?= $p['precio'] ?>','<?= $p['stock'] ?>','<?= $p['tipo_venta'] ?>','<?= $p['unidad_medida'] ?>','<?= $p['id_departamento'] ?>', '<?= $moneda_p ?>', '<?= $p['id_proveedor'] ?>', '<?= $p['usa_parametros_fiscales'] ?? 1 ?>', '<?= $pagina_actual ?>')" class="btn-action" style="background:var(--amber)">✏️</button>
                        <a href="?eliminar=<?= $p['id'] ?>&p_actual=<?= $pagina_actual ?>" class="btn-action" style="background:var(--dark);" onclick="return confirm('¿Está seguro de eliminar este producto por completo?')">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <span class="page-info">Página <?= $pagina_actual ?> de <?= $total_paginas ?> (<?= $total_productos ?> artículos)</span>
        <?php if ($pagina_actual > 1): ?>
            <a href="?p=1" class="page-link">«</a>
            <a href="?p=<?= $pagina_actual - 1 ?>" class="page-link">Anterior</a>
        <?php endif; ?>
        <?php
        $rango = 2;
        for ($i = max(1, $pagina_actual - $rango); $i <= min($total_paginas, $pagina_actual + $rango); $i++):
        ?>
            <a href="?p=<?= $i ?>" class="page-link <?= ($i == $pagina_actual) ? 'active' : '' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pagina_actual < $total_paginas): ?>
            <a href="?p=<?= $pagina_actual + 1 ?>" class="page-link">Siguiente</a>
            <a href="?p=<?= $total_paginas ?>" class="page-link">»</a>
        <?php endif; ?>
    </div>

    <!-- MODAL NUEVO ARTÍCULO -->
    <div id="modalNuevo" class="modal"><div class="modal-content">
        <h3 style="margin-top:0; color:var(--green); font-size:20px;">➕ Nuevo Artículo</h3>
        <form method="POST">
            <div class="switch-container">
                <label class="switch">
                    <input type="checkbox" name="usa_inventario" id="checkInv" checked onchange="toggleStockInput(this.checked)">
                    <span class="slider"></span>
                </label>
                <span id="labelInv" style="font-size:13px; font-weight:700; color:var(--blue);">Este producto maneja Inventario</span>
            </div>

            <div class="switch-container" style="margin-top: 10px;">
                <label class="switch">
                    <input type="checkbox" name="usa_parametros_fiscales" id="checkFiscal" checked>
                    <span class="slider"></span>
                </label>
                <span style="font-size:13px; font-weight:700; color:var(--blue);">Aplicar Parámetros Fiscales (IVA / IGTF)</span>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">CÓDIGO</label><input type="text" name="codigo" required></div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">DEPARTAMENTO</label>
                    <select name="departamento">
                        <option value="">-- General --</option>
                        <?php foreach($deptos_array as $d): ?> <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option> <?php endforeach; ?>
                    </select>
                </div>
            </div>
            
            <div style="margin-bottom:15px;">
                <label style="font-size:11px; font-weight:700; color:var(--blue)">🏢 PROVEEDOR ASOCIADO</label>
                <select name="proveedor">
                    <option value="">-- Sin Proveedor Especial --</option>
                    <?php foreach($proveedores_array as $pr): ?> 
                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre_empresa']) ?></option> 
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">NOMBRE DEL PRODUCTO</label><input type="text" name="nombre" required></div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">MONEDA BASE</label>
                    <select name="moneda" id="n_moneda" onchange="cambiarLabelMoneda('n')">
                        <option value="USD">Dólares (USD)</option>
                        <option value="VES">Bolívares (VES)</option>
                    </select>
                </div>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">TIPO VENTA</label>
                    <select name="tipo_venta" onchange="checkTipo(this.value, 'n_s')">
                        <option value="unidad">Unidad</option><option value="granel">Granel</option><option value="paquete">Paquete</option>
                    </select>
                </div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">UNIDAD MEDIDA</label>
                    <select name="unidad_medida">
                        <option value="Unid">Unid</option><option value="KG">KG</option><option value="G">G</option><option value="M">M</option><option value="Serv">Servicio</option>
                    </select>
                </div>
            </div>
            <div class="triple-row">
                <div><label style="font-size:10px; font-weight:700;" id="lbl_nc">COSTO $</label><input type="number" step="0.01" name="costo" id="nc" oninput="calc('n','p')" required></div>
                <div><label style="font-size:10px; font-weight:700;">GAN %</label><input type="number" step="0.1" id="ng" oninput="calc('n','g')"></div>
                <div><label style="font-size:10px; font-weight:700;" id="lbl_nv">VENTA $</label><input type="number" step="0.01" name="venta" id="nv" oninput="calc('n','p')" required></div>
            </div>
            <div id="seccionStock" style="margin-bottom:20px;">
                <label style="font-size:11px; font-weight:700; color:var(--text-sub)">STOCK INICIAL</label>
                <input type="number" step="0.001" name="stock" id="n_s" required>
            </div>
            <button type="submit" name="add_prod" class="btn" style="background:var(--green); width:100%; justify-content:center; padding:12px;">💾 GUARDAR ARTÍCULO</button>
            <button type="button" onclick="cerrarModal('modalNuevo')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:15px; cursor:pointer; font-weight:600;">CANCELAR</button>
        </form>
    </div></div>

    <!-- MODAL EDITAR PRODUCTO -->
    <div id="modalEditar" class="modal"><div class="modal-content">
        <h3 style="margin-top:0; color:var(--amber); font-size:20px;">✏️ Editar Producto</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit">
            <input type="hidden" name="pagina_retorno" id="pag_retorno_edit" value="<?= $pagina_actual ?>">
            
            <div class="switch-container" style="margin-bottom: 15px;">
                <label class="switch">
                    <input type="checkbox" name="usa_parametros_fiscales_edit" id="checkFiscalEdit">
                    <span class="slider"></span>
                </label>
                <span style="font-size:13px; font-weight:700; color:var(--blue);">Aplicar Parámetros Fiscales (IVA / IGTF)</span>
            </div>

            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">NOMBRE</label><input type="text" id="en" name="n_edit" required></div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">MONEDA BASE</label>
                    <select name="moneda_edit" id="emo" onchange="cambiarLabelMoneda('e')">
                        <option value="USD">Dólares (USD)</option>
                        <option value="VES">Bolívares (VES)</option>
                    </select>
                </div>
            </div>

            <div style="margin-bottom:15px;">
                <label style="font-size:11px; font-weight:700; color:var(--blue)">🏢 PROVEEDOR ASOCIADO</label>
                <select name="proveedor_edit" id="epr">
                    <option value="">-- Sin Proveedor Especial --</option>
                    <?php foreach($proveedores_array as $pr): ?> 
                        <option value="<?= $pr['id'] ?>"><?= htmlspecialchars($pr['nombre_empresa']) ?></option> 
                    <?php endforeach; ?>
                </select>
            </div>

            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">TIPO</label>
                    <select name="tipo_v_edit" id="etv" onchange="checkTipo(this.value, 'es')">
                        <option value="unidad">Unidad</option><option value="granel">Granel</option><option value="paquete">Paquete</option>
                    </select>
                </div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">UNIDAD</label>
                    <select name="unidad_m_edit" id="eum">
                        <option value="Unid">Unid</option><option value="KG">KG</option><option value="G">G</option><option value="M">M</option><option value="Serv">Servicio</option>
                    </select>
                </div>
            </div>
            <div class="triple-row">
                <div><label style="font-size:10px; font-weight:700;" id="lbl_ec">COSTO $</label><input type="number" step="0.01" id="epc" name="pc_edit" oninput="calc('e','p')"></div>
                <div><label style="font-size:10px; font-weight:700;">GAN %</label><input type="number" step="0.1" id="eg" oninput="calc('e','g')"></div>
                <div><label style="font-size:10px; font-weight:700;" id="lbl_ev">VENTA $</label><input type="number" step="0.01" id="epv" name="pv_edit" oninput="calc('e','p')" required></div>
            </div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:20px;">
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">STOCK</label><input type="number" step="0.001" id="es" name="s_edit" required></div>
                <div><label style="font-size:11px; font-weight:700; color:var(--text-sub)">DEPTO.</label>
                    <select name="depto_edit" id="ede">
                        <option value="">-- General --</option>
                        <?php foreach($deptos_array as $d): ?> <option value="<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></option> <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" name="update_prod" class="btn" style="background:var(--amber); width:100%; justify-content:center; padding:12px;">🔄 ACTUALIZAR DATOS</button>
            <button type="button" onclick="cerrarModal('modalEditar')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:15px; cursor:pointer; font-weight:600;">CANCELAR</button>
        </form>
    </div></div>

    <div id="modalVer" class="modal"><div class="modal-content">
        <h3 style="margin-top:0; color:var(--blue); font-size:20px;">🔍 Vista de Artículo</h3>
        <div id="detalleContenido" style="line-height:2; font-size:15px; margin:20px 0; border-top:1px solid var(--border); padding-top:15px;"></div>
        <button onclick="cerrarModal('modalVer')" class="btn" style="background:var(--dark); width:100%; justify-content:center;">CERRAR VENTANA</button>
    </div></div>

    <div id="modalDepto" class="modal"><div class="modal-content" style="width: 450px;">
        <h3 style="margin-top:0; color:var(--dark);">🏢 Gestionar Departamentos</h3>
        <form method="POST" style="display:flex; gap:10px; margin-bottom:20px;">
            <input type="text" name="nombre_depto" placeholder="Nombre del nuevo departamento..." required>
            <button type="submit" name="add_depto" class="btn" style="background:var(--green)">➕</button>
        </form>
        <div style="max-height:280px; overflow-y:auto; border:1px solid var(--border); border-radius:12px;">
            <table style="width:100%; font-size: 14px;">
                <tbody>
                    <?php foreach($deptos_array as$d): ?>
                    <tr>
                        <td style="padding:12px; border-bottom:1px solid #f1f5f9;">
                            <span id="txtDepto<?= $d['id'] ?>"><?= htmlspecialchars($d['nombre']) ?></span>
                            <form id="formEdit<?= $d['id'] ?>" method="POST" style="display:none; gap:5px;">
                                <input type="hidden" name="id_depto_edit" value="<?= $d['id'] ?>">
                                <input type="text" name="nombre_edit" value="<?= htmlspecialchars($d['nombre']) ?>" style="padding:6px;">
                                <button type="submit" name="edit_depto" class="btn" style="background:var(--blue); padding:6px;">✅</button>
                            </form>
                        </td>
                        <td style="text-align:right; padding:12px; border-bottom:1px solid #f1f5f9;">
                            <button onclick="mostrarEditDepto(<?= $d['id'] ?>)" style="background:none; border:none; cursor:pointer; font-size:14px;">✏️</button>
                            <a href="?del_depto=<?= $d['id'] ?>" onclick="return confirm('¿Eliminar departamento?')" style="text-decoration:none; margin-left:8px;">🗑️</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <button onclick="cerrarModal('modalDepto')" class="btn" style="background:var(--dark); width:100%; justify-content:center; margin-top:20px;">CERRAR</button>
    </div></div>

    <script>
        const TASA_ACTUAL = parseFloat("<?= $tasa_actual ?>");
    
        function abrirModal(id) { document.getElementById(id).style.display='block'; }
        function cerrarModal(id) { document.getElementById(id).style.display='none'; }
        
        function mostrarEditDepto(id) {
            document.getElementById('txtDepto'+id).style.display = 'none';
            document.getElementById('formEdit'+id).style.display = 'flex';
        }

        function cambiarLabelMoneda(modo) {
            let select = document.getElementById(modo === 'n' ? 'n_moneda' : 'emo');
            let lblCosto = document.getElementById(modo === 'n' ? 'lbl_nc' : 'lbl_ec');
            let lblVenta = document.getElementById(modo === 'n' ? 'lbl_nv' : 'lbl_ev');
            
            if(select.value === 'VES') {
                lblCosto.innerText = "COSTO $ (USD)";
                lblVenta.innerText = "VENTA Bs";
                lblCosto.style.color = "var(--green)";
                lblVenta.style.color = "var(--blue)";
            } else {
                lblCosto.innerText = "COSTO $";
                lblVenta.innerText = "VENTA $";
                lblCosto.style.color = "";
                lblVenta.style.color = "";
            }
        }

        function buscarGlobal(valor) {
            const contenedor = document.querySelector("tbody");
            const paginacion = document.querySelector(".pagination");
            if (valor.length > 0) {
                if(paginacion) paginacion.style.display = 'none';
                fetch('buscar_producto.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'query=' + encodeURIComponent(valor)
                })
                .then(response => response.text())
                .then(data => { contenedor.innerHTML = data; });
            } else {
                location.reload();
            }
        }

        function toggleStockInput(checked) {
            const seccion = document.getElementById('seccionStock');
            const label = document.getElementById('labelInv');
            const input = document.getElementById('n_s');
            if(checked) {
                seccion.style.opacity = "1";
                seccion.style.pointerEvents = "auto";
                label.innerText = "Este producto maneja Inventario";
                label.style.color = "var(--blue)";
                input.required = true;
            } else {
                seccion.style.opacity = "0.3";
                seccion.style.pointerEvents = "none";
                label.innerText = "Producto sin stock (Servicio/Ilimitado)";
                label.style.color = "var(--slate)";
                input.value = "0";
                input.required = false;
            }
        }

        function checkTipo(val, inputId) {
            const input = document.getElementById(inputId);
            if(input) input.step = (val === 'granel') ? "0.001" : "1";
        }

        function verDetalles(cod, nom, costo, venta, stock, unidad, tipo, bs, moneda, totalUsd, totalBs) {
            let u = unidad.toUpperCase();
            let stockDisplay = (u === 'KG' || u === 'G') ? parseFloat(stock).toFixed(3) : parseInt(stock);
            let htmlDetalle = `
                <b>Código:</b> ${cod}<br>
                <b>Artículo:</b> ${nom}<br>
                <b>Tipo Venta:</b> <span class="badge-tipo">${tipo}</span><br>
                <hr style="border:0; border-top:1px solid var(--border);">
                <b style="color:var(--green)">Costo:</b> $ ${parseFloat(costo).toFixed(2)}<br>
            `;
            if (moneda === 'VES') {
                htmlDetalle += `
                    <b style="color:var(--blue)">Precio Base:</b> Bs ${bs} <span class="badge-moneda" style="font-size:9px;">Fijo</span><br>
                    <small style="color:var(--text-sub);">Equivalente ref: $ ${venta}</small>
                `;
            } else {
                htmlDetalle += `
                    <b style="color:var(--green)">Precio Base:</b> $ ${venta}<br>
                    <b style="color:var(--blue)">Ref. en Bolívares:</b> Bs ${bs}
                `;
            }
            htmlDetalle += `
                <br><b style="color:#7c3aed">Precio Venta + Impuestos (IVA/IGTF):</b> $ ${totalUsd} (Bs ${totalBs})
                <br><br><b>Disponibilidad:</b> <span style="font-weight:700">${stockDisplay}</span> ${unidad}
            `;
            document.getElementById('detalleContenido').innerHTML = htmlDetalle;
            abrirModal('modalVer');
        }

        function abrirE(id,c,n,pc,pv,s,tv,um,de,mo,id_prov,usa_fis,pag) {
            document.getElementById('id_edit').value = id;
            document.getElementById('en').value = n;
            document.getElementById('epc').value = pc;
            document.getElementById('epv').value = pv;
            document.getElementById('es').value = s;
            document.getElementById('etv').value = tv;
            document.getElementById('eum').value = um;
            document.getElementById('ede').value = de;
            
            document.getElementById('pag_retorno_edit').value = pag || 1;
            
            let selectProv = document.getElementById('epr');
            selectProv.value = id_prov || '';

            let checkFiscalEdit = document.getElementById('checkFiscalEdit');
            checkFiscalEdit.checked = (parseInt(usa_fis) === 1);

            let selectMoneda = document.getElementById('emo');
            selectMoneda.value = mo || 'USD';
            cambiarLabelMoneda('e');
            abrirModal('modalEditar');
        }

        function calc(modo, origen) {
            let inputCosto = document.getElementById(modo==='n'?'nc':'epc');
            let inputVenta = document.getElementById(modo==='n'?'nv':'epv');
            let inputGanancia = document.getElementById(modo==='n'?'ng':'eg');
            let selectMoneda = document.getElementById(modo==='n'?'n_moneda':'emo');
            
            let c = parseFloat(inputCosto.value) || 0;
            let v = parseFloat(inputVenta.value) || 0;
            let g = parseFloat(inputGanancia.value || 0);

            if (selectMoneda.value === 'VES') {
                let costoEnBs = c * TASA_ACTUAL;
                if (origen === 'g') {
                    inputVenta.value = (costoEnBs + (costoEnBs * (g/100))).toFixed(2);
                } else {
                    inputGanancia.value = costoEnBs > 0 ? (((v - costoEnBs) / costoEnBs) * 100).toFixed(1) : 0;
                }
            } else {
                if(origen==='g') {
                    inputVenta.value = (c + (c * (g/100))).toFixed(2);
                } else {
                    inputGanancia.value = c > 0 ? (((v - c) / c) * 100).toFixed(1) : 0;
                }
            }
        }
    </script>
</body>
</html>