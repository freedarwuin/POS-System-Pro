<?php
session_start();

// 1. VALIDACIÓN DE LICENCIA
include 'validar_licencia.php';

// 2. SEGURIDAD: Solo Admin
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}

include 'conexion.php';
require_once __DIR__ . '/plan_respaldos_helper.php';

// Datos de sesión
$usuario_actual = $_SESSION['usuario'];
$nombre_usuario = $_SESSION['nombre'] ?? 'Administrador';

// Crear la tabla del plan si no existe y cargar su configuración.
try {
    $plan_respaldo = posObtenerPlanRespaldos($conexion);
} catch (Throwable $e) {
    error_log('[configuracion] ' . $e->getMessage());
    $plan_respaldo = ['id' => 1, 'activo' => 1, 'frecuencia' => 'semanal', 'ultimo_respaldo' => null];
}

if (empty($_SESSION['csrf_plan_respaldo'])) {
    $_SESSION['csrf_plan_respaldo'] = bin2hex(random_bytes(32));
}

$plan_frecuencias = ['diario' => 'Diario', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];
$plan_frecuencia = (string) ($plan_respaldo['frecuencia'] ?? 'semanal');
$plan_vencido = posPlanRespaldoVencido($plan_respaldo);
$plan_ultimo = !empty($plan_respaldo['ultimo_respaldo'])
    ? date('d/m/Y H:i', strtotime((string) $plan_respaldo['ultimo_respaldo']))
    : 'Nunca se ha descargado un respaldo';
$plan_proximo = null;
if (!empty($plan_respaldo['ultimo_respaldo'])) {
    $plan_intervalos = ['diario' => '+1 day', 'semanal' => '+1 week', 'mensual' => '+1 month'];
    $plan_proximo = (new DateTimeImmutable((string) $plan_respaldo['ultimo_respaldo']))
        ->modify($plan_intervalos[$plan_frecuencia] ?? '+1 week');
}
$plan_mensaje = (string) ($_SESSION['mensaje_plan_respaldo'] ?? '');
unset($_SESSION['mensaje_plan_respaldo']);

// Datos de configuración actualizados
$query = mysqli_query($conexion, "SELECT * FROM configuracion WHERE id = 1");
$data = mysqli_fetch_assoc($query);

// Obtener listado de usuarios
$res_usuarios = mysqli_query($conexion, "SELECT * FROM usuarios");

// Obtener los últimos 10 registros de la bitácora de auditoría
$res_auditoria = mysqli_query($conexion, "SELECT * FROM auditoria ORDER BY id DESC LIMIT 10");

