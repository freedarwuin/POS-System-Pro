# Servicio de licencias Supabase

## Configuración inicial

1. Crea un proyecto Supabase y ejecuta `licensing.sql` completo en **SQL Editor**.
2. En el servidor PHP configura las variables de entorno `SUPABASE_URL` y `SUPABASE_ANON_KEY` usando la URL del proyecto y su clave pública `anon`/publishable. Nunca configures `service_role` en PHP ni la publiques en el repositorio.
3. Instala/activa la extensión PHP `curl` y comprueba que Apache/PHP herede esas variables de entorno.
4. Importa o conserva la tabla local `licencia` incluida en `puntodeventa.sql`. El UUID de instalación se guarda en `licencia.servidor_id`; haz respaldo de la base antes de probar.

Al validar por primera vez, Supabase crea un periodo de demostración de 15 días para ese UUID. La fecha de expiración se registra en Supabase y no se reinicia al borrar datos operativos locales. La activación cambia el tipo a anual y añade un año; las claves se consumen una sola vez y quedan ligadas a la instalación.

## Crear una clave anual

Genera una clave aleatoria fuerte fuera del repositorio (mínimo 32 caracteres; preferiblemente 256 bits aleatorios) y ejecuta en SQL Editor:

```sql
insert into public.pos_license_keys (key_hash)
values (encode(extensions.digest(upper('CLAVE-ALEATORIA-ENTREGADA-AL-CLIENTE'), 'sha256'), 'hex'));
```

Entrega el valor original por un canal seguro. La base guarda únicamente su hash. No introduzcas claves reales en migraciones, commits o archivos públicos.

## Notas

- El periodo comienza en la primera validación y requiere que el servicio Supabase esté accesible. Si la conexión al servicio falla, el sistema bloquea el acceso hasta poder verificarlo.
- Cambiar o reinstalar la base local puede generar otro UUID si se pierde el valor de `servidor_id`. Inclúyelo siempre en los respaldos; una licencia anual activada no se puede transferir automáticamente.
- Las funciones RPC están expuestas a `anon`, pero las tablas no. El identificador de instalación es público y no debe considerarse un mecanismo anti-manipulación inviolable. Para un control comercial más fuerte, añade autenticación/limitación de solicitudes y un proceso administrativo de suspensión y transferencia.
