<?php
// ============================================================
// admin/login.php — TalebDZ Admin Login Page
// ============================================================
declare(strict_types=1);

$_rootDir = dirname(__DIR__);
require_once $_rootDir . '/db/config.php';
require_once __DIR__ . '/auth.php';

// Base URL for links
$baseUrl = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');

// Redirect if already logged in
if (auth_isAuthenticated()) {
    $redirect = $_GET['redirect'] ?? './index.php';
    header("Location: {$redirect}");
    exit;
}

$loggedOut = isset($_GET['logged_out']);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark" dir="ltr">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — TalebDZ</title>
  <meta name="robots" content="noindex, nofollow"/>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:opsz,wght@9..40,300;9..40,400;9..40,500;9..40,600&display=swap"
        rel="stylesheet"/>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.11.0/dist/tabler-icons.min.css" crossorigin/>
  <link rel="stylesheet" href="<?= $baseUrl ?>/style.css"/>
  <link rel="icon" type="image/jpeg" href="<?= $baseUrl ?>/photos/logo.jpg"/>
  <style>
    body {
      font-family: 'DM Sans', sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      background: var(--bg);
      padding: 1.5rem;
    }
    .login-box {
      width: 100%;
      max-width: 420px;
      background: var(--card);
      border: 1px solid var(--border);
      border-radius: 18px;
      padding: 2.5rem 2rem;
      box-shadow: 0 12px 48px rgba(0,0,0,.15);
    }
    .login-logo {
      text-align: center;
      margin-bottom: 1.5rem;
    }
    .login-logo img {
      width: 64px;
      height: 64px;
      border-radius: 50%;
      margin-bottom: .75rem;
    }
    .login-logo h1 {
      font-family: 'Syne', sans-serif;
      font-size: 1.6rem;
      font-weight: 800;
      margin-bottom: .25rem;
      background: var(--grad);
      -webkit-background-clip: text;
      background-clip: text;
      -webkit-text-fill-color: transparent;
    }
    .login-logo p {
      font-size: .875rem;
      color: var(--text3);
    }
    .form-group {
      margin-bottom: 1.25rem;
    }
    label {
      display: block;
      font-size: .82rem;
      font-weight: 500;
      color: var(--text2);
      margin-bottom: .45rem;
    }
    input[type="email"],
    input[type="password"] {
      width: 100%;
      padding: .8rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border2);
      border-radius: 10px;
      color: var(--text);
      font-size: .875rem;
      outline: none;
      transition: border-color .15s;
    }
    input:focus {
      border-color: rgba(59,130,246,.5);
    }
    .btn-login {
      width: 100%;
      padding: .85rem;
      background: var(--grad);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-size: .9rem;
      font-weight: 600;
      cursor: pointer;
      transition: opacity .15s;
      font-family: 'DM Sans', sans-serif;
    }
    .btn-login:hover {
      opacity: .9;
    }
    .btn-login:disabled {
      opacity: .5;
      cursor: not-allowed;
    }
    .error-msg {
      background: rgba(248,113,113,.12);
      border: 1px solid rgba(248,113,113,.3);
      color: #f87171;
      padding: .75rem 1rem;
      border-radius: 10px;
      font-size: .82rem;
      margin-bottom: 1.25rem;
      display: none;
    }
    .error-msg.show {
      display: block;
    }
    .success-msg {
      background: rgba(52,211,153,.12);
      border: 1px solid rgba(52,211,153,.3);
      color: #34d399;
      padding: .75rem 1rem;
      border-radius: 10px;
      font-size: .82rem;
      margin-bottom: 1.25rem;
    }
    .back-link {
      text-align: center;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
    }
    .back-link a {
      color: var(--text2);
      font-size: .82rem;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: .4rem;
      transition: color .15s;
    }
    .back-link a:hover {
      color: var(--text);
    }
  </style>
</head>
<body>

<div class="login-box">
  <div class="login-logo">
    <img src="<?= $baseUrl ?>/photos/logo.jpg" alt="TalebDZ Logo"/>
    <h1>TalebDZ</h1>
    <p>Admin Dashboard</p>
  </div>

  <?php if ($loggedOut): ?>
  <div class="success-msg">
    You have been logged out successfully.
  </div>
  <?php endif; ?>

  <div class="error-msg" id="error-msg"></div>

  <form id="login-form">
    <div class="form-group">
      <label for="email">Email Address</label>
      <input type="email" id="email" name="email" required autofocus autocomplete="email"/>
    </div>

    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required autocomplete="current-password"/>
    </div>

    <button type="submit" class="btn-login" id="submit-btn">
      Sign In
    </button>
  </form>

  <div class="back-link">
    <a href="<?= $baseUrl ?>/index.php">
      <i class="ti ti-arrow-left"></i>
      Back to Website
    </a>
  </div>
</div>

<script>
const form = document.getElementById('login-form');
const errorMsg = document.getElementById('error-msg');
const submitBtn = document.getElementById('submit-btn');

form.addEventListener('submit', async (e) => {
  e.preventDefault();
  
  const email = document.getElementById('email').value;
  const password = document.getElementById('password').value;
  
  // Clear previous errors
  errorMsg.textContent = '';
  errorMsg.classList.remove('show');
  
  // Disable submit button
  submitBtn.disabled = true;
  submitBtn.textContent = 'Signing in...';
  
  try {
    const response = await fetch('auth-api.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ email, password })
    });
    
    // Get response text first (can only read once)
    const responseText = await response.text();
    
    // Check if response is OK
    if (!response.ok && response.status !== 401 && response.status !== 400 && response.status !== 429) {
      console.error('HTTP Error:', response.status, response.statusText);
      console.error('Response:', responseText);
      throw new Error(`Server error (${response.status}). Check console for details.`);
    }
    
    // Try to parse JSON from the text
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (jsonError) {
      console.error('JSON parse error:', jsonError);
      console.error('Response text:', responseText);
      throw new Error('Invalid response from server. Check browser console for details.');
    }
    
    if (response.ok && data.admin) {
      // Success - redirect to dashboard
      window.location.href = data.redirect || 'index.php';
    } else {
      // Error
      errorMsg.textContent = data.error || 'Login failed. Please try again.';
      if (data.attempts_remaining !== undefined) {
        errorMsg.textContent += ` (${data.attempts_remaining} attempts remaining)`;
      }
      errorMsg.classList.add('show');
      submitBtn.disabled = false;
      submitBtn.textContent = 'Sign In';
    }
  } catch (error) {
    console.error('Login error:', error);
    errorMsg.textContent = error.message || 'Network error. Please check your connection.';
    errorMsg.classList.add('show');
    submitBtn.disabled = false;
    submitBtn.textContent = 'Sign In';
  }
});
</script>

</body>
</html>
