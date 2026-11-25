<?php
session_start();

// Validar que esté logueado
if (!isset($_SESSION['usuario'])) {
    header("Location: login.php");
    exit();
}

include 'conexion.php';
$usuario_actual = $_SESSION['usuario'];

// 1. Obtener el ID del usuario actual desde la base de datos
$query_user_id = mysqli_query($conexion, "SELECT id FROM usuarios WHERE username = '$usuario_actual' LIMIT 1");
$row_u = mysqli_fetch_assoc($query_user_id);
$id_usuario = $row_u['id'] ?? 0;

// 2. Verificar usando la estructura real de tu tabla 'aperturas_caja'
$query_turno = mysqli_query($conexion, "SELECT * FROM aperturas_caja WHERE id_usuario = '$id_usuario' AND estatus = 'Abierta' LIMIT 1");
$turno_abierto = mysqli_num_rows($query_turno) > 0;
$datos_turno = mysqli_fetch_assoc($query_turno);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Turno | POS System Pro</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        :root {
            --accent-red: #e11d48; 
            --accent-red-hover: #be123c;
            --bg-main: #0f172a;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg-main) url('Imagenes/6.png') no-repeat center center;
            background-size: cover;
            color: white;
            padding: 20px;
        }
        .dashboard-container {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            width: 100%;
            max-width: 1000px;
            gap: 30px;
        }
        .glass-card {
            background: rgba(255, 255, 255, 0.03);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 40px;
            box-shadow: 0 25px 50px rgba(0,0,0,0.3);
        }
        h2, h3 { font-weight: 800; margin-bottom: 20px; text-transform: uppercase; letter-spacing: 1px; }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 50px;
            font-weight: 700;
            font-size: 0.8rem;
            text-transform: uppercase;
            margin-bottom: 25px;
        }
        .form-group { margin-bottom: 20px; text-align: left; }
        label { font-size: 0.75rem; text-transform: uppercase; color: #a1a1aa; margin-bottom: 8px; display: block; font-weight: 700; }
        .input-field {
            width: 100%; padding: 14px; background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1); border-radius: 12px;
            color: white; font-size: 1rem; outline: none; transition: 0.3s;
        }
        .input-field:focus { border-color: var(--accent-red); background: rgba(255, 255, 255, 0.1); }
        .btn-submit {
            width: 100%; padding: 16px; background: var(--accent-red); color: white;
            border: none; border-radius: 12px; font-weight: 800; text-transform: uppercase;
            cursor: pointer; transition: 0.3s;
        }
        .btn-submit:hover { background: var(--accent-red-hover); transform: scale(1.01); }
        .btn-green { background: #10b981; }
        .btn-green:hover { background: #059669; }
        .btn-secondary { background: #334155; }
        .btn-secondary:hover { background: #1e293b; }
    </style>
</head>
<body>

<div class="dashboard-container">
    
    <div class="glass-card">
        <h2><i class="fa-solid fa-cash-register" style="color: var(--accent-red);"></i> Control de Turno</h2>
        
        <?php if (!$turno_abierto): ?>
            <div class="status-badge" style="background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4);">
                <i class="fa-solid fa-lock"></i> Caja Cerrada
            </div>
            <p style="color: #a1a1aa; font-size: 0.9rem; margin-bottom: 25px;">Para desbloquear el sistema de ventas, es obligatorio declarar los fondos físicos disponibles en caja.</p>
            
            <!-- FORMULARIO CORREGIDO PARA APERTURA DE CAJA -->
            <form action="procesar_turno.php" method="POST">
                <input type="hidden" name="accion" value="abrir_turno">
                <div class="form-group">
                    <label>Monto Inicial USD ($)</label>
                    <input type="number" step="0.01" name="monto_usd" class="input-field" placeholder="0.00" required>
                </div>
                <div class="form-group">
                    <label>Monto Inicial Bolívares (Bs.)</label>
                    <input type="number" step="0.01" name="monto_bs" class="input-field" placeholder="0.00" required>
                </div>
                <button type="submit" class="btn-submit btn-green">Abrir Turno Diario</button>
            </form>

        <?php else: ?>
            <div class="status-badge" style="background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4);">
                <i class="fa-solid fa-circle-check"></i> Turno Activo
            </div>
            
            <div style="background: rgba(255,255,255,0.02); padding: 20px; border-radius: 15px; border: 1px solid rgba(255,255,255,0.05); margin-bottom: 30px; font-size: 0.9rem;">
                <p style="margin-bottom: 10px;">Apertura: <b style="color: #fff;"><?php echo date('d/m/Y h:i A', strtotime($datos_turno['fecha_apertura'])); ?></b></p>
                <p style="margin-bottom: 10px;">Base USD ($): <b style="color: #34d399;">$<?php echo number_format($datos_turno['monto_usd'], 2); ?></b></p>
                <p>Base Bolívares: <b style="color: #60a5fa;">Bs. <?php echo number_format($datos_turno['monto_bs'], 2); ?></b></p>
            </div>

            <div style="display: flex; flex-direction: column; gap: 15px;">
                <button onclick="window.location.href='ventas.php'" class="btn-submit btn-green" style="font-size: 1.1rem; padding: 20px;">
                    <i class="fa-solid fa-cart-shopping"></i> Ir al Módulo de Ventas
                </button>
                
                <form action="procesar_turno.php" method="POST" id="formCierre">
                    <input type="hidden" name="accion" value="cerrar_turno">
                    <input type="hidden" name="id_turno" value="<?php echo $datos_turno['id']; ?>">
                    <button type="button" onclick="confirmarCierre()" class="btn-submit btn-secondary">
                        <i class="fa-solid fa-door-open"></i> Cerrar Turno e ir al Corte
                    </button>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <div class="glass-card" style="height: fit-content;">
        <h3><i class="fa-solid fa-user-gear" style="color: var(--accent-red);"></i> Mi Perfil</h3>
        <p style="color: #a1a1aa; font-size: 0.85rem; margin-bottom: 25px;">Puedes actualizar tus credenciales directamente sin contactar al administrador.</p>
        
        <form action="procesar_perfil.php" method="POST">
            <div class="form-group">
                <label>Nombre de Usuario</label>
                <input type="text" name="cambiar_usuario" class="input-field" value="<?php echo htmlspecialchars($usuario_actual); ?>" required>
            </div>
            <div class="form-group">
                <label>Nueva Contraseña</label>
                <input type="password" name="cambiar_clave" class="input-field" placeholder="Dejar en blanco para mantener actual">
            </div>
            <button type="submit" class="btn-submit" style="background: rgba(255,255,255,0.1); color: white;">Guardar Cambios</button>
        </form>
        
        <a href="logout.php" style="display: block; text-align: center; color: var(--accent-red); margin-top: 25px; font-weight: 700; text-decoration: none; font-size: 0.85rem; text-transform: uppercase;">
            <i class="fa-solid fa-right-from-bracket"></i> Desconectarse
        </a>
    </div>
</div>

<script>
function confirmarCierre() {
    Swal.fire({
        title: '¿Cerrar turno de caja?',
        text: "Se finalizarán las operaciones de venta y serás redirigido al corte final.",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#e11d48',
        cancelButtonColor: '#334155',
        confirmButtonText: 'Sí, cerrar caja',
        cancelButtonText: 'Cancelar'
    }).then((result) => {
        if (result.isConfirmed) {
            document.getElementById('formCierre').submit();
        }
    });
}

const urlParams = new URLSearchParams(window.location.search);
if(urlParams.get('msj')) {
    Swal.fire({ text: urlParams.get('msj'), icon: 'success', toast: true, position: 'top-end', showConfirmButton: false, timer: 3000 });
}
</script>
</body>
</html>