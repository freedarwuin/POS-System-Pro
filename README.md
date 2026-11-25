# POS-System-Pro

Sistema de punto de venta (POS) desarrollado en PHP y MySQL para la gestión de ventas, inventario, facturación, proveedores, créditos y reportes.

**Versión:** 1.0.0

## Características

- Inicio de sesión y control de usuarios con roles.
- Gestión de permisos por módulo.
- Punto de venta para escritorio y móvil.
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

## Tecnologías

- **PHP 7.3 o superior**
- **MySQL 8.0** / MariaDB compatible
- **HTML5, CSS3 y JavaScript**
- **mysqli** para la conexión a la base de datos
- **phpMyAdmin** recomendado para importar y administrar la base de datos

## Requisitos

- Servidor web Apache o compatible.
- PHP 7.3 o una versión posterior compatible.
- Extensión `mysqli` habilitada en PHP.
- MySQL 5.7+ o MySQL 8.0.
- Navegador web actualizado.

## Instalación

### 1. Clonar el repositorio

```bash
git clone https://github.com/freedarwuin/POS-System-Pro.git
cd POS-System-Pro
```

También puedes descargar el proyecto como ZIP y colocar sus archivos dentro de la carpeta pública de tu servidor web, por ejemplo:

- XAMPP: `htdocs/POS-System-Pro`
- WAMP: `www/POS-System-Pro`
- Apache/Linux: `/var/www/html/POS-System-Pro`

### 2. Crear la base de datos

1. Accede a phpMyAdmin.
2. Crea una base de datos llamada `puntodeventa`.
3. Selecciona la base de datos.
4. Abre la pestaña **Importar**.
5. Importa el archivo [`puntodeventa.sql`](puntodeventa.sql).

También puedes importar desde la terminal:

```bash
mysql -u root -p -e "CREATE DATABASE puntodeventa CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;"
mysql -u root -p puntodeventa < puntodeventa.sql
```

### 3. Configurar la conexión

Edita [`conexion.php`](conexion.php) con las credenciales de tu servidor:

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

> Importante: no publiques usuarios, contraseñas o datos sensibles en repositorios públicos.

### 4. Ejecutar el sistema

Inicia Apache y MySQL y abre:

```text
http://localhost/POS-System-Pro/
```

El archivo [`index.php`](index.php) redirige automáticamente a la pantalla de inicio de sesión.

## Acceso

Los usuarios disponibles dependen de los datos importados desde `puntodeventa.sql`. Crea usuarios nuevos o modifica los existentes desde el módulo de gestión de usuarios después de acceder al sistema.

Por seguridad:

- Cambia las contraseñas predeterminadas.
- Utiliza contraseñas fuertes.
- Elimina datos de demostración antes de poner el sistema en producción.
- Revise la implementación de autenticación antes de exponer el proyecto a Internet.

## Funcionalidades principales

| Área | Archivos relacionados |
| --- | --- |
| Acceso | `login.php`, `validar.php`, `logout.php` |
| Punto de venta | `ventas.php`, `ventas_movil.php`, `procesar_venta_multiple.php` |
| Inventario | `inventario.php`, `guardar_producto.php`, `editar_producto.php`, `eliminar_producto.php` |
| Facturas | `facturas.php`, `historial_facturas.php`, `imprimir_factura.php` |
| Compras y proveedores | `nueva_compra.php`, `proveedores.php`, `procesar_compra.php`, `procesar_proveedor.php` |
| Créditos | `creditos.php`, `historial.php`, `ver_pagos.php` |
| Caja | `corte.php`, `procesar_apertura.php`, `procesar_turno.php`, `chequear_caja.php` |
| Usuarios | `gestion_usuarios.php`, `actualizar_permisos.php` |
| Reportes | `estadisticas.php`, `reporte_ventas.php`, `reporte_inventario.php`, `resumen_ventas.php` |
| Configuración | `configuracion.php`, `actualizar_config.php`, `actualizar_config_avanzada.php` |
| Base de datos | `respaldo_db.php`, `limpiar_sistema.php`, `limpiar_base_datos.sql` |
| Licencia | `validar_licencia.php`, `licencia_bloqueada.php`, `licencia_error.php` |

## Estructura general

```text
POS-System-Pro/
├── Imagenes/                 Recursos gráficos y asistente visual
├── facturas_adjuntas/        Archivos adjuntos de facturas
├── conexion.php              Conexión a MySQL
├── index.php                 Redirección inicial
├── login.php                 Inicio de sesión
├── validar.php               Validación de credenciales
├── ventas.php                Punto de venta principal
├── ventas_movil.php          Punto de venta para móviles
├── inventario.php            Gestión de inventario
├── facturas.php              Gestión de facturas
├── proveedores.php           Gestión de proveedores
├── creditos.php              Gestión de créditos
├── corte.php                 Apertura y corte de caja
├── configuracion.php         Configuración del sistema
├── puntodeventa.sql          Estructura y datos de la base de datos
├── respaldo_db.php           Respaldo de la base de datos
└── version.json              Versión del proyecto
```

## Configuración adicional

Desde el módulo de configuración pueden administrarse:

- Nombre, RIF y dirección del negocio.
- Mensajes de tickets y facturas.
- Tasa de cambio.
- IVA e IGTF.
- Métodos de pago activos.
- Configuración de Pago Móvil.
- Impresora y lector.
- Fondo de caja y datos de WhatsApp, si están disponibles.

## Licencia

El sistema incluye validación de licencia mediante las tablas y archivos `licencia` y `validar_licencia.php`. Debes configurar una licencia válida para evitar el bloqueo de la aplicación.

## Seguridad

Antes de usar el sistema en producción:

- Cambia todas las credenciales predeterminadas.
- Protege `conexion.php` y otros archivos sensibles.
- Implementa consultas preparadas en todos los procesos dinámicos.
- Usa contraseñas cifradas mediante `password_hash()` y `password_verify()`.
- Valida y sanitiza todas las entradas de usuario.
- Configura HTTPS y permisos adecuados en el servidor.
- Realiza copias de seguridad periódicas.

## Desarrollo

```bash
# Clonar
git clone https://github.com/freedarwuin/POS-System-Pro.git

# Entrar al proyecto
cd POS-System-Pro

# Ejecutar con el servidor integrado de PHP
php -S localhost:8000
```

Luego abre `http://localhost:8000/`.

> El servidor integrado de PHP puede servir para pruebas, pero se recomienda Apache o un entorno de producción adecuado para el despliegue.

## Contribuciones

Las contribuciones son bienvenidas:

1. Haz un fork del repositorio.
2. Crea una rama: `git checkout -b feature/nueva-funcionalidad`.
3. Realiza los cambios y confirma: `git commit -m "Agrega nueva funcionalidad"`.
4. Envía tu rama: `git push origin feature/nueva-funcionalidad`.
5. Abre un Pull Request.

## Autor

**freedarwuin**

Repositorio: <https://github.com/freedarwuin/POS-System-Pro>
