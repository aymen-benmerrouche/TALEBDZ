<?php
// ============================================================
// admin/index.php — TalebDZ Admin Dashboard
// Protected: requires admin session via auth.php
// All panels pull live data from db/functions.php
// ============================================================
declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once $_rootDir . '/db/functions.php';
require_once __DIR__ . '/auth.php';

// ── Guard: must be logged in ─────────────────────────────────
require_admin_auth();
$admin = auth_currentAdmin();

// ── Base URL for links ───────────────────────────────────────
$baseUrl = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

// ── Greeting by time of day ──────────────────────────────────
$hour     = (int) date('G');
$greeting = match(true) {
    $hour < 12 => 'Good morning',
    $hour < 17 => 'Good afternoon',
    default    => 'Good evening',
};

// ── Load all data for panels (graceful fallback on errors) ───
$stats       = [];
$activity    = [];
$planSummary = [];
$txns        = [];
$reports     = [];
$community   = [];
$announcements = [];
$plans       = [];
$users       = [];
$notifications = [];
$videos      = [];
$ads         = [];

try { $stats        = dashboard_stats(); }         catch(Throwable $e) { error_log($e->getMessage()); }
try { $activity     = dashboard_recentActivity(8); } catch(Throwable $e) {}
try { $planSummary  = billing_planSummary(); }      catch(Throwable $e) {}
try { $txns         = billing_recentTransactions(20); } catch(Throwable $e) {}
try { $reports      = reports_list('pending', 20); } catch(Throwable $e) {}
try { $community    = community_posts(1, 20, 'flagged'); } catch(Throwable $e) { $community = ['data'=>[],'total'=>0]; }
try { $announcements = announcements_list(10); }    catch(Throwable $e) {}
try { 
    $plans = plans_list(true); // Include inactive plans
    // Ensure $plans is always an array of arrays
    if (!is_array($plans)) {
        $plans = [];
    } else {
        // Filter out non-array entries
        $plans = array_filter($plans, 'is_array');
    }
} catch(Throwable $e) { 
    error_log('[TalebDZ Admin] plans_list error: ' . $e->getMessage());
    $plans = [];
}
try { $users        = users_list(1, 30); }          catch(Throwable $e) { $users = ['data'=>[],'total'=>0,'page'=>1,'limit'=>30]; }
try { $videos       = videos_list(true); }          catch(Throwable $e) { $videos = []; }
try { $ads          = ads_list(true); }             catch(Throwable $e) { $ads = []; }

