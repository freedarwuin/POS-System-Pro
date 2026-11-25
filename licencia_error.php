<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Activación Requerida | POS Pro</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body { background: #0f172a; color: white; font-family: 'Inter', sans-serif; display: flex; justify-content: center; align-items: center; height: 100vh; margin: 0; }
        .lock-card { background: #1e293b; padding: 40px; border-radius: 24px; text-align: center; max-width: 400px; border: 1px solid #334155; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5); }
        .icon { font-size: 50px; margin-bottom: 10px; }
        h1 { color: #f87171; font-size: 1.5rem; margin-bottom: 10px; }
        p { color: #94a3b8; font-size: 0.9rem; margin-bottom: 20px; }
        .info-server { background: #0f172a; padding: 12px; border-radius: 12px; font-family: monospace; color: #818cf8; margin-bottom: 20px; border: 1px solid #1e293b; }
        input { width: 100%; padding: 12px; border-radius: 10px; border: 1px solid #334155; background: #0f172a; color: white; margin-bottom: 10px; box-sizing: border-box; text-align: center; font-weight: bold; }
        .btn-act { background: #4f46e5; color: white; border: none; padding: 12px; width: 100%; border-radius: 10px; font-weight: 700; cursor: pointer; transition: 0.3s; }
        .btn-act:hover { background: #4338ca; }
        .btn-act:disabled { background: #334155; cursor: not-allowed; }
    </style>
</head>
<body>
    <div class="lock-card">
        <div class="icon">🔒</div>
        <h1>Acceso Restringido</h1>
        <p><?php echo htmlspecialchars($_GET['msj'] ?? 'Licencia inválida o expirada'); ?></p>
        
        <div style="text-align: left; font-size: 0.8rem; color: #64748b; margin-bottom: 5px;">ID DE SERVIDOR:</div>
        <div class="info-server"><?php echo $_SERVER['SERVER_NAME']; ?></div>

        <form id="formLicencia">
            <input type="text" name="key_cliente" id="key_cliente" placeholder="Pegue su Clave de Activación aquí" required>
            <button type="submit" class="btn-act" id="btnSubmit">Activar Sistema</button>
        </form>
        
        <a href="index.php" style="color: #64748b; text-decoration: none; font-size: 0.8rem; display: block; margin-top: 15px;">Volver al Inicio</a>
    </div>

    <script>
        document.getElementById('formLicencia').addEventListener('submit', function(e) {
            e.preventDefault(); // Evita que la página se recargue
            
            const btn = document.getElementById('btnSubmit');
            const key = document.getElementById('key_cliente').value;
            btn.disabled = true;
            btn.innerText = 'Validando...';

            // Enviamos los datos a validar_licencia.php
            fetch('validar_licencia.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'activar_licencia=1&key_cliente=' + encodeURIComponent(key)
            })
            .then(response => response.json()) // Esperamos una respuesta JSON
            .then(data => {
                if (data.status === 'success') {
                    Swal.fire({
                        icon: 'success',
                        title: '¡Activado!',
                        text: 'Licencia activada exitosamente.',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#4f46e5'
                    }).then(() => {
                        window.location.href = 'configuracion.php';
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error',
                        text: data.message || 'La clave es incorrecta.',
                        background: '#1e293b',
                        color: '#fff',
                        confirmButtonColor: '#f87171'
                    });
                    btn.disabled = false;
                    btn.innerText = 'Activar Sistema';
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error de conexión',
                    text: 'No se pudo contactar con el servidor de licencias.',
                    background: '#1e293b',
                    color: '#fff'
                });
                btn.disabled = false;
                btn.innerText = 'Activar Sistema';
            });
        });
    </script>
</body>
</html>