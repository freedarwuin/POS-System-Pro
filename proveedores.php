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
    foreach ($productos_descripciones as $index => $d_prod) {
        $desc_articulos .= "- " . $d_prod . " (Cant: " . ($productos_cantidades[$index] ?? 0) . ")\n";
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
            $cant = (int)($productos_cantidades[$key] ?? 0);

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
    </style>
</head>
<body>
<?php include_once __DIR__ . '/sidebar.php'; ?>

    <!-- IMPRESIÓN DE COMPONENTES DE ALERTAS -->
    <?php if (isset($_SESSION['error_sistema'])): ?>
        <div class="toast-error" id="errorToast">⚠️ <div><?= htmlspecialchars($_SESSION['error_sistema']) ?></div></div>
        <script>setTimeout(() => { document.getElementById('errorToast')?.remove(); }, 4500);</script>
        <?php unset($_SESSION['error_sistema']); ?>
    <?php endif; ?>

    <?php if (isset($_SESSION['exito_sistema'])): ?>
        <div class="toast-exito" id="exitoToast">✅ <div><?= htmlspecialchars($_SESSION['exito_sistema']) ?></div></div>
        <script>setTimeout(() => { document.getElementById('exitoToast')?.remove(); }, 4500);</script>
        <?php unset($_SESSION['exito_sistema']); ?>
    <?php endif; ?>

    <div class="header-container">
        <h2 style="margin:0; font-weight: 800;">🏢 Gestión de Proveedores</h2>
        <div style="display:flex; gap:12px;">
            <a href="inventario.php" class="btn" style="background:var(--slate)">📦 INVENTARIO</a>
            <a href="ventas.php" class="btn" style="background:var(--blue)">🛒 IR A CAJA</a>
        </div>
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
                        <a href="?eliminar=<?= $prov['id'] ?>" class="btn-action" style="background:var(--dark);" onclick="return confirm('¿Está seguro de eliminar a este proveedor?')">🗑️</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div class="pagination">
        <?php for($i=1; $i<=$total_paginas; $i++): ?>
            <a href="?p=<?= $i ?>" class="page-link <?= ($i==$pagina_actual)?'active':'' ?>"><?= $i ?></a>
        <?php endfor; ?>
    </div>
</body>
</html>
