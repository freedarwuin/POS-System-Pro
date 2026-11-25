<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }
include 'conexion.php';

$usuario_activo = $_SESSION['usuario'];
$rol_activo = $_SESSION['rol'];

// --- 1. LÓGICA PARA ANULAR FACTURA COMPLETA ---
if (isset($_GET['anular'])) {
    $id_anular = (int)$_GET['anular'];
    $check = mysqli_query($conexion, "SELECT estado, id_cliente, total, metodo_pago FROM ventas WHERE id = $id_anular");
    $factura = mysqli_fetch_assoc($check);
    
    if ($factura && $factura['estado'] !== 'ANULADA') {
        $detalles = mysqli_query($conexion, "SELECT id_producto, cantidad FROM detalle_ventas WHERE id_venta = $id_anular");
        while ($item = mysqli_fetch_assoc($detalles)) {
            $id_p = $item['id_producto']; $cant = $item['cantidad'];
            mysqli_query($conexion, "UPDATE productos SET stock = stock + $cant WHERE id = $id_p");
            mysqli_query($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario, fecha) 
                                     VALUES ('$id_p', 'ENTRADA', '$cant', 'Anulación Factura #$id_anular', '$usuario_activo', NOW())");
        }
        if ($factura['metodo_pago'] === 'Crédito' && !empty($factura['id_cliente'])) {
            $id_c = $factura['id_cliente'];
            mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = deuda_actual - {$factura['total']} WHERE id = $id_c");
        }
        mysqli_query($conexion, "UPDATE ventas SET estado = 'ANULADA' WHERE id = $id_anular");
        header("Location: facturas.php?msj=Factura anulada"); exit();
    }
}

