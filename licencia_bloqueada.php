<?php
// licencia_bloqueada.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Mensaje por defecto profesional si no se recibe uno personalizado por URL
$mensaje_default = "Estimado usuario, la licencia de uso de este sistema se encuentra inactiva debido a un pago pendiente o al vencimiento del periodo contratado. Para restablecer el servicio o coordinar la regularización de su cuenta, comuníquese con el desarrollador del software.";

$mensaje_mostrar = !empty($_GET['msj']) ? htmlspecialchars($_GET['msj']) : $mensaje_default;
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso Restringido | POS System Pro</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        :root {
            --accent-red: #e11d48;
            --accent-red-hover: #be123c;
            --bg-dark: #0f172a;
            --card-bg: rgba(30, 41, 59, 0.85);
            --text-muted: #94a3b8;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body {
            background: var(--bg-dark) url('Imagenes/8.jpeg') no-repeat center center;
            background-size: cover;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            padding: 20px;
        }

        .lock-card {
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 45px 35px;
            border-radius: 28px;
            text-align: center;
            max-width: 480px;
            width: 100%;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.7);
        }

        .icon-wrapper {
            width: 80px;
            height: 80px;
            background: rgba(225, 29, 72, 0.15);
            border: 1px solid rgba(225, 29, 72, 0.3);
            border-radius: 50%;
            display: flex;
            justify-content: center;
            align-items: center;
            margin: 0 auto 20px auto;
            color: var(--accent-red);
            font-size: 2rem;
        }

        h1 {
            color: #ffffff;
            font-size: 1.6rem;
            font-weight: 800;
            margin-bottom: 12px;
        }

        .message-box {
            background: rgba(15, 23, 42, 0.6);
            border-left: 4px solid var(--accent-red);
            padding: 15px 18px;
            border-radius: 12px;
            color: #cbd5e1;
            font-size: 0.9rem;
            line-height: 1.5;
            text-align: justify;
            margin-bottom: 25px;
        }

        .info-server {
            background: #0f172a;
            padding: 10px;
            border-radius: 10px;
            font-family: monospace;
            color: #818cf8;
            font-size: 0.85rem;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.05);
        }

        input {
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(15, 23, 42, 0.8);
            color: white;
            margin-bottom: 15px;
            box-sizing: border-box;
            text-align: center;
            font-weight: bold;
            font-size: 0.95rem;
            outline: none;
            transition: border-color 0.3s;
        }

        input:focus {
            border-color: var(--accent-red);
        }

        .btn-act {
            background: var(--accent-red);
            color: white;
            border: none;
            padding: 14px;
            width: 100%;
            border-radius: 12px;
            font-weight: 700;
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            transition: all 0.3s;
        }

        .btn-act:hover {
            background: var(--accent-red-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(225, 29, 72, 0.3);
        }

        .btn-act:disabled {
            background: #334155;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .contact-dev {
            margin-top: 20px;
            font-size: 0.8rem;
            color: var(--text-muted);
        }
    </style>
</head>
<body>

    <div class="lock-card">
        <div class="icon-wrapper">
            <i class="fa-solid fa-lock"></i>
        </div>
        
        <h1>Acceso Restringido</h1>
        
        <div class="message-box">
            <?php echo $mensaje_mostrar; ?>
        </div>

        <div style="text-align: left; font-size: 0.75rem; color: var(--text-muted); margin-bottom: 5px; font-weight: 600;">
            IDENTIFICADOR DEL SERVIDOR:
        </div>
        <div class="info-server"><?php echo $_SERVER['SERVER_NAME']; ?></div>

        <form id="formLicencia">
            <input type="text" name="key_cliente" id="key_cliente" placeholder="Pegue la clave de reactivación aquí" required>
            <button type="submit" class="btn-act" id="btnSubmit">Reactivar Sistema</button>
        </form>

        <div class="contact-dev">
            POS System Pro © <?php echo date('Y'); ?> — Todos los derechos reservados
        </div>
    </div>

    <script>
        document.getElementById('formLicencia').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const btn = document.getElementById('btnSubmit');
            const key = document.getElementById('key_cliente').value;
            btn.disabled = true;
            btn.innerText = 'Verificando...';

            fetch('validar_licencia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'activar_licencia=1&key_cliente=' + encodeURIComponent(key)
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Sistema Activado!',
                        text: 'La licencia se ha renovado con éxito.',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#e11d48'
                    }).then(() => {
                        window.location.href = 'index.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Licencia Inválida',
                        text: data.message || 'La clave proporcionada no es válida.',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#e11d48'
                    });
                    btn.disabled = false;
                    btn.innerText = 'Reactivar Sistema';
                }
            })
            .catch(() => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de Red',
                    text: 'No se pudo conectar con el servidor para validar la clave.',
                    background: '#1e293b',
                    color: '#fff',
                    confirmButtonColor: '#e11d48'
                });
                btn.disabled = false;
                btn.innerText = 'Reactivar Sistema';
            });
        });
    </script>
</body>
</html>