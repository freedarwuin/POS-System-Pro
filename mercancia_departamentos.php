<?php
session_start();
include 'conexion.php';

// 1. SEGURIDAD: Validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// --- PROCESAR AUMENTO DE PRECIOS (DEPARTAMENTOS O PRODUCTOS INDIVIDUALES) ---
if (isset($_POST['aplicar_aumento'])) {
    $porcentaje = (float)str_replace(',', '.', $_POST['porcentaje_aumento']);
    $tipo_seleccion = $_POST['tipo_seleccion'] ?? 'departamentos'; // 'departamentos' o 'productos'
    $modo_calculo = $_POST['modo_calculo'] ?? 'sobre_venta'; // 'sobre_costo' o 'sobre_venta'

    if ($porcentaje > 0) {
        $factor = 1 + ($porcentaje / 100);
        $sql_upd = "";

        if ($tipo_seleccion === 'departamentos') {
            $deptos_seleccionados = $_POST['departamentos'] ?? [];
            if (!empty($deptos_seleccionados)) {
                $partes_sql = [];
                $ids_general = false;

                foreach ($deptos_seleccionados as $d_id) {
                    if ($d_id === 'GENERAL') {
                        $ids_general = true;
                    } else {
                        $partes_sql[] = (int)$d_id;
                    }
                }

                $condiciones = [];
                if (!empty($partes_sql)) {
                    $condiciones[] = "id_departamento IN (" . implode(',', $partes_sql) . ")";
                }
                if ($ids_general) {
                    $condiciones[] = "id_departamento IS NULL";
                }

                if (!empty($condiciones)) {
                    $where = implode(' OR ', $condiciones);
                    if ($modo_calculo === 'sobre_costo') {
                        $sql_upd = "UPDATE productos SET precio = precio_costo * $factor WHERE ($where) AND precio_costo > 0";
                    } else {
                        $sql_upd = "UPDATE productos SET precio = precio * $factor WHERE ($where)";
                    }
                }
            }
        } else { // Selección por productos individuales
            $prods_seleccionados = $_POST['productos_ids'] ?? [];
            if (!empty($prods_seleccionados)) {
                $ids_prods = array_map('intval', $prods_seleccionados);
                $where = "id IN (" . implode(',', $ids_prods) . ")";

                if ($modo_calculo === 'sobre_costo') {
                    $sql_upd = "UPDATE productos SET precio = precio_costo * $factor WHERE $where AND precio_costo > 0";
                } else {
                    $sql_upd = "UPDATE productos SET precio = precio * $factor WHERE $where";
                }
            }
        }

        if (!empty($sql_upd) && mysqli_query($conexion, $sql_upd)) {
            $_SESSION['msj_aumento'] = "¡Precios actualizados exitosamente en un +{$porcentaje}%!";
        } else {
            $_SESSION['error_aumento'] = "No se seleccionaron elementos o hubo un error en la base de datos.";
        }
    } else {
        $_SESSION['error_aumento'] = "Debe ingresar un porcentaje válido mayor a 0.";
    }
    header("Location: mercancia_departamentos.php");
    exit();
}

