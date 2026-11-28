# Servicio de licencias con Supabase

Esta integración usa Supabase únicamente para validar licencias y activar claves anuales. **POS-System-Pro sigue usando MySQL/MariaDB para ventas, inventario, usuarios y el resto de los datos del negocio**; no migra automáticamente la base de datos del POS a Supabase.

## Configuración inicial

1. En el proyecto Supabase correspondiente, abre **SQL Editor** y ejecuta todo `licensing.sql`. El script crea las tablas privadas y las funciones RPC expuestas para licencias.
2. En la configuración del servidor PHP/Apache define `SUPABASE_URL` y `SUPABASE_PUBLISHABLE_KEY` con la URL del proyecto y la clave publishable de **API Keys**. La aplicación también acepta `SUPABASE_ANON_KEY` por compatibilidad con instalaciones anteriores.
3. **No configures `SUPABASE_SECRET_KEY`, `service_role` ni otra clave secreta en PHP.** El cliente rechaza claves secretas conocidas; la clave publishable es suficiente para invocar estas funciones.
4. Activa las extensiones PHP `curl` y `mysqli`, y verifica que el entorno de Apache/PHP herede las variables. El archivo `.env.example` es solo una plantilla: PHP no carga archivos `.env` automáticamente.
5. Importa o conserva la tabla local `licencia` incluida en `puntodeventa.sql`. El UUID persistente de instalación se almacena en `licencia.servidor_id`. Haz una copia de seguridad antes de probar.

Ejemplo de variables (reemplaza los valores en la configuración privada del servidor, no en Git):

```dotenv
SUPABASE_URL=https://tu-proyecto.supabase.co
SUPABASE_PUBLISHABLE_KEY=sb_publishable_REEMPLAZAR
```

En Apache puedes definirlas en la configuración privada del VirtualHost con directivas `SetEnv` y recargar Apache. No publiques esa configuración ni añadas credenciales reales a `.htaccess`, capturas, commits o archivos servidos por el sitio.

Al validar por primera vez, Supabase crea un periodo de demostración de 15 días para ese UUID. La fecha de expiración se registra de forma remota y no se reinicia al borrar datos operativos locales. La validación necesita que Supabase esté accesible; si el servicio no responde, se bloquea el acceso hasta poder verificarlo.

## Crear una clave anual

Genera una clave aleatoria fuerte fuera del repositorio (mínimo 32 caracteres; preferiblemente 256 bits aleatorios). Ejecuta en SQL Editor, sustituyendo el marcador por la clave generada:

```sql
insert into public.pos_license_keys (key_hash)
values (encode(extensions.digest(upper('CLAVE-ALEATORIA-ENTREGADA-AL-CLIENTE'), 'sha256'), 'hex'));
```

Entrega el valor original por un canal seguro. Supabase guarda únicamente su hash. No introduzcas claves reales en migraciones, commits o archivos públicos.

## Seguridad y operación

- Las tablas tienen RLS habilitado y no conceden acceso de lectura/escritura a `anon` ni a `authenticated`. Las dos funciones `SECURITY DEFINER` conceden únicamente ejecución a `anon`.
- La clave publishable es pública por diseño; la protección de datos depende de que no se concedan permisos directos a las tablas y de las funciones SQL.
- El UUID de instalación no es un secreto ni un mecanismo anti-manipulación inviolable. Un sistema instalado bajo control del cliente no puede ofrecer una protección de licencia infalible.
- Conserva `licencia.servidor_id` en los respaldos. Si se pierde, la instalación genera otro UUID y Supabase lo considerará otra instalación; una licencia anual activada no se transfiere automáticamente.
- Las RPC públicas no sustituyen controles de abuso. Supervisa las solicitudes y desactiva claves en `pos_license_keys` cuando sea necesario.