// Obtener todos los métodos de pago
$res_metodos = mysqli_query($conexion, "SELECT * FROM metodos_pago ORDER BY id ASC");
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configuración | POS System Pro</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700&family=Inter:wght@300;400;600;700&family=Courier+Prime&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root {
            --red-main: #d81b60;
            --red-hover: #e91e63;
            --dark-sidebar: #1e283c;
            --glass-white: rgba(255, 255, 255, 0.9);
            --bg-main: #f9f5f6;
            --text-dark: #2d2d2d;
            --input-bg: #eef4fe;
        }

        body { margin: 0; padding: 0; font-family: 'Inter', sans-serif; color: var(--text-dark); min-height: 100vh; display: flex; background: var(--bg-main); }
        .sidebar { width: 280px; background: var(--dark-sidebar); height: 100vh; position: fixed; padding: 40px 0px 20px 0px; z-index: 1000; box-shadow: 4px 0 15px rgba(0,0,0,0.15); overflow-y: auto; }
        .sidebar h2 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 1.3rem; color: #ffffff; text-align: center; letter-spacing: 1.5px; margin-bottom: 40px; text-transform: uppercase; font-weight: 800; }
        .sidebar nav { display: flex; flex-direction: column; padding: 0 15px; }
        .nav-link { display: flex; align-items: center; gap: 12px; padding: 10px 20px; color: #94a3b8; text-decoration: none; border-radius: 12px; margin-bottom: 4px; transition: 0.2s; font-weight: 600; font-size: 0.9rem; }
        .nav-link:hover { background: rgba(255, 255, 255, 0.05); color: #ffffff; }
        .nav-link.active { background: #3b82f6; color: #ffffff; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3); }
        .nav-link.salir { color: #ff5252; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px; border-radius: 0; }
        .main-content { margin-left: 280px; flex: 1; padding: 40px; }
        .header h1 { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 2rem; font-weight: 800; margin: 0; }
        .config-grid { display: grid; grid-template-columns: 1fr 1.3fr; gap: 30px; margin-top: 30px; }
        .glass-card { background: #ffffff; border-radius: 24px; padding: 30px; box-shadow: 0 10px 30px rgba(0, 0, 0, 0.02); margin-bottom: 5px; }
        .card-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 0.95rem; font-weight: 800; color: #3a2d32; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; text-transform: uppercase; border-bottom: 1px solid #f1f1f1; padding-bottom: 15px; }
        .form-group { margin-bottom: 15px; }
        .form-row-flex { display: flex; gap: 15px; }
        .form-row-flex > div { flex: 1; }
        label { font-size: 0.7rem; font-weight: 800; color: #888; display: block; margin-bottom: 5px; text-transform: uppercase; }
        input, select, textarea { width: 100%; background: var(--input-bg); border: 1px solid transparent; border-radius: 12px; padding: 12px 15px; color: #222; font-size: 0.9rem; outline: none; transition: 0.2s; box-sizing: border-box; font-weight: 600; }
        input:focus, textarea:focus, select:focus { border-color: #3b82f6; background: #fff; box-shadow: 0 0 10px rgba(59, 130, 246, 0.1); }
        .btn-red-hud { background: var(--red-main); color: white; border: none; padding: 14px; border-radius: 12px; font-weight: 700; width: 100%; cursor: pointer; transition: 0.2s; text-transform: uppercase; font-family: 'Plus Jakarta Sans', sans-serif; }
        .btn-red-hud:hover { background: var(--red-hover); box-shadow: 0 8px 20px rgba(216, 27, 96, 0.2); }
        .table-hud { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table-hud th { background: #f1ecef; color: #3a2d32; padding: 12px; text-align: left; font-size: 0.75rem; font-weight: 800; text-transform: uppercase; }
        .table-hud td { padding: 12px; border-bottom: 1px solid #f5f5f5; font-size: 0.85rem; font-weight: 600; }
        .user-capsule { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 12px 15px; border-radius: 12px; margin-bottom: 8px; border-bottom: 1px solid #f5f5f5; }
        .badge-pro { background: #fce4ec; color: var(--red-main); padding: 3px 10px; border-radius: 50px; font-size: 0.65rem; font-weight: 700; }
        .actions-pro a { text-decoration: none; font-weight: 700; font-size: 0.7rem; margin-left: 12px; color: #625358; cursor: pointer; }
        .actions-pro a:hover { color: var(--red-main); }
        .preview-container { background: #cbd5e1; padding: 25px; border-radius: 20px; width: 320px; flex-shrink: 0; }
        .ticket-visual { background: #fff; padding: 15px; font-family: 'Courier Prime', monospace; font-size: 11px; color: #000; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; }
        .modal-box { background: #fff; padding: 35px; border-radius: 30px; width: 420px; box-shadow: 0 20px 50px rgba(0,0,0,0.2); }
        .plan-status { padding: 12px 14px; border-radius: 12px; font-size: .82rem; line-height: 1.5; margin-bottom: 12px; }
        .plan-status.pending { background: #fef2f2; color: #7f1d1d; border: 1px solid #fecaca; }
        .plan-status.ok { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .plan-status.paused { background: #f1f5f9; color: #334155; border: 1px solid #cbd5e1; }
        .plan-grid { display: grid; grid-template-columns: minmax(180px, 1fr) auto auto; gap: 12px; align-items: end; }
        .plan-checkbox { display: flex; align-items: center; gap: 8px; min-height: 44px; font-size: .82rem; color: #475569; }
        .plan-checkbox input { width: 18px; height: 18px; }
        .plan-save { border: 0; border-radius: 10px; background: #2563eb; color: #fff; padding: 12px 16px; min-height: 44px; font-weight: 700; cursor: pointer; }
        @media (max-width: 700px) { .plan-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<?php include_once __DIR__ . '/sidebar.php'; ?>

<main class="main-content">
    <div class="header">
        <h1>Centro de Control</h1>
        <p style="opacity: 0.6; font-weight: 600;">Administración global del sistema</p>
    </div>

    <div class="config-grid">
        <div style="display: flex; flex-direction: column; gap: 30px;">
            <div class="glass-card">
                <div class="card-title"><i class="fa-solid fa-sliders"></i> Finanzas & Parámetros Fiscales</div>
                <form action="actualizar_config_avanzada.php" method="POST">
                    <input type="hidden" name="seccion" value="finanzas">
                    <div class="form-group"><label>Tasa de Cambio USD ($)</label><input type="number" step="0.01" name="tasa_dolar" value="<?php echo $data['tasa_dolar']; ?>"></div>
                    <div class="form-row-flex">
                        <div class="form-group"><label>Habilitar IVA</label><select name="usar_iva"><option value="1" <?php echo $data['usar_iva'] == 1 ? 'selected' : ''; ?>>Sí</option><option value="0" <?php echo $data['usar_iva'] == 0 ? 'selected' : ''; ?>>No</option></select></div>
                        <div class="form-group"><label>% IVA</label><input type="number" step="0.01" name="impuesto_iva" value="<?php echo $data['impuesto_iva']; ?>"></div>
                    </div>
                    <div class="form-row-flex">
                        <div class="form-group"><label>Habilitar IGTF (Divisas)</label><select name="usar_igtf"><option value="1" <?php echo $data['usar_igtf'] == 1 ? 'selected' : ''; ?>>Sí</option><option value="0" <?php echo $data['usar_igtf'] == 0 ? 'selected' : ''; ?>>No</option></select></div>
                        <div class="form-group"><label>% IGTF</label><input type="number" step="0.01" name="impuesto_igtf" value="<?php echo $data['impuesto_igtf']; ?>"></div>
                    </div>
                    <button type="submit" class="btn-red-hud">Actualizar Finanzas</button>
                </form>
            </div>

            <div class="glass-card">
                <div class="card-title"><i class="fa-solid fa-wallet"></i> Métodos de Pago Visibles</div>
                <p style="font-size: 0.8rem; color: #666; margin-bottom: 20px;">Configura qué métodos de pago aparecerán disponibles en el módulo de ventas.</p>
                <button type="button" onclick="abrirModalMetodos()" class="btn-red-hud"><i class="fa-solid fa-eye"></i> Seleccionar Métodos Visibles</button>
            </div>

            <div class="glass-card">
                <div class="card-title" style="color: #25D366;"><i class="fa-brands fa-whatsapp"></i> Reportes Automatizados (WhatsApp)</div>
                <form action="actualizar_config_avanzada.php" method="POST">
                    <input type="hidden" name="seccion" value="whatsapp">
                    <div class="form-group"><label>Número de WhatsApp del Dueño</label><input type="text" name="whatsapp_telefono" placeholder="Ej: 584121234567" value="<?php echo $data['whatsapp_telefono']; ?>" required><small style="font-size: 0.75rem; color: #777; display: block; margin-top: 4px;">Coloca el código de país sin el signo (+). Ejemplo: 58 para Venezuela.</small></div>
                    <div class="form-group"><label>API Key (Proveedor de Mensajería)</label><input type="password" name="whatsapp_api_key" placeholder="Tu API Token" value="<?php echo $data['whatsapp_api_key']; ?>"></div>
                    <button type="submit" class="btn-red-hud" style="background: #25D366;">Guardar Parámetros de WhatsApp</button>
                </form>
            </div>

            <div class="glass-card" id="respaldo-estructurado">
                <div class="card-title"><i class="fa-solid fa-cloud-arrow-up"></i> Respaldo Estructurado</div>
                <p style="font-size: 0.8rem; color: #666; margin-bottom: 20px;">Descarga una copia completa (.SQL) de tu base de datos actual.</p>

                <?php if ($plan_mensaje !== ''): ?>
                    <div class="plan-status ok" role="status"><?php echo htmlspecialchars($plan_mensaje, ENT_QUOTES, 'UTF-8'); ?></div>
                <?php endif; ?>

                <?php if (empty($plan_respaldo['activo'])): ?>
                    <div class="plan-status paused"><strong>Recordatorios desactivados.</strong> Puedes activarlos nuevamente desde el plan.</div>
                <?php elseif ($plan_vencido): ?>
                    <div class="plan-status pending" role="alert"><strong>Respaldo pendiente.</strong> Ya corresponde realizar el respaldo <?php echo htmlspecialchars(strtolower($plan_frecuencias[$plan_frecuencia]), ENT_QUOTES, 'UTF-8'); ?>.</div>
                <?php else: ?>
                    <div class="plan-status ok"><strong>Plan al día.</strong> Próximo recordatorio: <?php echo htmlspecialchars($plan_proximo ? $plan_proximo->format('d/m/Y H:i') : '', ENT_QUOTES, 'UTF-8'); ?>.</div>
                <?php endif; ?>

                <div style="font-size:.82rem;color:#475569;margin-bottom:14px;"><strong>Último respaldo registrado:</strong> <?php echo htmlspecialchars($plan_ultimo, ENT_QUOTES, 'UTF-8'); ?></div>

                <form action="guardar_plan_respaldo.php" method="post" style="margin-bottom:16px;">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_plan_respaldo'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="plan-grid">
                        <div>
                            <label for="frecuencia-plan-respaldo">Frecuencia</label>
                            <select id="frecuencia-plan-respaldo" name="frecuencia" required>
                                <?php foreach ($plan_frecuencias as $valor => $etiqueta): ?>
                                    <option value="<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>" <?php echo $plan_frecuencia === $valor ? 'selected' : ''; ?>><?php echo htmlspecialchars($etiqueta, ENT_QUOTES, 'UTF-8'); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <label class="plan-checkbox"><input type="checkbox" name="activo" value="1" <?php echo !empty($plan_respaldo['activo']) ? 'checked' : ''; ?> style="width:18px;"> Activar</label>
                        <button type="submit" class="plan-save">Guardar plan</button>
                    </div>
                </form>

                <button onclick="generarBackup()" class="btn-red-hud" style="background: #fff; color: var(--red-main); border: 2px solid var(--red-main);">
                    <i class="fa-solid fa-database"></i> Descargar Respaldo SQL
                </button>
                <div style="margin-top:12px;font-size:.76rem;color:#64748b;">Descarga el archivo SQL y conserva una copia fuera del equipo POS. La fecha del último respaldo se actualiza después de completar la descarga.</div>
            </div>
        </div>

        <div style="display: flex; flex-direction: column; gap: 30px;">
            <div class="glass-card">
                <div class="card-title"><i class="fa-solid fa-user-shield"></i> Gestión de Usuarios</div>
                <form action="gestion_usuarios.php" method="POST" style="display: flex; gap: 8px; margin-bottom: 25px;"><input type="text" name="nuevo_username" placeholder="Usuario" required><input type="password" name="nuevo_password" placeholder="Clave" required><select name="nuevo_rol" style="width: 100px;"><option value="Cajero">Cajero</option><option value="Admin">Admin</option></select><button type="submit" name="accion" value="crear" class="btn-red-hud" style="width: 50px; padding:0;">+</button></form>
                <div style="max-height: 250px; overflow-y: auto;">
                    <?php while($user = mysqli_fetch_assoc($res_usuarios)): ?>
                    <div class="user-capsule"><div><strong><?php echo $user['username']; ?></strong><br><span class="badge-pro"><?php echo $user['rol']; ?></span></div><div class="actions-pro"><?php if($user['username'] != 'admin'): ?><a onclick='abrirModalPermisos(<?php echo json_encode($user); ?>)'>PERMISOS</a><a href="gestion_usuarios.php?eliminar=<?php echo $user['id']; ?>" onclick="return confirm('¿Eliminar?')">BORRAR</a><?php else: ?><span style="font-size: 0.7rem; opacity: 0.4;">SISTEMA</span><?php endif; ?></div></div>
                    <?php endwhile; ?>
                </div>
            </div>
        </div>

        <div class="glass-card" style="grid-column: span 2;"><div class="card-title"><i class="fa-solid fa-receipt"></i> Personalización de Ticket & Vista Previa</div><div style="display: flex; gap: 40px; align-items: start; flex-wrap: wrap;"><form action="actualizar_ticket.php" method="POST" style="flex: 1; min-width: 300px;"><div class="form-group"><label>Nombre del Negocio</label><input type="text" name="nombre_negocio" id="in_nombre" value="<?php echo $data['nombre_negocio']; ?>" onkeyup="updatePreview()"></div><div class="form-group"><label>RIF / Identificación</label><input type="text" name="rif" id="in_rif" value="<?php echo $data['rif']; ?>" onkeyup="updatePreview()"></div><div class="form-group"><label>Dirección Fiscal</label><textarea name="direccion" id="in_dir" rows="3" onkeyup="updatePreview()"><?php echo $data['direccion']; ?></textarea></div><div class="form-group"><label>Mensaje de Pie (Despedida)</label><input type="text" name="mensaje_ticket" id="in_msg" value="<?php echo $data['mensaje_ticket']; ?>" onkeyup="updatePreview()"></div><button type="submit" class="btn-red-hud">Guardar Cambios en Ticket</button></form><div class="preview-container"><h3 style="text-align:center; font-size: 0.8rem; margin-bottom: 15px; color: #1e283c;">VISTA PREVIA REAL</h3><div id="ticket_visual" class="ticket-visual"><center><b id="pre_nombre" style="font-size: 13px; text-transform: uppercase;"><?php echo $data['nombre_negocio']; ?></b><br><span id="pre_rif">RIF: <?php echo $data['rif']; ?></span><br><small id="pre_dir" style="font-size: 9px;"><?php echo $data['direccion']; ?></small></center><hr style="border: none; border-top: 1px dashed #000; margin: 10px 0;"><div style="font-size: 9px;">FACTURA: #0001<br>FECHA: <?php echo date('d/m/Y'); ?><br></div><table style="width: 100%; margin-top: 10px; font-size: 9px;"><tr><td>1.00</td><td>PROD. EJEMPLO</td><td align="right">100,00</td></tr></table><hr style="border: none; border-top: 1px dashed #000; margin: 5px 0;"><div style="text-align: right; font-size: 9px;">Subtotal: 100,00<br>IVA (16%): 16,00<br><b>TOTAL BS: 116,00</b></div><hr style="border: none; border-top: 1px dashed #000; margin: 10px 0;"><center id="pre_msg"><?php echo $data['mensaje_ticket']; ?></center></div></div></div></div>

        <div class="glass-card" style="grid-column: span 2;"><div class="card-title"><i class="fa-solid fa-clock-rotate-left"></i> Bitácora de Auditoría (Últimos 10 Eventos)</div><div style="overflow-x: auto;"><table class="table-hud"><thead><tr><th>Fecha y Hora</th><th>Usuario</th><th>Acción Técnica</th><th>Detalle de Operación</th></tr></thead><tbody><?php if(mysqli_num_rows($res_auditoria) > 0): ?><?php while($log = mysqli_fetch_assoc($res_auditoria)): ?><tr><td style="color:#666;"><?php echo date('d/m/Y h:i A', strtotime($log['fecha_hora'])); ?></td><td><span style="background:#eef4fe; padding:4px 8px; border-radius:6px; font-weight:700;"><?php echo $log['usuario']; ?></span></td><td style="color:var(--red-main); font-weight:700;"><?php echo $log['accion']; ?></td><td style="font-size:0.8rem; color:#444;"><?php echo htmlspecialchars($log['detalles']); ?></td></tr><?php endwhile; ?><?php else: ?><tr><td colspan="4" align="center" style="color:#999;">Ninguna actividad registrada en auditoría aún.</td></tr><?php endif; ?></tbody></table></div></div>
    </div>
</main>

<div id="modalMetodos" class="modal-overlay"><div class="modal-box"><h3><i class="fa-solid fa-wallet"></i> Métodos de Pago Visibles</h3><p style="font-size: 0.8rem; font-weight: 600; margin-bottom: 20px; color: #555;">Selecciona los métodos que estarán activos en Ventas:</p><form action="actualizar_metodos_pago.php" method="POST"><div style="background: #f8f9fa; border-radius: 15px; padding: 10px; margin-bottom: 20px; border: 1px solid #eee; max-height: 280px; overflow-y:auto; display: flex; flex-direction: column; gap: 8px;"><?php $tiene_biopago = false; mysqli_data_seek($res_metodos, 0); while($m = mysqli_fetch_assoc($res_metodos)): if(strtolower($m['nombre']) === 'biopago') $tiene_biopago = true; ?><label style="display:flex;justify-content:space-between;align-items:center;background:#fff;padding:12px 15px;border-radius:12px;cursor:pointer;border:1px solid #eee;"><span style="font-weight:600;font-size:.9rem;"><?php echo $m['icono'] . ' ' . htmlspecialchars($m['nombre']); ?></span><input type="checkbox" name="metodos_activos[]" value="<?php echo $m['id']; ?>" <?php echo $m['activo'] == 1 ? 'checked' : ''; ?> style="width:20px;height:20px;accent-color:var(--red-main);"></label><?php endwhile; ?><?php if(!$tiene_biopago): ?><label style="display:flex;justify-content:space-between;align-items:center;background:#fff;padding:12px 15px;border-radius:12px;cursor:pointer;border:1px solid #eee;"><span>🧬 BioPago</span><input type="checkbox" name="metodos_activos_extra[]" value="BioPago" checked style="width:20px;height:20px;"></label><?php endif; ?></div><div style="display:flex;gap:10px;"><button type="button" onclick="cerrarModalMetodos()" style="flex:1;padding:12px;border-radius:10px;border:none;cursor:pointer;font-weight:700;">CANCELAR</button><button type="submit" class="btn-red-hud" style="flex:1;">GUARDAR</button></div></form></div></div>

<div id="modalPermisos" class="modal-overlay"><div class="modal-box"><h3>Configurar Permisos</h3><p style="font-size:.8rem;font-weight:600;margin-bottom:20px;color:#555;">Usuario: <span id="perm-username-text" style="color:var(--red-main);"></span></p><form action="gestion_usuarios.php" method="POST"><input type="hidden" name="accion" value="actualizar_permisos"><input type="hidden" name="id_usuario" id="perm-id-usuario"><div style="background:#f8f9fa;border-radius:15px;padding:10px;margin-bottom:20px;border:1px solid #eee;max-height:250px;overflow-y:auto;"><?php $modulos=['ventas'=>'🛒 Ventas','inventario'=>'📦 Inventario','corte'=>'📊 Corte','estadisticas'=>'📊 Estadísticas','historial'=>'📋 Historial','facturas'=>'📄 Facturas','config'=>'⚙️ Ajustes','proveedores'=>'🚚 Proveedores','creditos'=>'💳 Créditos','departamentos'=>'📁 Departamentos','usuarios'=>'👥 Usuarios']; foreach($modulos as $val=>$lbl): ?><label style="display:flex;justify-content:space-between;padding:10px;border-bottom:1px solid #f1f1f1;cursor:pointer;"><span style="font-weight:600;font-size:.85rem;"><?php echo $lbl; ?></span><input type="checkbox" name="p[]" value="<?php echo $val; ?>" style="width:auto;accent-color:var(--red-main);"></label><?php endforeach; ?></div><div style="display:flex;gap:10px;"><button type="button" onclick="cerrarModalPermisos()" style="flex:1;padding:12px;border-radius:10px;border:none;cursor:pointer;">CANCELAR</button><button type="submit" class="btn-red-hud" style="flex:1;">GUARDAR</button></div></form></div></div>

<script>
    function updatePreview() { document.getElementById('pre_nombre').innerText=document.getElementById('in_nombre').value; document.getElementById('pre_rif').innerText='RIF: '+document.getElementById('in_rif').value; document.getElementById('pre_dir').innerText=document.getElementById('in_dir').value; document.getElementById('pre_msg').innerText=document.getElementById('in_msg').value; }
    function generarBackup() { Swal.fire({title:'¿Generar respaldo?',text:'Se descargará un archivo SQL con toda la información.',icon:'question',showCancelButton:true,confirmButtonColor:'#d81b60',confirmButtonText:'Sí, descargar'}).then((result)=>{if(result.isConfirmed) window.location.href='respaldo_db.php';}); }
    function abrirModalMetodos(){document.getElementById('modalMetodos').style.display='flex';} function cerrarModalMetodos(){document.getElementById('modalMetodos').style.display='none';}
    function abrirModalPermisos(user){document.getElementById('perm-id-usuario').value=user.id;document.getElementById('perm-username-text').innerText=user.username;const checks=document.querySelectorAll('#modalPermisos input[type="checkbox"]');checks.forEach(c=>c.checked=false);if(user.permisos){try{const p=JSON.parse(user.permisos);p.forEach(val=>{const el=document.querySelector(`#modalPermisos input[value="${val}"]`);if(el)el.checked=true;});}catch(e){}}document.getElementById('modalPermisos').style.display='flex';} function cerrarModalPermisos(){document.getElementById('modalPermisos').style.display='none';}
    window.onclick=function(e){if(e.target.className==='modal-overlay'){cerrarModalPermisos();cerrarModalMetodos();}};
</script>
</body>
</html>