// --- MOTOR DE EXPORTACIÓN A EXCEL (CSV) ---
if (isset($_GET['exportar_depto']) && !empty($_GET['exportar_depto'])) {
    $depto_export = $_GET['exportar_depto'];
    
    header('Content-Type: text/csv; charset=utf-8');
    $nombre_archivo = preg_replace('/[^a-zA-Z0-9]/', '_', $depto_export);
    header('Content-Disposition: attachment; filename=Reporte_' . $nombre_archivo . '_' . date('d-m-Y') . '.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    $res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
    $tasa_act = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

    fputcsv($output, ['Código', 'Producto', 'Departamento', 'Moneda Base', 'Precio Base', 'Venta USD', 'Venta BS', 'Stock Actual'], ';');
    
    $depto_esc = mysqli_real_escape_string($conexion, $depto_export);
    
    if ($depto_export === 'TODOS') {
         $condicion = "1=1";
    } elseif ($depto_export === '⚠️ GENERAL') {
         $condicion = "d.nombre IS NULL";
    } else {
         $condicion = "d.nombre = '$depto_esc'";
    }

    $query_export = "SELECT p.codigo_barras, p.nombre, p.precio, p.moneda, p.stock, IFNULL(d.nombre, '⚠️ GENERAL') AS depto_nombre 
                     FROM productos p 
                     LEFT JOIN departamentos d ON p.id_departamento = d.id 
                     WHERE $condicion
                     ORDER BY p.nombre ASC";
                     
    $res_export = mysqli_query($conexion, $query_export);
    
    while ($row = mysqli_fetch_assoc($res_export)) {
        $moneda = strtoupper($row['moneda'] ?? 'USD');
        $precio_orig = (float)$row['precio'];
        
        if ($moneda === 'VES' || $moneda === 'BS') {
            $precio_bs = $precio_orig;
            $precio_usd = $precio_orig / $tasa_act;
        } else {
            $precio_usd = $precio_orig;
            $precio_bs = $precio_orig * $tasa_act;
        }

        fputcsv($output, [
            $row['codigo_barras'],
            $row['nombre'],
            $row['depto_nombre'],
            $moneda,
            number_format($precio_orig, 2, ',', '.'),
            number_format($precio_usd, 2, ',', '.'),
            number_format($precio_bs, 2, ',', '.'),
            $row['stock']
        ], ';');
    }
    fclose($output);
    exit();
}

// --- OBTENER TASA DEL DÓLAR ---
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

// --- CONSULTA DE DEPARTAMENTOS REALES ---
$query_lista_deptos = mysqli_query($conexion, "SELECT id, nombre FROM departamentos ORDER BY nombre ASC");
$lista_deptos = [];
while ($d = mysqli_fetch_assoc($query_lista_deptos)) {
    $lista_deptos[] = $d;
}

// --- CONSULTA DE TODOS LOS PRODUCTOS PARA SELECCIÓN INDIVIDUAL ---
$query_todos_prods = mysqli_query($conexion, "SELECT id, codigo_barras AS codigo, nombre, precio FROM productos ORDER BY nombre ASC");
$lista_todos_prods = [];
while ($pr = mysqli_fetch_assoc($query_todos_prods)) {
    $lista_todos_prods[] = $pr;
}

// 2. CONSULTA PRINCIPAL
$sql = "SELECT p.id, 
               p.codigo_barras AS codigo, 
               p.nombre AS nombre_producto, 
               p.precio, 
               p.precio_costo,
               p.moneda, 
               p.stock, 
               IFNULL(d.nombre, '⚠️ GENERAL') AS depto_nombre,
               p.id_departamento
        FROM productos p 
        LEFT JOIN departamentos d ON p.id_departamento = d.id 
        ORDER BY depto_nombre ASC, p.nombre ASC";

$resultado = mysqli_query($conexion, $sql);

// 3. AGRUPACIÓN DE DATOS
$mercancia_por_departamento = [];
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $depto = $fila['depto_nombre'];
        $mercancia_por_departamento[$depto][] = $fila;
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Inventario por Departamentos | PosSystetmPro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary: #4f46e5;
            --bg: #f8fafc;
            --card: #ffffff;
            --text: #1e293b;
            --text-light: #64748b;
            --border: #e2e8f0;
            --success: #10b981;
            --amber: #f59e0b;
            --bs-color: #2563eb;
        }

        body { font-family: 'Inter', sans-serif; background: var(--bg); color: var(--text); margin: 0; display: flex; }

        .sidebar { width: 260px; background: #0f172a; color: white; height: 100vh; position: fixed; padding: 25px; box-sizing: border-box; }
        .sidebar h2 { font-size: 1.4rem; font-weight: 700; margin-bottom: 40px; color: #818cf8; }
        .nav-link { display: block; padding: 12px 15px; color: #94a3b8; text-decoration: none; border-radius: 10px; margin-bottom: 8px; transition: 0.3s; }
        .nav-link.active { background: var(--primary); color: white; }

        .main-content { margin-left: 260px; flex: 1; padding: 40px; }
        
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; }
        .tasa-badge { background: #e0e7ff; color: var(--primary); padding: 8px 15px; border-radius: 8px; font-weight: 700; border: 1px solid #c7d2fe; white-space: nowrap; }

        .toast-msg { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 600; display: flex; justify-content: space-between; align-items: center; }
        .toast-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .toast-error { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }

        .grid-deptos { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 25px; }
        .card-depto { 
            background: var(--card); border: 1px solid var(--border); border-radius: 16px; 
            padding: 20px; border-top: 5px solid var(--primary); cursor: pointer; transition: 0.2s;
        }
        .card-depto:hover { transform: translateY(-5px); box-shadow: 0 10px 15px rgba(0,0,0,0.1); }
        .card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 15px; display: flex; justify-content: space-between; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; }

        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; font-size: 0.7rem; color: var(--text-light); padding: 12px; text-transform: uppercase; border-bottom: 1px solid var(--border); background: #f8fafc; }
        td { padding: 12px; border-bottom: 1px solid #f8fafc; font-size: 0.85rem; }
        
        .badge-moneda { font-size: 0.65rem; padding: 2px 6px; border-radius: 4px; background: #f1f5f9; color: var(--text-light); font-weight: 600; display: inline-block; margin-top: 4px; }
        .stock-pill { padding: 3px 8px; border-radius: 6px; font-weight: 700; font-size: 0.75rem; }
        .stock-ok { background: #dcfce7; color: #166534; }
        .stock-low { background: #fee2e2; color: #991b1b; }

        .modal-overlay { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
        .modal-content { background: white; width: 90%; max-width: 950px; max-height: 85vh; border-radius: 20px; padding: 30px; position: relative; overflow: hidden; display: flex; flex-direction: column; }
        .search-input { width: 100%; padding: 10px 14px; border: 1px solid var(--border); border-radius: 10px; outline: none; box-sizing: border-box; font-size: 0.9rem; }
        
        .btn-action { background: var(--success); color: white; padding: 8px 18px; border-radius: 10px; text-decoration: none; font-size: 0.85rem; font-weight: bold; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-action:hover { opacity: 0.9; transform: scale(1.02); }

        /* Contenedor de listas de selección */
        .items-checklist { max-height: 180px; overflow-y: auto; border: 1px solid var(--border); border-radius: 10px; padding: 8px; background: #f8fafc; margin-bottom: 15px; }
        .check-item { display: flex; align-items: center; gap: 10px; padding: 6px 8px; border-bottom: 1px solid #edf2f7; cursor: pointer; font-size: 0.85rem; }
        .check-item:hover { background: #e2e8f0; border-radius: 6px; }
        .check-item input { width: 16px; height: 16px; cursor: pointer; }

        /* Tabs de Selección */
        .tab-btn { padding: 8px 16px; border: none; background: #e2e8f0; color: var(--text-light); font-weight: 700; border-radius: 8px; cursor: pointer; transition: 0.2s; }
        .tab-btn.active { background: var(--amber); color: white; }
    </style>
</head>
<body>

<aside class="sidebar">
    <h2>PosSystetmPro</h2>
    <nav>
        <a href="ventas.php" class="nav-link">🛒 Ventas</a>
        <a href="inventario.php" class="nav-link">📦 Inventario</a>
        <a href="mercancia_departamentos.php" class="nav-link active">📁 Por Departamentos</a>
        <a href="configuracion.php" class="nav-link">⚙️ Ajustes</a>
    </nav>
</aside>

<main class="main-content">
    
    <?php if (isset($_SESSION['msj_aumento'])): ?>
        <div class="toast-msg toast-success">
            <span>✅ <?php echo $_SESSION['msj_aumento']; ?></span>
            <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-weight:bold;">✕</button>
        </div>
        <?php unset($_SESSION['msj_aumento']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['error_aumento'])): ?>
        <div class="toast-msg toast-error">
            <span>⚠️ <?php echo $_SESSION['error_aumento']; ?></span>
            <button onclick="this.parentElement.remove()" style="background:none; border:none; cursor:pointer; font-weight:bold;">✕</button>
        </div>
        <?php unset($_SESSION['error_aumento']); ?>
    <?php endif; ?>

    <div class="header">
        <div>
            <h1 style="margin:0;">Departamentos</h1>
            <p style="color: var(--text-light); margin-top: 5px;">Visualización, aumentos masivos y exportación por grupos.</p>
        </div>
        
        <div style="display: flex; gap: 12px; align-items: center;">
            <button onclick="abrirModalAumento()" class="btn-action" style="background: var(--amber);">📈 Aumentar Precios %</button>
            
            <form method="GET" style="display: flex; gap: 8px; background: white; padding: 6px 12px; border-radius: 12px; border: 1px solid var(--border); box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
                <select name="exportar_depto" style="border: none; outline: none; background: transparent; font-weight: 600; color: var(--text); cursor: pointer;" required>
                    <option value="" disabled selected>📥 Exportar Depto...</option>
                    <option value="TODOS">Todos (Consolidado)</option>
                    <?php foreach (array_keys($mercancia_por_departamento) as $nom_depto): ?>
                        <option value="<?php echo htmlspecialchars($nom_depto); ?>"><?php echo htmlspecialchars($nom_depto); ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn-action" style="padding: 5px 12px;">Ir</button>
            </form>
            <a href="imprimir_precios.php" class="btn-action" style="background: #4f46e5;">🖨️ Habladores</a>
            <div class="tasa-badge">💵 Tasa: <?php echo number_format($tasa_actual, 2); ?> Bs/$</div>
        </div>
    </div>

    <div class="grid-deptos">
        <?php foreach ($mercancia_por_departamento as $depto => $productos): ?>
            <div class="card-depto" onclick='abrirModal("<?php echo htmlspecialchars($depto); ?>", <?php echo json_encode($productos); ?>)'>
                <div class="card-title">
                    <span>📁 <?php echo htmlspecialchars($depto); ?></span>
                    <span style="font-size: 0.75rem; color: var(--primary);"><?php echo count($productos); ?> Items</span>
                </div>
                <table>
                    <tbody>
                        <?php for ($i = 0; $i < min(3, count($productos)); $i++): 
                            $p = $productos[$i];
                            $moneda_p = strtoupper($p['moneda'] ?? 'USD');
                            $precio_usd = ($moneda_p == 'VES' || $moneda_p == 'BS') ? ($p['precio'] / $tasa_actual) : $p['precio'];
                        ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['nombre_producto']); ?></td>
                                <td style="text-align: right; font-weight: 700; color: var(--success);">$<?php echo number_format($precio_usd, 2); ?></td>
                            </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
                <div style="margin-top: 10px; font-size: 0.7rem; color: var(--text-light); text-align: right;">Ver todos →</div>
            </div>
        <?php endforeach; ?>
    </div>
</main>

<!-- MODAL BÚSQUEDA Y DETALLE POR DEPARTAMENTO -->
<div id="modalDepto" class="modal-overlay">
    <div class="modal-content">
        <span onclick="cerrarModal()" style="position: absolute; right: 25px; top: 20px; font-size: 28px; cursor: pointer;">&times;</span>
        
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-right: 45px;">
            <h2 id="modalTitulo" style="color: var(--primary); border-left: 4px solid var(--primary); padding-left: 15px; margin: 0;"></h2>
            <a id="btnExportarModal" href="#" class="btn-action">📥 Descargar esta lista</a>
        </div>

        <input type="text" id="busquedaModal" class="search-input" placeholder="🔍 Buscar dentro de este departamento..." style="margin-bottom: 20px;">
        
        <div style="overflow-y: auto; flex: 1; border: 1px solid var(--border); border-radius: 12px;">
            <table>
                <thead style="position: sticky; top: 0; background: white; z-index: 10;">
                    <tr>
                        <th>Producto</th>
                        <th>Código</th>
                        <th style="text-align: right;">Precio ($)</th>
                        <th style="text-align: right;">Precio (Bs)</th>
                        <th style="text-align: center;">Stock</th>
                    </tr>
                </thead>
                <tbody id="cuerpoTablaModal"></tbody>
            </table>
        </div>
    </div>
</div>

<!-- MODAL AUMENTAR PRECIOS (POR DEPARTAMENTOS O PRODUCTOS) -->
<div id="modalAumento" class="modal-overlay">
    <div class="modal-content" style="max-width: 600px;">
        <span onclick="cerrarModalAumento()" style="position: absolute; right: 25px; top: 20px; font-size: 28px; cursor: pointer;">&times;</span>
        <h2 style="color: var(--amber); margin-top: 0; border-left: 4px solid var(--amber); padding-left: 12px;">📈 Aumento Masivo %</h2>
        
        <form method="POST">
            <input type="hidden" name="tipo_seleccion" id="tipo_seleccion" value="departamentos">

            <!-- SELECTOR DE PESTAÑAS (TIPO DE SELECCIÓN) -->
            <div style="display: flex; gap: 10px; margin-bottom: 15px;">
                <button type="button" class="tab-btn active" id="tabDeptos" onclick="cambiarTab('departamentos')">📁 Por Departamentos</button>
                <button type="button" class="tab-btn" id="tabProds" onclick="cambiarTab('productos')">📦 Productos Específicos</button>
            </div>

            <!-- VISTA DEPARTAMENTOS -->
            <div id="secDeptos">
                <div style="margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 0.85rem;">Seleccionar Departamentos:</span>
                    <button type="button" onclick="marcarTodos('check-depto', true)" style="background:none; border:none; color: var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:bold;">Marcar Todos</button>
                </div>
                <div class="items-checklist">
                    <?php foreach ($lista_deptos as $ld): ?>
                        <label class="check-item">
                            <input type="checkbox" name="departamentos[]" value="<?php echo $ld['id']; ?>" class="check-depto">
                            <span>📁 <?php echo htmlspecialchars($ld['nombre']); ?></span>
                        </label>
                    <?php endforeach; ?>
                    <label class="check-item">
                        <input type="checkbox" name="departamentos[]" value="GENERAL" class="check-depto">
                        <span>⚠️ GENERAL (Sin Depto)</span>
                    </label>
                </div>
            </div>

            <!-- VISTA PRODUCTOS INDIVIDUALES -->
            <div id="secProds" style="display: none;">
                <div style="margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                    <span style="font-weight: 700; font-size: 0.85rem;">Buscar y seleccionar productos:</span>
                    <button type="button" onclick="marcarTodos('check-prod', true)" style="background:none; border:none; color: var(--primary); cursor:pointer; font-size: 0.75rem; font-weight:bold;">Marcar Visibles</button>
                </div>
                
                <input type="text" id="buscarProductoAumento" class="search-input" placeholder="🔍 Buscar producto por descripción o código..." oninput="filtrarProductosAumento()" style="margin-bottom: 10px;">
                
                <div class="items-checklist" id="contenedorProdsAumento">
                    <?php foreach ($lista_todos_prods as $prod): ?>
                        <label class="check-item item-prod-row" data-search="<?php echo strtolower(htmlspecialchars($prod['nombre'] . ' ' . $prod['codigo'])); ?>">
                            <input type="checkbox" name="productos_ids[]" value="<?php echo $prod['id']; ?>" class="check-prod">
                            <span><strong>[<?php echo htmlspecialchars($prod['codigo']); ?>]</strong> <?php echo htmlspecialchars($prod['nombre']); ?> - <span style="color:var(--success); font-weight:bold;">$<?php echo number_format($prod['precio'], 2); ?></span></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- CAMPOS COMUNES -->
            <div style="margin-bottom: 12px;">
                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Porcentaje de Aumento (%):</label>
                <input type="number" step="0.01" min="0.01" name="porcentaje_aumento" placeholder="Ej. 10 para aumentar 10%" required style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); font-size: 1rem; font-weight: bold; box-sizing: border-box;">
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; font-weight: 700; font-size: 0.85rem; margin-bottom: 6px;">Aplicar aumento sobre:</label>
                <select name="modo_calculo" style="width: 100%; padding: 10px; border-radius: 8px; border: 1px solid var(--border); font-size: 0.9rem; background: white;">
                    <option value="sobre_venta">Precio de Venta Actual (Aumento Directo %)</option>
                    <option value="sobre_costo">Precio Costo (Margen de Ganancia %)</option>
                </select>
            </div>

            <button type="submit" name="aplicar_aumento" class="btn-action" style="background: var(--amber); width: 100%; justify-content: center; padding: 12px;" onclick="return confirm('¿Confirma que desea aplicar el aumento de precio a la selección realizada?')">
                ⚡ Aplicar Aumento Masivo
            </button>
        </form>
    </div>
</div>

<script>
let productosActivos = []; 
const TASA = <?php echo $tasa_actual; ?>;

function abrirModal(nombre, productos) {
    document.getElementById('modalTitulo').innerText = nombre;
    document.getElementById('btnExportarModal').href = '?exportar_depto=' + encodeURIComponent(nombre);
    document.getElementById('modalDepto').style.display = 'flex';
    productosActivos = productos; 
    renderizarTabla(productos);
}

function cerrarModal() { document.getElementById('modalDepto').style.display = 'none'; }

function abrirModalAumento() { document.getElementById('modalAumento').style.display = 'flex'; }
function cerrarModalAumento() { document.getElementById('modalAumento').style.display = 'none'; }

// Cambiar entre Pestaña Departamentos y Productos
function cambiarTab(tipo) {
    document.getElementById('tipo_seleccion').value = tipo;
    
    if (tipo === 'departamentos') {
        document.getElementById('secDeptos').style.display = 'block';
        document.getElementById('secProds').style.display = 'none';
        document.getElementById('tabDeptos').classList.add('active');
        document.getElementById('tabProds').classList.remove('active');
    } else {
        document.getElementById('secDeptos').style.display = 'none';
        document.getElementById('secProds').style.display = 'block';
        document.getElementById('tabDeptos').classList.remove('active');
        document.getElementById('tabProds').classList.add('active');
    }
}

// Búsqueda en vivo para productos específicos
function filtrarProductosAumento() {
    const busqueda = document.getElementById('buscarProductoAumento').value.toLowerCase();
    const filas = document.querySelectorAll('.item-prod-row');
    
    filas.forEach(fila => {
        const texto = fila.getAttribute('data-search');
        if (texto.includes(busqueda)) {
            fila.style.display = 'flex';
        } else {
            fila.style.display = 'none';
        }
    });
}

function marcarTodos(clase, estado) {
    document.querySelectorAll('.' + clase).forEach(chk => {
        if (chk.closest('label').style.display !== 'none') {
            chk.checked = estado;
        }
    });
}

function renderizarTabla(lista) {
    const cuerpo = document.getElementById('cuerpoTablaModal');
    cuerpo.innerHTML = '';
    
    lista.forEach(p => {
        let pUsd, pBs;
        const base = (p.moneda || 'USD').toUpperCase();

        if (base === 'VES' || base === 'BS') {
            pBs = parseFloat(p.precio);
            pUsd = pBs / TASA;
        } else {
            pUsd = parseFloat(p.precio);
            pBs = pUsd * TASA;
        }

        const claseStock = p.stock > 5 ? 'stock-ok' : 'stock-low';
        
        cuerpo.innerHTML += `
            <tr>
                <td>
                    <div style="font-weight: 600;">${p.nombre_producto}</div>
                    <div class="badge-moneda">Base: ${base}</div>
                </td>
                <td style="color: var(--text-light); font-family: monospace;">${p.codigo}</td>
                <td style="text-align: right; color: var(--success); font-weight: 700;">$${pUsd.toFixed(2)}</td>
                <td style="text-align: right; color: var(--bs-color); font-weight: 600;">Bs ${pBs.toLocaleString('es-VE', {minimumFractionDigits: 2})}</td>
                <td style="text-align: center;">
                    <span class="stock-pill ${claseStock}">${p.stock}</span>
                </td>
            </tr>
        `;
    });
}

// Búsqueda dentro del modal de lista por departamento
document.getElementById('busquedaModal').addEventListener('input', function(e) {
    const term = e.target.value.toLowerCase();
    const filtrados = productosActivos.filter(p => 
        p.nombre_producto.toLowerCase().includes(term) || 
        p.codigo.toLowerCase().includes(term)
    );
    renderizarTabla(filtrados);
});

// Cerrar al hacer clic fuera del modal
window.onclick = function(e) { 
    if (e.target.id == 'modalDepto') cerrarModal(); 
    if (e.target.id == 'modalAumento') cerrarModalAumento(); 
}
</script>

</body>
</html>