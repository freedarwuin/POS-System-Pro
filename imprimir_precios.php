<?php
session_start();
include 'conexion.php';

// 1. SEGURIDAD: Validar sesión
if (!isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

// OBTENER TASA DEL DÓLAR
$res_tasa = mysqli_query($conexion, "SELECT tasa_dolar FROM configuracion WHERE id = 1");
$tasa_actual = mysqli_fetch_assoc($res_tasa)['tasa_dolar'] ?? 1;

// CAPTURAR DEPARTAMENTO SI SE FILTRA
$depto_seleccionado = isset($_GET['depto']) ? $_GET['depto'] : 'TODOS';

// CONSULTA BASE
$sql = "SELECT p.id, 
               p.codigo_barras AS codigo, 
               p.nombre AS nombre_producto, 
               p.precio, 
               p.moneda, 
               IFNULL(d.nombre, '⚠️ GENERAL') AS depto_nombre 
        FROM productos p 
        LEFT JOIN departamentos d ON p.id_departamento = d.id";

if ($depto_seleccionado !== 'TODOS') {
    $depto_esc = mysqli_real_escape_string($conexion, $depto_seleccionado);
    if ($depto_seleccionado === '⚠️ GENERAL') {
        $sql .= " WHERE d.nombre IS NULL";
    } else {
        $sql .= " WHERE d.nombre = '$depto_esc'";
    }
}

$sql .= " ORDER BY depto_nombre ASC, p.nombre ASC";
$resultado = mysqli_query($conexion, $sql);

// AGRUPACIÓN DE DATOS
$mercancia = [];
if ($resultado) {
    while ($fila = mysqli_fetch_assoc($resultado)) {
        $depto = $fila['depto_nombre'];
        $mercancia[$depto][] = $fila;
    }
}

// TRAER TODOS LOS DEPARTAMENTOS PARA EL SELECTOR DEL FILTRO
$deptos_query = mysqli_query($conexion, "SELECT nombre FROM departamentos ORDER BY nombre ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Generador de Precios para Exhibición</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #f1f5f9; color: #1e293b; margin: 0; padding: 0; display: flex; }
        
        /* --- ESTILOS EN PANTALLA --- */
        .sidebar {
            width: 260px; background: #0f172a; color: white; min-height: 100vh;
            padding: 20px; box-sizing: border-box; flex-shrink: 0; position: fixed; height: 100%; overflow-y: auto;
        }
        .sidebar h2 { font-size: 1.2rem; font-weight: 900; margin-bottom: 30px; color: #3b82f6; text-align: center; letter-spacing: 1px; }
        .sidebar nav { display: flex; flex-direction: column; gap: 8px; }
        .nav-link {
            display: flex; align-items: center; gap: 10px; color: #94a3b8; text-decoration: none;
            padding: 12px 16px; border-radius: 8px; font-weight: 600; transition: all 0.2s;
        }
        .nav-link:hover { background: #1e293b; color: white; }
        .nav-link.active { background: #2563eb; color: white; }
        .nav-link.salir { margin-top: 20px; color: #f87171; }
        .nav-link.salir:hover { background: #7f1d1d; color: white; }

        .main-content { margin-left: 260px; padding: 30px; flex-grow: 1; min-width: 0; box-sizing: border-box; }

        .no-print {
            background: white; padding: 20px; border-radius: 12px; 
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1); margin-bottom: 30px;
            display: flex; flex-direction: column; gap: 15px;
        }
        .barra-superior { display: flex; justify-content: space-between; align-items: center; width: 100%; gap: 15px; flex-wrap: wrap; }
        .bloque-acciones { display: flex; gap: 10px; align-items: center; flex-wrap: wrap; }
        .barra-busqueda { width: 100%; }
        
        .input-buscar {
            width: 100%; padding: 12px 16px; border-radius: 8px; 
            border: 2px solid #cbd5e1; font-size: 1rem; font-weight: 500;
            box-sizing: border-box; outline: none; transition: border-color 0.2s;
        }
        .input-buscar:focus { border-color: #3b82f6; }

        .btn {
            background: #4f46e5; color: white; padding: 10px 20px; 
            border-radius: 8px; text-decoration: none; font-weight: bold; border: none; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center; transition: background 0.2s;
        }
        .btn-print { background: #10b981; }
        .btn-print:disabled { background: #cbd5e1; color: #94a3b8; cursor: not-allowed; }
        .btn-clear { background: #64748b; }
        .btn-select-all { background: #2563eb; }
        .btn-select-depto { background: #0284c7; }
        
        select { padding: 10px; border-radius: 8px; border: 1px solid #cbd5e1; font-weight: 600; background: white; }

        .depto-seccion { margin-bottom: 40px; page-break-inside: avoid; }
        .depto-titulo { font-size: 1.4rem; font-weight: 800; color: #4f46e5; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; margin-bottom: 15px; }

        .grid-etiquetas { display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr)); gap: 15px; }

        /* Estilo del Hablador */
        .etiqueta {
            background: white; border: 2px dashed #cbd5e1; border-radius: 8px;
            padding: 12px; text-align: center; box-sizing: border-box;
            display: flex; flex-direction: column; justify-content: space-between;
            height: 160px; position: relative; transition: all 0.2s ease;
        }
        
        .etiqueta.oculto-por-busqueda { display: none !important; }

        /* Estado NO SELECCIONADO */
        .etiqueta.no-seleccionada { opacity: 0.5; border-color: #cbd5e1; background: #f8fafc; }

        /* Estado SELECCIONADO (Azul) */
        .etiqueta.seleccionada {
            opacity: 1; border: 2.5px solid #2563eb; background: #eff6ff;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.1);
        }

        .selector-etiqueta { position: absolute; top: 10px; right: 10px; width: 18px; height: 18px; cursor: pointer; z-index: 10; }
        .prod-nombre { font-size: 0.9rem; font-weight: 700; color: #0f172a; margin: 0 0 4px 0; line-height: 1.2; text-transform: uppercase; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; padding-right: 18px; }
        .prod-codigo { font-size: 0.68rem; color: #64748b; font-family: monospace; margin-bottom: 6px; }
        
        /* CONTENEDOR DE PRECIOS RESALTADOS */
        .precios-box { 
            display: flex; 
            flex-direction: column;
            justify-content: center; 
            align-items: center; 
            margin-top: auto; 
            border-top: 1px dashed #cbd5e1; 
            padding-top: 6px; 
            gap: 2px;
        }
        
        /* PRECIO EN BOLÍVARES: SÚPER RESALTADO Y GRANDE */
        .precio-bs { 
            color: #000; 
            font-size: 2.2rem; 
            font-weight: 900; 
            line-height: 1.1;
            letter-spacing: -0.5px;
        }
        
        /* PRECIO EN DÓLARES: SECUNDARIO Y MÁS PEQUEÑO */
        .precio-usd { 
            color: #15803d; 
            font-size: 1.7rem; 
            font-weight: 700; 
            opacity: 0.9;
        }

        #alerta-vacio { color: #ef4444; font-size: 0.85rem; font-weight: bold; margin-top: 5px; display: none; }

        /* --- CONFIGURACIÓN DE IMPRESIÓN --- */
        @media print {
            .sidebar, .no-print, .selector-etiqueta { display: none !important; }
            body { background: white; padding: 0; margin: 0; display: block; }
            .main-content { margin-left: 0 !important; padding: 0 !important; }
            
            .etiqueta.no-seleccionada { display: none !important; }
            .depto-seccion:has(.grid-etiquetas:empty),
            .depto-seccion:not(:has(.etiqueta.seleccionada)) { display: none !important; }

            .grid-etiquetas { grid-template-columns: repeat(3, 1fr) !important; gap: 10px !important; }
            .etiqueta { border: 1px solid #94a3b8 !important; page-break-inside: avoid; height: 145px; opacity: 1 !important; background: white !important; box-shadow: none !important; }
            .depto-titulo { font-size: 1.1rem; margin-top: 20px; }
            
            .precio-bs { color: #000000 !important; font-size: 3.7rem !important; }
            .precio-usd { color: #333333 !important; font-size: 1.05rem !important; }
        }
    </style>
</head>
<body>

<aside class="sidebar">
    <h2>POS SYSTEM PRO</h2>
    <nav>
        <a href="ventas.php" class="nav-link">🛒 Ventas</a>
        <a href="inventario.php" class="nav-link">📦 Inventario</a>
        <a href="corte.php" class="nav-link">📊 Corte</a>
        <a href="estadisticas.php" class="nav-link">📊 Estadisticas</a>
        <a href="historial.php" class="nav-link">📋 HISTORIAL</a>
        <a href="historial_facturas.php" class="nav-link">📋 FACTURAS</a>
        <a href="proveedores.php" class="nav-link">🚚 Proveedores</a>
        <a href="modulo_reporte_stock.php" class="nav-link">📋 Reporte_stock</a>
        <a href="creditos.php" class="nav-link">💳 Créditos</a>
        <a href="mercancia_departamentos.php" class="nav-link">📁 Departamentos</a>
        <a href="configuracion.php" class="nav-link active">⚙️ Ajustes</a>
        <a href="logout.php" class="nav-link salir">🚪 Salir</a>
    </nav>
</aside>

<div class="main-content">
    <div class="no-print">
        <div class="barra-superior">
            <div>
                <h1 style="margin:0; font-size: 1.5rem;">Etiquetas de Precios para Vitrina</h1>
                <p style="color: #64748b; margin: 5px 0 0 0;">Filtra por departamento, selecciona en lote o individualmente e imprime.</p>
                <div id="alerta-vacio">⚠️ Debes seleccionar al menos 1 producto para poder imprimir.</div>
            </div>
            
            <div class="bloque-acciones">
                <form method="GET" style="display: flex; margin: 0;">
                    <select name="depto" onchange="this.form.submit()">
                        <option value="TODOS" <?= $depto_seleccionado === 'TODOS' ? 'selected' : '' ?>>📁 Todos los Departamentos</option>
                        <option value="⚠️ GENERAL" <?= $depto_seleccionado === '⚠️ GENERAL' ? 'selected' : '' ?>>⚠️ GENERAL</option>
                        <?php while($d = mysqli_fetch_assoc($deptos_query)): ?>
                            <option value="<?= htmlspecialchars($d['nombre']) ?>" <?= $depto_seleccionado === $d['nombre'] ? 'selected' : '' ?>><?= htmlspecialchars($d['nombre']) ?></option>
                        <?php endwhile; ?>
                    </select>
                </form>

                <button onclick="seleccionarTodoElDepto()" class="btn btn-select-depto">☑️ Seleccionar Depto. Actual</button>
                <button onclick="seleccionarTodoGlobal()" class="btn btn-select-all">✅ Seleccionar Todo (Bs y $)</button>
                <button onclick="limpiarSeleccion()" class="btn btn-clear">🧹 Limpiar</button>
                
                <button id="btnImprimir" onclick="window.print()" class="btn btn-print" disabled>🖨️ Imprimir Selección</button>
                <a href="inventario.php" class="btn" style="background: #0f172a;">Volver</a>
            </div>
        </div>
        
        <div class="barra-busqueda">
            <input type="text" id="txtBuscar" class="input-buscar" placeholder="🔍 Escribe el nombre del producto o código de barras para filtrar..." oninput="filtrarProductos()">
        </div>
    </div>

    <!-- RENDERIZADO DE LAS TARJETAS -->
    <?php foreach ($mercancia as $depto => $productos): ?>
        <div class="depto-seccion" data-depto-name="<?= htmlspecialchars($depto) ?>">
            <div class="depto-titulo">Departamento: <?= htmlspecialchars($depto) ?></div>
            <div class="grid-etiquetas">
                <?php foreach ($productos as $p): 
                    $base = strtoupper($p['moneda'] ?? 'USD');
                    $precio_orig = (float)$p['precio'];
                    
                    if ($base === 'VES' || $base === 'BS') {
                        $precio_bs = $precio_orig;
                        $precio_usd = $precio_orig / $tasa_actual;
                    } else {
                        $precio_usd = $precio_orig;
                        $precio_bs = $precio_orig * $tasa_actual;
                    }
                ?>
                    <div class="etiqueta no-seleccionada" 
                         data-id="<?= $p['id'] ?>" 
                         data-nombre="<?= strtolower(htmlspecialchars($p['nombre_producto'])) ?>" 
                         data-codigo="<?= strtolower(htmlspecialchars($p['codigo'])) ?>">
                        
                        <input type="checkbox" class="selector-etiqueta" onchange="alternarProducto(this, <?= $p['id'] ?>)">
                        
                        <div class="prod-nombre"><?= htmlspecialchars($p['nombre_producto']) ?></div>
                        <div class="prod-codigo">Cod: <?= htmlspecialchars($p['codigo']) ?></div>
                        
                        <div class="precios-box">
                            <!-- BS ARRIBA Y MÁS GRANDE -->
                            <div class="precio-bs">Bs <?= number_format($precio_bs, 2, ',', '.') ?></div>
                            <!-- USD ABAJO Y MÁS PEQUEÑO -->
                            <div class="precio-usd">Ref: $<?= number_format($precio_usd, 2, '.', ',') ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<script>
    document.addEventListener("DOMContentLoaded", () => {
        let seleccionados = JSON.parse(localStorage.getItem("habladores_seleccionados")) || [];
        
        document.querySelectorAll(".etiqueta").forEach(tarjeta => {
            let id = parseInt(tarjeta.getAttribute("data-id"));
            let checkbox = tarjeta.querySelector(".selector-etiqueta");
            
            if (seleccionados.includes(id)) {
                checkbox.checked = true;
                tarjeta.classList.remove("no-seleccionada");
                tarjeta.classList.add("seleccionada");
            } else {
                checkbox.checked = false;
                tarjeta.classList.remove("seleccionada");
                tarjeta.classList.add("no-seleccionada");
            }
        });
        actualizarVisibilidadSecciones();
        validarBotonImpresion();
    });

    function alternarProducto(checkbox, id) {
        let tarjeta = checkbox.closest(".etiqueta");
        let seleccionados = JSON.parse(localStorage.getItem("habladores_seleccionados")) || [];
        
        if (checkbox.checked) {
            tarjeta.classList.remove("no-seleccionada");
            tarjeta.classList.add("seleccionada");
            if (!seleccionados.includes(id)) seleccionados.push(id);
        } else {
            tarjeta.classList.remove("seleccionada");
            tarjeta.classList.add("no-seleccionada");
            seleccionados = seleccionados.filter(item => item !== id);
        }
        
        localStorage.setItem("habladores_seleccionados", JSON.stringify(seleccionados));
        validarBotonImpresion();
    }

    function seleccionarTodoGlobal() {
        let seleccionados = JSON.parse(localStorage.getItem("habladores_seleccionados")) || [];
        
        document.querySelectorAll(".etiqueta:not(.oculto-por-busqueda)").forEach(tarjeta => {
            let id = parseInt(tarjeta.getAttribute("data-id"));
            let checkbox = tarjeta.querySelector(".selector-etiqueta");
            
            checkbox.checked = true;
            tarjeta.classList.remove("no-seleccionada");
            tarjeta.classList.add("seleccionada");
            if (!seleccionados.includes(id)) seleccionados.push(id);
        });
        
        localStorage.setItem("habladores_seleccionados", JSON.stringify(seleccionados));
        validarBotonImpresion();
    }

    function seleccionarTodoElDepto() {
        let seleccionados = JSON.parse(localStorage.getItem("habladores_seleccionados")) || [];
        
        document.querySelectorAll(".depto-seccion").forEach(seccion => {
            if(seccion.style.display !== "none") {
                seccion.querySelectorAll(".etiqueta:not(.oculto-por-busqueda)").forEach(tarjeta => {
                    let id = parseInt(tarjeta.getAttribute("data-id"));
                    let checkbox = tarjeta.querySelector(".selector-etiqueta");
                    
                    checkbox.checked = true;
                    tarjeta.classList.remove("no-seleccionada");
                    tarjeta.classList.add("seleccionada");
                    if (!seleccionados.includes(id)) seleccionados.push(id);
                });
            }
        });
        
        localStorage.setItem("habladores_seleccionados", JSON.stringify(seleccionados));
        validarBotonImpresion();
    }

    function validarBotonImpresion() {
        let seleccionados = JSON.parse(localStorage.getItem("habladores_seleccionados")) || [];
        let btn = document.getElementById("btnImprimir");
        let alerta = document.getElementById("alerta-vacio");
        
        if (seleccionados.length > 0) {
            btn.disabled = false;
            alerta.style.display = "none";
        } else {
            btn.disabled = true;
            alerta.style.display = "block";
        }
    }

    function filtrarProductos() {
        let query = document.getElementById("txtBuscar").value.toLowerCase().trim();
        
        document.querySelectorAll(".etiqueta").forEach(tarjeta => {
            let nombre = tarjeta.getAttribute("data-nombre");
            let codigo = tarjeta.getAttribute("data-codigo");
            
            if (nombre.includes(query) || codigo.includes(query)) {
                tarjeta.classList.remove("oculto-por-busqueda");
            } else {
                tarjeta.classList.add("oculto-por-busqueda");
            }
        });
        
        actualizarVisibilidadSecciones();
    }

    function actualizarVisibilidadSecciones() {
        document.querySelectorAll(".depto-seccion").forEach(seccion => {
            let totalVisibles = seccion.querySelectorAll(".etiqueta:not(.oculto-por-busqueda)").length;
            if (totalVisibles === 0) {
                seccion.style.display = "none";
            } else {
                seccion.style.display = "block";
            }
        });
    }

    function limpiarSeleccion() {
        localStorage.removeItem("habladores_seleccionados");
        document.querySelectorAll(".etiqueta").forEach(tarjeta => {
            tarjeta.querySelector(".selector-etiqueta").checked = false;
            tarjeta.classList.remove("seleccionada");
            tarjeta.classList.add("no-seleccionada");
        });
        validarBotonImpresion();
    }
</script>

</body>
</html>