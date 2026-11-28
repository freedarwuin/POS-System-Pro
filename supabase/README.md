# Servicio de licencias con Supabase

Esta integración usa Supabase únicamente para validar licencias y activar claves anuales. **POS-System-Pro sigue usando MySQL/MariaDB para ventas, inventario, usuarios y el resto de los datos operativos**; no migra automáticamente la base de datos del POS a Supabase.

## Configuración inicial

1. En el proyecto Supabase correspondiente, abre **SQL Editor** y ejecuta todo `licensing.sql`. El script crea las tablas privadas y las funciones RPC expuestas para licencias.
2. En el servidor PHP/Apache define `SUPABASE_URL` y `SUPABASE_PUBLISHABLE_KEY` con la URL del proyecto y la clave publicable. La aplicación también acepta `SUPABASE_ANON_KEY` por compatibilidad con instalaciones anteriores.
3. **No configures `SUPABASE_SECRET_KEY`, `service_role` ni otra clave secreta en PHP.** La clave publishable es suficiente para invocar las funciones RPC.
4. Activa las extensiones PHP `curl` y `mysqli`, y verifica que Apache/PHP herede las variables. El archivo `.env.example` es solo una plantilla: PHP no carga archivos `.env` automáticamente.
5. Importa o conserva la tabla local `licencia` incluida en `puntodeventa.sql`. El UUID persistente de instalación se almacena en `licencia.servidor_id`. Haz una copia de seguridad antes de probar.

Ejemplo de variables privadas del servidor:

```dotenv
SUPABASE_URL=https://tu-proyecto.supabase.co
SUPABASE_PUBLISHABLE_KEY=sb_publishable_REEMPLAZAR
```

No publiques estas variables en Git ni las incluyas en archivos que Apache entregue públicamente.

## Tipos de licencia y equipo

Al validar por primera vez cada equipo, se crea una **demo inicial** en Supabase:

- Cada equipo obtiene un UUID persistente en `licencia.servidor_id` de su MySQL local.
- El esquema actual asigna una demo de 15 días. Para pruebas puede ajustarse a una hora.
- El demo no se reinicia al borrar datos operativos ni al reinstalar el sistema si se conserva el UUID.
- Si se pierde el UUID local, Supabase considera al equipo una instalación nueva.
- El estado remoto es la fuente de verdad. La fecha local es únicamente una copia sincronizada.

Cada clave anual se vincula a un único UUID y queda consumida para ese equipo. Una clave no se comparte entre varios equipos.

## Probar el sistema durante una hora

Para modificar una instalación que ya existe, utiliza el UUID de `licencia.servidor_id`:

```sql
update public.pos_installations
set expires_at = now() + interval '1 hour',
    updated_at = now()
where installation_id = 'UUID-DE-LA-INSTALACION'
  and license_type = 'demo';
```

Para que todas las demos nuevas duren una hora, cambia esta línea de `licensing.sql`:

```sql
values (p_installation_id, 'demo', now() + interval '15 days')
```

por:

```sql
values (p_installation_id, 'demo', now() + interval '1 hour')
```

Ejecuta nuevamente las funciones modificadas en Supabase. Este cambio afecta a las instalaciones nuevas; no modifica automáticamente demos existentes.

## Crear claves independientes

Genera una clave aleatoria fuerte fuera del repositorio. Con 32 bytes aleatorios se obtienen 64 caracteres hexadecimales:

**Linux, macOS o Git Bash:**

```bash
openssl rand -hex 32
```

**PowerShell en Windows:**

```powershell
-join ((1..32) | ForEach-Object { '{0:x2}' -f (Get-Random -Minimum 0 -Maximum 256) })
```

El resultado es la clave original que se entrega al equipo. En Supabase se guarda únicamente su hash SHA-256:

```sql
insert into public.pos_license_keys (key_hash)
values (
  encode(
    extensions.digest(upper('CLAVE_ORIGINAL_DEL_EQUIPO'), 'sha256'),
    'hex'
  )
);
```

Cada equipo debe tener una clave independiente. Crear una sola clave compartida permite activarla una sola vez con el esquema actual y no representa una licencia válida para varios equipos.

