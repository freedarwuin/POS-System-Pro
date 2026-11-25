# POS-System-Pro

Sistema de punto de venta desarrollado en PHP y MySQL para gestionar ventas, inventario, facturación, proveedores, créditos y reportes.

**Versión estable:** 1.0.0  
**Release:** <https://github.com/freedarwuin/POS-System-Pro/releases/tag/v1.0.0>

## Características

- Inicio de sesión y control de usuarios con roles.
- Gestión de permisos por módulo.
- Punto de venta para escritorio y dispositivos móviles.
- Inventario con productos por unidad, paquete o granel.
- Departamentos y proveedores.
- Facturación e impresión de tickets.
- Gestión de clientes y ventas a crédito.
- Apertura y cierre de caja por turnos.
- Reportes de ventas, inventario y movimientos.
- Métodos de pago configurables.
- Configuración de tasa de cambio, IVA e IGTF.
- Pago móvil, punto de venta, efectivo, divisas y otros métodos.
- Respaldo de base de datos y limpieza del sistema.
- Validación y bloqueo por estado o vencimiento de licencia.
- Registro de auditoría y movimientos de inventario.
- Comprobación de nuevas versiones mediante GitHub Releases.
- Modal de actualización después del inicio de sesión para administradores.

## Tecnologías

- PHP 7.3 o posterior
- MySQL 8.0 / MariaDB compatible
- HTML5, CSS3 y JavaScript
- Extensión `mysqli`
- Extensión `ZipArchive`
- GitHub Releases y GitHub API

## Requisitos

- Servidor web Apache o compatible.
- PHP 7.3 o posterior.
- Extensiones `mysqli` y `ZipArchive` habilitadas.
- MySQL 5.7+ o MySQL 8.0.
- Permisos de escritura sobre los archivos que vaya a reemplazar el actualizador.
- Conexión HTTPS válida hacia GitHub desde el servidor.
- Navegador web actualizado.

## Instalación

