# Licencias remotas con Supabase

Esta carpeta contiene el servicio de licencias anual y demo de POS System Pro.

## Cómo funciona

- Cada instalación tiene un `installation_id` UUID persistente almacenado en la tabla local `licencia.servidor_id`.
- La primera consulta crea automáticamente una licencia demo de **15 días**.
- La demo no se reinicia después de vencer.
- Una licencia anual se activa con una clave única aleatoria.
- Cada clave solo puede usarse una vez y queda vinculada a una instalación.
- Supabase guarda solamente el hash SHA-256 de las claves; la clave original no se almacena.

## 1. Crear el proyecto Supabase

1. Cree un proyecto en Supabase.
2. Abra **SQL Editor**.
3. Copie y ejecute todo el contenido de `licensing.sql`.
4. Verifique que existan las tablas:
   - `public.pos_installations`
   - `public.pos_license_keys`

## 2. Configurar PHP

Obtenga la URL del proyecto y la clave **Publishable** desde **Project Settings > API**.

Configure variables de entorno del servidor Apache/PHP:

```bash
export SUPABASE_URL="https://TU-PROYECTO.supabase.co"
export SUPABASE_PUBLISHABLE_KEY="sb_publishable_..."
```

En Apache puede agregarlas con `SetEnv` dentro de la configuración del virtual host:

```apache
SetEnv SUPABASE_URL "https://TU-PROYECTO.supabase.co"
SetEnv SUPABASE_PUBLISHABLE_KEY "sb_publishable_..."
```

Reinicie Apache después de configurar las variables.

No use una clave `service_role` ni una clave `sb_secret_*` en PHP. El cliente ya rechaza ambas formas por seguridad.

## 3. Generar una clave anual

Genere una clave aleatoria fuera del servidor usando OpenSSL:

```bash
openssl rand -base64 48
```

Ejemplo de formato generado:

```text
A8m...Xk2
```

No guarde ni muestre esta clave en la base de datos original. Guárdela temporalmente en un gestor de contraseñas y entréguesela al cliente de forma segura.

## 4. Registrar el hash de la clave

En el **SQL Editor** de Supabase, calcule el hash y registre la clave como pendiente:

```sql
insert into public.pos_license_keys (key_hash)
values (encode(extensions.digest(upper(trim('PEGUE_AQUI_LA_CLAVE_GENERADA')), 'sha256'), 'hex'));
```

Reemplaza `PEGUE_AQUI_LA_CLAVE_GENERADA` sin modificar el texto generado. Por ejemplo:

```sql
insert into public.pos_license_keys (key_hash)
values (encode(extensions.digest(upper(trim('A8m...Xk2')), 'sha256'), 'hex'));
```

No ejecute el hash usando la clave en minúsculas o agregando espacios. El sistema normaliza la clave a mayúsculas y elimina espacios laterales antes de calcular el hash.

## 5. Entregar y activar la clave

Entregue la clave original al cliente y haga que la introduzca en la pantalla de activación del POS.

Internamente se ejecuta:

```text
pos_activate_annual_license(installation_id, clave)
```

La activación:

1. Calcula el hash de la clave ingresada.
2. Busca ese hash en `pos_license_keys`.
3. Comprueba que esté activa y no haya sido usada por otra instalación.
4. Vincula la clave con el `installation_id`.
5. Establece o extiende la licencia anual por un año.

## 6. Consultar licencias creadas

```sql
select
    key_hash,
    is_active,
    activated_by,
    activated_at,
    created_at
from public.pos_license_keys
order by created_at desc;
```

Clave disponible: `activated_by` es `NULL`.
Clave ya usada: `activated_by` contiene el UUID de la instalación.

## 7. Consultar instalaciones

```sql
select
    installation_id,
    license_type,
    status,
    started_at,
    expires_at,
    updated_at
from public.pos_installations
order by updated_at desc;
```

Estados posibles:

- `status = active` y fecha vigente: licencia activa.
- `expires_at` vencida: licencia expirada.
- `status = suspended`: licencia bloqueada.

## 8. Suspender una instalación

```sql
update public.pos_installations
set status = 'suspended',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION';
```

Esto bloquea la instalación tanto en la demo como en la licencia anual.

## 9. Reactivar una instalación

```sql
update public.pos_installations
set status = 'active',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION'
  and expires_at > now();
```

Si la licencia venció, debe renovarse o activarse nuevamente antes de que pueda quedar activa.

## 10. Revocar una clave

```sql
update public.pos_license_keys
set is_active = false
where key_hash = encode(
    extensions.digest(upper(trim('PEGUE_AQUI_LA_CLAVE_GENERADA')), 'sha256'),
    'hex'
);
```

Revocar una clave impide su activación futura. No desvincula automáticamente una instalación que ya haya activado esa clave.

## 11. Renovar una instalación activada

En el diseño actual, una clave nueva activada por una instalación existente puede extender su fecha de vencimiento:

```text
expires_at = mayor(fecha_actual_vencimiento, ahora) + 1 año
```

Es decir, si la licencia aún no vence, conserva el tiempo restante y agrega un año. Si ya venció, comienza un año desde el momento de la renovación.

## Seguridad operativa

- No agregue claves ni hashes al código fuente ni a archivos públicos.
- No use el SQL Editor para mantener una hoja permanente con claves sin hash.
- No comparta una clave de Supabase `service_role`.
- Use HTTPS para la URL de Supabase.
- Use el usuario/administrador de Supabase únicamente para crear y administrar licencias.
- Haga copias de seguridad de las tablas `pos_installations` y `pos_license_keys`.

## Pruebas recomendadas

1. Instale el POS en una máquina nueva y confirme que se crea el UUID local.
2. Confirme que se crea una licencia demo de 15 días.
3. Cree una clave con el proceso descrito.
4. Active la clave y confirme que aparece como anual y activa.
5. Intente usar la misma clave en otra instalación: debe devolver `key_used`.
6. Suspenda la instalación y confirme que el POS la bloquea.
7. Reactive la instalación con una fecha vigente.