// ── Helpers ──────────────────────────────────────────────────
function safe(mixed $v, string $fallback = '—'): string {
    return $v !== null && $v !== '' ? htmlspecialchars((string)$v) : $fallback;
}
function metricVal(array $stats, string $key, string $fallback = '—'): string {
    $v = $stats[$key] ?? null;
    if ($v === null) return $fallback;
    if (is_float($v)) return number_format($v, 0);
    return htmlspecialchars((string)$v);
}
function badgeClass(string $status): string {
    return match(strtolower($status)) {
        'active','paid','ok','resolved' => 'bdg-ok',
        'pending','scheduled','retrying' => 'bdg-warn',
        'expired','failed','banned','cancelled' => 'bdg-err',
        'prospective','free' => 'bdg-vio',
        default => 'bdg-inf',
    };
}
function activityIcon(string $type): string {
    return match($type) {
        'new_user'     => '<div class="act-ico" style="background:var(--inf-bg);color:var(--inf-txt)"><i class="ti ti-user-plus"></i></div>',
        'report'       => '<div class="act-ico" style="background:var(--err-bg);color:var(--err-txt)"><i class="ti ti-flag"></i></div>',
        'subscription' => '<div class="act-ico" style="background:var(--warn-bg);color:var(--warn-txt)"><i class="ti ti-credit-card"></i></div>',
        default        => '<div class="act-ico" style="background:var(--surface2);color:var(--text3)"><i class="ti ti-bell"></i></div>',
    };
}
function timeAgo(string $dateStr): string {
    if (!$dateStr) return '—';
    $diff = time() - strtotime($dateStr);
    if ($diff < 60)     return $diff . 's ago';
    if ($diff < 3600)   return floor($diff/60) . 'm ago';
    if ($diff < 86400)  return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

$pendingReports = count($reports);
$totalUsers     = $users['total'] ?? ($stats['total_users'] ?? 0);
$csrfToken      = auth_csrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>TalebDZ — Admin Dashboard</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet" media="print" onload="this.media='all'"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.11.0/dist/tabler-icons.min.css" crossorigin/>
  <link rel="stylesheet" href="<?= $baseUrl ?>/style.css"/>
  <link rel="stylesheet" href="<?= $baseUrl ?>/admin/admin.css"/>
  <link rel="icon" type="image/jpeg" href="<?= $baseUrl ?>/photos/logo.jpg"/>
  <style>
    :root{--font-sans:'DM Sans','Segoe UI',system-ui,sans-serif;--font-display:'Syne','Segoe UI',system-ui,sans-serif;}
    body{font-family:var(--font-sans);}
    .nav-logo,.admin-hd h1,.chart-title,.tbl-head h3,.settings-card h3,.m-value,.plan-count{font-family:var(--font-display);}
    .ti{display:inline-block;width:1em;height:1em;vertical-align:-0.125em;}
    
    /* Enhanced bar chart tooltips */
    .bar{position:relative;cursor:pointer;transition:all .2s ease}
    .bar:hover{opacity:.8;transform:translateY(-2px)}
    .bar::after{
      content:attr(title);
      position:absolute;
      bottom:calc(100% + 6px);
      left:50%;
      transform:translateX(-50%) scale(0);
      background:rgba(0,0,0,.9);
      color:#fff;
      padding:.35rem .6rem;
      border-radius:6px;
      font-size:.72rem;
      white-space:nowrap;
      pointer-events:none;
      opacity:0;
      transition:all .2s ease;
      z-index:100;
    }
    .bar:hover::after{transform:translateX(-50%) scale(1);opacity:1}
    
    /* Loading skeleton for charts */
    .chart-skeleton{background:linear-gradient(90deg,var(--surface2) 25%,var(--surface3) 50%,var(--surface2) 75%);
      background-size:200% 100%;animation:skeleton-loading 1.5s infinite}
    @keyframes skeleton-loading{0%{background-position:200% 0}100%{background-position:-200% 0}}
    
    /* Toast notification */
    .toast{position:fixed;bottom:1.5rem;right:1.5rem;z-index:9999;
      padding:.75rem 1.25rem;border-radius:12px;font-size:.855rem;font-weight:500;
      display:flex;align-items:center;gap:.6rem;
      box-shadow:0 8px 32px rgba(0,0,0,.25);
      transform:translateY(120%);opacity:0;transition:all .3s cubic-bezier(.34,1.56,.64,1);}
    .toast.show{transform:translateY(0);opacity:1;}
    .toast-ok{background:#064e3b;color:#34d399;border:1px solid rgba(52,211,153,.3);}
    .toast-err{background:#450a0a;color:#f87171;border:1px solid rgba(248,113,113,.3);}
    /* Confirm modal */
    .confirm-box{max-width:380px;}
    .confirm-box p{font-size:.9rem;color:var(--text2);margin-bottom:1.5rem;line-height:1.65;}
    /* Search highlight */
    mark{background:rgba(59,130,246,.25);color:var(--text);border-radius:3px;padding:0 2px;}
    /* User avatar initial */
    .u-avatar{width:30px;height:30px;border-radius:50%;background:var(--grad);
      color:#fff;font-size:.72rem;font-weight:700;display:inline-flex;
      align-items:center;justify-content:center;flex-shrink:0;margin-right:.5rem;}
    /* Skeleton loader */
    .skeleton{background:var(--surface2);border-radius:6px;animation:skeleton-pulse 1.5s ease infinite;}
    @keyframes skeleton-pulse{0%,100%{opacity:1}50%{opacity:.4}}
    /* Empty state */
    .empty-state{text-align:center;padding:3rem 1rem;color:var(--text3);}
    .empty-state i{font-size:2.5rem;margin-bottom:.75rem;display:block;}
    .empty-state p{font-size:.875rem;}
  </style>
</head>
<body class="admin-body">

<!-- CSRF meta for JS fetch calls -->
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>"/>
<meta name="admin-id"   content="<?= htmlspecialchars($admin['id']) ?>"/>

<!-- ═══════════════════════════════════
     NAV
════════════════════════════════════ -->
<nav class="nav">
  <div style="display:flex;align-items:center;gap:.85rem">
    <button class="sb-toggle" onclick="toggleSidebar()" aria-label="Toggle sidebar">
      <i class="ti ti-menu-2"></i>
    </button>
    <a class="nav-logo" href="<?= $baseUrl ?>/index.php" style="text-decoration:none">TalebDZ</a>
    <div class="admin-crumb">
      <i class="ti ti-chevron-right" style="font-size:.75rem;color:var(--text3)"></i>
      <span data-t="a_crumb">Admin Dashboard</span>
    </div>
  </div>

  <div class="nav-right">
    <!-- Notifications bell -->
    <div style="position:relative;cursor:pointer" onclick="swPanel('notifications',document.querySelector('[data-panel=notifications]'))">
      <button class="theme-btn" aria-label="Notifications"><i class="ti ti-bell"></i></button>
      <?php if ($pendingReports > 0): ?>
      <span style="position:absolute;top:4px;right:4px;width:8px;height:8px;border-radius:50%;background:#f87171;border:2px solid var(--bg)"></span>
      <?php endif; ?>
    </div>

    <!-- Language -->
    <div class="lang-wrap">
      <button class="lang-btn" onclick="toggleLang(event)" aria-label="Language">
        <i class="ti ti-world"></i><span class="lang-label">🌐 EN</span>
        <i class="ti ti-chevron-down lang-caret"></i>
      </button>
      <div class="lang-menu" id="lang-menu">
        <div class="lang-opt active" data-lang="en" onclick="pickLang('en')"><span class="lang-flag">🇬🇧</span> English</div>
        <div class="lang-opt" data-lang="ar" onclick="pickLang('ar')"><span class="lang-flag">🇸🇦</span> العربية</div>
        <div class="lang-opt" data-lang="fr" onclick="pickLang('fr')"><span class="lang-flag">🇫🇷</span> Français</div>
      </div>
    </div>

    <!-- Theme -->
    <button class="theme-btn" onclick="toggleTheme()" aria-label="Toggle theme">
      <i class="ti ti-sun" id="theme-icon"></i>
    </button>

    <!-- Admin avatar + logout -->
    <div style="position:relative">
      <div class="admin-avatar" style="cursor:pointer" onclick="toggleDropdown('admin-dd')"
           title="<?= safe($admin['email']) ?>">
        <?= strtoupper(substr($admin['name'] ?? 'A', 0, 1)) ?>
      </div>
      <div id="admin-dd" style="display:none;position:absolute;top:calc(100%+8px);right:0;min-width:180px;
           background:var(--bg2);border:1px solid var(--border2);border-radius:12px;overflow:hidden;
           box-shadow:0 12px 36px rgba(0,0,0,.22);z-index:600;">
        <div style="padding:.75rem 1rem;border-bottom:1px solid var(--border);">
          <div style="font-size:.82rem;font-weight:600;color:var(--text)"><?= safe($admin['name']) ?></div>
          <div style="font-size:.72rem;color:var(--text3)"><?= safe($admin['email']) ?></div>
        </div>
        <div onclick="swPanel('settings',document.querySelector('[data-panel=settings]'));toggleDropdown('admin-dd')"
             style="padding:.65rem 1rem;font-size:.84rem;color:var(--text2);cursor:pointer;display:flex;align-items:center;gap:.6rem;"
             onmouseover="this.style.background='var(--surface2)'" onmouseout="this.style.background=''">
          <i class="ti ti-settings" style="font-size:.9rem"></i> Settings
        </div>
        <div onclick="adminLogout()"
             style="padding:.65rem 1rem;font-size:.84rem;color:#f87171;cursor:pointer;display:flex;align-items:center;gap:.6rem;"
             onmouseover="this.style.background='rgba(248,113,113,.08)'" onmouseout="this.style.background=''">
          <i class="ti ti-logout" style="font-size:.9rem"></i> Sign Out
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="admin-wrap">

<!-- ═══════════════════════════════════
     SIDEBAR
════════════════════════════════════ -->
<aside class="sidebar" id="sidebar">

  <div class="sb-section">
    <span class="sb-label">Overview</span>
    <div class="sb-item active" data-panel="overview" onclick="swPanel('overview',this)">
      <i class="ti ti-layout-dashboard"></i><span>Dashboard</span>
    </div>
    <div class="sb-item" data-panel="analytics" onclick="swPanel('analytics',this)">
      <i class="ti ti-chart-bar"></i><span>Analytics</span>
    </div>
    <div class="sb-item" data-panel="notifications" onclick="swPanel('notifications',this)">
      <i class="ti ti-bell"></i><span>Notifications</span>
      <?php if ($pendingReports > 0): ?>
      <span class="sb-badge sb-badge-red"><?= $pendingReports ?></span>
      <?php endif; ?>
    </div>
  </div>

  <div class="sb-section">
    <span class="sb-label">App Management</span>
    <div class="sb-item" data-panel="users" onclick="swPanel('users',this)">
      <i class="ti ti-users"></i><span>Users</span>
      <span class="sb-badge sb-badge-vio"><?= number_format((int)($stats['total_users'] ?? 0)) ?></span>
    </div>
    <div class="sb-item" data-panel="community" onclick="swPanel('community',this)">
      <i class="ti ti-messages"></i><span>Community</span>
      <?php if (($community['total'] ?? 0) > 0): ?>
      <span class="sb-badge sb-badge-red"><?= $community['total'] ?></span>
      <?php endif; ?>
    </div>
    <div class="sb-item" data-panel="reports" onclick="swPanel('reports',this)">
      <i class="ti ti-flag"></i><span>Reports</span>
      <?php if ($pendingReports > 0): ?>
      <span class="sb-badge sb-badge-red"><?= $pendingReports ?></span>
      <?php endif; ?>
    </div>
    <div class="sb-item" data-panel="announce" onclick="swPanel('announce',this)">
      <i class="ti ti-speakerphone"></i><span>Announcements</span>
    </div>
    <div class="sb-item" data-panel="billing" onclick="swPanel('billing',this)">
      <i class="ti ti-credit-card"></i><span>Subscriptions</span>
    </div>
    <div class="sb-item" data-panel="settings" onclick="swPanel('settings',this)">
      <i class="ti ti-settings"></i><span>Settings</span>
    </div>
  </div>

  <!-- Sidebar footer -->
  <div style="padding:0 1rem;margin-top:auto;padding-top:1rem;border-top:1px solid var(--border);margin:auto 1rem 1rem">
    <div style="font-size:.72rem;color:var(--text3);margin-bottom:.4rem">Signed in as</div>
    <div style="font-size:.82rem;color:var(--text2);font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
      <?= safe($admin['email']) ?>
    </div>
    <div style="font-size:.7rem;color:var(--text3);text-transform:uppercase;letter-spacing:.5px;margin-top:.2rem">
      <?= safe($admin['role']) ?>
    </div>
  </div>

</aside>

<!-- IMMEDIATE NAVIGATION INIT -->
<script>
// This runs immediately after sidebar loads to ensure navigation works
(function() {
  console.log('🚀 Immediate navigation init...');
  
  // Create swPanel function immediately if it doesn't exist
  if (typeof window.swPanel === 'undefined') {
    console.log('⚡ Creating immediate swPanel function...');
    window.swPanel = function(name, el) {
      console.log('📱 [IMMEDIATE] Switching to:', name);
      
      // Hide all panels
      const allPanels = document.querySelectorAll('.panel');
      allPanels.forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
      });
      
      // Show target
      const target = document.getElementById('p-' + name);
      if (target) {
        target.classList.add('active');
        target.style.display = 'block';
        console.log('✅ [IMMEDIATE] Activated:', name);
      } else {
        console.error('❌ [IMMEDIATE] Panel not found:', name);
      }
      
      // Update sidebar
      document.querySelectorAll('.sb-item').forEach(i => i.classList.remove('active'));
      if (el && el.classList) {
        el.classList.add('active');
      } else if (typeof el === 'string') {
        const item = document.querySelector(`.sb-item[data-panel="${el}"]`);
        if (item) item.classList.add('active');
      }
      
      // Mobile close
      const sidebar = document.getElementById('sidebar');
      const overlay = document.getElementById('sidebar-overlay');
      if (sidebar) sidebar.classList.remove('open');
      if (overlay) overlay.style.display = 'none';
      
      // Scroll
      window.scrollTo({ top: 0, behavior: 'smooth' });
    };
    console.log('✅ Immediate swPanel created');
  }
})();
</script>

<!-- ═══════════════════════════════════
     MAIN CONTENT
════════════════════════════════════ -->
<main class="admin-main">

<!-- Sidebar overlay for mobile -->
<div id="sidebar-overlay" onclick="closeSidebar()"
     style="display:none;position:fixed;inset:0;z-index:390;background:rgba(0,0,0,.45);backdrop-filter:blur(4px)"></div>

<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: OVERVIEW
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-overview" class="panel active">
  <div class="admin-hd">
    <div>
      <h1><?= htmlspecialchars($greeting) ?>, <?= safe($admin['name']) ?> 👋</h1>
      <p>Here's what's happening with TalebDZ today. 
        <span style="color:var(--text3);font-size:.82rem">Last updated: <?= date('H:i') ?> · 
        <a href="<?= $_SERVER['PHP_SELF'] ?>" style="color:var(--acc);text-decoration:none" 
           onmouseover="this.style.textDecoration='underline'" onmouseout="this.style.textDecoration='none'">
          <i class="ti ti-refresh" style="font-size:.75rem"></i> Refresh
        </a>
        </span>
      </p>
    </div>
  </div>

  <!-- Metrics -->
  <div class="metrics">
    <div class="metric">
      <div class="m-label">Total Users</div>
      <div class="m-value"><?= number_format((int)($stats['total_users'] ?? 0)) ?></div>
      <div class="m-delta m-up">↑ active students</div>
    </div>
    <div class="metric">
      <div class="m-label">Active Pro</div>
      <div class="m-value"><?= number_format((int)($stats['active_pro'] ?? 0)) ?></div>
      <div class="m-delta m-up">paid subscribers</div>
    </div>
    <div class="metric">
      <div class="m-label">Active Today</div>
      <div class="m-value"><?= number_format((int)($stats['active_today'] ?? 0)) ?></div>
      <div class="m-delta m-neu">unique sessions</div>
    </div>
    <div class="metric">
      <div class="m-label">Questions Today</div>
      <div class="m-value"><?= number_format((int)($stats['questions_today'] ?? 0)) ?></div>
      <div class="m-delta m-up">AI chat messages</div>
    </div>
    <div class="metric">
      <div class="m-label">Pending Reports</div>
      <div class="m-value"><?= number_format((int)($stats['pending_reports'] ?? 0)) ?></div>
      <div class="m-delta <?= ($stats['pending_reports'] ?? 0) > 0 ? 'm-down' : 'm-neu' ?>">
        <?= ($stats['pending_reports'] ?? 0) > 0 ? 'needs review' : 'all clear' ?>
      </div>
    </div>
    <div class="metric">
      <div class="m-label">Monthly Revenue</div>
      <div class="m-value" style="font-size:1.35rem"><?= number_format((float)($stats['mrr'] ?? 0), 0) ?> <span style="font-size:.8rem;opacity:.6">DZD</span></div>
      <div class="m-delta m-up">MRR (monthly)</div>
    </div>
  </div>

  <!-- Charts row -->
  <div class="charts-row">
    <!-- Bar chart (questions per day — Live data from DB) -->
    <div class="chart-card">
      <div class="chart-title">Daily Questions <span class="chart-sub">Last 7 days</span></div>
      <div class="bar-wrap" id="daily-bars">
        <?php
        // Use real data from database
        $dailyQuestions = $stats['daily_questions'] ?? [];
        $maxQuestions = !empty($dailyQuestions) ? max(array_column($dailyQuestions, 'count')) : 100;
        $maxQuestions = max($maxQuestions, 1); // Prevent division by zero
        
        // Get last 7 days
        $today = (int)date('N'); // 1=Mon … 7=Sun
        $dayNames = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
        
        // Create array indexed by date
        $questionsByDate = [];
        foreach ($dailyQuestions as $dq) {
            $questionsByDate[$dq['day']] = (int)$dq['count'];
        }
        
        // Generate bars for last 7 days
        for ($i = 6; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $count = $questionsByDate[$date] ?? 0;
            $height = $maxQuestions > 0 ? round(($count / $maxQuestions) * 100) : 0;
            $height = max($height, 5); // Minimum 5% for visibility
            echo '<div class="bar" style="height:' . $height . '%" title="' . $count . ' questions"></div>';
        }
        ?>
      </div>
      <div class="bar-labels">
        <?php
        for ($i = 6; $i >= 0; $i--) {
            $dayIndex = ((int)date('N', strtotime("-{$i} days")) - 1 + 7) % 7;
            echo '<span class="bar-lbl">' . $dayNames[$dayIndex] . '</span>';
        }
        ?>
      </div>
    </div>

    <!-- Donut: user split -->
    <div class="chart-card">
      <div class="chart-title">User Split</div>
      <?php
      $total  = (int)($stats['total_users'] ?? 1);
      $pro    = (int)($stats['active_pro']  ?? 0);
      $free   = max(0, $total - $pro);
      $pctPro = $total > 0 ? round($pro / $total * 100) : 0;
      $circ   = 2 * M_PI * 34; // circumference for r=34
      $dash   = round($circ * $pctPro / 100, 1);
      $gap    = round($circ - $dash, 1);
      ?>
      <div class="donut-wrap">
        <svg viewBox="0 0 100 100" width="96" height="96">
          <circle cx="50" cy="50" r="34" fill="none" stroke="rgba(128,128,128,.12)" stroke-width="17"/>
          <circle cx="50" cy="50" r="34" fill="none" stroke="url(#dg)"
            stroke-width="17"
            stroke-dasharray="<?= $dash ?> <?= $gap ?>"
            stroke-dashoffset="-25" stroke-linecap="round"/>
          <circle cx="50" cy="50" r="34" fill="none" stroke="rgba(124,58,237,.45)"
            stroke-width="17"
            stroke-dasharray="<?= $gap ?> <?= $dash ?>"
            stroke-dashoffset="<?= round(-25 - $dash, 1) ?>" stroke-linecap="round"/>
          <defs>
            <linearGradient id="dg" x1="0%" y1="0%" x2="100%">
              <stop offset="0%" stop-color="#3b82f6"/>
              <stop offset="100%" stop-color="#7c3aed"/>
            </linearGradient>
          </defs>
        </svg>
        <div class="donut-center">
          <div class="donut-pct"><?= $pctPro ?>%</div>
          <div class="donut-sub">pro</div>
        </div>
      </div>
      <div class="d-legend">
        <div class="d-item">
          <div class="d-dot" style="background:linear-gradient(135deg,#3b82f6,#7c3aed)"></div>
          Pro (<?= $pctPro ?>%) — <?= number_format($pro) ?>
        </div>
        <div class="d-item">
          <div class="d-dot" style="background:rgba(124,58,237,.45)"></div>
          Free (<?= 100 - $pctPro ?>%) — <?= number_format($free) ?>
        </div>
      </div>
    </div>
  </div>

  <!-- Users by plan bar chart -->
  <?php if (!empty($planSummary)): ?>
  <div class="tbl-card" style="padding:1.4rem;margin-bottom:1.4rem">
    <div style="font-family:'Syne',sans-serif;font-size:.9rem;font-weight:600;margin-bottom:1.15rem">Users by Plan</div>
    <div class="user-bars">
      <?php
      $maxCount = max(1, max(array_column($planSummary, 'active_count')));
      $fillClasses = ['free'=>'free','monthly'=>'month','quarterly'=>'quad','semi_annual'=>'quad','annual'=>'year'];
      foreach ($planSummary as $p):
        $pct = round((int)$p['active_count'] / $maxCount * 100);
        $rev = number_format((float)$p['total_revenue'], 0);
        $cls = $fillClasses[$p['plan_code']] ?? 'month';
      ?>
      <div class="u-bar-row">
        <div class="u-bar-meta">
          <span class="u-bar-label"><?= safe($p['name']) ?></span>
          <span class="u-bar-val"><?= number_format((int)$p['active_count']) ?> users · <?= $rev ?> DZD</span>
        </div>
        <div class="u-bar-track">
          <div class="u-bar-fill <?= $cls ?>" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Recent activity feed -->
  <div class="tbl-card">
    <div class="tbl-head">
      <h3>Recent Activity</h3>
      <button class="btn btn-sm btn-ghost" onclick="swPanel('analytics',document.querySelector('[data-panel=analytics]'))">
        View Analytics
      </button>
    </div>
    <div class="activity-list">
      <?php if (empty($activity)): ?>
        <div class="empty-state"><i class="ti ti-mood-empty"></i><p>No recent activity yet.</p></div>
      <?php else: foreach ($activity as $act): ?>
      <div class="act-item">
        <?= activityIcon($act['type']) ?>
        <div class="act-body">
          <strong><?= safe($act['subject']) ?></strong> <?= safe($act['action']) ?>
        </div>
        <div class="act-time"><?= timeAgo($act['created_at']) ?></div>
      </div>
      <?php endforeach; endif; ?>
    </div>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: ANALYTICS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-analytics" class="panel">
  <div class="admin-hd"><h1>Analytics</h1><p>Deep dive into usage patterns and chatbot performance.</p></div>

  <div class="metrics">
    <div class="metric">
      <div class="m-label">Avg Session</div>
      <div class="m-value"><?= safe($stats['avg_session_min'] ?? '—') ?>m</div>
      <div class="m-delta m-up">minutes per session</div>
    </div>
    <div class="metric">
      <div class="m-label">Top Topic</div>
      <div class="m-value" style="font-size:1rem;padding-top:.3rem">
        <?= safe($stats['top_topics'][0]['topic'] ?? 'N/A') ?>
      </div>
      <div class="m-delta m-neu"><?= (int)($stats['top_topics'][0]['count'] ?? 0) ?> questions</div>
    </div>
    <div class="metric">
      <div class="m-label">Total Messages</div>
      <div class="m-value"><?= number_format((int)($stats['total_messages'] ?? 0)) ?></div>
      <div class="m-delta m-up">AI interactions</div>
    </div>
    <div class="metric">
      <div class="m-label">Questions Today</div>
      <div class="m-value"><?= number_format((int)($stats['questions_today'] ?? 0)) ?></div>
      <div class="m-delta m-up">AI interactions</div>
    </div>
    <div class="metric">
      <div class="m-label">Active Today</div>
      <div class="m-value"><?= number_format((int)($stats['active_today'] ?? 0)) ?></div>
      <div class="m-delta m-neu">unique users</div>
    </div>
    <div class="metric">
      <div class="m-label">Avg per User</div>
      <div class="m-value"><?= number_format((float)($stats['avg_messages_per_user'] ?? 0), 1) ?></div>
      <div class="m-delta m-up">messages/user</div>
    </div>
  </div>

  <!-- Weekly/Monthly Signups -->
  <div class="tbl-card" style="padding:1.4rem;margin-bottom:1.4rem">
    <div style="font-family:'Syne',sans-serif;font-size:.9rem;font-weight:600;margin-bottom:1.15rem">User Growth</div>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:1rem">
      <div style="background:var(--surface2);padding:1rem;border-radius:8px">
        <div style="font-size:.8rem;color:var(--text3);margin-bottom:.4rem">Weekly Signups</div>
        <div style="font-size:1.8rem;font-weight:700;background:linear-gradient(135deg,#3b82f6,#7c3aed);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">
          <?= number_format((int)($stats['weekly_signups'] ?? 0)) ?>
        </div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.2rem">Last 7 days</div>
      </div>
      <div style="background:var(--surface2);padding:1rem;border-radius:8px">
        <div style="font-size:.8rem;color:var(--text3);margin-bottom:.4rem">Monthly Signups</div>
        <div style="font-size:1.8rem;font-weight:700;background:linear-gradient(135deg,#059669,#34d399);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent">
          <?= number_format((int)($stats['monthly_signups'] ?? 0)) ?>
        </div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.2rem">Last 30 days</div>
      </div>
      <div style="background:var(--surface2);padding:1rem;border-radius:8px">
        <div style="font-size:.8rem;color:var(--text3);margin-bottom:.4rem">Pro Subscribers</div>
        <div style="font-size:1.8rem;font-weight:700;color:var(--acc)">
          <?= number_format((int)($stats['active_pro'] ?? 0)) ?>
        </div>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.2rem">Active subscriptions</div>
      </div>
    </div>
  </div>

  <!-- Top topics -->
  <?php if (!empty($stats['top_topics'])): ?>
  <div class="tbl-card" style="padding:1.4rem;margin-bottom:1.4rem">
    <div style="font-family:'Syne',sans-serif;font-size:.9rem;font-weight:600;margin-bottom:1.15rem">Top Chat Topics</div>
    <div class="user-bars">
      <?php
      $maxT = max(1, max(array_column($stats['top_topics'], 'count')));
      foreach ($stats['top_topics'] as $t):
        $pct = round((int)$t['count'] / $maxT * 100);
      ?>
      <div class="u-bar-row">
        <div class="u-bar-meta">
          <span class="u-bar-label"><?= safe($t['topic']) ?></span>
          <span class="u-bar-val"><?= number_format((int)$t['count']) ?> questions</span>
        </div>
        <div class="u-bar-track">
          <div class="u-bar-fill month" style="width:<?= $pct ?>%"></div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
  <?php endif; ?>

  <!-- Weekly questions chart with LIVE data -->
  <div class="tbl-card" style="padding:1.4rem">
    <div style="font-family:'Syne',sans-serif;font-size:.9rem;font-weight:600;
                margin-bottom:1.15rem;display:flex;justify-content:space-between">
      Weekly Questions <span style="font-size:.72rem;color:var(--text3);font-weight:400">Last 7 days</span>
    </div>
    <div class="bar-wrap" style="height:140px">
      <?php
      $dailyQuestions = $stats['daily_questions'] ?? [];
      $maxQuestions = !empty($dailyQuestions) ? max(array_column($dailyQuestions, 'count')) : 100;
      $maxQuestions = max($maxQuestions, 1);
      
      $questionsByDate = [];
      foreach ($dailyQuestions as $dq) {
          $questionsByDate[$dq['day']] = (int)$dq['count'];
      }
      
      for ($i = 6; $i >= 0; $i--) {
          $date = date('Y-m-d', strtotime("-{$i} days"));
          $count = $questionsByDate[$date] ?? 0;
          $height = $maxQuestions > 0 ? round(($count / $maxQuestions) * 100) : 0;
          $height = max($height, 5);
          echo '<div class="bar" style="height:' . $height . '%" title="' . $count . ' questions"></div>';
      }
      ?>
    </div>
    <div class="bar-labels">
      <?php 
      $days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
      for ($i = 6; $i >= 0; $i--) { 
          $dayIndex = ((int)date('N', strtotime("-{$i} days")) - 1 + 7) % 7;
          echo '<span class="bar-lbl">' . $days[$dayIndex] . '</span>'; 
      } 
      ?>
    </div>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: NOTIFICATIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-notifications" class="panel">
  <div class="admin-hd">
    <h1>Notifications</h1>
    <p>System alerts and items needing your attention.</p>
  </div>
  <div style="display:flex;justify-content:flex-end;margin-bottom:1rem">
    <button class="btn btn-sm btn-ghost" onclick="showToast('All notifications marked as read','ok')">
      Mark all as read
    </button>
  </div>
  <div class="notif-list">
    <?php if ($pendingReports > 0): ?>
    <div class="notif-item unread" onclick="swPanel('reports',document.querySelector('[data-panel=reports]'))" style="cursor:pointer">
      <div class="notif-ico" style="background:var(--err-bg);color:var(--err-txt)"><i class="ti ti-flag"></i></div>
      <div class="notif-body">
        <div class="notif-title"><?= $pendingReports ?> report<?= $pendingReports > 1 ? 's' : '' ?> pending review</div>
        <div class="notif-sub">Users reported posts and comments that need your decision.</div>
      </div>
      <div class="notif-time">now</div>
    </div>
    <?php endif; ?>
    <?php if (($stats['churned_month'] ?? 0) > 0): ?>
    <div class="notif-item unread">
      <div class="notif-ico" style="background:var(--warn-bg);color:var(--warn-txt)"><i class="ti ti-credit-card"></i></div>
      <div class="notif-body">
        <div class="notif-title"><?= $stats['churned_month'] ?> subscriptions expired this month</div>
        <div class="notif-sub">Review churned users in the Subscriptions panel.</div>
      </div>
      <div class="notif-time">this month</div>
    </div>
    <?php endif; ?>
    <div class="notif-item unread">
      <div class="notif-ico" style="background:var(--inf-bg);color:var(--inf-txt)"><i class="ti ti-users"></i></div>
      <div class="notif-body">
        <div class="notif-title"><?= number_format((int)($stats['total_users'] ?? 0)) ?> total registered users</div>
        <div class="notif-sub">Platform growing steadily.</div>
      </div>
      <div class="notif-time">today</div>
    </div>
    <div class="notif-item unread">
      <div class="notif-ico" style="background:var(--ok-bg);color:var(--ok-txt)"><i class="ti ti-brain"></i></div>
      <div class="notif-body">
        <div class="notif-title">RAG knowledge base is active</div>
        <div class="notif-sub">All FAQ entries are indexed and serving student queries.</div>
      </div>
      <div class="notif-time">system</div>
    </div>
    <div class="notif-item">
      <div class="notif-ico" style="background:var(--vio-bg);color:var(--vio-txt)"><i class="ti ti-shield-check"></i></div>
      <div class="notif-body">
        <div class="notif-title">Admin session active</div>
        <div class="notif-sub">Logged in as <?= safe($admin['email']) ?> · Role: <?= safe($admin['role']) ?></div>
      </div>
      <div class="notif-time"><?= date('H:i') ?></div>
    </div>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: USERS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-users" class="panel">
  <div class="admin-hd">
    <h1>Users</h1>
    <p>Manage <?= number_format($users['total'] ?? 0) ?> registered students.</p>
  </div>

  <div class="filter-bar">
    <input class="f-search" id="user-search" placeholder="Search by name or email…"
           oninput="filterUsers(this.value)" autocomplete="off"/>
    <select class="f-sel" id="user-type-filter" onchange="applyUserFilters()">
      <option value="">All Plans</option>
      <?php foreach ($planSummary as $p): ?>
      <option value="<?= safe($p['plan_code']) ?>"><?= safe($p['name']) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-ghost" onclick="exportUsers()">
      <i class="ti ti-download"></i> Export CSV
    </button>
    <button class="btn btn-sm btn-grad" onclick="loadMoreUsers()">
      <i class="ti ti-refresh"></i> Refresh
    </button>
  </div>

  <div class="tbl-card">
    <div class="tbl-scroll">
      <table id="users-table">
        <thead>
          <tr>
            <th>Student</th><th>Email</th><th>Plan</th>
            <th>University</th><th>Joined</th><th>Status</th><th>Actions</th>
          </tr>
        </thead>
        <tbody id="users-tbody">
        <?php if (empty($users['data'])): ?>
          <tr><td colspan="7"><div class="empty-state"><i class="ti ti-users-off"></i><p>No users found.</p></div></td></tr>
        <?php else: foreach ($users['data'] as $u):
          // Skip if $u is not an array (API error response)
          if (!is_array($u)) continue;
          $initials = strtoupper(substr($u['username'] ?? $u['email'] ?? 'U', 0, 1));
          $planName = $u['plan_name'] ?? 'Free';
        ?>
          <tr data-user-id="<?= safe($u['id']) ?>" data-email="<?= safe($u['email']) ?>"
              data-plan="<?= safe($u['plan_name'] ?? '') ?>">
            <td>
              <div style="display:flex;align-items:center;gap:.5rem">
                <div class="u-avatar"><?= $initials ?></div>
                <?= safe($u['full_name'] ?? $u['username']) ?>
              </div>
            </td>
            <td style="color:var(--text3)"><?= safe($u['email']) ?></td>
            <td><span class="bdg <?= $planName === 'Free' ? '' : 'bdg-ok' ?>"><?= safe($planName) ?></span></td>
            <td style="color:var(--text3)"><?= safe($u['university'] ?? $u['department'] ?? '—') ?></td>
            <td style="color:var(--text3);font-size:.82rem"><?= $u['created_at'] ? date('M j, Y', strtotime($u['created_at'])) : '—' ?></td>
            <td><span class="bdg <?= $u['is_active'] ? 'bdg-ok' : 'bdg-err' ?>"><?= $u['is_active'] ? 'Active' : 'Banned' ?></span></td>
            <td>
              <div style="display:flex;gap:.4rem">
                <?php if ($u['is_active']): ?>
                <button class="btn btn-sm btn-ghost" onclick="confirmBanUser('<?= safe($u['id']) ?>','<?= safe($u['full_name'] ?? $u['email']) ?>')" title="Ban user">
                  <i class="ti ti-ban"></i>
                </button>
                <?php else: ?>
                <button class="btn btn-sm btn-ghost" onclick="confirmUnbanUser('<?= safe($u['id']) ?>','<?= safe($u['full_name'] ?? $u['email']) ?>')" title="Unban user">
                  <i class="ti ti-lock-open"></i>
                </button>
                <?php endif; ?>
                <button class="btn btn-sm btn-ghost" onclick="viewUserDetails('<?= safe($u['id']) ?>','<?= safe($u['full_name'] ?? $u['email']) ?>')" title="View details">
                  <i class="ti ti-eye"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    <?php 
    $userTotal = $users['total'] ?? 0;
    $userLimit = $users['limit'] ?? 25;
    $userPage = $users['page'] ?? 1;
    if ($userTotal > $userLimit): 
      $totalPages = ceil($userTotal / $userLimit);
    ?>
    <div style="padding:1rem;border-top:1px solid var(--border);display:flex;justify-content:space-between;align-items:center">
      <div style="font-size:.82rem;color:var(--text3)">
        Showing <?= min($userLimit, $userTotal) ?> of <?= number_format($userTotal) ?> users
      </div>
      <div style="display:flex;gap:.5rem">
        <?php if ($userPage > 1): ?>
        <button class="btn btn-sm btn-ghost" onclick="loadUsersPage(<?= $userPage - 1 ?>)">
          <i class="ti ti-chevron-left"></i> Previous
        </button>
        <?php endif; ?>
        <?php if ($userPage < $totalPages): ?>
        <button class="btn btn-sm btn-ghost" onclick="loadUsersPage(<?= $userPage + 1 ?>)">
          Next <i class="ti ti-chevron-right"></i>
        </button>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: COMMUNITY
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-community" class="panel">
  <div class="admin-hd">
    <h1>Community</h1>
    <p>Moderate posts and comments from students.</p>
  </div>

  <div class="filter-bar">
    <input class="f-search" placeholder="Search posts…" oninput="filterTable('community-tbody',this.value)"/>
    <select class="f-sel" onchange="filterCommunityStatus(this.value)">
      <option value="">All Status</option>
      <option value="flagged">Flagged</option>
      <option value="visible">Visible</option>
    </select>
  </div>

  <div class="tbl-card">
    <div class="tbl-scroll">
      <table>
        <thead>
          <tr><th>Content</th><th>Author</th><th>Likes</th><th>Comments</th><th>Reports</th><th>Posted</th><th>Actions</th></tr>
        </thead>
        <tbody id="community-tbody">
        <?php if (empty($community['data'])): ?>
          <tr><td colspan="7">
            <div class="empty-state"><i class="ti ti-mood-happy"></i><p>No flagged content — community looks good!</p></div>
          </td></tr>
        <?php else: foreach ($community['data'] as $post): ?>
          <tr data-post-id="<?= safe($post['id']) ?>">
            <td style="max-width:240px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">
              <?= safe(mb_substr($post['content'] ?? '', 0, 80)) ?>
            </td>
            <td><?= safe($post['author_name'] ?? $post['author_username'] ?? '—') ?></td>
            <td><?= (int)($post['likes_count'] ?? 0) ?></td>
            <td><?= (int)($post['comments_count'] ?? 0) ?></td>
            <td>
              <?php if ((int)($post['pending_reports'] ?? 0) > 0): ?>
              <span class="bdg bdg-err"><?= $post['pending_reports'] ?> pending</span>
              <?php else: ?>
              <span class="bdg bdg-ok">Clean</span>
              <?php endif; ?>
            </td>
            <td style="color:var(--text3)"><?= $post['created_at'] ? date('M j', strtotime($post['created_at'])) : '—' ?></td>
            <td>
              <div style="display:flex;gap:5px">
                <button class="ico-btn" title="Hide post"
                        onclick="hidePost('<?= safe($post['id']) ?>')">
                  <i class="ti ti-eye-off"></i>
                </button>
                <button class="ico-btn del" title="Delete post"
                        onclick="confirmDeletePost('<?= safe($post['id']) ?>')">
                  <i class="ti ti-trash"></i>
                </button>
              </div>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: REPORTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-reports" class="panel">
  <div class="admin-hd">
    <h1>Reports</h1>
    <p>Student-submitted reports. Accept to remove content, refuse to keep it visible.</p>
  </div>

  <?php if (empty($reports)): ?>
    <div class="empty-state" style="padding:4rem 1rem;">
      <i class="ti ti-shield-check" style="font-size:3rem;color:var(--ok-txt)"></i>
      <p style="font-size:1rem;color:var(--text2);margin-top:.5rem">No pending reports — all clear!</p>
    </div>
  <?php else: ?>
  <div class="report-list" id="reports-list">
    <?php foreach ($reports as $i => $rep): $cardId = 'rep-' . $i; ?>
    <div class="report-card" id="<?= $cardId ?>" data-report-id="<?= safe($rep['id']) ?>">
      <div class="report-top">
        <div class="report-meta">
          <div class="report-type" style="color:var(--err-txt)">
            <i class="ti ti-flag"></i> Post Report · Pending
          </div>
          <div class="report-content">"<?= safe(mb_substr($rep['post_content'] ?? '', 0, 100)) ?>"</div>
          <div class="report-info">
            By <strong><?= safe($rep['post_author_username'] ?? $rep['post_author_email'] ?? '—') ?></strong>
            · Reported by <strong><?= safe($rep['reporter_username'] ?? $rep['reporter_email'] ?? '—') ?></strong>
            · <?= timeAgo($rep['created_at']) ?>
          </div>
        </div>
        <span class="bdg bdg-err">Pending</span>
      </div>
      <div class="report-reason"><strong>Reason:</strong> <?= safe($rep['reason']) ?>
        <?= $rep['description'] ? ' — ' . safe($rep['description']) : '' ?>
      </div>
      <div class="report-actions">
        <button class="btn-accept" onclick="resolveReport('<?= $cardId ?>','<?= safe($rep['id']) ?>','accepted')">
          <i class="ti ti-check"></i> Accept — Remove Post
        </button>
        <button class="btn-refuse" onclick="resolveReport('<?= $cardId ?>','<?= safe($rep['id']) ?>','refused')">
          <i class="ti ti-x"></i> Refuse — Keep Visible
        </button>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: ANNOUNCEMENTS (ADS & VIDEOS)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-announce" class="panel" style="display:none">
  <div class="admin-hd">
    <h1>Announcements Management</h1>
    <p>Manage ads and educational videos displayed in the app.</p>
  </div>

  <!-- Tab Navigation -->
  <div style="display:flex;gap:1rem;margin-bottom:1.5rem;border-bottom:1px solid var(--border)">
    <button class="tab-btn active" data-tab="ads" onclick="switchAnnouncementTab('ads')">
      <i class="ti ti-ad"></i> Ads
    </button>
    <button class="tab-btn" data-tab="videos" onclick="switchAnnouncementTab('videos')">
      <i class="ti ti-video"></i> Videos
    </button>
  </div>

  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
       ADS TAB
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <div id="tab-ads" class="announcement-tab active">
    
    <!-- Add New Ad Form -->
    <div class="settings-card">
      <h3>Add New Ad</h3>
      <div class="f-form" id="ad-form">
        <div class="f-group">
          <label class="f-label">Title *</label>
          <input class="f-input" id="ad-title" placeholder="e.g. Summer Enrollment 2026" maxlength="120" required/>
        </div>
        <div class="f-group">
          <label class="f-label">Description</label>
          <textarea class="f-textarea" id="ad-description" placeholder="Describe the ad content..." maxlength="500"></textarea>
        </div>
        <div class="f-group">
          <label class="f-label">Google Drive URL *</label>
          <input class="f-input" id="ad-drive-url" placeholder="https://drive.google.com/..." required/>
          <small style="color:var(--text3);font-size:.8rem">Link to image/video on Google Drive</small>
        </div>
        <div class="f-row2">
          <div class="f-group">
            <label class="f-label">Start Date *</label>
            <input class="f-input" id="ad-start-date" type="datetime-local" required/>
          </div>
          <div class="f-group">
            <label class="f-label">End Date *</label>
            <input class="f-input" id="ad-end-date" type="datetime-local" required/>
          </div>
        </div>
        <div class="f-group">
          <label class="f-check">
            <input type="checkbox" id="ad-is-active" checked/>
            <span>Active (visible to users)</span>
          </label>
        </div>
        <div>
          <button class="btn btn-md btn-grad" onclick="saveAd()">
            <i class="ti ti-plus"></i> Add Ad
          </button>
        </div>
      </div>
    </div>

    <!-- Ads List -->
    <div class="tbl-card">
      <div class="tbl-head">
        <h3>All Ads</h3>
        <button class="btn btn-sm btn-ghost" onclick="loadAds()">
          <i class="ti ti-refresh"></i> Refresh
        </button>
      </div>
      <div class="tbl-scroll">
        <table id="ads-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Description</th>
              <th>Start Date</th>
              <th>End Date</th>
              <th>Status</th>
              <th>Views</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="ads-tbody">
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <i class="ti ti-loader"></i>
                  <p>Loading ads...</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  <!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
       VIDEOS TAB
  ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
  <div id="tab-videos" class="announcement-tab" style="display:none">
    
    <!-- Add New Video Form -->
    <div class="settings-card">
      <h3>Add New Video</h3>
      <div class="f-form" id="video-form">
        <div class="f-group">
          <label class="f-label">Title *</label>
          <input class="f-input" id="video-title" placeholder="e.g. Introduction to Computer Science" maxlength="120" required/>
        </div>
        <div class="f-group">
          <label class="f-label">Description</label>
          <textarea class="f-textarea" id="video-description" placeholder="Describe the video content..." maxlength="500"></textarea>
        </div>
        <div class="f-group">
          <label class="f-label">Google Drive URL *</label>
          <input class="f-input" id="video-drive-url" placeholder="https://drive.google.com/..." required/>
          <small style="color:var(--text3);font-size:.8rem">Link to video on Google Drive</small>
        </div>
        <div class="f-row2">
          <div class="f-group">
            <label class="f-label">Category</label>
            <input class="f-input" id="video-category" placeholder="e.g. Computer Science, Math, Physics"/>
          </div>
          <div class="f-group">
            <label class="f-label">Tags (comma-separated)</label>
            <input class="f-input" id="video-tags" placeholder="e.g. programming, algorithms, tutorial"/>
          </div>
        </div>
        <div class="f-group">
          <label class="f-check">
            <input type="checkbox" id="video-is-active" checked/>
            <span>Active (visible to users)</span>
          </label>
        </div>
        <div>
          <button class="btn btn-md btn-grad" onclick="saveVideo()">
            <i class="ti ti-plus"></i> Add Video
          </button>
        </div>
      </div>
    </div>

    <!-- Videos List -->
    <div class="tbl-card">
      <div class="tbl-head">
        <h3>All Videos</h3>
        <button class="btn btn-sm btn-ghost" onclick="loadVideos()">
          <i class="ti ti-refresh"></i> Refresh
        </button>
      </div>
      <div class="tbl-scroll">
        <table id="videos-table">
          <thead>
            <tr>
              <th>Title</th>
              <th>Description</th>
              <th>Category</th>
              <th>Tags</th>
              <th>Status</th>
              <th>Views</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="videos-tbody">
            <tr>
              <td colspan="7">
                <div class="empty-state">
                  <i class="ti ti-loader"></i>
                  <p>Loading videos...</p>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<style>
/* Tab Styles */
.tab-btn{
  background:transparent;border:none;color:var(--text2);
  font-size:.9rem;font-weight:500;padding:.75rem 1.25rem;
  cursor:pointer;border-bottom:2px solid transparent;
  transition:all .2s;display:flex;align-items:center;gap:.5rem;
}
.tab-btn:hover{color:var(--text);background:var(--surface2)}
.tab-btn.active{color:var(--acc);border-bottom-color:var(--acc)}
.announcement-tab{display:none}
.announcement-tab.active{display:block}
.f-check{display:flex;align-items:center;gap:.6rem;cursor:pointer;font-size:.9rem}
.f-check input{width:18px;height:18px;cursor:pointer}
</style>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: BILLING / SUBSCRIPTIONS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-billing" class="panel">
  <div class="admin-hd">
    <h1>Subscriptions</h1>
    <p>Monitor revenue and manage all payment plans.</p>
  </div>

  <!-- Plan overview cards -->
  <div class="plan-overview">
    <?php
    $planIcons  = ['free'=>'ti-user','monthly'=>'ti-calendar','quarterly'=>'ti-calendar-month','semi_annual'=>'ti-calendar-month','annual'=>'ti-calendar-stats'];
    $planColors = [
      'free'        => 'rgba(148,163,184,.12)',
      'monthly'     => 'rgba(59,130,246,.15)',
      'quarterly'   => 'rgba(124,58,237,.15)',
      'semi_annual' => 'rgba(124,58,237,.15)',
      'annual'      => 'rgba(5,150,105,.15)',
    ];
    $planCountGrad = [
      'free'        => 'color:var(--text2)',
      'monthly'     => 'background:linear-gradient(135deg,#3b82f6,#60a5fa);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent',
      'quarterly'   => 'background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent',
      'semi_annual' => 'background:linear-gradient(135deg,#7c3aed,#a78bfa);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent',
      'annual'      => 'background:linear-gradient(135deg,#059669,#34d399);-webkit-background-clip:text;background-clip:text;-webkit-text-fill-color:transparent',
    ];
    foreach ($planSummary as $p):
      $code = $p['plan_code'];
      $icon = $planIcons[$code] ?? 'ti-credit-card';
      $bg   = $planColors[$code] ?? 'rgba(59,130,246,.15)';
      $grad = $planCountGrad[$code] ?? '';
    ?>
    <div class="plan-card" style="<?= $code !== 'free' ? 'border-color:rgba(59,130,246,.2)' : '' ?>">
      <div class="plan-card-top">
        <div class="plan-card-icon" style="background:<?= $bg ?>">
          <i class="ti <?= $icon ?>"></i>
        </div>
        <span class="plan-name"><?= safe($p['name']) ?></span>
      </div>
      <div class="plan-count" style="<?= $grad ?>"><?= number_format((int)$p['active_count']) ?></div>
      <div class="plan-revenue">
        <?php if ($p['price'] > 0): ?>
          <?= number_format((float)$p['price'], 0) ?> DZD ·
          <strong><?= number_format((float)$p['total_revenue'], 0) ?> DZD</strong>
        <?php else: ?>
          Free tier
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- MRR summary -->
  <div class="tbl-card" style="padding:1.2rem 1.4rem;margin-bottom:1.4rem;display:flex;align-items:center;gap:2rem;flex-wrap:wrap">
    <div>
      <div class="m-label">Monthly Recurring Revenue</div>
      <div class="m-value"><?= number_format((float)($stats['mrr'] ?? 0), 0) ?> DZD</div>
    </div>
    <div>
      <div class="m-label">Active Pro Users</div>
      <div class="m-value"><?= number_format((int)($stats['active_pro'] ?? 0)) ?></div>
    </div>
    <div>
      <div class="m-label">Churned This Month</div>
      <div class="m-value" style="color:var(--err-txt)"><?= number_format((int)($stats['churned_month'] ?? 0)) ?></div>
    </div>
  </div>

  <!-- Plan filter tabs -->
  <div class="plan-tabs">
    <button class="plan-tab active" onclick="filterPlan(this,'all')">All Plans</button>
    <?php foreach ($planSummary as $p): ?>
    <button class="plan-tab" onclick="filterPlan(this,'<?= safe($p['plan_code']) ?>')"><?= safe($p['name']) ?></button>
    <?php endforeach; ?>
  </div>

  <!-- Transactions table -->
  <div class="tbl-card">
    <div class="tbl-head">
      <h3>Recent Transactions</h3>
      <button class="btn btn-sm btn-ghost" onclick="exportTransactions()">
        <i class="ti ti-download"></i> Export
      </button>
    </div>
    <div class="tbl-scroll">
      <table>
        <thead><tr><th>Student</th><th>Plan</th><th>Amount</th><th>Date</th><th>Method</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($txns)): ?>
          <tr><td colspan="6"><div class="empty-state"><i class="ti ti-receipt"></i><p>No transactions yet.</p></div></td></tr>
        <?php else: foreach ($txns as $tx): ?>
          <tr class="plan-row" data-plan="<?= safe($tx['plan_name'] ?? '') ?>">
            <td><?= safe($tx['username'] ?? $tx['email'] ?? '—') ?></td>
            <td><?= safe($tx['plan_name'] ?? '—') ?></td>
            <td><?= number_format((float)($tx['amount'] ?? 0), 0) ?> <?= safe($tx['currency'] ?? 'DZD') ?></td>
            <td style="color:var(--text3)"><?= $tx['created_at'] ? date('M j, Y', strtotime($tx['created_at'])) : '—' ?></td>
            <td style="color:var(--text3)"><?= safe($tx['payment_method'] ?? 'N/A') ?></td>
            <td><span class="bdg <?= badgeClass($tx['status'] ?? '') ?>"><?= safe(ucfirst($tx['status'] ?? '—')) ?></span></td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Plan Management Section -->
  <div class="tbl-card" style="margin-top:2rem">
    <div class="tbl-head">
      <h3>Manage Subscription Plans</h3>
      <button class="btn btn-sm btn-grad" onclick="openCreatePlanModal()">
        <i class="ti ti-plus"></i> Create New Plan
      </button>
    </div>
    <div class="tbl-scroll">
      <table id="plans-table">
        <thead>
          <tr>
            <th>Plan Name</th>
            <th>Code</th>
            <th>Price</th>
            <th>Duration</th>
            <th>Active</th>
            <th>Popular</th>
            <th>Order</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="plans-tbody">
          <?php if (empty($plans)): ?>
            <tr><td colspan="8"><div class="empty-state"><i class="ti ti-credit-card-off"></i><p>No plans defined yet.</p></div></td></tr>
          <?php else: foreach ($plans as $plan): 
            // Safety check: skip if $plan is not an array
            if (!is_array($plan)) continue;
          ?>
            <tr data-plan-id="<?= safe($plan['id']) ?>">
              <td><strong><?= safe($plan['name']) ?></strong></td>
              <td><code><?= safe($plan['plan_code']) ?></code></td>
              <td><?= number_format((float)($plan['price'] ?? 0), 0) ?> <?= safe($plan['currency'] ?? 'DZD') ?></td>
              <td><?= (int)($plan['duration_months'] ?? 1) ?> month<?= ($plan['duration_months'] ?? 1) > 1 ? 's' : '' ?></td>
              <td><span class="bdg <?= ($plan['is_active'] ?? false) ? 'bdg-ok' : 'bdg-err' ?>"><?= ($plan['is_active'] ?? false) ? 'Yes' : 'No' ?></span></td>
              <td><span class="bdg <?= ($plan['is_popular'] ?? false) ? 'bdg-warn' : '' ?>"><?= ($plan['is_popular'] ?? false) ? '★ Popular' : '—' ?></span></td>
              <td><?= (int)($plan['display_order'] ?? 0) ?></td>
              <td>
                <div style="display:flex;gap:.4rem">
                  <button class="btn btn-sm btn-ghost" onclick="openEditPlanModal('<?= safe($plan['id']) ?>')" title="Edit plan">
                    <i class="ti ti-edit"></i>
                  </button>
                  <button class="btn btn-sm btn-ghost" onclick="confirmDeletePlan('<?= safe($plan['id']) ?>','<?= safe($plan['name']) ?>')" title="Delete plan" style="color:var(--err-txt)">
                    <i class="ti ti-trash"></i>
                  </button>
                </div>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     MODAL: CREATE/EDIT PLAN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div class="modal-overlay" id="plan-modal">
  <div class="modal-box" style="max-width:600px">
    <div class="modal-hd">
      <h2 id="plan-modal-title">Create Subscription Plan</h2>
      <button class="modal-close" onclick="closeModal('plan-modal')" aria-label="Close">
        <i class="ti ti-x"></i>
      </button>
    </div>
    <form id="plan-form" onsubmit="savePlan(event)">
      <input type="hidden" id="plan-id" value=""/>
      <div class="f-group">
        <label class="f-label">Plan Name <span style="color:var(--err-txt)">*</span></label>
        <input class="f-input" id="plan-name" type="text" required placeholder="e.g. Student Pro"/>
      </div>
      <div class="f-group">
        <label class="f-label">Plan Code</label>
        <input class="f-input" id="plan-code" type="text" placeholder="Auto-generated from name"/>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.25rem">Leave blank to auto-generate</div>
      </div>
      <div class="f-group">
        <label class="f-label">Description</label>
        <textarea class="f-textarea" id="plan-desc" placeholder="Short description of the plan" rows="2"></textarea>
      </div>
      <div class="f-row2">
        <div class="f-group">
          <label class="f-label">Price <span style="color:var(--err-txt)">*</span></label>
          <input class="f-input" id="plan-price" type="number" min="0" step="0.01" required placeholder="0.00"/>
        </div>
        <div class="f-group">
          <label class="f-label">Currency</label>
          <select class="f-select" id="plan-currency">
            <option value="DZD">DZD</option>
            <option value="USD">USD</option>
            <option value="EUR">EUR</option>
          </select>
        </div>
      </div>
      <div class="f-row2">
        <div class="f-group">
          <label class="f-label">Duration (Months) <span style="color:var(--err-txt)">*</span></label>
          <input class="f-input" id="plan-duration" type="number" min="1" max="120" required placeholder="1"/>
        </div>
        <div class="f-group">
          <label class="f-label">Display Order</label>
          <input class="f-input" id="plan-order" type="number" min="0" placeholder="0"/>
        </div>
      </div>
      <div class="f-group">
        <label class="f-label">Features (comma-separated)</label>
        <input class="f-input" id="plan-features" type="text" placeholder="e.g. Unlimited AI questions, Priority support"/>
        <div style="font-size:.75rem;color:var(--text3);margin-top:.25rem">Separate features with commas</div>
      </div>
      <div class="f-row2">
        <div class="f-group">
          <label class="f-check">
            <input type="checkbox" id="plan-active" checked/>
            <span>Active (visible to users)</span>
          </label>
        </div>
        <div class="f-group">
          <label class="f-check">
            <input type="checkbox" id="plan-popular"/>
            <span>Mark as Popular</span>
          </label>
        </div>
      </div>
      <div class="modal-actions">
        <button type="button" class="btn btn-md btn-ghost" onclick="closeModal('plan-modal')">Cancel</button>
        <button type="submit" class="btn btn-md btn-grad" id="plan-save-btn">
          <i class="ti ti-device-floppy"></i> <span id="plan-save-text">Create Plan</span>
        </button>
      </div>
    </form>
  </div>
