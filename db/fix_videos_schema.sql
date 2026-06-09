-- ============================================================
-- Fix videos table schema and RLS policies
-- ============================================================

-- Ensure proper indexes exist
CREATE INDEX IF NOT EXISTS idx_videos_category ON public.videos(category) WHERE category IS NOT NULL;
CREATE INDEX IF NOT EXISTS idx_videos_is_active ON public.videos(is_active) WHERE is_active = TRUE;
CREATE INDEX IF NOT EXISTS idx_videos_created_at ON public.videos(created_at DESC);

-- Ensure RLS is properly configured
ALTER TABLE public.videos ENABLE ROW LEVEL SECURITY;

-- Drop existing policies to recreate them
DROP POLICY IF EXISTS "Authenticated users can view active videos" ON public.videos;
DROP POLICY IF EXISTS "Users can insert own videos" ON public.videos;
DROP POLICY IF EXISTS "Users can update own videos" ON public.videos;
DROP POLICY IF EXISTS "Users can delete own videos" ON public.videos;
DROP POLICY IF EXISTS "Admins full access to videos" ON public.videos;

-- Recreate RLS policies
-- Anyone authenticated can view active videos
CREATE POLICY "Authenticated users can view active videos" ON public.videos
    FOR SELECT USING (auth.role() = 'authenticated' AND is_active = TRUE);

-- Admins (service_role) can do everything
CREATE POLICY "Admins full access to videos" ON public.videos
    FOR ALL USING (auth.role() = 'service_role');

-- Grant necessary permissions
GRANT SELECT ON public.videos TO authenticated;
GRANT ALL ON public.videos TO service_role;
