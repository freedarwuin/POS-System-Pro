<?php
include 'conexion.php';

echo "<h2>🕵️ Diagnóstico de Conexión y Datos</h2>";

// 1. Verificar si existe la tabla ventas
$check_tabla = mysqli_query($conexion, "SHOW TABLES LIKE 'ventas'");
if(mysqli_num_rows($check_tabla) == 0) {
    die("<p style='color:red;'>❌ ERROR: La tabla 'ventas' no existe en tu base de datos.</p>");
} else {
    echo "<p style='color:green;'>✅ La tabla 'ventas' existe.</p>";
}

// 2. Ver las columnas de la tabla ventas (para saber si usas id_cliente o cliente)
echo "<h3>Columnas en la tabla 'ventas':</h3><ul>";
$columnas = mysqli_query($conexion, "DESCRIBE ventas");
while($col = mysqli_fetch_assoc($columnas)) {
    echo "<li>" . $col['Field'] . "</li>";
}
echo "</ul>";

// 3. Ver qué nombres de clientes hay registrados en ventas a crédito
echo "<h3>Muestra de últimas 5 ventas marcadas como Crédito:</h3>";
$test_query = mysqli_query($conexion, "SELECT * FROM ventas WHERE metodo_pago LIKE '%Credito%' LIMIT 5");

if(mysqli_num_rows($test_query) > 0) {
    echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>
            <tr>
                <th>ID Venta</th>
                <th>Columna 'cliente'</th>
                <th>Columna 'id_cliente'</th>
                <th>Metodo Pago</th>
                <th>Total</th>
            </tr>";
    while($v = mysqli_fetch_assoc($test_query)) {
        echo "<tr>
                <td>{$v['id']}</td>
                <td>".($v['cliente'] ?? 'N/A')."</td>
                <td>".($v['id_cliente'] ?? 'N/A')."</td>
                <td>{$v['metodo_pago']}</td>
                <td>{$v['total']}</td>
              </tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:orange;'>⚠️ No encontré NINGUNA venta que diga 'Credito' en el metodo_pago.</p>";
    
    // 4. Ver qué métodos de pago existen realmente
    echo "<h3>Métodos de pago detectados en el sistema:</h3><ul>";
    $metodos = mysqli_query($conexion, "SELECT DISTINCT metodo_pago FROM ventas");
    while($m = mysqli_fetch_assoc($metodos)) {
        echo "<li>[" . $m['metodo_pago'] . "]</li>";
    }
    echo "</ul>";
}
?>