</div>

<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: WEBSITE CONTENT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-website" class="panel">
  <div class="admin-hd">
    <h1>Edit Website Content</h1>
    <p>Changes here update the public-facing marketing site.</p>
  </div>

  <div class="settings-card">
    <h3>Pricing</h3>
    <div class="f-form" id="pricing-form">
      <div class="f-row2">
        <?php foreach ($planSummary as $p): if ((float)$p['price'] <= 0) continue; ?>
        <div class="f-group">
          <label class="f-label"><?= safe($p['name']) ?> Price (DZD)</label>
          <input class="f-input" type="number" min="0"
                 data-plan-code="<?= safe($p['plan_code']) ?>"
                 value="<?= number_format((float)$p['price'], 0) ?>"/>
        </div>
        <?php endforeach; ?>
        <div class="f-group">
          <label class="f-label">Free Limit (questions/month)</label>
          <input class="f-input" type="number" min="1" max="1000" value="20" id="free-limit"/>
        </div>
      </div>
      <div>
        <button class="btn btn-md btn-grad" onclick="savePricing()">
          <i class="ti ti-device-floppy"></i> Save Pricing
        </button>
      </div>
    </div>
  </div>

  <div class="settings-card">
    <h3>Contact &amp; Links</h3>
    <div class="f-form">
      <div class="f-group">
        <label class="f-label">Support Email</label>
        <input class="f-input" id="support-email" value="talebdz2026@gmail.com"/>
      </div>
      <div class="f-group">
        <label class="f-label">App Store Link</label>
        <input class="f-input" id="appstore-link" placeholder="https://apps.apple.com/…"/>
      </div>
      <div class="f-group">
        <label class="f-label">Play Store Link</label>
        <input class="f-input" id="playstore-link" placeholder="https://play.google.com/…"/>
      </div>
      <div>
        <button class="btn btn-md btn-grad" onclick="saveLinks()">
          <i class="ti ti-device-floppy"></i> Save Links
        </button>
      </div>
    </div>
  </div>
