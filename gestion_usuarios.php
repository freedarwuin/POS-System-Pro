<?php
session_start();
include 'conexion.php';

// Seguridad: Solo el Admin puede procesar estas acciones
if (!isset($_SESSION['usuario']) || $_SESSION['rol'] !== 'Admin') {
    header("Location: ventas.php");
    exit();
}

// 1. ACCIÓN: CREAR USUARIO
if (isset($_POST['accion']) && $_POST['accion'] == 'crear') {
    $user = mysqli_real_escape_string($conexion, $_POST['nuevo_username']);
    // Guardamos texto directo según tu requerimiento
    $pass = mysqli_real_escape_string($conexion, $_POST['nuevo_password']); 
    $rol = $_POST['nuevo_rol'];

    // Insertamos incluyendo la columna permisos como un array vacío []
    $query = "INSERT INTO usuarios (username, password, rol, permisos) VALUES ('$user', '$pass', '$rol', '[]')";
    
    if (mysqli_query($conexion, $query)) {
        header("Location: configuracion.php?msj=Usuario creado correctamente");
    } else {
        header("Location: configuracion.php?msj=Error al crear usuario");
    }
    exit();
}

// 2. ACCIÓN: ACTUALIZAR PERMISOS (Lo que faltaba)
if (isset($_POST['accion']) && $_POST['accion'] == 'actualizar_permisos') {
    $id = mysqli_real_escape_string($conexion, $_POST['id_usuario']);
    
    // Capturamos los checks marcados, si no hay ninguno, guardamos array vacío
    $permisos_asignados = isset($_POST['p']) ? $_POST['p'] : [];
    $permisos_json = json_encode($permisos_asignados);

    $query_permisos = "UPDATE usuarios SET permisos = '$permisos_json' WHERE id = '$id'";
    
    if (mysqli_query($conexion, $query_permisos)) {
        header("Location: configuracion.php?msj=Permisos actualizados con éxito");
    } else {
        header("Location: configuracion.php?msj=Error al actualizar permisos");
    }
    exit();
}

// 3. ACCIÓN: RESETEAR CLAVE
if (isset($_POST['accion']) && $_POST['accion'] == 'reset_clave') {
    $id = mysqli_real_escape_string($conexion, $_POST['id_usuario']);
    $nueva_clave = mysqli_real_escape_string($conexion, $_POST['nueva_clave']);
    
    $query_update = "UPDATE usuarios SET password = '$nueva_clave' WHERE id = '$id'";
    
    if (mysqli_query($conexion, $query_update)) {
        header("Location: configuracion.php?msj=Contraseña actualizada");
    } else {
        header("Location: configuracion.php?msj=Error al actualizar contraseña");
    }
    exit();
}

// 4. ACCIÓN: ELIMINAR USUARIO
if (isset($_GET['eliminar'])) {
    $id = mysqli_real_escape_string($conexion, $_GET['eliminar']);
    
    // Verificamos que no sea el admin principal (opcional pero recomendado)
    $res = mysqli_query($conexion, "SELECT username FROM usuarios WHERE id = '$id'");
    $row = mysqli_fetch_assoc($res);

    if ($row['username'] !== 'admin') {
        $query_del = "DELETE FROM usuarios WHERE id = '$id'";
        mysqli_query($conexion, $query_del);
        header("Location: configuracion.php?msj=Usuario eliminado");
    } else {
        header("Location: configuracion.php?msj=No puedes eliminar al administrador raíz");
    }
    exit();
}
?>