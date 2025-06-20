PGDMP  ;    $        
        }           hovervid_db    14.18 (Homebrew)    17.4 �    �           0    0    ENCODING    ENCODING        SET client_encoding = 'UTF8';
                           false            �           0    0 
   STDSTRINGS 
   STDSTRINGS     (   SET standard_conforming_strings = 'on';
                           false            �           0    0 
   SEARCHPATH 
   SEARCHPATH     8   SELECT pg_catalog.set_config('search_path', '', false);
                           false            �           1262    35418    hovervid_db    DATABASE     w   CREATE DATABASE hovervid_db WITH TEMPLATE = template0 ENCODING = 'UTF8' LOCALE_PROVIDER = libc LOCALE = 'en_US.UTF-8';
    DROP DATABASE hovervid_db;
                     postgres    false                        2615    2200    public    SCHEMA     2   -- *not* creating schema, since initdb creates it
 2   -- *not* dropping schema, since initdb creates it
                  	   achrafdev    false            �           0    0    SCHEMA public    ACL     Q   REVOKE USAGE ON SCHEMA public FROM PUBLIC;
GRANT ALL ON SCHEMA public TO PUBLIC;
                     	   achrafdev    false    5                        3079    35419    pg_trgm 	   EXTENSION     ;   CREATE EXTENSION IF NOT EXISTS pg_trgm WITH SCHEMA public;
    DROP EXTENSION pg_trgm;
                        false    5            �           0    0    EXTENSION pg_trgm    COMMENT     e   COMMENT ON EXTENSION pg_trgm IS 'text similarity measurement and index searching based on trigrams';
                             false    2            �           1247    35501    plugin_status_enum    TYPE     �   CREATE TYPE public.plugin_status_enum AS ENUM (
    'not_installed',
    'pending_activation',
    'active',
    'inactive',
    'pending_deactivation',
    'suspended',
    'error'
);
 %   DROP TYPE public.plugin_status_enum;
       public               postgres    false    5            �           0    0    TYPE plugin_status_enum    COMMENT     y   COMMENT ON TYPE public.plugin_status_enum IS 'ENUM type defining all possible plugin status states for domain tracking';
          public               postgres    false    902                       1255    35515 +   get_plugin_status_history(integer, integer)    FUNCTION       CREATE FUNCTION public.get_plugin_status_history(p_domain_id integer, p_limit integer DEFAULT 50) RETURNS TABLE(id bigint, old_status public.plugin_status_enum, new_status public.plugin_status_enum, changed_by integer, change_reason text, created_at timestamp without time zone)
    LANGUAGE plpgsql
    AS $$
            BEGIN
                RETURN QUERY
                SELECT 
                    psl.id,
                    psl.old_status,
                    psl.new_status,
                    psl.changed_by,
                    psl.change_reason,
                    psl.created_at
                FROM plugin_status_logs psl
                WHERE psl.domain_id = p_domain_id
                ORDER BY psl.created_at DESC
                LIMIT p_limit;
            END;
            $$;
 V   DROP FUNCTION public.get_plugin_status_history(p_domain_id integer, p_limit integer);
       public               postgres    false    5    902            �           0    0 H   FUNCTION get_plugin_status_history(p_domain_id integer, p_limit integer)    COMMENT     �   COMMENT ON FUNCTION public.get_plugin_status_history(p_domain_id integer, p_limit integer) IS 'Get plugin status change history for a specific domain';
          public               postgres    false    283            $           1255    35516    handle_plugin_status_change()    FUNCTION     5
  CREATE FUNCTION public.handle_plugin_status_change() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    user_id INTEGER := NULL;
    reason TEXT := NULL;
BEGIN
    -- Only proceed if plugin_status actually changed
    IF OLD IS NULL OR OLD.plugin_status IS DISTINCT FROM NEW.plugin_status THEN
        
        -- Update the updated_at timestamp
        NEW.updated_at := CURRENT_TIMESTAMP;
        
        -- Validate status transition (only if this is an update, not insert)
        IF OLD IS NOT NULL AND OLD.plugin_status IS NOT NULL THEN
            IF NOT validate_plugin_status_transition(OLD.plugin_status, NEW.plugin_status) THEN
                RAISE EXCEPTION 'Invalid plugin status transition from % to %. Check valid transitions in validate_plugin_status_transition function.', 
                    OLD.plugin_status, NEW.plugin_status
                    USING ERRCODE = '23514', -- Check constraint violation
                          DETAIL = 'Plugin status transitions must follow business rules',
                          HINT = 'Use the validate_plugin_status_transition function to check valid transitions';
            END IF;
        END IF;
        
        -- Try to get user_id and reason from session variables if they exist
        BEGIN
            user_id := current_setting('app.current_user_id', true)::INTEGER;
        EXCEPTION WHEN OTHERS THEN
            user_id := NULL;
        END;
        
        BEGIN
            reason := current_setting('app.status_change_reason', true);
            IF reason = '' THEN
                reason := NULL;
            END IF;
        EXCEPTION WHEN OTHERS THEN
            reason := NULL;
        END;
        
        -- Log the status change with proper casting
        INSERT INTO plugin_status_logs (
            domain_id,
            old_status,
            new_status,
            changed_by,
            change_reason,
            created_at,
            updated_at
        ) VALUES (
            NEW.id,
            CASE WHEN OLD IS NULL THEN NULL ELSE OLD.plugin_status::plugin_status_enum END,
            NEW.plugin_status::plugin_status_enum,
            user_id,
            reason,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        );
        
        -- Clear the session variables 
        BEGIN
            PERFORM set_config('app.current_user_id', '', false);
            PERFORM set_config('app.status_change_reason', '', false);
        EXCEPTION WHEN OTHERS THEN
            -- Ignore errors when clearing session variables
        END;
        
    END IF;
    
    RETURN NEW;
END;
$$;
 4   DROP FUNCTION public.handle_plugin_status_change();
       public               postgres    false    5            �           0    0 &   FUNCTION handle_plugin_status_change()    COMMENT     �   COMMENT ON FUNCTION public.handle_plugin_status_change() IS 'Trigger function to handle plugin status changes: validates transitions, updates timestamps, and logs changes';
          public               postgres    false    292            )           1255    35771    handle_plugin_status_logging()    FUNCTION     �  CREATE FUNCTION public.handle_plugin_status_logging() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
DECLARE
    user_id INTEGER := NULL;
    reason TEXT := NULL;
BEGIN
    -- Only proceed if plugin_status actually changed
    IF OLD IS NULL OR OLD.plugin_status IS DISTINCT FROM NEW.plugin_status THEN
        
        -- Try to get user_id and reason from session variables if they exist
        BEGIN
            user_id := current_setting('app.current_user_id', true)::INTEGER;
        EXCEPTION WHEN OTHERS THEN
            user_id := NULL;
        END;
        
        BEGIN
            reason := current_setting('app.status_change_reason', true);
            IF reason = '' THEN
                reason := NULL;
            END IF;
        EXCEPTION WHEN OTHERS THEN
            reason := NULL;
        END;
        
        -- Log the status change with proper casting
        INSERT INTO plugin_status_logs (
            domain_id,
            old_status,
            new_status,
            changed_by,
            change_reason,
            created_at,
            updated_at
        ) VALUES (
            NEW.id,
            CASE WHEN OLD IS NULL THEN NULL ELSE OLD.plugin_status::plugin_status_enum END,
            NEW.plugin_status::plugin_status_enum,
            user_id,
            reason,
            CURRENT_TIMESTAMP,
            CURRENT_TIMESTAMP
        );
        
        -- Clear the session variables
        BEGIN
            PERFORM set_config('app.current_user_id', '', false);
            PERFORM set_config('app.status_change_reason', '', false);
        EXCEPTION WHEN OTHERS THEN
            -- Ignore errors when clearing session variables
        END;
        
    END IF;
    
    RETURN NEW;