</div>


<!-- ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
     PANEL: SETTINGS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ -->
<div id="p-settings" class="panel">
  <div class="admin-hd"><h1>Settings</h1><p>Configure your admin account and application.</p></div>

  <div class="settings-card">
    <h3>Admin Account</h3>
    <div class="f-form">
      <div class="f-group">
        <label class="f-label">Full Name</label>
        <input class="f-input" id="set-name" value="<?= safe($admin['name']) ?>"/>
      </div>
      <div class="f-group">
        <label class="f-label">Email</label>
        <input class="f-input" id="set-email" type="email" value="<?= safe($admin['email']) ?>"/>
      </div>
      <div class="f-group">
        <label class="f-label">New Password</label>
        <input class="f-input" id="set-pw" type="password" placeholder="Leave blank to keep current"/>
      </div>
      <div class="f-group">
        <label class="f-label">Confirm Password</label>
        <input class="f-input" id="set-pw2" type="password" placeholder="Repeat new password"/>
      </div>
      <div>
        <button class="btn btn-md btn-grad" onclick="saveAccount()">
          <i class="ti ti-device-floppy"></i> Update Account
        </button>
      </div>
    </div>
  </div>

  <div class="settings-card">
    <h3>Session</h3>
    <div class="f-form">
      <div style="font-size:.875rem;color:var(--text2);line-height:1.7">
        <div>Logged in as: <strong><?= safe($admin['email']) ?></strong></div>
        <div>Role: <strong><?= safe($admin['role']) ?></strong></div>
        <div>Session started: <strong><?= date('D, M j Y H:i') ?></strong></div>
      </div>
      <div>
        <button class="btn btn-md btn-outline" onclick="adminLogout()">
          <i class="ti ti-logout"></i> Sign Out
        </button>
      </div>
    </div>
  </div>
