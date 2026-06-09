-- =====================================================
-- TALEBDZ COMPLETE DATABASE SCHEMA
-- =====================================================
-- Application: TalebDZ Student Assistant
-- Version: 1.1.7
-- Date: 2026-06-02
-- Database: PostgreSQL (Supabase)
-- =====================================================
-- This file contains the complete database schema for the TalebDZ application
-- including all tables, indexes, constraints, and Row Level Security (RLS) policies
-- =====================================================

-- Enable required extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- =====================================================
-- SECTION 1: USER & AUTHENTICATION TABLES
-- =====================================================

-- =====================================================
-- TABLE: users
-- Description: Core user profiles and authentication data
-- Note: This extends Supabase auth.users table
-- =====================================================
CREATE TABLE IF NOT EXISTS public.users (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    email TEXT UNIQUE NOT NULL,
    username TEXT UNIQUE NOT NULL,
    full_name TEXT,
    student_id TEXT,
    department TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for users table
CREATE INDEX IF NOT EXISTS idx_users_email ON public.users(email);
CREATE INDEX IF NOT EXISTS idx_users_username ON public.users(username);
CREATE INDEX IF NOT EXISTS idx_users_student_id ON public.users(student_id);

-- RLS Policies for users table
ALTER TABLE public.users ENABLE ROW LEVEL SECURITY;

-- Users can view their own profile
CREATE POLICY "Users can view own profile" ON public.users
    FOR SELECT USING (auth.uid() = id);

-- Users can update their own profile
CREATE POLICY "Users can update own profile" ON public.users
    FOR UPDATE USING (auth.uid() = id);

-- Service role can do anything (admin operations)
CREATE POLICY "Service role full access to users" ON public.users
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- TABLE: profiles
-- Description: Extended user academic information
-- =====================================================
CREATE TABLE IF NOT EXISTS public.profiles (
    id UUID PRIMARY KEY REFERENCES auth.users(id) ON DELETE CASCADE,
    full_name TEXT NOT NULL,
    faculty TEXT NOT NULL,
    department TEXT NOT NULL,
    speciality TEXT NOT NULL,
    level TEXT NOT NULL,
    study_system TEXT NOT NULL,
    university TEXT,
    avatar_url TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for profiles table
CREATE INDEX IF NOT EXISTS idx_profiles_university ON public.profiles(university);
CREATE INDEX IF NOT EXISTS idx_profiles_faculty ON public.profiles(faculty);
CREATE INDEX IF NOT EXISTS idx_profiles_department ON public.profiles(department);
CREATE INDEX IF NOT EXISTS idx_profiles_level ON public.profiles(level);

-- RLS Policies for profiles table
ALTER TABLE public.profiles ENABLE ROW LEVEL SECURITY;

-- Users can view their own profile
CREATE POLICY "Users can view own profile" ON public.profiles
    FOR SELECT USING (auth.uid() = id);

-- Users can view other profiles (for community features)
CREATE POLICY "Users can view other profiles" ON public.profiles
    FOR SELECT USING (true);

-- Users can insert their own profile
CREATE POLICY "Users can insert own profile" ON public.profiles
    FOR INSERT WITH CHECK (auth.uid() = id);

-- Users can update their own profile
CREATE POLICY "Users can update own profile" ON public.profiles
    FOR UPDATE USING (auth.uid() = id);

-- =====================================================
-- SECTION 2: CHAT & MESSAGING TABLES
-- =====================================================

-- =====================================================
-- TABLE: conversations
-- Description: Chat conversation sessions
-- =====================================================
CREATE TABLE IF NOT EXISTS public.conversations (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT,
    last_message TEXT,
    message_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for conversations table
CREATE INDEX IF NOT EXISTS idx_conversations_user_id ON public.conversations(user_id);
CREATE INDEX IF NOT EXISTS idx_conversations_updated_at ON public.conversations(updated_at DESC);

-- RLS Policies for conversations table
ALTER TABLE public.conversations ENABLE ROW LEVEL SECURITY;

-- Users can view their own conversations
CREATE POLICY "Users can view own conversations" ON public.conversations
    FOR SELECT USING (auth.uid() = user_id);

-- Users can insert their own conversations
CREATE POLICY "Users can insert own conversations" ON public.conversations
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Users can update their own conversations
CREATE POLICY "Users can update own conversations" ON public.conversations
    FOR UPDATE USING (auth.uid() = user_id);

-- Users can delete their own conversations
CREATE POLICY "Users can delete own conversations" ON public.conversations
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: messages
-- Description: Individual chat messages
-- =====================================================
CREATE TABLE IF NOT EXISTS public.messages (
    id SERIAL PRIMARY KEY,
    conversation_id UUID NOT NULL REFERENCES public.conversations(id) ON DELETE CASCADE,
    sender_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    role TEXT NOT NULL CHECK (role IN ('user', 'assistant', 'system')),
    content TEXT NOT NULL,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for messages table
CREATE INDEX IF NOT EXISTS idx_messages_conversation_id ON public.messages(conversation_id);
CREATE INDEX IF NOT EXISTS idx_messages_created_at ON public.messages(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_messages_role ON public.messages(role);

-- RLS Policies for messages table
ALTER TABLE public.messages ENABLE ROW LEVEL SECURITY;

-- Users can view messages from their own conversations
CREATE POLICY "Users can view own messages" ON public.messages
    FOR SELECT USING (
        conversation_id IN (
            SELECT id FROM public.conversations WHERE user_id = auth.uid()
        )
    );

-- Users can insert messages to their own conversations
CREATE POLICY "Users can insert own messages" ON public.messages
    FOR INSERT WITH CHECK (
        conversation_id IN (
            SELECT id FROM public.conversations WHERE user_id = auth.uid()
        )
    );

-- =====================================================
-- TABLE: chat_sessions
-- Description: Backend chat session tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS public.chat_sessions (
    session_key TEXT PRIMARY KEY,
    user_id TEXT,
    first_message_at TIMESTAMPTZ,
    last_message_at TIMESTAMPTZ,
    message_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for chat_sessions table
CREATE INDEX IF NOT EXISTS idx_chat_sessions_user_id ON public.chat_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_chat_sessions_updated_at ON public.chat_sessions(updated_at DESC);

-- =====================================================
-- TABLE: chat_messages
-- Description: Backend chat message storage
-- =====================================================
CREATE TABLE IF NOT EXISTS public.chat_messages (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    session_key TEXT NOT NULL,
    role TEXT NOT NULL CHECK (role IN ('user', 'assistant')),
    content TEXT NOT NULL,
    intent TEXT,
    mode TEXT,
    sources_used TEXT[],
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for chat_messages table
CREATE INDEX IF NOT EXISTS idx_chat_messages_session_key ON public.chat_messages(session_key);
CREATE INDEX IF NOT EXISTS idx_chat_messages_created_at ON public.chat_messages(created_at DESC);

-- =====================================================
-- SECTION 3: SCHEDULE MANAGEMENT TABLES
-- =====================================================

-- =====================================================
-- TABLE: schedule_items
-- Description: User schedule entries
-- =====================================================
CREATE TABLE IF NOT EXISTS public.schedule_items (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    subject_name TEXT NOT NULL,
    teacher_name TEXT,
    room TEXT,
    day_of_week TEXT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    duration_minutes INTEGER DEFAULT 90,
    type TEXT NOT NULL CHECK (type IN ('lecture', 'TD', 'TP', 'exam')),
    notes TEXT,
    reminder_enabled BOOLEAN DEFAULT FALSE,
    reminder_offset INTEGER DEFAULT 0,
    notification_id INTEGER,
    university TEXT,
    faculty TEXT,
    department TEXT,
    year_level TEXT,
    group_name TEXT,
    semester TEXT,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for schedule_items table
CREATE INDEX IF NOT EXISTS idx_schedule_items_user_id ON public.schedule_items(user_id);
CREATE INDEX IF NOT EXISTS idx_schedule_items_day_of_week ON public.schedule_items(day_of_week);
CREATE INDEX IF NOT EXISTS idx_schedule_items_start_time ON public.schedule_items(start_time);

-- RLS Policies for schedule_items table
ALTER TABLE public.schedule_items ENABLE ROW LEVEL SECURITY;

-- Users can view their own schedule items
CREATE POLICY "Users can view own schedule" ON public.schedule_items
    FOR SELECT USING (auth.uid() = user_id);

-- Users can insert their own schedule items
CREATE POLICY "Users can insert own schedule" ON public.schedule_items
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Users can update their own schedule items
CREATE POLICY "Users can update own schedule" ON public.schedule_items
    FOR UPDATE USING (auth.uid() = user_id);

-- Users can delete their own schedule items
CREATE POLICY "Users can delete own schedule" ON public.schedule_items
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: schedule_reminders
-- Description: Schedule reminders
-- =====================================================
CREATE TABLE IF NOT EXISTS public.schedule_reminders (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    description TEXT,
    reminder_time TIMESTAMPTZ NOT NULL,
    is_completed BOOLEAN DEFAULT FALSE,
    notification_id INTEGER,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for schedule_reminders table
CREATE INDEX IF NOT EXISTS idx_schedule_reminders_user_id ON public.schedule_reminders(user_id);
CREATE INDEX IF NOT EXISTS idx_schedule_reminders_time ON public.schedule_reminders(reminder_time);
CREATE INDEX IF NOT EXISTS idx_schedule_reminders_completed ON public.schedule_reminders(is_completed);

-- RLS Policies for schedule_reminders table
ALTER TABLE public.schedule_reminders ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view own reminders" ON public.schedule_reminders
    FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can insert own reminders" ON public.schedule_reminders
    FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own reminders" ON public.schedule_reminders
    FOR UPDATE USING (auth.uid() = user_id);

CREATE POLICY "Users can delete own reminders" ON public.schedule_reminders
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: schedule_notes
-- Description: Schedule-related notes
-- =====================================================
CREATE TABLE IF NOT EXISTS public.schedule_notes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    title TEXT NOT NULL,
    content TEXT NOT NULL,
    color TEXT DEFAULT '#FFE066',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for schedule_notes table
CREATE INDEX IF NOT EXISTS idx_schedule_notes_user_id ON public.schedule_notes(user_id);
CREATE INDEX IF NOT EXISTS idx_schedule_notes_created_at ON public.schedule_notes(created_at DESC);

-- RLS Policies for schedule_notes table
ALTER TABLE public.schedule_notes ENABLE ROW LEVEL SECURITY;

CREATE POLICY "Users can view own notes" ON public.schedule_notes
    FOR SELECT USING (auth.uid() = user_id);

CREATE POLICY "Users can insert own notes" ON public.schedule_notes
    FOR INSERT WITH CHECK (auth.uid() = user_id);

CREATE POLICY "Users can update own notes" ON public.schedule_notes
    FOR UPDATE USING (auth.uid() = user_id);

CREATE POLICY "Users can delete own notes" ON public.schedule_notes
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- SECTION 4: EVENTS MANAGEMENT TABLE
-- =====================================================

-- =====================================================
-- TABLE: events
-- Description: University events and announcements
-- =====================================================
CREATE TABLE IF NOT EXISTS public.events (
    id SERIAL PRIMARY KEY,
    title TEXT NOT NULL,
    description TEXT,
    location TEXT,
    event_date TIMESTAMPTZ NOT NULL,
    organizer TEXT,
    source TEXT,
    image_url TEXT,
    category TEXT,
    priority INTEGER DEFAULT 0 CHECK (priority IN (0, 1, 2)),
    priority_expires_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for events table
CREATE INDEX IF NOT EXISTS idx_events_date ON public.events(event_date);
CREATE INDEX IF NOT EXISTS idx_events_category ON public.events(category);
CREATE INDEX IF NOT EXISTS idx_events_priority ON public.events(priority DESC);
CREATE INDEX IF NOT EXISTS idx_events_created_at ON public.events(created_at DESC);

-- RLS Policies for events table
ALTER TABLE public.events ENABLE ROW LEVEL SECURITY;

-- Anyone can view events (public)
CREATE POLICY "Anyone can view events" ON public.events
    FOR SELECT USING (true);

-- Only admins can insert events
CREATE POLICY "Admins can insert events" ON public.events
    FOR INSERT WITH CHECK (auth.role() = 'service_role');

-- Only admins can update events
CREATE POLICY "Admins can update events" ON public.events
    FOR UPDATE USING (auth.role() = 'service_role');

-- Only admins can delete events
CREATE POLICY "Admins can delete events" ON public.events
    FOR DELETE USING (auth.role() = 'service_role');

-- =====================================================
-- SECTION 5: COMMUNITY FEATURES TABLES
-- =====================================================

-- =====================================================
-- TABLE: community_posts
-- Description: Community forum posts
-- =====================================================
CREATE TABLE IF NOT EXISTS public.community_posts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    community_id TEXT NOT NULL,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    post_type TEXT DEFAULT 'discussion' CHECK (post_type IN ('question', 'announcement', 'discussion', 'help')),
    image_url TEXT,
    likes_count INTEGER DEFAULT 0,
    comments_count INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for community_posts table
CREATE INDEX IF NOT EXISTS idx_community_posts_community_id ON public.community_posts(community_id);
CREATE INDEX IF NOT EXISTS idx_community_posts_user_id ON public.community_posts(user_id);
CREATE INDEX IF NOT EXISTS idx_community_posts_created_at ON public.community_posts(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_community_posts_post_type ON public.community_posts(post_type);

-- RLS Policies for community_posts table
ALTER TABLE public.community_posts ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can view posts
CREATE POLICY "Authenticated users can view posts" ON public.community_posts
    FOR SELECT USING (auth.role() = 'authenticated');

-- Users can insert their own posts
CREATE POLICY "Users can insert own posts" ON public.community_posts
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Users can update their own posts
CREATE POLICY "Users can update own posts" ON public.community_posts
    FOR UPDATE USING (auth.uid() = user_id);

-- Users can delete their own posts
CREATE POLICY "Users can delete own posts" ON public.community_posts
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: community_likes
-- Description: Post likes tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS public.community_likes (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id UUID NOT NULL REFERENCES public.community_posts(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    UNIQUE(post_id, user_id)
);

-- Indexes for community_likes table
CREATE INDEX IF NOT EXISTS idx_community_likes_post_id ON public.community_likes(post_id);
CREATE INDEX IF NOT EXISTS idx_community_likes_user_id ON public.community_likes(user_id);

-- RLS Policies for community_likes table
ALTER TABLE public.community_likes ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can view likes
CREATE POLICY "Authenticated users can view likes" ON public.community_likes
    FOR SELECT USING (auth.role() = 'authenticated');

-- Users can insert their own likes
CREATE POLICY "Users can insert own likes" ON public.community_likes
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Users can delete their own likes
CREATE POLICY "Users can delete own likes" ON public.community_likes
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: community_comments
-- Description: Comments on posts
-- =====================================================
CREATE TABLE IF NOT EXISTS public.community_comments (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id UUID NOT NULL REFERENCES public.community_posts(id) ON DELETE CASCADE,
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    content TEXT NOT NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for community_comments table
CREATE INDEX IF NOT EXISTS idx_community_comments_post_id ON public.community_comments(post_id);
CREATE INDEX IF NOT EXISTS idx_community_comments_user_id ON public.community_comments(user_id);
CREATE INDEX IF NOT EXISTS idx_community_comments_created_at ON public.community_comments(created_at DESC);

-- RLS Policies for community_comments table
ALTER TABLE public.community_comments ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can view comments
CREATE POLICY "Authenticated users can view comments" ON public.community_comments
    FOR SELECT USING (auth.role() = 'authenticated');

-- Users can insert their own comments
CREATE POLICY "Users can insert own comments" ON public.community_comments
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Users can update their own comments
CREATE POLICY "Users can update own comments" ON public.community_comments
    FOR UPDATE USING (auth.uid() = user_id);

-- Users can delete their own comments
CREATE POLICY "Users can delete own comments" ON public.community_comments
    FOR DELETE USING (auth.uid() = user_id);

-- =====================================================
-- TABLE: post_reports
-- Description: Content moderation reports
-- =====================================================
CREATE TABLE IF NOT EXISTS public.post_reports (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    post_id UUID NOT NULL REFERENCES public.community_posts(id) ON DELETE CASCADE,
    reporter_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    reason TEXT NOT NULL,
    description TEXT,
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'reviewed', 'resolved', 'dismissed')),
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for post_reports table
CREATE INDEX IF NOT EXISTS idx_post_reports_post_id ON public.post_reports(post_id);
CREATE INDEX IF NOT EXISTS idx_post_reports_reporter_id ON public.post_reports(reporter_id);
CREATE INDEX IF NOT EXISTS idx_post_reports_status ON public.post_reports(status);

-- RLS Policies for post_reports table
ALTER TABLE public.post_reports ENABLE ROW LEVEL SECURITY;

-- Users can view their own reports
CREATE POLICY "Users can view own reports" ON public.post_reports
    FOR SELECT USING (auth.uid() = reporter_id);

-- Admins can view all reports
CREATE POLICY "Admins can view all reports" ON public.post_reports
    FOR SELECT USING (auth.role() = 'service_role');

-- Users can insert their own reports
CREATE POLICY "Users can insert own reports" ON public.post_reports
    FOR INSERT WITH CHECK (auth.uid() = reporter_id);

-- Admins can update reports
CREATE POLICY "Admins can update reports" ON public.post_reports
    FOR UPDATE USING (auth.role() = 'service_role');

-- =====================================================
-- SECTION 6: VIDEO & ADVERTISING TABLES
-- =====================================================

-- =====================================================
-- TABLE: videos
-- Description: Educational video metadata
-- =====================================================
CREATE TABLE IF NOT EXISTS public.videos (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title TEXT NOT NULL,
    description TEXT,
    google_drive_url TEXT NOT NULL,
    thumbnail_url TEXT,
    duration INTEGER,
    category TEXT,
    tags TEXT[],
    views_count INTEGER DEFAULT 0,
    created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for videos table
CREATE INDEX IF NOT EXISTS idx_videos_category ON public.videos(category);
CREATE INDEX IF NOT EXISTS idx_videos_created_by ON public.videos(created_by);
CREATE INDEX IF NOT EXISTS idx_videos_is_active ON public.videos(is_active);
CREATE INDEX IF NOT EXISTS idx_videos_created_at ON public.videos(created_at DESC);

-- RLS Policies for videos table
ALTER TABLE public.videos ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can view active videos
CREATE POLICY "Authenticated users can view active videos" ON public.videos
    FOR SELECT USING (auth.role() = 'authenticated' AND is_active = TRUE);

-- Video creators can insert their own videos
CREATE POLICY "Users can insert own videos" ON public.videos
    FOR INSERT WITH CHECK (auth.uid() = created_by);

-- Video creators can update their own videos
CREATE POLICY "Users can update own videos" ON public.videos
    FOR UPDATE USING (auth.uid() = created_by);

-- Video creators can delete their own videos
CREATE POLICY "Users can delete own videos" ON public.videos
    FOR DELETE USING (auth.uid() = created_by);

-- Admins can do anything
CREATE POLICY "Admins full access to videos" ON public.videos
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- TABLE: video_views
-- Description: Video view tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS public.video_views (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    video_id UUID NOT NULL REFERENCES public.videos(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    viewed_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for video_views table
CREATE INDEX IF NOT EXISTS idx_video_views_video_id ON public.video_views(video_id);
CREATE INDEX IF NOT EXISTS idx_video_views_user_id ON public.video_views(user_id);
CREATE INDEX IF NOT EXISTS idx_video_views_viewed_at ON public.video_views(viewed_at DESC);

-- RLS Policies for video_views table
ALTER TABLE public.video_views ENABLE ROW LEVEL SECURITY;

-- Users can view their own view history
CREATE POLICY "Users can view own view history" ON public.video_views
    FOR SELECT USING (auth.uid() = user_id);

-- Anyone authenticated can insert view records
CREATE POLICY "Authenticated users can insert views" ON public.video_views
    FOR INSERT WITH CHECK (auth.role() = 'authenticated');

-- =====================================================
-- TABLE: ads
-- Description: Advertisement content for announcements panel
-- =====================================================
CREATE TABLE IF NOT EXISTS public.ads (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    title TEXT NOT NULL,
    description TEXT,
    drive_url TEXT NOT NULL,
    start_date TIMESTAMPTZ NOT NULL,
    end_date TIMESTAMPTZ NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    views_count INTEGER DEFAULT 0,
    created_by UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for ads table
CREATE INDEX IF NOT EXISTS idx_ads_is_active ON public.ads(is_active);
CREATE INDEX IF NOT EXISTS idx_ads_ad_type ON public.ads(ad_type);
CREATE INDEX IF NOT EXISTS idx_ads_dates ON public.ads(start_date, end_date);

-- RLS Policies for ads table
ALTER TABLE public.ads ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can view active ads
CREATE POLICY "Authenticated users can view active ads" ON public.ads
    FOR SELECT USING (auth.role() = 'authenticated' AND is_active = TRUE);

-- Admins can manage ads
CREATE POLICY "Admins full access to ads" ON public.ads
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- TABLE: ad_impressions
-- Description: Ad impression & click tracking
-- =====================================================
CREATE TABLE IF NOT EXISTS public.ad_impressions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    ad_id UUID NOT NULL REFERENCES public.ads(id) ON DELETE CASCADE,
    user_id UUID REFERENCES auth.users(id) ON DELETE SET NULL,
    impression_type TEXT DEFAULT 'view' CHECK (impression_type IN ('view', 'click')),
    created_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for ad_impressions table
CREATE INDEX IF NOT EXISTS idx_ad_impressions_ad_id ON public.ad_impressions(ad_id);
CREATE INDEX IF NOT EXISTS idx_ad_impressions_user_id ON public.ad_impressions(user_id);
CREATE INDEX IF NOT EXISTS idx_ad_impressions_type ON public.ad_impressions(impression_type);
CREATE INDEX IF NOT EXISTS idx_ad_impressions_created_at ON public.ad_impressions(created_at DESC);

-- RLS Policies for ad_impressions table
ALTER TABLE public.ad_impressions ENABLE ROW LEVEL SECURITY;

-- Anyone authenticated can insert impressions
CREATE POLICY "Authenticated users can insert impressions" ON public.ad_impressions
    FOR INSERT WITH CHECK (auth.role() = 'authenticated');

-- Admins can view all impressions
CREATE POLICY "Admins can view all impressions" ON public.ad_impressions
    FOR SELECT USING (auth.role() = 'service_role');

-- =====================================================
-- SECTION 7: SUBSCRIPTION & PAYMENT TABLES
-- =====================================================

-- =====================================================
-- TABLE: subscription_plans
-- Description: Available subscription tiers
-- =====================================================
CREATE TABLE IF NOT EXISTS public.subscription_plans (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    plan_code TEXT UNIQUE NOT NULL,
    name TEXT NOT NULL,
    description TEXT,
    price NUMERIC(10, 2) NOT NULL,
    currency TEXT DEFAULT 'DZD',
    duration_months INTEGER NOT NULL,
    features JSONB DEFAULT '[]',
    is_active BOOLEAN DEFAULT TRUE,
    is_popular BOOLEAN DEFAULT FALSE,
    display_order INTEGER DEFAULT 0,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for subscription_plans table
CREATE INDEX IF NOT EXISTS idx_subscription_plans_plan_code ON public.subscription_plans(plan_code);
CREATE INDEX IF NOT EXISTS idx_subscription_plans_is_active ON public.subscription_plans(is_active);
CREATE INDEX IF NOT EXISTS idx_subscription_plans_display_order ON public.subscription_plans(display_order);

-- RLS Policies for subscription_plans table
ALTER TABLE public.subscription_plans ENABLE ROW LEVEL SECURITY;

-- Anyone can view active plans
CREATE POLICY "Anyone can view active plans" ON public.subscription_plans
    FOR SELECT USING (is_active = TRUE);

-- Admins can manage plans
CREATE POLICY "Admins full access to plans" ON public.subscription_plans
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- TABLE: user_subscriptions
-- Description: User subscription records
-- =====================================================
CREATE TABLE IF NOT EXISTS public.user_subscriptions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    plan_id UUID NOT NULL REFERENCES public.subscription_plans(id) ON DELETE RESTRICT,
    payment_reference TEXT,
    amount_paid NUMERIC(10, 2) NOT NULL,
    currency TEXT DEFAULT 'DZD',
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'active', 'expired', 'cancelled', 'failed')),
    starts_at TIMESTAMPTZ,
    expires_at TIMESTAMPTZ,
    cancelled_at TIMESTAMPTZ,
    cancellation_reason TEXT,
    auto_renew BOOLEAN DEFAULT FALSE,
    metadata JSONB DEFAULT '{}',
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for user_subscriptions table
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_user_id ON public.user_subscriptions(user_id);
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_plan_id ON public.user_subscriptions(plan_id);
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_status ON public.user_subscriptions(status);
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_expires_at ON public.user_subscriptions(expires_at);
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_payment_ref ON public.user_subscriptions(payment_reference);

-- RLS Policies for user_subscriptions table
ALTER TABLE public.user_subscriptions ENABLE ROW LEVEL SECURITY;

-- Users can view their own subscriptions
CREATE POLICY "Users can view own subscriptions" ON public.user_subscriptions
    FOR SELECT USING (auth.uid() = user_id);

-- Users can insert their own subscriptions
CREATE POLICY "Users can insert own subscriptions" ON public.user_subscriptions
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Admins can manage all subscriptions
CREATE POLICY "Admins full access to subscriptions" ON public.user_subscriptions
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- TABLE: payment_transactions
-- Description: Payment transaction history
-- =====================================================
CREATE TABLE IF NOT EXISTS public.payment_transactions (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    user_id UUID NOT NULL REFERENCES auth.users(id) ON DELETE CASCADE,
    subscription_id UUID REFERENCES public.user_subscriptions(id) ON DELETE SET NULL,
    chargily_checkout_id TEXT,
    payment_reference TEXT UNIQUE NOT NULL,
    amount NUMERIC(10, 2) NOT NULL,
    currency TEXT DEFAULT 'DZD',
    status TEXT DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'failed', 'cancelled', 'refunded')),
    payment_method TEXT,
    chargily_response JSONB,
    webhook_data JSONB,
    error_message TEXT,
    completed_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Indexes for payment_transactions table
CREATE INDEX IF NOT EXISTS idx_payment_transactions_user_id ON public.payment_transactions(user_id);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_subscription_id ON public.payment_transactions(subscription_id);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_reference ON public.payment_transactions(payment_reference);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_status ON public.payment_transactions(status);
CREATE INDEX IF NOT EXISTS idx_payment_transactions_created_at ON public.payment_transactions(created_at DESC);

-- RLS Policies for payment_transactions table
ALTER TABLE public.payment_transactions ENABLE ROW LEVEL SECURITY;

-- Users can view their own transactions
CREATE POLICY "Users can view own transactions" ON public.payment_transactions
    FOR SELECT USING (auth.uid() = user_id);

-- Users can insert their own transactions
CREATE POLICY "Users can insert own transactions" ON public.payment_transactions
    FOR INSERT WITH CHECK (auth.uid() = user_id);

-- Admins can manage all transactions
CREATE POLICY "Admins full access to transactions" ON public.payment_transactions
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- SECTION 8: TRIGGERS & FUNCTIONS
-- =====================================================

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = NOW();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply updated_at trigger to all tables
CREATE TRIGGER update_users_updated_at BEFORE UPDATE ON public.users
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_profiles_updated_at BEFORE UPDATE ON public.profiles
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_conversations_updated_at BEFORE UPDATE ON public.conversations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_schedule_items_updated_at BEFORE UPDATE ON public.schedule_items
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_schedule_reminders_updated_at BEFORE UPDATE ON public.schedule_reminders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_schedule_notes_updated_at BEFORE UPDATE ON public.schedule_notes
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_events_updated_at BEFORE UPDATE ON public.events
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_community_posts_updated_at BEFORE UPDATE ON public.community_posts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_community_comments_updated_at BEFORE UPDATE ON public.community_comments
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_post_reports_updated_at BEFORE UPDATE ON public.post_reports
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_videos_updated_at BEFORE UPDATE ON public.videos
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_ads_updated_at BEFORE UPDATE ON public.ads
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_subscription_plans_updated_at BEFORE UPDATE ON public.subscription_plans
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_user_subscriptions_updated_at BEFORE UPDATE ON public.user_subscriptions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_payment_transactions_updated_at BEFORE UPDATE ON public.payment_transactions
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Function to automatically create user profile on signup
CREATE OR REPLACE FUNCTION create_user_profile()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO public.users (id, email, username)
    VALUES (
        NEW.id,
        NEW.email,
        COALESCE(NEW.raw_user_meta_data->>'username', split_part(NEW.email, '@', 1))
    );
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Trigger to create user profile on signup
CREATE TRIGGER on_auth_user_created
    AFTER INSERT ON auth.users
    FOR EACH ROW EXECUTE FUNCTION create_user_profile();

-- Function to update community post counts
CREATE OR REPLACE FUNCTION update_post_counts()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        IF TG_TABLE_NAME = 'community_comments' THEN
            UPDATE public.community_posts 
            SET comments_count = comments_count + 1 
            WHERE id = NEW.post_id;
        ELSIF TG_TABLE_NAME = 'community_likes' THEN
            UPDATE public.community_posts 
            SET likes_count = likes_count + 1 
            WHERE id = NEW.post_id;
        END IF;
    ELSIF TG_OP = 'DELETE' THEN
        IF TG_TABLE_NAME = 'community_comments' THEN
            UPDATE public.community_posts 
            SET comments_count = GREATEST(comments_count - 1, 0)
            WHERE id = OLD.post_id;
        ELSIF TG_TABLE_NAME = 'community_likes' THEN
            UPDATE public.community_posts 
            SET likes_count = GREATEST(likes_count - 1, 0)
            WHERE id = OLD.post_id;
        END IF;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Triggers for post count updates
CREATE TRIGGER update_comments_count_insert
    AFTER INSERT ON public.community_comments
    FOR EACH ROW EXECUTE FUNCTION update_post_counts();

CREATE TRIGGER update_comments_count_delete
    AFTER DELETE ON public.community_comments
    FOR EACH ROW EXECUTE FUNCTION update_post_counts();

CREATE TRIGGER update_likes_count_insert
    AFTER INSERT ON public.community_likes
    FOR EACH ROW EXECUTE FUNCTION update_post_counts();

CREATE TRIGGER update_likes_count_delete
    AFTER DELETE ON public.community_likes
    FOR EACH ROW EXECUTE FUNCTION update_post_counts();

-- Function to update video view count
CREATE OR REPLACE FUNCTION update_video_views_count()
RETURNS TRIGGER AS $$
BEGIN
    UPDATE public.videos 
    SET views_count = views_count + 1 
    WHERE id = NEW.video_id;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

-- Trigger for video view count
CREATE TRIGGER update_video_views_count_trigger
    AFTER INSERT ON public.video_views
    FOR EACH ROW EXECUTE FUNCTION update_video_views_count();

-- =====================================================
-- SECTION 9: SEED DATA - DEFAULT SUBSCRIPTION PLANS
-- =====================================================

-- Insert default subscription plans
INSERT INTO public.subscription_plans (plan_code, name, description, price, duration_months, features, is_active, is_popular, display_order)
VALUES
    ('monthly', 'Monthly Premium', 'Full access to all features for 1 month', 170.00, 1, 
     '["Community Access", "Events Calendar", "Schedule Management", "Study Groups", "Resource Library", "Priority Support"]'::jsonb,
     TRUE, FALSE, 1),
    
    ('quarterly', '3 Months Premium', 'Full access to all features for 3 months - Best Value!', 365.00, 3,
     '["Community Access", "Events Calendar", "Schedule Management", "Study Groups", "Resource Library", "Priority Support", "Save 28% compared to monthly"]'::jsonb,
     TRUE, TRUE, 2),
    
    ('semi_annual', '6 Months Premium', 'Full access to all features for 6 months - Great Savings!', 675.00, 6,
     '["Community Access", "Events Calendar", "Schedule Management", "Study Groups", "Resource Library", "Priority Support", "Save 34% compared to monthly"]'::jsonb,
     TRUE, FALSE, 3),
    
    ('annual', '12 Months Premium', 'Full access to all features for 1 year - Maximum Savings!', 1255.00, 12,
     '["Community Access", "Events Calendar", "Schedule Management", "Study Groups", "Resource Library", "Priority Support", "Save 38% compared to monthly"]'::jsonb,
     TRUE, FALSE, 4)
ON CONFLICT (plan_code) DO UPDATE SET
    name = EXCLUDED.name,
    description = EXCLUDED.description,
    price = EXCLUDED.price,
    duration_months = EXCLUDED.duration_months,
    features = EXCLUDED.features,
    is_popular = EXCLUDED.is_popular,
    display_order = EXCLUDED.display_order;

-- =====================================================
-- SECTION 10: UTILITY FUNCTIONS
-- =====================================================

-- Function to check if user has active subscription
CREATE OR REPLACE FUNCTION user_has_active_subscription(user_uuid UUID)
RETURNS BOOLEAN AS $$
DECLARE
    has_subscription BOOLEAN;
BEGIN
    SELECT EXISTS (
        SELECT 1 
        FROM public.user_subscriptions 
        WHERE user_id = user_uuid 
        AND status = 'active'
        AND expires_at > NOW()
    ) INTO has_subscription;
    
    RETURN has_subscription;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to get user's active subscription
CREATE OR REPLACE FUNCTION get_active_subscription(user_uuid UUID)
RETURNS TABLE (
    subscription_id UUID,
    plan_name TEXT,
    expires_at TIMESTAMPTZ,
    days_remaining INTEGER
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        us.id,
        sp.name,
        us.expires_at,
        EXTRACT(DAY FROM (us.expires_at - NOW()))::INTEGER
    FROM public.user_subscriptions us
    JOIN public.subscription_plans sp ON us.plan_id = sp.id
    WHERE us.user_id = user_uuid
    AND us.status = 'active'
    AND us.expires_at > NOW()
    ORDER BY us.expires_at DESC
    LIMIT 1;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- Function to expire old subscriptions (can be run as a cron job)
CREATE OR REPLACE FUNCTION expire_old_subscriptions()
RETURNS INTEGER AS $$
DECLARE
    expired_count INTEGER;
BEGIN
    UPDATE public.user_subscriptions
    SET status = 'expired'
    WHERE status = 'active'
    AND expires_at < NOW();
    
    GET DIAGNOSTICS expired_count = ROW_COUNT;
    RETURN expired_count;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- =====================================================
-- SECTION 11: PERFORMANCE OPTIMIZATIONS
-- =====================================================

-- Create composite indexes for common queries
CREATE INDEX IF NOT EXISTS idx_user_subscriptions_user_status_expires 
    ON public.user_subscriptions(user_id, status, expires_at DESC);

CREATE INDEX IF NOT EXISTS idx_community_posts_community_created 
    ON public.community_posts(community_id, created_at DESC);

CREATE INDEX IF NOT EXISTS idx_messages_conversation_created 
    ON public.messages(conversation_id, created_at ASC);

CREATE INDEX IF NOT EXISTS idx_schedule_items_user_day 
    ON public.schedule_items(user_id, day_of_week, start_time);

-- Create GIN indexes for array and JSONB columns
CREATE INDEX IF NOT EXISTS idx_videos_tags_gin 
    ON public.videos USING GIN (tags);

CREATE INDEX IF NOT EXISTS idx_subscription_plans_features_gin 
    ON public.subscription_plans USING GIN (features);

CREATE INDEX IF NOT EXISTS idx_user_subscriptions_metadata_gin 
    ON public.user_subscriptions USING GIN (metadata);

-- =====================================================
-- SECTION 12: DATABASE VIEWS
-- =====================================================

-- View for active users with subscription status
CREATE OR REPLACE VIEW user_subscription_status AS
SELECT 
    u.id,
    u.email,
    u.username,
    u.full_name,
    CASE 
        WHEN EXISTS (
            SELECT 1 
            FROM public.user_subscriptions us 
            WHERE us.user_id = u.id 
            AND us.status = 'active' 
            AND us.expires_at > NOW()
        ) THEN TRUE 
        ELSE FALSE 
    END AS has_active_subscription,
    (
        SELECT us.expires_at 
        FROM public.user_subscriptions us 
        WHERE us.user_id = u.id 
        AND us.status = 'active' 
        AND us.expires_at > NOW()
        ORDER BY us.expires_at DESC 
        LIMIT 1
    ) AS subscription_expires_at
FROM public.users u
WHERE u.is_active = TRUE;

-- View for community posts with user details
CREATE OR REPLACE VIEW community_posts_detailed AS
SELECT 
    cp.*,
    p.full_name AS user_full_name,
    p.university,
    p.faculty,
    p.department,
    p.level,
    p.speciality,
    p.avatar_url AS user_avatar_url
FROM public.community_posts cp
LEFT JOIN public.profiles p ON cp.user_id = p.id;

-- View for video statistics
CREATE OR REPLACE VIEW video_statistics AS
SELECT 
    v.id,
    v.title,
    v.category,
    v.views_count,
    COUNT(DISTINCT vv.user_id) AS unique_viewers,
    v.created_at,
    v.updated_at
FROM public.videos v
LEFT JOIN public.video_views vv ON v.id = vv.video_id
WHERE v.is_active = TRUE
GROUP BY v.id, v.title, v.category, v.views_count, v.created_at, v.updated_at;

-- =====================================================
-- SECTION 13: COMMENTS & DOCUMENTATION
-- =====================================================

-- Add table comments for documentation
COMMENT ON TABLE public.users IS 'Core user profiles extending Supabase auth.users';
COMMENT ON TABLE public.profiles IS 'Extended user academic information including university details';
COMMENT ON TABLE public.conversations IS 'Chat conversation sessions for AI assistant';
COMMENT ON TABLE public.messages IS 'Individual chat messages within conversations';
COMMENT ON TABLE public.schedule_items IS 'User schedule entries for classes and events';
COMMENT ON TABLE public.schedule_reminders IS 'Custom reminders for schedule items';
COMMENT ON TABLE public.schedule_notes IS 'User notes related to schedule and studies';
COMMENT ON TABLE public.events IS 'University events and announcements';
COMMENT ON TABLE public.community_posts IS 'Community forum posts created by users';
COMMENT ON TABLE public.community_likes IS 'Likes on community posts';
COMMENT ON TABLE public.community_comments IS 'Comments on community posts';
COMMENT ON TABLE public.post_reports IS 'User reports for inappropriate content';
COMMENT ON TABLE public.videos IS 'Educational video content metadata';
COMMENT ON TABLE public.video_views IS 'Video view tracking for analytics';
COMMENT ON TABLE public.ads IS 'Advertisement content for the platform';
COMMENT ON TABLE public.ad_impressions IS 'Ad view and click tracking';
COMMENT ON TABLE public.subscription_plans IS 'Available premium subscription plans';
COMMENT ON TABLE public.user_subscriptions IS 'User subscription records and status';
COMMENT ON TABLE public.payment_transactions IS 'Payment transaction history with Chargily';

-- =====================================================
-- SECTION 14: GRANT PERMISSIONS
-- =====================================================

-- Grant appropriate permissions to authenticated users
GRANT USAGE ON SCHEMA public TO authenticated;
GRANT USAGE ON SCHEMA public TO anon;

-- Grant select on all tables to authenticated users (controlled by RLS)
GRANT SELECT ON ALL TABLES IN SCHEMA public TO authenticated;
GRANT INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO authenticated;

-- Grant usage on sequences
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO authenticated;

-- Grant execute on functions
GRANT EXECUTE ON ALL FUNCTIONS IN SCHEMA public TO authenticated;

-- Service role has full access (no RLS restrictions)
GRANT ALL ON ALL TABLES IN SCHEMA public TO service_role;
GRANT ALL ON ALL SEQUENCES IN SCHEMA public TO service_role;
GRANT ALL ON ALL FUNCTIONS IN SCHEMA public TO service_role;

-- =====================================================
-- END OF SCHEMA
-- =====================================================

-- Success message
DO $$
BEGIN
    RAISE NOTICE '✅ TalebDZ Database Schema Created Successfully!';
    RAISE NOTICE '📊 Total Tables: 20';
    RAISE NOTICE '🔒 Row Level Security (RLS) Enabled on All User Tables';
    RAISE NOTICE '🔧 Triggers and Functions Created';
    RAISE NOTICE '📦 Default Subscription Plans Inserted';
    RAISE NOTICE '';
    RAISE NOTICE 'Next Steps:';
    RAISE NOTICE '1. Verify RLS policies are working correctly';
    RAISE NOTICE '2. Test authentication and user creation';
    RAISE NOTICE '3. Configure Supabase Storage buckets if needed';
    RAISE NOTICE '4. Set up any additional custom functions or triggers';
    RAISE NOTICE '5. Run the subscription plans insertion script';
END $$;


-- =====================================================
-- ADMIN ACCOUNT AND RLS POLICIES FOR TALEBDZ
-- =====================================================
-- This file adds:
-- 1. Admin accounts table for admin panel login
-- 2. Additional RLS policies for admin access
-- 3. Helper functions for admin dashboard
-- =====================================================

-- =====================================================
-- TABLE: admin_accounts
-- Description: Admin user accounts for admin panel access
-- =====================================================
CREATE TABLE IF NOT EXISTS public.admin_accounts (
    id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    email TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    full_name TEXT NOT NULL,
    role TEXT DEFAULT 'admin' CHECK (role IN ('super_admin', 'admin', 'moderator')),
    is_active BOOLEAN DEFAULT TRUE,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ DEFAULT NOW(),
    updated_at TIMESTAMPTZ DEFAULT NOW()
);

-- Index for admin_accounts
CREATE INDEX IF NOT EXISTS idx_admin_accounts_email ON public.admin_accounts(email);
CREATE INDEX IF NOT EXISTS idx_admin_accounts_is_active ON public.admin_accounts(is_active);

-- RLS Policies for admin_accounts
ALTER TABLE public.admin_accounts ENABLE ROW LEVEL SECURITY;

-- Only service role can access admin accounts (for admin panel backend)
CREATE POLICY "Service role full access to admin_accounts" ON public.admin_accounts
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- ADDITIONAL RLS POLICIES FOR ADMIN ACCESS
-- =====================================================

-- Ensure service role can manage all reports
DROP POLICY IF EXISTS "Service role can manage reports" ON public.post_reports;
CREATE POLICY "Service role can manage reports" ON public.post_reports
    FOR ALL USING (auth.role() = 'service_role');

-- Ensure service role can manage community posts
DROP POLICY IF EXISTS "Service role can manage posts" ON public.community_posts;
CREATE POLICY "Service role can manage posts" ON public.community_posts
    FOR ALL USING (auth.role() = 'service_role');

-- Ensure service role can manage ads (already in main schema with "Admins full access to ads")
-- This is redundant but ensures it works
DROP POLICY IF EXISTS "Service role can manage ads" ON public.ads;
CREATE POLICY "Service role can manage ads" ON public.ads
    FOR ALL USING (auth.role() = 'service_role');

-- Ensure service role can manage videos (already in main schema)
DROP POLICY IF EXISTS "Service role can manage videos" ON public.videos;
CREATE POLICY "Service role can manage videos" ON public.videos
    FOR ALL USING (auth.role() = 'service_role');

-- Ensure service role can manage subscription plans (already in main schema)
DROP POLICY IF EXISTS "Service role can manage subscription_plans" ON public.subscription_plans;
CREATE POLICY "Service role can manage subscription_plans" ON public.subscription_plans
    FOR ALL USING (auth.role() = 'service_role');

-- Ensure service role can view all user subscriptions
DROP POLICY IF EXISTS "Service role can view user_subscriptions" ON public.user_subscriptions;
CREATE POLICY "Service role can view user_subscriptions" ON public.user_subscriptions
    FOR ALL USING (auth.role() = 'service_role');

-- =====================================================
-- FUNCTION: Get Dashboard Statistics
-- =====================================================
CREATE OR REPLACE FUNCTION get_dashboard_stats()
RETURNS JSON
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
DECLARE
    stats JSON;
BEGIN
    SELECT json_build_object(
        'total_users', (SELECT COUNT(*) FROM public.users WHERE is_active = TRUE),
        'total_subscriptions', (SELECT COUNT(*) FROM public.user_subscriptions WHERE status = 'active'),
        'pending_reports', (SELECT COUNT(*) FROM public.post_reports WHERE status = 'pending'),
        'total_posts', (SELECT COUNT(*) FROM public.community_posts),
        'total_revenue', (SELECT COALESCE(SUM(amount), 0) FROM public.payment_transactions WHERE status = 'paid')
    ) INTO stats;
    
    RETURN stats;
END;
$$;

-- =====================================================
-- FUNCTION: Get Recent Activity
-- =====================================================
CREATE OR REPLACE FUNCTION get_recent_activity(limit_count INT DEFAULT 10)
RETURNS TABLE (
    activity_type TEXT,
    activity_description TEXT,
    user_email TEXT,
    created_at TIMESTAMPTZ
)
LANGUAGE plpgsql
SECURITY DEFINER
AS $$
BEGIN
    RETURN QUERY
    SELECT 
        'new_user'::TEXT as activity_type,
        'New user registered: ' || u.email as activity_description,
        u.email as user_email,
        u.created_at
    FROM public.users u
    WHERE u.created_at > NOW() - INTERVAL '7 days'
    
    UNION ALL
    
    SELECT 
        'new_subscription'::TEXT,
        'New subscription: ' || sp.name,
        u.email,
        us.created_at
    FROM public.user_subscriptions us
    JOIN public.users u ON us.user_id = u.id
    JOIN public.subscription_plans sp ON us.plan_id = sp.id
    WHERE us.created_at > NOW() - INTERVAL '7 days'
    
    UNION ALL
    
    SELECT 
        'new_report'::TEXT,
        'Post reported: ' || pr.reason,
        u.email,
        pr.created_at
    FROM public.post_reports pr
    JOIN public.users u ON pr.reporter_id = u.id
    WHERE pr.created_at > NOW() - INTERVAL '7 days'
    
    ORDER BY created_at DESC
    LIMIT limit_count;
END;
$$;

-- =====================================================
-- INITIAL DATA: Create default admin account
-- =====================================================
-- Password: admin123 (CHANGE THIS IN PRODUCTION!)
-- Use bcrypt to hash the password in production
INSERT INTO public.admin_accounts (email, password_hash, full_name, role)
VALUES (
    'admin@talebdz.com',
    '$2b$12$LQv3c1yqBWVHxkd0LHAkCOYz6TtxMeshQ7H.IvXRJC3ZKqPM1WQYK', -- admin123
    'System Administrator',
    'super_admin'
)
ON CONFLICT (email) DO NOTHING;

-- =====================================================
-- Grant necessary permissions
-- =====================================================
GRANT USAGE ON SCHEMA public TO anon, authenticated, service_role;
GRANT SELECT ON public.subscription_plans TO anon, authenticated;
GRANT ALL ON public.admin_accounts TO service_role;
GRANT ALL ON public.post_reports TO service_role;
GRANT ALL ON public.community_posts TO service_role;
GRANT ALL ON public.ads TO service_role;
GRANT ALL ON public.videos TO service_role;
GRANT ALL ON public.user_subscriptions TO service_role;
GRANT EXECUTE ON FUNCTION get_dashboard_stats() TO service_role;
GRANT EXECUTE ON FUNCTION get_recent_activity(INT) TO service_role;

-- =====================================================
-- END OF ADMIN CONFIGURATION
-- =====================================================
