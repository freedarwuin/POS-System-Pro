# POS-System-Pro

<p align="center">
  <img src="assets/pos-system-pro-logo.svg" alt="Logo de POS SystemPro" width="100%">
</p>

**POS-System-Pro** es una plataforma web de punto de venta desarrollada en PHP y MySQL. Permite gestionar ventas, inventario, facturación, proveedores, créditos, caja, usuarios y reportes desde una interfaz adaptable.

> Versión estable actual: **1.1.7**  
> Última actualización registrada: **27/11/2026**

[![PHP](https://img.shields.io/badge/PHP-7.3%2B-777BB4?logo=php&logoColor=white)](https://www.php.net/) [![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white)](https://www.mysql.com/) [![Supabase](https://img.shields.io/badge/Supabase-licencias-3ECF8E?logo=supabase&logoColor=white)](https://supabase.com/)

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
- Validación opcional de licencias en Supabase; MySQL/MariaDB continúa siendo la base de datos operativa del POS.

## Tecnologías y requisitos

- PHP 7.3 o posterior con `mysqli`, `zip`, `mbstring`, `curl` y `gd` habilitados.
- MySQL 5.7+ o MariaDB compatible.
- Apache 2 o servidor web compatible.
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

También puede descargarse la última versión publicada desde https://github.com/freedarwuin/POS-System-Pro/releases.
Coloque el proyecto en el directorio público correspondiente (por ejemplo, `htdocs/POS-System-Pro` en XAMPP o `/var/www/html/POS-System-Pro` en Apache/Linux).

### 2. Crear la base de datos

Acceda a phpMyAdmin o al cliente MySQL e importe:

```sql
CREATE DATABASE puntodeventa CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Luego importe `puntodeventa.sql` desde phpMyAdmin o desde una terminal:

```bash
mysql -u root -p puntodeventa < puntodeventa.sql
```

### 3. Configurar la conexión MySQL

Configure `conexion.php` con los datos de su instalación. No publique contraseñas reales ni credenciales sensibles.

### 4. Configurar Supabase para licencias (opcional)

Sigue las instrucciones de [`supabase/README.md`](supabase/README.md): ejecuta `supabase/licensing.sql` en SQL Editor y configura `SUPABASE_URL` y `SUPABASE_PUBLISHABLE_KEY` en el entorno privado de PHP/Apache. No uses ni publiques la clave secreta/service-role. Esta integración es para licencias; ventas, inventario, usuarios y los demás datos operativos siguen en MySQL/MariaDB.

### 5. Iniciar el sistema

Inicie Apache y MySQL y abra `http://localhost/POS-System-Pro/`. El archivo `index.php` redirige a `login.php` cuando no existe una sesión activa.

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
| Supabase | `supabase/licensing.sql`, `supabase/license_client.php`, `supabase/README.md` |
| Base de datos | `puntodeventa.sql`, `limpiar_base_datos.sql`, `actualizar_plan_respaldos.sql` |

## Base de datos

`puntodeventa.sql` incluye la estructura inicial y datos de ejemplo o demostración. Compruebe antes de usarlo si esos datos corresponden a un entorno real. La base operativa contiene productos, ventas, facturas, créditos, caja, usuarios, licencia y configuración comercial.

> Antes de instalar en una base existente, cree una copia de seguridad. La limpieza y restauración pueden eliminar información operativa.

## Actualizaciones

La versión instalada se lee desde `version.json`. El actualizador integrado no ejecuta migraciones MySQL automáticamente; revise manualmente los scripts SQL incluidos antes de actualizar.

Antes de actualizar:

1. Cree un respaldo completo de archivos y base de datos.
2. Verifique la versión y los cambios disponibles.
3. Conserve `conexion.php` y `.htaccess`.
4. Revise las migraciones incluidas.
5. Evite actualizar si existe una base de datos o configuración crítica activa.

## Seguridad

Antes de utilizar el sistema en producción:

- Cambie todas las credenciales predeterminadas y no use contraseñas fijas de instalación.
- Mantenga `conexion.php` y las variables de entorno fuera del repositorio y del directorio servido públicamente cuando sea posible.
- Cambie las contraseñas existentes a hashes con `password_hash()` y verifique con `password_verify()`.
- Valide y sanee todas las entradas del usuario.
- Habilite HTTPS y permisos mínimos en el servidor.
- Restrinja las operaciones administrativas a usuarios autorizados.
- Revise periódicamente usuarios, auditoría y copias de seguridad.
- Permita escritura únicamente sobre las rutas requeridas por adjuntos y respaldos.

## Respaldo y limpieza

La aplicación incluye respaldos desde `respaldo_db.php`, `limpiar_sistema.php` y el plan de respaldos administrado desde la interfaz. Tome siempre una copia de seguridad antes de ejecutar limpieza o restauración.

## Licencia

Este proyecto no declara actualmente una licencia de software en el repositorio.

## Contribuir

1. Cree un fork del repositorio.
2. Cree una rama: `git checkout -b feature/nueva-funcionalidad`.
3. Confirme los cambios con un mensaje descriptivo.
4. Envíe la rama y abra un Pull Request hacia `main`.

## Autor

**freedarwuin**

- Repositorio: https://github.com/freedarwuin/POS-System-Pro
- Releases: https://github.com/freedarwuin/POS-System-Pro/releases
