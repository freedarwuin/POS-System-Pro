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

// Sentencia preparada para proteger la consulta
$stmt = mysqli_prepare($conexion, "SELECT id, username, password, rol, nombre FROM usuarios WHERE username = ? LIMIT 1");

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "s", $usuario_enviado);
    mysqli_stmt_execute($stmt);
    $resultado = mysqli_stmt_get_result($stmt);
    $datos     = mysqli_fetch_assoc($resultado);
    mysqli_stmt_close($stmt);

    if ($datos) {
        $password_valida = password_verify($clave_enviada, $datos['password'])
            || $clave_enviada === $datos['password'];

        if ($password_valida) {
            session_regenerate_id(true);

            $_SESSION['id']      = $datos['id'];
            $_SESSION['user_id'] = $datos['id'];
            $_SESSION['usuario'] = $datos['username'];
            $_SESSION['rol']     = $datos['rol'];
            $_SESSION['nombre']  = $datos['nombre'];

            if (file_exists('validar_licencia.php')) {
                include_once 'validar_licencia.php';
            }

            // Comprueba una vez por inicio de sesión y luego redirige según rol.
            header("Location: comprobar_actualizacion.php");
            exit();
        }
    }
}

header("Location: login.php?error=1");
exit();
?>
