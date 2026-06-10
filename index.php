<?php
// ============================================================
// index.php — TalebDZ Landing Page
// Serves: hero, features, about, how-it-works, pricing, CTA, footer
// Server-side: pulls live pricing from DB, upcoming events count,
//              total user count for social proof stats.
// ============================================================

declare(strict_types=1);

// ── Bootstrap ────────────────────────────────────────────────
require_once __DIR__ . '/db/config.php';
require_once __DIR__ . '/db/functions.php';

// Base URL for links/assets — web path only (e.g. /Taleb-DZ), never a filesystem path
$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '/index.php');
$baseUrl    = str_replace('\\', '/', rtrim(dirname($scriptPath), '/\\'));
if ($baseUrl === '' || $baseUrl === '/' || $baseUrl === '.') {
    $baseUrl = '';
}
$adminLoginUrl = $baseUrl . './admin/login.php';

// ── Live stats from database (graceful fallback on DB error) ──
$stats = ['total_users' => '2k+', 'accuracy' => '94%', 'response_time' => '<5s'];
$plans = [];

try {
    $userCount = users_count();
    $stats['total_users'] = $userCount >= 1000
        ? round($userCount / 1000, 1) . 'k+'
        : $userCount . '+';
} catch (Throwable $e) {
    error_log('[TalebDZ index.php] Stats error: ' . $e->getMessage());
}

// Plans use Supabase REST API first — must not depend on direct DB stats query
try {
    $plans = plans_list();
} catch (Throwable $e) {
    error_log('[TalebDZ index.php] Plans error: ' . $e->getMessage());
    $plans = [];
}