END;
$$;
 5   DROP FUNCTION public.handle_plugin_status_logging();
       public               postgres    false    5            (           1255    35770 !   handle_plugin_status_validation()    FUNCTION     �  CREATE FUNCTION public.handle_plugin_status_validation() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
BEGIN
    -- Only proceed if plugin_status actually changed
    IF OLD IS NULL OR OLD.plugin_status IS DISTINCT FROM NEW.plugin_status THEN
        
        -- Update the updated_at timestamp
        NEW.updated_at := CURRENT_TIMESTAMP;
        
        -- Validate status transition (only if this is an update, not insert)
        IF OLD IS NOT NULL AND OLD.plugin_status IS NOT NULL THEN
            IF NOT validate_plugin_status_transition(OLD.plugin_status::plugin_status_enum, NEW.plugin_status::plugin_status_enum) THEN
                RAISE EXCEPTION 'Invalid plugin status transition from % to %. Check valid transitions in validate_plugin_status_transition function.', 
                    OLD.plugin_status, NEW.plugin_status
                    USING ERRCODE = '23514',
                          DETAIL = 'Plugin status transitions must follow business rules',
                          HINT = 'Use the validate_plugin_status_transition function to check valid transitions';
            END IF;
        END IF;
    END IF;
    
    RETURN NEW;
END;
$$;
 8   DROP FUNCTION public.handle_plugin_status_validation();
       public               postgres    false    5            %           1255    35517 /   set_plugin_status_change_context(integer, text)    FUNCTION     *  CREATE FUNCTION public.set_plugin_status_change_context(p_user_id integer DEFAULT NULL::integer, p_reason text DEFAULT NULL::text) RETURNS void
    LANGUAGE plpgsql
    AS $$
            BEGIN
                IF p_user_id IS NOT NULL THEN
                    PERFORM set_config('app.current_user_id', p_user_id::TEXT, false);
                END IF;
                
                IF p_reason IS NOT NULL THEN
                    PERFORM set_config('app.status_change_reason', p_reason, false);
                END IF;
            END;
            $$;
 Y   DROP FUNCTION public.set_plugin_status_change_context(p_user_id integer, p_reason text);
       public               postgres    false    5            �           0    0 K   FUNCTION set_plugin_status_change_context(p_user_id integer, p_reason text)    COMMENT     �   COMMENT ON FUNCTION public.set_plugin_status_change_context(p_user_id integer, p_reason text) IS 'Helper function to set context for plugin status changes (user_id and reason)';
          public               postgres    false    293                       1255    35803    update_updated_at_column()    FUNCTION     �   CREATE FUNCTION public.update_updated_at_column() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
        BEGIN
            NEW.updated_at = CURRENT_TIMESTAMP;
            RETURN NEW;
        END;
        $$;
 1   DROP FUNCTION public.update_updated_at_column();
       public               postgres    false    5            &           1255    35518 !   update_user_status_from_session()    FUNCTION       CREATE FUNCTION public.update_user_status_from_session() RETURNS trigger
    LANGUAGE plpgsql
    AS $$
            BEGIN
                -- If user_id is not null, update the user status
                IF NEW.user_id IS NOT NULL THEN
                    -- Update user status to active if session is active
                    IF NEW.is_active = true THEN
                        UPDATE users SET status = 'active' WHERE id = NEW.user_id;
                    ELSE
                        -- Check if user has any other active sessions
                        IF NOT EXISTS (
                            SELECT 1 FROM sessions 
                            WHERE user_id = NEW.user_id 
                            AND is_active = true 
                            AND id != NEW.id
                        ) THEN
                            UPDATE users SET status = 'inactive' WHERE id = NEW.user_id;
                        END IF;
                    END IF;
                END IF;
                RETURN NEW;
            END;
            $$;
 8   DROP FUNCTION public.update_user_status_from_session();
       public               postgres    false    5            '           1255    35519 W   validate_plugin_status_transition(public.plugin_status_enum, public.plugin_status_enum)    FUNCTION     �  CREATE FUNCTION public.validate_plugin_status_transition(old_status public.plugin_status_enum, new_status public.plugin_status_enum) RETURNS boolean
    LANGUAGE plpgsql IMMUTABLE
    AS $$
            BEGIN
                RETURN CASE
                    WHEN old_status = 'not_installed' AND new_status IN ('pending_activation', 'active', 'error') THEN TRUE
                    WHEN old_status = 'pending_activation' AND new_status IN ('active', 'error', 'not_installed') THEN TRUE
                    WHEN old_status = 'active' AND new_status IN ('inactive', 'pending_deactivation', 'suspended', 'error') THEN TRUE
                    WHEN old_status = 'inactive' AND new_status IN ('active', 'pending_activation', 'suspended', 'not_installed', 'error') THEN TRUE
                    WHEN old_status = 'pending_deactivation' AND new_status IN ('inactive', 'not_installed', 'error') THEN TRUE
                    WHEN old_status = 'suspended' AND new_status IN ('active', 'inactive', 'not_installed', 'error') THEN TRUE
                    WHEN old_status = 'error' THEN TRUE
                    WHEN old_status = new_status THEN TRUE
                    ELSE FALSE
                END;
            END;
            $$;
 �   DROP FUNCTION public.validate_plugin_status_transition(old_status public.plugin_status_enum, new_status public.plugin_status_enum);
       public               postgres    false    5    902            �           0    0 v   FUNCTION validate_plugin_status_transition(old_status public.plugin_status_enum, new_status public.plugin_status_enum)    COMMENT     �   COMMENT ON FUNCTION public.validate_plugin_status_transition(old_status public.plugin_status_enum, new_status public.plugin_status_enum) IS 'Validates whether a plugin status transition is allowed based on business rules';
          public               postgres    false    295            �            1259    35777    api_requests    TABLE     �   CREATE TABLE public.api_requests (
    id bigint NOT NULL,
    ip_address character varying(45) NOT NULL,
    endpoint character varying(255),
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);
     DROP TABLE public.api_requests;
       public         heap r       postgres    false    5            �            1259    35776    api_requests_id_seq    SEQUENCE     |   CREATE SEQUENCE public.api_requests_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 *   DROP SEQUENCE public.api_requests_id_seq;
       public               postgres    false    5    243            �           0    0    api_requests_id_seq    SEQUENCE OWNED BY     K   ALTER SEQUENCE public.api_requests_id_seq OWNED BY public.api_requests.id;
          public               postgres    false    242            �            1259    35520    cache    TABLE     o   CREATE TABLE public.cache (
    key text NOT NULL,
    value text NOT NULL,
    expiration integer NOT NULL
);
    DROP TABLE public.cache;
       public         heap r       postgres    false    5            �            1259    35525    content    TABLE     �  CREATE TABLE public.content (
    id character varying(255) NOT NULL,
    domain_id bigint NOT NULL,
    user_id bigint,
    content_element text NOT NULL,
    context text,
    url text,
    video_url text,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    text text,
    normalized_text text GENERATED ALWAYS AS (lower(TRIM(BOTH FROM regexp_replace(text, '\s+'::text, ' '::text, 'g'::text)))) STORED,
    page_name character varying(500)
);
    DROP TABLE public.content;
       public         heap r       postgres    false    5            �           0    0    COLUMN content.text    COMMENT     V   COMMENT ON COLUMN public.content.text IS 'Actual text content from fingerprint scan';
          public               postgres    false    211            �            1259    35531    domain_sessions    TABLE     �   CREATE TABLE public.domain_sessions (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);
 #   DROP TABLE public.domain_sessions;
       public         heap r       postgres    false    5            �            1259    35534    domain_sessions_id_seq    SEQUENCE        CREATE SEQUENCE public.domain_sessions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 -   DROP SEQUENCE public.domain_sessions_id_seq;
       public               postgres    false    5    212            �           0    0    domain_sessions_id_seq    SEQUENCE OWNED BY     Q   ALTER SEQUENCE public.domain_sessions_id_seq OWNED BY public.domain_sessions.id;
          public               postgres    false    213            �            1259    35535    domains    TABLE     �  CREATE TABLE public.domains (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    domain text NOT NULL,
    platform text NOT NULL,
    plugin_status text NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    api_key character varying(255),
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    is_active boolean DEFAULT false NOT NULL,
    is_verified boolean DEFAULT false NOT NULL,
    verification_token character varying(32),
    last_checked_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    subscription_expires_at timestamp(0) without time zone
);
    DROP TABLE public.domains;
       public         heap r       postgres    false    5            �            1259    35544    domains_id_seq    SEQUENCE     w   CREATE SEQUENCE public.domains_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 %   DROP SEQUENCE public.domains_id_seq;
       public               postgres    false    214    5            �           0    0    domains_id_seq    SEQUENCE OWNED BY     A   ALTER SEQUENCE public.domains_id_seq OWNED BY public.domains.id;
          public               postgres    false    215            �            1259    35545    failed_jobs    TABLE     �   CREATE TABLE public.failed_jobs (
    id bigint NOT NULL,
    connection text NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    exception text NOT NULL,
    failed_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
    DROP TABLE public.failed_jobs;
       public         heap r       postgres    false    5            �            1259    35551    failed_jobs_id_seq    SEQUENCE     {   CREATE SEQUENCE public.failed_jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 )   DROP SEQUENCE public.failed_jobs_id_seq;
       public               postgres    false    216    5            �           0    0    failed_jobs_id_seq    SEQUENCE OWNED BY     I   ALTER SEQUENCE public.failed_jobs_id_seq OWNED BY public.failed_jobs.id;
          public               postgres    false    217            �            1259    35958    invoices    TABLE     I  CREATE TABLE public.invoices (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    invoice_number character varying(255) NOT NULL,
    status character varying(255) DEFAULT 'draft'::character varying NOT NULL,
    total numeric(10,2) NOT NULL,
    balance numeric(10,2) DEFAULT '0'::numeric NOT NULL,
    issued_date date NOT NULL,
    due_date date NOT NULL,
    service character varying(255),
    description text,
    client_details json,
    payment_details json,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    CONSTRAINT invoices_status_check CHECK (((status)::text = ANY ((ARRAY['draft'::character varying, 'sent'::character varying, 'paid'::character varying, 'partial_payment'::character varying, 'past_due'::character varying, 'downloaded'::character varying])::text[])))
);
    DROP TABLE public.invoices;
       public         heap r       postgres    false    5            �            1259    35957    invoices_id_seq    SEQUENCE     x   CREATE SEQUENCE public.invoices_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 &   DROP SEQUENCE public.invoices_id_seq;
       public               postgres    false    5    247            �           0    0    invoices_id_seq    SEQUENCE OWNED BY     C   ALTER SEQUENCE public.invoices_id_seq OWNED BY public.invoices.id;
          public               postgres    false    246            �            1259    35552    jobs    TABLE     !  CREATE TABLE public.jobs (
    id bigint NOT NULL,
    queue text NOT NULL,
    payload text NOT NULL,
    attempts integer NOT NULL,
    reserved_at timestamp(0) with time zone,
    available_at timestamp(0) with time zone NOT NULL,
    created_at timestamp(0) with time zone NOT NULL
);
    DROP TABLE public.jobs;
       public         heap r       postgres    false    5            �            1259    35557    jobs_id_seq    SEQUENCE     t   CREATE SEQUENCE public.jobs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 "   DROP SEQUENCE public.jobs_id_seq;
       public               postgres    false    218    5            �           0    0    jobs_id_seq    SEQUENCE OWNED BY     ;   ALTER SEQUENCE public.jobs_id_seq OWNED BY public.jobs.id;
          public               postgres    false    219            �            1259    35558    licenses    TABLE     &  CREATE TABLE public.licenses (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    license_key text NOT NULL,
    status text NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp(0) with time zone,
    subscription_id bigint
);
    DROP TABLE public.licenses;
       public         heap r       postgres    false    5            �            1259    35564    licenses_id_seq    SEQUENCE     x   CREATE SEQUENCE public.licenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 &   DROP SEQUENCE public.licenses_id_seq;
       public               postgres    false    220    5            �           0    0    licenses_id_seq    SEQUENCE OWNED BY     C   ALTER SEQUENCE public.licenses_id_seq OWNED BY public.licenses.id;
          public               postgres    false    221            �            1259    35565 
   migrations    TABLE     �   CREATE TABLE public.migrations (
    id integer NOT NULL,
    migration character varying(255) NOT NULL,
    batch integer NOT NULL
);
    DROP TABLE public.migrations;
       public         heap r       postgres    false    5            �            1259    35568    migrations_id_seq    SEQUENCE     �   CREATE SEQUENCE public.migrations_id_seq
    AS integer
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 (   DROP SEQUENCE public.migrations_id_seq;
       public               postgres    false    222    5            �           0    0    migrations_id_seq    SEQUENCE OWNED BY     G   ALTER SEQUENCE public.migrations_id_seq OWNED BY public.migrations.id;
          public               postgres    false    223            �            1259    35569    password_resets    TABLE     �   CREATE TABLE public.password_resets (
    email text NOT NULL,
    token text NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
 #   DROP TABLE public.password_resets;
       public         heap r       postgres    false    5            �            1259    35575    payments    TABLE     �   CREATE TABLE public.payments (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    amount numeric(10,2) NOT NULL,
    payment_date timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    status text NOT NULL
);
    DROP TABLE public.payments;
       public         heap r       postgres    false    5            �            1259    35581    payments_id_seq    SEQUENCE     x   CREATE SEQUENCE public.payments_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 &   DROP SEQUENCE public.payments_id_seq;
       public               postgres    false    5    225            �           0    0    payments_id_seq    SEQUENCE OWNED BY     C   ALTER SEQUENCE public.payments_id_seq OWNED BY public.payments.id;
          public               postgres    false    226            �            1259    35582    personal_access_tokens    TABLE     �  CREATE TABLE public.personal_access_tokens (
    id bigint NOT NULL,
    tokenable_type character varying(255) NOT NULL,
    tokenable_id bigint NOT NULL,
    name character varying(255) NOT NULL,
    token character varying(64) NOT NULL,
    abilities text,
    last_used_at timestamp(0) without time zone,
    expires_at timestamp(0) without time zone,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);
 *   DROP TABLE public.personal_access_tokens;
       public         heap r       postgres    false    5            �            1259    35587    personal_access_tokens_id_seq    SEQUENCE     �   CREATE SEQUENCE public.personal_access_tokens_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 4   DROP SEQUENCE public.personal_access_tokens_id_seq;
       public               postgres    false    227    5            �           0    0    personal_access_tokens_id_seq    SEQUENCE OWNED BY     _   ALTER SEQUENCE public.personal_access_tokens_id_seq OWNED BY public.personal_access_tokens.id;
          public               postgres    false    228            �            1259    35588    plans    TABLE        CREATE TABLE public.plans (
    id bigint NOT NULL,
    name text NOT NULL,
    price numeric(10,2) NOT NULL,
    duration character varying(255) NOT NULL,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    features json
);
    DROP TABLE public.plans;
       public         heap r       postgres    false    5            �            1259    35594    plans_id_seq    SEQUENCE     u   CREATE SEQUENCE public.plans_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 #   DROP SEQUENCE public.plans_id_seq;
       public               postgres    false    229    5            �           0    0    plans_id_seq    SEQUENCE OWNED BY     =   ALTER SEQUENCE public.plans_id_seq OWNED BY public.plans.id;
          public               postgres    false    230            �            1259    35595    plugin_licenses    TABLE     �   CREATE TABLE public.plugin_licenses (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);
 #   DROP TABLE public.plugin_licenses;
       public         heap r       postgres    false    5            �            1259    35598    plugin_licenses_id_seq    SEQUENCE        CREATE SEQUENCE public.plugin_licenses_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 -   DROP SEQUENCE public.plugin_licenses_id_seq;
       public               postgres    false    231    5            �           0    0    plugin_licenses_id_seq    SEQUENCE OWNED BY     Q   ALTER SEQUENCE public.plugin_licenses_id_seq OWNED BY public.plugin_licenses.id;
          public               postgres    false    232            �            1259    35785    plugin_logs    TABLE     �   CREATE TABLE public.plugin_logs (
    id bigint NOT NULL,
    domain character varying(255) NOT NULL,
    event_type character varying(50) NOT NULL,
    message text,
    data jsonb,
    created_at timestamp with time zone DEFAULT CURRENT_TIMESTAMP
);
    DROP TABLE public.plugin_logs;
       public         heap r       postgres    false    5            �            1259    35784    plugin_logs_id_seq    SEQUENCE     {   CREATE SEQUENCE public.plugin_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 )   DROP SEQUENCE public.plugin_logs_id_seq;
       public               postgres    false    5    245            �           0    0    plugin_logs_id_seq    SEQUENCE OWNED BY     I   ALTER SEQUENCE public.plugin_logs_id_seq OWNED BY public.plugin_logs.id;
          public               postgres    false    244            �            1259    35599    plugin_status_logs    TABLE     �  CREATE TABLE public.plugin_status_logs (
    id bigint NOT NULL,
    domain_id bigint NOT NULL,
    old_status public.plugin_status_enum,
    new_status public.plugin_status_enum NOT NULL,
    changed_by bigint,
    change_reason text,
    created_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) without time zone DEFAULT CURRENT_TIMESTAMP NOT NULL
);
 &   DROP TABLE public.plugin_status_logs;
       public         heap r       postgres    false    902    5    902            �           0    0    TABLE plugin_status_logs    COMMENT     U   COMMENT ON TABLE public.plugin_status_logs IS 'Audit log for plugin status changes';
          public               postgres    false    233            �           0    0 #   COLUMN plugin_status_logs.domain_id    COMMENT     Y   COMMENT ON COLUMN public.plugin_status_logs.domain_id IS 'Foreign key to domains table';
          public               postgres    false    233            �           0    0 $   COLUMN plugin_status_logs.old_status    COMMENT     o   COMMENT ON COLUMN public.plugin_status_logs.old_status IS 'Previous plugin status (null for initial records)';
          public               postgres    false    233            �           0    0 $   COLUMN plugin_status_logs.new_status    COMMENT     O   COMMENT ON COLUMN public.plugin_status_logs.new_status IS 'New plugin status';
          public               postgres    false    233            �           0    0 $   COLUMN plugin_status_logs.changed_by    COMMENT     p   COMMENT ON COLUMN public.plugin_status_logs.changed_by IS 'User who made the change (null for system changes)';
          public               postgres    false    233            �           0    0 '   COLUMN plugin_status_logs.change_reason    COMMENT     f   COMMENT ON COLUMN public.plugin_status_logs.change_reason IS 'Optional reason for the status change';
          public               postgres    false    233            �            1259    35606    plugin_status_logs_id_seq    SEQUENCE     �   CREATE SEQUENCE public.plugin_status_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 0   DROP SEQUENCE public.plugin_status_logs_id_seq;
       public               postgres    false    233    5            �           0    0    plugin_status_logs_id_seq    SEQUENCE OWNED BY     W   ALTER SEQUENCE public.plugin_status_logs_id_seq OWNED BY public.plugin_status_logs.id;
          public               postgres    false    234            �            1259    35607    plugin_verification_logs    TABLE     �   CREATE TABLE public.plugin_verification_logs (
    id bigint NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone
);
 ,   DROP TABLE public.plugin_verification_logs;
       public         heap r       postgres    false    5            �            1259    35610    plugin_verification_logs_id_seq    SEQUENCE     �   CREATE SEQUENCE public.plugin_verification_logs_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 6   DROP SEQUENCE public.plugin_verification_logs_id_seq;
       public               postgres    false    235    5            �           0    0    plugin_verification_logs_id_seq    SEQUENCE OWNED BY     c   ALTER SEQUENCE public.plugin_verification_logs_id_seq OWNED BY public.plugin_verification_logs.id;
          public               postgres    false    236            �            1259    35611    sessions    TABLE     �  CREATE TABLE public.sessions (
    id character varying(255) NOT NULL,
    user_id bigint,
    ip_address character varying(45),
    user_agent text,
    payload text NOT NULL,
    last_activity integer NOT NULL,
    device_info json,
    expires_at timestamp(0) without time zone,
    is_active boolean DEFAULT true NOT NULL,
    created_at timestamp(0) without time zone,
    updated_at timestamp(0) without time zone,
    fingerprint character varying(255)
);
    DROP TABLE public.sessions;
       public         heap r       postgres    false    5            �            1259    35617    subscriptions    TABLE       CREATE TABLE public.subscriptions (
    id bigint NOT NULL,
    user_id bigint NOT NULL,
    plan_id bigint NOT NULL,
    status text NOT NULL,
    started_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    expires_at timestamp(0) with time zone
);
 !   DROP TABLE public.subscriptions;
       public         heap r       postgres    false    5            �            1259    35623    subscriptions_id_seq    SEQUENCE     }   CREATE SEQUENCE public.subscriptions_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 +   DROP SEQUENCE public.subscriptions_id_seq;
       public               postgres    false    5    238            �           0    0    subscriptions_id_seq    SEQUENCE OWNED BY     M   ALTER SEQUENCE public.subscriptions_id_seq OWNED BY public.subscriptions.id;
          public               postgres    false    239            �            1259    35624    users    TABLE     �  CREATE TABLE public.users (
    id bigint NOT NULL,
    name character varying(255) NOT NULL,
    email text NOT NULL,
    password text NOT NULL,
    role text NOT NULL,
    email_verified_at timestamp(0) without time zone,
    created_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    updated_at timestamp(0) with time zone DEFAULT CURRENT_TIMESTAMP NOT NULL,
    remember_token character varying(100),
    status character varying(255) DEFAULT 'active'::character varying NOT NULL,
    deleted_at timestamp(0) without time zone,
    avatar_url character varying(255),
    domain_id bigint,
    is_suspended boolean DEFAULT false NOT NULL
);
    DROP TABLE public.users;
       public         heap r       postgres    false    5            �            1259    35632    users_id_seq    SEQUENCE     u   CREATE SEQUENCE public.users_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
 #   DROP SEQUENCE public.users_id_seq;
       public               postgres    false    240    5            �           0    0    users_id_seq    SEQUENCE OWNED BY     =   ALTER SEQUENCE public.users_id_seq OWNED BY public.users.id;
          public               postgres    false    241            �           2604    35780    api_requests id    DEFAULT     r   ALTER TABLE ONLY public.api_requests ALTER COLUMN id SET DEFAULT nextval('public.api_requests_id_seq'::regclass);
 >   ALTER TABLE public.api_requests ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    242    243    243            f           2604    35633    domain_sessions id    DEFAULT     x   ALTER TABLE ONLY public.domain_sessions ALTER COLUMN id SET DEFAULT nextval('public.domain_sessions_id_seq'::regclass);
 A   ALTER TABLE public.domain_sessions ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    213    212            g           2604    35634 
   domains id    DEFAULT     h   ALTER TABLE ONLY public.domains ALTER COLUMN id SET DEFAULT nextval('public.domains_id_seq'::regclass);
 9   ALTER TABLE public.domains ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    215    214            l           2604    35635    failed_jobs id    DEFAULT     p   ALTER TABLE ONLY public.failed_jobs ALTER COLUMN id SET DEFAULT nextval('public.failed_jobs_id_seq'::regclass);
 =   ALTER TABLE public.failed_jobs ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    217    216            �           2604    35961    invoices id    DEFAULT     j   ALTER TABLE ONLY public.invoices ALTER COLUMN id SET DEFAULT nextval('public.invoices_id_seq'::regclass);
 :   ALTER TABLE public.invoices ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    246    247    247            n           2604    35636    jobs id    DEFAULT     b   ALTER TABLE ONLY public.jobs ALTER COLUMN id SET DEFAULT nextval('public.jobs_id_seq'::regclass);
 6   ALTER TABLE public.jobs ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    219    218            o           2604    35637    licenses id    DEFAULT     j   ALTER TABLE ONLY public.licenses ALTER COLUMN id SET DEFAULT nextval('public.licenses_id_seq'::regclass);
 :   ALTER TABLE public.licenses ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    221    220            q           2604    35638    migrations id    DEFAULT     n   ALTER TABLE ONLY public.migrations ALTER COLUMN id SET DEFAULT nextval('public.migrations_id_seq'::regclass);
 <   ALTER TABLE public.migrations ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    223    222            s           2604    35639    payments id    DEFAULT     j   ALTER TABLE ONLY public.payments ALTER COLUMN id SET DEFAULT nextval('public.payments_id_seq'::regclass);
 :   ALTER TABLE public.payments ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    226    225            u           2604    35640    personal_access_tokens id    DEFAULT     �   ALTER TABLE ONLY public.personal_access_tokens ALTER COLUMN id SET DEFAULT nextval('public.personal_access_tokens_id_seq'::regclass);
 H   ALTER TABLE public.personal_access_tokens ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    228    227            v           2604    35641    plans id    DEFAULT     d   ALTER TABLE ONLY public.plans ALTER COLUMN id SET DEFAULT nextval('public.plans_id_seq'::regclass);
 7   ALTER TABLE public.plans ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    230    229            x           2604    35642    plugin_licenses id    DEFAULT     x   ALTER TABLE ONLY public.plugin_licenses ALTER COLUMN id SET DEFAULT nextval('public.plugin_licenses_id_seq'::regclass);
 A   ALTER TABLE public.plugin_licenses ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    232    231            �           2604    35788    plugin_logs id    DEFAULT     p   ALTER TABLE ONLY public.plugin_logs ALTER COLUMN id SET DEFAULT nextval('public.plugin_logs_id_seq'::regclass);
 =   ALTER TABLE public.plugin_logs ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    244    245    245            y           2604    35643    plugin_status_logs id    DEFAULT     ~   ALTER TABLE ONLY public.plugin_status_logs ALTER COLUMN id SET DEFAULT nextval('public.plugin_status_logs_id_seq'::regclass);
 D   ALTER TABLE public.plugin_status_logs ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    234    233            |           2604    35644    plugin_verification_logs id    DEFAULT     �   ALTER TABLE ONLY public.plugin_verification_logs ALTER COLUMN id SET DEFAULT nextval('public.plugin_verification_logs_id_seq'::regclass);
 J   ALTER TABLE public.plugin_verification_logs ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    236    235            ~           2604    35645    subscriptions id    DEFAULT     t   ALTER TABLE ONLY public.subscriptions ALTER COLUMN id SET DEFAULT nextval('public.subscriptions_id_seq'::regclass);
 ?   ALTER TABLE public.subscriptions ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    239    238            �           2604    35646    users id    DEFAULT     d   ALTER TABLE ONLY public.users ALTER COLUMN id SET DEFAULT nextval('public.users_id_seq'::regclass);
 7   ALTER TABLE public.users ALTER COLUMN id DROP DEFAULT;
       public               postgres    false    241    240            �          0    35777    api_requests 
   TABLE DATA           L   COPY public.api_requests (id, ip_address, endpoint, created_at) FROM stdin;
    public               postgres    false    243   �/      }          0    35520    cache 
   TABLE DATA           7   COPY public.cache (key, value, expiration) FROM stdin;
    public               postgres    false    210   :0      ~          0    35525    content 
   TABLE DATA           �   COPY public.content (id, domain_id, user_id, content_element, context, url, video_url, created_at, text, page_name) FROM stdin;
    public               postgres    false    211   W0                0    35531    domain_sessions 
   TABLE DATA           E   COPY public.domain_sessions (id, created_at, updated_at) FROM stdin;
    public               postgres    false    212   �>      �          0    35535    domains 
   TABLE DATA           �   COPY public.domains (id, user_id, domain, platform, plugin_status, created_at, api_key, status, is_active, is_verified, verification_token, last_checked_at, updated_at, subscription_expires_at) FROM stdin;
    public               postgres    false    214   �>      �          0    35545    failed_jobs 
   TABLE DATA           [   COPY public.failed_jobs (id, connection, queue, payload, exception, failed_at) FROM stdin;
    public               postgres    false    216   ~?      �          0    35958    invoices 
   TABLE DATA           �   COPY public.invoices (id, user_id, invoice_number, status, total, balance, issued_date, due_date, service, description, client_details, payment_details, created_at, updated_at) FROM stdin;
    public               postgres    false    247   �?      �          0    35552    jobs 
   TABLE DATA           c   COPY public.jobs (id, queue, payload, attempts, reserved_at, available_at, created_at) FROM stdin;
    public               postgres    false    218   �@      �          0    35558    licenses 
   TABLE DATA           m   COPY public.licenses (id, user_id, license_key, status, created_at, expires_at, subscription_id) FROM stdin;
    public               postgres    false    220   �@      �          0    35565 
   migrations 
   TABLE DATA           :   COPY public.migrations (id, migration, batch) FROM stdin;
    public               postgres    false    222   �@      �          0    35569    password_resets 
   TABLE DATA           C   COPY public.password_resets (email, token, created_at) FROM stdin;
    public               postgres    false    224   �D      �          0    35575    payments 
   TABLE DATA           M   COPY public.payments (id, user_id, amount, payment_date, status) FROM stdin;
    public               postgres    false    225   �D      �          0    35582    personal_access_tokens 
   TABLE DATA           �   COPY public.personal_access_tokens (id, tokenable_type, tokenable_id, name, token, abilities, last_used_at, expires_at, created_at, updated_at) FROM stdin;
    public               postgres    false    227   �D      �          0    35588    plans 
   TABLE DATA           P   COPY public.plans (id, name, price, duration, created_at, features) FROM stdin;
    public               postgres    false    229   pF      �          0    35595    plugin_licenses 
   TABLE DATA           E   COPY public.plugin_licenses (id, created_at, updated_at) FROM stdin;
    public               postgres    false    231   �F      �          0    35785    plugin_logs 
   TABLE DATA           X   COPY public.plugin_logs (id, domain, event_type, message, data, created_at) FROM stdin;
    public               postgres    false    245   �F      �          0    35599    plugin_status_logs 
   TABLE DATA           �   COPY public.plugin_status_logs (id, domain_id, old_status, new_status, changed_by, change_reason, created_at, updated_at) FROM stdin;
    public               postgres    false    233   �F      �          0    35607    plugin_verification_logs 
   TABLE DATA           N   COPY public.plugin_verification_logs (id, created_at, updated_at) FROM stdin;
    public               postgres    false    235   {H      �          0    35611    sessions 
   TABLE DATA           �   COPY public.sessions (id, user_id, ip_address, user_agent, payload, last_activity, device_info, expires_at, is_active, created_at, updated_at, fingerprint) FROM stdin;
    public               postgres    false    237   �H      �          0    35617    subscriptions 
   TABLE DATA           ]   COPY public.subscriptions (id, user_id, plan_id, status, started_at, expires_at) FROM stdin;
    public               postgres    false    238   ��      �          0    35624    users 
   TABLE DATA           �   COPY public.users (id, name, email, password, role, email_verified_at, created_at, updated_at, remember_token, status, deleted_at, avatar_url, domain_id, is_suspended) FROM stdin;
    public               postgres    false    240   ��      �           0    0    api_requests_id_seq    SEQUENCE SET     B   SELECT pg_catalog.setval('public.api_requests_id_seq', 16, true);
          public               postgres    false    242            �           0    0    domain_sessions_id_seq    SEQUENCE SET     E   SELECT pg_catalog.setval('public.domain_sessions_id_seq', 1, false);
          public               postgres    false    213            �           0    0    domains_id_seq    SEQUENCE SET     =   SELECT pg_catalog.setval('public.domains_id_seq', 64, true);
          public               postgres    false    215            �           0    0    failed_jobs_id_seq    SEQUENCE SET     A   SELECT pg_catalog.setval('public.failed_jobs_id_seq', 1, false);
          public               postgres    false    217            �           0    0    invoices_id_seq    SEQUENCE SET     >   SELECT pg_catalog.setval('public.invoices_id_seq', 16, true);
          public               postgres    false    246            �           0    0    jobs_id_seq    SEQUENCE SET     :   SELECT pg_catalog.setval('public.jobs_id_seq', 1, false);
          public               postgres    false    219            �           0    0    licenses_id_seq    SEQUENCE SET     >   SELECT pg_catalog.setval('public.licenses_id_seq', 1, false);
          public               postgres    false    221            �           0    0    migrations_id_seq    SEQUENCE SET     @   SELECT pg_catalog.setval('public.migrations_id_seq', 53, true);
          public               postgres    false    223            �           0    0    payments_id_seq    SEQUENCE SET     >   SELECT pg_catalog.setval('public.payments_id_seq', 1, false);
          public               postgres    false    226            �           0    0    personal_access_tokens_id_seq    SEQUENCE SET     L   SELECT pg_catalog.setval('public.personal_access_tokens_id_seq', 36, true);
          public               postgres    false    228            �           0    0    plans_id_seq    SEQUENCE SET     ;   SELECT pg_catalog.setval('public.plans_id_seq', 1, false);
          public               postgres    false    230            �           0    0    plugin_licenses_id_seq    SEQUENCE SET     E   SELECT pg_catalog.setval('public.plugin_licenses_id_seq', 1, false);
          public               postgres    false    232            �           0    0    plugin_logs_id_seq    SEQUENCE SET     A   SELECT pg_catalog.setval('public.plugin_logs_id_seq', 1, false);
          public               postgres    false    244            �           0    0    plugin_status_logs_id_seq    SEQUENCE SET     I   SELECT pg_catalog.setval('public.plugin_status_logs_id_seq', 176, true);
          public               postgres    false    234            �           0    0    plugin_verification_logs_id_seq    SEQUENCE SET     N   SELECT pg_catalog.setval('public.plugin_verification_logs_id_seq', 1, false);
          public               postgres    false    236            �           0    0    subscriptions_id_seq    SEQUENCE SET     C   SELECT pg_catalog.setval('public.subscriptions_id_seq', 1, false);
          public               postgres    false    239            �           0    0    users_id_seq    SEQUENCE SET     ;   SELECT pg_catalog.setval('public.users_id_seq', 23, true);
          public               postgres    false    241            �           2606    35783    api_requests api_requests_pkey 
   CONSTRAINT     \   ALTER TABLE ONLY public.api_requests
    ADD CONSTRAINT api_requests_pkey PRIMARY KEY (id);
 H   ALTER TABLE ONLY public.api_requests DROP CONSTRAINT api_requests_pkey;
       public                 postgres    false    243            �           2606    35648    cache cache_pkey 
   CONSTRAINT     O   ALTER TABLE ONLY public.cache
    ADD CONSTRAINT cache_pkey PRIMARY KEY (key);
 :   ALTER TABLE ONLY public.cache DROP CONSTRAINT cache_pkey;
       public                 postgres    false    210            �           2606    35816 -   content content_domain_normalized_text_unique 
   CONSTRAINT     ~   ALTER TABLE ONLY public.content
    ADD CONSTRAINT content_domain_normalized_text_unique UNIQUE (domain_id, normalized_text);
 W   ALTER TABLE ONLY public.content DROP CONSTRAINT content_domain_normalized_text_unique;
       public                 postgres    false    211    211            �           2606    35650    content content_pkey 
   CONSTRAINT     R   ALTER TABLE ONLY public.content
    ADD CONSTRAINT content_pkey PRIMARY KEY (id);
 >   ALTER TABLE ONLY public.content DROP CONSTRAINT content_pkey;
       public                 postgres    false    211            �           2606    35652 $   domain_sessions domain_sessions_pkey 
   CONSTRAINT     b   ALTER TABLE ONLY public.domain_sessions
    ADD CONSTRAINT domain_sessions_pkey PRIMARY KEY (id);
 N   ALTER TABLE ONLY public.domain_sessions DROP CONSTRAINT domain_sessions_pkey;
       public                 postgres    false    212            �           2606    35654 "   domains domains_domain_name_unique 
   CONSTRAINT     _   ALTER TABLE ONLY public.domains
    ADD CONSTRAINT domains_domain_name_unique UNIQUE (domain);
 L   ALTER TABLE ONLY public.domains DROP CONSTRAINT domains_domain_name_unique;
       public                 postgres    false    214            �           2606    35656    domains domains_pkey 
   CONSTRAINT     R   ALTER TABLE ONLY public.domains
    ADD CONSTRAINT domains_pkey PRIMARY KEY (id);
 >   ALTER TABLE ONLY public.domains DROP CONSTRAINT domains_pkey;
       public                 postgres    false    214            �           2606    35658    failed_jobs failed_jobs_pkey 
   CONSTRAINT     Z   ALTER TABLE ONLY public.failed_jobs
    ADD CONSTRAINT failed_jobs_pkey PRIMARY KEY (id);
 F   ALTER TABLE ONLY public.failed_jobs DROP CONSTRAINT failed_jobs_pkey;
       public                 postgres    false    216            �           2606    35978 '   invoices invoices_invoice_number_unique 
   CONSTRAINT     l   ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_invoice_number_unique UNIQUE (invoice_number);
 Q   ALTER TABLE ONLY public.invoices DROP CONSTRAINT invoices_invoice_number_unique;
       public                 postgres    false    247            �           2606    35968    invoices invoices_pkey 
   CONSTRAINT     T   ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_pkey PRIMARY KEY (id);
 @   ALTER TABLE ONLY public.invoices DROP CONSTRAINT invoices_pkey;
       public                 postgres    false    247            �           2606    35660    jobs jobs_pkey 
   CONSTRAINT     L   ALTER TABLE ONLY public.jobs
    ADD CONSTRAINT jobs_pkey PRIMARY KEY (id);
 8   ALTER TABLE ONLY public.jobs DROP CONSTRAINT jobs_pkey;
       public                 postgres    false    218            �           2606    35662 $   licenses licenses_license_key_unique 
   CONSTRAINT     f   ALTER TABLE ONLY public.licenses
    ADD CONSTRAINT licenses_license_key_unique UNIQUE (license_key);
 N   ALTER TABLE ONLY public.licenses DROP CONSTRAINT licenses_license_key_unique;
       public                 postgres    false    220            �           2606    35664    licenses licenses_pkey 
   CONSTRAINT     T   ALTER TABLE ONLY public.licenses
    ADD CONSTRAINT licenses_pkey PRIMARY KEY (id);
 @   ALTER TABLE ONLY public.licenses DROP CONSTRAINT licenses_pkey;
       public                 postgres    false    220            �           2606    35666    migrations migrations_pkey 
   CONSTRAINT     X   ALTER TABLE ONLY public.migrations
    ADD CONSTRAINT migrations_pkey PRIMARY KEY (id);
 D   ALTER TABLE ONLY public.migrations DROP CONSTRAINT migrations_pkey;
       public                 postgres    false    222            �           2606    35668    payments payments_pkey 
   CONSTRAINT     T   ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_pkey PRIMARY KEY (id);
 @   ALTER TABLE ONLY public.payments DROP CONSTRAINT payments_pkey;
       public                 postgres    false    225            �           2606    35670 2   personal_access_tokens personal_access_tokens_pkey 
   CONSTRAINT     p   ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_pkey PRIMARY KEY (id);
 \   ALTER TABLE ONLY public.personal_access_tokens DROP CONSTRAINT personal_access_tokens_pkey;
       public                 postgres    false    227            �           2606    35672 :   personal_access_tokens personal_access_tokens_token_unique 
   CONSTRAINT     v   ALTER TABLE ONLY public.personal_access_tokens
    ADD CONSTRAINT personal_access_tokens_token_unique UNIQUE (token);
 d   ALTER TABLE ONLY public.personal_access_tokens DROP CONSTRAINT personal_access_tokens_token_unique;
       public                 postgres    false    227            �           2606    35674    plans plans_pkey 
   CONSTRAINT     N   ALTER TABLE ONLY public.plans
    ADD CONSTRAINT plans_pkey PRIMARY KEY (id);
 :   ALTER TABLE ONLY public.plans DROP CONSTRAINT plans_pkey;
       public                 postgres    false    229            �           2606    35676 $   plugin_licenses plugin_licenses_pkey 
   CONSTRAINT     b   ALTER TABLE ONLY public.plugin_licenses
    ADD CONSTRAINT plugin_licenses_pkey PRIMARY KEY (id);
 N   ALTER TABLE ONLY public.plugin_licenses DROP CONSTRAINT plugin_licenses_pkey;
       public                 postgres    false    231            �           2606    35793    plugin_logs plugin_logs_pkey 
   CONSTRAINT     Z   ALTER TABLE ONLY public.plugin_logs
    ADD CONSTRAINT plugin_logs_pkey PRIMARY KEY (id);
 F   ALTER TABLE ONLY public.plugin_logs DROP CONSTRAINT plugin_logs_pkey;
       public                 postgres    false    245            �           2606    35678 *   plugin_status_logs plugin_status_logs_pkey 
   CONSTRAINT     h   ALTER TABLE ONLY public.plugin_status_logs
    ADD CONSTRAINT plugin_status_logs_pkey PRIMARY KEY (id);
 T   ALTER TABLE ONLY public.plugin_status_logs DROP CONSTRAINT plugin_status_logs_pkey;
       public                 postgres    false    233            �           2606    35680 6   plugin_verification_logs plugin_verification_logs_pkey 
   CONSTRAINT     t   ALTER TABLE ONLY public.plugin_verification_logs
    ADD CONSTRAINT plugin_verification_logs_pkey PRIMARY KEY (id);
 `   ALTER TABLE ONLY public.plugin_verification_logs DROP CONSTRAINT plugin_verification_logs_pkey;
       public                 postgres    false    235            �           2606    35682    sessions sessions_pkey 
   CONSTRAINT     T   ALTER TABLE ONLY public.sessions
    ADD CONSTRAINT sessions_pkey PRIMARY KEY (id);
 @   ALTER TABLE ONLY public.sessions DROP CONSTRAINT sessions_pkey;
       public                 postgres    false    237            �           2606    35684     subscriptions subscriptions_pkey 
   CONSTRAINT     ^   ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_pkey PRIMARY KEY (id);
 J   ALTER TABLE ONLY public.subscriptions DROP CONSTRAINT subscriptions_pkey;
       public                 postgres    false    238            �           2606    35686    users users_email_unique 
   CONSTRAINT     T   ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_email_unique UNIQUE (email);
 B   ALTER TABLE ONLY public.users DROP CONSTRAINT users_email_unique;
       public                 postgres    false    240            �           2606    35688    users users_pkey 
   CONSTRAINT     N   ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_pkey PRIMARY KEY (id);
 :   ALTER TABLE ONLY public.users DROP CONSTRAINT users_pkey;
       public                 postgres    false    240            �           1259    35823    content_domain_page_index    INDEX     ]   CREATE INDEX content_domain_page_index ON public.content USING btree (domain_id, page_name);
 -   DROP INDEX public.content_domain_page_index;
       public                 postgres    false    211    211            �           1259    35817    content_normalized_text_index    INDEX     \   CREATE INDEX content_normalized_text_index ON public.content USING btree (normalized_text);
 1   DROP INDEX public.content_normalized_text_index;
       public                 postgres    false    211            �           1259    35822    content_page_name_index    INDEX     P   CREATE INDEX content_page_name_index ON public.content USING btree (page_name);
 +   DROP INDEX public.content_page_name_index;
       public                 postgres    false    211            �           1259    35800    idx_api_requests_created_at    INDEX     Z   CREATE INDEX idx_api_requests_created_at ON public.api_requests USING btree (created_at);
 /   DROP INDEX public.idx_api_requests_created_at;
       public                 postgres    false    243            �           1259    35799    idx_api_requests_ip    INDEX     R   CREATE INDEX idx_api_requests_ip ON public.api_requests USING btree (ip_address);
 '   DROP INDEX public.idx_api_requests_ip;
       public                 postgres    false    243            �           1259    35794    idx_domains_domain    INDEX     H   CREATE INDEX idx_domains_domain ON public.domains USING btree (domain);
 &   DROP INDEX public.idx_domains_domain;
       public                 postgres    false    214            �           1259    35796    idx_domains_is_active    INDEX     N   CREATE INDEX idx_domains_is_active ON public.domains USING btree (is_active);
 )   DROP INDEX public.idx_domains_is_active;
       public                 postgres    false    214            �           1259    35797    idx_domains_plugin_status    INDEX     V   CREATE INDEX idx_domains_plugin_status ON public.domains USING btree (plugin_status);
 -   DROP INDEX public.idx_domains_plugin_status;
       public                 postgres    false    214            �           1259    35795    idx_domains_status    INDEX     H   CREATE INDEX idx_domains_status ON public.domains USING btree (status);
 &   DROP INDEX public.idx_domains_status;
       public                 postgres    false    214            �           1259    35798    idx_domains_user_id    INDEX     J   CREATE INDEX idx_domains_user_id ON public.domains USING btree (user_id);
 '   DROP INDEX public.idx_domains_user_id;
       public                 postgres    false    214            �           1259    35802    idx_plugin_logs_created_at    INDEX     X   CREATE INDEX idx_plugin_logs_created_at ON public.plugin_logs USING btree (created_at);
 .   DROP INDEX public.idx_plugin_logs_created_at;
       public                 postgres    false    245            �           1259    35801    idx_plugin_logs_domain    INDEX     P   CREATE INDEX idx_plugin_logs_domain ON public.plugin_logs USING btree (domain);
 *   DROP INDEX public.idx_plugin_logs_domain;
       public                 postgres    false    245            �           1259    35976    invoices_issued_date_index    INDEX     V   CREATE INDEX invoices_issued_date_index ON public.invoices USING btree (issued_date);
 .   DROP INDEX public.invoices_issued_date_index;
       public                 postgres    false    247            �           1259    35975    invoices_status_index    INDEX     L   CREATE INDEX invoices_status_index ON public.invoices USING btree (status);
 )   DROP INDEX public.invoices_status_index;
       public                 postgres    false    247            �           1259    35974    invoices_user_id_index    INDEX     N   CREATE INDEX invoices_user_id_index ON public.invoices USING btree (user_id);
 *   DROP INDEX public.invoices_user_id_index;
       public                 postgres    false    247            �           1259    35689 8   personal_access_tokens_tokenable_type_tokenable_id_index    INDEX     �   CREATE INDEX personal_access_tokens_tokenable_type_tokenable_id_index ON public.personal_access_tokens USING btree (tokenable_type, tokenable_id);
 L   DROP INDEX public.personal_access_tokens_tokenable_type_tokenable_id_index;
       public                 postgres    false    227    227            �           1259    35690 #   plugin_status_logs_changed_by_index    INDEX     h   CREATE INDEX plugin_status_logs_changed_by_index ON public.plugin_status_logs USING btree (changed_by);
 7   DROP INDEX public.plugin_status_logs_changed_by_index;
       public                 postgres    false    233            �           1259    35691 #   plugin_status_logs_created_at_index    INDEX     h   CREATE INDEX plugin_status_logs_created_at_index ON public.plugin_status_logs USING btree (created_at);
 7   DROP INDEX public.plugin_status_logs_created_at_index;
       public                 postgres    false    233            �           1259    35692 -   plugin_status_logs_domain_id_created_at_index    INDEX     }   CREATE INDEX plugin_status_logs_domain_id_created_at_index ON public.plugin_status_logs USING btree (domain_id, created_at);
 A   DROP INDEX public.plugin_status_logs_domain_id_created_at_index;
       public                 postgres    false    233    233            �           1259    35693 "   plugin_status_logs_domain_id_index    INDEX     f   CREATE INDEX plugin_status_logs_domain_id_index ON public.plugin_status_logs USING btree (domain_id);
 6   DROP INDEX public.plugin_status_logs_domain_id_index;
       public                 postgres    false    233            �           1259    35694 #   plugin_status_logs_new_status_index    INDEX     h   CREATE INDEX plugin_status_logs_new_status_index ON public.plugin_status_logs USING btree (new_status);
 7   DROP INDEX public.plugin_status_logs_new_status_index;
       public                 postgres    false    233            �           1259    35695    sessions_expires_at_index    INDEX     T   CREATE INDEX sessions_expires_at_index ON public.sessions USING btree (expires_at);
 -   DROP INDEX public.sessions_expires_at_index;
       public                 postgres    false    237            �           1259    35696    sessions_is_active_index    INDEX     R   CREATE INDEX sessions_is_active_index ON public.sessions USING btree (is_active);
 ,   DROP INDEX public.sessions_is_active_index;
       public                 postgres    false    237            �           1259    35697    sessions_last_activity_index    INDEX     Z   CREATE INDEX sessions_last_activity_index ON public.sessions USING btree (last_activity);
 0   DROP INDEX public.sessions_last_activity_index;
       public                 postgres    false    237            �           1259    35698    sessions_user_id_index    INDEX     N   CREATE INDEX sessions_user_id_index ON public.sessions USING btree (user_id);
 *   DROP INDEX public.sessions_user_id_index;
       public                 postgres    false    237            �           1259    35699    users_active_index    INDEX     j   CREATE INDEX users_active_index ON public.users USING btree (id) WHERE ((status)::text = 'active'::text);
 &   DROP INDEX public.users_active_index;
       public                 postgres    false    240    240            �           1259    35700    users_admin_index    INDEX     ^   CREATE INDEX users_admin_index ON public.users USING btree (id) WHERE (role = 'admin'::text);
 %   DROP INDEX public.users_admin_index;
       public                 postgres    false    240    240            �           1259    35701    users_domain_id_index    INDEX     L   CREATE INDEX users_domain_id_index ON public.users USING btree (domain_id);
 )   DROP INDEX public.users_domain_id_index;
       public                 postgres    false    240            �           1259    35702    users_email_trgm_idx    INDEX     Y   CREATE INDEX users_email_trgm_idx ON public.users USING gin (email public.gin_trgm_ops);
 (   DROP INDEX public.users_email_trgm_idx;
       public                 postgres    false    240    2    5    2    2    5    2    5    2    5    2    5    2    2    5    5    2    5    2    5    2    5    2    5    5            �           1259    35703    users_role_index    INDEX     B   CREATE INDEX users_role_index ON public.users USING btree (role);
 $   DROP INDEX public.users_role_index;
       public                 postgres    false    240            �           1259    35704    users_status_created_at_index    INDEX     ]   CREATE INDEX users_status_created_at_index ON public.users USING btree (status, created_at);
 1   DROP INDEX public.users_status_created_at_index;
       public                 postgres    false    240    240            �           1259    35705    users_status_index    INDEX     F   CREATE INDEX users_status_index ON public.users USING btree (status);
 &   DROP INDEX public.users_status_index;
       public                 postgres    false    240            �           2620    35775 -   domains domains_plugin_status_logging_trigger    TRIGGER     �   CREATE TRIGGER domains_plugin_status_logging_trigger AFTER INSERT OR UPDATE OF plugin_status ON public.domains FOR EACH ROW EXECUTE FUNCTION public.handle_plugin_status_logging();
 F   DROP TRIGGER domains_plugin_status_logging_trigger ON public.domains;
       public               postgres    false    214    214    297            �           2620    35774 0   domains domains_plugin_status_validation_trigger    TRIGGER     �   CREATE TRIGGER domains_plugin_status_validation_trigger BEFORE INSERT OR UPDATE OF plugin_status ON public.domains FOR EACH ROW EXECUTE FUNCTION public.handle_plugin_status_validation();
 I   DROP TRIGGER domains_plugin_status_validation_trigger ON public.domains;
       public               postgres    false    214    214    296            �           2620    35806 !   domains update_domains_updated_at    TRIGGER     �   CREATE TRIGGER update_domains_updated_at BEFORE UPDATE ON public.domains FOR EACH ROW EXECUTE FUNCTION public.update_updated_at_column();
 :   DROP TRIGGER update_domains_updated_at ON public.domains;
       public               postgres    false    214    279            �           2620    35707 #   sessions update_user_status_trigger    TRIGGER     �   CREATE TRIGGER update_user_status_trigger AFTER INSERT OR UPDATE OF is_active ON public.sessions FOR EACH ROW EXECUTE FUNCTION public.update_user_status_from_session();
 <   DROP TRIGGER update_user_status_trigger ON public.sessions;
       public               postgres    false    294    237    237            �           2606    35708 !   content content_domain_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.content
    ADD CONSTRAINT content_domain_id_foreign FOREIGN KEY (domain_id) REFERENCES public.domains(id);
 K   ALTER TABLE ONLY public.content DROP CONSTRAINT content_domain_id_foreign;
       public               postgres    false    3739    211    214            �           2606    35713    content content_user_id_foreign    FK CONSTRAINT     ~   ALTER TABLE ONLY public.content
    ADD CONSTRAINT content_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
 I   ALTER TABLE ONLY public.content DROP CONSTRAINT content_user_id_foreign;
       public               postgres    false    211    3790    240            �           2606    35718    domains domains_user_id_foreign    FK CONSTRAINT     ~   ALTER TABLE ONLY public.domains
    ADD CONSTRAINT domains_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
 I   ALTER TABLE ONLY public.domains DROP CONSTRAINT domains_user_id_foreign;
       public               postgres    false    214    3790    240            �           2606    35969 !   invoices invoices_user_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.invoices
    ADD CONSTRAINT invoices_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id) ON DELETE CASCADE;
 K   ALTER TABLE ONLY public.invoices DROP CONSTRAINT invoices_user_id_foreign;
       public               postgres    false    3790    247    240            �           2606    35723 )   licenses licenses_subscription_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.licenses
    ADD CONSTRAINT licenses_subscription_id_foreign FOREIGN KEY (subscription_id) REFERENCES public.subscriptions(id) ON DELETE SET NULL;
 S   ALTER TABLE ONLY public.licenses DROP CONSTRAINT licenses_subscription_id_foreign;
       public               postgres    false    220    238    3782            �           2606    35728 !   licenses licenses_user_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.licenses
    ADD CONSTRAINT licenses_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
 K   ALTER TABLE ONLY public.licenses DROP CONSTRAINT licenses_user_id_foreign;
       public               postgres    false    220    3790    240            �           2606    35733 -   password_resets password_resets_email_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.password_resets
    ADD CONSTRAINT password_resets_email_foreign FOREIGN KEY (email) REFERENCES public.users(email);
 W   ALTER TABLE ONLY public.password_resets DROP CONSTRAINT password_resets_email_foreign;
       public               postgres    false    240    224    3788            �           2606    35738 !   payments payments_user_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.payments
    ADD CONSTRAINT payments_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
 K   ALTER TABLE ONLY public.payments DROP CONSTRAINT payments_user_id_foreign;
       public               postgres    false    3790    225    240            �           2606    35743 8   plugin_status_logs plugin_status_logs_changed_by_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.plugin_status_logs
    ADD CONSTRAINT plugin_status_logs_changed_by_foreign FOREIGN KEY (changed_by) REFERENCES public.users(id) ON DELETE SET NULL;
 b   ALTER TABLE ONLY public.plugin_status_logs DROP CONSTRAINT plugin_status_logs_changed_by_foreign;
       public               postgres    false    3790    233    240            �           2606    35748 7   plugin_status_logs plugin_status_logs_domain_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.plugin_status_logs
    ADD CONSTRAINT plugin_status_logs_domain_id_foreign FOREIGN KEY (domain_id) REFERENCES public.domains(id) ON DELETE CASCADE;
 a   ALTER TABLE ONLY public.plugin_status_logs DROP CONSTRAINT plugin_status_logs_domain_id_foreign;
       public               postgres    false    214    3739    233            �           2606    35753 +   subscriptions subscriptions_plan_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_plan_id_foreign FOREIGN KEY (plan_id) REFERENCES public.plans(id);
 U   ALTER TABLE ONLY public.subscriptions DROP CONSTRAINT subscriptions_plan_id_foreign;
       public               postgres    false    3763    238    229            �           2606    35758 +   subscriptions subscriptions_user_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.subscriptions
    ADD CONSTRAINT subscriptions_user_id_foreign FOREIGN KEY (user_id) REFERENCES public.users(id);
 U   ALTER TABLE ONLY public.subscriptions DROP CONSTRAINT subscriptions_user_id_foreign;
       public               postgres    false    240    238    3790            �           2606    35763    users users_domain_id_foreign    FK CONSTRAINT     �   ALTER TABLE ONLY public.users
    ADD CONSTRAINT users_domain_id_foreign FOREIGN KEY (domain_id) REFERENCES public.domains(id) ON DELETE SET NULL;
 G   ALTER TABLE ONLY public.users DROP CONSTRAINT users_domain_id_foreign;
       public               postgres    false    3739    214    240            �   �   x�}ѻ!E�x�¹g=} Q�+���%�(?��pq���\Ll7�-����&��<t����`JJ5̕�i�� 	e�ZC���#u"s�7g�`�5�*�s̏JVW���`�P:!��#8W�X��p�Θ��9D��2��"܄�̏C�m�����魵/��v&      }      x������ � �      ~   %  x��]Io�>ӿ�� #ҽ/:d���ǳfJ2�` ���������4}�)�\�S��S�������n.�&MQ��dK����{��-Ք�x�	�ܠgY��K��R��R�e/R|6�f�B��*�9y���+<W��^>���EF�A�L�~)^��iN�L�B�~Ba:���B����t�(F�ޏ��,�r���7b�����5<��I)���y�7�f��!,��^���G��� � \����2�����`!K�/e��GH��>�6��j�y6T��{U������Q�D"W����ך�����B��0����g�4�㶦I�Y"ȷp��S\F���f��l'�oG���*�� +u�j�L%�8-�j}BY��#A9��G���X]C�I�1-��C��vFb�
��I"X)UJTL�j� 2%�ZV9`%�H�j.dT�8Wc�dA��͗"-J!��q� ����࿳��(I�����������ӿ�n]D~�	�ju1�2�X)ޙ墯r.r����m0�E,����\5��W��7���y��叫��*�d3T��ֹ"j�u��dّM=�|G���^�x$b��aG!�[#�W��BUe��b@w
�iղ�(Ւj��{ ��z���	n��d�������|B�Zُ����S�,9��m�_\�L��&s��F~M�Ѐ������Q������d�^@#A�X܌���c�3lE��ڕ�����^1�+��d�{�A�rj:�ۖ����
�8��m�>��)���7�^A���+��SH#������2�u���3�a�kgP�:��^I�Ϣ%ivj(�<����s|y.<�0\#�c3v��$m���ʇ�*�-��[GܵCFM�LpwNȜXĎgBp��A�qe
D8�W���i%٭[��p�E�a[v��i��w�C�0_�ξ~x���g��{�q�i�s�ps�i� �i{���ɹ��9�E~�����U�v���F�F'������U�2����^���
��F#�:]�P�<P�s-�N��:���ag6w�Bn:�)8u�)�Df���0,��X��ֆWI��3LTD�~F9�K�U�9�_chz�%r���^yX�x`U7�f��@#�G�7��;U�B3�¶,yRB�U��
��j�a	+�m���D{�\���t"����K��g������F"��k#�-�ǁ��粚��P�����<�W�eA���Ćaj��D�"	N- ��hw"/�yNu9i��X�hI��*�VDQ��	���89H�����Ȫ�,NH.b]�L���*�/*A�r���Rp�E�WL� �&(�R�`�����߁�D�6D��p>;i���,���G�������0q���7W0��~�.��Ƣ��UU�U�Ѷ�m
H"c-5 ��X�X��dDsXu$�))�[	V�(�� M���r6 O��Ə�y�����7��I@g���h*��E3��=*��"��3P;�=� `Cu�e���b"�I6(��Db$5f����ֲT���R�d�'��`�XP�*�ۄ&���e.�7�P=����Vy�lB�=ĕ����9��`dL�(��x	F!Ѽ�E��1Ƞ�Q��~J�O�wcJuʑ�E!�oh;��L`N���)@P�.�C�\@��q��ɛ�ꇚǷ¦�w�S����Ya(�H��F��b�9�n*&c��h���P��Xt���;R�\���i����L�E)�n&�x���Д򣱬Mw�v��v�R�L��_��y��1%?�*~�X�,C��Q�]��Br>��+��eH�t|�
9�Z���?X�K+���*�nJ6<�UJ_
��GƩE�]5	l+��g���6-!T:+.a��*�����/�c�n�ہϭ���=�^�J(���F�`v�X0�����>�s��>g��l��A�y���Bp%�XS��̉��voD�|���H��ڀ��������`[�[�����3�R�|+S�9�Ύ��F�����<j��A8LI���EI84k�=�=�#X�6��.a�=�o#P���x\�1��o�]�۠�M(���β�� �Gxwķ{��兦�B�m@~`1�oy�zΫ�`��n��v��3��������ۆ�ی�Kk�^Z�� ��}Wi�IC�N�� q��\�H��J�E���80�hg��	��#%Հ���l�/^:��Hih�:��x��V|KK������J�r"f{���X+�\���;6�yKa
0g�����ίܡW/�4In�9p�����-�|*��p*��q�Z�ԝ��X�Q��� �h)$���k�r܀|^���"}�a	%�
���-�h�]��N��b���H(�;X�)�d1������.�<�c���x@���2����¬�� �:�i�+^���8�-4�EB�4��$E��$@- +G}%����&�����M��Sך��������_�	b��X=���\<$��ȴ�p��.�E�}aNg'����6e ��nx��"�32���l��_����`	VMF��d�C�N8�#�c�_PvY��y�(���7��r
���)H�Jݧ'�<���]�F����z;�����.lS�z[V�.�p2��&ok�o^���?� eb��y������?�@>Uj$٥��O�n	P�Dw���A~f蛵�a��"ҥ=/*ȭ`���(�|C%�1�S0E53�� �)pc�	�{�a ��n��['�a�.��+m�8�K�r]m�SQ�=%(�B�
�Om���b�/0m��ncy�kG<s�)b�:S����9F`��Op���J`"+$��OzI��=����?�An���^jt���У��5��Y��y�~%�҇�c����}��(�mL���o��ց�����z'�Κڊ?u������}#b�b�1�x��mӶ���^����vk|K�ʘ۠e�cF�g̣4�Ў"�q���k�y�V�Gs܇ߝٰ�b;�V[�x.�D�������I%����-��xm��3���S��A�2����ũe�.�{�4;<f�셄/-�X�6(ܗ��CbRl�-!H,���d�Ķ��9Ֆ�aX���X�-�^_Ӝ���D��[k��&2ѽ]z�O�i�^y�Z��ix�z����ЅF��?*iy��M�����^�pu�-���3. |��>���f'm�K|s��b�m�ba��R�O"��ͽKB�h/m�ba/�]��I�;�'Vh���e?�~$�w6%��n������w1�>�<���pڢ�[Ξu���e3ۮ�B
7Vv��QWX��UPX`�Ś�Nlo��;���~-'h0�	4/�/M�C����<�h�t��r-(z;�N4����[K:8���ݴ���H�ݭ��q�[;�h�tS�=kA��;�v���l�B�kA�.�d�Dbh�B�l�Z�D�k�8�l��͎�%��t���H6�����C��ډ�*��Ů����}�Y��ب,���ɓ�0��E�4雃��bV �z2��2�'�\<����/p��w��]����е,˱.��ƙs�CY������%��/t��:u�S�_��6�q�����`�ڦ            x������ � �      �   �   x���K� �����*��nƖV�L[��NML|���×���+�t��܆Xt���i�O�G�j
(�i���TNm�Z������xs��9�NK�4_gf�0�6��b{�֋K�}?��l�se�7�_��Þ�֕��r���W��ϰ��[�n4_�9j���i:��N=�DU�X[gp����<�"˲��w      �      x������ � �      �     x����j�0Eף��8��ïu6�dS�v(n��c���kYnl�]@H���9C@�ۿD�����$7�0La�"�c>���57S��j���q�3q��#�J��jL�X����4���؋�(}<�o=������k5F(������)�dZz�Rj��#c�c�z.��4�V��~~�ޙ��Յ���խiW�t�kZ�1R �8��2��t�D�D� %=k��$C{~If�tR�)ų�	J�Wj:[���:1�;�raOv�c�����Ŷ      �      x������ � �      �      x������ � �      �   ~  x��Vے�0}^>�c��_:�q�a��ؤ����v��;yٙs$��H�(�\!�I����rZ��ܘ^Z��l����2��B.i)Ap����Փ�N�[�@��%p=t��+�?���ҽ�+V<ǎ���@zV�z��xz�{�@���QY�{�j9i�����+�w��W,��`���aS��|��dF�2�c�θ(��Z�����>�̡�B��Z�y�9�=?(	�b��C�Z��
+F�ޖ��T(�-��tw�S$<�Y��B���D���U�}3��趶�� �r@�;��QwN+��+!�j�j�J+w�A�B�G�$uUNMr�Z�d;w
��r*�@�X�W,�6×a���4�;�T�PO�s�6z@x���|�C���L�Q"hd^�d.��\%�>�))�O�8y�o$�F����a��<�0Z�\�c}XQ����[�����C�@�8�s��3�L�L�n�"�R��كȋ���0��sʉ�)�BlȰ%��s�%�n�+7O�E�,�2�?�hj��C?��M��Bb��u���Z�2��sw�ĖL �rV����r~
��`���G�jT�/Ft�$�Ny|��"X���;v���e��/c���x���7��<�I���/�"H<<��i#�����Y�.�x8�!�r�kp�?[\6����*�r� n*m�>L^at*������k������`�8O򬬮��Г��=��Q/�z9�K?+��L��f<O��@i�M����b����� �b�7��2� #xUӣ�F���'u����2�N���1v|sz���H<�/x�q����L�E& ���ę�*�^u��*3��~����X�َMw�S&XJ�.+�����m����e�_X���      �      x������ � �      �      x������ � �      �   �  x���ϊ1���S{F�oR�7@o����������YP��V������W|z��p������z}���Ntjߟ>]��?��S�0���g��Y�	|�Ĕl<@Y{��
�+h@����ݫ�����! ��^Έ;�Nv�mr��z��hԇ����QI��x�¹��J�nSi�d�}�W�b���L�k��z�m忬AEܳw�)8�4Z�)maK���zɉÒ�Hc(�_,_�e��o]�j���X��Pshd�� kCD6�l#��U*��ͥ�t������̷��w�]���x }�4��������L`��"̣Wk)�E��)2�ϑY��vX]գlc:��-��gCI�jk��M��/�����Zz(��j�:��j�Z��pY�g}�-�������m? ӽ�T      �      x������ � �      �      x������ � �      �      x������ � �      �   �  x���MN�0���)z�"Ϗ�ć�� ĢV���Ʃ8�0HYT�����#;Td)����q�|[���g{8s��z�z����DEM�>�u.��D��v�A�.�,q*(�~��R�o����`�D���'ʗL��N�� `�/�`d�c��I�����&�A��&ҩz�nR��c��p�c��p����w�A��� �<�m���Y2X���M,0o�`�P�I�y^�� 	O��c��'V���c��'V�-�}�[xbe�� 1Ixb-p�bc���8����I;g�A��f��$[�`u$�3���D�� �h��1H8���1H����$�ks�;$�k��S�y�n���� i�7]V�A��=0_�A�� ���[����`�'��v_�x�
N���^2X�=��� eiC{      �      x������ � �      �      x�Խi��J�����W�n����C���1��	L�Jj��!���6�����[�ݷ��yJ��룴�����+�+V��^V^�3�۝yѯ�n�sy���d���6Gނ�ÿ����?Jӏ���_AۏA��6b)eJ/��f��g�7�'�y�b��l���W6�dMY��R�W���=�{���k�2W��@�f~���>N����5��b��S�uS�eP�f�*/���� ϵ�L�tY��u���Ϗ����l������(ۛ�oW��N�q�70;%�~;�O���G�߯����������X�9%aڐw�r�Id��-�&#����o���5}I^?y�˙&-��~���\nK�n���j������2�6����>�D	d��̊J�DfE`��2I��*���+�,��:j9��,�~_�m{>����8\7�:%��;{4[bk������?|��gfYϢb���\1b<4�%f�2�H���LX2m���=��&�����Xi���F4�Zqjw��/#���~�n�Z[{f��I�|�[(yk$yf�
L��33���x .�B���k����P��l��Ï�?�����_�3��z��}=�y�lf�;�����G9��B�!�1��Y	�^����3�;��}�k����~�F���&Α~�"PnI�;tbw�o�m9�YU�����?� �1�x֋'��6�Q8K��$�pK��h�����]b�l�Ϟ�����_���)!Z���7�IZ3(�58;4۟eM�O������_~J�rCTΒ�Wp6��Kׁ�v)oP�O`�u�t��o��?w=����o�E���7�S��3`��MSJ�]�v��-S@�9�#W�Y`���zY�N�Hb"����b0�ty�MR��������M��c:M�����x���۵�tm��Ͽ�8�?n�����-���/Q_�Q���z7�����t�e8ź8�ͷN�UP�e�׍쯢Δ!�Kp>&þӫ�}M�H!k��WF��"2!�ou�����W����W��_k�]e�ߨ��cT�v
xd-Y�d�s}�N��4��x1SCͻ��q�FO+x�&���b�OZv2~?f@���#�2$6י�R��T��W�(%Ј*�S�O�t*��F��G�[$ZΧϘ&��';�Ryc1k�#އA���r��A�a�WΨ����3&5��A�mÇM`�=2�L����Ӊ*�a�l�3�<"6�Y�s�ҿI���%YY��I�၊���g���m�"��������S�1( kr��R�;�lQ�� R��G��T��p��P��ڎ|�CdH\Y*8��IUgo�R��od��8]����p�8�ξ����<��ù���Lť����f���<�R�f���ƻ��3���M������m�%?{��$S�o,����m����y����l��"��5��x<��������x"�'��_���槶?����˦���t� �%�E��������e�u��E�=�����Tg��筋���E��������e]^�M�O�:��ϑ~��Cz=��%?@K������V�'��u
z�PMIɀ�p.B6��u{6���6���Q�[5f�Ng��]vZ�I�I3M��M���9]�>�w�X��z��)A��PX'�w�D��� ��ɪ���UVa�QKb�������@��, ��k�^��\�f��?q���h�#�W��:w��S��}yWnF���9����G�����qL[��/�J�G���Z�|<�Թ)y��.^?=v��k�_�����gK���W��r�C߶:��W_�\���?�P�8$�B���� "&tЈU>��o\V;�80͜eG��L×ߘ��=��Fz�˶��Y�x��F��y��{I���Zi�����Z��o-in(�� C�G!�֞Bf��
��TI��Ͼ�3i&�����%i���eq}b�]&�r:�p�b�@����O��K0� "Cڔz"�)��#R��ЙJf .�hO������4�7r�v����G�jU�:nz��uy�Y�7����7'+��#h�W�v�T� *?���-Y�<����h�mf��L�h����5`�m<\�tAo5_.�%��A.릷�o�R��L�+��l��F�h�w�%k�#'�$Z(�T��{ۤf(�������+i
S����;_�U���O��;��*�o�#s�%��F�U��\Y{"e���][�5���U����NdP�z������p�R����O��E9��HɟL&"�%.��ߜ�{C���-��U�ۜ�<��<���c�D�а����R���_��nWfҲ��|T
��kJ��$��Pk��0����6Ѐj��gO�)xE���7�4�z�oq|�h�.N���:P��p�V�v���/��LH\�~-�0��ܥ�S8^nb�'�9k��Z�����~���o�׍������̂�b���
�Ps�`�X˗Yy��Q���K��bFJ�6����p�waDE�Ť�-b��>Gv��Y���D�s]y7�n�۳�e����,I�����2?�V6ˠD�����R�K��� �[O!��m8N�M������ؑo��;�a:|��׹��z�|���ھ�U^�{���ڟ6��՝�[h��˘�B^�~)�nY�q>PU"���rD*�@G?%�8MA�>^T8%����׋��8|Yjl9��o�����w���y�."U_<�L�fhtw6�.�r���-�	eG���)��Gpp�+�h/������#6���[���p�ӓKMZ�Q��H+��Z�bphyp?��ņ��|���E�N=\���I��_KZ��Tna\q��>��;6(��JW���q�ÙԽ�DF@�Z�2)�ɫvS�/I���ϟ���y+���?��ֿ������U���9����~ٟ��:���󵙾ʿ�˟�4���O��?(�2�o����"��,���J�lLW��ݵ��f�A��i�I}̣/���K84��zH���.'�=��寯G���bt2tX��.��4�p�+���D�Eӿx�ۜڛ�PB�&��5�����&�:?��-\���1��C(�-+�!/]߄���������x!�~�i�P�[G�1$5D�f>����iB��Ĉ��T?Q� -������n�wH����ҽ�*E���Yz�/a�b�UW�/�
�N#.x�&�59pR�2�X�GY뿰m۩m�9�K����s����t�9�C�`?d��&���e~������:�6���+��XOk�]3�%��(|�O{2=�
(�8��,�蛵��UOc�����,�o��z1����η�KMB�en�єXl]&d�����ݣ}]r�+��;�����j��|�L������~�E�Ÿ��'����㟳�(~����ꅰ%���Is�Ê�>S�h؆/Z/Ӱ�C(�{�^����;�q�����ҝ�iV.�^�� �: �����X%�g��!���Q��}Oe�1���z&񠰈�O�t���h#Hf��ĥ�����+E�N?ԳMN���l�ʃ�~�*��K��K�G�}�}��H�����xU�0�a8yy�3w:?SJ���Z��~�}~/���~�7v�|#��q�MY�������+�ݘ�&s�{Ԑ2T���״dj3��v��]�\��<a���D�J������_���������I�vS��f�*67��������N��Î���<RG����&c)�~;y`�|��Sd\f&����X�~�����2��ջ������y~�<'-ewv��/�`a|��b��:�1��"�M"��me5H#�vBIxYU�HfY|�~~��{�/�P��}�@�k��[o�(|u�Ϡ}����
�5�>�\bkZ���	S�7K�]I����}b�l�
>��)iMj�t1���G����
��T���뭶�;�"��][�ͳ���b��S���2�A�4���p�p�;�]�O��Y�tMV9��	#�!2͊�x2o���9�Nٖ��P�5s�����ʹR����JZ��}v��	�1�e�t��H=���q�t�<Q{�"�wJ��(�Fc��?aRgo�Q    �&���ü�FE�w�,_�P-�U�VFح=�P=�;Db�kI��(�ٖ�ӊ��&��b��zr2Un�P2���x5oF��[P�8*U6 ͜Gp��݅NN������a'���D<(�2j��ƚ�+9�%�W�Y�KZ�lt���v��׵�v�c�d��O�:�3��k�9w%���a�1�i�P��P�ԁia�P�Y�-�]5)�(%��|�y�?J�XWo��Mޭ$c��X-�=�6�L?�R)_-�>�Q�� +K�h������a	5" �`�^h�zְ=����y����#?ı��+�$[��"if�Ȑ�ә�A�*%�>�0�k6K['��ݘ�ϡ�G*�e�G� � y�6-7�'������f7�u��$���y�/�QW;v�����LC�jM��xbl���O�_�f',�mR�Y�2�w�X.*K�?���.�:왾'բba~Hs��]���/J<�xޮ�_���j��.�ge�B�~	�2��d���`x��Ջ��$����W��5�f��������^eY9��1�aѕFi��|i;���Z�}�PY�H�Թ���5���?'j.C�Y�{���4���M��V������XG�8e���`�y�6��l��Oy�l��Yd�-A̪w����w��.l�P��@Êپ�AJ��O���!ً7EHRr�3�x}�]gd8�y�T���04�[p��V���{AYO�Ύl�,e1�ē �%HY9��{��B
k�eX�~�Z�_�ޏW����r�r(�kuw����F69���ۭ���a�L��R_���\8�0��=�:ĉtQ�L��RkQ\K�G�����B2�W󈓹!06�ݠD�C�5i.��.�����2����q�3D)�q`�H��g��(�3�@� }$���ҡ��ۛ���^Ǉ��P���<L�b8n~���a��`��%����B�L��=�G%�g)u횩��v.�݃xqo��F't�t�����ȌF8��٠?�`�"��Qz�ڦ��S��6�[nI�|U &ۻ1+k�Y.��p�����.8�;��M�����!?.����T1�����>\�.�$Zh��X�~vL�u���_�N,��tK;���:|�Sk�\>�(D&�C�G��a�룍�����.;O�� Fm��'o��4|��)v<D⮄)�F���|�&����gI����	ˡF?�Y�YҘIHю���/�ۻ�����l�/FY.��e�|����iK9pd�c���D抮b�xl��u������W�i���=�Q��K���4OX�\og}g�K�j�<�߇=��^�T�H��He#���ۘ)�Z삈"����� {�d�e2t��U���D��\�Y� dY�[�b��j��{��0��'qy��e���-�M�0`M48or/�<�R����'�̻�X7�|�@���M�烾z`�=���5����a�k{���=�I�d��}k��vLt1'��W��H��Y�H)�;�'MOf�_�������)vi�}7��Uw�ޑ/�w�N��j���Pl&��&��(�+�`����*:�q��X���&hvUz.��汸^VHl���"��4����K��̣��a��F!ԎxS�y�?#����}R+X%5]X4��WD�+/	��vUy��Q;D	U,�<ӱ�Z=��@�Guۍ�����Y�099�!��M�)��H�r(jQQdi��x劧���B�����'���=�Pڽ�����Q��&�����jE/"o��Y����%2��)\�7�όn���)&k"��#cG�ɹ��X4�WX�O4�|�]�}�ZT��º�Jm�uz���dח��if���>��h7	b5�z��+SJ-�=�:@�#� �+S��QSk�ɟ��:����Q27�xަy*�S�7ra�_�\8��˭���y����}�#�M$;���Lq�Km�fny+j6R"q������~��Y���*��ؽ�R��"G2���f�Uk�q����=]�0��/�R}��Q-$�<L�g#�Z���e�H%vfV)#yr,%�g�Q�o�����SU���5�sL�u��E<��#K���۳�7����nwͅ��xB���J@���2z^�:o��S�ј\~z'���v���&jC+�mk�|j
�Y���c2��������\�)�)t��w��9��^�+��A4�k��^z(���on����gu�J�7�d��%����8���L��D�;6�$\Su3��=��N,(�-���r6�	H@�#��HXdn�I�;��=�� ?�:���Ţ��^�����CM�ݙ˹?[C�n�ؓ�V�ܙ�I�}���Kq#�):�A4�@��=��,U���5E��DN@:9��]�}��H� &�j�£�a�T�8����_Ew��RX�ޤ�)4y%X$	��6�%��(yD��(~����q��S)�K
�rG�(�9�f
ށ�ܧǻU��.7V���⍕�!�V��}c�W&��Ady.�4���bv"R9Y��}�L�#?t�{V�iL��d��T���b>����ɠ�i�ԇ�6J�~�5W�: )I���6Q,���ts�shk�a�Z@�)�6zfw�O�Ƽž�/��r������\�6յ��YZ���מ=���H��������v�@��stE��#A�_�[$��~k�;���Ǯ�5�[�T::Y8��Lt���ma��r��e=���f}�Q.�K�>���\��Px��ej���ɻAe�;H���D�]�ެ(q�6��{h��a���̫s����EX.��S�{"��@�c�k��)1�P������:�u-��;�Ā>�dބHՉ8�[Nf�Z%p>�w��%[�0���M����,nȆ=W����B��6T;��>��Q����N.Dײ�H���t�����Gi�I7�Z� �-�l����:��dR�Ёa��I�#/�[H�S���=�v`�pY�aU&D�DY�~zo޿�d$��v��R�����g�{����ʗ��86��m�}ؙ���mj���*w�!� ך�{�Mb��<Y-I��O�;U�7&5�b�o��bo�P/M��w��,�{B/����5@_�A#l���� B�4X��0�!x��*�f(s� i�V|"��N�=�@���ӳ'�.�ݹ���zv�bZ�TP�&��=3a�	W��l
��l��5�Y-|P1���bv-lȭ�e��O`'�M�����ݬi�pH��K��ܻc���v�/�n���YG��^@K*"�$]:H^jr
f"�i)"VZ$(��ÿ1���a���S?�eT�w�c�l�/��3^9�[IB�����^6�֘ߥ��^��%�v/Ly�H�Y%�,�z�p�{�	ԝ�*d�MNT��r���ӛ��	2�CIz��e3���w�Q]o��������o�� �w�jD@�0B�g� �Ja ���PF�"aہɟ�N�#��7�"ɳSht���䆸��Z��N׎�0<6;��&�>��T�T�ґH��)��"_h(r;'��!p짯�G"��@���w6���k�}G�ͳڮ�Y�_"�~4���}���z};Ul�W���}ع���	��!���`d���FS8��Lr�����I�ݡ�_���?��+��ۇ�Y��Z���خ���n�n�F}�]��'�D�>��.r9����S��O�'���^����2Qm_�(��J4����,��x�.�.�1���b��0T�!�SO�'�o�_!��I�ֈ�+�s<��D(>�HUޘ�n��"��*���B�w��Ѽ�������X�M�m���Xb\��^�}�~a!]�!���0�=���]�OZ�B�%�� B�?Yӷ)���,x�ڪ�GaO�D�t��ۗ[j2M�`t]�IMKySlO�\n�0�>�{�rg��m
k�J"S�ol'�����qHP	��]r�%~K6�A�j�j\��t�}E���ij�&$9��uYw�F+\��/�q!��0���j��$S�WFE�!���s|���H��(j�O��y��S�-?�ܧ�Y��4ka�^�:Eag���    ��${/���
�����Ll�D�GP)Yf�;�<U��8������"&��b��O��ߝ�p �p�����5����%W�� �]�̝%;,V�p)I�Hz@8$̨�Dn��I��YiHy��\b&�!�TY�Ϙ�7��0�ϲ��5�[��^��Z|�Zs1*^�8v�0��?���,�����5�����
�]>�e�
Z�>�HXt�o?����x�m����~yj��/LV=�z����~|׳���3�xE�����)Oi\��N�pį�.����9z����}�����b����s�����%����j,�N�5���D>�zos���ߏ�w���d�p�����PyM�eZļ��il��J<�����7�S�N��(
����9��ӫ �Eszxa⍸������S���s��]�l<�8�d2�nN�-W��$!���4����ʞ�t�]F��/��/*������8.?) ��5=�p�"����������d�PP�mI4���b ���*��od����e�چ�Y�p��y�F�������$Y�?dY��,��2�B�uQ�rP�aˮ�����e
8K0�����w��<ӟ�/�N�rd<�It�͏��Ckk�K赋�i)�榶��a�&x�z��F�u����'���R2���%��p	�`w�%�F�ŀԘm�Y��%;�h�Ħ��z϶�w:�[or����3�o�w���Dg>�/f�&��K��%�=�[^��G%s�3��O7���wZ�,/�.�����:_,7��|>����_v��J�M)@3�7:m�k�xj`�z��;���s�:���a�]/wS�,(?���y��v�\�������<�P� ǻ�.{��ְ�d>bz�I�^�l�BM����0f
�p�D:��g6��3ݭ����8 ��mu�������ΗG/��WzڞUfq�F�Ȉ|gx���R�ۦ��b�i��U>v���ﱏ�Yw`��y[u�!*C�V�kc���$��=��;57z��'o�5u����H>��t+L�aS���S�K{=A�5m~Ĥ�i�ӵ�:�nydl�E��A5+�&Rބz���Z��p_h�����[�.���Q�&x��q�T���R+�|�X�+��J��K�lw�Ҝ��t;�o�n[��8m���KJ��n>Ob#Im��FlB��\�#Bޘ���-XN��Gv�ESP*ak�?Ыk���0�8�'<�ݮ=�x����~,M�'�a���}�]^r�� 	���-�"g0�0�ӝr�A�lF����q.}��⯘TU�n2U���9�XNa�>9E{!yW�wq�鰻��ȇTx!vC��+w�3T�ufMns�|��M}�k�j��.���ɼ;ыTA9Ƶ��Ԕ�^���i ��9/J�M��p��Z��W�Z��xk�N��6`�����ɏo�]�BZ���X�h���d�ק���1f�=�6���W|�������1L�/lQ�Odt���cI�lDm�E`k�$r�L!���I��7%���[s�K�zC�́��[q�5��ܤ��P�n�+ȏ�+ˏ���9����`1bg�B%���@��'p1�D�P�I��0�������v/K�c�۹)y��l7�E�N���v�A~P�s,}�������I�w�0ƶ����Vl��{jQ{�� H�F�I��d�,7Ӹ�V�?V�9F���R�tZt�۱^<i��H�P�'��	
_w�@qiF�n��j(\/�o�\A����|�d����c�1%���v B�(Y?S�.��E�mQ���a/b�L$VO���"�3ٗ�h�	 o�4���aJg�<��n�Z*���u^���Y�G��4]�hL���_t�/L`ɾ�&q3�k�8�HPJ�a�Tt	r��3 jFjq��͊ﱿ��]��n�s�h���@{�z�r��V'�d�}�S�SF���"TB0������r5Q��cL��~�L�&����C����l㎚�s�[$��ڴ����?��n��ڣ�V���RRZ@��5(l���B5h���D���^��i��9>�z�|&�;�R9��fwޕ��~�]8�}�n �����^�Zv��:FjyWH��$�E!$DN{�G?�\�S�;�̈����u��	��`�4_>�fs��W�)�l�6��c���g�|a�վ�S�M���jȚ\+jP��g$
�~`��.��R��y*h �;Y�]��J���6�%��H�^�kr
��Ng�ީ_��	b��3��JH?zw��0{>kx�(M��ɦp*QYL+�>��BU�o�v�;i��um�&��m�����͝tG���#�_�d"�m2��XG6ռ�R_�=��"��j�ά�lR�-oB���@��~��5%֥�݉6�x�l^�D�^������J)�_hR�H�N&-kX�-6	��qL+��hc�QlxEc)1�4�O��~k@߾�;_aPՍ�p����L�ԝ��~�7�lP�#��ׄ��0�5�V?f�J`�E��E���i��4�K���r&��~�,�p~l�Y�v��rƷ�M窆�/��HH�Ǧ!-Ul=��!u��K�歱�m٤gl$e`�}���n�^�Eb�UwP��~n�A�����zb2��N�6�_�b�Ȕz-��!Q7zNu�ڬ�}��y���1��ȓ�q3���~JY��@j�p}R3Yh�|n��f��r��~Qu��;�����׭v��J.���\�b(�V�#W$�`�`o�����D)Vs�0)�'�o�]�Ù$��ҷ�}�^�+�Ց��e_V�������"�$>j��)�r�jXy����QF�}��hX@�������h<��Go[�&ku[˓��A�	�!�Ů��oIؐ�O������/�F������/3f�����2�w�$����o�5�� �7�����s�s������<^;x�ta�\����x��F)L��ZDQ�,�ȶ�Tz*��|6�۰J^�ƴ���^��$���6�/'�ex1m�a0�@ɛ ��/<e��š�;\Λ�D��+K������\8�'5���|پ�� YK}�=͝loI�Yg�.����J��`����8:L���v��ۅ�R�?FR�QMbw�Vd�-�ؕTeJ$���#
Ǐ$~�)�jX �����py0����r�W�nQl>�f���01%���F(��8%@5���VT!��a=��k2ɣ���r�^ڻ����{,�>&��)>�&��3��NXi�� ߇=�a�+AR?�[&N(��A�cᤊ� ��%=�`��p魴�!�0��a�Tc(���p��Lb���\x�/�R�i��+���f	�&����=KM.��뇾�����ƜD�H��;i��>�.�)��E=H�{����(C�9){%��_.��(O&S�*Q�����p3�%���U(�*��$Q������H��da7ٍ^v�m��ͪ�E��:���^��X�8Y}���4�B�Zx9��5���Ua�Zƈta^�=t�ZԖ�d�P��@V��s�-���|a.y�t���h�㶮�y�.����9�H�q%c���6ZP�]$�sTo�ie���zI�!��=�,���z���JA�%x�譝[�>WC�u	��}�W*_WUk�߇�M�S%r�B�~�q3���P͵L�h��J��m�0)$�3���-��c?��F4q�ر���/jPY�<,h�G��i�������@n�C(JZ�����P�$kd� �q>��<�F0|���{�Ṿ�7K[}�����-W�W����sO�b�9��X�:�@���F��bb���t=a	P;!�0�Ωӭ?����]��R�^��M��]f�ݹ?��+=�3����cB���9 ��LY^�#ox}|S4�Q�kD�!Wj�o��#�x�#xպY�FBk�3yËø)g������X���l,C7�ďZv? Db��Et�&1h�*��@�"�F�W~���_��J4pQ��L̷�Ӽ�ݓ7(����:��f[�_����^����mKI�2$6���Ѕ7`�Z$�&�6Nj��u7�H�}&J}�}�PϥV�����    <oUy��Wf�(��]���j��_��xaS6�Yz�5f�*qm+�����!�I�d(
��̉�i�����z/^���I�_��c��\_�����Mn$��-� /�P"��x(�l��R�#(y�y�u�mI�
mCc�>�d�tO���nmD���R���ΡeL7���m8|ar�)lL�RJ�P/(���Qf:x�U`u%q�$�G�#4Ï�d�`�� �N�{Q�=����������~\�[�꒓�v��;i�gjy�D	,Qsdl3(��wؖ^%j�#�עz�?�A���'E٨*���C�Ͻ��K���&�{�O��_�o��FfJ�����n7�L���,3���a��P�O�5����[d�+�w��H��y�����n2P��˖�����9P7�Z޹Aj�J��[�k��u�0�,Hk^rEO1L`��3�w�K�7�!<����"�`�+e���܈�cd���XA�;ɧC�5�bV�٩�T�^���vF�(���)d�2��r`�Ga�Y{�����H����lI��y���0�	��nNI�0��h\R�t�oB)�@M�r�E�P>��	7d�"W���(%�g�0����]3ә8�lvn�w��K�<�n|HP�}ؙ�P%Rڈ4��H�Kr�)����Pkߑ�]C2W���#&��A=�����gpj^��uq�ݬY5�����5��2~=v_x��r�[��h�	�Pp����8���B��r� ���In�&�	�Qؗ�~QB<Uw�)[N��lq}��<s�U�����L��\�&x�I̩�.����u(�8u<T��� �3Y����o�}a�ve����(l)��s�n�aՁ�9��(��7��.%����U2TBA�+��O�@"d�t��hVa�ѯũ���'Z߿�~��D^�<~�4�U]�n^�<�Ҥ{-�/��L
�P��m�ҝ��kDeƥ�S ��~1i� l�"f1KȾ��>S��;V��~ӯQPtuQ<�Z���Þ�_�e�ݫT����D�8~�����p3I8*�g��!�JJ�Q�%���b�0���x��~�v%��5�.�Ԙ¤^�K�]��%��e��>�>��B�h��c6�17S!��� �@�h��h�3I���@�x@���&��0������z}����N�j���)�����dHŤ�dF�C%j;ߗ&�w��~L]��qƔ�9�	�E�IK�3��oZ+/��xw˻���Ս6���I˫���{��Z�A�~��%4"ߴבL�ic]��q.�}A!�V��#��P#T�~Fɼ�޹���7�B���Ǝ?��T�/���>�����h_�/B"Dxp*+~L>q�ؚ���R4��o�J"�8抝��x2o�+�y�&��OaӠ)��k������dov,�:5��v��~V����
��pI��PN]�eY�d�87=R=���?Jɜv���R��9�P����.�N�����Bk��$�嚸��[�� ,��-g~��h{�z�N�.u׬֭��ɼ�.v��z�絥�1�Z_=;�l�C�.����n~'�SST�&&�Y�q�l��Z�I�®G��4��8U�["����@���q�AX�cv[��qu3��*M�#gB��:�4���sb?si��(���w���06X�v"�W�#rl��������ǖg��/I�����X����Q�,�=�n�;�Lm�0bT\�	$%LF�a����S���L��@�r������;��E���%��"g���P;�xD(���~[�N������鎾L��ʼ�|��m>t̻k��U�.����oğ��wR�h�z��0�W����'�L��h��f`��K�����?�F����|#�W�����M�����N�8�u�#UM\����%��~�NY:�g��JL�-�xk_JK�9՟� wlC��|���VNu�;I��u�S����mގ�ƴ�)��|T��*4�G����{�C�?��+�w��j��x
fv�-�1}��B�l�����O�Wjy߼W��<Z��y�XvF��j�f�ѶO�!>��	�[��Q�%��qC��jR����Ef�a�a�:��\�WѮO�k]�+�E� ���2w￻�D'���q:>�6ZI$3�:���z��BŦ0I�TJuސ��U�f�,�_�Yn�57�H�k�=�Z���$�]\E��~�_��
��Lb�j��r~B�*eJu�m�d��%`�f,5*l��}�+�v/��]����$/�g_���}~��zs�6	6���Ef��I��u(�)h�;��wH`����	6#"Z.�W�n���.}p�>*1��Qq:;moA�O:��G�wU���po�,��;�a~�|�V<�0��h�"��i�u/�L�J܁�,�Α���OV��� ���W��j��!9z��s�kљ�|]�5�ib��nE���g63�!��9�+��ԕ�Ͼ��W�n����a�Mk���~�Z'�t��k}y~̗�/^�աlS=�+'�UR(�̻"��c�g�N��T?�e�r*�yK��ރT�c������:���cm�M��
�h���$�SX[9sz��`
I�#�jP�;��I��>L�T��\��-��WѾ�t�V^��χ^׹���x>�]���[3|�\%���4v���$����=P�#j{9����?����͔~�VE����H���>o�s?��۟o~ꍟ���l���ۃ�YAR��v$Xz�f�$��*�8e.���!Ƕ�����#��a�����>l2�]��_�C�Ψ* ���oֶM.?x�fI���ݣ��N)"W�)s�WN�Q���v��S�~�����8�׶s�&B���W�s�K*�j��=/�?�ۙ�[��_1��X6����A�M�:}�]?daycM�	�̒�o5�������H/� ��6�d���I���;��T��f���{Ll�zA}����0Tf�IY�7[�h�&���S��"����U�I��G}��'�������'�#�a%���	�m��,�#������%U��:���p,�>#X?B�O E�w�h>x��x�_�{w�V��zwC���Cl�����h�y���ni��y3�JB���[�V���^
7P�4��L��7B����wړ>`�F���-��FY�+}Y���O�vù��1�UE�`�5�*�>	�턭I-�BϨ�"�X�%n��تt(��r��\����EOD���2[Wq�����a�R�~=�c�C4&JLz���������]{ĎŊ`P=Ņ�P�9��!}g[���6��,m}z@�~��N�EgǓ��_�l��n��a����4�*RY�m�n�8��N�eOC�9em�8�&n�*�S}��w���j퟇�f[͠���i�-b���
�������v��0��\t`�#�Y#m�"y�m3���\ ������O�#Z>��+�M	�Q].���j����۽�������i�D3t�6��]����<�-M�fQg�>a��"yۧ_�z���1=ڵ����?��#*�s_m��d�Wp���L2P�f� �����l�m�@���|VY��$'��$`�R���_�����y�-T�Iao��6ͣ��c��N�{���~�њ���j�.��y�-L�2a5�&�e8ٯ�֛d_���	{�[׼���K �2,�(�}�[��=sd3�Z��ʙm��T5����Ax�PCT`�&� -�m��Hg�������H�(�N#��p�+;���]���+�ruܮ,�o���aϕ��
�4�)�8o��V2ܱ�^D3ג����Օ��f����6W�� kߵ�6��ð��f��38�`���4����{5tz/��JL�pI��
�X��X�L���@D����O^H�D���?`OU-N�C^����������f����sM��Υ��\OG�o;RB\���f�����KQ%gK-0��j�_y����������t�n�܌�5���ǛoғY;��=�]�)���_��l5��>m�5P^ԍ�;x�����6���    �O���Pͣ'ا�R5Q=�sʃ�|���걊N {���	V�τ�v�i͇ҩ������i��r��� ����'�	�t<�����M�ϻ�o�q�g���%uYV:RyR���a����$s��T�Q ���`�5<�P���a�t�8S�J�%��Z��֙NZ/�5>�CQH/��M�.���%���{�Ig%�)/�0��"�|u�#M��(�;&Oi+������f!���̒������د���\����rQ,�����%,��(o

�Lz(Ţ�K��&�Zr�aی�����z��ׯ��}�~��^3u���В܎{mzU�-誼�=�J����|K���$T�o9s�K��T�:k�Tq!���e��u��tW�+6>��{V��8�K��1�{�^�6�YV��h����7���X�2�{���M�xz���@����8f:���%�+[����Jl��о�,������;N�k����WP�������w�VM��"ۣ��g-����ȈJ26}'@�%�h;�H#��X�}�~��XiADyQug*��7;/-�{j��nn��
�6<��<*y�T/|"8�@n���	��JB�0�$�����>O�r�z�5�p.r)N?�W�[�_J�P�&�=�X��-�y�&<��ܖ$�C�4�XFM�a	fL�$����c�O��J$7짱ة��.K1>~w_�����*,���	�B˿p�1���A��R: �`-RzA�p�������(��խ������f��y���߳�|��Y�����D�~�=Iꕠ�>�4�x��%f"*��*m�:��K�Ċ7:ͺF&_q���}%���'ӒO���m�]��v�a|��_t�Z2�Յ�`n��s׾��ș&m_�|���Ku: gf��Lan�l爈�+]a��[^mguZ/��nq�}��t�ӣ�Q��isj���a_ԉ����)��v��>UEZ��e����q���(Q3Ƙ_���|0�:���]qZԟn���p�jB���;]Cӫ{����L���I]$�K/K���TT�l��cH��jߎZ=���'�w9ҥk��p/t��*GM�m(t�,�]�Vp���(	P�	��:S�:��+����I��f{��G�uJ��+=����r��������'Ś|c�dk�ċ�Y�*?�:���qGR#�.K�VD�!ǰ�����.v3Fuuŭ��
V�;�z������W����,��~��ӷ��7ߍ������*Ö?��&@�d��Z�aNI�3n�ͥ-�$1!+R⺺��p�ү\�~�n��j���+V���|�P�y��ꮭiI=��f��ّ�K�ə��s�(�b�q;d��Ml�sI�q)�Ҹ6�������x�V�n'n��Ӷ��~�3�Y��iLV	���3�� �n��@	M2-I�s�ӣ����?�Ŀdu�&�!�_��L�V�i�>PП5o?�A�W@3�2�A��?xV�v&�:�ˆ�q�C*���A��l�g�����3�]>~�N���)�Wc�_y�������9\C	IJR+��׍\y��b���9}�Zރ��*d�x�b�
BD�n��X}%�|�N�v�^�x�K�����Mp������2ŝ��Kk�Z�A�_��Q�)����&��E�3eB�G,�YK�),Zx�Z"�N��b�Q���0�&"{=N�OSP�X�AeD��]�_~��8"E���8����~b!�Ӕ�ZA��:�Ͷᘥ��Wvs|�~�I���O�����p��>�F��*#mV������U,��2�%�=Uwo�h*7�6�'��Ͷʨɿ��D<�O�ّ�w]\�yN^�C��u�C@5`vu]U�����^�������c6�ą#����n��6I�Jb�XJ$��P{�;G��h_o^p�� ��}������;i	z<o��bh�?��ˬ�JUx���$5pR%�t�j��kހ*V�DDepĴ�La������o���m�jlH��afD��';M9�vwR��������h��N��\ڦR�+=��.��1s5 �����|����\YS�h�Ȁ/5A'z[��������ڼ�̻�
c*��'�ZS��f�����J��
�!j/�1x������|�^�?�V�CcL-�	��>[��{(�N9v/[��ء�W�7���>Z��[p*����NBV41��r��h�M�n����vh'"'����}�6e�#�2��ۆ��?���S�(Q��x�u��g�AD�h�رtQ�~�h�d{_I2l|nP׆��e���(k��^;Km���r8������8��9��Ϭ*:<q���3���!&Ch�^^ۘ+܍p�����
{�S�u�|\��;��Ĥ{��̷����<>�ݭ�=�2��H��"be�'�S�2� %�[�$��T�-�����+�&��?`4>w4��w�K��k��d�E�r��:PV����S��r���V1�Z.�1"$T?2z�#�9&af��Α��Wʥ�&��dS��ىqm��{��1Ξιަu�ބ��ū��:�C���Ɵ)�=�8���F�&�4��p*U�P���ʑ���f�8ϽvY����
�%�	���O�]旛�o�f�X����~C�lmݼ�Դe����Ha��(\
�.���|�~*��L�����"�r�$�1;�^���n{�z��ui��p���İ�
 �-��6LPW���a�I5���Dt߹
�డ����Q��vL�o�Q�+:�_�O�a��o�9��v��AB�$�iR�MD�Wa�:�IȤ�-�ξ�d>`��6���}+����5s)��[��W�lm��<���v��)�eJ/�f�N�z��s#ES1��z�@?�䐹���΁�)�����-y��h������G.�kY�cAoS�{��,�X������fu�|�m�S,O��'N���Mhg�β�ҿퟰ�sL����q'Ò���鏸� uo��^�z�m���J5Ē����T�o�0J	��Q�L��:Tŉa��pi�_q=��]1�}|��/��J[?��ț!w8X{cT�_���R�,ڜ+�)m+)VD�X!���5�� ���"[�5�J��_�����H��:�u�q��6�>��[9� .�ܥdz%��=�d���,�֚@W����7�D�ȅX�~^'��kN��Wn ��Rw�y}��͏]���"���1�t���v�
h����fV�V+��ci�W!�
R�}I<*��#TȰ|�oD��XfB�w�>a��|=�,�疃����LY��ث��Pe��@����jܶS��V*2��z�zP�����\~/��;p�+v�.MHK�Q.}�^����n����;�=��V�8�Ð��?�$iק���$Ƒ�B,�ҘY��a[y,ZICd`&YD|�Rq�j�K��;����b���Yw��^�3��)��ӡ�>�х��6e�<�֤B<]�}��w�$�V��C�<�T�~N9DM��g�΀~�i����V�wU��S����k/ץ\;�~��?8���ƊIs%���鱼{�	�W$/��#�[�Ԭ.L�&�n��}�'�*KOn�ץd���T"c���j�^J���&�2��o-Y�Zk�?�n�Ge�� (�1��g���K��z�X�T"�W�h���{������f?��c������ݬ�x&X]^?�Iq?��_�u����Y*_'@z�h<=��k�*E�l��W�M>bW�}	�%z�n�����eet��%��u���v�����Z���h�M�{J @��Wؒ�V�I�\�T_�_�������sl���U�Q�R��x[�ν��F�[�r~��4��w`n¹Z���R��U�l��}(q��JZZ��6��r�O�Sll��6���8E� \�85�'c0��n�o�,���im?r�gb�w���� 7�8�j0c�;�����U�i�O��U����t��v���Z{����ql��3?^��V�����T�#n�I�"�0$�4�+�4�P���f����*�dc���h��p}��dt'���Z���'U���@g��b�O�=    �I4�0�d?.���u�D�d6DQ��>�E��
���+#c�S�0�*o%ΑvE>g�fE�����7M�.0ĵ�v?8�	 %�� q��?'~�p$�Ah�\5���܉C�	�s�;�v����n���������8ڜ��[�������5r�}�HR y#�آa�w� ��>I��E��X��F�K�9�wʥ������4lP�޻9c��<g�~��}_�ox�v����ʅ��g��g�pOBXÝЮ���U�$n�P"B�B#U�C�_aWf1�.B���@�C�|��aG��ɝ�����l4�K�C�� f��~;�杞7�R�T8֔*�*��0X}��z�a.�u �\�6�m��wB��?1}~�����6�h��\�VZ+�t,i�p2ӛI����X�?K�KU�U�c���S.}������C"���sW�ēL0�U���ݚ���4���
^G3�ci��i�#N�ڞ
�D��=��{ ����;W�	3�wړ>a?��z�\���w$���0:�����ֈ��Aۻ���i�I��^܉;�jhEZL��d̨��z���ض�������|G���0�C�m>�^����zN^�A�K�s�z7��{�Ҏ�A]i�(2#	+�)\ʅ}"�1a�L�3$XP�͜�K���;gG������/�g��ޜn�F}o��?��#C[p��V����\ڞI��2BSN�KA�8�:�J��'�	F�I򷖟-��F����T���V��@��}2�4D�
���w$<��;�cl�a�1��a]�He(m�1U����a���:�+�Y�g8�5J��V�I4R��x�"�i�vF<�9�a�$W��|H�*6[�;��]�0+@�O�~ŻB��}���d�@ΝB�����{.k_�Ð'��2W�04�����41�w��ʅ#?R�;�o����#S�ysşc���?��`�4��Gx�S����/���Dֹ�۸氚H�:(��� Q.���~7}L$恚P�E`��'���+B@*$�
7¯��'J���:�܇���ﶈ���q���O,'�EA����0�K:MjC�%L���A��CĬ,�iaYS�yrl�U@��w����X7�V�4u��l�]3e{�vo���΢ί���O�=�ԙn�Y6�Кn UI�A�5��o�xA{�[v��wZ� ����O�_a���>��Y�)�I�f,=�&O�|�[�����`n��8��� GD4P�,����@�����CB����{�^HT�\�(��C����M�:פ�������L`�M��iD�J`i#���4.�����-D�(�Qk_c��ӹ2�bl�	�~~��r�w;��4=��pu�`%�'����L��?�ƥB��X˯�ڿh��3�ѿ�c��#��c�fY�$ �ҹ�.�Y��z�v����ܯ�����L�s����w��~i��`;k��t&[��J��$��������TuDa���mCy�����9���R�ï���7���RS>�r�ϗ�cț�t:����Hr��&��%� Ŏb��1!'RۧX�oUL��H�K=�$�*��e9�@��UB^�_/!�!�ɼ�SS^�l�.��#
�0a�G��[7|Δ*H����&`�l�a�8�Wܓ>a�.��Ş#�-
��ig�r����'ډ}\��_|]Jc�����iݨ|9B�L��"U���S�m����G�L��+a�Wn�?��p+�����\_�4�As�8���w6J�!Ӧ_�>r(-��e{�,�Ă{bB���/��Rt$����s��\�Cǧ��?��!�]Z��&�c�!�f0�`3�z�-�[5y{Ub��º�u^W$V@�����J���v��N������<�'o�H;N�:{���:�B�ʗ��$I����I��k�1�Iض�w"�����ퟍ>jd#Z�1ZV�>���}%�|��N[+�����w�v�t�p�_�X_)޽�^)�?x9 >�L�I�<>s`L�=h -y��@��̃��
"}�O�����3Ϯ밫+��2�J�>�j���j��k���ޥ��o1�M,��c�j1s��� C��Ɂ�H�lADh�����7�����P��l���1����*�����~����A�-�S!��Ӹf��"@AY���f$՗�V��Y͛-(�!Ax���h�����U�ԣ���td�V�s���ֹ��n��^:z���L�E�w���2+�덩jO����yLK����b۾}��������7 �c�*�����ܳC�	*KD�s��\AT�T��Fncb��^I#=��-Z=��	�3ĺ0�Hl����a��׏�����Q��mH�i�->��v�..����u���c��KK^�OPM�/,mfZ�u�c��bWv�W^/"ҁ��U����V�jP���������CɚH�s�TY�6�����P'̥����edMnm��MhK.���ɝ��m�-l��<���;�!>�[��K�J�il&{{����C�Y��7��� ���-VWAL}%��1F,	�ꙷr�	��osՒR��Xͅ����V�9�/�}�����M���`�EiyV�����<!��{u<�K�&��5�Z���HbJ��p��K'�94+��d�Un�I��x���>^U��.l�>����]�9�����3�´
��8��Y��P�:s�
��َ���S�ۍʰ}����G�+HХb�/n�;�����f���8>F	w�T���Xjt5�+#o�6s��Y����*Z��E��َ��
�����m��;?b�����77��.zC^B�2�y�ӏnZA�G�|�@���6�:�2�ұf	7�����9��;v����F��G��%z�|kT��ڭ�ۿ�h���v�4�++~�*��S�ĺ�5��f�J�
���"$	��]�]����+���'��F��&}�z=oT�ZGc�'�o�[[��ߋe���X�)���?92�XW,k��}������7��1ky;�!��@�o\|�.���Ъ��L+�6�g?8�+����;9�Twx�?�'���BP�x�T���G :E-f���ѱ	��SP��A�C؍��c�[���F��F��۞�fC<����?h�P�[�Kl\
Q���Ս�0�eX��Y�y�S&�ui���
w��;J���%�8��RJ\ce��� ������@��|#�t;��{ c���%�k�\5T�3p*6���1�t�k��]���#c��)�W�BKnK���{(�}����������{�����\Ҕ�#zF|�F��E��5�Ĕ9�
��gRٗ�����g�γ���ʮ��ߺ�o"�tm�Ww�$a㛋6?��kXe�uhMW�Z�UR�#��o��*���|�;�4��8�>��j��p�H�<S	2��X�4A�Ξ���O�dN'��L�B��>w �3��O!!.�i��=p�~Q9�o�V}�N��<��n�[�n�-������"#I�G���=�W)l��ÐAᏋh|�I4Q�%�2��D��l�d�C�d���-c�+�.��a��:%��� {Y�Q�2Nv����Iyj���1X��r�^�TL6�kJ�>���H��fo,�B/1J,C�l��,�+��>�>_ɸހ巩_2����?�2]�n�������}��%�#f��#�Ze�q�3�)vG[ȱ���W�����Uq\o��H$�Mvk�Nm4�뮻\��M�EӪV���|��ܲ3$��҂$ �]����g�<1R��έ��7�R?b?��P������Yy�=;>�(Y)d��Ͽ9?�dr��3lG��K��SB���g��Ǵ�S��C�V/��b�3,I�0�դ�.?���v��H���2�v"�0�I?&��p��ŝd���=!��m@�!ZрT"�) K����E�"u7~��#�2��ě���i�Gg���k�U�n�_��c��&�=���B0�9ÊlG*Ts՛㙫�,�@�����T�k H8�G�_aO�Ľ��!&e���ܘ2���,n���wѯ����Nȿ�/���]$Lb�n�U�R4��    .��\��!u��*��)���WI�F�,�����*���O���a�����{� Q~�=)#U�)� �\N���)��-c�<9�k��2��LFZGc�r����'Ӫ�u�NT�����,]�Q7,�<�o�-�֝���.-��L�U�T��~ǎ�/����H&o����!#���4���`���Vm�N��T�m�۞]%w�+%O��z���G*��wiic.�*��00�m�B�H���"O"�t�.���ƾTM��q��ҋ�d?K3v�+�Z�Qg�c�7�s;o��t��jp�T�Ra�L9k˯K��͘句�F�};m�rlW�ݮ~��H��;N�Q\q�n�f�_���$9ǌ?�K�?h6K�%g�`��F��0!\�^�T^�f�t�OPC k����RX���v�y��ñ�Q%c/6�hm�5�����98����^U���dXD
l������s�F�;_,�"��W��o�� FI��4Q��Omh�^0�ä�Aq�)����;��R� �����Z�_�0V�!�L�� �	HpO��Z�����^a�}��wk����z��4>��ö_
sf7�w��o���t3�K�����B��`˛�ٛJL��V ;��������7ZO��^a�!7ʃm�����r�u�F��4x�'wI��Fkl�A�@��;��d�;{�4��1��^��$��\�$�#Dg�+���ݘo�~zVg#�&���:(�1��!��gj�����'�ǑRE�Ӗ���"
�����[��̕�$Q`U�~Qf}e�����V��K����	�+�4��?o�ED5sz��y���zKR9E8X��6.�
A��"�܀���[��ɭ�]v,$r!Ru��r��ҫ��4�V䰱�Sk�){����WC%�������=�]��U^G�����UDMg�T��:�8�=sy�������O�.w��	�u��RY��{�w�X�r|�R���-��u{�)��\8҈q�-r����͈�r�oW����J�p�+�ퟢ��z�Л"�⪎`�I����x�$��6�����>��S`����T��
�L��U$���k��>I��_��JFw��x
�1�誡�T{D��Ϸs����>aS��к��ܢ;�Զ�lAP�
D���v�өk\c�o�V}������s�+�=�o���}�*w3I��]�ǵ=��Z��n��"6L��V8ӃX�Lx*�@������9�Ĉ �ҭN�XA�{����</���\�}ֿuis4�Лtn����6>Q�g����#�-��%UZ&���UcLNL�i�GF�c9�&�TZ�_E�)�Qr"��U�ߏ��>��"$��-X�OU1���K��l@�&g$ƞ8Ř�D�-qC�q�PC׈�Qc*���L�>�vY��e�~�׹�P����'nwL�^^��1]���Þ�<A�0�CH.�CH	v )-�p�_�����В/9�g ���!�O�ICAl_S%��x���EqV3�йq��۸�A���z\�C�*P ��m�҃��5j�r�p��B�����-�=_�ퟰ[�˱(�������Rl�^/�ow����w�%?�/�S�\��(�x�$'��
�J�ig_rK7�~�
Ӄ�f1���<s|0ǧ�j�؞�?�c18���E�E1�l���kz=�~;�v�b��[}�D(�p5�����f�����[Ka5rf�����e�$��;o�О�t��'R�}�Ь��^x��?8 �d���59D�nf�ˑz�.	��Y~�3Q.�+븀�I��n1&�9>t�J�	��A��"|f�
�yj�]����u;P&���Kۈ4,�P��i0bC9'��$6����&2y�H��z�V�I�����;:2t�S]RN`e���(?��1jtB`���b�f��{V[oR%N|�Bɗ���j�v��+�����R�h�Rl�LPʮ�_�R��M�N3��?��J��b��{�Tm���=G��0�d�̌��-8�d�S���7+dKޚ�v��*񫽻������)�0~q�&&Ɓ8DLOˑȸr���L(';-j�5U�S�ሚ�~����Uq���Wv �f�զ��|�m�5�$��?y	��f�P�"M/�TQ�UmJ�[a���l�
��^&dJ�h
��]������ķ�b���|���?c���a��ߞ���z���a��f�"�נk�(�I�mS�{-V�4��5�r,v���c�ܯ���,�`���׵ft(�y��r�W{V��PY��E���J����-�7Z�LVa�6mm�F��E���0!6���QO�఑�����B������M۷Dq�y��]���yy����u�*��(�2N%R�29�Ϣ�fe�I%5x���2�x�~S�>x��������j�G�����81�'0Q�N �S-�����>�ݿ!�j�jK�u�l�L	�c�r�T�A緅�W��>a?Yc?\M��D��~ue��!����A��+��|���%�������wFHd㈺F)l��x0�s3��@���X���u�7d�;�����i^,�^��')߯
������g��MۡL
;Rr�ab�Kr	�Y\a3!d�K�p
gu%��8R�=����m��I��\W����OXd��ݎ~q;p�y���J���D�Lr�o%2zlA���ț~9^�W��o���+���C|ܭ׉���R���S��q��m3<��G|6~p@�{��D�mG3�K�j�JQRiD5fM^ف.n&7|�&<|��W�𨷙���l�I�a}�
���&�(;�V]Ȗ4?hl�f��mu@�dX�r{;� d�Qv��?I����;e�$}'�?����5���8���\�G��gC�4�h��ms����'1������S2[?�!��>ĕ��(�۞;��'������;�v�?�[ݘs���q���8�c���JO��#����Nm�T�.�ڈubj��2G�����}���&Y,e�p}�Ⱑ}x�þU����v����ki�V?N�=_�Ξ݈�Þ���,�̈́RT R�E�:i'D��RM ����T_y]��=Hk8�*�7�T	کN�;i��^߽���<6?�z�*���G�^a���0�lr���!�*��,��0>���?�.���������#���/��.�W~��s��N:���_�L�4!o�^�<�v�KK[�@��%R��'ڞ�������o,e���O��їηg?�����{�W�&]�z��Q_ρ�m�;cH6�I�R�Hl	�66�����꽻gO��F�O��֪*j�U~H"#2ވH�G��l{�=^O����Þ���9�s�ڞ#�U(��>�!D�.� {���<}ǁ��]j��l�����%�6ŷ��r���m%�~���{۔"��lQ�,s, ���M�r�X�#	]Ȃ.ѕ A�W�h>a����\B}g��Nئ���\��sYq�/�nu43�I�p�_)�'��>mG5���T�6s�����彽b�_錧|Hs<�2���HK�h����H�v�\��rρѸ���Q����πX����L L�Ρ��7|!���h9��r�>��t���}ܷ{#uo\�+�|~�����y��"��/H�{�If�J�[$�,��"��!BU�F?qe$9BN��	��q ?d��H�Epz_�N/Ww%��1���G���}#����c�<��i��Ҭ��:4,/��6��j�jg$ +a�?�������]|��nn�E���m[���o2��Q������gK��\L�D�M��K�	tQ[�!D���o_�B&�Ӯj�R��	�c��<�?&�}>�/�*��q�j���O~;1=%��1o���Ր�`6�&X5�$�)�<�ѫ�܉��#}E��	{(�$ (�kkΧpXi��|�x��[�������������O[��T�ه��
���Wl�U���+�����+���)l��f��W�i�2�G�.g��Z�^�p�W{!<� ��5\�[l52l��
��aj{-U��
�Q=H�H�z_���Y �ox2�    ��4��8	E��Vx�=�����Y�~k���EЗx)�`��gT>lޞ����}ԑԏP�xAQUy6���/������(�v�`ѰZ$��v��������cP��q\�����s�a�>X���qsV�{eC�s��P~���,�$XCЛ��ͯ��U?$����uQQ�^7
��}��R<��X<}�A����^b�,�E�w�1S�3�%Lj1��8��
������'�	;���\]�@�����U��I,����	�.����R��WܚcnS�hw&�z��U���Hf���Psx�V�<��TR���.��� ���k��[	A����!�E>	�58�1�Ş�2�?�X�hq�W"m�*n�U��)誗'�<�]F[%�yO���?`7}����:݀�ZH$��h.���_�|,�J��*~S���ױ|�vXSK�����c>��P���j����W>aOR���L?�_�?��=0y����#�ڑ\�?X�QX���}1R,�Q�̨U�}�҆4�%m�=9
>a��8�R�x��vg�_���/$�|s��;��7)��Bw1��P���TR��$�����Hz��U���;���N��^�'&OD��"-,top:� 8�(�)���=����Q��E3=J��qC������Q)DN����W����<�����ܱxY9qy���Et���v�٫3��~{*V	��� y/����a�b��X��͔������T���o��~����<��92q�1�/������U;�q'����k�Ђ��U&)����>=¦��ܜ�Nd]���x5��U$���N��!�m7�m��J��ںx��D�H-m�����}����Pz������8��.�PdBdJl �Dw҅�<�]niU��}���+��O�W�s���OW�di���bg�+I^������ކd"�8A Wi�$����,83�Sn[�d"O��>���߱�R��H��6�<{ݡEcdf�4�����R+�#E��`���ɺK��A '��QY�,d_ǰ%"i�F��0	Vo�@��'���6�%5uZf�C��6������u���=���$!�*��}L�'#�mlh�[j��_2��D���Vi�&�F���������%����ܓ�2�]��O���b��U���'y5:F52S\,���<�EK#�0�=��\��=��#:a�(�_��A9�&�e��������X��ϥ�p�❝���{��=���DW `u�a�5}Gl~-"��.A���	��|'\���n�����p����.���t-ۂ�?C!SN��,b3���UԺ	�����.��B��`�R?��5_*�+B�O�K.�h�����b]��7B�Q�Z"<��F7(?8��� ��E����{��t�b5
8�ATUa���l��35�+=�)����iQ��`}l�sLy��yo�F|��u���;3	�}L��Ň�Mӿ	�5=���8�� y�V-�~�Z��mE��a�v��v?/��~>�Q��˩�]s�I뭯��pC��b�4#��FQEm!0�XޞM_�@���!f�D°d��@�vv�J?������nawf�V��r��#�NI�o�vl5?��6�CR�A�o�1Ä���pV(LF��)�1nD�X�,��J.�������jK�c <�T�u=<3�Ha��"����)lZ�
w��T�����b5��
g�������H<��i.�Qy_	�V��N�`��+�?�42⼺nN"�����C���-������R	�B�+����y���*���0�Ͳ;�,�TB�;F%ߩK��݌��k��'Y��i����3zv��z���	����9���y��\du�A@E� x~<�Y��\$�g�[ւ9��E�W��O�2�Ē	�Y�l<��	��4]����\�-}���MF䬦M#��>K(��̀v(����B��Œ��Z��J#�����>o��Ŏ^��QO��ެi��,.>i�noS�lH� ��c����
�뒚�]b�
9m�vp}S�L�]����JӪO��}GC{��z�����|s��e̚��[�㘹��S��Sa�� 	��{���
9��:��ы7J&)r�R	�9�G�+�hV��ڶ��_���g����^l�$�����%���S��Á��U �!����&4��Lp�%Gao�yK��] �6mY�}œ�$�(�P=�qW��ⶖ��ܗ����=G�������Ѐ���f�T�Sd�]$xsd�#3)E5�R��u�@ �7�*�O��8�}6,]Ǿ^ޗ���h�CF�ؘ��~0�ZXh�2S߈�4b7�2�g<Kеhէ��RRiL9�+��O�o�Y|N�lh�X�Q���pSZ_�J����-'?��#�Y!�������<�(�"��1{�,w�M�M2"���W<�ʁ0��@��[[o��Sގt��J�:/�����{�e��P)]��+�A+��ӽ�M� �߄����/��
����Wl�'�F��}�,��"UV��E�ʒ�\�(C�9������Q�*A��T��XT�~�;.z����X����¦qdyr�#���[(^�\�Ǘ`~`�1�w�>�z�ت�_TD�Bk�J����F��!��%��X=�"[QA�fQ�0K��R �� �Ȝ�����\M��SC~�3id�,����n:�8{H��6�-hA\�m��JXFp"p1�*�
��'���;����Jڗ5D]��|U��8�hp�x���ymP�L�Ď��@��B"�z��H�E���*�X��fz"C�Da<5e��(~?bo�˃�{�������8f��3�	��~���H~��"p!���"��20 :6'���K!�La�:h�BO���L�\}l��M�i��u�/Co>A�n����:6�������E�yC-l�S�r�0G��P�N&iϢ.Ὅ9��Å�_i��	;��4�~Rc�F�:H0<g���1��1�j��8�����+�Q�.����":�T0���4j��u��R�D_��8���1_�6�H׭`�YLP[������+�i±�?xY�n��
x\�c@㴝��i䢅�P}b�,#I�|[?�v�J�O�)_S&7N����|�}|�RNN���	�f��~�A��F+R3��
@(�@�GȄ�<B���]q*=h�3�g�;���r`�V��,6�Őq�:})j�g��s_��4%��aO1�AͳH���:�Yf1%��% [�.k�]��35،"}Ǔ��2t��5G{*Jy�V���� �Vx��dm��?��$��Pz��R����C/��!�Z�,�cITu�,d���������x�Ò�7���N�Ɩ��Si�����I��R�`�_���ۘ4���}�^BͿL�����}T�*z{:j!i\�+���S���j/R��j������N��F	�uC���2��5�qf�Q�h��a&}RH`�ٺS$��ؐ
J�1���+-��R���m�攸{�ZFa��ɬ������ �u��NƖ) ���8qj3v�晢�CFМ.�9£Lne:�'���S`Uu\Ex�
����js�m�W�R���1�E�6����ƸFN�6.�����է^�FA{]B	�L&(��W�x��+)3RP/�;+-ߞ�}��E�3�Nۡ���ф���6��걌�Ʈ�D�
��X��x����ɕ�_A���؝�T��ܫ��xJY��鮠[_��)|?��l{?x8���ı0XT�f�X���Ɔ����f���P���Y�B�+u���o�>Z�pH�ș��`7����~<��sV�������
*�JB&�]y�%oCìBz�E�̦��%���F�@'_�]�����CL�o�1�EE�!��#ۭ�����M|B�����+�PB�F�"���!�#ԡы��Bd2��r޾� �h])~�_���7	W�����5��3z`=�,~��b���˥)��Ԍ�/�gUo#ޑ�
 �T�B�fp���`�v/�W    �zi�k���zO��p\�� =o{osN�i��|�=�i�(L<Hؘ�|$m*DJ�Q�Pw@ϔ�6y����*k��+�����E����0�N�kn�7�'e�<6�o���������-l�[BJsz!��I���LR3d�6\w�̦�SaQ�|e��.���#�6�2G���yu;O�S?WdӸHܝ�T�������QMH�W��B�0� l�ߟ_�-4�	l%���j����~�8��������84{pC�����]l��Qv�=�q���3���!\���P	E��완F��,�H�bx!2���_��	{g����+�$�\�|�	���-�N�XM}n���a��z�h�9��qM*Lײ�.6�s�h�oj]�T*"�Y���]n��������	�G~&��xk�bP��Q����#�Աͤ�0�\dwبN��۞+��tѥl9˴�������HO?a�].O=�[�-�ɿ�z�_+r���Q��5�T0ӝJ[�9���֥�yGG�&�ĬVY����X�f�#����~��F��ϤKj��d��q-
�P�#K��E���ņ�zB�S��pR�0z3���� qҿ=�f΅�߬� ������r�ߕ�io��NOƭ�RZ�SGp-���\g�C�&��a']���㚌E͝p�}ֹ�k���p��.���ǔ-˾s������;۠��8�<E>:П3ߞ���0g�~�p��|E�@�<{�ˑM��t��f,6K�pŷQ�ۼC���WV��h��=�+���=�yxkZ]pw��Iy����h�����`�boc��q�[O��ά�l�Q� �K�P�0���ȓ>a?�R4�����n̋��\�8��<��]�o�����_��K�Uv��J��xoA�T^Kw���	�@B~;��k���L�O�uրjV�m��]�vmd��\e�s�����}��p��۠��aGz*l�L��T� �#��h(�~�t��J�¯ȓ��r8Cn�v�J6*^)x�{�uR��~�9������2�Z6yǬ�
�L��bHP�L�����²�Fd5J��W��~eL�'�4�y�y�`��&�~ʒˡ�N�:Q���a�qU��_<���Sh�O$�;dI���d���O����o>6�^�ߧޠ�(�w��7��-�z��C�j��5[_b��a�឵��F��d�+L�E����yy����)a�iXӣǱ�������2^	��/�|�gq��ѭ��[��X]���꿇=�]�rs�ߠ�o�DL�*3�r�����sdj�g�U�-��SwR��n�~�HJv������6vb�,������Va#R�Y�К�AB8Eq�:E�G`� �؂�2�0�%��	�j�H'�?��yo<�K�ߘ�ڶh�[ֻqr;��{س�����	��홌f"=bRU5K1�$�x�b��0��~E�����n�{���}�ח�V�um��Cy��ܸ�=�/�G�GR��&0Q�y;9a���!��Z�@�g$1����Y,�99�_��	{�h0���oaҤ{'��&�^�2��m�T�������b�-��3��mx`�+3j� ���K��������#O�,���i�{T��� ��Ni���G���'�/�HF,��폩�e[E�P󷝏���È��)p��%¯���;��K���p��Ԛ����A��۞����F?���5J��҆aa7"����_��5o�}��Z����c��F��Y�g��.�A��Mp�k��n�@dk���;��烬v?8A�t�Һ��E�)�Յ:�z1Y�Hb	�$�Z���@~��D�Z��Cl��֯�%}�%�b$b,���J�!P?�]"֐y� �2��W�uA; �3����w��m�-�"��Ӱ��7k�&�]T	�ej�T�+T+p�벦�e�
	�=�淓zv0R |&B3 |�l<��$�u=�����l��B��+l�A']u� �(�|����.���T�������'�R_~{���՞�[��Q*5�HМqD��D��a��A}V}�_�}�^]��t���l���'Yk7Mi�˙�%gެ-�+��;O$Ϝ���Zw�~�6���B�l҆6]�~�wҖM�F_22�g7;g{U=�
�G��y�어-b�>��uٹ���ء�Li�>0�������u��erH�'����"ؽ}{%n�����ÁO�$*]c��-��'*�v8\�T.����x"�i{�A�/��Q�ʎkb�6�v��A<$��J/���#n�>  4�J���r���@�pA�U}�Z�!�����PRE��Vl�������b��YM�L���hTҖL(�(aM��
P�5j�WV��L�S�t��ٜ�5[-fp<�E!ު$>x��7�F���� �t�*�5�@����ev�m�N,p��|'���y��zT�9���{�vm����4ۯ{V��Q�kb?�(--,$��°O��2����,{�z+#=$��}��<��e�ܯ���|��X�2��w�.,�^�ǘ��,k�����na���������{���Ng��W×\�!�;�4J�'D�;0IeB�����v�m������xQZ�����tW����^5�;�l���-<�.Dn���r�l��`TA+6aK��z,&��;b�O��Jg�>�ҧ��H�6���0���^����0_�C�^���c��o�_����?�	Y�&�߶U������� ��F��s�
YR�8!G"� ��9���������寿!���v�G �x�忷�WN[	�ޮ���%X�V�*�`����iUa5�4!B���B"����3�&	���K~�����/���?N����R���Kϳ�t���������_$AZ�o�����?���9�������Ssδ���Gw��x��C�2�ߋY�����<d V1�J���.:�#�B�z?&5��F�Ō��L��ڵ�Rͽ��.�Se����Y��x^{��"<�_�wZ�	I���m<#ҷ1~Ho�/d�$ Ζ,�W4����fOVPW!�s$�}��}8R��bv�ɇ��8�aI0R�����H3z�?�.aq0y�CS㡑Jy䉬~ُْ�%Ʃ��g%��PZ�Cj�y_��?����С/��I��2y�9g$��2�h0����ԁucU�V�d�`�����
�B�-���T$�Hb���䠶?�d_θ�#�H��K���*��]HK4�kW�%�E����}���*;jو7�9�	4�8��V�Q��aG��)z�E._�ͷ� �;�},�E�z�|f�x扣�d�X]Z]#�����66����fD�(2'*MCA�6����Q�"�.L�s��ԋ��;a����u���&���6烥�*��=�PF`�^���v,iYa���MEև�3s�ʮoQ3tA�I��!�܋א�+�>�̩�ϮѲZ��ˋ�lw��\��?3M��^�Kb��SB��A �X����e5�1���"I���#��W	\�'����jX�֍���nG�Bִh�F@ח�͡A�ކ���T����C�7��>YǕ�'����JH���zxM�n���v!���uׇ�YدT�J��7�$zY����6�T�d���w\�B
����k�樾}�{!�[ӣ�u�cr%�ٟ�[�����ܤ0ZJ*.�H���-���6ٓ��u�����JX[�2ٜ#@<,��+I��X�4��(č)F��֧@�\�8K_��ɶ��� ~��Y�q�YV;{cuy&���r�~O?8e8����,c�-8���!&�| m7�h���+��ǚYC�u�'�7�Ԯ���Y�yy<�u��^�E���w*NU�� "�3i�5���'* هv5Ɯ�T&�8�%$����r�����mr;<���_E
���eo�f�2�5� +@�!�k��D�Ü�v���V �C��B�T%�-ږ�{��	("�7�d탨O'�N�w�ܼLm`sҍ�>��%����p��\=��I��p��.T���(�a��'�6�'a%��%���4�4�#l�[����    �����-�H�y��o뎷�]�3�K�z}t�����^�O��#-�JSk@+� t#-xE� ��+b�����|�o^���rM��h�Һ,VO��{I�����4������Q0zV<+}D5�RѷH-�xF��������6���WB��:o�ڃ��k����?0?iR�O����m�^c,Z"e�&�I��?3���t����W_/���¸��9�Y���EW�3��Acjunq�w����^��J���+�O�O݈��#'���Ϥ��t��a�i���v�ӵ?����:>����{��#.Բ���J����[�,��C����w^��Dh�HBN�E�r��@n����o��Z`��q#�2H���v�����k�E������3-�v�ӵ?�	&�͍ƶqD���dA
���$~�kM��K}��f!��wi͖hz&���l�خ���������5�������H��._A|�s�n��*�r;� ܮ
s�� �[ǽǚ#�Vj�����@p����!��r�
	���`����5L%Op��}Ň��ݸؼ��c��G�D���x9���G�ps�=��vڇ�v��A�X|&�qBoF1��Cd��TvD,p��̉�|�S�����j�G��쐧嵢\���%:U��.�N���F��ɩq,x�z-1�Fm�U�)O���E�E�"T����z}����X�.e�� �j��;6�Q������?�I���+$�E]�aLԢOpW�b�W~$��L�*�����o������O��z�*�;�䡂K�~W��(�OAo��;&�.[���{��qMx�@��#�èJ�	�L��P���xs 2�d�~���v�t]9��x���@����_KV�'!�+��χ����aq�x�(C��q����ٮ�����fp��]hN^!s��V���_��[����z���f��{�9�v���ؙ���$��?�EO��f�qM��3i�|a���x�5�EuU��g
�F$�Kʫ[�#��#G�i�QT������!�������ݹu�c3�`�ai�6J*�q��;�a@�M�	X@ĭ�P����=��+F���ڼ�v�%��U
k�Z�� p����1�H*~;!ٛʾ%Nq'���됚w�#�)�m3�xx	���7W����L1���t��>	񦑙zGá�֘��!*����$Mއ]�框�tm�o����q������+�	�>`׎���>��˪���z�u �|�J'���m��� &���\&� f�@�,g9z#'�֯#�%����\��V�z���!�}pgN�ո�1���X@���}k��4����;}G1y����r�BJ'�����=`�'��D0�Ǡ���IT����̆�����m���QitY���u�U�?�bV!�{�;Y�k<eZ���4"��#�L�A!�#��Im4xx\����~��4z�Om{ލ��n��6id{�c��S����ı�q�[^���b{$q�5.H��ۋ�����F�����~P�֙s^�(uȼ�y�]����&����)kN���Q�A�7�N11����]� jy�,N9��YB�Pڨ�LԸ<���� �9��1�<�_�~��/�8mE'�O [6����\���a���'<�f*���	\J��H�.�_x���� �ތ[5����Oؙ��M���j�d�b|���U����a�&M������E:w��P�ʙ]�Ӛ�q*۷C�@B-��fا"�^��l	�o�a��͈�Y�l��m�+��z/E�6+(e;��*}[�K"����L�>��Rf@���e�����Z� KƱ�[�4����
�1q�#���6z]�.>�MG����|��#����!h��t���Kтs�C�����,�P&J�g(3�#�Zī����Q�G�I�*�S֎hs} �=OR?^���w��~�����=��h��G�r�V��ƽd�Qȡ�7X�Cdҩ���^�J*��7&w}�΢�Cn�Q��y�֚� V4HuLX\��_�d����Le=Nk<�̄�B"beN�MkoF"���nFG"�F�	]}C��
��զ����@wy���
�`��e?t��b�`eC�p%m���v ��äs�Ҳ����È�P��4Z�+��?b��Kf�Z!k�g�O��.'O��ݫ�Ѷ�����cIzJg�����͚��}�4i����v�Pz�o6T�r�P&�'7��?^DQ1�t�&�,^��p֦�܏����(z{'X��v3c��8��;4"^���'���h�Q�I�,�P(��-��G#�H8�+�����/��*��׀��t^M�����*k�'�R9�a���K�ˋ|[�}֐�`��:}��	�~c��*|P�ڲ�.Y���0�ˮҫl����(���j�F�y��TY0vIQaM�����G���$WN�[��f�)^�8���>�]t�I�ߞþ��e�����o��ᔡ<��ǌ�ؽ�;`�-Es=۽K��I:3Bi&C��,��F�Q�:F_12�|k6��ك�f���ہ��`��N٢�����$�!l�&o*�l��M횉X�-I���%��.�j*q'�.��x��؍�1ʴv���e��I]�ug{���{���p��1#ALA�Z��6�Q]=JPa���?�;�1|Eﭕ�hyo�_q ?�����u�4�#YS�3�v��̝OAx����y�{�aDҢ�.���+L���E]�&��
���4����4%�U�ʁ�؏^�u�L����Nޯy�ΒU ������w?X�C�~_���|�|ac��܋�v�g��xJ�Y��8��1�����ߏ��G�皲9�rӤ�`�d}t���2[���t��?��MI1�Q�Y#�(vFȋqPîZ"�wQ�>"�D8�Ͱ��oȓ>boj���������g�`Sz�R�Wn�"eY���(5��-XE�����<^��LLX��^�;S4sBߘܥ
�n-0J�D{	U%f1�ע�[/*(�wL�<�A�Ŝ�4���} �4X�ND mBԒ��E�?�8<{�
�)���(��&���SR)���j��>��?�@R	�quoa��)-���x�]�����6iH�/L��;���W��ʁ�����������z��O�׹SV�I�����*L���%�C�H*���"g�mkr�I%�؍���Μi\��12�_.]&D�M6����p�m��Tu�x7N��%����M>"�d���)#<�0���ӝ�E���Q������7
�U�`c���%ڹFc�(�zV����F7�&<�lm��t �v��)Ꮇ� l]H�v%U(7��
�`a�tC-��L�oLS���t�_��9=�k&����|��b�5s0�K�(��Þ�i����G�E�y�.��W�5��9���^�`����u���+�z���s��0�;�����G���j�!^��y�S׶?�
�⁤	}��:Ba0��ZЮ`&��"�-�6�HӪ�����Fo����G#vv�W�`������@V W�o��yD�/��iU�F����'��M�fv�gBu)#�A,>2���\�>ي��w�W��'=�{�kz۳<�V�,��)Ms�8t#�쵪~pN]��r=́7q0WB�a4�&�I�Q� �f�4�8q����O�Cw�jp�Z�d7��\��49��W�Y#�]��Ga����X�t(��"zB*��YuoLaR���,Y;�8rwyK�W�%�`#C-c2{�r:R�O'\��e�Z�����Y7�A�f�<a2�P
L�&�B�v��j��&��ݕ�䲻¢~��j�J��	�&f�Î����9x"i*MV.|�g�D�|?˧k��ݷ��YΜ���<��y�ͷa	BS�L`�U*�K*�&�����:�~��ٛSdU�./���=��`��[,]_������G���n#�e���-�5�e�!w��/(�~��:J*ǞHE�}%��	{-��^�mpk��?�¸J{������������/6p����	�D����WX���fB��a�˥݋��zA��+�_�    �`C@��ނ���|���e�:�[W�9���� �?��P�;,z��F�{6�2�_^��0V�tq�y��0�y��v.#S�J
����vm&�^�u}���l����so��s@�v������E3�1����na0l��	W�l�b�=
S�A����w���8�}ͼ��@6��8̍h-v�Ԇ&ax� m�Z~��I֨"2�9m&�9�m�ǃN�K�4)~-p	�+��-�|E��	����sIec۰���+��uz�/]tǪrQ��ݻ��xv?dy�&��25''�d�\?�,_A�G���6��y��A����f]&�҆s{���m�Z�E���S�d);�`?��﨏� BQ��%o����{��ԋ�%4)d�޷!E_�R?`�a�ʢ��x�?�V�O!!+)]Z��dt��]��q*�W,��P��T� �FD׋1��\^qO�k;,F���
��_9�?ȓ��~�^%&Ej��|r[+F�OTV/������(��M0郸c/���e6<ff�^��ӏ�'�@����h�J���~�����oWˍ*hS>���ɽ~�N7�a�)r���{#�Ot�\�7���#���819Q���N�T|��I�f��+�?6`����W��q;�<�]?�ʂ%��*���8o~1�!!��VK���e�:l=����ZmU4�dB�Xu�����o� ��]��[fE���l��q_��ja}ԭ]p��9�ǬK㪎bQ,�y<��[H�TR'Ժ�-_t�hɝE��j�`#� �b���\��#����mp;[�כ�i��e�>�T��)÷O���Gl`]]sK����;(�9Xd��x\|BI�#��`4;���J���;�nE�f�%Z��H'J"I6�3 �"$�����k}w�Գ�Z��T��D�F���.�E�'��O&���"=���d��h�\�w��ͩӈ�V|^�OjrM�~��ې�]P8���I=��`�a��\��;w��ڕ��65�G�'�N���-{�p���5_԰�>O��]�� �\�r�"S����]f���'C4߰�j��y��vd��5�N�ŕ�.���P�rzYv�8��(�1���ݨ�;b�8D�-	>6�bǋRy�o/9�IS���W��L��2M�еpͽ�z�u��y��w�X`�����Ȳ!"������>'qycV��1�5�]њ��l���?~��=ٝ�S�k�i���]��{����Q3�������X����W��Ɔ�㮎�\K�nL)�8%��Z)7�U\�
/ß��/��?v}��h �~u�����~`i>�ω"�V*��N�k��+� i�1���Ld��[(H@*�S_��k�2Y\}���M�_a�I�-6��T��5b�w{������[/I����W"QJ��Uv	l2&-�Q���>�`6k����O�
��'��ƶ �]�ғ.��\]2Bާ4�ZsH=T흇^���9�	��i�c�J�,l���YcMQ�ry�����d�~�����y�Lg��rۜC3;��n<5�U�
7u�J
b�uԷ�,�e�X��*v��`~l���=�J«R�[�%�E�8��{�r_���O�a�wZ�vo_��Q��R������^�v^o��.�Q�z���s{���ķ��X�L ��s9e�o�/����/�%$��O�i�L�59L��/(ɀ_��Z-Pz�;c���Ğ�F�j���r;��L�Le6Ap��I�����B���E�d�0��.ήd�׵,_)tԼЛ�����vT��-oxet����N�w��D5XD���
���h�-V�P�I ���>��T���^���>���+9��+�oc��@s�Ns����_��� 6z�o*���*�߸5�s�F�,����<���+����k��Z{h�Z]�+�e�T��d����K	�Ns�ꓑ:�cfr�?t�C!�T�2��}�`�W��
�K��=���o�ދ�h�G����u��tJ�.�4*^F$��ag��J�5s�zXy+N73��	bH3�L�[X�s��T-Ip���8����r�!��2m����}���j�����˦���str� �S�RE���dǹ`:1�	��H��/��7r��#�E'�v��K�C�_*H3�CF�����av���Z珜��O$"�ٳ�ʸ��N�:��&�E�	ŝ�)W�����O^��v"lE'��~��R�WC
�Z���+w������~�q{���ϵa��)��7B$5��Tb߶��5QI�+  �G2��je]����ӤT�VA�fn�����Ҳq<�*L�P)�v�|\�	���M����;��� �;IxA?��)pK��`f{�d�v>o�J��e�թf|~�،�08HF4ZV���y����]��N��ca'A���6�T}I@岯��Y���ߟ����+�t21�n���p>�_��q@n�[������5�Zv`��XdDu>f���T���G���"v.!א}F?YH��-�6�7�7��Yٜ��{�W�a�(w�Y`�إ����D���Vo%�`m��dl�&PwЌaj&ty�,��N*��B���(U�Mt��j�;�i��5{n��ԋ��9G��)SxݲE�{��Y��"���O�3�4���?Y$�����2ϥS2�Т>��wN����K��>xb����5�)O-S�*��C�pJ��c�ˠ�l%A^YPڏ[?����=;��]7���;���<��V���td|sZV���R�<28�h�&s	rQ	�V<H(3jSR�[��3S@��l����|�'�ڡ�:��h�w�<ax��N�Sz'�7�Z.;�m�p��(f"�v�U2�Y�I�5 �ELk�����O\��u��J�~������{r���x��9n<����]����"��-'&�MA��-ҦK�3H��*�5�-=�`��A��m��]L���Ǧ��X�Nt�KΉ�[N&^ʭ0���x$
KN*���R�Z��F�3��h�c�?�}\O�c��c��V���E's��2'z6��Lʻց�<�Q���tܿûu<O�2�����Z?�Ϝ~���wz\�B��s�v� �J���O߰�9:���Zs<�kC8��i��2����߸޼��Þ�^	q|�F>A�<~~uh�#���	!�-I����$����M�Nf?���1B�a ��~6� �7}��>6�����}JZ��2�� ��F�ڧ\e�Ojd|,=��#� �F�&�oz�_����Y�e�Pm��������Y�����00_`��	8d-�&1&�OI���P�z
�=����!F(��0�MM�r@l�F#�Ϥۗ����sj��*��ǝc�1�%�ߤ�5S�kT�V�3�a�j��n���2ǻG�sX�~�ǟ�þoX9R*O�%��V�	�z�^��M>Q|�����W�p-lF�e�b2C\�� �V(�s�'��T��۰�Lc��KU�(fw��5Գ�.V�p�i����>Z�k��c_�s@��g�׎)2��Wyk(i�9I|ӱ�11u�3l���a� �I��;i���_�'� �|5��˥��,wU��g�/f���V��ܰ`V�lR���I�;Sů58-ps����q��R��r����E�ZλK����Rʦ��Pk�1��Y���.�xe������'Z!����'E:���W��wFZe���͝���\�7�݆����}7#�V���P������g��:�=��a/��c�	#Dt§�A��Efibu7@Uh!���L�͛��d�/�w�}^wq���I����</py~��=i#�.Y`Sȷ���c�'���*kp7����%����U$Ԓ"������}L_��<)���D�f,���ӰI��Q;S��
d$�Op�y׌�1R��,b5�S�M*����a.{A,H�+��k�"���iʔ��z�!�@�{>����M���Z�my��D�	H��c��7��K�S.��G$�;�=��Nk�P~����\�>H�y�`��ݶ��a��S1}���    '�X^v�R�� �����k?��[>������0�H ��g���dX�"�0�\(�n+�M}�.E>��2�.������Y�����mY�sE/Sꩩ%��-T� j��׳d���W��)��$fL�m$��'�߰oEm�e'yZҪW�8�_�z�\�dJ[`�K<w%��<+��j�6QCik&3�>��dN�ܒwt�o,�p��/��V�k.I�Lp�W�y8R�+}���+sg�_��4sĊ�d�6Lq��9�'����dJ�Ư���%�� ���O��~�n������j]W���㪌�]K���b�w�˦JO#$H6��~a�cڈ.��9�����s�� �����ّә?���=(�咞7����'q<q��l}h�ޟ �r��]bq���N����i~�1 �R�rΉ�]��D���w̜�U/�����a�����r{�ʼ�f!;�1N�u�b%��/�$	��[����yL�a���$'�#��*�(�\T�4�%��Ol���U�`?�A������G�!��l_H�+O*��t������d� 2�D�KD��5�E��#S�vNZ�*r�����{�O����^��Vg�O/hu��;����
��*���#��X�D��$Rh1�q����"�\�c�o����Ķ�2��!�7Q��o�y��l��D�)2��6��%.�".x��׹�?Qk�#�^H!��=��
�$�
���l ���'�M�aO7����j�K�Ϸ�j�0��'m/#��/��D��*T� :ĲEb8E�D���;\O����z�)��߼�/������Q3��a�n�Y?�WW���;�p�ݜ|�6K+�gq�5I>ɿ�q�O_����sV�3r�L*_�����绰���^�������L�9g���v���(���U΂+:o,����'\����2�R�||l�ĭPg��RJ�\f�p��nmd�ϯ��'�R�/�~^Bk���|j�� �[iw1�M68w2�����a�+ڰ�mRa��G�ȟ��Q~�i��;�zʤ���5�!�3��՟����o]�gKѧW�:���w}<ڪ��{wb���w�+�9�=�J�������O��tn���Ra�M�B�����׸�&n����%�Ȧx�9즣��8�+G�~^��A2u^[�_�O�"�e[.����}�� ff&�9usa��J�+�B6X����E's%��hu�β����Bo:O���u�ޡ~�;~�{P�R�@�K�tf.�d_�P��)Q;���#��&b̛m���(߰k��1谻틘n6���[��X��g��t6��%N�ꓑ��dk\%��Nƕd�f�@��P�W��wY�~bd�`??%T�s�C���	&q��.��w����:�M�����O&�l_�&s�=/�Y�.�k2ېq�(�Ӱ��N�,��'��`Cy�be^��i %�ʁuH�W�:16\�ͨ/p$Pw#�`��b3��.0�Hi����%</���?�i���{�>�^�YQ����a%���]e�E���?���@B�RP�����S"�j�r�����^XPBu_�f[�
��8�`�"��W;AweA���^��R��R�����Z���)��#h���r�]E��`u)�۰�a��U%-��웄Һ��H�v��vVh��-��
H��3��ћ�\�!8l�lD�7R�%jJ��b���j����T)��d&:�5p��O����+��u���[�O�Z}��~E�z;�[͠�v��,{�֟`��`�|ӟP�����EJ�1��K��j�O�2��&���9����0@O���-������<6<��%;^�*�����.Hf�K�*�/�����N�$�_� 7D����I��D2_�����R������J���)w����V`X�)�y���[>}2�4u�B0(هPtoz��� %g,��W؍����?���Al���ؾiOC��=�_���?�}|k��'9�M3�p�hr<��?��HP��yc����/�$!�������ɻv7�Cv��ڱ����i_Σk-��N��*σS��P��sH���r8%�;As�\���'���0���c7�7�{�1��#��i6��<z�.{6��0�B
l���Ԑ���W"x�eѧ�
7�Qs~Rо6��w�����^��Wֵ7�[]F�k�xϿ��'.	gO��a�>�iJ=�0%OO�Q+�L0�a���w=��!�oد�>Wwq?Yg�DՄ��=&B��+��+�.��*�$q91�C�M�6���,�*�J1yҍ���.Q�+?_��7���ƀ�,��v��i_���<g[C%�q�!]����d�Yj��Ld;"�B5׹۝!�Ӣ;&y�/����	��La�?	��_a�o��Ń����s�ݻ/RI��f�x�^�5�R쩌�cj�cT����Q�΅�l7�`�e�?��bd�6ys�+B.�~���%cv���^�^����r�O����O���3`f�=r(H�դ�zlu5��+���Je��0��~�]���,J嶹kz�i���%���4���`�6=Xo/Z� )u��垈�J���I����t�f@m:c
gqM-���K�"�����5Y�QU���x��j�(�hۏ��ՙ�oas�>|Kb��N�G��"�O�ǥ�W���S8s!\,�'�ݑ�$���]RS;y�щ+�y�5�����U�,�\���,W_vR�{ �!E��+c�$Y)�1Bt�"� �[N:7����I�M)�r`��\��s>�D��X������iG�X}|�Ϳ.[ ��>d�%s3T��W���T=L$>�]d�,f%��1p�����ڿ`/�^��'�z�:o%ȁG�!i�L��>�;����ʓ������$�X�; �e� X���ၸs��R,���k_Zؕ����XR[�#�^���L}��k:�z�<�i������J����ǲ'c��̑f(.#�zbh"B��e��]+�g��l�{�>���(�$
{#��{��F�Z�D��O$��B������]4�
��3x̩�
��7^�>2�k������M־�<�X/�
��#!k��y�M�F��A,p�,qjf���k�P�����Ҹ��\���\�'��������+v�x�7�Ӻ�.�+�q�
�~�
��ڏT���7-pm�Ǟ"�Sn�1��>�]��E��J�r�`��qji3�����8���6>u�F����bu���{o;i���;���*+G���L,"�ZF &�B�l�({d�S�gK	kfk4���е���o���ۭn��ݛ�\k��������u׹���%j ��-�"q�!��|	��� -V���DI�`86jV�o1�'5��`㙾6k�e���Z���O�k�_�,���%G�+�^+�N"&pu\1��6�QGTܣX�h:*jIeO��~��^>�Il���+�r��b��.��(�����`��)���8Xe����\�m9t�뵅τ���$���0�����_���6����p�
V�+x���&��Թj�a3��d���K�}�~p�w�m��@����B���N/A�ܧ����Ƛ���=�n����s�u��n��K��z����ʹ�����H�S�����,}$�>A�H��t#���Cl"yПwcZy�c~�R�6�l�砇����v������]�zoG��A��X��e�m�H���d���ǥB<��,J��%2��!��o2ҩ�O\���	���Sb޹v�o���C�����L�l/�@�����s��
�x.9TA#����J���sJ�-��O���/��#�	����ԗ!;?��7�ls�3Ӣ���Xū</{��)��O&�%��[�6�1����?0�"K���g1��L��ĥ~�n�`����U�Q�器����7R�m���>.0�A���4�X�QD�(0�6XT��9�b����r�0�ƶl����
iG��tl�he��Xl�u^p�A�RqU^����`� �5j�i    �	fY�_�N!ԓ�f|���R�d�7쓰�E�!�1�a~���������9iS>�3�X�v�e�R٢�xg��7F�����q�g����%KkrUC"kR�տ6��Xq�W��|��q?F���Ց\MJ�ח|��K�ѫ�����P�#��@�M��f��x\!N������������X0�ٖ�,
�G坻�f�׃�?h��Ͽ����(ؘ=�+}��v��,NM�Iq~��$���'�O$=-��J�ݑ��ۿa���OIz�\6�*����n�@�U�k#��+�W����噫,�����XLx>Q�N[r��|��};����e���L�k�����rT���l����U�mZ��{�/PzZ`Xsڷ�a7_٨�	���s*�;�K���-_��ie�3>~2���?����?��5�©}T�JF5�$"��4�<�-p{�9S�h�2G�E��B5��5;�8��=P㌐~~
�����7�!���㖕�iGH��-��K�[�<�}��x��v����P�mi�6�i��`&U�K�I�-�y����k��~�K�"O�Â��;ۣ�C�˨����N�X�Rjz�ك.�6GB�-uIGT��&P��y!Ք�P"�+N���j"�C�t1��O�|�^擟6�����q��K�촓��x3˂������)lV�>����V�w�9.t���r�����|^��o3?$ݞ�ĥ~�'���N.'�N
�8�x��j����.�G��jy�Ӷ��m��S?�dx�}�Pa�q�Fuץ�0��

Q�_aχ��ߞ�)�K���M�TP�h�z%Lj�f'nW��^���0"	|^8�B,7�
V����*X�!mF-��䭧B�~22��'թj�+�ݭ��KV7���i��;W~���>]��Sڔ�x�[ؐ�Fxf�,%���[�3�ˏa)(�#vB�����/ʁL��������i6���uT�%y���f��@�iV	V�*`@��%>�l���A�$�����D�`�-��|������&}ܬa���)y��aE	�Ixv�apx���~.p;Vm�'ݘ�<1�#J�s5����eؼJ1?��a�T�y���z�BI⧸$���.�{�M��[1���_q�����d�� 2�y��8�>Ք�#�F�#�x�O��Ň)���J@�N-\F?)�������I�v�����笊�t�����1i7�7�p�d�۩�i�)c=��<b��%q�بB:L\�1�wJ*���i�՗^��;��dH��>~Dm(���+?�u�:�5�f۫��vn�SR�s��dP�U�v�
[�CՖ(�>��\[�Z�DXo~�K��}<f��w2_�����˫z�W��I��K!��[���2��9�`�*ļ���P�ht3½�(i>�R��\�ɑ�՗^�nt[�f9�P�E�9��5>��(W��P�(����7o�9o����nU�pe(ZnE�q��hd��.`
~cd�`7��������/2N��FAͥ�;��8'�>_Cw���Þ�<�Z[f�0%�<�l�`�_��/j&�3h����Ha���_d�߰g���V&�$'�*�r~�A��m}Ւ��U��`M�~r��?��;��K�R���)|`�W_�����'Y��K������)�N��v���d���o+w>��N$�$�~�³���?�
sؕ7�)j}5R`
o�c!�j��OTa߰������}��ޚ�]�d���������������٥�П�҆߱*�&a��VHc ��@��Z^��6�7�ۭE�G�|>J�<�_]m[�*���ʲo�]�`��sG��}���)�D��;Y��v\�ܢZ�]��Ϗ���]a�/-l��;3Wu[+_;]�{k������YK��@[`2r�1u/r���y�2V�P.eT>v^�S�n�yr�]SO��R�`��,̏���ԪCt�Bw܈\VJ��*���hNlaC!F��DS��t�T�_]��/�ӗ��Ié͊ƚ�O���p08D"��#������s�q�*�y��K��1x�l��0���5�6��>�3�xCR��am�d��#�;v��lri�p�o�K�����eU1[3����y�����#�ʩ,�N�G���A��g&%#4K�[��x@�h`K��B�_a�m�������jH%j�Y9��)47%Wv�'��(���o��K,�����.CB;D��מJiG0bV��i��'�z�/-�l{c^�Z��)k��'�q�N���v�P*��Z�XA�lY&ٷ��[T�O�ɞ)�	ͻ�6[?Q?�[��ic?�#�\�ο12_��:�p�G���6��/J�;��&2O� o
R�Xo/���ȲK�e��j�n�l��Wl%sD����a��S�����������������w��w����j$C��� fa�{d��!�ŉH[>�(�-+�kD!E%)*k��M���Q��S�i�/??K1��=��yז��]Ph��Z�)CL�	b[		�] �'��0R9��ސ;�h�9��� f�&]��=¤gw$��)�g��hc>~4��F�p�m�t\�r o��;�Wޕ
v�}�2�aꈔ8�nr�v��`��A�%��:���%I?k}�?���i��-�����="�m�H�����hd���SA�d�X�[��8Tq� �׌������|�}�?����\���Ԍ?[ڝr��F��Z�����Nk�~��'%-�I�E0R�zi�!l�S��Z$�$G�s&T\�#�?Y$����n���"�V}�K�Q����q����<�A,;S���N���c�L�݁&�"��C� tl�Se 8c)�D~a��_�;	�"�#�U�?L����w�v�rֈ��P��aϬ.I��N��x�097W�C���W�խrd� -��z�o^����:��RO���qԬ���s�J!�����<�H�#Ԕ=�����Ae�d��z .Q�orf��x�U��ɪ�o�#O�;x�#�ˢ�M�������`��ll\�ߗ�=�K%��9r�:j���WN���#��7�dX3Y��������WF&�����;��\=c	��X�Y۾��=�����H�R~�杄0p�o8b^�}�o��6��WK�m�(�Q{��%�?Y��������`�����{XV}棴��?�ȣ�����O�<��ԋ b�	SehuGR%3]�>F��5϶�b�'q��?��m�C�՝�d~ΘN^�`>�F �7��⋧6�~�[�[�L�H�)�A��� wh�
S��
�zP2%�2b��,�\&$��m��=G�9y)(���<ϰ�N�7"	EqR�Rw+/a��VN+!�N-S�92E� &���")��Es���"�;�x�en�D߾���>�owyݶf|���hPp:��[�Z8\OO�VLC^-0��q2�2�6k�]n�J��P5ޟ�2Mƴ�!�X�[��ĥ~�V�8���p���49��$Ɯ⇵���6��l�>w8��p��#aR��Y���J@�!���K��x����Ma�u������[8Ǘt<4�zy��=ݲN\�����u4�0[ ���r�^���餁z4B��>f�r�+	���K�έ���W��W������^�:u����LY�
��)�\N(_vf�+*����Mp�6l����	�<��"������Hz�R��u���^�~.�Zh�Q�@zM��V��\�ƝU8.��'0�YS�}ǚC��!"��* Ⴁ�)��:�!�������K߰�]}ǬT��#~����Q]�V��ϖJ�K�s�.;������Ȑ�	)]�[� ��3��,�O�z��r�灟`��ԻG�ϴ������09c��/��I��N|�-p�u�	U����'�E����7HN��*���\1g��@l˾)���ϓ֮t��^y�sQ�9(;Ͻ?�Ͷ������=5�,١�VO���B=��C��$iD�-ς2+Yl�����'Y�7�Eز��t��*�Ss��9&}�)�M1�_C#���v�]�Fx\�[Xx�	�R�e ����U�C�N�    �x�d�������]���0yF��`v������n��f�̮�LV��0����K�����-��HyI:C'pk=p��O6Z����k��ȫ��wǷ[8��ڐ�F�w	�s��c�Ӏ������� m�������D���\t'�<G�WT��w��O�V}�����7�K7�g;{Z�?�^B{˟Flre�n=�i$�`k��eRI�����\��3��k�b�U��?��|ia+�h��.^'��G?�|�>q�H����-B���k�ƭr T��t��D�f�$�yKl�["�cn��D��Ĵ˟�.}þ���s�Q��?Q�n戤�J�l��w�:qo.P��P2����}��bh�b��'U��([JN{՗˖������d!�7�q���_��N����vH�S�ӝ ���>-q�	#,��'K�D,\6I���%�J���KCɞr�	��%�O��n�6���^�J��p=R�H��6��{߇������~냸^v���Ť�<�QΩ*4"�_�*���w�4j:?������Iq���~;��O8�+S���e��"N�i��G]p6��ǋ�<����ؗ:�$���P����2"�.�ZLq���3 �/"�o�t�+(�]�v;;�d:�!O�k��������X�������咫6�\�d�}d�X�����X��k?�.m�(�ۦ�YT�C�+��x�5���N�^ݟīI��H�2�EH4�S��(��O�����x�fH1NR�������߰?����)���z�on��"g�:���xu�nO�W�j��I� �C������7XC�5�S�������y���O�l�~�t'32��5^�~{ޖ��������Cr�P㖻��s꼹H�M��ղ���-GȐ}��8��Q1!HΕ���a�5@��<IJ�Z7p=?֮e��{�8����<�3z�f�})��{Q�I��R"�o�[�*��X"�OA{P~�+l�m���-��,��3<����{Y^7��G�ÉU���OR�<�c��ʄ�z,,"gU�F�7�F� .���}$q�e�TO�/�O�/����R铆Z�\�DX�}��䙰�_DW���q�,�e����� 3�o
���6n��I����2��U��e��?)|î���GL��dn����7�o���i�ο>t�^�d�q�M'㸴>��5*v����#bf���Xސ�������>�u6K��o�t���/���s��������J���T�D6L@<ƅ��� ��1Rd�����XSK���\�r!�|��O^�����>o3�lgnvM��q�g���T�P�o��!����_���ls�E�d�tھ���1�E.������OV�m�-6A~�H}y�����\+�{T����̭��X�ɬp��n�-V2k<"�RŻ#���{D����P�!r�M��H��%��p��V�m�hHn���$�ǻ�jG�S�Q���U>��Ǹ�8J�̱�Tu��
S�%X\y�=�	"$�|-���ol���QhW�ʫ���՚���>�o٭9ex��5a��< �g�q�L0a�����
��eCH���!�Jַ�S��s�C(?Y��{���~�=�)o��m�����m�w��[��wZ�kc� �����Ďl`@�!�&����-�ܽ��M���+w��'�M6_�۞�:�«���.�J�9���g��aw�,��K���M`C���%3�ϵ��^����a�5#�l�K��M!?12_�ώ����6�LJ�.���^�D��89ߝ7Ӆ��a5�=|��;ȅ�(f���j���٩	Ͱ Y��[N~���vc뱺?���#��:��7��T�%��io�x���dyةS�� LD��!6F�;�ALk�2z�n�b�)�����Of�6_��.��1�ϯ�zm��Jݬb��m��h���Y�<�>.	��!�D��.HM;��C˛��|Q�ȑ[R��|�����a?��ռ��jAqpPA��][�z�py]b��[��*��>C�VP�e�
�	b'\�7"��2���5��YJ��OTa߰W#�������O��7�Be�m �|6W��,��l�ԲӼ��sjiX�´���~1SIR�J &y{.��������n��tj�&��@}�������L�������/{D����H6�p9�?�#h��w�� �BI�8� �j���ĥ~�����?IN�� �J|��֐��-@��Wk�н���K4I}��w��>�;���,
1�G��]��r��n������>��������u=�=����	�D��Gݹt+�3a�/q�2! J��d�-B@T���7v�Y�Q�Z���>�e�䭪��Ծ���_l�i�������-C,5�U^ވ;RA�7���>�����V�߹
���k����'JH�_ϓ��Z����<g}�:M�?�dXC����ZUKREǨ�'\[v0�;g~P:S�1�b����]W����V�]6� �|�솯\��x?��F��l+�:��
}W���v�g�.���@�Ѭh��(�:�E" V%H�q��`O
��C�*\�pm���h��-�Ӽׇ��y���T4�����#�y����r��Q��H����,�_%U&���o��'��Y�j��|��s�˞Y�m���aHQ`;TBn~;�j�r̌
+�;5v`a��]ea�����Hza��W�o~¾4.���Ey���������K���D�y��*!������]��۾�M���H+�m�T�"��zb�Tƪ��ϰ���{�j���	K��i\"��BX��٣���ĭ�*���cV��N}HUJ�v�s���[���ʪ�A�+w2��Ҽo�3W�#�R�r�s�R��=9���t'?�dr�פ�i^�fB�4fN�1a�na:�rM�2B�����������AEl������,�N�Qt���bѭt�-����~{��{Ѭ;Ī� ߧ�ECJ�嵿(��lQg�<���`�����
�O�hԲ�v����m��f_�ʟ�(Uf�[#�i�C]8?���zA��u��Z��v7q��]W��T�0���֬H|#�������s@=�����S�y����as�.�/�q8�LeM���NA��3�(I�HKx<mMJ���dF�P����`#[�_i����w:|ӱ��=�]��9ű���z�l�M�����_�t
2\�}��OaCDl�f�#���&�Lǀ�S��L�����	{1�˩�����L5������g���Fc	�q�{Ʉ>�����Ҋ��Gl�k�P�n��w��q��R�;�v���P�{�h�g�jgl5����J.�x�6պ��A�.�a ��r�txG����˝(����
�YCglW��\��?���]M�;���I���L������y|��&��RM��=�b��Qx,Z�	hRi��X
��+M�1	Se�p"��&��+���?�6E�$gX��d�����B�>�Fۼ�n��B��`;p&-�՛�̎J$w�گ"Ҡ�7f��λ��~�H��N�O�_T]{EW�	��#�����D+)Xv�j��u�5i�V��Li������H�N80��BS�Y"�B=�_������\�-~�����G�ȗ���^����3��������J��t#
υ�D�CA/��=!��e���e*u����+;�>a_'�>m~�����o\�����Ϟ�{��ލo?� Je
Y���܅Z�
���!&��3qf	�!�i� E�
�n�|e����3Gˏ�33��b�`�N3?,��݋�T�WWS���]�O�r/��e�P�V��BCΆ���L2]U���`B��[߈���ᙣ8o���/W%�k�<qֲ*p��� �)��t�2�^�����x#,V�&p��g��[���pzK0c�����cD�G��idg67V���`�Л+�Z�^�%�vP���E��c�l)�&��RĒs�*zJЄj�sy�mFjK�����#����nT�cѭ����w}V��:��    ~l���<��.l���@��+^R��Z�,�T����gn��>�7�2��̹�'��]Z(������ꠛ8	�O%;[sB�+'���F���?x'S�ҀI�eT���lR�2�Scb^�Ư�m�s�10�6:�D���v��4М���#������qn��I��X���_P�`�S-U��C�Nq�U2v�JPR^c�-,�T����.a��}��ץ�د��UU�7�D���f-Յ�\��s���n���KB�V���y�R<���N�E{s&w�� U%��:�N��u�o�2���o�m��l�g��=to.#h���ݯ��ge��D�i�����B�J&9`̤�^Q��A֠�(FL }%���֮r.�� \�D�Ӷ6g�$�yw�zt��=뿇=f��y��v�Bgg2����U)��6��Z��Il�������tKB�;��l*�B��k�;�<�˽&ps��r������"FS�q)�6dt�Iz#���u0�>Ӧ?Ä�����x���=LV���o�XK�Eٗ�Yr���VU�̂뺺�;db���ʄ̈́�	E���P@V���y'�!R�Nl�L���?`�������9�(�Y9��B�^Qj�`<��Q�˺��;���Vq�z���$�v�T� ����p�958���# ?8�n�����Jl�=W�.�{�:�A���봂�lp2�`P:��`j��
 ���f.��'|z�G�?	T�˛�����h����_q?��7Y��jS�����l���w�/�F{�p-n�.n�R+�|�"�e��� T:�� ��%�ʑ��9��,����$�{^�����^C�H���K2k���{�$�q�k�����Tt�aߧ����5#,�H�0o���k����u/ �]�[��.�Y�\i����L����|��D`������n�QJx]�2!�|�$��eH��s�8fЊ�	?b�/&�o=\���9�|���m�4Q�V]L��7z?����LU��o��zG?��Q3[&Tj�S��[>�� ����+�acm��U��X�յ_�w#LһTc8j�����u���� �b������m��l��M��y�wco9�ҝ��7F=|�>.�3��Y�~���l����n�E��z�T>��팭��G֚j���`ˎj`����F�7ҪL��C3����o$\(��P��������(�D6�6���)���;��_��Q�%�c:��)�w�RY住TJ�TC
�S���w�
e��W��v�ɞٹ�����u���m���=�A�M��$����0x���E��d��u�*����Y��>�e%\4|c��G�E<U��-qs�O�Y���t�OM嘑F�S,N���aGu5F��s��H�����k+% �w�*�h-ι.��o,���}`�:�.�\GG�hW���v���=	��J9��\���d�ٔ.�0�}�6/樰�Oc�T���F�@�>��*�J���'��I�<Έh'����vY��U�O�}uz<���2�I�}2Х�q��� �$�߀&К�p�;S&$��L_�޽b�ots|�.hq��J]�n��W\�ixF'��U���U�����W&j���8c�0
�_���3P%�(��R���<�UX�����'�����rO�׼4�joZQ�]Ă+@���=�X_������L	�BZ�y]���
�F I�.� �nD��B35Az="���9���A�,���̻9/�4����U�]U��n����e?�����Z�I�%!�@�5�ﰪi+������8�`��]��1�#�ǆ&�*���X�������	�m�����]a7�|K:��Y�|[�IA�������N�X"�B�`�L��65����#����Q��!;������ٹx<H{�l�!����� v��)̌���hɓ��`^��$P��e)uQW:U��'l��+�4���~��L�ϊ� �ǟ:��j��Z���c�Ë���1_�-dȹ+��p���h��B2��ǒOa�2.��F���}W���y�7�����O�#�hR���,ς4��Uq��GH`�MDݼr�7c=}��_C�|��;4��E�X����K]��xZ�Fi�wq����p\�MoN�i��"�;i���a�9�kܣC�NwF�C���S��em�bmh��e�p׾�7����~%�|��'�b�m�PYY�c|�C]��Z#���C��\�`�D������Y�<�7��`D��ڽ�9��N�k��tV@5���*��4�Q�]4"�վدM�mp:�o�`���Xd���{��֙1���]��q&�;�Y��1���G-DR3b���O��/u�~0㙠P�7��^"3�T]w�<0l'��ƹZ�Ӵ��`[��}�a�-��9�B�7� �!P�#g��.1�DSI�����O���*���+��rҊ`廧u�'��<X��R?( �;<2���^�\��N^����>͵��g����n&D19 �o6���v��c3,�vT�|f��yt�-C�ΰ*B�O?�%, �
�z18�l�q�>�U�!���'�w�o�W���W�T��OF��zx��?��
<�Xo��s�)���vM��?X��ygƵ�.����Q��2��-��u�^���z�����)������@�]��ߵ���V�f�R�M�����p>m����-�JILHQ%%����x� �{E8fC:`]lc5��?W�r��ʽ< �����vQӒ,������,ۅ�W?�dfn�፱���`�w�\8A�\�(�v�@R8�
��%l�c��G���\W���u��M�PV82�k=�lu�˩�B@�ߞ�Ֆ�>ϙ0�g�9&��<�	�-V�3�����&�-o����n��=){��y�v��y���cG
�-l.����0fQo�td2R�- W���5MH]&��2�ǈ�K�W)Q�5���&������M�����ہ�Qo�4w��4���ʲp�$���fae� �B�d�T�p5���Zԁ����)�0j��6\�(��!v����l�;c��^���x��-vW�aA���7�$�A��$n0�*P(�I� "�9�-Q����G��\a�ev�䴝�'|�����M$�����-x��4lĪx+�MK�{o=3����e&z�E��5	�Ӓ����9�ʑ�{c�?�����Z���#R�=��Z�!�VW�����we:m5��H��ȰP
���\�ޕ��ag�tY38��[��_q|�n�ɉ��|��"όP�����*���8�֙��aǞl��3!-�P��h|��>RhG�G�jl-�1CA�����Y��I}���/`ߎ���j']ݑ�7�*���z�I$�4�	*�U|���S�f`	�2�N�ж�
-UL���>뀋�ï���3�+��\��!?��<��~���Rzw\H����R���A�B���3|#j���>/l4�J)�c@���Ｅ~�'����X�Z.�|~�:��}���s���<��������V1�u&m:f�L��{�f]��P�vDC�[!QL�c:g��߉������A^�m�l�iT#�▪űƋ�G��~p6���6�f��:s�'�`x�\��8P�v��pc�w��`�`ؠ�:�u^���k���٦�ry���M��X9�r��K"�DA��:
�1���Z�����m5����p��K �����u�9��۩�:�n���U��x���/��Sn���{����]��N�Ğ�X��<٘�54xm9�#���ХB��2�}0l��Q�l/d�J�u��B᩸����nv7��|	~{D�7|A:JbZ�XI哵͈��{Ke��Μ)��Q��^�o�K��ù���Kc�"�	�����z��� �8y�Yߗ?( ��s��􉘥E�b�=�q�z�Fބz��|F����D�H��Ck^ޡ:�{�lԁ�&�����x�kW��Gok��NOz��pNc�U�<��ғ:�df�v����R��P�)�|{�! ���E�يꤊ�^�l��en�    �3�Vv��e^�������^0�ƍ@��c ������d�F-�?���o�����'��D�CR�=�Y�����y�Ҝ���.<���7��DJ��O
dS���BR�d�����0x��U������ռ5�W�R?a_�p�y����x��U�������O�}z���"��"�ve�d7�Fzy]�9�ht�^y�{D���-� ��F7\�����6Ѡ���[��o���+y����λ�UjoL����z��5�D[`D�E�:��]�qU��$u�(V��Ǔj��~���_���QQ��e1�=u��m~ T�f�hv���,ް�Bpqg�+=o�K��K�Z@���RC	�9�)�e�z�򆼾b�����/�{�[��M܅0���x܋�H���I�����?�� אd�F�����w�&^!��7C$�0ߢ�|Ds*����+�K�W����մ��L��{+D�u�g�����^���W�p/��Y�Y0�d
��b���+@"�}h�E|�t���ӭݣ@?��$K�s�ӆҎ�n�yrFQK�͋l�:�Hk82�DM!o�vY�s��#��	�s�	V�t����gje��G����,�U&��P����,��z�h�x��'l �9Qo�C�ОQ��l��Z�8��l���0`B�	�%1��`�`OZ	�z�|yZYg�T�<�Ѭ0�N�-�J���
˥�.LJ��]��	��w9Y'H�qP.��#vI�/JO��J��{<k�+��\��e�F��sxJ��6�xY�6�V�qݼX]��j@K4�v��'<D��"�R��G��1s�c��7��-�>�^M7�����I�Uîw�c��7�ּ%�����=�`�rd�����b"z��+q���hT���b̒�Igq�3i�_ү��/�0�'�7�jY�6�������4��+q����Ъ,U�7c�Y�I1��f̕i�@%����;���Hwz'���T^}�Q�v�v#��xB������>�^����z�ڋ�� v�G�� G��r��M^�?��-S��4t���Ҍ�5GX����wr���#��k�^���5�]}��a�9yw
E��?( I��ğX��D����}�6��w!��G�����C��N��Wz�>a��q���zZn��r�Ϊlw��3o�XjV�A0����#wyϜ���?P�^¤���t�U�a�P;R��k�J������vK�by:x�-.��Z�eݮ�F��M�6�h�r��;�+�3
2��ǂ��̭�i��\k.�'w�#������d<�=I��b�:����;:0NO[}�w}؛�]^����]���qio��h[t��5�)�j�2��Y虺13)�w䣢Q��<a�^��"���h�xq2bDF
S�>2�`S�+η���YWL�#_A��D���E7��o��z�e�.t��\�<n����x�{Rs�\�;|1�M;�N՚�k�N��-<��|����=셬ܠY/h#/E�_tDzz�R2cF,�SjY����Zh��wt���S]���L��Bɼ��[����la�9Z8?���(,���IF�3��,ZCYzrݾ����3R�� f�U���a���C����7�m[=��m��k}����XS���Þ+�c���@�O��6f��eR���5xR��G�p�+����m�m�d�4ߨ���rڟ���R0W�r�@0(~�z��Ad�0���Q���L�pA`��l��P � ��Ywq}2����f<?<^N�|߫�6�^��}a���t��u�;��!�'�=�*�=N�T/��;����=)���f��7ud挦�����Or��?�,�����9L���6Q����=�&Λ��?J 
dTzᒆ��wA+#%ŋ+T$���+$?a��e�ѥ�]/���??��qVx�ةt;�K����������]rp5�?29�F�Ni�	�5�ܾ�m�-)u��3y�+_Y��	�ei�wx�u.#�Kn����ԦЁ��S#��6I��<�T�dk�t�D-my����{�N4Y&}�HP��!�̯l�?x ��o���Ue<����
���c�Yd����ӵ�=��Q_�� �������e�h
���$����tJ1,��R��
����]��P�~�6�拒���?k��)����a#M�V4↰����43@�[��q��+�B]�RC4�;)_�
���l5M�q"�>���Tp{9��8�yv�<��W$����%����d�X@��K�MG�:���40�G�t�w�߾���=�<-/�����ɖ,��}ܖ��>߷�cw<�e��a�Dy!�O�ôsf�.�c̝w	����g��Y���F��}N
�+]؟���7��>������'H5gU��5�c54�e�����n�Ez�C�5��l+�Y�"���ߥ�T�R�����e�~��W���Q�sy�]v�;�����N���-��H��lE�b�7@�Q/ܡS��!����$7rD[�A�T^̭�_)�>a_��ea����䁊��y��{~8����K�Y�`����l4�3�FԼF*�h�Iա���J� g����_���=��B����۱}T{><s�,�=X�0q����-��6�K��B#M50�=��%�Y^�V�q�N6_���I�N�d���<�]�Y[�*��a�WF�B�����BNAe�ȴ/j"��I���^!֬���s�)�i��JI[���&�O�翩6�u��k��L���Wu-6W��wۅ�:/~{k�c��F)�-U��f*���t1:آi�4�9�Q�uW��c�^��_��=����d�Bo%�ޟ[����J�z����֕�X�{�VD�V�g~&-_��^�[�N	��NO�l=Y�ͯ�?ؓ�TLY�O���XT͞W���S�ͫ���vׇ��?q��LըC{�s�U&��AıJ��.o�wP�N!œ8���N;�'�'�eۦ\��5ȭA�]*豹�ft�[����?��A���v=�N"U:D�)��<��W@�3">K�����;��ߌv���
���D�u:�}GCJ��K���J����t���y�=�ɉ�U(�� �᮪�N>�:2Ej��lFU:[ �����+6��$����p��]Ki�� |=���i$�{rAC��7`5�gE�c��P������6��z�����k0b�J�Y��D�	��O�#�Fd{v0��9�Cǁ�������-"�|��d�ڤ�޹;c�3d��lS=��rve�'�4���4תFHN�W����2������k��U����X��3:z�c��a'J��.��_cLi@ii�5K(����󧑻�س�EKͯta�\ax��0=(�Zk����v�,���]���%q��dAjZK��z��˞�ңT��G�c2�z��`m_��	�M��SVm�k����Uw��j��3v��v�������KؓW��}`;F�s�N�8�:��,����T`ߤ�@�v���|2�n��j��p��-��u$�i:�}�v���:	��B���-ԷZy���Zq��BAI�Y������:vz��� A�����5��Ϙ����	K�t���uy_��#�)ʢ��q+/EGem�܆2 S\:���@�)�紕T&S4t�j��<�O�K�m���,����c��T{.���,%�yι�dv�v��<��Id`*"�LVޠ-�7��5{�I����9k���+��	��' S��۪�]m�j�V��i��U��[����{؅n�E�L����F�A9t���i�0������l�Vc��h����r!�����Hr�Cl�E��z�E��7�������h޽�<���z��֕	�j�625��9wM��"�(�~E���D��.��0�E�������#e6���u�)�~��Km�Z(q&nWS�y>��@�.����[�@=zao�f=���{��itsW��c`w�{��̳�xGV�Mi�L2��7��T��]4�q̊9wVS�z�B!�Su�1�\��W$��0:�7����w��WX��f�CS�W��$i�u�}2Y    � u(�h�Cc�U]��w�4e�����Ƣ�g4��W�D|�^;����t���ЬvR�8�r4/vU.{���9@T��,|��6h�Lh|`6?R�{A�<��Y��!A1��K���W6��w>���9�ۿ�)]�+_����x4ݼ&gO����=�⁴5�l ��ma�I��z|�Q����N	�Q �I��g�O��X2'�D:ޣ�;�zz��ܜ�⥥���o	�����3�
�÷N��n��D�.X�1��'k����±0�y�w����'��ڰαw�s�*�{�a3�;3��o7k��Ck�{��䤳Ο)���;F�r���������ePGD��-�w5;��*��<x]��� �/��R�c�>�"�#W#4���b�=�Zh;f�B]nem���ѣٺ�Zƺ���t17�q���+Fk�rao{ʷ����w��}X#9���*��l����$#e��lQ�w�$�x�g,�E̲�f�@3o��Ū��5����M�����k��W����w�t={�uԠcj�~���S�ݧ���m��qdR{�B^1̘����Y����l��mſ� i|0���3ů�"K�^�1�7u���)/�KC�l~G� ��
F� ��� 7�͚���!�h��}�	�Q�H��%���R�Q]���ߦ�M�E*���>��5������S��RsZ�o�,�U�I�\��[%E�u��B;�b  ������*�K���[��5�5ZϽ�@�7kpc�gU\�?��w�%'r�N J�Ҕ�ji��uA�r�H/ÁV$��У�P)���|�τ�5��沽:��W=VFyk�N��q��񃮰�&�F֠��5�]�*��0�-����+fԀ����(E����/�~t1N�����<<���Bp�?q��Bk�����	O��d �!U|��b��Λ��o�p0rY%��V����x ?a/��MW�C��Q!r_<�ڸ�ە�;���=n����H#3��	F)��k�+oe�N:'�c��`�j�L���`�`�K��nN3:di�
��2�,���w9�U��n�g���v<��L��^YK�'�V��hHw��R�#s��C[��������kxS����:<�K��\û��덙wL���,����1�/ҊR�ci�
�p����������N0i�P�t�ڏ�2H���
S�K��ӧ�c�_�=���~�
8<^�~�����T3v��^�L��+&/���g���	 Q�_M��5V��Wf�}���W����=<�b�=/#,`礐�w��d���U��=K"%#�+=��T9A6�`�`m��LS�T)�x�'2��+�K����3�&��q��~.o눼�)>b���(�nkN�/n��m8PY���ۨ�4*r@�S�#Q��1R}� �cF*�+���f<o{��y���X�溽{f7�����'��w��`�d��t�]�4b	:"����kҨ�������j����p����+�K��oG�
�$���f(z@�ߛ1޺�b	+��t5j3w�&uЌ%�3�%:m����)�0M;�үl������+��.�nNu>�i�a�o7V���+�A�T��g�-�D� �Y����s�_�֌�'�@�+w�,���f|HX�3"�;�h,��1�ۭg�E����D��a��h�z��[�V-��\Ht'.��f�g�d)^Ab�ot��+�0�O��Mw.
zڑ��]w:-�)�Vf�^l_w��c��7�!�1NM��}��niK��N��Eh����50� W���A�$��Y��8�쪞t� ����n��;�g��w(�yx�b��j�tD��E��o�NE'�L,N`M��Q㨤mI��H��+��>���9��)��oz�޻��Xr����rL�������L�cFL7#��TE#�D�j�W��z�;(�A��+6>a/�iy�w�������NC|mժ���dL�^\+���� k}#��u���8�i�I#s���T��A4�F���)�S�+G�'��M=Ń^g�[��ϲ��pr��rv�G�,!vO��A�oH{ΰ	AE�B�Ǯ?��b�r��"U4؝�B�^"����+��O�o�#�9��v�>�U�+�z� גE��;�b����v�E*I�Tt�R6do��Tr*�am�Q�y���L֨߱'�|2��xq���1U���Y;�7�#K�H���~�w�>�e����w��u� ��s�@��=�9F:�"���>2 �(�O�x��_�«��9�;_�����r��d��Yn{  ��O�uc�AZt.kI"�z�^e��㹪����;���+Fk�OfW
���S���1V'���4�.W�&&U�~����a��ُZp������T��lsG�(�R� �6�
Y0W6r��W~®Id�vy������K<q�i�J'��C�ٽb���7�[$+�l�-�T9ǂ�9`�h���k�}�t��X�U��{���'t`�~=�,:��`��6����X����׷����}��f�������Plx��y��m��+o�����m��8m�%(��IM�N]7��ݞPl����~���Q���)}��	�4rz~�u�v}��^)�5kR�|�{Rg\�b���u�i�	�3��߁i���r�5��v{N��i�g5�
Vu�ψ����=������e�4U�-�I2���P�����~���mBZ����v�d��q:�)�4d�?�-B�9"��ln�NofdT��o�ڪ#,_y]2?�d�������s?x*4����lZ��6�<'�i�?�d�-��5��3�&dpO(P1�.�A������p(ؿ-���O�����֕���7���~����t������i��=�!�oł�H�&�(��?��;\��̳<�53w�;����	r��+��O��4����Yaln'jF�|���_Sy����/�|]�j�BП�*-��z}��BϽ~�k}�:�1]g�b�k_��	�大7���?�+����W����<f����l+�Ut�M�u1æP���Jf��o��5�W�z|���v�>��P��3[�i�ZM���=Ym��O��?x9)t[z��Ud����� �BУ���a�TU��&n��9}}�*�����E����r����qFkw}/��m��.�_t���q5�����%��0	L��GĆ&wѭt�)�6X|�-���/u#Z�V�/����/���]P����Yngܾ�������]0%'��pf	�(N�-%W%
�0bٿ�VM���p"VE�W�'�d>��.@�<_��̛G"/K2G��K�.R����w��3�}���Z�*���wVO�)�	`�,c�Rk�f��L�+���'Ӟ�r��e����Chś><��`!9WP����{؅N4�8zܬoB�T$�N}��6Oz=$�]�C�ܫ|ҮU��{��.��"ysMr��u�O�i��ʠn���ں��
K�L�~(>Ō��������aac��E�o���
Up��_��a~�'���{e,=�Ai5DEyY��ډ���o���6��G�ʀ�B/�Ӌ��3�Mu@ߘ1����
8S�DBv����z��/��ԗs��Ӫ�>��t�F����z��P��3u~;���`'�h�S��*a�I5y@���F�L��N�3��_��	�x	�"8�ݸ$w�=^:l���b�L��y�d�� vEj�Af�H_(S��I�:d|'�&ՆK17/�s� 5U{��o}e���֑Ĺ�~j���5�'/q�{r�<�����~0څ���C��cZ[����SJ3��iS�� &�9t(�{o�䨒��>��m�|�nFI�}b���fǀ@BLB3����U��5�N���2�J�n���+U���x��_�G�!���9ݩa�4:��f��l����"P�Qx��GM	������}��&���p�.��%4�K`-h��� r&:ڔ���!���j�	;�/��%�ar�$T��X����x��l�|bwp�1۳�6]��0�l;�XEkI�U��    v>G-G��� ��ɌГnY��)`D��bS�����T�oʸ�G[yl�������{Bt���:�^�b����:�#�9�"�zb�yEnD�#�!~�#��Y�����~q�vӸ� �� >0��V��{�ŝ�ī;)���+W;!(�X�	B��*Z�BU��TN��wB��>D�ГX2��D�S/v�S
38�����l�)sC�,6�'v�>9>��#�_���)9����dEXn"�'��i�4��$��vU�UNG�Vڤ�y��n.�����gyF���}(�)�@i/� ���#X����/9�6�gB'b�(,{�+�#��? lD^g.'ҝN�K4^��#+�
��u<��4B� *q�c{N�\�kh�&6a"�jbM�Wr�{(�蓛k��H��#�;�Ƴ슏�%w�����^��U�)S<��'��Sq~ŕ�1�^� A�l����)���X���ޣ��H�W�?����a���:q�����:[��x�pKڵ�(��^���4C����<��F�jǥr@� f�^$ػzeu�����#����4��b�+�F�T+��U�q����l>�O8������]�:��t�qW	p���*����p�O�w{���К��t􀞄�d�@�^�{�ӽ,��usC{5��'v��P���&͎sIy��s.E���J�i-=�?���E�K�Z�O�*6���f�Y��ۙ ��'8�SdQ�=a�_������[�>���)�C�	i�2�J��$��+����C2��ľX���Q��&=qG�2�*��4޺����(?�z>�{�Q3b�,0�*B'���T�6���k�3jQ�"{���C��>�]��O����k�,��y�:ߖp+�#��έ���H��Hi��#��6�����-	�CjV}��3ߠ>F��5X�r�@> ㉤�����p����:�Xܧ2od� k�V9��|bLPb<�]��A`jB��藕;h�2����@rM�&�s%�]b�/�Nb���POR,邷��l*ܦe}�|T���O8����J�ǚzB��gx�f�|&��0�\�j},䦣A�1F摶/y��j8�hѕMd� YMH���s�[�)�_�p�2)i�7z�T,��i��#L�5#�/ αSVGGhgw�́|$�X�̼�� n�,GQv+�>[���\������(X��'�R���i�E��5���ߨ����c�ز}�����T@�����2��l�b�?�ӱ�6�D��	��7������Vڍ�'���H���R�����D������ ���	R4�{WV�8m�C�'=�ٺ	�Xq�@��{�Y��]Ԇ���0R4sN�
���xVeu��;/,4��!l�#.&���
C�Ū 5=B�p��j�7VNzڊ���85k��s-6f�-�C;P������V"�����F��Je%\c��׉�}��IQ]>d_�#�_���cQgJW'�,���a� 5�z����:���z ����f��A�ZB�f��[Fx��U���1[m�1�����]t�<9��D�I�oO<�h�؎�N;���3��	7Ѥ��yͦ�v��.�(����x�<��'���E�h��d&o`�P/��� �Qo��:ߋ�������|�p�6\�E\v�'H!yd.�e�ĔWb��o�ߚ��`r$�+���@�e�o,CQC$��0ِ�����!u,����=��'��Z��aU�e^Ʀ����ى�b]`�>��6���G|����1��b_�WC��M>'I��I����V�](�f���8��'v ε����Ǹ%�#1�7�Ew����<��{�춀�V�a����<�@Nf`NC��F]G�j��0C{m�<��~�������L+oHcjVХS�m$�+�j�	�9V���9Y�+���x��|����B��M�]��I!/�n'�㺚�t�h|��|���Y����P��I���SDd� �K�Ła3�I��gq x�v��C�I�.��0�V\ "S㱣�Q��N�Ԝ�mߺf����aa\�Xr�D�����/��>`F%g8Q���8lybа��p /L�x��y5Z�]d卨����&� �R\&�(Q fCd�m��*�F�P#�T\� �%�E�g���w�~H_�#��gq���-e��u�����a�$�6AMA��	s2  ��d��	���붤<�Y9���VꞢ�����x�g!��#$���}�ۡ��A��SU)&Z�i�w�H�֩�m��t^K�.��J/�w�=�w���@�c�����n~Qu���l�H��B<]���Q��k�=EA�j�Gu�ģXm�nr�u�	$=��qH����+���Ul�6��S���4�A��0��Aٚ�G8���~(ֱy��NQ{nJ����0N�ڸT�y>�P|�N=^�R�rI1��ePU��.*����ہ{�c�Nr*�J�?��4~@=���w���3�It��?�/�d3�S|
���	�KCz�;W&��-�F�S1�"<��ps�Jh':�a�\� ���'={�O�Z�ˏ��:�v��Yl�x�ZK����ͧ{��ZUN�A�VF�u�"�X,e�T4�a�C��9OCv�|[��%/�.�ŞZv����B�E��?Eϧ�u���h^�����M9&�3���7r��ՙ)yj��(T��Mi��^b��Pq��=2��FC�sf�6��z�������lub�}��Ϫ��W9��:`�O��L��j�+?�����6&�@S�P�96wF䮮�$��t],�ܔ�z��7đ�|��c�O�?��c�@�����ֿ91O�Od��{�J\h��q)�R`sv��c��_äs���̤���q���§� ���N��\b����Ey�'M�僎k�/*ڏIMƾ��K�2-��b�d�;Mʶ��I�FN �m��h�= � a#r��� �c�;p��c-|���p�'M��(Z�N��1�a�*/�2��7��G�>O�W'�����۷��w:T`-���
�r�5*s?B�A��	j{H��O5�/y�2����do���t��sc̅�w<��v����a(7�8�������/���8�)
����Ύ1l���(*wr�#"�#a$�{�*c^�_��ߒ��z�������?��x���������^�8����_k������������1��o���8a�wn������xy�tx@�ߢ��;�ڞ�nu��ͱ^_�nE���&��$nb�_�\�g���;�vZТ�[���׼�7F��X����T1�XU�Ni�nW�)��ӯN��)�|�_I�9WկE�}'�8�7���D��#IOF�{Y�s�"{5�/3��Y[�M���32Y�~O��7�OEP��Rv'�WX��x�%����pt���a}��4��N�2�&&�	x�H�j�>���s�
�v��Hr�h�@�x��90Q�h�����H���$Yx���sk�^%o��Yޡ��O0LX�%�5nٜ������꛹M5PЦZ0=��POqQyH�e�}J�W�vW�:.	��`���'K���z]�|�,%��m�}	���?A��m�1���q�����d����}�A�Ai������T s�*��p����Z)�MF��q��7a��*xAa��tK��/��t�)�	����n,�؃��N]=p7��?`���h�^��++� �!����u$0_�d!��2������W�>x�w�L�7N�&r�N�}�ݛ�o[A�%oC#%7Q�ǂr8T6��:J�d�s�s��ZiD/�5(�eR�g��BO�O��=X*[/�}f2�5�����/�܃k��oiȄ��m8]��R�X��mK4L������c�sN�~;�5O���׫��tR0�իix4�2�=� yN�I���+�&����0����E2�7�q�D�d�6�<Z��5�3�#Å�3>�A$cI��贀��_���*3��`��8����IT�&�����X࿆73���Gy��#��*�U��C�    Փ{��:�4�������D~�㉟>���Z^�J~����1-�k��E`2+�L���^#v0��/(6��Z>�&_D��߄�k-��>��] B*^�c�H�t���Y��b��n/��ػx)���Dr�&\��K1����߫,�<(;)"�����M�|U-�G��(|-�o��J�_�G�=��){H�jf��O$�R���S5?\��8�Ն8OX�A�&�?8��3�
q��Y8x0$/�a\��f�`P.Pc�u���'_Ė��7�-Aaw��tC��2K�d�U�8�V
�\f���M֦"�'�����r|�z%�#Tq�+�&�<���8�y.F�<uh�j�P�yg�����D�"�%}��Gٽ}����ۄS�n�cYΦ��MZjgιs��g��|J6�O�kF��J�=$���}��{�"��M�J��������{\�&LT��	��o�k��>�~V��B���l�U2n�˫x��ĕf��\v=���@߼�]}ҥ%���thP���c:�r!����hߒp'0d� �����|���]�_��_�G�=�fZ�~�E'-PU]�MV�rAc���	e���$�[�9r�]O;r�?3=�Eq�^AQI�$堜i9HC���~���x����_��@R���*o�}����	�Ⱦ�r�M^���}Y&բW�U�=]��^]��\Q��q`���20�620�P�������	�[�f�c'�����o~�q� Ҥ�=*ы�
�IeG�<�+�Њ=Y͏�"߀)O�6#�z������<���l;(���0�1Ya1�w�{�;P{7jR!����m��U���ì�� �����`�e,]��\LG��h8��_&�a�ݪ��0S���yN�n����?�7���� ��������7/���k��'��� l��}K	��L��eҫER�f�H�r���R��Qg��w���M��"��V?&���6�Zf�g5�����D�w��mx�P�_�����)9��Ҵ�q�8]���|5��b78jq���"l���	�k0���E�t��I�]YՆY�A�%V�׌�#R�yV#	�����nA��x�z�`��>����ë��x	:}�%.&IP���(�9��p�'��9��M��<¥�e�T����šzK-��jO�*#Ħ8�X`t �~��Z#����-׊�*l�/Vx|o�-�M�S�#C��r�鶇'0�cH�=U&����.�W��D_�c ���U�3DLc�|��0�a,�y+=�������F�}�Z��&�X����~8�[����$�N�'�KӛF���-����6���P��6��:O�j�p�	G��	�(Y��Vξ(��
�T�I�i�/�#�>	;<-a�;���1ڋH��YZ`$�[qWM5�P˩��dG���g[��{WW��@���0��=�z�~Χ_���{	[ᾈ�K��ה��vwXe���~,��i\��[5y�A^uZ���Lg�R�߮��iO� Ŏ��P�ȷGX�+H��p��-zv	�D�ȣS�?���]�#?[�s��ѨNR�,��i���蜎�te�>��C��4�Дud����x�����a�h.�������
)0�N�G��E��{Pྈ)��o�kS��>�SZE��⯥�SA�hv]�+�݈�����T{�^����w8`�ƈ��&�y�n� �����/X�+�."�����*��y�%`��I|7���Z��e��c�ٷ�q͜�%Y��ª�p��O��I~��� a��T@* �2hT�K�p��N,�pP�ux�\����y���EG=�cc*&��a��hUwk.�s�l�nv�dg�r���Dt�:%�X���LK�2x�4欠7Z��;2*�}*$(��SGħ� ��@?VA�{j�@a��ӻ���z�/7|��'H�Ën����4J�]Z�s�e~�r�"-����(1%1Ay�le���8zup��]�|~�j�}�|��ʶ�h��y�ޒ�wT�9�R��X�N�hF*:?�ڞ,u4z��wI>�z]t�r��9���Ur8C�Kh�n��R�;U�U4��r�3�0~0xk3iiɍԚj9r�����]�F��)��#���I�}��(�T���c���"�1:�ï�Ĳ�g�xרv8X���?�#��4��e�Q}˱fv�,���T��:AK97�M����@�i(��v�A��1*`Ǵ��$o=�	 �Af�jj�yl�C��(xw&����o����r ����q�7�7�?�G��Ŕ�SY�e4^�ɥ>������qt����a���v3=4aVs������ъT����-2�>J;ȁ�`�4��s�<Ft�x�Ju[�����ъ�������6<�W���u��Q.�L[槕J���N�s!��9{��ޙ�P���F�
�8��r�d�=�ݲ�ZT3<�+�1�l0��;�H�U�C���EV�v:����"V�;���8�|4i�Кj| ��T��#��i�N3W��v���*��)��|� j�P]��Q��_u&��������a`Ud�ߚ��>fj��k�X���;�ubXRFW�Ѫ..g�����2���i��N;7���i/�`/Y	FT���Ɂ�a,�Q·.��Y�
3���r��"Tba�M~M%~Aa���s�=o$w����Zg{�D�*/����ѭ'�şs����N����D���
x�$A.�GقW� kl��q�0�l����R��P�Y�����#a���������W�Qs* ��i��л#��6�1
������O�Е�pP~�z�����֯C�WU�W��m��|�������r]��D٦���.sz0��w��Oy��bO��$����.S�β P�c׸Tr��.a ����L���{O�KI���^�V����Ͼ�j&%a�ڌ*��r�~�gd�����UgZ֍o�S*��s�CU��F��ʮ�<T�u�I[�%j�:�x#��$�ڂ/�/�^��J`����b<�*0���λ���&�uj+;Y�����ʡ���5���]�>gD��)�u+'�m&ܣ�-KՇ�����R��L�[�s5������k�_�_Ĕޫ��M���ٔjKbnj��Bh7c�K���G+'����˂���LRN��M�t��!�@�|��#ٍkX?V����xU�E�OR'U~39���7Qx]'��>��S���eu�j���ʻ�cL�i��y��~�Ȟ��1����h�jjx|ĹȚ��"7t:�DN�͕T��醔��:*Y~4�YR/����hb[�9
gW��8�ޘ���������/ڈ�t�L���%�&@`�OZ1Bƌ���ȕk�,h��pG�h��R&~P'Ԓe~�V�4��1X$\iWS�\���<KƦ?~��$�{��Pޢ`ʡZ���H�~
b˘1�Z�([_��*.j�=���糿^D��y�3�\��������oW��q�A%ؤ&{��l��zF�^�q����z$W�Hø�u`~f�yV�3��Wu�_��R+n��|(�թ_�PIE�]�Rg��j���|{�Y�[e�Oz��9�U����;��}��|�W�p�-1y'�j��e�Q��=šp_d �(�^d|Aa����De�ݦ�V�����-�<7m]j*Ɲ�IG~�C�L�K��5t�ULc���C���.ވ	Vm��=.OuTL�ɇ��9���c�l���l����ᨚ%��daS;8\&;�+�b�,�TULS��鮬������a׬HE,`��7��5*(���� �E=�U(��k?�r��S�����D���=�#fl�	��n�A[<�J���T��8|�M�oS��Htv��;'C8ȃK���x8h����O�=|z�F�!�X&_��Jߤ6�;��9��Ƴj�w'�Ղ*�;iBn�!ɯB}>�
azR��d�q<��`10Y�"G :k���}�Z�zu�*�f):�:Uk}�p�r�E}w"�kS��>��QSb�6����b%�+	=���2o��U�fe�~��-Pd����6&��t�&�#��~9�H�3�5    �r�ǵ�+m+�Ю"�;we���Lg<�
��l�]�)y$�6$�翧-ո���|/���m�pλ��G����g�Z?̴�Q�K�������wQ͛�΃����	_EH7�Xh�x�C���9)9>�����HtA�nh�'߬Оp�8?��.��Ux9-�Լ�O�+x����*���������Y0�ST�X0I����s�~�,�݆��J�S|X�?�?϶-u)����͍���rh�U�hoCsw�����X�ف#���CR>)�!�5tŸ��b#ظАi��F����x�����������~ڤ4������u��ch��	��\������g��7K4[�z$�rdbU:%�\Ub�"Ѫ=��*"^����k�E�Q}���#���7�]}��>�ڍ҃�*yq���>.���6�`��x��5k����ہ��U߰F�k@|����Ν��"�=҂�LT��vĵ��A����4��)ҳ3���B��q\�>�K�8�������{|����p�	�&a#GƦ��t�<�L�T�9F�p�1*��ԧ*+�Oh��ρ��nTY�W�D7��RU�g(�{��/�_��ʏH�/�#��O�O
��Q�����9	��t�2쳬}u[_��)��`;r�����K:gu(b�/*����-��jC�>oP��1����^��1/~�W1���9�L�4���˖�l�����B*���e�01f^���9����TN	y��^Q�F�0f������N(C�eH>�Ɠ<l�Qhl��#�d���?�R=��Z*��+'|��o�/-�/�G�}
�8��W�I��E��k��0���e,�7E\͟�D��^e����.�<�a��/t[�Ւ�Ht��`��}e�޿�D<M����<���tٝWӵs�5���U�-��0�;��(%����jx�8#����K�LV�Z6`�S!#N0�a��+�d��J�ϪD<�j�?�G���$[��z��y�͠B}��FQl+Z�~kI�rɢuk�&춟D��7I6���� R���!����а�$�e5A�(�����)�^�>�:Q�?�5���O_ڴ��d�%�����6� �j��R�.�`cS陶��!�[��O-��6�PS9E�:�B6|�Af�zd���V~�#�h���	HI͎�Bu�WP~��yEz{w��|PF�x��������<�I6��I�����>v�[� �X��r�����s&-�!�0Ս������, ��)�Aʁ� ���L����sD;l�����ʂ�M�Ա���
�7�{������!��>!�D�Y4��vP*Jѵ�2m��x;*�@��׽�1�/#����b��r8�z�i�/X����%��y�ϳUE���n�M|����>��3T�옲K�L��L�V�N���Y��$��-�W��Io�*i��>��	�����u���2 ]~o�\�;Xy�=sI*9�<��;WL��=1�`����</�#��zv1/�.�o6�K�F�y��!/\G���v���S��7]�D�r�A7Z�s�+��"�Q����	��EXzvx�5L�)xzw����V?�Y��yxO�d��Z��KW|j�"&��nJ��9�["�ղ�k�%�~Y�}"H���Oe������7���#��t�F#a����Jg}܅'�?�z{�xC�Q6I�nb��)��o���
����>�	V��'�]Z�G�s1��+!��稈����#=G��{/գb���욲����Z8�F�q�Zܵc��OW�{+?�I�eD���N�%�Y`��a�A`��2b�b֧\�P�oM��h홎_|DE��*FP^��TÈS�V����N4�X9;g��O�}��R�0Ĵ�HoG(�� �rRV&����p��y�W�q�(��{/��E�m�ңq[/�#l�r*�'}L
v�����R�G�����T��f~s�-�}�Ʌo7�
'�r�O�}ʂ\�bE�gv*�Q���e4)�}f�)��E[��Z>��ͯ��λL��xv�)/�3�s�-������ҷ8]�O��|{���b�]cT�^cǄИ��Ǩ�nQHm�灙X���|�����~�Y���f�2�g������8����u�Z>S��[��3ؐXgel��CԪIe�0��% %�b�48O��W�D8aP��n;���aǈ�o�m�@a��sq�\ގJ:)���!fG^ �6����y��w��iy�vr�N���%�zu��R�C^�KMN"��9|�H���T����B����w���zAa���D����v��U�n���m������]R�S����D:��%��o6�Y��hcڗ7h�"(���b)�좞~?�Ӷ��z��{�+%ri�U9h���B��׾Y��WA��fKZ]4��Mv{��n��`�Y���s�����aFk�ꐨz�~� ��8L5�Dz~��x(������a������{���/#��n��Γ��d^�~�nF)���(�����'����`tK����jal��ehE�, |B���[�/OML:)-���I��R�(�p])���;���E��Wl�n�&��v].�co$����m���%�3By�|����ue,tV`��O�ꆵT�����Zz�'V����x����L�F��:X����Ͱ(�|}��K�y��,R�7]�7����V�9"��mN�O�\�EX �S�����Ab��[�~��j��''
1ϔ���������?+���I��܆�f��7�����#�^J��ǫ�.N��n��7�����]��zt>���lڋ��~nF�x��s0N�j�e�|q�9�Nmf���夯��c�����v�>bH=�d��8q�g��5q��$�q��7����{����3���y�����+`mxXWt9"���SSa��!����*N�S��{��G�3��5�m�َ��__�*�B�V!�y��v�S��CÌ`b��tIa��1�̄Ƽg�������� �ĔWC�D�w��'/��Y}���C�	�+J��LX9���p��8i���qX�ir��%�N���Q7��Y'{{�wAMjUyZ拠� �I��Kj^��l���v�ZbM�� �x�>����/2nK?i��>�~6a��&�	�-$g�뼞����UYm�۟�C5���齟��KIMm��46l�J�{���A���$�a԰�O��_7&�Ӳ�&Yk���`�n�L�k�.¶m�k��_����$�.���B~
����J�U8���P\�p`lnOI�qS������w.�Nؚ�����?��;qU�8��5�f��n�-<��æ�s�]����ʭ�}%z���3��;��p��H\��NQJ)�$3+H���B�wڱ��/�����u�Ϫ̜�M�uxJ�C�>w�Y8�~r�̤l*�q�e=?�11d���HB�W��"]��gG��Q"��OsV�� �s����C��1�ڸֹ��I.����zk��C��ᨿ@a�X L�J:����]GN�ί퉠-%/���q�Ջ��.h�E��� x�M������%������
b`�]"�8yo��=�v�Ծ{��Ү_����
�|��˂��IYCج���IA/8jٺ!�
O�[�3>�����1�|����n��p�� )��DdM��諹��ʋ�#��'o�	��7���-]=�~nK�.��ӥ�z_�GXO��S�����	z(�'4Q:�1�/������ȹW�<N����.1�ݟ��J����-��,O�����0Q�`xƯL��L��vP��Qq�O"�������ܕ�de���C��C�EI��*���|���tJ~KG`Z�D>�@P�q���n}����w���p����˗<��JI��h���B����.��W�>
�P��j`TQ���	�0���`腆����*,d8ƕ7���+������#����I����w��)�Rz$stH�^�Y�S�\Yk]�eK9ݶ������	��7!��:�3m*;$��    fe���a���^Y��nS�-��������/B4�W_�^�G�ω�s�q�%�}ݞ|mOu3?3��Ok#_�j߼?�������+�+���C4T&��듞ip��֋�E��1�xj�����;�L.>�Q+fH�=79������`�J�U�e]���F;���qX?CH������O,f�jIF�QA��όS:Z���l
�R;ʗ����;���3�KYё�e�az��Ѓ����4]�rs<�5X�9��,'(2���G9�;)�)"k���HQq�_��a�b�2���o�0i"�o�x��U�u��}�&j�Ѷ'm����u*Nn�2��@%�|�1%��s��ۓ����CL��
���i�:�����\=�i�J�Tv�	}�o���d$��T��;f���I��>����v�DA��g��E��%v�x|n�s?3n��=����!��a�{���!����09���{��F���Vy�%o$�^?d��۽����^w.�}��|�{�VKV�&e1�O�\��'۝T�ؙ���Ho�zR��>���{�W'L`�r x$��e��_b�!�d�S�=ks�	>��s�o�0'8y�&���s޷�
��]:]G`�u�gDi2���A����?��	U��U�� �I��Rˬ�ʘbja"���C��P�������~s�%r��7��u\�K�v�ͳٕ�:�Z4�T���-��EX6��ދ�p�P�a����葕o��b!�(n��T�~��=$c�WqR�v9i�>@�bQ�j�Y������Rk�2��(9�a&6+�U���٘��L�Gx.+�L��z3K1�#�*2ΨQ�Wic��f*��Ac����-A�u{��/��n��6��4�J���NLx{z>9�vu��-�pE~"�D��;���M.o��9�!������93��C���P�'f%�.���N	�dxm�N��䄮��=&k�J)�� ��Q��e��[��d�M;l��~5�H�D�h�\�`#߹{�}���3y.����	��ƽn�yAa?'�E��0o��t:�Y{��qdܰ�n��6ґ�����{|�
MF z����D�Au���@�c��#�c#�w�Ƹ�o����Sy޵B#ȏ*4���Yɸ��E��|�O�����v�hH�8�䋛�X��P�f�1������/#��>� %�(�qyj���:�>��k�J
��4�^(�,���>�~Ψ�v���w�*Zqq^�$]�)P�v�tٺ�">k���M$��D�������u�d3u�L;��������`�%&�}�7���cޟ
�ojɿ@a�����={ǩ3�f]�.��L���.�]V`՚o�b|R�5�S��N��׻6*#KU��]I�:����6��o9�������^
�=�-X�}�}�n��]m��5�6kP��fy[I�Y;�@eJG�z>-����j���m�p���
�>9�>'aN2=G�lU��Sb�R������l�;p����o��|����"��v�ͷht������*;%c��R�eud�?�e��j�"��}�J!o�}:?,E$T�'�$4���央��-�? ,_�����o�k����>���t�jc���ZRj�i��e��A�r;�(����څ��\~%�}�5&I`��lk��Fq1qy�j*�VtÄV�0D�?w2�;&�_7��}�}��訅���h�م�D�&�nV>��&:Ubo���t[��ur�Wp�a+�gW״M��Pz<��!�A��P?���~���w�H�ݜ������秫�t�+k�ʃZ�wl,I���(ry���Fsr:�ݭX�������S�<��Z�=C����pj%J����$�U�f,GW���ؤfRxWj��}�i��c������vߺ&U��umF���l�p�������~6񦓝�|��ȵ��^P�v鴌���a��B{L�N��f=��c��޽f��d�=�6���fc������$ڋd-�lEz��=e;*#�7ܕϧU�� �������J␝?�����igL/����1W�#bRE~M�/�Op��r04���n���L�ƶ76s�-[$o5\}C��G�U��uэ��,WIQ]��'�z�*�&$�Z,�=��G,��dJ�~�.��>��{;�^^_}�C[������͢z��׷q�[}Jw��Kߪt��{,���]��K{��"�.;�U�T��&���pǉ$qO�J����fL�/�G�=��#���7��~�bv����,��v4��W+�������h��u蒆I-�QT��sbT�H����Zl�il4�g�? m$s�N�wM�����6hW����Cݝ,�V�k��f������2T��3�|RL�o?�ih�>cj�]f�=��ֶE<������+wһ7�y�ϸ��;[^~]~Aa?W�uh��;�7������&�O�g�h��$p��[æ>�ȴ��vO�dFb4�>)�sq�)�����i��#R�i�!����d~,>��U���|���r���d�dSn�^�l.�Xe�<�����_���c��#����Im�����k��o�$*�R-R��Em�]��_�gƞ1����i쉰��g�Dv�ɓ�'Ej+�L1|9����S����4��mx��l�>D�x���Y��C�?6�O�oYc�ַ����<W�tfϚՕ��n���
��[6O���@ {T�&*���e�	�zHx��"�mZ�Vq��U�Yw?bYV~��Á�G��?�g�=E��x.�7.�F�H�3��ǓZ��s��
�ֲ�{��]R��>���2	iy9<��C�hN����fd8c�EDT޿������%����~�>����L�n���.�������:Mð��zAP�%�������U�Rѕ2�L�jd��γF��H�b���s$��"x�|���X�Ir����]_�g�c�yf¶wg-�p���UB�V�?Mj�'��J�����%�s"lonQ#N�7��@�����/Y�"�46�-��2����*ݬ���0zD������U�����Ǚ��Ҥ>,/I�Ι��d��-������L�ŢGmmC��1�;u�R�����V�-ī�)p���D���k(s[z�ɦ��Yўw�m�=Dֶ[�v���&f��PC��fYY�J��<��N�D����H����劵�
�*6B��Jޣ��>�N�>_�g�=z��L�术�����s�ZZ�R���QTG`�Y��d�B��x��Ҡ*/��̥����)�hA~ȗ���aG�(]�������g�Q�M՞mo��S�[�f�`���,�sT�ֺ_j��:	�]��T���b����)j���`��Z"ld"�kN��+.F�B�LZ�x���#W�?�������o��c�������
M�F'�l%߯���֭b�y�E~���8���x����e@/|�Ty����#H��+n�X.�E�h�EARr
���l-h�>�2�����t�̏���.�R�ɶ���� �XP�괢Q*Է�-�IX�n�l�ƧY�'JB&��zs�߳n����ʟ=M��f�ϰ{���UkV�oRWOÿ�K�G���a�N��оe�iR7�ѕ�M@а�c�ǃ����U�f����&8�p�����׆���b�U2gy�DQj�������8.$W-����[L��|И�Ej?�v�t�<D��PΤ~+`"LUn������N��m���ǌć�������cF_�o����eG���Eӷ�������Y�͈y��/�� ��o����|�aG�{X��p9łc=``*��a�ޱ@������a��e_|r�����(M^L�x~^�@��O��[���6v�)ȁ��dLE�_/�PS�0�8�u��geQ"���$2׀��F^G�_m$R�3
��C$a�x#ݫ�Ǖ���3�]�|w�`�]�&F�z\����e�Kc6�Β�m�r^/��Σ;g%�Z���cw��#Rf'_��D\,���(>}D���z�    ���6?6���ϰ�Mr�?̋Qmo�a՞F�¹�@:�r��Y�L����8{�w���Z�g����6j�ƽZ2���܁�A�[�,fP�2��0��tP��礃�����䤃��I��b�A���6J��گ��Bv���Km/���8����&���k�"�(6�k8�����,�F�`H{�NWbj7���@����Ui$~Y����[M�~꧓z<@���⪉S��R·��_ϐ@�j��&��>�"j�Sg��N[��q흔W��z6��V����#vD��ٟ���ߤ'�X��ϰG�O���r��"9�ҟ���V+585[�V�B셛�
��Ǖ�~�
݂�� ��m�s˕c�Yq��T�����������'&�~�����¼&nXD�0�Qw_YA�`v��!I�i��#�	��Ԣq*�6*1(�ǌz��P�R�"-���T�8���D}��g2'#U�Y��������vOQ
�U>u��p�("�'�ܾN�p2���.�&+��^!E��>�gX�#i��N�~4u�%�Ga�?���
��~x/��_�_3-ӺO�ɤyԎ�iQ��a�ɵ���0&�Z��(?�G�*HPs��uD꬈M"c�;��ʸ�����7'+)y��__��㟢��z���u��3�a��+d�6�L���݉l�K�3:�"mY9Z|_�dɱt���t�L�G�A��i^VQzE_�vҘ*�j�Y�����gY�2��L��ae�+v����Р��C=_-��Ps�J36�
�WP��([�_®�٠#&��%Ƽ�{6R8F���@��X�\�yU\�� ���*<�mo��� ��SG\�#�����&�
�f8(ōϣ��^#����h��M����|�K����1�0r��	,��M��Ma ��#��r����ߝ���8^���)���+��{8�"Q@��v�L���~�`2���I7��D�r3L_P�5��������N*茤.D�#��+ĕ8�l^�2a�j*i�����W�M���o��Qgq��o�2�G�|����q]������-��O�b����9ۘ���� �U*�^":W���FY����ۏP:����AA�����{�u�Ť��4vO�κt����rg)gL7�H�+Dk���b��A�p��D����)��Bڜ|*%���O=R�>��+*��3&\���D�q4�
�s�#�=P֒��漛�X���{r*fz&����.���b��K�,H"��T8!�:ƎC�j�R߿�9�o�(���]��F?*�_�g�=E�K{���j�c,�S]M�{��F��=�����|����iR��F�+u{.f���J؜�@���eT�%��YT����%H���2|���)����Y��h��D�ͽ}���v�*��П��:�B�z)*'����k w��D)���~�j����˨��M�ݡ�r�?e5|���C��Y&z���D�W���6��5�*2����U�v)��b⑟,��N����w��Cu�����b�qθ �͉B���M5�8.�I��1���������}�}Ӣ��7��ߒ&M�܈��/x`�.�ϝ�������R}�L���3KWH+����0��)sN~m�A�˹��\�P��r)ʋ�:m����M���3�QPz����k��[D7d7bsf��=���i,͓r�^���@�qF�(-y��̡�Ġf":2�rl�{�-�99S��'�4�5�~��V��@����+��GW��iΣ~���-����٭z���'v9׳%��ޒ+>��
�N��x����&� !�E�v��n��>�Kl��̊�HDv�!�6yE5;(XY+����ч������1��Z��p�c/�׵��B�Prebd�T�����ż:���� �ՐQ�������K�%
��n&}�>�}i!���PϢ�����֤�.6`�L��VZӥi���qF�&��̗kI������+�JjiP�c-:��\H������;(�|U�6�0�u۞5��*@C'�|���9L�!�t��xk�ᓲ�{(ɔ�JՀ�SQ'���R�ĩ�Q�p�$tD�:u��*���E���_;�]'U~S�,�A�aE5�h�A{	��l��3f;������(���R��K������Ql1F�-��D�F9s��rvѐ���N^C1�k+��t��I�t�}�}7���$��Yl�����5��롌���U ��^/E�A�p�
XDGl#o�cj�;	�H@��n��3 �B�w��R%�)��ɽ��qt��{5�F��Ɗ��17�ɬ���}�Ou�^V�Rj��[�B�B���S(��z)��]"2!abئ���"�	25;�7;�#�4�%�>��W��{��MQ%�Y���g�}��֬\v�x],mx����4�Ȧe�zjA&�"5F�+��ⰻR�Ħ*�!`��N~$V7*Tb�������<Ԃ+<��w8������T&��4��ѝ�k�J!�ח�[;�&S%�i�+Uϙ: fވm�����M$r���;E���`s�x{��o�@{�zO�&�X�}E�a�w�.�>vO��B����ư7��7};/P97�U�|L>M��-���ة��M\�:i�Kۮ�;o���>��D��av���G�_)��9T�v�'�C�k��ϋ>s:�*6&P�9�Vq�8�9X���FH��GŹYs����9���ꅮDeg�b���҆�3
��*N��=�&����6j ���Z���b�����B3�l�F�qn�3�ę��-V����/��ըI
<���\�
"�m�¬�Rw�G�6��D�����i����L���>þ��!��a� tR/��w�[K����S� G.ND"�^��B��y��~P�1�����ʖ ��5@6<3�xM��6M���WHQ_G	$���ߣϰ{�ڀ���og{z���'ä�}�ׯ��]�Q��D�?s�㿛��J=H-�m�s�[K�qvy�ϹA����j/ ���l��WZ���W7W����G-��{x�.����˨B��t�3wnprXqC��|}z��٧y aS�	S���=�d��>C$u���񅞣zz�k�p�yu����ڋv�a�T������� Bc��҈e�|�7�i2�[�92{KݤO��<u�%m2���79S��.������̉�'5KY�i[�q%ڮQ�~@�1U��M��D���p����8yL���R��w8��x���h�w]�d1@�i�o��|R%3.��	b�6���� �DC����L^a�^$f���S�r��R���%'AT��{W%�g��C�aߔ̲���n��K4�\��H��Va�L����6�NZ��6���<�5�Ԋ&x.��/S�.��,tzh;+����]�~@�7��ϗ���'U񙒩�O�{�2ԑ���d�����vܦ�il��sf^���ݿ�_"z䎥X���-��N�E�_uND��[P!M��H2LUW�;�sO�=pꓢ�L���7���A���zf���6ٸ��N.��Rhd;���.$Ky�B.e�̛BJ�b��l�"�9�6���hS�q�q[�9)"n @>`�t,���������$��d~A�a�u
_���ᛴe-_i���� ,���ēn�
)��pale2��K$V6��(�jޥ�ye�r��bY��UA=���Ъ���#b|�ω���T�/;���ɕ�~�(�ļ���h���Ҧ0�QbfUV�;�gm*z��N��a�n	�HP,@,SJ?அ*��h����t����/�3잢|:�������s]�$0�.{^���t�����/8U�X�bڧR�RP	�`���N�~��j���,;����6�����g�����z�=�{�!�J�W@^A
��~1�"_�F�L��K��o]�4�k
C�ʰ��>{8#VGQ����(>쩈U�s�f�MBv���*���O�8��a��'c/��]V�޷˃���^,�D�pǈjbFuD?�4���K�F�sMMM�I�ˮ�J�v8y�Q߇��b�S��ɿ{���>��)����&��س^6�*��fr,��Z.�e��X�    WJf*�7��%_�-nM) ��u�!�0�~넁AI75��6[�U2��<O�s���Bʗk��)jsP�����Q��RTз�~�E�������e=��&P��B��i�
�)3E�L��Cg��������_��d�/��ԧb)n�>녑�ܺ�U���A��b�9/�ו� ��Vf��w����#s�
�A#��i�Cq��i�MZ�w�_P�4�\��^ڷ5).�\No�2�/��y�X]f`����j�+"����Omx�EZ<���JFV������������@�ￂ�4��L�7��h��v'S��';�J>����N���U��n��ߌ�P�o��O]i%fB	��*���2.k?f�[=�u�{!�=���:-|\�������̫G��
��R���3�]#�̊���&��1)؎���q5kb����w(v�6c\1�2��0���Z`�/�r[�$�O�y�R�cI�e Y��{�I���_��$��-�+�{(�:a%��d:Z���:�~\G���/���D�%���$��M��X5�h�-�RR�X�E,�yN�S2T7b�EJj)6t;�M��������i
ݣXЮ�S�H�I�K�.�ژq�]���c�o�����&g��v�u�j'r���M��t��n9�PӲ��}hC�U������CV�G��+�HOe�)v����c���1Z]/�>F��8�xE�|�g��m-��O���~�+GMA6�-�u<�v����Π��gZ��s�l5�?bK�W+�}{~����>o�4�p�4m5*�X����N�`��� eƝP���{K�b��5j� /j�(�u���C'�>�~�e�����<+��)vOQ�l�d����,�Fyӭ��E��^�n��vV�.���^��X����(�/d�G4'3xG�f�u�pE������|+�Ob�}/A~8e���G�äɞ�)0�]:Uw���G���ee<Y��K����1Sڝ��Ϣ��QK�xU��+B��x$"R��b��
�}Cag��ff�}�۳�JoK��7�d��f�L�3�Y�
A�1@�q^�(���)�F�9��A�w%�u��G��Ftн��JM������A�}�}���>F	E�3��j)�óz"|��:'i���E��}�I�Hxӻ��.��zhn�Y%��]	I�T�9���yĠ,���%'�౓�{�����t��sһezvg�uY�����d���Do�mrs��#�`�ۆ?F!�}��= J�����KJ��n�J�/�<����K�9��:�'���%���/*�f~�eu�<L�3���U������#�jw���Q?~��+��_������0��%���gط��h��t.[�/�e��x����)f�V���6M?�����LT�X�#�G5e���BaǰeH��W��B$b̃��G�#�G�����*�:~�r��ط��.�-9�:�ל��x���S{w���e:V��,�>I�}��VfWDM5�@
Bn$�l
P�3�ծks�qp1��YLĂWy��e���c��$Q8V֜o��8V<1&��2�� �#�aZ�_�M�ZB���":P���{�jgB�khI"�g,�\D�U�>��y<z������7A�Q���>��Q�$\ބ����A�j��L�'���^��5?�峌��g��%�x�f�|�7�*$j��+�(N�@�q�a1�J��H_����͏���ϰ�kdO�6QMN��q��l.I��G�V�������O�#����i�%IU߈yU�&3�5�o��~�;T-5@L?����cze��9�ϙ����dzE��#��t�`'����Gr��k����}'��	O�C2\'�O�#ߖ�k�����Ha���uj�!1�EW��=w��=�L%`�+���!3��K���W��{�����k0j��h���rP}j�ť���H�w��nnv�����C��4�'�9dB'#�	��ܺ*�丁�*��"ljf�4�����-8�ܛʭ���l��egZ��q<���R�l�R�YN_b[!�2�n�X���A_��9D\�Mļ��� �`8�]�X��n���|M�{�׽���^��u�G�����������	rN'a`�n��yp�����+�t�xs��I7�΅�Ǟ[��B�3��P�!�.>���_�����{~ߵ�&�������g������h���?:�������>]��b�Y��T�u��_�8���c@'qYD�pV]�@��@>1{	T����A�_�A�O���w�{�v�.w�Et8��.ε6;w�ڠ�&O�c���}&�k�-�H@ �y�z�Yw�S�i]�V��
�^�q�������0U�����Yp����v4�M�<�q!���x�۪z� ��Sö0]���?�3
�%��*�0|v�e \뗰��������?���/��>þѕWi��)�M�	���r<�>
�,3��W�qV�p�+��!�WtA��gV��8��U�{�����<�RϽ�d�Z���i=7y�}��n�N��������`O����䦻m�5s7)���%����
u(�e�z��N���+���p�h"v��ɱ���o[�h�M}�L%L~8+�'���%�(wq��f��Gi����Fa3J�f����q�{�_!zx[��cdOŬB_�07�=Wa2	hO�T!�2��~��n�<�-�'���U����]���^񡓜]�Һ�]��h�w�?I���z���I�9d� /���P�DYy�Hf�wK�{�m�
����XѦ7���s����j ʏ�6��ϰ{�
f#�V�O�;�����	���bæ
��0����R�NRG}08}�v�'��70���x 06��'*I5��q藣�q�/��1��m��QE.�(�5-�>̮�`�C���ȩ�����r*�<7�Ɉ6ʚ΀m�v���SO�՗������T\��3���r���9�Z�IV�x1%���H����|���Aw �̠ �7�[fiN���7*��3�@r�Gb5@@��'�K�yq}�������8@$�۾|�.~�ߦ6�Σ���ρ�^�X4�6x��2�m�80�T�mu��t�1༉��r<Xꫠ����@�ܲ�h�m�Y��N���B�I�P��A��rz∨�N������q�^�{S�k(�f��	�ODY#^\�[���q��i�a$r5����a���0�LeA�����Go[-~� �� �}�}�`�/m���u2�m�K\�DXmǋ�9�K���d_����u�`��5��W=����G'2�S�������js�a ��1aAg`&��<JL[v�f4>������,��m��m����'e���w�bŔsJ.Ӱ6r�ԸA'� ��ƫ�W����+��E��`�T,���crM)�MP�Lg��g���)���6@�L7�>��g�r�Cr$w�_��@:)	�g�AG$uz����c��!¿���7�G�+����Ҽ<䇡�j{�MkRX��*��`�ʖ�i���K��|��QRA�������6�5�4L���140�X������<�����/�3�[-'��+��Ej��Z��5X���7E��3h,V�uD�[=�OD~	B�8��"*N�N��F��F�bI��;S���IPpe���#�R�Ǫ��b���۰=@z����UNt�|ٔ�8~� �h`���7H���(�C>�0�h�H?�I�	�������'�Qk�>�ۙ,�M�/� �Y�hYcL[�y�����w*�Cb�q^�k�<�&�L	�Ӛ�@@}Z�M0 5%�U*�ܕ��x+w2�#���>þ�>�D�U�iWsS��Xc6�N~V(ɆJB��+d�@�uV:U�"a}�OE���\3W�*�����^�5�����Ѿ�W��էR��D�V�w���n�x.M1s��F85�����^I᲻M�����ۧ	"�-:h8Q$�ޕu)�����i�Pŗ<L%(o$n]����>Ae�/�������v�72-8��5�\z@Մ�-��r=��^o    ����U%%�����S�Q�RS,Μ[&R�V'����Um�]�T��d���d������C0_�g����$bp6���W�y5�G�Ʋ\�������HB޷�gi-�/� �eu-���0F6��H��tN+J8p8&��b��~ �����re��y���VI��F�aG4��I|M�h?Z�+�X��DQ�Äf2�ـq}�e>�k�Ȉ6�Hᱪ����Md*f����oa�(�^��+=��JO:��_;����Lf{�آo��q�D�Ϡe80n�I)�:1?���y�pѧ��'LB*cr��5$�U},�v��D�oT8͝��>�޿e�AX���/c�,�8lC�2N�\[���v;��6�\C{s����.�+9��.�%���	���r��ԭT����'؀%7����,?�$կQ^�I�����7g�Ʒ°��\��1��ژ�wu���^�u���X�\n�Q���}^:"��YM�F����/����7�~������C.���n�a��P�<ٍ�+��^j�~k��֕MM}�0�~�����B>�EW�6U�����pW����ջ�?���O�,���忢ϰ{�:�	>�;s���M%�ri�$a��hb���F'�������9��q�
Ptꀁ]lTb��b�뛏�>��*��vj шZ�����_��\��YX��V5w�qVu~��q��o�:�&��+s}��BAJu��Jŵs���X� ǹ�k��IIr��Iv��2��w��+'/9�"ʿ	O���ϰoA$.!��%\-���+�yS-��U�A��v�uE������ʨ����J*�!�'̃r~��O�NOe}Eh��22�I��?���.�%��Z[m�#�r�S�c�O�Վ[��IϦtX�#ǭ�'Cq�t�"SS����4����*�;�~�t!�珰i�E�o�*A*��AЌ�fS��%��"ڙe�u�l���y�
	v#
�`T��h`��!}DD[���Z��q��W+�S�zǍ���W�F�p����p��gxw��`�ze]Ʋ%"[�wg�G$=L�y@wr"v0`��֠L ��Ĩ*����i�5�'�#�ߤ툿�ϰG�лY�[�*�zY�b�nr-1�v�I�Z��y�Ӷ���`�
;b"���ʩ��l(�B5�7
g�����%�{F�O�bYgZ?�b|(�n^��\m����`��Jq�����.�DicJ�1�a���%����vs���{n�-#5�Ns"���9����^�](?u���8��u�E
�Lt���M�<~�Z[,O�	��^I�4:�^Y���>�M?���Q�#AQ������[�vBT�&j��
��룬{�:�VY�=-봧ط�n�Q��[Z��;Zt�B0_��Ptש ��nQ��K�:�Z�Rw��z�5�Xg>��_i�1�OK�EL�bK������G4Z^4S)��OW��'+������,�>kM�T�Z;ͣ�5v���WL]��������{� J�SFA�(WH4�mxs��(�j+@�I��С9��H̬�E���L�Dw&�$���ϰ�|�7����,��m~BH�劯���44b����>�sǍ��v����)�n���T� k�4��V��Sq���Y��ܩo��z�D��s'�O�o��d�P���<�_�I�1SV��^������K@avT^!S�U������K��ƮLw�W�9	�q
��D(7j.��}����\����J�~�U�/�3�[�j��fh�+0��^$y��N��l;@�%�n#����(����;_���ACK��k2��r��X����`�|���ƙp#��>��	�T?��u���� 8*��ha���+�R�T"Ŧ+Fbw�%�SOF62��OT5R5�yԈ�T�B���q��������h�ف�����햫@D�I��-��r&��o��WB9��Z��N���J�M�>`�6b��3d��!����ߗ3�O9���ƙ��5�䩠�]��ls���l&����g�(~�E�Q��\�.�4 �VM����:���h	�^�K~�"C�b�/Ǚn���n�YH&+an�;)6n�K����;�]��%\����v��N�W�]\�V7�F�K�P�n9U1��,ԫ��W�_�=8�K�<�Џ�_�g�7�dv`v�[ܵvv�o�)�����a��I}3��K�k2�a7tF(�Y��
�.�@�@	X?�R 5�S�=�!�|�g��@:�l`:Q(���/�	3F���Ѳ:�Y=l���q&h��𘛼��q4��Q �1�m"��!�r����m�_������֨5\j�*�G����EovA�8�je�Sx��[#��&SA���o�3o�)���@��g�'neMr��H�8���ۮ��}�_�3Q�EU���7��6��0�����m����!P����ȗ�� D��-�3^0[f"1i�X��0�FvJ��c����˙�{�$,�I?��G`X��a|��<ڔ��ޓS�6�[Kǟ&�"J$��q�/f�����Z�0��>H	U��J���x�nEՏ�?��8��u��3y��:6��1.)9�c����J$\d��9GQ�\1�X���pEJ����<�(�z1(�
�J%~�!P��r����m��+ƿ0w���]�6kۃ�īkI�|>��^��ܞ�:�DD��*}y#�9\�7��C�֑_m䔡�T�Z��7GS��F;8��ˑ�v��m�'��Zg��t�ƍ>l&{�4���J���|� �um��YF�#���\�Rc��a��<��=Q�;���LД���x��7ׯ>k=���)i�<ž����_Ҫ�p���F��Ӿ��檛H�6�@{� ��L!
)#@���y�l"{�x��v�Ѹ����{���5'���h��)���=�-)<�-)|�-�G��B�&�&��} Ugf'*/��/��H��?%i�_&_ԕ_U}P�[h�Q����6�G��˔�h���-���-r}�ے_�қu�k�%�)YR�b߄.���˓�uE�25�xگ.��h�-7������(������qWp�X,LW���R{-D�v$#�#\dߞ�����΄�>��'������}�G_�m�o����?[ц��'��夸�f�[�DYX0C</�H�]���ǜK�:��!"��m���X��qJY^�S�� �O\Կ� D�0ŗQ��[Fy;\���&��炣��]�-��D�!7"������`�
�=5
����wm8���	�>����/ș`U�&���-�k�H��{5s+�;�����g
Z��Rv�9�CD�+�H�+�25;��L��N��D��4y�j�"eW���bf��"��4�|��솃
�GC��B�J�b��])c>�DX�*f��Ŏ&��ł&��D�-�Fc���#t�_�3U����xw<Z��ͪ��ۯ�O��9���Y�g94��
�)�uI ԢV�a���N�i���Bw�?�f s)�iE�_��������&]��s+����ʍq�� mFk�N�o��}��6#]���`�U(H�8&�Q^>���3.��Qh[���߫|��o6�_�=}oGO�o�y������vIӍ<ܨ6�dy��H����l�Y$�7S%5a�--̪L�UA\�/cI�⪈�&���HM��@0�4�:�����y��ҽ�zRM��q�&7w�Џ�n��|0۾����<*ӗ���0n��o�F0�8���Ғ�1�1:�Y۵\�V'�N� ���NON_O�1���|C�+;��Y��Z�Fk�h>H�UcUn'v���{{犲+t���X i��5fFv�b�8]wH�<t����hܥ���.�������ڃNU�V�;5�y��{��"���P��qj��[�G�4�2��Y×A�D��9��t.� ��9(3 @6G^���3$_%!���,^*�$�Y�I�S�[�ArѨ�l�ғ%��7�7��xX/n���a+������<u` ��V��C�B\\�īGB�AK\@;s�.���|�}Ą��%�c�Iz�G�}�}Q��a;Zq���h�'X��Z �  Yi3tn�[��r$n���^��@�=R�iXsbg7�4!���J�6���L.L�e&�q�x%��Ïx��TaT�e^�Y�#�h�jA�7}��:;z����2�om����4����v��j�8&!�ݖZ�������W��`>rA�x��O{���?���ߝ/�D�a�sݨ�'�Y�g&xX3�|j�z�����LZC��(�_65Ŗ���Q�)Q���r�J1��U_"��yf��ځ�)\����G(y�'��]'�}�}K�z����l��-��
��r���,���܇�S�/XYmMQ�	ĦeҜΞ�QRY? ���( ��|�_��t`5�?dw�� �Ÿ���;%c!mN����n���4Z�-v�s����a�r5���\Μ���Y��� �Ս��z���ڒ����� ��y�n�9�2g������ҁyJ��&?^2"_0��!��\���q]��)�U��bm�b��g��(�a�� ��G��6�3���G��;v�nIm��8�N�2&��*A�TTq��e�^3��ԼJw��tq&6��P���*N��>D������RA�(ςLQ�b߂l~��%�����>�d��a�1��EYLK�巣.�^�q֯h��X(pnj!�!�!弅;.��c�	�\��sA����J�#4����R����'-��������C2F=^k��%c�Jw�Ō�b��ꁾ`���IZP"�K��|l�j�No��F9�~g��2�Q�Y|	M��?"���d��-o�ES�ۘ�q��ҫ1�����������(�b�u�7Z��JM�����2p�^�	� GH@'"h$������d�b؅�ꎪ��	�S������ˎ=�a�:޽BߊTp�E�j��Ӱ8Qj�~S�S8�(Q)����V�I|������/�,h��?�E�a��V��u��r�)f`;��[�ز���]뫣.���Twes�1R�y��q�Őp�ͣ�I���D	�0�f)������7S=���XM��N'n?���<5�1���u��c�6#�p_!SE���9lh跙N�z�^���8$�K���I�����Q�����Ϛ�D��V��d+O������LEhUJ}'��.���p�;���h�v4�|΁�7���y����6E��6���I�J�@���{��q6����@1���vi�1�cZ�L�'���~[O����/tz�NxX�ǬT� Z�_��ڍȧ���!Y�t(���@	�
+͹;��(nX])(.�A! �������f9|FsM���b�5E��4��.�	����L14�+ʨL����.	�
H���*K�J.z�,,��/,$,2�-�8)'�2<�/+*� �7��,07�-17*1��04$4��/$�8��+�3��e��e�2��&�Hd1z\\\ 6?�V      �      x������ � �      �   M  x���˒�0���.z7՚�����4(���l�K+W�O?ڎ]3���J��ς�;��D�����u|�/�}��e3�; n�����f��=7"u���� � �M�浶,�<P��Ӝp�����~�+} 9@s4|�� <Ȑ�H�*OAk�ݔ�v&[�Ύ���~dE��<N)���Q��x,�J��O���%l��j���]CAc/���>����<¾�?���M�;�G���9G��forYQ���nM|r$q�`�m�VE$�dd����`�W��� � �L��PWn�"�WC�-��,����x�uK�*y^}�I�m ��I
'��:5�4�{�h�
�ܗ�"���O�=�,�P99��\�[iFz5Ї�Ut����9�-�Eٕ2��w�F�c�Hs�� �L����S��b-l�f���T?��9Q#A5�wk_T����=�Z�M>N򜜬K>d�(������C�o���wT!ک��p5�[Q�=���a�(��SgzB�m,��f�����}D^�͑�3�{�š�U�:N�$'�k���<���ˌ�^��e �C�t켊��;�d���~�M��g���__0������>)���z�䁶?��Aa��n��k�m�W�X�0��$<y�����=�̥
W�ٶ��vG��C�3�>�%%"|>xJfc��M�V�x�p@�Cd^t�]���_�����Du������8;�۰���zS��J��)V͸���mP� F2�Ie�+��Y��XG���r�}9�r=�F�f2ǐJ���IIG�⼒��@��I��4�<�x�D���>ټ�0�����k���~._�=     