## Juan con tres equipos

Juan compra una licencia anual para tres equipos. Crea tres claves aleatorias distintas y registra cada hash:

```sql
insert into public.pos_license_keys (key_hash)
values
  (encode(extensions.digest(upper('CLAVE_JUAN_EQUIPO_1'), 'sha256'), 'hex')),
  (encode(extensions.digest(upper('CLAVE_JUAN_EQUIPO_2'), 'sha256'), 'hex')),
  (encode(extensions.digest(upper('CLAVE_JUAN_EQUIPO_3'), 'sha256'), 'hex'));
```

Entrega una clave original a cada equipo. Al activarla, Supabase registra `activated_by` con el UUID correspondiente y `activated_at` con la fecha de activación.

Pedro puede comprar un contrato de 12 equipos y recibir 12 claves independientes registradas de la misma manera.

## Consultar el estado de las claves

```sql
select
  left(key_hash, 12) as identificador_hash,
  is_active,
  activated_by,
  activated_at,
  created_at
from public.pos_license_keys
order by created_at desc;
```

El identificador anterior es solamente una forma abreviada de consulta. No muestres el hash completo ni la clave original en interfaces públicas.

Para revocar o desactivar una clave:

```sql
update public.pos_license_keys
set is_active = false
where key_hash = encode(
  extensions.digest(upper('CLAVE_ORIGINAL_DEL_EQUIPO'), 'sha256'),
  'hex'
);
```

La desactivación impide usos posteriores. Para suspender todos los equipos de un cliente, suspende sus `pos_installations` desde Supabase:

```sql
update public.pos_installations
set status = 'suspended',
    updated_at = now()
where installation_id in (
  'UUID-EQUIPO-1',
  'UUID-EQUIPO-2'
);
```

## Ejemplo de control operativo

Mantén fuera de Supabase, o en tablas administrativas privadas adicionales, una ficha por cliente y una relación de equipos:

| Dato | Ejemplo |
|---|---|
| Cliente | Juan Pérez |
| Contrato | 3 equipos |
| Tipo | Anual |
| Pago/renovación | Fecha acordada |
| Equipo 1 | UUID + clave 1 |
| Equipo 2 | UUID + clave 2 |
| Equipo 3 | UUID + clave 3 |

Para control administrativo, recomienda una hoja privada o tablas adicionales con, como mínimo:

- Nombre o identificación del cliente.
- Cantidad de equipos contratados.
- UUID de cada equipo.
- Hash o identificador de cada clave.
- Fecha de creación y activación.
- Fecha de vencimiento o renovación.
- Estado del contrato y del equipo.
- Historial de pago y suspensión.

El esquema actual incluye claves activadas y instalaciones, pero todavía no incluye una tabla administrativa de clientes ni límites de equipos por contrato. Para vender licencias comerciales, añade una tabla privada donde la clave se asigne a un cliente y a un contrato antes de entregarla.

## Renovaciones

Cada clave anual activa una fecha de vencimiento de un año desde la activación en el esquema actual.

Antes de entregar una renovación:

1. Confirma el cliente y el contrato.
2. Identifica los UUID de sus equipos.
3. Genera una clave independiente por equipo o renueva el contrato asociado a esas claves.
4. Registra el pago y la nueva fecha de vencimiento.
5. Entrega las claves originales por un canal privado.

## Seguridad y operación

- Las tablas tienen RLS habilitado y no conceden acceso de lectura/escritura a `anon` ni a `authenticated`.
- Las funciones RPC públicas son la única superficie remota prevista.
- La clave publishable es pública por diseño. Nunca configures en PHP la clave secreta o `service_role`.
- El UUID de instalación no es un secreto ni un mecanismo anti-manipulación infalible.
- Conserva `licencia.servidor_id` en los respaldos de MySQL.
- Usa claves únicas, aleatorias y de al menos 32 caracteres.
- No uses textos descriptivos, nombres de clientes o claves reutilizadas como claves reales.
- Las claves reales y los identificadores comerciales deben permanecer fuera de Git, SQL de ejemplo y documentación pública.
- Supervisa activaciones y desactiva claves sospechosas o vencidas.
