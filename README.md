# POS-System-Pro

**POS-System-Pro** es una plataforma web de punto de venta desarrollada en PHP y MySQL. Permite administrar ventas, inventario, facturación, proveedores, créditos, caja, usuarios y reportes desde una interfaz adaptable para escritorio y dispositivos móviles.

[![Versión](https://img.shields.io/badge/version-1.1.2-blue)](https://github.com/freedarwuin/POS-System-Pro/releases/tag/v1.1.2)
[![PHP](https://img.shields.io/badge/PHP-7.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)

## Características

- Inicio de sesión con usuarios, roles y permisos por módulo.
- Punto de venta para escritorio y dispositivos móviles.
- Ventas con efectivo, dólares, Pago Móvil, Punto de Venta, BioPago, créditos y pagos múltiples.
- Múltiples facturas abiertas simultáneamente mediante pestañas en el navegador.
- Productos por unidad, paquete o granel, con soporte para balanza y venta a peso.
- Control de inventario, departamentos, proveedores y compras.
- Configuración de IVA e IGTF por producto y parámetros fiscales globales.
- Facturación, impresión de tickets y comprobantes.
- Clientes, ventas a crédito y registro de abonos.
- Apertura y cierre de caja por turnos.
- Reportes de ventas, inventario, movimientos y stock bajo.
- Métodos de pago configurables y tasa de cambio configurable.
- Respaldos SQL completos y limpieza segura de datos operativos.
- Validación de licencia y registro de auditoría.
- Actualizaciones desde GitHub Releases con instalador integrado.
- Navegación lateral responsive compartida entre los módulos.

## Tecnologías

- PHP 7.3 o posterior
- MySQL 5.7+, MySQL 8.0 o MariaDB compatible
- HTML5, CSS3 y JavaScript
- Extensión PHP `mysqli`
- Extensión PHP `ZipArchive`
- SweetAlert2 para notificaciones e interfaz de confirmación
- Font Awesome y Google Fonts para recursos visuales

## Requisitos

- Servidor web Apache o compatible.
- PHP 7.3 o posterior con `mysqli` y `ZipArchive` habilitados.
- MySQL 5.7+ o MariaDB compatible.
- Permisos de escritura para el actualizador del sistema.
- Conexión HTTPS válida hacia GitHub para comprobar releases.
- Navegador web actualizado.

## Instalación

### 1. Obtener el proyecto

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
```

También puedes descargar el archivo de la [última versión estable](https://github.com/freedarwuin/POS-System-Pro/releases/latest) y colocar el proyecto en la carpeta pública del servidor:

- XAMPP: `htdocs/POS-System-Pro`
- WAMP: `www/POS-System-Pro`
- Apache/Linux: `/var/www/html/POS-System-Pro`

### 2. Crear la base de datos

1. Accede a phpMyAdmin o utiliza tu cliente MySQL.
2. Crea una base de datos llamada `puntodeventa`.
3. Importa `puntodeventa.sql`.

Desde una terminal:

```bash
mysql -u root -p -e "CREATE DATABASE puntodeventa CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -p puntodeventa < puntodeventa.sql
```

### 3. Configurar la conexión

Edita [`conexion.php`](conexion.php) con las credenciales de tu instalación:

```php
<?php
$host = "localhost";
$user = "tu_usuario";
$pass = "tu_contraseña";
$db   = "puntodeventa";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
```

No publiques credenciales reales ni datos sensibles en un repositorio público.

### 4. Iniciar el sistema

Inicia Apache y MySQL y abre:

```text
http://localhost/POS-System-Pro/
```

El archivo [`index.php`](index.php) inicia la sesión o redirige a [`login.php`](login.php).

Para desarrollo local sin Apache puedes ejecutar:

```bash
php -S localhost:8000
```

Después abre `http://localhost:8000/`.

## Uso principal

- **Acceso:** inicia sesión con un usuario registrado. El sistema valida la licencia antes de permitir el acceso.
- **Punto de venta:** busca productos por nombre o código de barras, agrega unidades o pesos y valida el método de pago.
- **Inventario:** registra productos, departamentos, proveedores, costos, precios y existencias.
- **Facturación:** consulta, imprime y administra las facturas emitidas.
- **Compras:** registra facturas de proveedores y controla abonos pendientes.
- **Créditos:** administra clientes, límites de crédito, deuda e historial de pagos.
- **Caja:** abre turnos, registra movimientos y genera cortes.
- **Configuración:** administra usuarios, permisos, impuestos, tasa, ticket, métodos de pago y respaldos.

## Módulos principales

| Área | Archivos principales |
| --- | --- |
| Acceso | `login.php`, `validar.php`, `logout.php`, `validar_licencia.php` |
| Punto de venta | `ventas.php`, `ventas_movil.php`, `procesar_venta.php`, `procesar_venta_multiple.php` |
| Inventario | `inventario.php`, `guardar_producto.php`, `editar_producto.php`, `eliminar_producto.php`, `operaciones_inventario.php` |
| Facturación | `facturas.php`, `historial_facturas.php`, `imprimir_factura.php`, `imprimir_ticket.php` |
| Compras | `nueva_compra.php`, `procesar_compra.php`, `procesar_proveedor.php`, `proveedores.php` |
| Créditos | `creditos.php`, `historial.php`, `ver_pagos.php`, `imprimir_abono.php` |
| Caja | `corte.php`, `procesar_apertura.php`, `procesar_turno.php`, `chequear_caja.php` |
| Usuarios | `gestion_usuarios.php`, `actualizar_permisos.php` |
| Reportes | `estadisticas.php`, `reporte_ventas.php`, `reporte_inventario.php`, `resumen_ventas.php` |
| Configuración | `configuracion.php`, `actualizar_config.php`, `actualizar_config_avanzada.php` |
| Actualizaciones | `comprobar_actualizacion.php`, `actualizar_sistema.php`, `version.json` |
| Navegación | `sidebar.php`, `sidebar.css` |
| Base de datos | `respaldo_db.php`, `limpiar_sistema.php`, `limpiar_base_datos.sql`, `puntodeventa.sql` |

## Base de datos

El archivo [`puntodeventa.sql`](puntodeventa.sql) contiene la estructura inicial, datos de ejemplo y configuración base. También incluye tablas para:

- Productos, departamentos, proveedores e inventario.
- Ventas, detalles y métodos de pago.
- Facturas de proveedores y abonos.
- Clientes de crédito y pagos.
- Aperturas y turnos de caja.
- Usuarios, permisos, licencia y auditoría.
- Configuración fiscal, comercial y de Pago Móvil.

Para dejar una instalación preparada sin registros operativos, revisa primero el contenido de [`limpiar_base_datos.sql`](limpiar_base_datos.sql). El script conserva las tablas de configuración, métodos de pago, usuarios y licencia, pero elimina datos operativos permanentemente.

> Haz siempre una copia de seguridad antes de ejecutar scripts SQL de limpieza o restauración.

## Actualización del sistema

La versión instalada se consulta desde [`version.json`](version.json):

```json
{
  "version": "1.1.2",
  "channel": "stable",
  "last_updated": "2026-11-25"
}
```

Después de iniciar sesión como administrador, [`comprobar_actualizacion.php`](comprobar_actualizacion.php) consulta el último release estable publicado en GitHub. Si hay una versión nueva, permite continuar hacia [`actualizar_sistema.php`](actualizar_sistema.php).

El actualizador:

- Descarga el ZIP asociado al tag estable más reciente.
- Valida las rutas antes de extraer archivos.
- Crea copias temporales de los archivos reemplazados.
- Intenta revertir los cambios si ocurre un error durante la instalación.
- Conserva `conexion.php`, `.htaccess` y `facturas_adjuntas/`.

> El actualizador reemplaza archivos de la aplicación, pero no ejecuta migraciones de MySQL automáticamente.

### Antes de actualizar

1. Genera un respaldo desde **Configuración > Respaldo estructurado**.
2. Conserva una copia de `conexion.php`, `.htaccess` y `facturas_adjuntas/`.
3. Revisa manualmente las migraciones de base de datos incluidas en la nueva versión.
4. Evita operaciones críticas durante la actualización.

## Publicar una nueva versión

1. Prepara y prueba los cambios en `main`.
2. Actualiza [`version.json`](version.json) con la nueva versión estable.
3. Confirma los cambios en `main`.
4. Crea un GitHub Release estable con una etiqueta coincidente:

```text
Tag: v1.1.3
Título: v1.1.3
Estado: Release estable
```

El actualizador utiliza `/releases/latest` y descarga el ZIP del mismo tag. No marques la versión como prerelease si debe detectarse como actualización estable.

## Seguridad

Antes de utilizar el sistema en producción:

- Cambia todas las credenciales predeterminadas.
- Protege `conexion.php` y otros archivos sensibles.
- Usa contraseñas cifradas con `password_hash()` y `password_verify()`.
- Valida y sanitiza todas las entradas del usuario.
- Configura HTTPS y permisos mínimos en el servidor.
- Restringe las actualizaciones a usuarios administradores.
- Realiza copias de seguridad periódicas.
- Permite escritura únicamente sobre las rutas requeridas por el actualizador.

## Contribuciones

1. Haz un fork del repositorio.
2. Crea una rama:

```bash
git checkout -b feature/nueva-funcionalidad
```

3. Realiza y confirma los cambios:

```bash
git add .
git commit -m "Agrega nueva funcionalidad"
```

4. Envía la rama:

```bash
git push origin feature/nueva-funcionalidad
```

5. Abre un Pull Request hacia `main`.

## Autor

**freedarwuin**

- Repositorio: <https://github.com/freedarwuin/POS-System-Pro>
- Releases: <https://github.com/freedarwuin/POS-System-Pro/releases>
