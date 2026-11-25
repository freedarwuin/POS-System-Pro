<?php 
session_start();
include 'conexion.php';
include 'validar_licencia.php';

if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

// 1. Obtener Tasa de Referencia
$res_conf = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1 LIMIT 1");
$conf = mysqli_fetch_assoc($res_conf);
$tasa_referencia = $conf['tasa_dolar'] ?? 1;

// --- PROCESAMIENTO ---

// Guardar Cliente
if (isset($_POST['guardar_cliente'])) {
    $nombre = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $cedula = mysqli_real_escape_string($conexion, $_POST['cedula']);
    $telefono = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $limite = floatval($_POST['limite']);
    mysqli_query($conexion, "INSERT INTO clientes_credito (nombre, cedula_rif, telefono, limite_credito, deuda_actual) VALUES ('$nombre', '$cedula', '$telefono', '$limite', 0)");
    header("Location: creditos.php?msj=Cliente registrado"); exit();
}

// Editar Cliente
if (isset($_POST['editar_cliente'])) {
    $id_e = intval($_POST['id_cliente_edit']);
    $nom = mysqli_real_escape_string($conexion, $_POST['nombre']);
    $ced = mysqli_real_escape_string($conexion, $_POST['cedula']);
    $tel = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $lim = floatval($_POST['limite']);
    mysqli_query($conexion, "UPDATE clientes_credito SET nombre='$nom', cedula_rif='$ced', telefono='$tel', limite_credito='$lim' WHERE id=$id_e");
    header("Location: creditos.php?msj=Cliente actualizado"); exit();
}

// Eliminar Cliente
if (isset($_GET['eliminar'])) {
    $id_del = intval($_GET['eliminar']);
    mysqli_query($conexion, "DELETE FROM clientes_credito WHERE id = $id_del");
    header("Location: creditos.php?msj=Cliente eliminado"); exit();
}

// Procesar Abono
if (isset($_POST['procesar_abono_especifico'])) {
    $id_c = intval($_POST['id_cliente']);
    $id_v = intval($_POST['id_venta_destino']);
    $monto_usd = floatval($_POST['monto_final_usd']);

    if ($monto_usd > 0) {
        // Validar que no abone más de la deuda para evitar deudas negativas
        $res_deuda = mysqli_query($conexion, "SELECT deuda_actual FROM clientes_credito WHERE id = $id_c");
        $dat_deuda = mysqli_fetch_assoc($res_deuda);
        $deuda_real = floatval($dat_deuda['deuda_actual']);
        
        // Si el abono es mayor a la deuda, solo abonamos lo necesario para saldarla
        if ($monto_usd > $deuda_real) {
            $monto_usd = $deuda_real;
        }

        // 1. REGISTRAR EL ABONO EN EL HISTORIAL GENERAL
        mysqli_query($conexion, "INSERT INTO historial_abonos (id_cliente, monto_abonado, fecha) VALUES ('$id_c', '$monto_usd', NOW())");

        // 2. DISTRIBUCIÓN DEL PAGO (AQUÍ ESTÁ LA CORRECCIÓN)
        if ($id_v > 0) {
            // A. SI ES UNA FACTURA ESPECÍFICA
            mysqli_query($conexion, "INSERT INTO pagos_credito (id_venta, monto) VALUES ($id_v, $monto_usd)");
            
            // Verificar si la factura se pagó completa
            $res_v = mysqli_query($conexion, "SELECT total, (SELECT IFNULL(SUM(monto),0) FROM pagos_credito WHERE id_venta=$id_v) as abonado FROM ventas WHERE id=$id_v");
            $data_v = mysqli_fetch_assoc($res_v);
            if ($data_v && $data_v['abonado'] >= ($data_v['total'] - 0.01)) {
                mysqli_query($conexion, "UPDATE ventas SET pagada = 1 WHERE id = $id_v");
            }
        } else {
            // B. SI ES UN ABONO GENERAL (SIN FACTURA): Distribuir el dinero entre las facturas pendientes
            $monto_restante = $monto_usd;
            
            // Obtener todas las facturas pendientes del cliente (de la más antigua a la más reciente)
            $res_pendientes = mysqli_query($conexion, "SELECT id, total, (SELECT IFNULL(SUM(monto),0) FROM pagos_credito WHERE id_venta=ventas.id) as abonado FROM ventas WHERE id_cliente = $id_c AND pagada = 0 ORDER BY id ASC");
            
            if ($res_pendientes) {
                while ($factura = mysqli_fetch_assoc($res_pendientes)) {
                    if ($monto_restante <= 0.01) break; // Ya no queda dinero para repartir
                    
                    $deuda_factura = $factura['total'] - $factura['abonado'];
                    
                    if ($deuda_factura > 0.01) {
                        // Abonar lo que alcance (toda la deuda de la factura o lo que quede del monto restante)
                        $monto_a_abonar = min($monto_restante, $deuda_factura);
                        
                        mysqli_query($conexion, "INSERT INTO pagos_credito (id_venta, monto) VALUES ({$factura['id']}, $monto_a_abonar)");
                        
                        $monto_restante -= $monto_a_abonar;
                        
                        // Si con este abono se cubrió el total, marcar la factura como pagada
                        if (($factura['abonado'] + $monto_a_abonar) >= ($factura['total'] - 0.01)) {
                            mysqli_query($conexion, "UPDATE ventas SET pagada = 1 WHERE id = {$factura['id']}");
                        }
                    }
                }
            }
        }

        // 3. ACTUALIZAR LA DEUDA GLOBAL DEL CLIENTE
        mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = deuda_actual - $monto_usd WHERE id = $id_c");

        // 4. LIMPIEZA DE SEGURIDAD: Si la deuda es menor a 0.01, ponerla en 0 exacto
        mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = 0 WHERE id = $id_c AND deuda_actual < 0.01");

        header("Location: creditos.php?msj=Abono procesado correctamente");
        exit();
    }
}

