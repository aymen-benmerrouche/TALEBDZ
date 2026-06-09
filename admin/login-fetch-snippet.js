/* ============================================================
   login-fetch-snippet.js
   
   Replace the entire "Demo credentials" setTimeout block inside
   handleLogin() in login.html with this fetch() call.
   
   The function should look like this after the swap:
   ============================================================ */

function handleLogin(e) {
  e.preventDefault();
  hideError();

  const email    = document.getElementById('login-email').value.trim();
  const password = document.getElementById('login-password').value;
  const btn      = document.getElementById('login-btn');

  btn.classList.add('loading');

  fetch('/admin/auth.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',          // send/receive session cookie
    body: JSON.stringify({ email, password }),
  })
  .then(res => res.json())
  .then(data => {
    btn.classList.remove('loading');

    if (data.admin) {
      // Store CSRF token for subsequent admin API calls
      sessionStorage.setItem('talebdz_csrf', data.csrf_token || '');
      // Redirect to dashboard
      window.location.href = data.redirect || '/admin/index.php';
    } else {
      const msg = data.error || 'Invalid credentials.';
      const rem = data.attempts_remaining;
      showError(rem !== undefined ? `${msg} (${rem} attempt${rem !== 1 ? 's' : ''} left)` : msg);
    }
  })
  .catch(() => {
    btn.classList.remove('loading');
    showError('Server error. Please try again.');
  });
}

/* ============================================================
   LOGOUT — add to admin.html nav logout button:
   
   <button onclick="adminLogout()">Logout</button>
   
   And add this script anywhere in admin.html:
   ============================================================ */

function adminLogout() {
  const csrf = sessionStorage.getItem('talebdz_csrf') || '';
  fetch('/admin/logout.php', {
    method:  'POST',
    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrf },
    credentials: 'same-origin',
    body: JSON.stringify({ csrf_token: csrf }),
  })
  .then(r => r.json())
  .then(d => { window.location.href = d.redirect || '/admin/login.php'; })
  .catch(() => { window.location.href = '/admin/login.php'; });
}
