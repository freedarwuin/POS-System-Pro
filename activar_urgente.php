<?php
include 'conexion.php';

// Actualizamos la licencia a Activa, con vencimiento lejano y le ponemos el ID actual
$query = "UPDATE licencia SET 
            estado = 'Activo', 
            fecha_vencimiento = '2030-12-31', 
            codigo_licencia = 'LICENCIA-LOCAL-DEV' 
          WHERE id = 1";

if (mysqli_query($conexion, $query)) {
    echo "<h1>✅ Licencia activada a la fuerza</h1>";
    echo "<p>La base de datos ha sido actualizada. Ya puedes entrar al sistema.</p>";
    echo '<a href="configuracion.php">Ir a Configuración</a>';
} else {
    echo "Error actualizando: " . mysqli_error($conexion);
}
?>