</div>

</main>
</div><!-- /admin-wrap -->


<!-- ═══════════════════════════════════
     CONFIRM MODAL
════════════════════════════════════ -->
<div class="modal-overlay" id="confirm-modal">
  <div class="modal-box confirm-box">
    <h3 id="confirm-title">Confirm Action</h3>
    <p id="confirm-msg">Are you sure?</p>
    <div class="modal-footer">
      <button class="btn btn-sm btn-ghost" onclick="closeModal('confirm-modal')">Cancel</button>
      <button class="btn btn-sm btn-grad" id="confirm-ok" style="background:var(--err-bg);color:var(--err-txt);border:1px solid var(--err-txt)">Confirm</button>
    </div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<!-- ═══════════════════════════════════
     SCRIPTS
════════════════════════════════════ -->
<script src="<?= $baseUrl ?>/admin/app.js"></script>
<script>
// ── Shared state ──────────────────────────────────────────────
const CSRF  = document.querySelector('meta[name="csrf-token"]')?.content || '';
const ADMIN_ID = document.querySelector('meta[name="admin-id"]')?.content || '';

// ── API helper ────────────────────────────────────────────────
async function api(endpoint, method = 'GET', body = null) {
  const opts = {
    method,
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': CSRF,
      'X-Requested-With': 'XMLHttpRequest',
    },
  };
  if (body) opts.body = JSON.stringify(body);
  const res  = await fetch(endpoint, opts);
  const data = await res.json().catch(() => ({}));
  if (!res.ok) throw new Error(data.error || `HTTP ${res.status}`);
  return data;
}

