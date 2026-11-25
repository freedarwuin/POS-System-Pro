<?php
// 1. Iniciar sesión si no se ha iniciado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Conectar a la BD y validar licencia antes de renderizar la página
require_once 'conexion.php';

$queryLic = mysqli_query($conexion, "SELECT fecha_vencimiento, estado FROM licencia LIMIT 1");
$licencia = mysqli_fetch_assoc($queryLic);

$hoy = date('Y-m-d');
$bloqueado = false;
$mensajeError = '';

if (!$licencia) {
    $bloqueado = true;
    $mensajeError = 'No se encontró registro de licencia activo.';
} elseif (strtolower(trim($licencia['estado'])) !== 'activo') {
    $bloqueado = true;
    $mensajeError = 'La licencia del sistema se encuentra inactiva.';
} elseif ($licencia['fecha_vencimiento'] < $hoy) {
    $bloqueado = true;
    $mensajeError = 'La licencia del sistema ha expirado el ' . $licencia['fecha_vencimiento'];
}

// Si la licencia está vencida o inactiva, redirige al bloqueo inmediatamente
if ($bloqueado) {
    header("Location: licencia_bloqueada.php?msj=" . urlencode($mensajeError));
    exit();
}

// Leer la versión actual desde el archivo de configuración del sistema
$versionSistema = '1.0.0';
$datosVersion = json_decode(@file_get_contents(__DIR__ . '/version.json'), true);
if (is_array($datosVersion) && !empty($datosVersion['version'])) {
    $versionSistema = $datosVersion['version'];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SISTEMA DE FACTURACION | POS System Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
    :root {
        --accent-red: #e11d48; 
        --accent-red-hover: #be123c;
        --text-white: #ffffff;
        --text-muted: #a1a1aa;
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

    body {
        height: 100vh;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #000 url('Imagenes/8.jpeg') no-repeat center center;
        background-size: cover;
        color: white;
    }

    /* --- RELOJ --- */
    .pos-clock {
        position: absolute;
        top: 20px;
        right: 30px;
        background: rgba(0, 0, 0, 0.6);
        backdrop-filter: blur(10px);
        padding: 10px 20px;
        border-radius: 50px;
        border: 1px solid rgba(255, 255, 255, 0.1);
        font-size: 0.8rem;
        z-index: 100;
    }

    /* --- CONTENEDOR PRINCIPAL --- */
    .main-container {
        display: flex;
        width: 98%;
        max-width: 1700px;
        height: 80vh;
        align-items: center;
        justify-content: space-between;
        position: relative;
    }

    /* LADO IZQUIERDO */
    .welcome-side {
        flex: 1;
        padding-right: 20px;
        max-width: 550px;
        animation: fadeInLeft 1s ease;
    }

    .welcome-side h1 {
        font-size: 4rem;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 10px;
    }

    .welcome-side h2 {
        color: var(--accent-red);
        font-size: 1.5rem;
        letter-spacing: 2px;
        margin-bottom: 20px;
        text-transform: uppercase;
    }

    .welcome-side p {
        color: var(--text-muted);
        max-width: 500px;
        line-height: 1.6;
    }

    /* LADO DERECHO (CONTENEDOR DEL LOGIN) */
    .login-side {
        flex: 0 0 420px;
        position: relative;
        margin-left: auto; 
        margin-right: -10px;
    }

    /* TARJETA OSCURECIDA CON ALTO CONTRASTE */
    .login-card {
        background: rgba(10, 10, 15, 0.82);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 35px;
        padding: 50px 40px;
        text-align: center;
        box-shadow: 0 30px 60px rgba(0, 0, 0, 0.7);
        animation: zoomIn 0.8s ease;
    }

    /* --- BOT ASISTENTE --- */
    .bot-wrapper {
        position: absolute;
        bottom: -110px;
        right: 20px;
        z-index: 200;
        display: flex;
        flex-direction: column;
        align-items: center;
    }

    .bot-img {
        width: 130px;
        filter: drop-shadow(0 0 20px rgba(225, 29, 72, 0.4));
        animation: floatingBot 6s ease-in-out infinite;
    }

    .bot-bubble {
        background: white;
        color: #1a1a1a;
        padding: 12px 20px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
        width: 220px;
        box-shadow: 0 10px 30px rgba(0,0,0,0.4);
        position: relative;
        margin-bottom: -180px;
        opacity: 0;
        transform: translateY(10px);
        transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    }

    .bot-bubble.active { opacity: 1; transform: translateY(0); }

    .bot-bubble.error {
        background: var(--accent-red) !important;
        color: white !important;
    }

    .bot-bubble.error::after {
        border-top: 10px solid var(--accent-red) !important;
    }

    .bot-bubble::after {
        content: '';
        position: absolute;
        bottom: -80px;
        left: 50%;
        border-left: 10px solid transparent;
        border-right: 10px solid transparent;
        border-top: 10px solid white;
        transform: translateX(-50%);
        transition: all 0.4s ease;
    }

    @keyframes floatingBot {
        0%, 100% { transform: translateY(0) rotate(0deg); }
        50% { transform: translateY(-15px) rotate(2deg); }
    }

    /* --- INPUTS Y CAMPO DE CONTRASEÑA --- */
    .input-group { margin-bottom: 20px; text-align: left; }
    .input-group label {
        font-size: 0.7rem;
        text-transform: uppercase;
        letter-spacing: 2px;
        color: var(--text-muted);
        margin-bottom: 8px;
        display: block;
    }

    .password-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .input-field {
        width: 100%;
        padding: 16px;
        padding-right: 45px;
        background: rgba(255, 255, 255, 0.07);
        border: 1px solid rgba(255, 255, 255, 0.15);
        border-radius: 15px;
        color: white;
        font-size: 1rem;
        outline: none;
        transition: 0.3s;
    }

    .input-field:focus {
        border-color: var(--accent-red);
        background: rgba(255, 255, 255, 0.12);
        box-shadow: 0 0 20px rgba(225, 29, 72, 0.3);
    }

    .toggle-password {
        position: absolute;
        right: 15px;
        color: var(--text-muted);
        cursor: pointer;
        transition: color 0.3s;
        z-index: 10;
    }

    .toggle-password:hover { color: white; }

    .btn-submit {
        width: 100%;
        padding: 18px;
        background: var(--accent-red);
        color: white;
        border: none;
        border-radius: 15px;
        font-weight: 800;
        text-transform: uppercase;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
    }

    .btn-submit:hover {
        background: var(--accent-red-hover);
        transform: scale(1.02);
        box-shadow: 0 10px 30px rgba(225, 29, 72, 0.4);
    }

    .logo-text { font-size: 1.8rem; font-weight: 800; margin-bottom: 30px; }
    .logo-text span { color: var(--accent-red); }

    .version-info {
        margin-top: 8px;
        font-size: 0.68rem;
        color: var(--text-muted);
        letter-spacing: 1px;
    }

    /* --- RESPONSIVE --- */
    @media (max-width: 1024px) {
        .bot-wrapper { display: none; }
        .welcome-side { display: none; }
        .main-container { justify-content: center; }
        .login-side { margin-left: 0; }
    }
    </style>
</head>
<body>

    <div class="pos-clock" id="posClock">Cargando hora...</div>

    <div class="main-container">
        
        <div class="welcome-side">
            <h1>BIENVENIDO</h1>
            <h2>A tu Punto de Venta</h2>
            <p>Gestiona tus ventas, inventario y reportes con una plataforma diseñada para el crecimiento de tu negocio. Ingresa tus credenciales para acceder.</p>
        </div>

        <div class="login-side">
            
            <div class="bot-wrapper">
                <div class="bot-bubble" id="botBubble">¡Hola! Soy tu asistente. Ingrese su usuario.</div>
                <img src="Imagenes/32.gif" class="bot-img" alt="AI Assistant">
            </div>

            <div class="login-card">
                <div class="logo-text">POS <span>SystemPro</span></div>
                
                <form action="validar.php" method="POST" id="loginForm" autocomplete="off">
                    
                    <div class="input-group">
                        <label for="user">Usuario</label>
                        <input type="text" id="user" name="user" class="input-field" placeholder="Ej. cajero01" required autofocus>
                    </div>

                    <div class="input-group">
                        <label for="pass">Contraseña</label>
                        <div class="password-wrapper">
                            <input type="password" id="pass" name="pass" class="input-field" placeholder="••••••••" required>
                            <i class="fa-solid fa-eye toggle-password" id="togglePass"></i>
                        </div>
                    </div>

                    <button type="submit" class="btn-submit">Iniciar Sesión</button>
                </form>

                <div style="margin-top: 30px; font-size: 0.7rem; color: var(--text-muted);">
                    SISTEMA PROTEGIDO POR POS PRO © 2026
                </div>
                <div class="version-info">
                    Versión actualizada: <?php echo htmlspecialchars($versionSistema, ENT_QUOTES, 'UTF-8'); ?>
                </div>
            </div>
        </div>
    </div>

    <script>
    // 1. Reloj optimizado
    function updateClock() {
        const now = new Date();
        document.getElementById('posClock').innerText = now.toLocaleString('es-VE', { 
            weekday: 'long',
            day: 'numeric', 
            month: 'long', 
            year: 'numeric', 
            hour: '2-digit', 
            minute: '2-digit', 
            second: '2-digit',
            hour12: true
        });
    }
    updateClock();
    setInterval(updateClock, 1000);

    // 2. Elementos del Bot Asistente
    const bubble = document.getElementById('botBubble');
    const userField = document.getElementById('user');
    const passField = document.getElementById('pass');
    
    let hasError = false;

    // Detectar error desde la URL
    if (window.location.search.includes('error=1')) {
        hasError = true;
        bubble.innerText = "¡Usuario o contraseña incorrectos!";
        bubble.classList.add('active', 'error');
        document.querySelector('.login-card').classList.add('animate__animated', 'animate__shakeX');
        window.history.replaceState({}, document.title, window.location.pathname);
    } else {
        setTimeout(() => bubble.classList.add('active'), 800);
    }

    // 3. Eventos Focus
    userField.addEventListener('focus', () => {
        if (hasError) resetBubbleState();
        bubble.innerText = "Escriba su usuario registrado.";
    });

    passField.addEventListener('focus', () => {
        if (hasError) resetBubbleState();
        bubble.innerText = "Asegúrese de que nadie mire su clave.";
    });

    function resetBubbleState() {
        hasError = false;
        bubble.classList.remove('error');
    }

    // 4. Mostrar / Ocultar contraseña
    const togglePass = document.getElementById('togglePass');
    togglePass.addEventListener('click', () => {
        const type = passField.getAttribute('type') === 'password' ? 'text' : 'password';
        passField.setAttribute('type', type);
        togglePass.classList.toggle('fa-eye');
        togglePass.classList.toggle('fa-eye-slash');
    });
    </script>
</body>
</html>
