-- ============================================================
-- Create ads table in Supabase
-- Run this in Supabase SQL Editor
-- ============================================================

-- Drop existing table if it has wrong structure
-- DROP TABLE IF EXISTS public.ads CASCADE;

-- Create ads table with correct structure
CREATE TABLE IF NOT EXISTS public.ads (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title TEXT NOT NULL,
    description TEXT,
    google_drive_url TEXT NOT NULL,  -- Changed from drive_url to match videos table
    start_date TIMESTAMPTZ NOT NULL,
    end_date TIMESTAMPTZ NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    views_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Create indexes
CREATE INDEX IF NOT EXISTS idx_ads_is_active ON public.ads(is_active) WHERE is_active = TRUE;
CREATE INDEX IF NOT EXISTS idx_ads_dates ON public.ads(start_date, end_date);
CREATE INDEX IF NOT EXISTS idx_ads_created_at ON public.ads(created_at DESC);

-- Enable RLS
ALTER TABLE public.ads ENABLE ROW LEVEL SECURITY;

-- Drop any existing policies
DROP POLICY IF EXISTS "Authenticated users can view active ads" ON public.ads;
DROP POLICY IF EXISTS "Admins full access to ads" ON public.ads;

-- Create RLS policies
-- Anyone authenticated can view active ads
CREATE POLICY "Authenticated users can view active ads" ON public.ads
    FOR SELECT USING (auth.role() = 'authenticated' AND is_active = TRUE);

-- Admins (service_role) can do everything
CREATE POLICY "Admins full access to ads" ON public.ads
    FOR ALL USING (auth.role() = 'service_role');

-- Grant permissions
GRANT SELECT ON public.ads TO authenticated;
GRANT ALL ON public.ads TO service_role;

-- Verify table was created
SELECT 'Ads table created successfully' AS status;
SELECT column_name, data_type 
FROM information_schema.columns 
WHERE table_schema = 'public' 
AND table_name = 'ads'
ORDER BY ordinal_position;