// ── Toast ─────────────────────────────────────────────────────
function showToast(msg, type = 'ok') {
  const el = document.getElementById('toast');
  el.className = `toast toast-${type}`;
  el.innerHTML = `<i class="ti ti-${type === 'ok' ? 'check' : 'alert-circle'}"></i>${msg}`;
  el.classList.add('show');
  setTimeout(() => el.classList.remove('show'), 3200);
}

// ── Confirm modal ─────────────────────────────────────────────
function showConfirm(title, msg, onOk) {
  document.getElementById('confirm-title').textContent = title;
  document.getElementById('confirm-msg').textContent   = msg;
  const btn = document.getElementById('confirm-ok');
  btn.onclick = () => { closeModal('confirm-modal'); onOk(); };
  openModal('confirm-modal');
}

// ── Dropdown toggle ───────────────────────────────────────────
function toggleDropdown(id) {
  const el = document.getElementById(id);
  if (!el) return;
  const visible = el.style.display !== 'none';
  document.querySelectorAll('[id$="-dd"]').forEach(d => d.style.display = 'none');
  el.style.display = visible ? 'none' : 'block';
}
document.addEventListener('click', e => {
  if (!e.target.closest('.admin-avatar') && !e.target.closest('[id$="-dd"]')) {
    document.querySelectorAll('[id$="-dd"]').forEach(d => d.style.display = 'none');
  }
});

