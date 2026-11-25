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

// --- CONSULTA DE TASA DE CAMBIO ---
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

// --- PAGINACIÓN ---
$prov_por_pagina = 10; 
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
$inicio_limit = ($pagina_actual - 1) * $prov_por_pagina;

// --- PROCESAMIENTO PHP ---

// REGISTRAR PROVEEDOR
if (isset($_POST['add_prov'])) {
    $rif = trim($_POST['rif_cedula']);
    
    $stmt_check = mysqli_prepare($conexion, "SELECT id FROM proveedores WHERE rif_cedula = ?");
    mysqli_stmt_bind_param($stmt_check, "s", $rif);
    mysqli_stmt_execute($stmt_check);
    mysqli_stmt_store_result($stmt_check);
    
    if (mysqli_stmt_num_rows($stmt_check) > 0) {
        $_SESSION['error_sistema'] = "ESTE RIF / CÉDULA YA SE ENCUENTRA REGISTRADO [$rif].";
        header("Location: proveedores.php"); exit();
    }
    mysqli_stmt_close($stmt_check);

    $nombre = trim($_POST['nombre_empresa']);
    $telefono = trim($_POST['telefono']);
    $direccion = trim($_POST['direccion']);
    $fecha_registro = date("Y-m-d H:i:s");

    $sql = "INSERT INTO proveedores (nombre_empresa, rif_cedula, telefono, direccion, fecha_registro) VALUES (?, ?, ?, ?, ?)";
    $stmt_ins = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt_ins, "sssss", $nombre, $rif, $telefono, $direccion, $fecha_registro);
    
    if (mysqli_stmt_execute($stmt_ins)) {
        $_SESSION['exito_sistema'] = "¡Proveedor registrado correctamente!";
    } else {
        $_SESSION['error_sistema'] = "Error de base de datos al guardar el proveedor.";
    }
    header("Location: proveedores.php"); exit();
}

// REGISTRAR FACTURA DE PROVEEDOR CON CARGA DE STOCK DINÁMICA
if (isset($_POST['add_factura'])) {
    $id_prov = (int)$_POST['id_prov_factura'];
    $num_fact = trim($_POST['num_factura']);
    $monto = (float)str_replace(',', '.', $_POST['monto_total']);
    $fecha_f = $_POST['fecha_factura'];
    $fecha_reg = date("Y-m-d H:i:s");
    
    $productos_ids = $_POST['prod_ids'] ?? [];
    $productos_cantidades = $_POST['prod_cantidades'] ?? [];
    $productos_descripciones = $_POST['prod_descripciones'] ?? [];

    $desc_articulos = "Artículos recibidos:\n";
    foreach($productos_descripciones as $index => $d_prod) {
        $desc_articulos .= "- " . $d_prod . " (Cant: " . $productos_cantidades[$index] . ")\n";
    }

    $nombre_archivo = null;
    if (isset($_FILES['adjunto']) && $_FILES['adjunto']['error'] == 0) {
        $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $folder = 'facturas_adjuntas/';
            if (!is_dir($folder)) { mkdir($folder, 0777, true); }
            $nombre_archivo = 'fact_' . time() . '_' . uniqid() . '.' . $ext;
            move_uploaded_file($_FILES['adjunto']['tmp_name'], $folder . $nombre_archivo);
        }
    }

    $res_p = mysqli_query($conexion, "SELECT nombre_empresa FROM proveedores WHERE id = $id_prov");
    $nom_proveedor = mysqli_fetch_assoc($res_p)['nombre_empresa'] ?? 'Proveedor';

    mysqli_begin_transaction($conexion);
    try {
        $sql_f = "INSERT INTO facturas_proveedores (id_proveedor, numero_factura, descripcion_mercancia, monto_total, saldo_pendiente, archivo_factura, fecha_factura, fecha_registro) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_f = mysqli_prepare($conexion, $sql_f);
        mysqli_stmt_bind_param($stmt_f, "issddsss", $id_prov, $num_fact, $desc_articulos, $monto, $monto, $nombre_archivo, $fecha_f, $fecha_reg);
        mysqli_stmt_execute($stmt_f);
        mysqli_stmt_close($stmt_f);

        foreach ($productos_ids as $key => $id_p) {
            $id_p = (int)$id_p;
            $cant = (int)$productos_cantidades[$key];

            if ($cant > 0) {
                mysqli_query($conexion, "UPDATE productos SET stock = stock + $cant WHERE id = $id_p");

                $detalle_mov = "Entrada automática por Factura #$num_fact de $nom_proveedor";
                $tipo_mov = "Entrada";
                
                $stmt_h = mysqli_prepare($conexion, "INSERT INTO historial_inventario (id_producto, cantidad, tipo_movimiento, detalle, usuario, fecha) VALUES (?, ?, ?, ?, ?, ?)");
                mysqli_stmt_bind_param($stmt_h, "iissss", $id_p, $cant, $tipo_mov, $detalle_mov, $usuario_activo, $fecha_reg);
                mysqli_stmt_execute($stmt_h);
                mysqli_stmt_close($stmt_h);
            }
        }
        mysqli_commit($conexion);
        $_SESSION['exito_sistema'] = "¡Factura y carga de stock procesadas con éxito!";
    } catch (Exception $e) {
        mysqli_rollback($conexion);
        $_SESSION['error_sistema'] = "Error al procesar la carga masiva: " . $e->getMessage();
    }
    header("Location: proveedores.php"); exit();
}