// --- Procesar Pago Rápido desde Detalle de Factura ---
if (isset($_GET['cancelar_factura'])) {
    $id_v = intval($_GET['cancelar_factura']);
    $id_c = intval($_GET['id_cliente']);

    $res_v = mysqli_query($conexion, "SELECT total, (SELECT IFNULL(SUM(monto),0) FROM pagos_credito WHERE id_venta=$id_v) as abonado FROM ventas WHERE id=$id_v");
    $data_v = mysqli_fetch_assoc($res_v);
    
    if ($data_v) {
        $monto_pendiente = $data_v['total'] - $data_v['abonado'];

        if ($monto_pendiente > 0) {
            mysqli_query($conexion, "INSERT INTO historial_abonos (id_cliente, monto_abonado, fecha) VALUES ('$id_c', '$monto_pendiente', NOW())");
            mysqli_query($conexion, "INSERT INTO pagos_credito (id_venta, monto) VALUES ($id_v, $monto_pendiente)");
            mysqli_query($conexion, "UPDATE ventas SET pagada = 1 WHERE id = $id_v");
            mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = deuda_actual - $monto_pendiente WHERE id = $id_c");
            mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = 0 WHERE id = $id_c AND deuda_actual < 0.01");
        }
    }

    header("Location: creditos.php?msj=Factura cancelada");
    exit();
}

