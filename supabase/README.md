# Licencias remotas con Supabase

Esta carpeta contiene el servicio de licencias de POS System Pro. El sistema actual ofrece una licencia **demo automática de 15 días** y licencias **anuales activadas con claves únicas**.

## Cómo funciona

- Cada instalación tiene un identificador `installation_id` UUID persistente.
- El UUID se guarda localmente en `licencia.servidor_id`.
- La primera consulta crea automáticamente una licencia demo de **15 días**.
- La demo no se reinicia cuando vence.
- Una licencia anual se activa con una clave única, aleatoria y de un solo uso.
- Cada clave queda vinculada a una instalación y no puede activarse en otra.
- Supabase guarda únicamente el hash SHA-256 de las claves. La clave original no se almacena.

## Limitación importante sobre equipos

Una licencia pertenece a una **instalación**, no a un nombre de usuario ni a un cliente vendido genéricamente.

- Una instalación compartida por varias computadoras se cuenta como **una licencia**.
- Cuatro instalaciones independientes instaladas en cuatro equipos necesitan **cuatro claves**.
- Para cambiar de equipo, no basta con copiar los archivos o la base de datos: se debe identificar si se conserva el mismo `installation_id` o si se crea una instalación nueva.

El sistema actual no contempla licencias por cantidad de equipos conectados ni licencias comerciales de 1 hora. Esas características requieren agregar campos y reglas adicionales al esquema de Supabase.

## 1. Crear el proyecto Supabase

1. Cree un proyecto en Supabase.
2. Abra **SQL Editor**.
3. Copie y ejecute todo el contenido de [`licensing.sql`](licensing.sql).
4. Confirme que existan estas tablas:

- `public.pos_installations`
- `public.pos_license_keys`

Las funciones principales son:

- `pos_license_status(installation_id)` para consultar o crear la demo.
- `pos_activate_annual_license(installation_id, activation_key)` para activar o renovar una licencia anual.

## 2. Configurar PHP

Obtenga la URL del proyecto y la clave **Publishable** desde **Project Settings > API**.

Configure las variables de entorno del servidor Apache/PHP:

```bash
export SUPABASE_URL="https://TU-PROYECTO.supabase.co"
export SUPABASE_PUBLISHABLE_KEY="sb_publishable_..."
```

En Apache puede agregarlas dentro del virtual host:

```apache
SetEnv SUPABASE_URL "https://TU-PROYECTO.supabase.co"
SetEnv SUPABASE_PUBLISHABLE_KEY "sb_publishable_..."
```

Reinicie Apache después de configurar las variables:

```bash
sudo systemctl restart apache2
```

No use una clave `service_role`, `sb_secret_*` ni una clave privada en PHP. El cliente rechaza estas claves por seguridad.

## 3. Instalar en una máquina nueva

1. Complete la instalación del POS y asegúrese de que exista `licencia.servidor_id`.
2. Acceda al sistema.
3. La aplicación consulta Supabase y crea automáticamente:

```text
license_type = demo
status = active
expires_at = ahora + 15 días
```

El UUID se genera una sola vez y permanece asociado a esa instalación.

Para verificar la instalación:

```sql
select
    installation_id,
    license_type,
    status,
    started_at,
    expires_at
from public.pos_installations
order by updated_at desc;
```

Busque el registro correspondiente al UUID guardado localmente en la tabla `licencia`.

## 4. Generar una licencia anual

Genere una clave aleatoria fuera del servidor usando OpenSSL:

```bash
openssl rand -base64 48
```

Ejemplo de formato generado:

```text
A8m...Xk2
```

Guarde la clave temporalmente en un gestor de contraseñas y entréguesela al cliente por un canal seguro.

### Registrar el hash en Supabase

En **Supabase → SQL Editor**, reemplace `PEGUE_AQUI_LA_CLAVE_GENERADA` por la clave sin modificar mayúsculas ni caracteres:

```sql
insert into public.pos_license_keys (key_hash)
values (
    encode(
        extensions.digest(
            upper(trim('PEGUE_AQUI_LA_CLAVE_GENERADA')),
            'sha256'
        ),
        'hex'
    )
);
```

No registre la clave original en una tabla o campo adicional. El sistema compara la clave ingresada después de convertirla a mayúsculas y eliminar espacios laterales.

## 5. Entregar y activar la clave

Entregue la clave original al cliente y solicite que la introduzca en la pantalla de activación del POS.

Internamente se ejecuta:

```text
pos_activate_annual_license(installation_id, clave)
```

La activación:

1. Calcula el hash de la clave introducida.
2. Busca ese hash en `pos_license_keys`.
3. Comprueba que la clave esté activa.
4. Comprueba que no haya sido utilizada por otra instalación.
5. Vincula la clave con el UUID de la instalación.
6. Convierte la instalación a licencia anual con vigencia de un año.

Al activar una clave por primera vez, la vigencia se calcula con:

```text
expires_at = mayor(fecha_actual_vencimiento, ahora) + 1 año
```

Por tanto:

