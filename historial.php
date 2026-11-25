<?php
session_start();
// Si no es Admin, mandarlo a ventas.php inmediatamente
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}
// ... resto de tu código del archivo ...
?>

<?php
session_start();
if (!isset($_SESSION['usuario'])) { header("Location: login.php"); exit(); }
include 'conexion.php';

// --- 1. CONFIGURACIÓN DE PAGINACIÓN ---
$registros_por_pagina = 15;
$pagina_actual = isset($_GET['p']) ? (int)$_GET['p'] : 1;
if ($pagina_actual < 1) $pagina_actual = 1;
$inicio_limit = ($pagina_actual - 1) * $registros_por_pagina;

// --- 2. CONSULTA DE DATOS ---
$total_res = mysqli_query($conexion, "SELECT COUNT(*) as total FROM historial_movimientos");
$total_reg = mysqli_fetch_assoc($total_res)['total'];
$total_paginas = ceil($total_reg / $registros_por_pagina);

$query = "SELECT h.*, p.nombre as producto_nombre 
          FROM historial_movimientos h 
          LEFT JOIN productos p ON h.id_producto = p.id 
          ORDER BY h.id DESC 
          LIMIT $inicio_limit, $registros_por_pagina";

$resultado = mysqli_query($conexion, $query);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Historial de Movimientos</title>
    <style>
        :root {
            --dark: #1e293b; --slate: #64748b; --blue: #2563eb; --red: #ef4444;
            --bg-entrada: #dcfce7; --text-entrada: #166534;
            --bg-ajuste: #fef9c3;  --text-ajuste: #854d0e;
            --bg-eliminar: #fee2e2; --text-eliminar: #991b1b;
        }
        body { font-family: 'Segoe UI', sans-serif; background: #f1f5f9; margin: 0; padding: 20px; }
        
        .header-container { background: var(--dark); padding: 20px 25px; color: white; border-radius: 15px; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        
        /* Toolbar con filtros alineados */
        .toolbar { display: flex; gap: 15px; background: white; padding: 15px; border-radius: 15px; margin-bottom: 20px; align-items: center; box-shadow: 0 2px 4px rgba(0,0,0,0.02); }
        .search-input { padding: 12px; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; flex-grow: 1; }
        .date-input { padding: 11px; border: 2px solid #e2e8f0; border-radius: 10px; outline: none; color: var(--slate); font-weight: bold; }

        .btn-volver { padding: 10px 18px; background: var(--slate); color: white; text-decoration: none; border-radius: 10px; font-weight: bold; font-size: 14px; }

        table { width: 100%; border-collapse: collapse; background: white; border-radius: 15px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0,0,0,0.05); }
        th { background: #f8fafc; padding: 18px; text-align: left; font-size: 12px; color: var(--slate); text-transform: uppercase; }
        td { padding: 18px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: var(--dark); }

        .badge { display: inline-block; width: 140px; padding: 8px 0; border-radius: 8px; font-size: 10px; font-weight: bold; text-transform: uppercase; text-align: center; }
        .color-ajuste { background: var(--bg-ajuste); color: var(--text-ajuste); }
        .color-entrada { background: var(--bg-entrada); color: var(--text-entrada); }
        .color-eliminar { background: var(--bg-eliminar); color: var(--text-eliminar); }

        .pagination { margin-top: 25px; display: flex; justify-content: center; gap: 8px; }
        .pag-link { padding: 10px 18px; background: white; border-radius: 10px; text-decoration: none; color: var(--dark); font-weight: bold; }
        .pag-active { background: var(--blue) !important; color: white !important; }

        .tag-borrado { color: var(--red); font-size: 10px; border: 1px solid var(--red); padding: 2px 5px; border-radius: 4px; margin-right: 5px; font-weight: bold; }
    </style>
</head>
<body>

    <div class="header-container">
        <h2 style="margin:0;">📋 Historial de Movimientos</h2>
        <a href="inventario.php" class="btn-volver">⬅ Volver al Inventario</a>
    </div>

    <div class="toolbar">
        <input type="text" id="busq" class="search-input" placeholder="Buscar por producto, tipo o usuario..." onkeyup="filtrarTodo()">
        
        <label style="font-size: 12px; font-weight: bold; color: var(--slate);">CALENDARIO:</label>
        <input type="date" id="fechaBusq" class="date-input" onchange="filtrarTodo()">
        
        <button onclick="limpiarFiltros()" style="background:none; border:none; color:var(--blue); cursor:pointer; font-weight:bold; font-size:12px;">Limpiar</button>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 150px;">Fecha</th>
                <th>Producto</th>
                <th style="width: 160px; text-align: center;">Movimiento</th>
                <th style="width: 80px;">Cant.</th>
                <th>Detalle / Motivo</th>
                <th>Usuario</th>
            </tr>
        </thead>
        <tbody id="tablaBody">
            <?php while($row = mysqli_fetch_assoc($resultado)): 
                $tipo = strtoupper($row['tipo_movimiento']);
                $clase = (strpos($tipo, 'AJUSTE') !== false) ? 'color-ajuste' : 
                         ((strpos($tipo, 'ELIMIN') !== false) ? 'color-eliminar' : 'color-entrada');
                
                // Formato de fecha para el atributo de búsqueda de JS (YYYY-MM-DD)
                $fecha_js = date("Y-m-d", strtotime($row['fecha']));
            ?>
            <tr class="fila" data-fecha="<?= $fecha_js ?>">
                <td style="font-size:13px; color:var(--slate);"><?= date("d/m/y H:i", strtotime($row['fecha'])) ?></td>
                <td>
                    <b>
                        <?php 
                        if ($row['producto_nombre']) { echo $row['producto_nombre']; } 
                        else {
                            $nombre_limpio = str_replace("Se eliminó el producto: ", "", $row['detalle']);
                            echo '<span class="tag-borrado">ELIMINADO</span>' . $nombre_limpio;
                        }
                        ?>
                    </b>
                </td>
                <td style="text-align: center;">
                    <span class="badge <?= $clase ?>"><?= $row['tipo_movimiento'] ?></span>
                </td>
                <td style="font-weight:bold;"><?= $row['cantidad'] ?></td>
                <td><small style="color:var(--slate)"><?= $row['detalle'] ?></small></td>
                <td><span style="background:#f1f5f9; padding:4px 8px; border-radius:5px; font-size:12px;">👤 <?= $row['usuario'] ?></span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="pagination">
        <?php for($i=1; $i<=$total_paginas; $i++): ?>
            <a href="?p=<?= $i ?>" class="pag-link <?= ($i==$pagina_actual)?'pag-active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>

    <script>
        function filtrarTodo() {
            let texto = document.getElementById("busq").value.toLowerCase();
            let fecha = document.getElementById("fechaBusq").value;
            let filas = document.querySelectorAll(".fila");

            filas.forEach(f => {
                let contenidoTexto = f.innerText.toLowerCase();
                let fechaFila = f.getAttribute("data-fecha");
                
                // Si hay fecha seleccionada, debe coincidir la fecha Y el texto
                let coincideTexto = contenidoTexto.includes(texto);
                let coincideFecha = (fecha === "" || fecha === fechaFila);

                if (coincideTexto && coincideFecha) {
                    f.style.display = "";
                } else {
                    f.style.display = "none";
                }
            });
        }

        function limpiarFiltros() {
            document.getElementById("busq").value = "";
            document.getElementById("fechaBusq").value = "";
            filtrarTodo();
        }
    </script>
</body>
</html>