// ── Normalize plans data structure ───────────────────────────
// Handle both database array and fallback scenarios
if (!empty($plans)) {
    // Remove _http_status if present (from REST API response)
    if (isset($plans['_http_status'])) {
        unset($plans['_http_status']);
    }
    
    // Normalize features field - ensure it's always an array
    $plans = array_values(array_filter($plans, function($plan) {
        return is_array($plan) && isset($plan['plan_code']);
    }));
    
    foreach ($plans as &$plan) {
        // Ensure features is an array
        if (isset($plan['features'])) {
            if (is_string($plan['features'])) {
                $decoded = json_decode($plan['features'], true);
                $plan['features'] = is_array($decoded) ? $decoded : [];
            } elseif (!is_array($plan['features'])) {
                $plan['features'] = [];
            }
        } else {
            $plan['features'] = [];
        }
        
        // Ensure required fields exist with defaults
        $plan['price'] = (float)($plan['price'] ?? 0);
        $plan['duration_months'] = (int)($plan['duration_months'] ?? 1);
        $plan['is_popular'] = filter_var($plan['is_popular'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $plan['currency'] = $plan['currency'] ?? 'DZD';
        $plan['name'] = $plan['name'] ?? 'Unknown Plan';
        $plan['description'] = $plan['description'] ?? '';
    }
    unset($plan); // Break reference
}

// ── Default plans if DB returned nothing ─────────────────────
if (empty($plans)) {
    $plans = [
        [
            'plan_code'       => 'free',
            'name'            => 'Explorer',
            'description'     => 'For prospective students',
            'price'           => 0,
            'duration_months' => 0,
            'is_popular'      => false,
            'features'        => ['20 AI questions / month', 'Admissions & campus info', 'Public community read'],
        ],
        [
            'plan_code'       => 'monthly',
            'name'            => 'Student Pro',
            'description'     => 'For enrolled students',
            'price'           => 150,
            'duration_months' => 1,
            'is_popular'      => true,
            'features'        => ['Unlimited AI questions', 'Full community access', 'Smart reminders & calendar', 'Priority support'],
        ],
        [
            'plan_code'       => 'institution',
            'name'            => 'Institution',
            'description'     => 'For universities',
            'price'           => -1, // -1 = custom/contact
            'duration_months' => 12,
            'is_popular'      => false,
            'features'        => ['Unlimited students', 'Custom RAG knowledge base', 'Admin dashboard access'],
        ],
    ];
}

// ── Helper: format price ──────────────────────────────────────
function formatPrice(array $plan, string $lang = 'en'): string {
    $price = (float)($plan['price'] ?? 0);
    
    if ($price == 0) {
        return match($lang) {
            'ar' => 'مجاني',
            'fr' => 'Gratuit',
            default => 'Free'
        };
    }
    if ($price < 0) {
        return match($lang) {
            'ar' => 'مخصص',
            'fr' => 'Sur devis',
            default => 'Custom'
        };
    }
    $currency = $plan['currency'] ?? 'DZD';
    return number_format($price, 0) . ' ' . $currency;
}

// ── Helper: format duration ───────────────────────────────────
function formatDuration(array $plan, string $lang = 'en'): string {
    $price  = (float)($plan['price'] ?? 0);
    $months = (int)($plan['duration_months'] ?? 1);

    if ($price == 0) {
        return match($lang) {
            'ar' => 'للأبد',
            'fr' => 'pour toujours',
            default => 'forever',
        };
    }
    if ($price < 0) {
        return match($lang) {
            'ar' => 'تواصل معنا',
            'fr' => 'contactez-nous',
            default => 'contact us',
        };
    }

    return match($months) {
        1 => match($lang) { 'ar' => '/شهر', 'fr' => '/mois', default => '/month' },
        3 => match($lang) { 'ar' => '/3 أشهر', 'fr' => '/3 mois', default => '/3 months' },
        6 => match($lang) { 'ar' => '/6 أشهر', 'fr' => '/6 mois', default => '/6 months' },
        12 => match($lang) { 'ar' => '/سنة', 'fr' => '/an', default => '/year' },
        default => match($lang) {
            'ar' => '/' . $months . ' أشهر',
            'fr' => '/' . $months . ' mois',
            default => '/' . $months . ' months',
        },
    };
}

// ── SEO / OG meta ─────────────────────────────────────────────
$pageTitle       = 'TalebDZ — Your Campus AI Assistant';
$pageDescription = 'Instant answers to any university question, a student community, smart reminders, and more — powered by RAG and LLM. Built for Algerian students.';
$pageUrl         = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'talebdz.com') . '/';
$ogImage         = $pageUrl . 'photos/og-cover.jpg';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>"/>
  <meta name="keywords" content="TalebDZ, university assistant, AI chatbot Algeria, student app, RAG, campus AI"/>
  <meta name="robots" content="index, follow"/>

  <!-- Open Graph -->
  <meta property="og:type"        content="website"/>
  <meta property="og:title"       content="<?= htmlspecialchars($pageTitle) ?>"/>
  <meta property="og:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
  <meta property="og:url"         content="<?= htmlspecialchars($pageUrl) ?>"/>
  <meta property="og:image"       content="<?= htmlspecialchars($ogImage) ?>"/>

  <!-- Twitter Card -->
  <meta name="twitter:card"        content="summary_large_image"/>
  <meta name="twitter:title"       content="<?= htmlspecialchars($pageTitle) ?>"/>
  <meta name="twitter:description" content="<?= htmlspecialchars($pageDescription) ?>"/>
  <meta name="twitter:image"       content="<?= htmlspecialchars($ogImage) ?>"/>

  <title><?= htmlspecialchars($pageTitle) ?></title>

  <!-- Preconnect -->
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>

  <!-- Fonts: Syne (display) + DM Sans (body) -->
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet" media="print" onload="this.media='all'"/>
  <noscript>
    <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap" rel="stylesheet"/>
  </noscript>

  <!-- Icons -->
  <link rel="stylesheet"
        href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.11.0/dist/tabler-icons.min.css"
        crossorigin/>

  <!-- Styles -->
  <link rel="stylesheet" href="<?= htmlspecialchars($baseUrl) ?>/style.css"/>

  <!-- Favicon -->
  <link rel="icon" type="image/jpeg" href="<?= htmlspecialchars($baseUrl) ?>/photos/logo.jpg"/>
</head>
<body>

<!-- ═══════════════════════════════════════════════════════════
     NAVIGATION
════════════════════════════════════════════════════════════ -->
<nav class="nav" id="main-nav" role="navigation" aria-label="Main navigation">

  <!-- Logo -->
  <a class="nav-logo" href="<?= htmlspecialchars($baseUrl) ?>/index.php" aria-label="TalebDZ Home">
    <img src="<?= htmlspecialchars($baseUrl) ?>/photos/logo.jpg" alt="TalebDZ" class="nav-logo-img" width="36" height="36"/>
    <span>TalebDZ</span>
  </a>

  <!-- Desktop links -->
  <div class="nav-center" role="menubar">
    <a role="menuitem" onclick="goTo('features')"  data-t="nav_features">Features</a>
    <a role="menuitem" onclick="goTo('about')"     data-t="nav_about">About</a>
    <a role="menuitem" onclick="goTo('how')"       data-t="nav_how">How it works</a>
    <a role="menuitem" onclick="goTo('pricing')"   data-t="nav_pricing">Pricing</a>
    <a role="menuitem" onclick="goTo('contact')"   data-t="nav_contact">Contact</a>
  </div>

  <!-- Right controls -->
  <div class="nav-right">

    <!-- Language picker -->
    <div class="lang-wrap">
      <button class="lang-btn" onclick="toggleLang(event)" aria-label="Select language" aria-haspopup="true">
        <i class="ti ti-world" aria-hidden="true"></i>
        <span class="lang-label">🌐 EN</span>
        <i class="ti ti-chevron-down lang-caret" aria-hidden="true"></i>
      </button>
      <div class="lang-menu" id="lang-menu" role="menu">
        <div class="lang-opt active" data-lang="en" onclick="pickLang('en')" role="menuitem">
          <span class="lang-flag">🇬🇧</span> English
        </div>
        <div class="lang-opt" data-lang="ar" onclick="pickLang('ar')" role="menuitem">
          <span class="lang-flag">🇸🇦</span> العربية
        </div>
        <div class="lang-opt" data-lang="fr" onclick="pickLang('fr')" role="menuitem">
          <span class="lang-flag">🇫🇷</span> Français
        </div>
      </div>
    </div>

    <!-- Theme toggle -->
    <button class="theme-btn" onclick="toggleTheme()" aria-label="Toggle dark/light theme">
      <i class="ti ti-sun" id="theme-icon" aria-hidden="true"></i>
    </button>

    <!-- Admin login (ghost) -->
    <a class="btn btn-sm btn-ghost" href="<?= htmlspecialchars($adminLoginUrl) ?>" data-t="nav_admin">Admin Login</a>

    <!-- CTA -->
    <button class="btn btn-sm btn-grad" onclick="goTo('pricing')" data-t="nav_start">
      Get Started
    </button>

    <!-- Hamburger -->
    <button class="ham" onclick="toggleMob()" aria-label="Open menu" aria-expanded="false" id="ham-btn">
      <i class="ti ti-menu-2" aria-hidden="true"></i>
    </button>
  </div>
</nav>

<!-- Mobile nav -->
<nav class="mob-nav" id="mob-nav" aria-label="Mobile navigation">
  <a onclick="goTo('features'); closeMob()" data-t="nav_features">Features</a>
  <a onclick="goTo('about');    closeMob()" data-t="nav_about">About</a>
  <a onclick="goTo('how');      closeMob()" data-t="nav_how">How it works</a>
  <a onclick="goTo('pricing');  closeMob()" data-t="nav_pricing">Pricing</a>
  <a onclick="goTo('contact');  closeMob()" data-t="nav_contact">Contact</a>
  <a href="<?= htmlspecialchars($adminLoginUrl) ?>" data-t="nav_admin">Admin Login</a>
</nav>


<!-- ═══════════════════════════════════════════════════════════
     HERO
════════════════════════════════════════════════════════════ -->
<div class="pt-nav">
<section class="hero" id="home" aria-labelledby="hero-heading">

  <!-- Pill badge -->
  <div class="hero-pill" role="note">
    <i class="ti ti-sparkles" aria-hidden="true"></i>
    <span data-t="hero_pill">AI-Powered University Assistant</span>
  </div>

  <!-- Headline -->
  <h1 id="hero-heading">
    <span data-t="hero_h1a">Your Campus,</span><br>
    <em data-t="hero_h1b">Smarter.</em>
  </h1>

  <!-- Subheading -->
  <p class="hero-sub" data-t="hero_p">
    Instant answers to any university question, a student community, smart reminders,
    and more — powered by RAG and LLM. Built for students, trusted by institutions.
  </p>

  <!-- CTA buttons -->
  <div class="hero-btns">
    <button class="btn btn-xl btn-grad" onclick="goTo('pricing')" data-t="hero_dl">
      Download the App <i class="ti ti-arrow-right" aria-hidden="true"></i>
    </button>
    <button class="btn btn-xl btn-ghost" onclick="goTo('features')" data-t="hero_demo">
      Learn More
    </button>
  </div>

  <!-- Stats — user count is live from DB -->
  <div class="hero-stats" role="list" aria-label="Platform statistics">
    <div class="stat" role="listitem">
      <div class="stat-n" id="stat-users"><?= htmlspecialchars($stats['total_users']) ?></div>
      <div class="stat-l" data-t="stat_students">Students</div>
    </div>
    <div class="stat" role="listitem">
      <div class="stat-n">94%</div>
      <div class="stat-l" data-t="stat_accuracy">Accuracy</div>
    </div>
    <div class="stat" role="listitem">
      <div class="stat-n">&lt;5s</div>
      <div class="stat-l" data-t="stat_speed">Response Time</div>
    </div>
  </div>

</section><!-- /hero -->


<!-- ═══════════════════════════════════════════════════════════
     FEATURE ROW — AI CHAT
════════════════════════════════════════════════════════════ -->
<section class="section feat-section" style="background:var(--bg3)" aria-label="AI Chat feature">
  <div class="feat-row wrap">

    <div class="feat-text">
      <span class="tag" aria-label="Feature category">
        <i class="ti ti-message-chatbot" aria-hidden="true"></i>
        <span data-t="tag_chat">AI Chat</span>
      </span>
      <h2 data-t="h2_chat">Answers in seconds, not office hours</h2>
      <p data-t="p_chat">
        Students ask anything — enrollment deadlines, scholarships, campus life — and get
        precise, cited answers pulled directly from your university's official documents via RAG.
      </p>
      <ul>
        <li data-t="li_chat_2">Separate mode for prospective vs. enrolled students</li>
        <li data-t="li_chat_3">Available 24 / 7 with no waiting</li>
      </ul>
    </div>

    <div class="feat-visual">
      <div class="phone-frame tall">
        <div class="phone-slot">
          <img src="<?= htmlspecialchars($baseUrl) ?>/photos/chat screen2.jpg" alt="TalebDZ AI chat — showing a student asking about enrollment deadlines" loading="lazy"/>
        </div>
      </div>
      <div class="phone-frame">
        <div class="phone-slot">
          <img src="<?= htmlspecialchars($baseUrl) ?>/photos/chat screen.jpg" alt="TalebDZ AI chat — showing cited answer from university documents" loading="lazy"/>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     FEATURE ROW — COMMUNITY
════════════════════════════════════════════════════════════ -->
<section class="section feat-section" style="background:var(--bg2)" aria-label="Community feature">
  <div class="feat-row flip wrap">

    <div class="feat-text">
      <span class="tag">
        <i class="ti ti-users" aria-hidden="true"></i>
        <span data-t="tag_community">Community</span>
      </span>
      <h2 data-t="h2_community">Students helping students</h2>
      <p data-t="p_community">
        A built-in forum where students post questions, share notes, like and comment.
        Every post is moderated from the admin dashboard.
      </p>
      <ul>
        <li data-t="li_com_1">Post questions and get peer answers</li>
        <li data-t="li_com_2">Like, comment, and save posts</li>
        <li data-t="li_com_3">Admin moderates all content centrally</li>
      </ul>
    </div>

    <div class="feat-visual">
      <div class="phone-frame tall">
        <div class="phone-slot">
          <img src="<?= htmlspecialchars($baseUrl) ?>/photos/community.jpg" alt="TalebDZ community forum showing student posts" loading="lazy"/>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     FEATURE ROW — REMINDERS
════════════════════════════════════════════════════════════ -->
<section class="section feat-section" style="background:var(--bg3)" aria-label="Reminders feature">
  <div class="feat-row wrap">

    <div class="feat-text">
      <span class="tag">
        <i class="ti ti-calendar-event" aria-hidden="true"></i>
        <span data-t="tag_reminders">Reminders</span>
      </span>
      <h2 data-t="h2_reminders">Never miss a deadline again</h2>
      <p data-t="p_reminders">
        Students set personal reminders for assignments, exams, and events.
      </p>
      <ul>
        <li data-t="li_rem_1">Personal reminders synced to the calendar</li>
        <li data-t="li_rem_2">Color-coded by category and urgency</li>
        <li data-t="li_rem_3">Admin-pushed campus-wide alerts</li>
      </ul>
    </div>

    <div class="feat-visual">
      <div class="phone-frame tall">
        <div class="phone-slot">
          <img src="<?= htmlspecialchars($baseUrl) ?>/photos/schedule.jpg" alt="TalebDZ reminders — color-coded deadline calendar" loading="lazy"/>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     FEATURE ROW — EVENTS
════════════════════════════════════════════════════════════ -->
<section class="section feat-section" style="background:var(--bg2)" aria-label="Events feature">
  <div class="feat-row flip wrap">

    <div class="feat-text">
      <span class="tag">
        <i class="ti ti-calendar-event" aria-hidden="true"></i>
        <span data-t="tag_events">Events</span>
      </span>
      <h2 data-t="h2_events">Events tailored to your field</h2>
      <p data-t="p_events">
        Each student sees upcoming events matched to their speciality — workshops, seminars,
        career fairs, and campus activities relevant to what they study.
      </p>
      <ul>
        <li data-t="li_ev_1">Personalised feed based on your major &amp; interests</li>
        <li data-t="li_ev_2">Soonest events shown first, never miss a deadline</li>
      </ul>
    </div>

    <div class="feat-visual">
      <div class="phone-frame tall">
        <div class="phone-slot">
          <img src="<?= htmlspecialchars($baseUrl) ?>/photos/events.jpg" alt="TalebDZ events feed showing personalised upcoming events" loading="lazy"/>
        </div>
      </div>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     FEATURES GRID  (6 cards)
════════════════════════════════════════════════════════════ -->
<section class="section" id="features" style="background:var(--bg)" aria-labelledby="features-heading">
  <div class="wrap">

    <span class="tag">
      <i class="ti ti-layout-grid" aria-hidden="true"></i>
      <span data-t="tag_all">Everything you need</span>
    </span>
    <h2 class="sec-h" id="features-heading" data-t="sec_feat_h">
      Built for students.<br>Trusted by institutions.
    </h2>
    <p class="sec-p" data-t="sec_feat_p">
      Every feature is designed to reduce friction and increase student success — prospective or enrolled.
    </p>

    <div class="feat-grid" role="list">

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-brain"></i></div>
        <h3 data-t="f1_h">RAG-Powered Answers</h3>
        <p  data-t="f1_p">Pulls from your university's official documents to give precise, cited answers — never hallucinations.</p>
      </div>

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-users"></i></div>
        <h3 data-t="f2_h">Student Community</h3>
        <p  data-t="f2_p">Built-in forum for posts, likes, and comments — fully moderated through your admin panel.</p>
      </div>

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-bell"></i></div>
        <h3 data-t="f3_h">Smart Reminders</h3>
        <p  data-t="f3_p">Personal and campus-wide reminders synced to each student's schedule.</p>
      </div>

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-robot"></i></div>
        <h3 data-t="f4_h">Dual Student Modes</h3>
        <p  data-t="f4_p">Tailored information for applicants and enrolled students at every stage.</p>
      </div>

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-shield-check"></i></div>
        <h3 data-t="f5_h">Admin Control Center</h3>
        <p  data-t="f5_p">Full visibility into users, posts, FAQs, and analytics. Update your knowledge base in real-time.</p>
      </div>

      <div class="feat-card reveal" role="listitem">
        <div class="feat-ico" aria-hidden="true"><i class="ti ti-credit-card"></i></div>
        <h3 data-t="f6_h">Flexible Billing</h3>
        <p  data-t="f6_p">Free tier for prospects, monthly plans for enrolled students. Managed from one dashboard.</p>
      </div>

    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     ABOUT
════════════════════════════════════════════════════════════ -->
<section class="section" id="about" style="background:var(--bg3)" aria-labelledby="about-heading">
  <div class="feat-row wrap">

    <div class="feat-text">
      <span class="tag">
        <i class="ti ti-info-circle" aria-hidden="true"></i>
        <span data-t="tag_about">About</span>
      </span>
      <h2 id="about-heading" data-t="h2_about">About TalebDZ</h2>
      <p data-t="p_about">
        TalebDZ is an AI-powered student assistant designed to help university students manage
        their academic life. From intelligent chat assistance to event management and schedule
        planning, TalebDZ is your companion for academic success.
      </p>
    </div>

    <div class="feat-visual" style="flex-direction:column; gap:1.25rem; align-items:center;">
      <img
        src="<?= htmlspecialchars($baseUrl) ?>/photos/logo.jpg"
        alt="TalebDZ Logo"
        class="about-logo"
        width="160" height="160"
        loading="lazy"
      />
      <span class="about-brand-name" aria-hidden="true">TalebDZ</span>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     HOW IT WORKS
════════════════════════════════════════════════════════════ -->
<section class="section" id="how" style="background:var(--bg)" aria-labelledby="how-heading">
  <div class="wrap">

    <span class="tag">
      <i class="ti ti-route" aria-hidden="true"></i>
      <span data-t="tag_how">How it works</span>
    </span>
    <h2 class="sec-h" id="how-heading" data-t="sec_how_h">From question to answer in seconds</h2>

    <div class="steps-grid" role="list">

      <article class="step-card reveal" role="listitem">
        <div class="step-num" aria-hidden="true">01</div>
        <h3 data-t="step1_h">Student asks</h3>
        <p  data-t="step1_p">Typed naturally in the chat — no special commands needed.</p>
      </article>

      <article class="step-card reveal" role="listitem">
        <div class="step-num" aria-hidden="true">02</div>
        <h3 data-t="step2_h">RAG retrieves</h3>
        <p  data-t="step2_p">The system searches your vectorized FAQ database for the most relevant context.</p>
      </article>

      <article class="step-card reveal" role="listitem">
        <div class="step-num" aria-hidden="true">03</div>
        <h3 data-t="step3_h">LLM generates</h3>
        <p  data-t="step3_p">The retrieved context is sent to the LLM via API to produce a grounded, cited answer.</p>
      </article>

      <article class="step-card reveal" role="listitem">
        <div class="step-num" aria-hidden="true">04</div>
        <h3 data-t="step4_h">Save in database</h3>
        <p  data-t="step4_p">The system saves the conversation in the database for continuity.</p>
      </article>

    </div>
  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     PRICING  — live from DB
════════════════════════════════════════════════════════════ -->
<section class="section" id="pricing" style="background:var(--bg3)" aria-labelledby="pricing-heading">
  <div class="wrap">

    <span class="tag">
      <i class="ti ti-tag" aria-hidden="true"></i>
      <span data-t="tag_pricing">Pricing</span>
    </span>
    <h2 class="sec-h" id="pricing-heading" data-t="sec_price_h">Simple, fair pricing</h2>
    <p class="sec-p" data-t="sec_price_p">Students shouldn't pay a lot for help. Neither should your institution.</p>

    <div class="price-grid<?= count($plans) > 3 ? ' price-grid-wide' : '' ?>" role="list">
    <?php foreach ($plans as $i => $plan):
      $price       = (float)($plan['price'] ?? 0);
      $isFree      = ($price == 0);
      $isCustom    = ($price < 0);
      $isPopular   = !empty($plan['is_popular']);
      $features    = $plan['features'] ?? [];
      $priceLabel  = formatPrice($plan, 'en');
      $perLabel    = formatDuration($plan, 'en');
    ?>
      <div class="price-card<?= $isPopular ? ' pop' : '' ?> reveal" role="listitem"
           data-plan="<?= htmlspecialchars($plan['plan_code'] ?? '') ?>">

        <?php if ($isPopular): ?>
          <div class="pop-badge" data-t="pl2_badge">Most Popular</div>
        <?php endif; ?>

        <div class="p-name"><?= htmlspecialchars($plan['name'] ?? 'Plan') ?></div>
        <div class="p-desc"><?= htmlspecialchars($plan['description'] ?? '') ?></div>

        <div class="p-amt<?= ($isPopular && !$isFree && !$isCustom) ? ' grad' : '' ?>"
             data-price-en="<?= htmlspecialchars(formatPrice($plan, 'en')) ?>"
             data-price-ar="<?= htmlspecialchars(formatPrice($plan, 'ar')) ?>"
             data-price-fr="<?= htmlspecialchars(formatPrice($plan, 'fr')) ?>">
          <?= htmlspecialchars($priceLabel) ?>
        </div>
        <div class="p-per"
             data-per-en="<?= htmlspecialchars(formatDuration($plan, 'en')) ?>"
             data-per-ar="<?= htmlspecialchars(formatDuration($plan, 'ar')) ?>"
             data-per-fr="<?= htmlspecialchars(formatDuration($plan, 'fr')) ?>">
          <?= htmlspecialchars($perLabel) ?>
        </div>

        <ul class="p-feats">
          <?php foreach ($features as $feat): ?>
            <li class="p-feat">
              <i class="ti ti-check" aria-hidden="true"></i>
              <span><?= htmlspecialchars((string)$feat) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>

        <?php if ($isFree): ?>
          <button class="btn btn-md btn-ghost" style="width:100%" onclick="goTo('contact')" data-t="pl1_btn">
            Get Started Free
          </button>
        <?php elseif ($isCustom): ?>
          <button class="btn btn-md btn-ghost" style="width:100%" onclick="goTo('contact')" data-t="pl3_btn">
            Contact Sales
          </button>
        <?php else: ?>
          <button class="btn btn-md btn-grad" style="width:100%" onclick="goTo('contact')" data-t="pl2_btn">
            Subscribe Now
          </button>
        <?php endif; ?>

      </div>
    <?php endforeach; ?>
    </div>

  </div>
</section>


<!-- ═══════════════════════════════════════════════════════════
     CTA BAND
════════════════════════════════════════════════════════════ -->
<div class="cta-band section-sm" id="contact" aria-labelledby="cta-heading">
  <div class="wrap">
    <h2 id="cta-heading" data-t="cta_h">Ready to transform your campus?</h2>
    <p data-t="cta_p">Join students already using TalebDZ.</p>
    <div class="cta-btns">
      <button class="btn btn-xl btn-grad" onclick="goTo('pricing')" data-t="cta_dl">
        <i class="ti ti-download" aria-hidden="true"></i> Download Now
      </button>
    </div>
  </div>
</div>


<!-- ═══════════════════════════════════════════════════════════
     FOOTER
════════════════════════════════════════════════════════════ -->
<footer role="contentinfo">
  <div class="footer-inner">

    <!-- Logo + tagline -->
    <div class="footer-brand">
      <div class="nav-logo" style="-webkit-text-fill-color:unset; background:var(--grad); -webkit-background-clip:text; background-clip:text; -webkit-text-fill-color:transparent; font-family:'Syne',sans-serif; font-weight:800;">
        TalebDZ
      </div>
      <div class="footer-copy" data-t="f_copy">© 2026 TalebDZ. All rights reserved.</div>
    </div>

    <!-- Contact -->
    <div class="footer-social">
      <p data-t="f_contact">Contact us</p>
      <div style="display:flex; align-items:center; gap:.5rem;">
        <i class="ti ti-mail" style="font-size:1.1rem; color:var(--text3);" aria-hidden="true"></i>
        <a href="mailto:talebdz2026@gmail.com"
           style="font-size:.85rem; color:var(--text2); transition:color var(--t);"
           aria-label="Email TalebDZ">
          talebdz2026@gmail.com
        </a>
      </div>
    </div>

    <!-- Social links -->
    <div class="footer-social">
      <p data-t="f_follow">Follow us on</p>
      <div class="social-links">
        <a href="https://www.instagram.com/talebdzofficielaccount?igsh=MXhmd2s5MGJnNXkwMg=="
           target="_blank" rel="noopener noreferrer" aria-label="TalebDZ on Instagram">
          <i class="ti ti-brand-instagram" aria-hidden="true"></i>
        </a>
        <a href="https://www.facebook.com/profile.php?id=61590760011442"
           target="_blank" rel="noopener noreferrer" aria-label="TalebDZ on Facebook">
          <i class="ti ti-brand-facebook" aria-hidden="true"></i>
        </a>
        <a href="https://x.com/TalebDZ2f8y"
           target="_blank" rel="noopener noreferrer" aria-label="TalebDZ on X (Twitter)">
          <i class="ti ti-brand-x" aria-hidden="true"></i>
        </a>
        <a href="https://tiktok.com/@talebdz5"
           target="_blank" rel="noopener noreferrer" aria-label="TalebDZ on TikTok">
          <i class="ti ti-brand-tiktok" aria-hidden="true"></i>
        </a>
      </div>
    </div>

  </div>
</footer>
</div><!-- /pt-nav -->

<!-- ── Scripts ──────────────────────────────────────────────── -->
<script src="<?= htmlspecialchars($baseUrl) ?>/app.js" defer></script>

</body>
</html>
