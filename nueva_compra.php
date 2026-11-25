<?php
session_start();
include 'conexion.php';

if (!isset($_SESSION['usuario'])) {
    exit('Acceso denegado');
}

// Obtener proveedores y productos para los selectores
$res_prov = mysqli_query($conexion, "SELECT * FROM proveedores WHERE estado = 'ACTIVO'");
$res_prod = mysqli_query($conexion, "SELECT id, nombre, codigo FROM productos WHERE estado = 'ACTIVO'");

$productos_opciones = "";
while ($p = mysqli_fetch_assoc($res_prod)) {
    $productos_opciones .= "<option value='{$p['id']}'>{$p['codigo']} - {$p['nombre']}</option>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Ingreso de Mercancía</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-4">
    <h2 class="mb-4"><i class="fa-solid fa-truck-ramp-box"></i> Ingreso de Factura de Proveedor</h2>
    
    <form action="procesar_compra.php" method="POST" id="formCompra">
        <div class="card mb-4 shadow-sm">
            <div class="card-body row g-3">
                <div class="col-md-6">
                    <label class="form-label fw-bold">Proveedor</label>
                    <select name="id_proveedor" class="form-select" required>
                        <option value="">Seleccione un proveedor...</option>
                        <?php while($prov = mysqli_fetch_assoc($res_prov)): ?>
                            <option value="<?php echo $prov['id']; ?>"><?php echo htmlspecialchars($prov['nombre']); ?></option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label fw-bold">Nro. de Factura</label>
                    <input type="text" name="nro_factura" class="form-control" required placeholder="Ej: F-001234">
                </div>
            </div>
        </div>

        <div class="card shadow-sm mb-4">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Artículos Recibidos</h5>
                <button type="button" class="btn btn-sm btn-success" onclick="agregarFila()">+ Agregar Fila</button>
            </div>
            <div class="card-body p-0">
                <table class="table table-bordered mb-0" id="tablaProductos">
                    <thead class="table-light">
                        <tr>
                            <th width="50%">Producto (Ya en inventario)</th>
                            <th width="20%">Cant. Recibida</th>
                            <th width="20%">Costo Unitario ($)</th>
                            <th width="10%">Acción</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyProductos">
                        </tbody>
                </table>
            </div>
        </div>

        <div class="text-end">
            <button type="submit" class="btn btn-primary btn-lg">Procesar Ingreso y Actualizar Stock</button>
        </div>
    </form>
</div>

<script>
    // Variables para el select dinámico
    const opcionesProductos = `<?php echo $productos_opciones; ?>`;

    function agregarFila() {
        const tbody = document.getElementById('tbodyProductos');
        const tr = document.createElement('tr');
        
        tr.innerHTML = `
            <td>
                <select name="id_producto[]" class="form-select select-producto" required>
                    <option value="">Seleccione producto...</option>
                    ${opcionesProductos}
                </select>
            </td>
            <td>
                <input type="number" step="0.001" name="cantidad[]" class="form-control" required placeholder="Ej: 10 o 1.500">
            </td>
            <td>
                <input type="number" step="0.01" name="costo_unitario[]" class="form-control" required placeholder="Costo de compra">
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-danger btn-sm" onclick="this.closest('tr').remove()">X</button>
            </td>
        `;
        tbody.appendChild(tr);
    }

    // Agregar una fila inicial por defecto
    window.onload = agregarFila;
</script>
</body>
</html>