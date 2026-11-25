<?php
// Asegurar inicio de sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

include 'conexion.php';

// Validar recepción de datos por POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_POST['user']) || empty($_POST['pass'])) {
    header("Location: login.php?error=1");
    exit();
}

$usuario_enviado = trim($_POST['user']);
$clave_enviada   = $_POST['pass'];

// 1. Sentencia Preparada contra SQL Injection
$stmt = mysqli_prepare($conexion, "SELECT id, username, password, rol, nombre FROM usuarios WHERE username = ? LIMIT 1");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $usuario_enviado);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $datos     = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if ($datos) {
        // 2. Verificación Segura de Contraseña
        // Compatible tanto con hash seguro (password_verify) como fallback temporal si usas texto plano
        $password_valida = false;

        if (password_verify($clave_enviada, $datos['password'])) {
            $password_valida = true;
        } elseif ($clave_enviada === $datos['password']) {
            // Soporte temporal si aún no has migrado la BD a hash
            $password_valida = true;
        }

        if ($password_valida) {
            // 3. Prevención de Session Fixation
            session_regenerate_id(true);

            // 4. Definición de variables de sesión
            $_SESSION['id']      = $datos['id'];
            $_SESSION['user_id'] = $datos['id']; 
            $_SESSION['usuario'] = $datos['username'];
            $_SESSION['rol']     = $datos['rol']; 
            $_SESSION['nombre']  = $datos['nombre'];

            // 5. Validación de licencia (sin ocultar errores silenciosamente)
            if (file_exists('validar_licencia.php')) {
                include_once 'validar_licencia.php';
            }

            // 6. Redirección basada en Rol
            $rol = strtolower(trim($datos['rol']));

            if ($rol === 'admin') {
                header("Location: configuracion.php");
            } elseif ($rol === 'cajero') {
                header("Location: cajero_dashboard.php");
            } else {
                header("Location: ventas.php");
            }
            exit();

        } else {
            // Clave incorrecta
            header("Location: login.php?error=1");
            exit();
        }
    } else {
        // Usuario no encontrado
        header("Location: login.php?error=1");
        exit();
    }
} else {
    // Error en la consulta preparada
    header("Location: login.php?error=1");
    exit();
}
?>