- Si la demo venció, la licencia anual comienza desde la renovación y dura un año.
- Si la licencia anual aún está vigente, conserva el tiempo restante y agrega un año.
- Una misma clave no puede usarse en otra instalación.

## 6. Ejemplos de uso

### Juan recibe una licencia demo

Juan instala el POS por primera vez:

1. Se genera el UUID local.
2. Supabase crea una licencia demo de 15 días.
3. Juan no necesita introducir una clave.
4. Al vencer la demo, el sistema se bloquea y no crea una nueva demo.

### Pedro compra una licencia anual para un equipo

1. Genere una clave anual.
2. Registre su hash en Supabase.
3. Entregue la clave original a Pedro.
4. Pedro introduce la clave en su instalación.
5. La clave queda vinculada al UUID de ese equipo.

No vuelva a utilizar esta clave en otro equipo.

### Pedro compra una licencia para cuatro instalaciones independientes

Debe crear cuatro claves diferentes:

```text
Clave 1 -> Equipo / instalación A
Clave 2 -> Equipo / instalación B
Clave 3 -> Equipo / instalación C
Clave 4 -> Equipo / instalación D
```

Registre cada clave como una fila independiente en `pos_license_keys` y vincule cada una al UUID correspondiente al activarla.

Si las cuatro computadoras acceden a un único servidor y a una única base de datos, el sistema las identifica como una sola instalación; no detecta ni limita automáticamente equipos individuales.

### Juan vence su demo y compra una licencia anual

1. Genere una clave anual nueva.
2. Registre su hash en Supabase.
3. Entregue la clave a Juan.
4. Juan introduce la clave en la misma instalación cuya demo venció.
5. La función detecta que la licencia venció y establece un año desde la renovación.

No use la clave de otra persona ni una clave previamente utilizada.

## 7. Consultar licencias creadas

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

Significado:

- `activated_by = NULL`: clave disponible.
- `activated_by = UUID`: clave ya utilizada por esa instalación.
- `is_active = false`: clave revocada.

## 8. Consultar instalaciones

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

- `status = active` y `expires_at > now()`: licencia activa.
- `expires_at <= now()`: licencia vencida.
- `status = suspended`: licencia bloqueada.

## 9. Suspender una instalación

```sql
update public.pos_installations
set status = 'suspended',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION';
```

Esto bloquea tanto una demo como una licencia anual.

## 10. Reactivar una instalación suspendida con vigencia vigente

```sql
update public.pos_installations
set status = 'active',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION'
  and expires_at > now();
```

Si la licencia venció, no basta con cambiar el estado. Debe activar o renovar una clave anual válida.

## 11. Revocar una clave

```sql
update public.pos_license_keys
set is_active = false
where key_hash = encode(
    extensions.digest(
        upper(trim('PEGUE_AQUI_LA_CLAVE_GENERADA')),
        'sha256'
    ),
    'hex'
);
```

Revocar una clave impide activaciones futuras. No desvincula ni cambia automáticamente una instalación que ya haya activado esa clave.

## 12. Renovar una instalación existente

Una renovación usa una clave anual nueva. La función se comporta así:

```text
Si expires_at > ahora:
    nueva vigencia = expires_at + 1 año

Si expires_at <= ahora:
    nueva vigencia = ahora + 1 año
```

No reutilice una clave ya utilizada si desea entregar una renovación independiente: cree una clave nueva y registre su hash.

## Licencias de 1 hora

El diseño actual no genera licencias de 1 hora. Para incorporarlas se necesita ampliar:

- el tipo permitido en `license_type`;
- la tabla `pos_license_keys` con duración o plan;
- la función de activación para calcular `expires_at` según la duración;
- el README y las pantallas del POS con los nuevos planes disponibles.

Ejemplos posibles de duración futura:

```sql
now() + interval '1 hour'
now() + interval '15 days'
now() + interval '1 year'
```

No active manualmente vencimientos falsos ni modifique la fecha local como método de seguridad: el POS debe consultar siempre el estado remoto y sincronizarlo localmente.

## Seguridad operativa

- No coloque claves originales o privadas en el código fuente.
- No coloque hashes permanentes de claves en archivos públicos innecesarios.
- No guarde una hoja permanente con claves originales en el SQL Editor.
- No use una clave `service_role` o `sb_secret_*` en PHP.
- Use HTTPS para la URL de Supabase.
- Restrinja el acceso administrativo de Supabase.
- Haga copias de seguridad de `pos_installations` y `pos_license_keys`.
- Proteja la base de datos local y los archivos de configuración.

## Pruebas recomendadas

1. Instale el POS en una máquina nueva y confirme que se crea el UUID local.
2. Confirme que se crea una licencia demo de 15 días.
3. Verifique que una nueva consulta no reinicia la demo vencida.
4. Genere una clave anual y registre su hash.
5. Active la clave y confirme que aparece como anual y activa.
6. Intente usar la misma clave en otra instalación: debe devolver `key_used`.
7. Suspenda una instalación y confirme que el POS la bloquea.
8. Reactive una instalación con fecha vigente.
9. Renueve una demo vencida con una clave anual nueva.
10. Pruebe cada caso con una clave independiente para cada equipo.
