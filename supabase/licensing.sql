-- Esquema del servicio remoto de licencias para POS System Pro.
-- Ejecutar una vez en el SQL Editor del proyecto Supabase.

create extension if not exists pgcrypto with schema extensions;

create table if not exists public.pos_installations (
    installation_id uuid primary key,
    license_type text not null check (license_type in ('demo', 'annual')),
    status text not null default 'active' check (status in ('active', 'suspended')),
    started_at timestamptz not null default now(),
    expires_at timestamptz not null,
    updated_at timestamptz not null default now()
);

create table if not exists public.pos_license_keys (
    key_hash text primary key check (key_hash ~ '^[0-9a-f]{64}$'),
    is_active boolean not null default true,
    activated_by uuid references public.pos_installations(installation_id),
    activated_at timestamptz,
    created_at timestamptz not null default now()
);

alter table public.pos_installations enable row level security;
alter table public.pos_license_keys enable row level security;
revoke all on public.pos_installations from public, anon, authenticated;
revoke all on public.pos_license_keys from public, anon, authenticated;

-- Consulta/crea el periodo demo una sola vez por instalación. No se reinicia al vencer.
create or replace function public.pos_license_status(p_installation_id uuid)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog, public, extensions
as $$
declare
    v_installation public.pos_installations%rowtype;
begin
    if p_installation_id is null then
        raise exception 'installation_id inválido';
    end if;

    insert into public.pos_installations (installation_id, license_type, expires_at)
    values (p_installation_id, 'demo', now() + interval '15 days')
    on conflict (installation_id) do nothing;

    select * into v_installation
    from public.pos_installations
    where installation_id = p_installation_id;

    return jsonb_build_object(
        'status', case
            when v_installation.status <> 'active' then 'suspended'
            when v_installation.expires_at <= now() then 'expired'
            else 'active'
        end,
        'license_type', v_installation.license_type,
        'started_at', v_installation.started_at,
        'expires_at', v_installation.expires_at,
        'remaining_days', greatest(0, ceil(extract(epoch from (v_installation.expires_at - now())) / 86400)::integer)
    );
end;
$$;

-- Cada clave aleatoria de 256 bits se consume una sola vez y queda ligada a una instalación.
-- Repetir la misma petición en la instalación que ya la activó es idempotente.
create or replace function public.pos_activate_annual_license(
    p_installation_id uuid,
    p_activation_key text
)
returns jsonb
language plpgsql
security definer
set search_path = pg_catalog, public, extensions
as $$
declare
    v_key_hash text;
    v_key public.pos_license_keys%rowtype;
    v_installation public.pos_installations%rowtype;
begin
    if p_installation_id is null or p_activation_key is null
       or length(trim(p_activation_key)) < 32 or length(trim(p_activation_key)) > 256 then
        return jsonb_build_object('status', 'invalid_key', 'message', 'La clave de licencia no es válida.');
    end if;

    v_key_hash := encode(extensions.digest(upper(trim(p_activation_key)), 'sha256'), 'hex');

    select * into v_key
    from public.pos_license_keys
    where key_hash = v_key_hash
    for update;

    if not found or not v_key.is_active then
        return jsonb_build_object('status', 'invalid_key', 'message', 'La clave de licencia no es válida.');
    end if;

    if v_key.activated_by is not null and v_key.activated_by <> p_installation_id then
        return jsonb_build_object('status', 'key_used', 'message', 'Esta clave ya fue utilizada en otra instalación.');
    end if;

    insert into public.pos_installations (installation_id, license_type, expires_at)
    values (p_installation_id, 'annual', now() + interval '1 year')
    on conflict (installation_id) do nothing;

    select * into v_installation
    from public.pos_installations
    where installation_id = p_installation_id
    for update;

    if v_key.activated_by = p_installation_id then
        return jsonb_build_object(
            'status', case when v_installation.status = 'active' and v_installation.expires_at > now() then 'active' else 'expired' end,
            'license_type', v_installation.license_type,
            'expires_at', v_installation.expires_at,
            'remaining_days', greatest(0, ceil(extract(epoch from (v_installation.expires_at - now())) / 86400)::integer)
        );
    end if;

    update public.pos_installations
    set license_type = 'annual',
        status = 'active',
        expires_at = greatest(expires_at, now()) + interval '1 year',
        updated_at = now()
    where installation_id = p_installation_id
    returning * into v_installation;

    update public.pos_license_keys
    set activated_by = p_installation_id,
        activated_at = now()
    where key_hash = v_key_hash;

    return jsonb_build_object(
        'status', 'active',
        'license_type', v_installation.license_type,
        'expires_at', v_installation.expires_at,
        'remaining_days', greatest(0, ceil(extract(epoch from (v_installation.expires_at - now())) / 86400)::integer)
    );
end;
$$;

-- Las funciones SECURITY DEFINER son la única superficie pública; las tablas permanecen privadas.
revoke all on function public.pos_license_status(uuid) from public, anon, authenticated;
revoke all on function public.pos_activate_annual_license(uuid, text) from public, anon, authenticated;
grant execute on function public.pos_license_status(uuid) to anon;
grant execute on function public.pos_activate_annual_license(uuid, text) to anon;

-- Alta manual de una clave anual. Sustituye la cadena por una clave aleatoria
-- de al menos 32 caracteres y entrega la clave original al cliente de forma segura.
-- insert into public.pos_license_keys (key_hash)
-- values (encode(extensions.digest(upper('PEGA-AQUI-UNA-CLAVE-ALEATORIA'), 'sha256'), 'hex'));