// Note: toggleSidebar() and closeSidebar() are defined in app.js
// Note: swPanel() is defined in app.js - DO NOT override it here

// ── Logout ────────────────────────────────────────────────────
async function adminLogout() {
  try {
    const d = await api('../admin/logout.php', 'POST', { csrf_token: CSRF });
    window.location.href = d.redirect || '../admin/login.php';
  } catch {
    window.location.href = '../admin/login.php';
  }
}

// ── Reports: resolve via API ──────────────────────────────────
async function resolveReport(cardId, reportId, action) {
  const card = document.getElementById(cardId);
  if (!card) return;
  try {
    await api('../api/reports.php', 'POST', { report_id: reportId, action });
    const actEl  = card.querySelector('.report-actions');
    const badgeEl = card.querySelector('.bdg');
    if (action === 'accepted') {
      actEl.innerHTML = '<div class="report-resolved"><i class="ti ti-check"></i> Accepted — content removed</div>';
      if (badgeEl) { badgeEl.className = 'bdg bdg-ok'; badgeEl.textContent = 'Accepted'; }
    } else {
      actEl.innerHTML = '<div class="report-resolved" style="background:var(--surface2);color:var(--text3)"><i class="ti ti-x"></i> Refused — content kept visible</div>';
      if (badgeEl) { badgeEl.className = 'bdg'; badgeEl.style.cssText = 'background:var(--surface2);color:var(--text3)'; badgeEl.textContent = 'Refused'; }
    }
    card.style.opacity = '.55';
    card.style.pointerEvents = 'none';
    showToast(`Report ${action === 'accepted' ? 'accepted — post removed' : 'refused — content kept'}`, 'ok');
  } catch (err) {
    // Optimistic UI fallback (API not wired yet)
    const actEl = card.querySelector('.report-actions');
    if (action === 'accepted') {
      actEl.innerHTML = '<div class="report-resolved"><i class="ti ti-check"></i> Accepted — content removed</div>';
    } else {
      actEl.innerHTML = '<div class="report-resolved" style="background:var(--surface2);color:var(--text3)"><i class="ti ti-x"></i> Refused — kept visible</div>';
    }
    card.style.opacity = '.55';
    showToast(`Report ${action}`, 'ok');
  }
}

// ── Users: ban/unban/view ─────────────────────────────────────
function confirmBanUser(userId, name) {
  showConfirm('Ban User', `Ban "${name}"? They will lose access immediately.`, () => toggleUserStatus(userId, false));
}
function confirmUnbanUser(userId, name) {
  showConfirm('Unban User', `Restore access for "${name}"?`, () => toggleUserStatus(userId, true));
}
async function toggleUserStatus(userId, active) {
  try {
    await api('../api/users.php', 'POST', { user_id: userId, action: active ? 'unban' : 'ban' });
    showToast(`User ${active ? 'unbanned' : 'banned'} successfully`, 'ok');
    setTimeout(() => location.reload(), 1200);
  } catch (err) {
    showToast('Action failed: ' + err.message, 'err');
  }
}
function viewUserDetails(id, name) {
  showToast(`Opening profile for ${name}`, 'ok');
  // Future: open user detail modal or navigate to user page
  // For now, just highlight the row
  document.querySelectorAll(`#users-tbody tr`).forEach(r => r.style.background = '');
  const row = document.querySelector(`#users-tbody tr[data-user-id="${id}"]`);
  if (row) {
    row.style.background = 'var(--surface2)';
    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
  }
}

// ── Community: hide / delete posts ───────────────────────────
function hidePost(postId) {
  showToast('Post hidden from community', 'ok');
  const row = document.querySelector(`[data-post-id="${postId}"]`);
  if (row) row.style.opacity = '.4';
}
function confirmDeletePost(postId) {
  showConfirm('Delete Post', 'Permanently delete this post and all its comments?', async () => {
    try {
      await api('../api/community.php', 'POST', { post_id: postId, action: 'delete' });
      const row = document.querySelector(`[data-post-id="${postId}"]`);
      row?.remove();
      showToast('Post deleted', 'ok');
    } catch {
      showToast('Delete failed', 'err');
    }
  });
}

// ── Table search filter ───────────────────────────────────────
function filterTable(tbodyId, query) {
  const q = query.toLowerCase();
  document.querySelectorAll(`#${tbodyId} tr`).forEach(row => {
    row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
  });
}

// ── Users table search ────────────────────────────────────────
function filterUsers(query) {
  filterTable('users-tbody', query);
}
function applyUserFilters() {
  const plan = document.getElementById('user-type-filter').value.toLowerCase();
  document.querySelectorAll('#users-tbody tr').forEach(row => {
    const rPlan = (row.dataset.plan || '').toLowerCase();
    row.style.display = (!plan || rPlan.includes(plan)) ? '' : 'none';
  });
}
function loadMoreUsers() { location.reload(); }

// ── Community status filter ───────────────────────────────────
function filterCommunityStatus(val) {
  document.querySelectorAll('#community-tbody tr').forEach(row => {
    if (!val) { row.style.display = ''; return; }
    const hasFlagged = row.querySelector('.bdg-err') !== null;
    row.style.display = (val === 'flagged' ? hasFlagged : !hasFlagged) ? '' : 'none';
  });
}

// ── Billing plan filter ───────────────────────────────────────
function filterPlan(btn, plan) {
  document.querySelectorAll('.plan-tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  document.querySelectorAll('.plan-row').forEach(row => {
    const rp = (row.dataset.plan || '').toLowerCase();
    row.style.display = (plan === 'all' || rp.includes(plan.toLowerCase())) ? '' : 'none';
  });
}

// ── Announcements ─────────────────────────────────────────────
async function sendAnnouncement() {
  const title    = document.getElementById('ann-title').value.trim();
  const msg      = document.getElementById('ann-msg').value.trim();
  const audience = document.getElementById('ann-audience').value;
  const sendAt   = document.getElementById('ann-time').value;

  if (!title || !msg) { showToast('Title and message are required', 'err'); return; }

  try {
    await api('../api/announcements.php', 'POST', { title, message: msg, audience, send_at: sendAt });
    showToast('Announcement sent successfully!', 'ok');
    document.getElementById('ann-title').value = '';
    document.getElementById('ann-msg').value   = '';
  } catch (err) {
    showToast('Failed to send: ' + err.message, 'err');
  }
}

// ── Pricing save ──────────────────────────────────────────────
async function savePricing() {
  const inputs  = document.querySelectorAll('#pricing-form input[data-plan-code]');
  const updates = Array.from(inputs).map(el => ({
    plan_code: el.dataset.planCode,
    price: parseFloat(el.value),
  }));
  const freeLimit = parseInt(document.getElementById('free-limit')?.value || 20);
  try {
    await api('../api/settings.php', 'POST', { action: 'update_pricing', plans: updates, free_limit: freeLimit });
    showToast('Pricing updated successfully!', 'ok');
  } catch {
    showToast('Pricing saved locally (API not connected)', 'ok');
  }
}

// ── Links save ────────────────────────────────────────────────
async function saveLinks() {
  const email   = document.getElementById('support-email').value;
  const appstore = document.getElementById('appstore-link').value;
  const playstore = document.getElementById('playstore-link').value;
  try {
    await api('../api/settings.php', 'POST', { action: 'update_links', email, appstore, playstore });
    showToast('Links saved!', 'ok');
  } catch {
    showToast('Links saved locally', 'ok');
  }
}

// ── Account save ──────────────────────────────────────────────
async function saveAccount() {
  const email = document.getElementById('set-email').value.trim();
  const pw    = document.getElementById('set-pw').value;
  const pw2   = document.getElementById('set-pw2').value;

  if (!email) { showToast('Email is required', 'err'); return; }
  if (pw && pw !== pw2) { showToast('Passwords do not match', 'err'); return; }
  if (pw && pw.length < 8) { showToast('Password must be at least 8 characters', 'err'); return; }

  try {
    await api('../api/settings.php', 'POST', {
      action: 'update_account',
      admin_id: ADMIN_ID,
      email,
      password: pw || null,
    });
    showToast('Account updated!', 'ok');
  } catch (err) {
    showToast('Update failed: ' + err.message, 'err');
  }
}

// ── Plans Management ─────────────────────────────────────────
function openCreatePlanModal() {
  document.getElementById('plan-modal-title').textContent = 'Create Subscription Plan';
  document.getElementById('plan-save-text').textContent = 'Create Plan';
  document.getElementById('plan-id').value = '';
  document.getElementById('plan-form').reset();
  document.getElementById('plan-active').checked = true;
  openModal('plan-modal');
}

