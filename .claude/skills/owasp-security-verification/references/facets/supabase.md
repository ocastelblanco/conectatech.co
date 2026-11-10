# Faceta `supabase`

> CC BY-SA 4.0. Fuentes: Database Security, Authorization.

- **SB-01:** toda tabla de `public` es accesible por la API REST con la llave anon si no tiene RLS. La regla cruza todos los `.sql` del repositorio; si la tabla se creó desde el panel, confírmalo en Supabase (Advisors).
- **SB-02:** `using (true)` en `select` para `anon` solo es válido con datos públicos por diseño.
- La llave `service_role` ignora RLS: solo en backends, Edge Functions o n8n, nunca en el navegador (NG-07).
