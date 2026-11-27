# POS-System-Pro

**POS-System-Pro** es una plataforma web de punto de venta desarrollada en PHP y MySQL. Permite gestionar ventas, inventario, facturación, proveedores, créditos, caja, usuarios y reportes desde una interfaz adaptable para escritorio y dispositivos móviles.

> Versión estable actual: **1.1.7**  
> Última actualización registrada: **27/11/2026**

[![PHP](https://img.shields.io/badge/PHP-7.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/) [![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/) [![Apache](https://img.shields.io/badge/Apache-2-D22128?logo=apache&logoColor=white)](https://httpd.apache.org/)

## Características

- Inicio de sesión con usuarios y roles.
- Control de permisos por módulo.
- Punto de venta para escritorio y dispositivos móviles.
- Ventas en efectivo, dólares, Pago Móvil, Punto de Venta, Cashea, BioPago y otros métodos configurables.
- Ventas a crédito y registro de abonos.
- Apertura y cierre de caja por turno.
- Gestión de productos, departamentos, proveedores y existencias.
- Productos por unidad, granel o paquete.
- Control de stock y registro de movimientos.
- Compras a proveedores y facturas proveedor.
- Facturación, tickets e impresión de comprobantes.
- Clientes de crédito, límites de crédito y seguimiento de deuda.
- Reportes de ventas, inventario, movimientos y stock bajo.
- Configuración fiscal: IVA e IGTF.
- Conversión entre bolívares y dólares mediante tasa configurable.
- Respaldos SQL, limpieza controlada y actualización integrada.
- Interfaz lateral adaptable a escritorio y móvil.

## Tecnologías

- PHP 7.3 o posterior.
- MySQL 5.7+ o MariaDB compatible.
- Apache 2.
- HTML5, CSS3 y JavaScript.
- Extensión PHP `mysqli`.
- Extensión PHP `zip`.
- `SweetAlert2`, Font Awesome y Google Fonts.

## Requisitos

- Servidor web Apache o compatible.
- PHP 7.3 o posterior con las extensiones `mysqli`, `zip`, `mbstring`, `curl` y `gd` habilitadas.
- MySQL 5.7+ o MariaDB compatible.
- Permisos de escritura sobre los directorios que guardan archivos, respaldos y adjuntos.
- Conexión HTTPS para operaciones que requieran acceso externo.
- Navegador web actualizado.

Para Raspberry Pi OS o Debian puede utilizarse el instalador incluido:

```bash
sudo bash instalador.sh
```

El instalador requiere ejecución como administrador, `apt-get`, acceso a Internet y permisos sobre Apache y MariaDB.

## Instalación manual

### 1. Obtener el proyecto

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
```

También puede descargarse la última versión publicada desde:

https://github.com/freedarwuin/POS-System-Pro/releases

Coloque el proyecto en el directorio público correspondiente:

- XAMPP: `htdocs/POS-System-Pro`
- WAMP: `www/POS-System-Pro`
- Apache/Linux: `/var/www/html/POS-System-Pro`

### 2. Crear la base de datos

Acceda a phpMyAdmin o al cliente MySQL e importe:

```sql
CREATE DATABASE punto_de_venta CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Luego importe `puntodeventa.sql` desde la interfaz de phpMyAdmin o desde una terminal:

```bash
mysql -u root -p punto_de_venta < puntodeventa.sql
```

### 3. Configurar la conexión

Edite `conexion.php` con los datos de su instalación:

```php
<?php
$host = "localhost";
$user = "tu_usuario";
$pass = "tu_contraseña";
$db   = "punto_de_venta";

$conexion = mysqli_connect($host, $user, $pass, $db);

if (!$conexion) {
    die("Error de conexión: " . mysqli_connect_error());
}
```

No publique contraseñas reales ni credenciales sensibles en una instalación pública.

### 4. Iniciar el sistema

Inicie Apache y MySQL, luego abra:

```text
http://localhost/POS-System-Pro/
```

El archivo `index.php` redirige a `login.php` cuando no existe una sesión activa.

Para pruebas locales sin Apache:

```bash
php -S localhost:8000
```

Después abra `http://localhost:8000/`.

## Uso principal

1. Inicie sesión con un usuario registrado.
2. Configure negocio, impuestos, tasa de cambio y métodos de pago.
3. Cree productos, departamentos y proveedores.
4. Abra caja y opere desde el punto de venta.
5. Registre ventas, pagos múltiples, créditos y abonos.
6. Consulte facturas, movimientos e inventario.
7. Cierre caja y revise los reportes.

## Módulos principales

| Área | Archivos principales |
|---|---|
| Acceso | `login.php`, `validar.php`, `logout.php`, `validar_licencia.php` |
| Punto de venta | `ventas.php`, `ventas_movil.php`, `procesar_venta_multiple.php` |
| Inventario | `inventario.php`, `guardar_producto.php`, `editar_producto.php`, `eliminar_producto.php`, `operaciones_inventario.php` |
| Compras | `nueva_compra.php`, `procesar_compra.php`, `procesar_proveedor.php`, `proveedores.php` |
| Facturas | `facturas.php`, `historial_facturas.php`, `imprimir_factura.php`, `imprimir_ticket.php` |
| Créditos | `creditos.php`, `historial.php`, `ver_pagos.php`, `imprimir_abono.php` |
| Caja | `corte.php`, `procesar_apertura.php`, `procesar_turno.php`, `chequear_caja.php` |
| Reportes | `estadisticas.php`, `reporte_ventas.php`, `reporte_inventario.php`, `resumen_ventas.php` |
| Usuarios | `gestion_usuarios.php`, `actualizar_permisos.php` |
| Configuración | `configuracion.php`, `actualizar_config.php`, `actualizar_config_avanzada.php` |
| Actualizaciones | `comprobar_actualizacion.php`, `actualizar_sistema.php`, `version.json` |
| Navegación | `sidebar.php`, `sidebar.css` |
| Base de datos | `puntodeventa.sql`, `limpiar_base_datos.sql`, `actualizar_plan_respaldos.sql` |

## Base de datos

`puntodeventa.sql` incluye la estructura inicial y datos de ejemplo o demostración. Comprueba antes de usarlo si estos datos corresponden a un entorno real.

La base contiene tablas para:

- Productos, departamentos y proveedores.
- Ventas, detalles de venta y métodos de pago.
- Facturas y compras.
- Clientes de crédito y abonos.
- Aperturas y movimientos de caja.
- Usuarios, permisos, licencia y auditoría.
- Configuración fiscal y comercial.
- Respaldo, historial de stock y Pago Móvil.

> Antes de instalar en una base existente, cree una copia de seguridad. La limpieza y restauración pueden eliminar información operativa.

## Actualizaciones

La versión instalada se lee desde `version.json`:

```json
{
  "version": "1.1.7",
  "channel": "stable",
  "last_updated": "2026-11-27"
}
```

Como administrador puede consultar `comprobar_actualizacion.php`. El actualizador descarga el ZIP del release estable, crea copias temporales y valida las rutas antes de reemplazar archivos.

El actualizador conserva `conexion.php`, `.htaccess` y `facturas_adjuntas/`, pero no ejecuta migraciones MySQL automáticas. Revise manualmente las migraciones incluidas antes de actualizar.

Antes de actualizar:

1. Cree un respaldo completo de archivos y base de datos.
2. Verifique `version.json` y el cambio disponible.
3. Conserve una copia de `conexion.php` y `.htaccess`.
4. Revise los scripts SQL de la nueva versión.
5. Evite actualizar si existe una base de datos o configuración crítica activa.

## Seguridad

Antes de utilizar el sistema en producción:

- Cambie todas las credenciales predeterminadas.
- Proteja `conexion.php` y los archivos que contienen datos sensibles.
- Configure contraseñas mediante `password_hash()` y `password_verify()`.
- Valide y sanee todas las entradas del usuario.
- Habilite HTTPS y permisos mínimos en el servidor.
- Restrinja las operaciones administrativas a usuarios autorizados.
- Revise periódicamente usuarios, auditoría y copias de seguridad.
- Cambie la contraseña fija creada por el instalador antes de exponer el servicio.
- Permita escritura únicamente sobre las rutas requeridas por adjuntos y respaldos.

## Respaldo y limpieza

La aplicación incluye respaldos desde `respaldo_db.php`, `limpiar_sistema.php` y el plan de respaldos administrado desde la interfaz.

Los scripts SQL relacionados son:

- `limpiar_base_datos.sql`: elimina datos operativos.
- `limpiar_sistema.php`: ejecuta o prepara acciones de limpieza.
- `actualizar_plan_respaldos.sql`: crea o actualiza tablas del plan de respaldo.
- `guardar_plan_respaldo.php`: guarda la configuración del plan.

Tome siempre una copia de seguridad antes de ejecutar cualquier limpieza o restauración.

## Licencia

Este proyecto no declara actualmente una licencia de software en el repositorio.

## Contribuir

1. Cree un fork del repositorio.
2. Cree una rama:

```bash
git checkout -b feature/nueva-funcionalidad
```

3. Confirme los cambios:

```bash
git add .
git commit -m "Agrega nueva funcionalidad"
```

4. Envíe la rama:

```bash
git push origin feature/nueva-funcionalidad
```

5. Abra un Pull Request hacia `main`.

## Autor

**freedarwuin**

- Repositorio: https://github.com/freedarwuin/POS-System-Pro
- Releases: https://github.com/freedarwuin/POS-System-Pro/releases
