# Configurar Supabase desde cero (licencias de POS-System-Pro)

## Importante: Supabase no contiene los datos del punto de venta

POS-System-Pro utiliza **MySQL/MariaDB** para sus datos operativos: productos, ventas, inventario, usuarios, caja, facturas y clientes. Supabase se usa en esta integración solamente para **validar licencias**.

Por eso, un proyecto Supabase recién creado aparece vacío. No importes `puntodeventa.sql` a Supabase: ese archivo es un volcado de MySQL y no es la base operativa que usa esta aplicación. El proyecto de Supabase solo recibirá el esquema de licencias descrito en este documento.

## Requisitos

- Un proyecto activo de Supabase.
- POS-System-Pro instalado con MySQL/MariaDB y la tabla local `licencia`.
- PHP con las extensiones `mysqli` y `curl` habilitadas.
- Acceso a la configuración privada de variables de entorno del servidor PHP/Apache.

## Paso 1: crear o abrir el proyecto Supabase

1. En Supabase, crea un proyecto o abre el proyecto que usarás para las licencias.
2. Espera a que termine de aprovisionarse.
3. Ten a mano la URL del proyecto y su clave **publishable**. Se consultan en la configuración/API Keys del proyecto.

No compartas ni guardes en el repositorio la clave secreta (`secret` o `service_role`). PHP solo debe usar la clave publishable.

## Paso 2: crear el esquema de licencias

1. En este repositorio abre [`licensing.sql`](./licensing.sql).
2. Copia el archivo completo.
3. En Supabase abre **SQL Editor**, crea una consulta nueva, pega el SQL y pulsa **Run**.
4. Comprueba que la consulta termine sin errores.

El script crea:

- `public.pos_installations`: una fila por instalación/equipo que haya validado por primera vez.
- `public.pos_license_keys`: hashes de claves anuales; no almacena la clave original.
- `public.pos_license_status(...)`: RPC que crea/consulta la demo.
- `public.pos_activate_annual_license(...)`: RPC para activar una clave anual.

Las tablas permanecen privadas mediante RLS. La aplicación llama a las funciones RPC con la clave publishable.

### Comprobar que se crearon las tablas

En Supabase → **Table Editor** deben aparecer `pos_installations` y `pos_license_keys`. Al principio pueden tener cero filas: esto es normal. La tabla de instalaciones recibe una fila cuando la aplicación valida por primera vez un equipo; la tabla de claves recibe filas cuando tú registras claves para clientes.

También puedes comprobarlo en SQL Editor:

```sql
select to_regclass('public.pos_installations') as instalaciones,
       to_regclass('public.pos_license_keys') as claves;
```

## Paso 3: configurar el servidor PHP

Define estas variables en la configuración privada del servidor, panel de hosting o VirtualHost de Apache:

```text
SUPABASE_URL=https://TU-PROJECT-REF.supabase.co
SUPABASE_PUBLISHABLE_KEY=TU_CLAVE_PUBLISHABLE
```

Usa los valores del proyecto correcto. No pegues la clave real en este README, en GitHub, en un archivo accesible desde el navegador ni en un chat público. El archivo `supabase/.env.example` es una plantilla; PHP **no** lee automáticamente archivos `.env`.

La aplicación acepta `SUPABASE_ANON_KEY` como nombre heredado, pero se recomienda `SUPABASE_PUBLISHABLE_KEY`. **Nunca configures `SUPABASE_SECRET_KEY` ni `service_role` en PHP.**

Reinicia o recarga Apache/PHP después de configurar las variables. Asegúrate de que el proceso PHP realmente las herede. Si usas Apache, `SetEnv` puede configurarse en el VirtualHost privado del sitio; no pongas secretos en una ruta que el servidor entregue como archivo de texto.

## Paso 4: comprobar la base local MySQL/MariaDB

La base local del POS debe existir por separado y debe incluir la tabla `licencia`. Para una instalación nueva:

1. Crea la base MySQL/MariaDB.
2. Importa `puntodeventa.sql` en **MySQL/MariaDB**, no en Supabase.
3. Configura `conexion.php` con las credenciales de esa base local.
4. Conserva `licencia.servidor_id`: es el UUID persistente de ese equipo y permite reconocer su instalación en Supabase.

Haz un respaldo antes de cambiar o limpiar una base existente.

## Paso 5: probar la demo inicial

Al validar la licencia por primera vez, la aplicación envía el UUID local a `pos_license_status`. Supabase crea automáticamente una fila de demo para ese equipo. La duración configurada actualmente en `licensing.sql` es de **15 días**.

1. Asegúrate de que el servidor tenga Internet, cURL habilitado y las dos variables Supabase configuradas.
2. Abre POS-System-Pro e intenta iniciar sesión. La validación ocurre durante el flujo de acceso.
3. En Supabase → Table Editor → `pos_installations`, comprueba que apareció una fila.
4. En la base local MySQL, revisa `licencia.servidor_id`; debe contener el UUID del equipo.

La demo se crea una sola vez por UUID. No se reinicia al borrar datos operativos locales. Si borras o cambias `servidor_id`, Supabase lo verá como otra instalación y puede crear otra demo. Conserva ese UUID en los respaldos.

### Demo de una hora para una prueba

Para ajustar una instalación de demo ya creada, reemplaza el UUID por el valor de `licencia.servidor_id`:

```sql
update public.pos_installations
set expires_at = now() + interval '1 hour',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION'
  and license_type = 'demo';
```