### 1. Obtener el proyecto

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
```

También puede descargarse desde la página de [releases](https://github.com/freedarwuin/POS-System-Pro/releases) y colocarse dentro de la carpeta pública del servidor:

- XAMPP: `htdocs/POS-System-Pro`
- WAMP: `www/POS-System-Pro`
- Apache/Linux: `/var/www/html/POS-System-Pro`

### 2. Crear la base de datos

1. Accede a phpMyAdmin.
2. Crea una base de datos llamada `puntodeventa`.
3. Importa `puntodeventa.sql` desde la pestaña **Importar**.

Desde la terminal:

```bash
mysql -u root -p -e "CREATE DATABASE puntodeventa CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -p puntodeventa < puntodeventa.sql
```

### 3. Configurar la conexión

Edita [`conexion.php`](conexion.php) con las credenciales de MySQL:

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

No publique usuarios, contraseñas ni datos sensibles en repositorios públicos.

### 4. Ejecutar el sistema

Inicie Apache y MySQL y abra:

```text
http://localhost/POS-System-Pro/
```

[`index.php`](index.php) redirige a la pantalla de inicio de sesión.

## Actualizaciones

El sistema utiliza el último GitHub Release estable para detectar y descargar actualizaciones.

### Comprobación después del inicio de sesión

Después de autenticarse, un administrador puede recibir una ventana modal si existe una versión superior a la instalada:

- **Actualizar:** abre [`actualizar_sistema.php`](actualizar_sistema.php).
- **Más tarde:** continúa a la pantalla principal.

La comprobación se realiza en [`comprobar_actualizacion.php`](comprobar_actualizacion.php). Los usuarios con rol Cajero o cualquier otro rol no reciben el aviso de actualización y continúan directamente a su destino habitual.

### Instalación manual

La página [`actualizar_sistema.php`](actualizar_sistema.php) está disponible únicamente para administradores:

```text
http://localhost/POS-System-Pro/actualizar_sistema.php
```

Al instalar una actualización:

1. Consulta el último Release estable mediante la API de GitHub.
2. Comprueba que el tag tenga una versión válida.
3. Compara el tag con `version` en [`version.json`](version.json).
4. Descarga el ZIP asociado exactamente al tag publicado.
5. Valida las rutas del contenido ZIP.
6. Crea respaldos temporales de los archivos reemplazados.
7. Instala los archivos nuevos y revierte los cambios si ocurre un error.

La actualización conserva:

- `conexion.php`
- `.htaccess`
- `facturas_adjuntas/`

### Publicar una nueva versión

Para publicar una actualización:

1. Prepara y prueba los cambios en la rama `main`.
2. Actualiza [`version.json`](version.json) con la nueva versión:

```json
{
  "version": "1.1.0",
  "channel": "stable",
  "last_updated": "2026-11-25"
}
```

3. Confirma el archivo actualizado en `main`.
4. Crea un Release estable con un tag correspondiente:

```text
Tag: v1.1.0
Título: v1.1.0
Estado: estable
```

El actualizador obtendrá `v1.1.0` desde el último Release y descargará el código de ese mismo tag:

```text
https://codeload.github.com/freedarwuin/POS-System-Pro/zip/refs/tags/v1.1.0
```

> No publiques prereleases si deseas que sean reconocidas como actualizaciones estables. El actualizador usa el endpoint `/releases/latest`.

### Respaldos y migraciones

Antes de actualizar:

- Descarga un respaldo desde **Configuración > Respaldo estructurado**.
- Conserva una copia de `conexion.php`, `.htaccess` y `facturas_adjuntas/`.
- Detén temporalmente operaciones críticas si varios usuarios usan el sistema.
- Respaldos y migraciones de la base de datos deben verificarse antes de instalar.

El actualizador reemplaza archivos del proyecto, pero **no ejecuta migraciones MySQL automáticamente**.

## Funcionalidades principales

| Área | Archivos relacionados |
| --- | --- |
| Acceso | `login.php`, `validar.php`, `logout.php`, `comprobar_actualizacion.php` |
| Punto de venta | `ventas.php`, `ventas_movil.php`, `procesar_venta_multiple.php` |
| Inventario | `inventario.php`, `guardar_producto.php`, `editar_producto.php`, `eliminar_producto.php` |
| Facturas | `facturas.php`, `historial_facturas.php`, `imprimir_factura.php` |
| Compras y proveedores | `nueva_compra.php`, `proveedores.php`, `procesar_compra.php`, `procesar_proveedor.php` |
| Créditos | `creditos.php`, `historial.php`, `ver_pagos.php` |
| Caja | `corte.php`, `procesar_apertura.php`, `procesar_turno.php`, `chequear_caja.php` |
| Usuarios | `gestion_usuarios.php`, `actualizar_permisos.php` |
| Reportes | `estadisticas.php`, `reporte_ventas.php`, `reporte_inventario.php`, `resumen_ventas.php` |
| Configuración | `configuracion.php`, `actualizar_config.php`, `actualizar_config_avanzada.php` |
| Actualizaciones | `actualizar_sistema.php`, `comprobar_actualizacion.php`, `version.json` |
| Base de datos | `respaldo_db.php`, `limpiar_sistema.php`, `limpiar_base_datos.sql` |
| Licencia | `validar_licencia.php`, `licencia_bloqueada.php`, `licencia_error.php` |

## Estructura general

```text
POS-System-Pro/
├── Imagenes/
├── facturas_adjuntas/
├── conexion.php
├── index.php
├── login.php
├── validar.php
├── comprobar_actualizacion.php
├── ventas.php
├── ventas_movil.php
├── inventario.php
├── facturas.php
├── proveedores.php
├── creditos.php
├── corte.php
├── configuracion.php
├── actualizar_sistema.php
├── puntodeventa.sql
├── respaldo_db.php
└── version.json
```

## Seguridad

Antes de usar el sistema en producción:

- Cambia todas las credenciales predeterminadas.
- Protege `conexion.php` y otros archivos sensibles.
- Usa contraseñas cifradas mediante `password_hash()` y `password_verify()`.
- Valida y sanitiza todas las entradas de usuario.
- Configura HTTPS y permisos adecuados en el servidor.
- Limita la instalación de actualizaciones a administradores.
- Realiza copias de seguridad periódicas antes de actualizar.
- Verifica que el hosting permita escritura únicamente sobre las rutas necesarias.

## Desarrollo

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
php -S localhost:8000
```

Luego abra `http://localhost:8000/`. Para producción se recomienda Apache o un entorno de hosting adecuado.

## Contribuciones

1. Haz un fork del repositorio.
2. Crea una rama:

```bash
git checkout -b feature/nueva-funcionalidad
```

3. Realiza los cambios y confirma:

```bash
git commit -m "Agrega nueva funcionalidad"
```

4. Envía la rama:

```bash
git push origin feature/nueva-funcionalidad
```

5. Abre un Pull Request.

## Autor

**freedarwuin**

Repositorio: <https://github.com/freedarwuin/POS-System-Pro>  
Releases: <https://github.com/freedarwuin/POS-System-Pro/releases>
