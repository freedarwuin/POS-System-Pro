# POS-System-Pro

**POS-System-Pro** es una plataforma web de punto de venta desarrollada en PHP y MySQL. Permite administrar ventas, inventario, facturación, proveedores, créditos, caja, usuarios y reportes desde una interfaz adaptable a equipos de escritorio y dispositivos móviles.

[![Versión](https://img.shields.io/badge/version-1.0.0-blue)](https://github.com/freedarwuin/POS-System-Pro/releases/tag/v1.0.0)
[![PHP](https://img.shields.io/badge/PHP-7.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/)

## Funcionalidades

- Inicio de sesión con roles y permisos por módulo.
- Punto de venta para escritorio y dispositivos móviles.
- Productos por unidad, paquete o granel.
- Control de inventario, departamentos, proveedores y compras.
- Facturación, impresión de tickets y comprobantes.
- Clientes, ventas a crédito y registro de abonos.
- Apertura y cierre de caja por turnos.
- Reportes de ventas, inventario y movimientos.
- Métodos de pago configurables, incluidos efectivo, divisas y pago móvil.
- Configuración de IVA, IGTF y tasa de cambio.
- Respaldo y limpieza de la base de datos.
- Validación de licencia y registro de auditoría.
- Comprobación de actualizaciones mediante GitHub Releases.

## Tecnologías

- PHP 7.3 o posterior
- MySQL 5.7+, MySQL 8.0 o MariaDB compatible
- HTML5, CSS3 y JavaScript
- Extensión PHP `mysqli`
- Extensión PHP `ZipArchive`

## Requisitos

- Servidor web Apache o compatible.
- PHP 7.3 o posterior con `mysqli` y `ZipArchive` habilitados.
- MySQL 5.7+ o MariaDB compatible.
- Permisos de escritura para el actualizador del sistema.
- Conexión HTTPS válida hacia GitHub.
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
3. Importa el archivo `puntodeventa.sql`.

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
?>
```

No publiques credenciales reales ni datos sensibles en un repositorio público.

### 4. Iniciar el sistema

Inicia Apache y MySQL y abre:

```text
http://localhost/POS-System-Pro/
```

El archivo [`index.php`](index.php) redirige a la pantalla de inicio de sesión.

## Actualización del sistema

El actualizador consulta el último release estable publicado en GitHub y compara su etiqueta con la versión localizada en [`version.json`](version.json).

Después de iniciar sesión como administrador, el sistema puede mostrar una ventana modal cuando exista una versión nueva. Desde esta ventana puedes actualizar o continuar más tarde.

También puedes abrir manualmente:

```text
http://localhost/POS-System-Pro/actualizar_sistema.php
```

El proceso descarga el ZIP correspondiente al tag publicado, valida sus rutas, crea copias temporales de los archivos reemplazados y permite revertir cambios si ocurre un error.

El actualizador conserva:

- `conexion.php`
- `.htaccess`
- `facturas_adjuntas/`

> El actualizador reemplaza archivos del proyecto, pero no ejecuta migraciones de MySQL automáticamente.

Antes de actualizar:

1. Genera un respaldo desde **Configuración > Respaldo estructurado**.
2. Conserva una copia de `conexion.php`, `.htaccess` y `facturas_adjuntas/`.
3. Revisa manualmente las migraciones de base de datos de la nueva versión.
4. Evita operaciones críticas durante la actualización.

## Publicar una nueva versión

1. Prepara y prueba los cambios en la rama `main`.
2. Actualiza [`version.json`](version.json):

```json
{
  "version": "1.1.0",
  "channel": "stable",
  "last_updated": "2026-11-25"
}
```

3. Confirma los cambios en `main`.
4. Crea un GitHub Release estable con una etiqueta que coincida:

```text
Tag: v1.1.0
Título: v1.1.0
Estado: Release estable
```

El actualizador utiliza `/releases/latest` y descarga el archivo ZIP del mismo tag. No marques la versión como prerelease si debe detectarse como actualización estable.

## Módulos principales

| Área | Archivos principales |
| --- | --- |
| Acceso | `login.php`, `validar.php`, `logout.php` |
| Punto de venta | `ventas.php`, `ventas_movil.php`, `procesar_venta.php`, `procesar_venta_multiple.php` |
| Inventario | `inventario.php`, `guardar_producto.php`, `editar_producto.php`, `eliminar_producto.php` |
| Facturación | `facturas.php`, `historial_facturas.php`, `imprimir_factura.php`, `imprimir_ticket.php` |
| Compras | `nueva_compra.php`, `procesar_compra.php`, `procesar_proveedor.php`, `proveedores.php` |
| Créditos | `creditos.php`, `historial.php`, `ver_pagos.php`, `imprimir_abono.php` |
| Caja | `corte.php`, `procesar_apertura.php`, `procesar_turno.php`, `chequear_caja.php` |
| Usuarios | `gestion_usuarios.php`, `actualizar_permisos.php` |
| Reportes | `estadisticas.php`, `reporte_ventas.php`, `reporte_inventario.php`, `resumen_ventas.php` |
| Configuración | `configuracion.php`, `actualizar_config.php`, `actualizar_config_avanzada.php` |
| Actualizaciones | `actualizar_sistema.php`, `comprobar_actualizacion.php`, `version.json` |
| Base de datos | `respaldo_db.php`, `limpiar_sistema.php`, `limpiar_base_datos.sql` |
| Licencia | `validar_licencia.php`, `licencia_bloqueada.php`, `licencia_error.php` |

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

## Desarrollo local

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
php -S localhost:8000
```

Abre `http://localhost:8000/` en el navegador. Para producción se recomienda Apache o un entorno de hosting adecuado.

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
