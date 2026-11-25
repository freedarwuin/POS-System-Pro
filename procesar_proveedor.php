<?php
// Incluyes tu conexión a la base de datos (ajusta el nombre de tu archivo)
include("conexion.php"); 

if (isset($_POST['guardar_proveedor'])) {
    // Recibir y limpiar datos para evitar inyecciones SQL básicas
    $nombre_empresa = mysqli_real_escape_string($conexion, $_POST['nombre_empresa']);
    $rif_cedula     = mysqli_real_escape_string($conexion, $_POST['rif_cedula']);
    $telefono       = mysqli_real_escape_string($conexion, $_POST['telefono']);
    $direccion      = mysqli_real_escape_string($conexion, $_POST['direccion']);
    
    // fecha_registro se puede omitir si en MySQL la configuraste como CURRENT_TIMESTAMP, 
    // de lo contrario, la pasamos en el query:
    $fecha_actual   = date("Y-m-d H:i:s");

    // Validar que no se repita el RIF
    $verificar_rif = mysqli_query($conexion, "SELECT * FROM proveedores WHERE rif_cedula = '$rif_cedula'");
    
    if (mysqli_num_rows($verificar_rif) > 0) {
        echo "<script>alert('Error: Este RIF/Cédula ya está registrado.'); window.location='proveedores.php';</script>";
    } else {
        // Insertar en la base de datos
        $query_insertar = "INSERT INTO proveedores (nombre_empresa, rif_cedula, telefono, direccion, fecha_registro) 
                           VALUES ('$nombre_empresa', '$rif_cedula', '$telefono', '$direccion', '$fecha_actual')";
        
        $resultado = mysqli_query($conexion, $query_insertar);

        if ($resultado) {
            echo "<script>alert('Proveedor guardado exitosamente.'); window.location='proveedores.php';</script>";
        } else {
            echo "<script>alert('Error al guardar el proveedor.'); window.location='proveedores.php';</script>";
        }
    }
}
?>