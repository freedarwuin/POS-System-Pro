<?php
session_start();

// Si ya existe una sesión, va directo a inventario
if (isset($_SESSION['usuario'])) {
    header("Location: inventario.php");
    exit();
} else {
    // Si no, al login
    header("Location: login.php");
    exit();
}
?>