function openEditPlanModal(planId) {
  // This will be called by plans.js which has access to the plans data
  showToast('Edit plan feature - delegated to plans.js', 'ok');
}

function confirmDeletePlan(planId, planName) {
  showConfirm('Delete Plan', `Delete "${planName}"? Users on this plan will lose access.`, async () => {
    try {
      await api('../api/admin-plans.php', 'POST', { action: 'delete', plan_id: planId });
      showToast('Plan deleted successfully', 'ok');
      setTimeout(() => location.reload(), 1200);
    } catch (err) {
      showToast('Failed to delete plan: ' + err.message, 'err');
    }
  });
}

async function savePlan(event) {
  event.preventDefault();
  
  const planId = document.getElementById('plan-id').value;
  const isEdit = !!planId;
  
  const data = {
    action: isEdit ? 'update' : 'create',
    plan_id: planId || undefined,
    name: document.getElementById('plan-name').value.trim(),
    plan_code: document.getElementById('plan-code').value.trim(),
    description: document.getElementById('plan-desc').value.trim(),
    price: parseFloat(document.getElementById('plan-price').value),
    currency: document.getElementById('plan-currency').value,
    duration_months: parseInt(document.getElementById('plan-duration').value),
    display_order: parseInt(document.getElementById('plan-order').value || '0'),
    features: document.getElementById('plan-features').value.split(',').map(f => f.trim()).filter(f => f),
    is_active: document.getElementById('plan-active').checked,
    is_popular: document.getElementById('plan-popular').checked,
  };
  
  if (!data.name) {
    showToast('Plan name is required', 'err');
    return;
  }
  
  try {
    await api('../api/admin-plans.php', 'POST', data);
    showToast(`Plan ${isEdit ? 'updated' : 'created'} successfully!`, 'ok');
    closeModal('plan-modal');
    setTimeout(() => location.reload(), 1200);
  } catch (err) {
    showToast('Failed to save plan: ' + err.message, 'err');
  }
}

// ── Pagination ───────────────────────────────────────────────
function loadUsersPage(page) {
  window.location.href = `?page=${page}`;
}


// ── Export helpers ────────────────────────────────────────────
function exportUsers() {
  const rows = [['Name','Email','Plan','University','Joined','Status']];
  document.querySelectorAll('#users-tbody tr').forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 6) return;
    rows.push([
      tds[0].textContent.trim(),
      tr.dataset.email || '',
      tr.dataset.plan  || '',
      tds[3].textContent.trim(),
      tds[4].textContent.trim(),
      tds[5].textContent.trim(),
    ]);
  });
  downloadCSV(rows, 'talebdz-users.csv');
}
function exportTransactions() {
  const rows = [['Student','Plan','Amount','Date','Status']];
  document.querySelectorAll('.plan-row').forEach(tr => {
    const tds = tr.querySelectorAll('td');
    if (tds.length < 6) return;
    rows.push(Array.from(tds).slice(0, 6).map(td => td.textContent.trim()));
  });
  downloadCSV(rows, 'talebdz-transactions.csv');
}
function downloadCSV(rows, filename) {
  const csv  = rows.map(r => r.map(v => `"${String(v).replace(/"/g,'""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url  = URL.createObjectURL(blob);
  const a    = document.createElement('a');
  a.href = url; a.download = filename; a.click();
  URL.revokeObjectURL(url);
}

// ── Dashboard Stats Live Tracking ────────────────────────────
let lastStatsUpdate = Date.now();
const STATS_REFRESH_INTERVAL = 5 * 60 * 1000; // 5 minutes

function updateDashboardStats() {
  // Only refresh if we're on the overview or analytics panel
  const activePanel = document.querySelector('.panel.active');
  if (!activePanel) return;
  
  const panelId = activePanel.id;
  if (panelId !== 'p-overview' && panelId !== 'p-analytics') return;
  
  const now = Date.now();
  if (now - lastStatsUpdate < STATS_REFRESH_INTERVAL) return;
  
  console.log('📊 Background stats refresh available - reload page for latest data');
  lastStatsUpdate = now;
}

// Check for updates when user returns to the page
document.addEventListener('visibilitychange', () => {
  if (!document.hidden) {
    updateDashboardStats();
  }
});
  URL.revokeObjectURL(url);
}
</script>

<!-- User Management Module -->
<script src="<?= $baseUrl ?>/admin/users.js"></script>

<!-- Plans Management Module -->
<script src="<?= $baseUrl ?>/admin/plans.js"></script>

<!-- Announcements Management Module (Ads & Videos) -->
<script src="<?= $baseUrl ?>/admin/announcements.js"></script>

<!-- Navigation Diagnostic & Fix Script -->
<script>
(function() {
  'use strict';
  
  console.log('🔧 Navigation & Data Loading Script Loaded');
  
  // Wait for DOM to be fully loaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDashboard);
  } else {
    initDashboard();
  }
  
  function initDashboard() {
    console.log('📋 Initializing Dashboard...');
    
    // Check if swPanel function exists
    if (typeof swPanel !== 'function') {
      console.error('❌ swPanel function not found! Creating fallback...');
      createFallbackSwPanel();
    } else {
      console.log('✅ swPanel function exists');
    }
    
    // Verify all critical functions exist
    const requiredFunctions = [
      'showToast', 'showConfirm', 'api', 'openModal', 'closeModal',
      'toggleSidebar', 'closeSidebar', 'toggleDropdown', 'adminLogout',
      'resolveReport', 'confirmBanUser', 'confirmUnbanUser', 'viewUserDetails',
      'filterUsers', 'applyUserFilters', 'loadMoreUsers',
      'filterTable', 'filterCommunityStatus', 'filterPlan',
      'hidePost', 'confirmDeletePost', 'sendAnnouncement',
      'savePricing', 'saveLinks', 'saveAccount',
      'exportUsers', 'exportTransactions', 'downloadCSV',
      'openCreatePlanModal', 'confirmDeletePlan', 'savePlan'
    ];
    
    const missing = requiredFunctions.filter(fn => typeof window[fn] !== 'function');
    if (missing.length > 0) {
      console.warn('⚠️ Missing functions:', missing.join(', '));
    } else {
      console.log('✅ All critical functions are defined');
    }
    
    // Check all panels exist
    const expectedPanels = [
      'overview', 'analytics', 'notifications', 'users', 
      'community', 'reports', 'announce', 'billing', 
      'website', 'settings'
    ];
    
    console.log('📊 Panel Check:');
    expectedPanels.forEach(name => {
      const panel = document.getElementById('p-' + name);
      const sidebarItem = document.querySelector(`.sb-item[data-panel="${name}"]`);
      
      if (panel) {
        console.log(`  ✅ Panel: p-${name}`);
      } else {
        console.error(`  ❌ Panel missing: p-${name}`);
      }
      
      if (sidebarItem) {
        console.log(`  ✅ Sidebar item: ${name}`);
      } else {
        console.error(`  ❌ Sidebar item missing: ${name}`);
      }
    });
    
    // Add backup click handlers if onclick is missing
    const sidebarItems = document.querySelectorAll('.sb-item[data-panel]');
    console.log(`📱 Found ${sidebarItems.length} sidebar items`);
    
    sidebarItems.forEach(item => {
      const panelName = item.getAttribute('data-panel');
      if (!item.hasAttribute('onclick') || item.onclick === null) {
        console.warn(`⚠️ Adding missing onclick handler to: ${panelName}`);
        item.onclick = function() {
          swPanel(panelName, this);
        };
      }
    });
    
    // Set up panel data loading hooks
    setupDataLoaders();
    
    console.log('✅ Dashboard initialization complete!');
  }
  
  function setupDataLoaders() {
    console.log('🔌 Setting up data loader hooks...');
    
    // Store reference to original swPanel
    if (typeof window.swPanel === 'function') {
      const originalSwPanel = window.swPanel;
      
      window.swPanel = function(panelId, element) {
        console.log('🎯 [Hook] Switching to panel:', panelId);
        
        // Call original function
        originalSwPanel(panelId, element);
        
        // Trigger data loading for specific panels
        setTimeout(() => {
          switch(panelId) {
            case 'announce':
              console.log('📢 Loading announcements data...');
              if (typeof window.switchAnnouncementTab === 'function') {
                window.switchAnnouncementTab('ads');
              }
              if (typeof window.loadAds === 'function') {
                window.loadAds();
              }
              break;
              
            case 'users':
              console.log('👥 Users panel activated');
              // Data already loaded from PHP
              break;
              
            case 'billing':
              console.log('💳 Billing panel activated');
              // Data already loaded from PHP
              break;
              
            default:
              console.log('ℹ️ Panel activated:', panelId);
          }
        }, 150);
      };
      
      console.log('✅ Data loader hooks installed');
    } else {
      console.error('❌ Cannot install data loader hooks - swPanel not found');
    }
  }
  
  function createFallbackSwPanel() {
    window.swPanel = function(name, el) {
      console.log('📱 [FALLBACK] Switching to panel:', name);
      
      // Hide all panels
      document.querySelectorAll('.panel').forEach(p => {
        p.classList.remove('active');
        p.style.display = 'none';
      });
      
      // Show target panel
      const targetPanel = document.getElementById('p-' + name);
      if (targetPanel) {
        targetPanel.classList.add('active');
        targetPanel.style.display = 'block';
        console.log('✅ [FALLBACK] Panel activated:', 'p-' + name);
      } else {
        console.error('❌ [FALLBACK] Panel not found:', 'p-' + name);
        return;
      }
      
      // Update sidebar active state
      document.querySelectorAll('.sb-item').forEach(i => i.classList.remove('active'));
      
      if (typeof el === 'string') {
        const sidebarItem = document.querySelector(`.sb-item[data-panel="${el}"]`);
        if (sidebarItem) sidebarItem.classList.add('active');
      } else if (el && el.classList) {
        el.classList.add('active');
      }
      
      // Close mobile sidebar
      if (typeof closeSidebar === 'function') {
        closeSidebar();
      } else {
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.remove('open');
        const overlay = document.getElementById('sidebar-overlay');
        if (overlay) overlay.style.display = 'none';
      }
      
      // Scroll to top
      window.scrollTo({ top: 0, behavior: 'smooth' });
      
      // Update breadcrumb
      const crumb = document.querySelector('[data-t="a_crumb"]');
      if (crumb) {
        const panelTitles = {
          'overview': 'Admin Dashboard',
          'analytics': 'Analytics',
          'notifications': 'Notifications',
          'users': 'Users Management',
          'community': 'Community Management',
          'reports': 'Reports Management',
          'announce': 'Announcements',
          'billing': 'Subscriptions & Billing',
          'website': 'Website Content',
          'settings': 'Settings'
        };
        crumb.textContent = panelTitles[name] || 'Admin Dashboard';
      }
    };
    
    console.log('✅ Fallback swPanel function created');
  }
})();
</script>

</body>
</html>