// --- 2. LÓGICA PARA DEVOLUCIÓN PARCIAL (ACTUALIZADA) ---
if (isset($_POST['devolver_parcial'])) {
    $id_venta = (int)$_POST['id_venta'];
    $id_prod = (int)$_POST['id_producto'];
    $cant_dev = (float)$_POST['cant_devolver'];

    $q_venta = mysqli_query($conexion, "SELECT * FROM ventas WHERE id = $id_venta");
    $venta = mysqli_fetch_assoc($q_venta);
    
    $q_prod_info = mysqli_query($conexion, "SELECT nombre FROM productos WHERE id = $id_prod");
    $prod_info = mysqli_fetch_assoc($q_prod_info);
    $nombre_p = $prod_info['nombre'] ?? 'Producto Desconocido';

    $q_detalle = mysqli_query($conexion, "SELECT * FROM detalle_ventas WHERE id_venta = $id_venta AND id_producto = $id_prod LIMIT 1");
    $detalle = mysqli_fetch_assoc($q_detalle);

    if ($venta && $detalle && $cant_dev > 0 && $cant_dev <= $detalle['cantidad']) {
        $monto_usd_devolver = $detalle['precio'] * $cant_dev;
        
        mysqli_query($conexion, "UPDATE productos SET stock = stock + $cant_dev WHERE id = $id_prod");
        
        mysqli_query($conexion, "INSERT INTO historial_movimientos (id_producto, tipo_movimiento, cantidad, detalle, usuario, fecha) 
                                 VALUES ('$id_prod', 'ENTRADA', '$cant_dev', 'Devolución: $nombre_p (Fac #$id_venta)', '$usuario_activo', NOW())");
        
        $nueva_cant = $detalle['cantidad'] - $cant_dev;
        
        mysqli_query($conexion, "UPDATE detalle_ventas SET cantidad = $nueva_cant WHERE id = {$detalle['id']}"); 
        
        mysqli_query($conexion, "UPDATE ventas SET total = total - $monto_usd_devolver, estado = 'DEVOLUCIÓN' WHERE id = $id_venta");
        
        if ($venta['metodo_pago'] === 'Crédito' && !empty($venta['id_cliente'])) {
            mysqli_query($conexion, "UPDATE clientes_credito SET deuda_actual = deuda_actual - $monto_usd_devolver WHERE id = {$venta['id_cliente']}");
        }
        
        header("Location: facturas.php?msj=Devolución exitosa"); exit();
    }
}

// --- 3. LÓGICA DE BÚSQUEDA Y PAGINACIÓN EN SERVIDOR ---
$busqueda = isset($_GET['q']) ? mysqli_real_escape_string($conexion, $_GET['q']) : '';
$filtro_metodo = isset($_GET['m']) ? mysqli_real_escape_string($conexion, $_GET['m']) : '';

$sql = "SELECT * FROM ventas WHERE 1=1 ";
if ($busqueda !== '') { $sql .= " AND (id LIKE '%$busqueda%' OR cliente LIKE '%$busqueda%') "; }
if ($filtro_metodo !== '') { $sql .= " AND metodo_pago = '$filtro_metodo' "; }
$sql .= " ORDER BY id DESC LIMIT 150";
$res = mysqli_query($conexion, $sql);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro de Facturas</title>
    <style>
        :root { --blue: #3b82f6; --red: #ef4444; --green: #10b981; --dark: #1e293b; --slate: #64748b; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f1f5f9; margin: 0; padding: 0; }
        .navbar { background: white; padding: 10px 25px; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #e2e8f0; box-shadow: 0 2px 4px rgba(0,0,0,0.05); }
        .nav-links { display: flex; gap: 15px; }
        .nav-links a { text-decoration: none; color: var(--dark); font-weight: bold; font-size: 13px; padding: 8px 12px; border-radius: 6px; border: 1px solid #cbd5e1; }
        .nav-active { background: var(--blue) !important; color: white !important; border-color: var(--blue) !important; }
        .main-content { padding: 25px; max-width: 1300px; margin: auto; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); }
        .search-bar { display: flex; gap: 15px; margin-bottom: 20px; align-items: center; background: #f8fafc; padding: 15px; border-radius: 10px; border: 1px solid #e2e8f0; }
        .search-input { flex: 1; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; outline: none; }
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 12px; background: #f1f5f9; color: var(--slate); font-size: 11px; text-transform: uppercase; border-bottom: 2px solid #e2e8f0; }
        td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: var(--dark); }
        .badge { padding: 4px 12px; border-radius: 20px; font-size: 11px; font-weight: bold; }
        .status-pagada { background: #f1f5f9; color: #64748b; }
        .status-devolucion { background: #fee2e2; color: #ef4444; }
        .status-anulada { background: #fca5a5; color: #7f1d1d; }
        .item-devuelto { color: var(--red); text-decoration: line-through; opacity: 0.7; }
        
        .btn-ver { background: var(--blue); color: white; border: none; padding: 8px 15px; border-radius: 6px; cursor: pointer; font-weight: bold; font-size: 13px; }
        .modal { display: none; position: fixed; z-index: 2000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); backdrop-filter: blur(2px); }
        .modal-content { background: white; margin: 5vh auto; width: 650px; max-height: 90vh; border-radius: 15px; display: flex; flex-direction: column; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); }
        .modal-body { padding: 25px; overflow-y: auto; flex: 1; }
        .modal-header { background: #1e293b; color: white; padding: 18px 25px; display: flex; justify-content: space-between; align-items: center; border-radius: 15px 15px 0 0; }
        .btn-cerrar-modal { background: #e2e8f0; border: 1px solid #cbd5e1; padding: 10px; border-radius: 8px; cursor: pointer; font-weight: 600; font-size: 14px; }
    </style>
</head>
<body>

<nav class="navbar">
    <div style="font-weight: bold; color: var(--blue); font-size: 18px;">🛒 POS SYSTEM</div>
    <div class="nav-links">
        <a href="ventas.php">Ventas (F9)</a>
        <a href="inventario.php">Inventario</a>
        <a href="configuracion.php">Configuracion</a>
        <a href="creditos.php">Créditos</a>
        <a href="facturas.php" class="nav-active">Facturas</a>
        <a href="logout.php" style="background:#fee2e2; color:#b91c1c;">Salir</a>
    </div>
</nav>

<div class="main-content">
    <div class="card">
        <h2 style="margin-top:0;">📄 Registro de Facturas</h2>
        
        <form class="search-bar" method="GET" action="facturas.php">
            <strong>Buscar:</strong>
            <input type="text" name="q" id="inputBuscar" class="search-input" placeholder="Nombre de cliente o N° de factura..." value="<?php echo htmlspecialchars($busqueda); ?>" onkeyup="filtrarTabla()">
            <select name="m" id="filtroMetodo" class="search-input" style="flex:0.3" onchange="this.form.submit()">
                <option value="">Todos los Métodos</option>
                <option value="Efectivo $" <?php echo ($filtro_metodo == 'Efectivo $') ? 'selected' : ''; ?>>Efectivo $</option>
                <option value="Efectivo Bs." <?php echo ($filtro_metodo == 'Efectivo Bs.') ? 'selected' : ''; ?>>Efectivo Bs.</option>
                <option value="Crédito" <?php echo ($filtro_metodo == 'Crédito') ? 'selected' : ''; ?>>Crédito</option>
                <option value="Pago Móvil" <?php echo ($filtro_metodo == 'Pago Móvil') ? 'selected' : ''; ?>>Pago Móvil</option>
                <option value="Punto" <?php echo ($filtro_metodo == 'Punto') ? 'selected' : ''; ?>>Punto</option>
            </select>
            <button type="submit" class="btn-ver" style="background: var(--dark);">Buscar en BD</button>
            <a href="facturas.php" class="btn-cerrar-modal" style="text-align:center; text-decoration:none; width:auto; padding:8px 15px;">Limpiar</a>
        </form>

        <table id="tablaFacturas">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>FECHA / HORA</th>
                    <th>CLIENTE</th>
                    <th>TOTAL ($)</th>
                    <th>MÉTODO</th>
                    <th>ESTADO</th>
                    <th>VENDEDOR</th>
                    <th>ACCIONES</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = mysqli_fetch_assoc($res)): 
                    $est = $row['estado'] ?: 'PAGADA'; 
                    $badge_class = 'status-pagada';
                    if ($est == 'DEVOLUCIÓN') $badge_class = 'status-devolucion';
                    if ($est == 'ANULADA') $badge_class = 'status-anulada';
                ?>
                <tr>
                    <td><b>#<?php echo $row['id']; ?></b></td>
                    <td><?php echo date('d/m/Y h:i A', strtotime($row['fecha'])); ?></td>
                    <td><?php echo $row['cliente']; ?></td>
                    <td><b>$ <?php echo number_format($row['total'], 2); ?></b></td>
                    <td><?php echo $row['metodo_pago']; ?></td>
                    <td><span class="badge <?php echo $badge_class; ?>"><?php echo $est; ?></span></td>
                    <td><small><?php echo $row['usuario']; ?></small></td>
                    <td>
                        <button type="button" onclick="verFactura(<?php echo $row['id']; ?>)" class="btn-ver">Ver</button>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="modalDetalle" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 style="margin:0">Detalle Factura #<span id="f_id"></span></h3>
            <span onclick="cerrarModal('modalDetalle')" style="cursor:pointer; font-size:24px;">&times;</span>
        </div>
        <div class="modal-body">
            <div id="f_datos" style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; font-size: 14px; margin-bottom:20px;"></div>
            
            <table style="width:100%; margin-bottom: 20px;">
                <thead>
                    <tr style="background:#f8fafc; border-top:1px solid #eee; border-bottom:1px solid #eee;">
                        <th style="color: #64748b; font-size: 11px;">CANT</th>
                        <th style="color: #64748b; font-size: 11px;">PRODUCTO</th>
                        <th style="text-align:right; color: #64748b; font-size: 11px;">TOTAL</th>
                    </tr>
                </thead>
                <tbody id="f_items"></tbody>
            </table>

            <div style="text-align:right; border-top: 1px solid #eee; padding-top:15px; display: flex; justify-content: space-between; align-items: end;">
                <div id="pago_desglose" style="text-align: left; font-size: 12px; color: var(--slate);">
                    <!-- Detalle de pago y vuelto dinámico -->
                </div>
                <div>
                    <div style="font-size: 18px; font-weight: 800; color: #1e293b;">Total USD: $<span id="tot_usd"></span></div>
                    <div style="font-size: 16px; font-weight: 700; color: var(--green);">Total Bs: <span id="tot_bs"></span></div>
                </div>
            </div>

            <div style="margin-top:25px; display:flex; justify-content: space-between;">
                <button onclick="cerrarModal('modalDetalle')" class="btn-cerrar-modal" style="width: 48%;">Cerrar</button>
                <!-- Botón de anulación ahora accesible -->
                <a id="btnAnularFac" href="#" class="btn-cerrar-modal" style="width: 48%; background: var(--red); color: white; border-color: var(--red); text-align: center; text-decoration: none;" onclick="return confirm('¿Seguro que deseas anular esta factura por completo?')">Anular Factura</a>
            </div>
        </div>
    </div>
</div>

<div id="modalDevolucion" class="modal" style="z-index:3000;">
    <div class="modal-content" style="width: 380px; margin-top: 10%;">
        <form method="POST" style="padding: 25px;">
            <h3 style="margin:0 0 15px 0; color:var(--red);">Devolver Artículo</h3>
            <input type="hidden" name="id_venta" id="dev_id_v">
            <input type="hidden" name="id_producto" id="dev_id_p">
            <div id="dev_info" style="font-size:14px; margin-bottom:15px; padding:10px; background:#fff1f2; border-radius:8px;"></div>
            
            <label style="font-size:13px; font-weight:bold;">Cantidad a devolver:</label>
            <input type="number" name="cant_devolver" id="dev_cant" step="0.001" min="0.001" required style="width:100%; padding:10px; margin:8px 0; border:1px solid #ddd; border-radius:6px; box-sizing:border-box;">
            
            <button type="submit" name="devolver_parcial" style="width:100%; background:var(--red); color:white; border:none; padding:12px; border-radius:8px; cursor:pointer; font-weight:bold; margin-top:10px;">Confirmar Devolución</button>
            <button type="button" onclick="cerrarModal('modalDevolucion')" style="width:100%; margin-top:10px; border:none; background:none; cursor:pointer; color:var(--slate);">Cancelar</button>
        </form>
    </div>
</div>

<script>
function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

let timeoutBusqueda;
function filtrarTabla() {
    clearTimeout(timeoutBusqueda);
    timeoutBusqueda = setTimeout(() => {
        let input = document.getElementById("inputBuscar").value.toUpperCase();
        let metodo = document.getElementById("filtroMetodo").value.toUpperCase();
        let tr = document.getElementById("tablaFacturas").getElementsByTagName("tr");
        for (let i = 1; i < tr.length; i++) {
            let txt = tr[i].textContent.toUpperCase();
            if (txt.includes(input) && (metodo === "" || txt.includes(metodo))) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }, 300);
}

function verFactura(id) {
    document.getElementById('modalDetalle').style.display = 'block';
    fetch('obtener_detalle_factura.php?id=' + id)
    .then(r => r.json())
    .then(data => {
        const venta = data.venta;
        const tasa = parseFloat(venta.tasa_momento);
        
        document.getElementById('f_id').innerText = venta.id;
        document.getElementById('f_datos').innerHTML = `
            <div><b>Cliente:</b> ${venta.cliente}</div>
            <div><b>Fecha:</b> ${venta.fecha}</div>
            <div><b>Método:</b> ${venta.metodo_pago}</div>
            <div><b>Tasa:</b> ${tasa.toFixed(2)} Bs.</div>
            <div><b>Estado:</b> <span style="color:${venta.estado === 'ANULADA' ? 'red' : '#1e293b'}">${venta.estado || 'PAGADA'}</span></div>
        `;
        
        // Control de visibilidad del botón de Anular
        let btnAnular = document.getElementById('btnAnularFac');
        if (venta.estado !== 'ANULADA') {
            btnAnular.style.display = 'block';
            btnAnular.href = 'facturas.php?anular=' + venta.id;
        } else {
            btnAnular.style.display = 'none';
        }
        
        let htmlItems = '';
        let totalPrecisoBS = 0;

        data.items.forEach(i => {
            let cant = parseFloat(i.cantidad);
            let precioFinal = parseFloat(i.precio) * (1 - ((parseFloat(i.descuento) || 0) / 100));
            let subtotalUSD = cant * precioFinal;
            
            totalPrecisoBS += (subtotalUSD * tasa);

            let esDevuelto = (cant <= 0);
            let rowClass = esDevuelto ? 'item-devuelto' : '';
            
            // --- AQUÍ ESTÁ EL BOTÓN DE DEVOLUCIÓN REINTEGRADO ---
            let btnDevolver = '';
            if (!esDevuelto && venta.estado !== 'ANULADA') {
                // Escapamos comillas por si el producto se llama algo como "Maní M&M's"
                let nombreEscapado = i.nombre.replace(/'/g, "\\'");
                btnDevolver = `<br><button onclick="abrirDev(${venta.id}, ${i.id_producto}, '${nombreEscapado}', ${cant}, ${precioFinal})" style="background: #fee2e2; color: #ef4444; border: 1px solid #fca5a5; padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; cursor: pointer; margin-top: 5px;">↩ Devolver artículo</button>`;
            }
            
            htmlItems += `
            <tr class="${rowClass}" style="border-bottom: 1px solid #f8fafc;">
                <td style="padding:15px 12px; vertical-align:top;">${cant.toFixed(3)}</td>
                <td style="vertical-align:top;">
                    ${i.nombre}
                    ${btnDevolver}
                </td>
                <td style="text-align:right; vertical-align:top;">
                    <b>$${subtotalUSD.toFixed(2)}</b><br>
                    <small style="color:var(--slate);">$${precioFinal.toFixed(2)} c/u</small>
                </td>
            </tr>`;
        });

        document.getElementById('f_items').innerHTML = htmlItems;
        
        let totalBsMostrar = (parseFloat(venta.pago_bs) > 0) ? parseFloat(venta.pago_bs) : totalPrecisoBS;
        
        document.getElementById('tot_usd').innerText = parseFloat(venta.total).toFixed(2);
        document.getElementById('tot_bs').innerText = totalBsMostrar.toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

        // --- DESGLOSE DE MÚLTIPLES PAGOS Y CAMBIO ---
        let desgloseHtml = '';
        if(parseFloat(venta.pago_efectivo_usd) > 0) desgloseHtml += `<div>Efectivo $: <b>$${parseFloat(venta.pago_efectivo_usd).toFixed(2)}</b></div>`;
        if(parseFloat(venta.pago_bs) > 0) desgloseHtml += `<div>Efectivo Bs: <b>${parseFloat(venta.pago_bs).toLocaleString('es-VE', {minimumFractionDigits: 2})}</b></div>`;
        if(parseFloat(venta.pago_punto_bs) > 0) desgloseHtml += `<div>Punto Bs: <b>${parseFloat(venta.pago_punto_bs).toLocaleString('es-VE', {minimumFractionDigits: 2})}</b></div>`;
        if(parseFloat(venta.pago_movil_bs) > 0) desgloseHtml += `<div>Pago Móvil Bs: <b>${parseFloat(venta.pago_movil_bs).toLocaleString('es-VE', {minimumFractionDigits: 2})}</b></div>`;
        if(parseFloat(venta.cambio_usd) > 0) desgloseHtml += `<div style="color:var(--red); margin-top:3px;">Cambio $: <b>$${parseFloat(venta.cambio_usd).toFixed(2)}</b></div>`;
        if(parseFloat(venta.cambio_bs) > 0) desgloseHtml += `<div style="color:var(--red); margin-top:3px;">Cambio Bs: <b>${parseFloat(venta.cambio_bs).toLocaleString('es-VE', {minimumFractionDigits: 2})}</b></div>`;
        
        document.getElementById('pago_desglose').innerHTML = desgloseHtml;
    });
}

function abrirDev(idV, idP, nom, cant, prec) {
    document.getElementById('modalDevolucion').style.display = 'block';
    document.getElementById('dev_id_v').value = idV;
    document.getElementById('dev_id_p').value = idP;
    document.getElementById('dev_info').innerHTML = `<b>${nom}</b><br>Máximo a devolver: ${cant}`;
    document.getElementById('dev_cant').max = cant;
    document.getElementById('dev_cant').value = "";
}
</script>
</body>
</html>