Para que las instalaciones nuevas reciban una demo de una hora, cambia en `licensing.sql`:

```sql
now() + interval '15 days'
```

por:

```sql
now() + interval '1 hour'
```

Luego ejecuta de nuevo en SQL Editor la definición actualizada de la función `pos_license_status`. Esto no modifica automáticamente demos existentes.

## Paso 6: generar una clave anual

Cada clave debe ser aleatoria y única. Genérala en tu propio equipo; no uses nombres, contraseñas conocidas ni la clave que compartiste anteriormente en un chat.

### Windows PowerShell

```powershell
$bytes = New-Object byte[] 32
$rng = [System.Security.Cryptography.RandomNumberGenerator]::Create()
$rng.GetBytes($bytes)
$clave = [BitConverter]::ToString($bytes).Replace('-', '').ToLower()
$rng.Dispose()
$clave
```

El resultado es una clave de 64 caracteres hexadecimales. Guárdala en un lugar privado: Supabase almacenará el hash, así que no podrás recuperar la clave original desde la tabla.

### Linux, macOS o Git Bash

```bash
openssl rand -hex 32
```

## Paso 7: registrar el hash en Supabase

Por cada equipo, ejecuta en SQL Editor una inserción como esta. Sustituye el marcador por la clave original que acabas de generar:

```sql
insert into public.pos_license_keys (key_hash)
values (
  encode(
    extensions.digest(upper('PEGA_AQUI_LA_CLAVE_ORIGINAL'), 'sha256'),
    'hex'
  )
);
```

El texto del marcador no es una clave válida; debes reemplazarlo. No incluyas una clave real en commits o archivos de migración. Después de insertar, la tabla `pos_license_keys` tendrá una fila con el hash.

## Paso 8: activar la licencia en el equipo

1. Entrega al cliente la clave original de forma privada y segura.
2. Abre la pantalla de licencia del POS en el equipo correspondiente.
3. Introduce esa clave y envía el formulario de activación.
4. Comprueba que `pos_license_keys.activated_by` contiene el UUID del equipo y que `activated_at` tiene fecha.
5. Comprueba en `pos_installations` que la licencia del mismo UUID aparece con tipo anual y su nueva fecha de vencimiento.

La clave original se presenta una sola vez al cliente; Supabase conserva su hash. La aplicación y la pantalla de activación no necesitan la clave secreta de Supabase.

## Paso 9: clientes con varios equipos

En el esquema actual, **cada clave anual se vincula a una sola instalación/UUID**. No compartas una misma clave entre equipos.

- Juan compra 3 equipos → genera, registra y entrega 3 claves diferentes, una por equipo.
- Pedro compra 12 equipos → genera, registra y entrega 12 claves diferentes, una por equipo.

Las filas permiten ver `activated_by` (UUID), `activated_at` y `is_active`. El esquema actual no incluye todavía una tabla de clientes/contratos ni un límite automático de equipos por cliente. Lleva un registro privado con cliente, cantidad contratada, UUID de cada equipo, identificador de clave, fechas de pago y estado.

Para consultar activaciones sin mostrar claves originales:

```sql
select left(key_hash, 12) as referencia_hash,
       is_active,
       activated_by,
       activated_at,
       created_at
from public.pos_license_keys
order by created_at desc;
```

Para desactivar una clave, calcula su hash desde la clave original en SQL Editor:

```sql
update public.pos_license_keys
set is_active = false
where key_hash = encode(
  extensions.digest(upper('CLAVE_ORIGINAL'), 'sha256'),
  'hex'
);
```

La desactivación impide futuras activaciones de esa clave. Para suspender una instalación específica:

```sql
update public.pos_installations
set status = 'suspended',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION';
```

## Solución de problemas

- **Las tablas están vacías:** es normal antes de ejecutar la primera validación y registrar claves.
- **No aparecen las tablas:** confirma que `licensing.sql` se ejecutó completo y sin errores en el SQL Editor del proyecto correcto.
- **Falta configuración Supabase:** revisa que PHP/Apache herede `SUPABASE_URL` y `SUPABASE_PUBLISHABLE_KEY`; reinicia el servicio web si hace falta.
- **Error de cURL:** habilita la extensión PHP `curl` en la misma versión de PHP que utiliza Apache.
- **La activación dice clave inválida:** verifica que registraste el hash de esa clave exacta, que no la escribiste con espacios y que `is_active` sea `true`.
- **La clave ya se usó en otro equipo:** crea una clave independiente para ese equipo; no reutilices la existente.
- **No es una migración del POS a Supabase:** las consultas de ventas/inventario siguen dependiendo de la base MySQL/MariaDB local.

## Seguridad y límites

- Las tablas tienen RLS habilitado; la aplicación debe usar únicamente las RPC previstas.
- La clave publishable se puede usar desde el servidor para este diseño. Nunca uses la clave secreta o `service_role` en el POS.
- No guardes claves originales en Git, tickets públicos, logs ni capturas.
- Conserva respaldos de MySQL que incluyan `licencia.servidor_id`.
- La validación requiere comunicación con Supabase; si no se puede verificar el servicio, el acceso queda bloqueado por el comportamiento actual del código.
- El UUID identifica una instalación, pero no es un secreto ni una protección anti-manipulación inviolable.
- Este esquema sirve para activar y controlar instalaciones de forma básica. Para gestión comercial completa faltan clientes/contratos, cupos automáticos, renovaciones centralizadas y un panel administrativo.
