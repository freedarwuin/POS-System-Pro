<?php
include 'conexion.php';
$id = $_GET['id'];
$res = mysqli_query($conexion, "SELECT * FROM productos WHERE id = $id");
$p = mysqli_fetch_assoc($res);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Editar Producto</title>
</head>
<body>
    <h2>Editar Producto</h2>
    <form action="actualizar_producto.php" method="POST">
        <input type="hidden" name="id" value="<?php echo $p['id']; ?>">
        <input type="text" name="nombre" value="<?php echo $p['nombre']; ?>" required><br><br>
        <input type="number" step="0.01" name="precio" value="<?php echo $p['precio']; ?>" required><br><br>
        <input type="number" name="stock" value="<?php echo $p['stock']; ?>" required><br><br>
        <button type="submit">Actualizar Cambios</button>
        <a href="index.php">Cancelar</a>
    </form>
</body>
</html>