$res = mysqli_query($conexion, "SELECT * FROM clientes_credito ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Créditos | POS Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root { 
            --primary: #000; 
            --accent: #40916c; 
            --bg: #f0f2f5; 
            --text: #334155; 
            --white: #ffffff;
            --border: #e2e8f0;
            --danger: #ef4444; 
            --success: #10b981;
            --info: #0284c7;
            --sidebar-blue: #000;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; }

        .sidebar { 
            width: 260px; 
            background: var(--sidebar-blue); 
            color: white; 
            height: 100vh; 
            position: fixed; 
            padding: 25px; 
            box-sizing: border-box; 
        }
        .sidebar-brand { font-size: 1.5rem; font-weight: 800; margin-bottom: 40px; padding-left: 10px; }
        .nav-link { 
            display: flex; 
            align-items: center; 
            gap: 12px; 
            padding: 12px 15px; 
            color: #94a3b8; 
            text-decoration: none; 
            border-radius: 12px; 
            margin-bottom: 8px; 
            font-weight: 500;
            transition: 0.2s;
        }
        .nav-link:hover { background: #1e293b; color: white; }
        .nav-link.active { background: #4f46e5; color: white; }

        .wrapper { margin-left: 260px; width: calc(100% - 260px); min-height: 100vh; }

        .header-top { 
            background: var(--primary); 
            color: white; 
            padding: 15px 40px; 
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .main-content { padding: 30px 40px; max-width: 1400px; margin: auto; }

        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 12px; border-left: 5px solid var(--accent); box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .stat-card h4 { margin: 0; color: #64748b; font-size: 0.8rem; text-transform: uppercase; }
        .stat-card p { margin: 5px 0 0 0; font-size: 1.5rem; font-weight: 800; color: var(--primary); }

        .table-container { background: white; border-radius: 12px; overflow: hidden; border: 1px solid var(--border); }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 15px; font-size: 0.8rem; color: #475569; border-bottom: 2px solid var(--border); background: #f8fafc; }
        td { padding: 15px; border-bottom: 1px solid var(--border); vertical-align: middle; }
        tr:hover { background: #f1f5f9; }

        .btn-ui { 
            padding: 10px 18px; 
            border-radius: 8px; 
            border: 1px solid var(--border); 
            cursor: pointer; 
            font-weight: 600; 
            display: inline-flex; 
            align-items: center; 
            gap: 8px;
            background: white;
            transition: all 0.2s;
            text-decoration: none;
            color: var(--text);
            font-size: 0.85rem;
        }
        .btn-ui:hover { background: #f8fafc; transform: translateY(-1px); }
        .btn-primary-ui { background: var(--primary); color: white; border: none; }
        
        .badge-mov { padding: 5px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: bold; }
        
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); backdrop-filter: blur(4px); z-index: 1000; justify-content: center; align-items: center; }
        .modal-box { background: white; padding: 30px; border-radius: 20px; width: 450px; max-height: 90vh; overflow-y: auto; }
        input, select { width: 100%; padding: 12px; margin: 8px 0 15px 0; border: 1px solid var(--border); border-radius: 10px; box-sizing: border-box; }
        label { font-weight: 600; font-size: 0.9rem; color: #64748b; }
    </style>
</head>
<body>

<aside class="sidebar">
    <div class="sidebar-brand">POS Pro</div>
    <nav>
        <a href="ventas.php" class="nav-link"><span>🛒</span> Ventas</a>
        <a href="inventario.php" class="nav-link"><span>📦</span> Inventario</a>
        <a href="creditos.php" class="nav-link active"><span>💳</span> Créditos</a>
        <a href="historial_facturas.php" class="nav-link"><span>📄</span> Facturas</a>
    </nav>
</aside>

<div class="wrapper">
    <header class="header-top">
        <h2 style="margin:0;">CRÉDITO A CLIENTES</h2>
        <div style="text-align: right;">
            <small>Tasa de Referencia</small>
            <div style="font-weight: bold; font-size: 1.2rem;">Bs. <?php echo number_format($tasa_referencia, 2); ?></div>
        </div>
    </header>

    <main class="main-content">
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Clientes Registrados</h4>
                <p><?php echo mysqli_num_rows($res); ?></p>
            </div>
            <div class="stat-card" style="border-left-color: var(--danger);">
                <h4>Deuda Total Global</h4>
                <p>$ <?php 
                    $sum = mysqli_query($conexion, "SELECT SUM(deuda_actual) as total FROM clientes_credito");
                    $r_sum = mysqli_fetch_assoc($sum);
                    echo number_format($r_sum['total'] ?? 0, 2); 
                ?></p>
            </div>
            <div style="display: flex; align-items: center; justify-content: flex-end;">
                <button onclick="abrirModal('modalRegistro')" class="btn-ui btn-primary-ui">+ NUEVO CLIENTE</button>
            </div>
        </div>

        <div class="table-container">
            <div style="padding: 20px; border-bottom: 1px solid var(--border);">
                <input type="text" id="buscador" onkeyup="buscar()" placeholder="Filtrar por nombre, cédula o teléfono..." style="margin:0; border-radius: 8px; background: #f8fafc;">
            </div>
            
            <table id="tablaClientes">
                <thead>
                    <tr>
                        <th>CLIENTE</th>
                        <th>ESTADO</th>
                        <th style="text-align:right;">LÍMITE ($)</th>
                        <th style="text-align:right;">DEUDA ACTUAL</th>
                        <th style="text-align:right;">DISPONIBLE</th>
                        <th style="text-align:center;">ACCIONES</th>
                    </tr>
                </thead>
                <tbody>
                    <?php mysqli_data_seek($res, 0); while($c = mysqli_fetch_assoc($res)): 
                        $deuda_segura = floatval($c['deuda_actual']);
                        $limite = floatval($c['limite_credito']);
                        
                        $disponible = $limite - $deuda_segura;
                        if ($disponible < 0) $disponible = 0; 
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex; align-items:center; gap:12px;">
                                <div style="width:40px; height:40px; background:var(--accent); color:white; border-radius:50%; display:flex; align-items:center; justify-content:center; font-weight:bold;">
                                    <?php echo strtoupper(substr($c['nombre'], 0, 1)); ?>
                                </div>
                                <div>
                                    <div style="font-weight:700; color:var(--primary);"><?php echo $c['nombre']; ?></div>
                                    <small style="color:#64748b;"><?php echo $c['cedula_rif']; ?></small>
                                </div>
                            </div>
                        </td>
                        <td>
                            <?php if($deuda_segura > 0.01): ?>
                                <span class="badge-mov" style="background: #fee2e2; color: #991b1b;">CON DEUDA</span>
                            <?php else: ?>
                                <span class="badge-mov" style="background: #dcfce7; color: #166534;">SOLVENTE</span>
                            <?php endif; ?>
                        </td>
                        <td style="text-align:right;">$ <?php echo number_format($limite, 2); ?></td>
                        <td style="text-align:right;">
                            <span style="font-size: 1.1rem; font-weight: 800; color: <?php echo ($deuda_segura > 0.01) ? 'var(--danger)' : 'var(--success)'; ?>;">
                                $ <?php echo number_format($deuda_segura, 2); ?>
                            </span>
                        </td>
                        <td style="text-align:right;">
                            <span style="font-size: 1.1rem; font-weight: 800; color: var(--info);">
                                $ <?php echo number_format($disponible, 2); ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <button class="btn-ui" onclick="verHistorial(<?php echo $c['id']; ?>, '<?php echo $c['nombre']; ?>')">📂 Ver Estado</button>
                            <button class="btn-ui" onclick='abrirAbono(<?php echo json_encode($c); ?>)' style="color: var(--success);">💵 Abonar</button>
                            <button class="btn-ui" onclick='abrirEditar(<?php echo json_encode($c); ?>)'>✏️</button>
                            <button class="btn-ui" onclick="confirmarEliminar(<?php echo $c['id']; ?>, '<?php echo $c['nombre']; ?>')" style="color: var(--danger);">🗑️</button>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>

<div id="modalRegistro" class="modal-overlay">
    <div class="modal-box">
        <h3>Registrar Nuevo Cliente</h3>
        <form method="POST">
            <label>Nombre Completo</label>
            <input type="text" name="nombre" required>
            <label>Cédula / RIF</label>
            <input type="text" name="cedula" required>
            <label>Teléfono</label>
            <input type="text" name="telefono">
            <label>Límite de Crédito ($)</label>
            <input type="number" step="0.01" name="limite" required value="100.00">
            <button type="submit" name="guardar_cliente" class="btn-ui btn-primary-ui" style="width:100%; padding:15px; margin-top:10px; justify-content:center;">Guardar Cliente</button>
            <button type="button" onclick="cerrarModal('modalRegistro')" style="width:100%; background:none; border:none; margin-top:10px; cursor:pointer;">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalEditar" class="modal-overlay">
    <div class="modal-box">
        <h3>Editar Cliente</h3>
        <form method="POST">
            <input type="hidden" name="id_cliente_edit" id="edit_id">
            <label>Nombre Completo</label>
            <input type="text" name="nombre" id="edit_nombre" required>
            <label>Cédula / RIF</label>
            <input type="text" name="cedula" id="edit_cedula" required>
            <label>Teléfono</label>
            <input type="text" name="telefono" id="edit_telefono">
            <label>Límite de Crédito ($)</label>
            <input type="number" step="0.01" name="limite" id="edit_limite" required>
            <button type="submit" name="editar_cliente" class="btn-ui btn-primary-ui" style="width:100%; padding:15px; margin-top:10px; justify-content:center;">Actualizar Datos</button>
            <button type="button" onclick="cerrarModal('modalEditar')" style="width:100%; background:none; border:none; margin-top:10px; cursor:pointer;">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalAbono" class="modal-overlay">
    <div class="modal-box">
        <h3>Registrar Abono</h3>
        
        <div style="background: #fff5f5; padding: 15px; border-radius: 10px; margin-bottom: 15px; border: 1px solid #feb2b2;">
            <small style="color: #c53030; font-weight: bold;">DEUDA ACTUAL DEL CLIENTE</small>
            <div id="display_deuda_actual" style="font-size: 1.5rem; font-weight: 800; color: #c53030;">$ 0.00</div>
            <div id="display_deuda_bs" style="font-size: 1.1rem; font-weight: 700; color: #c53030; margin-top: 5px;">Bs. 0,00</div>
        </div>

        <form method="POST">
            <input type="hidden" name="id_cliente" id="id_abono">
            <input type="hidden" name="monto_final_usd" id="monto_final_usd">
            
            <label>Seleccionar Factura a abonar:</label>
            <select name="id_venta_destino" id="select_facturas" required>
                <option value="0">--- Abono General (Sin factura) ---</option>
            </select>

            <label>Monto a pagar en Bs.</label>
            <input type="number" step="0.01" id="in_bs" oninput="calcularPago()">
            
            <label>Monto a pagar en $</label>
            <input type="number" step="0.01" id="in_usd" oninput="calcularPago()">
            
            <div style="text-align: center; padding: 10px; background: #f0fff4; border-radius: 8px;">
                <small>TOTAL INGRESADO</small>
                <h2 id="display_usd" style="color: #2f855a; margin:0;">$ 0.00</h2>
            </div>

            <div id="vuelto_display" style="display:none; text-align: center; margin-top:10px; padding:10px; background:#fff3cd; border:1px solid #ffeeba; border-radius:8px;">
                <small style="color: #856404; font-weight: bold;">CAMBIO / VUELTO A ENTREGAR:</small>
                <div id="vuelto_monto" style="font-size: 1.3rem; font-weight:bold; color:#856404;">$ 0.00</div>
                <div id="vuelto_monto_bs" style="font-size: 0.9rem; font-weight:bold; color:#856404;">Bs. 0.00</div>
            </div>
            
            <button type="submit" name="procesar_abono_especifico" class="btn-ui btn-primary-ui" style="width:100%; margin-top:15px; justify-content:center;">Confirmar Pago</button>
            <button type="button" onclick="cerrarModal('modalAbono')" style="width:100%; background:none; border:none; margin-top:10px; cursor:pointer;">Cancelar</button>
        </form>
    </div>
</div>

<div id="modalHistorial" class="modal-overlay">
    <div class="modal-box" style="width: 750px;">
        <h3 id="hist_titulo">Estado de Cuenta</h3>
        <div id="contenido_historial" style="max-height: 500px; overflow-y: auto;">Cargando...</div>
        <button onclick="cerrarModal('modalHistorial')" class="btn-ui" style="width:100%; margin-top:20px; justify-content:center;">Cerrar</button>
    </div>
</div>

<div id="modalDetalleFactura" class="modal-overlay" style="z-index: 3000;">
    <div class="modal-box" style="width: 600px; position: relative;">
        <button onclick="cerrarModal('modalDetalleFactura')" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.2rem; cursor: pointer;">✕</button>
        <div id="contenido_factura"></div>
        <button onclick="cerrarModal('modalDetalleFactura')" class="btn-ui" style="width:100%; margin-top:20px; justify-content:center;">Cerrar Detalle</button>
    </div>
</div>

<script>
    const TASA = <?php echo $tasa_referencia; ?>;

    function abrirModal(id) { document.getElementById(id).style.display = 'flex'; }
    function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

    function buscar() {
        let input = document.getElementById("buscador").value.toLowerCase();
        let filas = document.querySelector("#tablaClientes tbody").getElementsByTagName("tr");
        for (let fila of filas) {
            fila.style.display = fila.innerText.toLowerCase().includes(input) ? "" : "none";
        }
    }

    function abrirEditar(c) {
        abrirModal('modalEditar');
        document.getElementById('edit_id').value = c.id;
        document.getElementById('edit_nombre').value = c.nombre;
        document.getElementById('edit_cedula').value = c.cedula_rif;
        document.getElementById('edit_telefono').value = c.telefono;
        document.getElementById('edit_limite').value = c.limite_credito;
    }

   function abrirAbono(c) {
    if(parseFloat(c.deuda_actual) <= 0.01) {
        return Swal.fire('Solvente', 'El cliente no tiene deuda pendiente', 'info');
    }
    
    abrirModal('modalAbono');
    document.getElementById('id_abono').value = c.id;
    
    let deudaUsd = parseFloat(c.deuda_actual);
    let deudaBs = deudaUsd * TASA;

    document.getElementById('display_deuda_actual').innerText = '$ ' + deudaUsd.toLocaleString('en-US', {minimumFractionDigits: 2});
    
    const lblBs = document.getElementById('display_deuda_bs');
    if(lblBs) {
        lblBs.innerText = 'Bs. ' + deudaBs.toLocaleString('es-VE', {minimumFractionDigits: 2});
    }
    
    document.getElementById('in_bs').value = '';
    document.getElementById('in_usd').value = '';
    document.getElementById('display_usd').innerText = '$ 0.00';
    document.getElementById('monto_final_usd').value = '0';
    document.getElementById('vuelto_display').style.display = 'none';
    
    const select = document.getElementById('select_facturas');
    select.innerHTML = '<option value="0">--- Abono General (Sin factura) ---</option>';
    
    fetch('get_historial_cliente.php?id=' + c.id + '&json=1')
        .then(response => response.json())
        .then(facturas => {
            facturas.forEach(f => {
                if (parseInt(f.pagada) !== 1) {
                    let total = parseFloat(f.total);
                    let abonado = parseFloat(f.total_abonado);
                    let pendiente = total - abonado;
                    
                    if (pendiente > 0.01) {
                        select.innerHTML += `<option value="${f.id}">Factura #${f.id} (Pendiente: $${pendiente.toFixed(2)})</option>`;
                    }
                }
            });
        })
        .catch(error => {
            console.error('Error cargando facturas:', error);
        });
}

    function calcularPago() {
        let bs = parseFloat(document.getElementById('in_bs').value) || 0;
        let usd = parseFloat(document.getElementById('in_usd').value) || 0;
        let totalIngresado = (bs / TASA) + usd;
        
        let deudaTxt = document.getElementById('display_deuda_actual').innerText.replace('$ ', '').replace(/,/g, '');
        let deudaActual = parseFloat(deudaTxt);

        document.getElementById('monto_final_usd').value = totalIngresado.toFixed(2);
        document.getElementById('display_usd').innerText = `$ ${totalIngresado.toFixed(2)}`;

        let divVuelto = document.getElementById('vuelto_display');
        if (totalIngresado > deudaActual + 0.01) {
            divVuelto.style.display = 'block';
            let vueltoUsd = totalIngresado - deudaActual;
            let vueltoBs = vueltoUsd * TASA;
            
            document.getElementById('vuelto_monto').innerText = `$ ${vueltoUsd.toFixed(2)}`;
            document.getElementById('vuelto_monto_bs').innerText = `Bs. ${vueltoBs.toFixed(2)}`;
        } else {
            divVuelto.style.display = 'none';
        }
    }

    function confirmarEliminar(id, nombre) {
        Swal.fire({
            title: '¿Eliminar cliente?',
            text: `Se borrará a "${nombre}". Esta acción es permanente.`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#ef4444',
            confirmButtonText: 'Sí, eliminar'
        }).then((result) => {
            if (result.isConfirmed) window.location.href = `creditos.php?eliminar=${id}`;
        });
    }

    function verHistorial(id, nombre) {
        abrirModal('modalHistorial');
        document.getElementById('hist_titulo').innerText = "Estado de Cuenta: " + nombre;
        document.getElementById('contenido_historial').innerHTML = "Cargando...";
        fetch('get_historial_cliente.php?id=' + id)
            .then(r => r.text())
            .then(data => document.getElementById('contenido_historial').innerHTML = data);
    }

    function verDetalleFactura(id) {
        abrirModal('modalDetalleFactura');
        const contenido = document.getElementById('contenido_factura');
        contenido.innerHTML = '<div style="padding:30px; text-align:center;">Cargando...</div>';

        fetch('get_detalle_factura.php?id=' + id)
            .then(response => response.text())
            .then(html => {
                contenido.innerHTML = html;
            });
    }
</script>
</body>
</html>