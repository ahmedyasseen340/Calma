-- ============================================================================
-- Run this ONCE in the Supabase SQL Editor (and again after adding new tables).
--
-- Supabase publishes every table of the "public" schema through its automatic REST API.
-- With Row Level Security (RLS) OFF, anyone who has the project URL and the public key can read
-- and write those tables without a password. We never use that API: the Laravel backend connects
-- straight to the database as the "postgres" role, which is not limited by RLS.
-- So turning RLS on (with no policies) closes the API and the backend keeps working.
-- ============================================================================
DO $$
DECLARE r record;
BEGIN
  FOR r IN SELECT tablename FROM pg_tables WHERE schemaname = 'public' LOOP
    EXECUTE format('ALTER TABLE public.%I ENABLE ROW LEVEL SECURITY', r.tablename);
  END LOOP;
END $$;

-- Check: every table should now show rowsecurity = true
SELECT tablename, rowsecurity FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename;