// REGISTRAR ABONO A FACTURA
if (isset($_POST['add_abono'])) {
    $id_factura = (int)$_POST['id_factura_abono'];
    $monto_usd = (float)str_replace(',', '.', $_POST['monto_usd']);
    $monto_bs = (float)str_replace(',', '.', $_POST['monto_bs']);
    $tasa_utilizada = (float)$_POST['tasa_abono'];
    $metodo = trim($_POST['metodo_pago']);
    $nota = trim($_POST['nota_abono']);
    $fecha_abono = date("Y-m-d H:i:s");

    $stmt_fact = mysqli_prepare($conexion, "SELECT saldo_pendiente FROM facturas_proveedores WHERE id = ?");
    mysqli_stmt_bind_param($stmt_fact, "i", $id_factura);
    mysqli_stmt_execute($stmt_fact);
    $res_fact = mysqli_stmt_get_result($stmt_fact);
    $factura_data = mysqli_fetch_assoc($res_fact);
    mysqli_stmt_close($stmt_fact);

    if ($factura_data) {
        $saldo_actual = (float)$factura_data['saldo_pendiente'];
        $abono_equivalente_usd = $monto_usd + ($monto_bs / $tasa_utilizada);

        if ($abono_equivalente_usd > ($saldo_actual + 0.01)) {
            $_SESSION['error_sistema'] = "EL ABONO INTENTADO ($" . number_format($abono_equivalente_usd, 2) . ") SUPERA EL SALDO PENDIENTE ($" . number_format($saldo_actual, 2) . ").";
            header("Location: proveedores.php"); exit();
        }

        $sql_abono = "INSERT INTO abonos_proveedores (id_factura, monto_abono_usd, monto_abono_bs, tasa_cambio, metodo_pago, nota, fecha_abono, usuario) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt_ab = mysqli_prepare($conexion, $sql_abono);
        mysqli_stmt_bind_param($stmt_ab, "idddssss", $id_factura, $monto_usd, $monto_bs, $tasa_utilizada, $metodo, $nota, $fecha_abono, $usuario_activo);
        
        if (mysqli_stmt_execute($stmt_ab)) {
            $nuevo_saldo = $saldo_actual - $abono_equivalente_usd;
            $nuevo_estado = ($nuevo_saldo <= 0.01) ? 'Pagada' : 'Abonada';
            $nuevo_saldo = ($nuevo_saldo <= 0.01) ? 0 : $nuevo_saldo;

            $stmt_upd_f = mysqli_prepare($conexion, "UPDATE facturas_proveedores SET saldo_pendiente = ?, estado = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_upd_f, "dsi", $nuevo_saldo, $nuevo_estado, $id_factura);
            mysqli_stmt_execute($stmt_upd_f);
            $_SESSION['exito_sistema'] = "¡Abono registrado correctamente!";
        } else {
            $_SESSION['error_sistema'] = "Error al procesar el abono.";
        }
    }
    header("Location: proveedores.php"); exit();
}

// ACTUALIZAR PROVEEDOR
if (isset($_POST['update_prov'])) {
    $id = (int)$_POST['id_edit']; 
    $nombre = trim($_POST['nombre_edit']);
    $rif = trim($_POST['rif_edit']);
    $telefono = trim($_POST['telefono_edit']);
    $direccion = trim($_POST['direccion_edit']);
    $p_retorno = isset($_POST['pagina_retorno']) ? (int)$_POST['pagina_retorno'] : 1;

    $sql = "UPDATE proveedores SET nombre_empresa=?, rif_cedula=?, telefono=?, direccion=? WHERE id=?";
    $stmt_upd = mysqli_prepare($conexion, $sql);
    mysqli_stmt_bind_param($stmt_upd, "ssssi", $nombre, $rif, $telefono, $direccion, $id);
    
    if (mysqli_stmt_execute($stmt_upd)) {
        $_SESSION['exito_sistema'] = "¡Proveedor editado correctamente!";
    } else {
        $_SESSION['error_sistema'] = "Error de base de datos al actualizar el proveedor.";
    }
    header("Location: proveedores.php?p=$p_retorno"); exit();
}

// ELIMINAR PROVEEDOR
if (isset($_GET['eliminar'])) {
    $id_del = (int)$_GET['eliminar'];
    $stmt_del = mysqli_prepare($conexion, "DELETE FROM proveedores WHERE id = ?");
    mysqli_stmt_bind_param($stmt_del, "i", $id_del);
    
    if (mysqli_stmt_execute($stmt_del)) {
        $_SESSION['exito_sistema'] = "¡Proveedor eliminado correctamente!";
    } else {
        $_SESSION['error_sistema'] = "No se pudo eliminar el proveedor (Es posible que tenga facturas o datos asociados).";
    }
    header("Location: proveedores.php"); exit();
}

$res_conteo = mysqli_query($conexion, "SELECT COUNT(*) as total FROM proveedores");
$total_proveedores = mysqli_fetch_assoc($res_conteo)['total'];
$total_paginas = ceil($total_proveedores / $prov_por_pagina);
$proveedores = mysqli_query($conexion, "SELECT * FROM proveedores ORDER BY id DESC LIMIT $inicio_limit, $prov_por_pagina");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Proveedores</title>
    <style>
        :root { 
            --bg-body: #f8fafc; --bg-card: #ffffff; --text-main: #334155; --text-sub: #64748b; 
            --border: #e2e8f0; --table-head: #f1f5f9; --dark: #0f172a; --blue: #3b82f6; 
            --blue-hover: #1d4ed8; --red: #ef4444; --green: #10b981; --amber: #f59e0b; --slate: #475569; 
        }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg-body); color: var(--text-main); margin: 0; padding: 24px; }
        
        /* ESTILOS DE ALERTAS TOAST */
        .toast-error { position: fixed; top: 20px; right: 20px; background: #fee2e2; color: #b91c1c; padding: 16px 24px; border-radius: 12px; border-left: 5px solid #ef4444; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 9999; display: flex; align-items: center; gap: 12px; font-weight: 600; animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        .toast-exito { position: fixed; top: 20px; right: 20px; background: #d1fae5; color: #065f46; padding: 16px 24px; border-radius: 12px; border-left: 5px solid #10b981; box-shadow: 0 20px 25px -5px rgba(0,0,0,0.1); z-index: 9999; display: flex; align-items: center; gap: 12px; font-weight: 600; animation: slideIn 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

        .header-container { background: linear-gradient(135deg, var(--dark) 0%, #1e293b 100%); padding: 20px 30px; color: white; border-radius: 16px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; }
        .toolbar { display: flex; justify-content: space-between; background: var(--bg-card); padding: 20px; border-radius: 16px; margin-bottom: 24px; border: 1px solid var(--border); align-items: center; }
        .tabla-container { background: var(--bg-card); border-radius: 16px; border: 1px solid var(--border); overflow: hidden; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th { background: var(--table-head); padding: 16px 20px; font-size: 12px; color: var(--text-sub); text-transform: uppercase; border-bottom: 1px solid var(--border); font-weight: 700; }
        td { padding: 16px 20px; border-bottom: 1px solid var(--border); font-size: 14px; vertical-align: middle; }
        .btn { padding: 10px 20px; border-radius: 10px; font-weight: 600; cursor: pointer; border: none; color: white; display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-size: 13px; }
        .btn-action { width: 36px; height: 36px; border-radius: 10px; border: none; cursor: pointer; color: white; display: inline-flex; align-items: center; justify-content: center; }
        input, select, textarea { width: 100%; padding: 12px 16px; border-radius: 10px; border: 1px solid var(--border); background: #f8fafc; color: var(--text-main); box-sizing: border-box; font-size: 14px; }
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.3); backdrop-filter: blur(8px); }
        .modal-content { background: var(--bg-card); margin: 40px auto; padding: 32px; width: 650px; border-radius: 24px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); max-height: 85vh; overflow-y: auto; }
        .pagination { display: flex; justify-content: center; align-items: center; gap: 6px; margin: 30px 0; }
        .page-link { padding: 10px 18px; background: var(--bg-card); border: 1px solid var(--border); border-radius: 10px; color: var(--text-main); text-decoration: none; font-weight: 600; }
        .page-link.active { background: var(--blue); color: white; }
        .busqueda-resultado { position: absolute; background: white; border: 1px solid var(--border); width: 93%; border-radius: 8px; max-height: 200px; overflow-y: auto; z-index: 99; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1); display: none; }
        .busqueda-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .busqueda-item:hover { background: #f8fafc; color: var(--blue); }
    </style>
</head>
<body>

    <!-- IMPRESIÓN DE COMPONENTES DE ALERTAS -->
    <?php if (isset($_SESSION['error_sistema'])): ?>
        <div class="toast-error" id="errorToast">⚠️ <div><?= htmlspecialchars($_SESSION['error_sistema']) ?></div></div>
        <script>setTimeout(() => { document.getElementById('errorToast').remove(); }, 4500);</script>
        <?php unset($_SESSION['error_sistema']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['exito_sistema'])): ?>
        <div class="toast-exito" id="exitoToast">✅ <div><?= htmlspecialchars($_SESSION['exito_sistema']) ?></div></div>
        <script>setTimeout(() => { document.getElementById('exitoToast').remove(); }, 4500);</script>
        <?php unset($_SESSION['exito_sistema']); ?>
    <?php endif; ?>

    <div class="header-container">
        <h2 style="margin:0; font-weight: 800;">🏢 Gestión de Proveedores</h2>
        <div style="display:flex; gap:12px;">
            <a href="inventario.php" class="btn" style="background:var(--slate)">📦 INVENTARIO</a>
            <a href="ventas.php" class="btn" style="background:var(--blue)">🛒 IR A CAJA</a>
        </div>
    </div>

    <div class="toolbar">
        <button onclick="abrirModal('modalNuevo')" class="btn" style="background:var(--green)">➕ NUEVO PROVEEDOR</button>
    </div>

    <div class="tabla-container">
        <table>
            <thead>
                <tr><th>Empresa / RIF</th><th>Teléfono</th><th>Dirección</th><th>Fecha Registro</th><th style="text-align:center;">Acciones</th></tr>
            </thead>
            <tbody>
                <?php while($prov = mysqli_fetch_assoc($proveedores)): ?>
                <tr>
                    <td><b><?= htmlspecialchars($prov['nombre_empresa']) ?></b><br><span style="color:var(--blue); font-size:12px;">RIF: <?= htmlspecialchars($prov['rif_cedula']) ?></span></td>
                    <td><?= htmlspecialchars($prov['telefono'] ?: 'N/A') ?></td>
                    <td><?= htmlspecialchars($prov['direccion'] ?: 'Sin Dirección') ?></td>
                    <td><?= date('d-m-Y', strtotime($prov['fecha_registro'])) ?></td>
                    <td style="display:flex; justify-content:center; gap:8px;">
                        <button onclick="abrirFacturas('<?= $prov['id'] ?>', '<?= addslashes(htmlspecialchars($prov['nombre_empresa'])) ?>')" class="btn-action" style="background:var(--blue)" title="Facturas y Cargas">📄</button>
                        <button onclick="abrirE('<?= $prov['id'] ?>','<?= addslashes(htmlspecialchars($prov['nombre_empresa'])) ?>','<?= htmlspecialchars($prov['rif_cedula']) ?>','<?= htmlspecialchars($prov['telefono']) ?>','<?= addslashes(htmlspecialchars($prov['direccion'])) ?>', '<?= $pagina_actual ?>')" class="btn-action" style="background:var(--amber)" title="Editar">✏️</button>
                        
                        <!-- CONFIRMACIÓN DE ELIMINACIÓN CON JS -->
                        <a href="?eliminar=<?= $prov['id'] ?>" class="btn-action" style="background:var(--dark);" onclick="return confirm('¿Está seguro de que desea eliminar a este proveedor?\n\nAcción: <?= addslashes(htmlspecialchars($prov['nombre_empresa'])) ?>')" title="Eliminar">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- PAGINACIÓN -->
    <?php if($total_paginas > 1): ?>
    <div class="pagination">
        <?php for($i=1; $i<=$total_paginas; $i++): ?><a href="?p=<?= $i ?>" class="page-link <?= $pagina_actual == $i ? 'active':'' ?>"><?= $i ?></a><?php endfor; ?>
    </div>
    <?php endif; ?>

    <!-- MODAL DE FACTURAS CON CARGA MASIVA INTEGRADA -->
    <div id="modalFacturas" class="modal"><div class="modal-content" style="width: 900px; max-width:95%;">
        <h3 id="titleFactura" style="margin-top:0; color:var(--blue);">📄 Facturas de Proveedor</h3>
        
        <div style="display: grid; grid-template-columns: 1.1fr 0.9fr; gap:20px;">
            <form method="POST" enctype="multipart/form-data" style="border-right: 1px solid var(--border); padding-right:15px;">
                <input type="hidden" name="id_prov_factura" id="id_prov_factura">
                <h4 style="margin:0 0 10px 0; color:var(--slate);">Cargar Factura & Stock</h4>
                
                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:10px;">
                    <div><label style="font-size:11px; font-weight:700;">N° FACTURA</label><input type="text" name="num_factura" required></div>
                    <div><label style="font-size:11px; font-weight:700;">FECHA EMISIÓN</label><input type="date" name="fecha_factura" required value="<?= date('Y-m-d') ?>"></div>
                </div>

                <!-- BUSCADOR INTERACTIVO EN TIEMPO REAL -->
                <div style="margin-bottom:10px; position:relative;">
                    <label style="font-size:11px; font-weight:700; color:var(--blue);">🔍 BUSCAR PRODUCTO DEL PROVEEDOR (CÓDIGO / NOMBRE)</label>
                    <input type="text" id="inputBuscarProducto" placeholder="Escribe para buscar artículos de este proveedor..." oninput="filtrarProductosProveedor()">
                    <div id="boxResultados" class="busqueda-resultado"></div>
                </div>

                <!-- TABLA TEMPORAL DE ARTÍCULOS A SUMAR -->
                <div style="background:#f8fafc; border:1px solid var(--border); border-radius:10px; padding:10px; margin-bottom:12px;">
                    <span style="font-size:11px; font-weight:800; color:var(--slate);">Lista de Artículos a Cargar en Inventario:</span>
                    <table style="width:100%; margin-top:5px; font-size:12px;" id="tablaCargaStock">
                        <thead>
                            <tr style="background:#e2e8f0;"><th style="padding:6px;">Detalle</th><th style="padding:6px; width:70px;">Cant.</th><th style="padding:6px; width:40px;">-</th></tr>
                        </thead>
                        <tbody>
                            <!-- Se llena dinámicamente con JS -->
                        </tbody>
                    </table>
                </div>

                <div style="display:grid; grid-template-columns: 1fr; gap:10px; margin-bottom:15px;">
                    <div><label style="font-size:11px; font-weight:700;">MONTO TOTAL DE LA FACTURA ($)</label><input type="number" step="0.01" name="monto_total" placeholder="0.00" required></div>
                    <div><label style="font-size:11px; font-weight:700;">ADJUNTAR COMPROBANTE</label><input type="file" name="adjunto"></div>
                </div>

                <button type="submit" name="add_factura" class="btn" style="background:var(--green); width:100%; justify-content:center;">💾 REGISTRAR COMPRA Y STOCK</button>
            </form>

            <div>
                <h4 style="margin:0 0 10px 0; color:var(--slate);">Historial & Cuentas por Pagar</h4>
                <div id="historialFacturas" style="max-height: 480px; overflow-y:auto; font-size:13px;"></div>
            </div>
        </div>
        <button type="button" onclick="cerrarModal('modalFacturas')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:20px; cursor:pointer; font-weight:600;">CERRAR VENTANA</button>
    </div></div>

    <!-- MODAL REGISTRAR ABONO -->
    <div id="modalNuevoAbono" class="modal"><div class="modal-content" style="width: 420px; margin-top: 80px;">
        <h3 id="titleAbono" style="margin-top:0; color:var(--green); font-size:18px;">💵 Registrar Pago Parcial</h3>
        <form method="POST">
            <input type="hidden" name="id_factura_abono" id="id_factura_abono">
            <input type="hidden" name="tasa_abono" value="<?= $tasa_actual ?>">
            <div style="background:#fee2e2; padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; font-weight:bold; color:#b91c1c; text-align:center;">Saldo deudor actual: <span id="textSaldoMax"></span></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom:12px;">
                <div><label style="font-size:11px; font-weight:700;">ABONO EN USD ($)</label><input type="number" step="0.01" name="monto_usd" value="0.00" required></div>
                <div><label style="font-size:11px; font-weight:700;">ABONO EN BS (Bs)</label><input type="number" step="0.01" name="monto_bs" value="0.00" required></div>
            </div>
            <div style="margin-bottom:12px;">
                <label style="font-size:11px; font-weight:700;">MÉTODO DE PAGO</label>
                <select name="metodo_pago" required>
                    <option value="Efectivo USD">Efectivo USD</option><option value="Efectivo BS">Efectivo BS</option><option value="Pago Móvil">Pago Móvil</option><option value="Transferencia">Transferencia Bancaria</option><option value="Zelle">Zelle</option>
                </select>
            </div>
            <div style="margin-bottom:20px;"><label style="font-size:11px; font-weight:700;">NOTA / REFERENCIA</label><input type="text" name="nota_abono" placeholder="Ej: Ref 5521..."></div>
            <button type="submit" name="add_abono" class="btn" style="background:var(--green); width:100%; justify-content:center;">✅ PROCESAR PAGO</button>
            <button type="button" onclick="cerrarModal('modalNuevoAbono')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:12px; cursor:pointer; font-weight:600;">CANCELAR</button>
        </form>
    </div></div>

    <!-- MODAL NUEVO PROVEEDOR -->
    <div id="modalNuevo" class="modal"><div class="modal-content">
        <h3 style="margin-top:0; color:var(--green);">➕ Nuevo Proveedor</h3>
        <form method="POST">
            <div style="margin-bottom:15px;"><label style="font-size:11px; font-weight:700;">NOMBRE DE LA EMPRESA</label><input type="text" name="nombre_empresa" required></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700;">RIF / CÉDULA</label><input type="text" name="rif_cedula" required></div>
                <div><label style="font-size:11px; font-weight:700;">TELÉFONO</label><input type="text" name="telefono"></div>
            </div>
            <div style="margin-bottom:20px;"><label style="font-size:11px; font-weight:700;">DIRECCIÓN</label><input type="text" name="direccion"></div>
            <button type="submit" name="add_prov" class="btn" style="background:var(--green); width:100%; justify-content:center;">💾 GUARDAR PROVEEDOR</button>
            <button type="button" onclick="cerrarModal('modalNuevo')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:15px; cursor:pointer;">CANCELAR</button>
        </form>
    </div></div>

    <!-- MODAL EDITAR PROVEEDOR -->
    <div id="modalEditar" class="modal"><div class="modal-content">
        <h3 style="margin-top:0; color:var(--amber);">✏️ Editar Proveedor</h3>
        <form method="POST">
            <input type="hidden" name="id_edit" id="id_edit">
            <input type="hidden" name="pagina_retorno" id="pag_retorno_edit" value="<?= $pagina_actual ?>">
            <div style="margin-bottom:15px;"><label style="font-size:11px; font-weight:700;">NOMBRE</label><input type="text" id="enombre" name="nombre_edit" required></div>
            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:15px; margin-bottom:15px;">
                <div><label style="font-size:11px; font-weight:700;">RIF</label><input type="text" id="erif" name="rif_edit" required></div>
                <div><label style="font-size:11px; font-weight:700;">TELÉFONO</label><input type="text" id="etelefono" name="telefono_edit"></div>
            </div>
            <div style="margin-bottom:20px;"><label style="font-size:11px; font-weight:700;">DIRECCIÓN</label><input type="text" id="edireccion" name="direccion_edit"></div>
            <button type="submit" name="update_prov" class="btn" style="background:var(--amber); width:100%; justify-content:center;">🔄 ACTUALIZAR DATOS</button>
            <button type="button" onclick="cerrarModal('modalEditar')" style="width:100%; background:none; border:none; color:var(--text-sub); margin-top:15px; cursor:pointer;">CANCELAR</button>
        </form>
    </div></div>

    <div id="modalPreviewCompleta" class="modal"><div class="modal-content" style="width:80%; max-width:900px; height:85vh;">
        <iframe id="globalIframe" style="width:100%; height:75vh; border:none;"></iframe>
        <button type="button" onclick="cerrarModal('modalPreviewCompleta')" class="btn" style="background:var(--dark); width:100%; justify-content:center; margin-top:10px;">CERRAR VISTA PREVIA</button>
    </div></div>

    <script>
        function abrirModal(id) { document.getElementById(id).style.display='block'; }
        function cerrarModal(id) { document.getElementById(id).style.display='none'; }
        
        function abrirE(id, nombre, rif, telf, dir, pag) {
            document.getElementById('id_edit').value = id;
            document.getElementById('enombre').value = nombre;
            document.getElementById('erif').value = rif;
            document.getElementById('etelefono').value = telf;
            document.getElementById('edireccion').value = dir;
            document.getElementById('pag_retorno_edit').value = pag || 1;
            abrirModal('modalEditar');
        }

        function abrirFacturas(id, nombre) {
            document.getElementById('id_prov_factura').value = id;
            document.getElementById('titleFactura').innerText = "📄 Historial: " + nombre;
            document.getElementById('inputBuscarProducto').value = '';
            document.getElementById('boxResultados').style.display = 'none';
            document.querySelector('#tablaCargaStock tbody').innerHTML = '';
            
            fetch('obtener_facturas.php?id=' + id)
                .then(r => r.text())
                .then(html => { document.getElementById('historialFacturas').innerHTML = html; });

            abrirModal('modalFacturas');
        }

        // CONTROL DEL BUSCADOR EN TIEMPO REAL CON FETCH
        function filtrarProductosProveedor() {
            const idProv = document.getElementById('id_prov_factura').value;
            const q = document.getElementById('inputBuscarProducto').value;
            const box = document.getElementById('boxResultados');

            if(q.trim().length < 2) { box.style.display = 'none'; return; }

            fetch(`buscar_productos_proveedor.php?id_proveedor=${idProv}&q=${q}`)
                .then(r => r.json())
                .then(data => {
                    box.innerHTML = '';
                    if(data.length === 0) {
                        box.innerHTML = '<div class="busqueda-item" style="color:gray;">No se encontraron artículos asignados a este proveedor</div>';
                    } else {
                        data.forEach(p => {
                            let div = document.createElement('div');
                            div.className = 'busqueda-item';
                            div.innerHTML = `<b>${p.codigo}</b> - ${p.descripcion} <span style="float:right; color:gray;">Stock: ${p.stock}</span>`;
                            div.onclick = () => { agregarArticuloALista(p.id, p.codigo, p.descripcion); };
                            box.appendChild(div);
                        });
                    }
                    box.style.display = 'block';
                });
        }

        // AGREGAR EL PRODUCTO SELECCIONADO A LA MINI TABLA PREVIA
        function agregarArticuloALista(id, codigo, descripcion) {
            document.getElementById('boxResultados').style.display = 'none';
            document.getElementById('inputBuscarProducto').value = '';

            if(document.getElementById('fila_prod_' + id)) { alert('Este artículo ya está en la lista.'); return; }

            const tbody = document.querySelector('#tablaCargaStock tbody');
            const tr = document.createElement('tr');
            tr.id = 'fila_prod_' + id;
            tr.innerHTML = `
                <td style="padding:6px;">
                    <input type="hidden" name="prod_ids[]" value="${id}">
                    <input type="hidden" name="prod_descripciones[]" value="${codigo} - ${descripcion}">
                    <b>${codigo}</b> - ${descripcion}
                </td>
                <td style="padding:6px;"><input type="number" name="prod_cantidades[]" value="1" min="1" style="padding:4px; text-align:center;" required></td>
                <td style="padding:6px; text-align:center;"><button type="button" onclick="this.closest('tr').remove()" style="background:var(--red); color:white; border:none; border-radius:4px; padding:2px 6px; cursor:pointer;">X</button></td>
            `;
            tbody.appendChild(tr);
        }

        function abrirFormAbono(idFactura, numFactura, saldoMax) {
            document.getElementById('id_factura_abono').value = idFactura;
            document.getElementById('titleAbono').innerText = "💵 Abonar a Factura #" + numFactura;
            document.getElementById('textSaldoMax').innerText = "$ " + parseFloat(saldoMax).toFixed(2);
            abrirModal('modalNuevoAbono');
        }

        function verFacturaCompleta(ruta) {
            document.getElementById('globalIframe').src = 'facturas_adjuntas/' + ruta;
            abrirModal('modalPreviewCompleta');
        }

        // Cerrar el buscador al hacer clic afuera
        document.addEventListener('click', function(e) {
            if(e.target.id !== 'inputBuscarProducto') { document.getElementById('boxResultados').style.display = 'none'; }
        });
    </script>
</body>
</html>