<?php
session_start();

if (empty($_SESSION['usuario'])) {
    header('Location: login.php');
    exit();
}
if (($_SESSION['rol'] ?? '') !== 'Admin') {
    header('Location: ventas.php');
    exit();
}

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/plan_respaldos_helper.php';

try {
    $plan = posObtenerPlanRespaldos($conexion);
} catch (Throwable $e) {
    error_log('[plan_respaldos] ' . $e->getMessage());
    http_response_code(500);
    exit('No se pudo cargar el plan de respaldos. Revisa el registro de errores del servidor.');
}

if (empty($_SESSION['csrf_plan_respaldo'])) {
    $_SESSION['csrf_plan_respaldo'] = bin2hex(random_bytes(32));
}
$ahora = new DateTimeImmutable();
$vencido = posPlanRespaldoVencido($plan, $ahora);
$frecuencias = ['diario' => 'Diario', 'semanal' => 'Semanal', 'mensual' => 'Mensual'];
$frecuenciaActual = $frecuencias[$plan['frecuencia']] ?? 'Semanal';
$ultimoRespaldo = !empty($plan['ultimo_respaldo'])
    ? date('d/m/Y H:i', strtotime($plan['ultimo_respaldo']))
    : 'Nunca se ha descargado un respaldo';
$proximoRespaldo = null;
if (!empty($plan['ultimo_respaldo'])) {
    $sumas = ['diario' => '+1 day', 'semanal' => '+1 week', 'mensual' => '+1 month'];
    $proximoRespaldo = (new DateTimeImmutable($plan['ultimo_respaldo']))->modify($sumas[$plan['frecuencia']] ?? '+1 week');
}
$mensaje = (string) ($_SESSION['mensaje_plan_respaldo'] ?? '');
unset($_SESSION['mensaje_plan_respaldo']);
function planRespaldosEscapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Plan de respaldos | POS System Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --ink:#1e293b; --muted:#64748b; --blue:#2563eb; --red:#b91c1c; --green:#047857; }
        * { box-sizing:border-box; }
        body { margin:0; min-height:100vh; padding:32px; background:#f8fafc; color:var(--ink); font-family:Inter,system-ui,sans-serif; }
        main { max-width:900px; margin:0 auto; }
        h1 { margin:0 0 8px; font-size:clamp(1.7rem,4vw,2.4rem); }
        .intro { color:var(--muted); margin:0 0 24px; }
        .card { padding:clamp(20px,4vw,32px); margin:20px 0; border:1px solid #e2e8f0; border-radius:20px; background:#fff; box-shadow:0 10px 25px #0f172a0a; }
        .status { padding:16px 18px; border-radius:14px; line-height:1.55; }
        .status.overdue { color:#7f1d1d; background:#fef2f2; border:1px solid #fecaca; }
        .status.ok { color:#065f46; background:#ecfdf5; border:1px solid #a7f3d0; }
        .status.paused { color:#334155; background:#f1f5f9; border:1px solid #cbd5e1; }
        .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:18px; }
        label { display:block; margin-bottom:8px; font-weight:700; font-size:.9rem; }
        select { width:100%; min-height:46px; padding:10px 12px; border:1px solid #cbd5e1; border-radius:10px; background:#fff; font:inherit; }
        .check { display:flex; align-items:center; gap:10px; margin:18px 0; color:#334155; }
        .check input { width:18px; height:18px; }
        .actions { display:flex; flex-wrap:wrap; gap:12px; margin-top:20px; }
        .button { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:11px 18px; border:0; border-radius:10px; background:var(--blue); color:white; text-decoration:none; font:700 .95rem Inter, sans-serif; cursor:pointer; }
        .button.secondary { color:var(--ink); background:#e2e8f0; }
        .facts { display:grid; gap:10px; color:#334155; }
        .facts strong { color:var(--ink); }
        .note { color:var(--muted); font-size:.88rem; line-height:1.6; }
        @media(max-width:600px) { body { padding:76px 16px 24px; } .actions .button { width:100%; } }
    </style>
</head>
<body>
<?php include_once __DIR__ . '/sidebar.php'; ?>
<main>
    <h1>Plan de respaldos</h1>
    <p class="intro">Elige cada cuánto quieres que el sistema te recuerde descargar una copia SQL. El respaldo se descarga manualmente; este plan no ejecuta copias automáticas.</p>

    <?php if ($mensaje !== ''): ?>
        <div class="status ok" role="status"><?= planRespaldosEscapar($mensaje) ?></div>
    <?php endif; ?>

    <?php if (empty($plan['activo'])): ?>
        <div class="status paused"><strong>Recordatorios desactivados.</strong> Puedes volver a activarlos cuando quieras desde el formulario.</div>
    <?php elseif ($vencido): ?>
        <div class="status overdue" role="alert"><strong>Respaldo pendiente.</strong> El plan está configurado como <?= planRespaldosEscapar(strtolower($frecuenciaActual)) ?> y ya corresponde realizar una copia de seguridad.</div>
    <?php else: ?>
        <div class="status ok"><strong>Plan al día.</strong> El próximo recordatorio está previsto para <?= planRespaldosEscapar($proximoRespaldo->format('d/m/Y H:i')) ?>.</div>
    <?php endif; ?>

    <section class="card" aria-labelledby="plan-title">
        <h2 id="plan-title">Configuración del recordatorio</h2>
        <form action="guardar_plan_respaldo.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= planRespaldosEscapar($_SESSION['csrf_plan_respaldo']) ?>">
            <div class="grid">
                <div>
                    <label for="frecuencia">Frecuencia</label>
                    <select id="frecuencia" name="frecuencia" required>
                        <?php foreach ($frecuencias as $valor => $etiqueta): ?>
                            <option value="<?= planRespaldosEscapar($valor) ?>" <?= $plan['frecuencia'] === $valor ? 'selected' : '' ?>><?= planRespaldosEscapar($etiqueta) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <label class="check"><input type="checkbox" name="activo" value="1" <?= !empty($plan['activo']) ? 'checked' : '' ?>> Activar recordatorios de respaldos</label>
            <button class="button" type="submit">Guardar plan</button>
        </form>
    </section>

    <section class="card" aria-labelledby="estado-title">
        <h2 id="estado-title">Estado del respaldo</h2>
        <div class="facts">
            <div><strong>Último respaldo registrado:</strong> <?= planRespaldosEscapar($ultimoRespaldo) ?></div>
            <div><strong>Frecuencia actual:</strong> <?= planRespaldosEscapar($frecuenciaActual) ?></div>
        </div>
        <p class="note">La fecha se actualiza cuando termina la generación del archivo SQL. Guarda el archivo descargado en un lugar seguro, preferiblemente fuera del equipo donde funciona el POS.</p>
        <div class="actions">
            <a class="button" href="respaldo_db.php">Descargar respaldo SQL ahora</a>
            <a class="button secondary" href="configuracion.php">Volver a configuración</a>
        </div>
    </section>
</main>
</body>
</html>
