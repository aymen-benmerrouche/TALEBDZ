-- ============================================================
-- Fix ads table schema
-- Remove index on non-existent ad_type column
-- ============================================================

-- Drop the incorrect index
DROP INDEX IF EXISTS public.idx_ads_ad_type;

-- Recreate proper indexes
CREATE INDEX IF NOT EXISTS idx_ads_is_active ON public.ads(is_active) WHERE is_active = TRUE;
CREATE INDEX IF NOT EXISTS idx_ads_dates ON public.ads(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_ads_created_at ON public.ads(created_at DESC);

-- Ensure RLS is properly configured
ALTER TABLE public.ads ENABLE ROW LEVEL SECURITY;

-- Drop existing policies to recreate them
DROP POLICY IF EXISTS "Authenticated users can view active ads" ON public.ads;
DROP POLICY IF EXISTS "Admins full access to ads" ON public.ads;

-- Recreate RLS policies
-- Anyone authenticated can view active ads
CREATE POLICY "Authenticated users can view active ads" ON public.ads
    FOR SELECT USING (auth.role() = 'authenticated' AND is_active = TRUE);

-- Admins (service_role) can do everything
CREATE POLICY "Admins full access to ads" ON public.ads
    FOR ALL USING (auth.role() = 'service_role');

-- Grant necessary permissions
GRANT SELECT ON public.ads TO authenticated;
GRANT ALL ON public.ads TO service_role;
