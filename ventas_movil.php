<?php
session_start();
include 'conexion.php';
if (!isset($_SESSION['usuario'])) { header("Location: index.php"); exit(); }

$usuario_activo = $_SESSION['usuario'];

// 1. OBTENER CONFIGURACIÓN Y TASA
$res_config = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$config = mysqli_fetch_assoc($res_config);
$tasa = (float)$config['tasa_dolar']; 

// 2. CARGAR PRODUCTOS (Incluyendo unidad_medida para la báscula)
$query_productos = "SELECT id, nombre, precio, stock, codigo_barras, unidad_medida, moneda FROM productos WHERE stock > 0 ORDER BY nombre ASC";
$res_prods = mysqli_query($conexion, $query_productos);
$productos_lista = [];
while($p = mysqli_fetch_assoc($res_prods)){
    $precio_base = (float)$p['precio'];
    if (isset($p['moneda']) && $p['moneda'] === 'VES') { $precio_base = $precio_base / $tasa; }
    
    $productos_lista[] = [
        'id' => $p['id'],
        'nombre' => $p['nombre'],
        'precio' => $precio_base,
        'stock' => (float)$p['stock'],
        'unidad' => $p['unidad_medida'], // 'KG', 'UNID', etc.
        'codigo' => $p['codigo_barras']
    ];
}

// 3. PROCESAMIENTO DE VENTA (Idéntico a ventas.php)
if (isset($_POST['guardar_venta'])) {
    $cliente = mysqli_real_escape_string($conexion, $_POST['cliente']);
    $total = (float)$_POST['total_final'];
    $metodo = mysqli_real_escape_string($conexion, $_POST['metodo_pago']);
    $productos = json_decode($_POST['productos_json'], true);

    $sql_venta = "INSERT INTO ventas (cliente, total, metodo_pago, tasa_momento, usuario, fecha, estado) 
                  VALUES ('$cliente', '$total', '$metodo', '$tasa', '$usuario_activo', NOW(), 'PAGADA')";
    
    if (mysqli_query($conexion, $sql_venta)) {
        $id_v = mysqli_insert_id($conexion);
        foreach ($productos as $p) {
            $id_p = (int)$p['id']; $cant = (float)$p['cantidad']; $prec = (float)$p['precio'];
            mysqli_query($conexion, "INSERT INTO detalle_ventas (id_venta, id_producto, cantidad, precio) VALUES ('$id_v', '$id_p', '$cant', '$prec')");
            mysqli_query($conexion, "UPDATE productos SET stock = stock - $cant WHERE id = $id_p");
        }
        header("Location: ventas_movil.php?success=1"); exit();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>POS Android</title>
    <style>
        :root { --primary: #1a73e8; --success: #1e8e3e; --dark: #202124; --bg: #f1f3f4; }
        body { font-family: 'Roboto', sans-serif; background: var(--bg); margin: 0; padding-bottom: 140px; user-select: none; }
        
        /* Barra Superior */
        .header { background: var(--primary); color: white; padding: 15px; display: flex; justify-content: space-between; align-items: center; position: sticky; top: 0; z-index: 100; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        
        /* Buscador */
        .search-area { background: white; padding: 12px; position: sticky; top: 54px; z-index: 99; border-bottom: 1px solid #ddd; }
        .input-mobile { width: 100%; padding: 14px; border: 1px solid #dfe1e5; border-radius: 24px; font-size: 16px; outline: none; box-sizing: border-box; }
        
        #sugerencias { background: white; position: absolute; width: 94%; left: 3%; box-shadow: 0 4px 12px rgba(0,0,0,0.15); border-radius: 8px; max-height: 300px; overflow-y: auto; z-index: 1000; }
        .sug-item { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }

        /* Items del Carrito */
        .card { background: white; margin: 10px; padding: 15px; border-radius: 12px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 1px 3px rgba(0,0,0,0.1); }
        .info-prod { flex: 1; }
        .nombre-prod { font-weight: 500; font-size: 15px; color: var(--dark); }
        .det-prod { font-size: 13px; color: #5f6368; margin-top: 4px; }
        
        /* Controles de cantidad */
        .controls { display: flex; align-items: center; gap: 15px; }
        .btn-op { width: 38px; height: 38px; border-radius: 50%; border: none; background: #f1f3f4; font-size: 20px; color: var(--primary); }

        /* Footer con totales */
        .footer-total { position: fixed; bottom: 0; width: 100%; background: white; padding: 15px; box-sizing: border-box; border-top: 1px solid #dadce0; }
        .row-total { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 12px; }
        .btn-main { background: var(--success); color: white; border: none; width: 100%; padding: 16px; border-radius: 8px; font-size: 18px; font-weight: bold; }

        /* Modales */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 2000; }
        .modal-body { background: white; width: 92%; margin: 10% auto; padding: 20px; border-radius: 16px; box-sizing: border-box; }
        
        /* Teclado Numérico para Granel */
        .numpad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: 15px; }
        .num-btn { padding: 20px; background: #f8f9fa; border: 1px solid #ddd; border-radius: 8px; font-size: 20px; text-align: center; }
    </style>
</head>
<body>

<div class="header">
    <span><b>Punto de Venta</b></span>
    <span style="font-size: 13px;">Tasa: <b><?php echo number_format($tasa, 2); ?></b></span>
</div>

<div class="search-area">
    <input type="text" id="txtBuscar" class="input-mobile" placeholder="Buscar por nombre o código..." onkeyup="buscar(this.value)">
    <div id="sugerencias"></div>
</div>

<div id="lista-carrito">
    <div style="text-align:center; padding:50px; color:#9aa0a6;">
        <div style="font-size: 40px;">🛒</div>
        <p>Tu carrito está vacío</p>
    </div>
</div>

<div class="footer-total">
    <div class="row-total">
        <div>
            <div style="font-size: 26px; font-weight: 700;">$ <span id="view-usd">0.00</span></div>
            <div style="color: var(--success); font-weight: 500;">Bs. <span id="view-bs">0.00</span></div>
        </div>
        <button class="btn-main" onclick="abrirCaja()">COBRAR</button>
    </div>
</div>

<div id="modalGranel" class="modal">
    <div class="modal-body">
        <h3 id="granel-titulo" style="margin-top:0">Peso del Producto</h3>
        <input type="number" id="granel-input" class="input-mobile" style="text-align:center; font-size:24px;" step="0.001" placeholder="0.000">
        <div class="numpad">
            <div class="num-btn" onclick="addN(1)">1</div><div class="num-btn" onclick="addN(2)">2</div><div class="num-btn" onclick="addN(3)">3</div>
            <div class="num-btn" onclick="addN(4)">4</div><div class="num-btn" onclick="addN(5)">5</div><div class="num-btn" onclick="addN(6)">6</div>
            <div class="num-btn" onclick="addN(7)">7</div><div class="num-btn" onclick="addN(8)">8</div><div class="num-btn" onclick="addN(9)">9</div>
            <div class="num-btn" onclick="addN('.')">.</div><div class="num-btn" onclick="addN(0)">0</div><div class="num-btn" onclick="addN('C')" style="color:red">C</div>
        </div>
        <button class="btn-main" style="margin-top:20px;" onclick="confirmarGranel()">AGREGAR</button>
        <button class="btn-main" style="margin-top:10px; background:#ddd; color:#333;" onclick="cerrarModal('modalGranel')">CANCELAR</button>
    </div>
</div>

<div id="modalPago" class="modal">
    <div class="modal-body" style="margin-top:5%">
        <h3 style="margin-top:0">Finalizar Venta</h3>
        <form method="POST">
            <input type="hidden" name="productos_json" id="js_productos">
            <input type="hidden" name="total_final" id="js_total">
            
            <label style="font-size:12px; color:#666">CLIENTE</label>
            <input type="text" name="cliente" class="input-mobile" value="Consumidor Final" style="margin-bottom:15px;">
            
            <label style="font-size:12px; color:#666">MÉTODO DE PAGO</label>
            <select name="metodo_pago" class="input-mobile" style="margin-bottom:15px;">
                <option value="Efectivo $">Efectivo $</option>
                <option value="Efectivo Bs.">Efectivo Bs. (Manual)</option>
                <option value="Pago Móvil">Pago Móvil</option>
                <option value="Punto">Punto de Venta</option>
                <option value="Crédito">Crédito</option>
            </select>

            <div style="background:#f8f9fa; padding:15px; border-radius:12px; margin-bottom:15px;">
                <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span>Recibido $:</span>
                    <input type="number" id="paga_con" step="0.01" class="input-mobile" style="width:100px; text-align:right" onkeyup="vuelto()">
                </div>
                <hr style="border:0; border-top:1px solid #eee; margin:15px 0">
                <div style="display:flex; justify-content:space-between;">
                    <span>Cambio $:</span> <b id="v-usd">0.00</b>
                </div>
                <div style="display:flex; justify-content:space-between; color:var(--success); margin-top:5px;">
                    <span>Cambio Bs:</span> <b id="v-bs">0.00</b>
                </div>
            </div>

            <button type="submit" name="guardar_venta" class="btn-main">CONFIRMAR VENTA</button>
            <button type="button" class="btn-main" style="margin-top:10px; background:none; color:#666;" onclick="cerrarModal('modalPago')">Volver</button>
        </form>
    </div>
</div>

<script>
const productosDB = <?php echo json_encode($productos_lista); ?>;
const tasa = <?php echo $tasa; ?>;
let carrito = [];
let prodTemp = null;

function buscar(val) {
    const sug = document.getElementById('sugerencias');
    if (val.length < 2) { sug.innerHTML = ''; return; }
    
    const res = productosDB.filter(p => p.nombre.toLowerCase().includes(val.toLowerCase()) || (p.codigo && p.codigo.includes(val)));
    let h = '';
    res.slice(0, 8).forEach(p => {
        h += `<div class="sug-item" onclick="seleccionar(${p.id})">
                <span>${p.nombre}</span>
                <b>$${p.precio.toFixed(2)}</b>
              </div>`;
    });
    sug.innerHTML = h;
}

function seleccionar(id) {
    const p = productosDB.find(x => x.id == id);
    document.getElementById('sugerencias').innerHTML = '';
    document.getElementById('txtBuscar').value = '';

    if (p.unidad === 'KG' || p.unidad === 'GR') {
        prodTemp = p;
        document.getElementById('granel-titulo').innerText = p.nombre;
        document.getElementById('granel-input').value = '';
        document.getElementById('modalGranel').style.display = 'block';
    } else {
        agregar(p, 1);
    }
}

function agregar(p, cant) {
    const exist = carrito.find(x => x.id == p.id);
    if (exist) { exist.cantidad += cant; } 
    else { carrito.push({ ...p, cantidad: cant }); }
    render();
}

function render() {
    const cont = document.getElementById('lista-carrito');
    if (carrito.length === 0) {
        cont.innerHTML = '<div style="text-align:center; padding:50px; color:#9aa0a6;"><div style="font-size:40px;">🛒</div><p>Tu carrito está vacío</p></div>';
        update(0); return;
    }
    let h = '', t = 0;
    carrito.forEach((p, i) => {
        t += (p.precio * p.cantidad);
        h += `<div class="card">
                <div class="info-prod">
                    <div class="nombre-prod">${p.nombre}</div>
                    <div class="det-prod">$${p.precio.toFixed(2)} x ${p.cantidad} ${p.unidad}</div>
                </div>
                <div class="controls">
                    <button class="btn-op" onclick="modificar(${i}, -1)">-</button>
                    <b>${p.cantidad}</b>
                    <button class="btn-op" onclick="modificar(${i}, 1)">+</button>
                </div>
              </div>`;
    });
    cont.innerHTML = h;
    update(t);
}

function modificar(i, v) {
    carrito[i].cantidad += v;
    if (carrito[i].cantidad <= 0) carrito.splice(i, 1);
    render();
}

function update(t) {
    document.getElementById('view-usd').innerText = t.toFixed(2);
    document.getElementById('view-bs').innerText = (t * tasa).toLocaleString('es-VE', {minimumFractionDigits: 2});
    document.getElementById('js_total').value = t;
    document.getElementById('js_productos').value = JSON.stringify(carrito);
}

// Lógica de Báscula / Numpad
function addN(n) {
    const input = document.getElementById('granel-input');
    if (n === 'C') input.value = '';
    else input.value += n;
}

function confirmarGranel() {
    const peso = parseFloat(document.getElementById('granel-input').value);
    if (peso > 0) {
        agregar(prodTemp, peso);
        cerrarModal('modalGranel');
    }
}

function abrirCaja() {
    if (carrito.length === 0) return;
    document.getElementById('modalPago').style.display = 'block';
}

function cerrarModal(id) { document.getElementById(id).style.display = 'none'; }

function vuelto() {
    const total = parseFloat(document.getElementById('js_total').value);
    const pago = parseFloat(document.getElementById('paga_con').value) || 0;
    const dif = pago - total;
    if (dif > 0) {
        document.getElementById('v-usd').innerText = dif.toFixed(2);
        document.getElementById('v-bs').innerText = (dif * tasa).toLocaleString('es-VE', {minimumFractionDigits: 2});
    } else {
        document.getElementById('v-usd').innerText = "0.00";
        document.getElementById('v-bs').innerText = "0.00";
    }
}
</script>
</body>
</html>