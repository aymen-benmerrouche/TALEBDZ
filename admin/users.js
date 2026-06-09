/* ============================================================
   users.js — User Management Functions
   Handles: user filtering, ban/unban, view, export
   ============================================================ */

// ── Get CSRF token from meta tag ────────────────────────────
function getCSRFToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

// ── Show toast notification ─────────────────────────────────
function showToast(msg, type = 'ok') {
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `<i class="ti ti-${type === 'ok' ? 'check' : 'alert-triangle'}"></i> ${msg}`;
  document.body.appendChild(toast);

  setTimeout(() => toast.classList.add('show'), 10);
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 3500);
}

// ── Filter users table by search term ───────────────────────
let searchTimeout;
function filterUsers(query) {
  clearTimeout(searchTimeout);
  searchTimeout = setTimeout(() => {
    loadUsersWithFilters();
  }, 400);
}

// ── Apply user filters (plan type) ──────────────────────────
function applyUserFilters() {
  loadUsersWithFilters();
}

// ── Load users with current filters ─────────────────────────
async function loadUsersWithFilters(page = 1) {
  const search = document.getElementById('user-search')?.value || '';
  const plan = document.getElementById('user-type-filter')?.value || '';
  
  try {
    const params = new URLSearchParams({
      page: page.toString(),
      limit: '25',
      ...(search && { search }),
      ...(plan && { plan })
    });

    const response = await fetch(`../api/users.php?${params}`, {
      method: 'GET',
      headers: {
        'Content-Type': 'application/json'
      },
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();
    
    if (data.error) {
      showToast(data.error, 'err');
      return;
    }

    renderUsersTable(data);
  } catch (error) {
    console.error('Failed to load users:', error);
    showToast('Failed to load users', 'err');
  }
}

// ── Render users table ──────────────────────────────────────
function renderUsersTable(data) {
  const tbody = document.getElementById('users-tbody');
  if (!tbody) return;

  if (!data.data || data.data.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7"><div class="empty-state"><i class="ti ti-users-off"></i><p>No users found.</p></div></td></tr>';
    return;
  }

  tbody.innerHTML = data.data.map(user => {
    const initials = (user.username || user.email || 'U').charAt(0).toUpperCase();
    const planName = user.plan_name || 'Free';
    const badgeClass = planName === 'Free' ? '' : 'bdg-ok';
    const statusBadge = user.is_active ? 'bdg-ok' : 'bdg-err';
    const statusText = user.is_active ? 'Active' : 'Banned';
    const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : '—';

    return `
      <tr data-user-id="${escapeHtml(user.id)}" data-email="${escapeHtml(user.email)}">
        <td>
          <div style="display:flex;align-items:center;gap:.5rem">
            <div class="u-avatar">${initials}</div>
            ${escapeHtml(user.full_name || user.username || user.email)}
          </div>
        </td>
        <td style="color:var(--text3)">${escapeHtml(user.email)}</td>
        <td><span class="bdg ${badgeClass}">${escapeHtml(planName)}</span></td>
        <td style="color:var(--text3)">${escapeHtml(user.university || user.department || '—')}</td>
        <td style="color:var(--text3);font-size:.82rem">${createdDate}</td>
        <td><span class="bdg ${statusBadge}">${statusText}</span></td>
        <td>
          <div style="display:flex;gap:.4rem">
            ${user.is_active ? 
              `<button class="btn btn-sm btn-ghost" onclick="confirmBanUser('${escapeHtml(user.id)}','${escapeHtml(user.full_name || user.email)}')" title="Ban user">
                <i class="ti ti-ban"></i>
              </button>` :
              `<button class="btn btn-sm btn-ghost" onclick="confirmUnbanUser('${escapeHtml(user.id)}','${escapeHtml(user.full_name || user.email)}')" title="Unban user">
                <i class="ti ti-lock-open"></i>
              </button>`
            }
            <button class="btn btn-sm btn-ghost" onclick="viewUserDetails('${escapeHtml(user.id)}','${escapeHtml(user.full_name || user.email)}')" title="View details">
              <i class="ti ti-eye"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

// ── Escape HTML to prevent XSS ──────────────────────────────
function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

// ── Load users page (pagination) ────────────────────────────
function loadUsersPage(page) {
  loadUsersWithFilters(page);
}

// ── Refresh users list ──────────────────────────────────────
function loadMoreUsers() {
  loadUsersWithFilters(1);
  showToast('Users refreshed', 'ok');
}

// ── Confirm ban user ────────────────────────────────────────
function confirmBanUser(userId, userName) {
  if (confirm(`Ban user "${userName}"? They will lose access to the platform.`)) {
    toggleUserStatus(userId, false, userName);
  }
}

// ── Confirm unban user ──────────────────────────────────────
function confirmUnbanUser(userId, userName) {
  if (confirm(`Unban user "${userName}"? They will regain access to the platform.`)) {
    toggleUserStatus(userId, true, userName);
  }
}

// ── Toggle user active status (ban/unban) ───────────────────
async function toggleUserStatus(userId, setActive, userName = '') {
  try {
    const response = await fetch('../api/users.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCSRFToken()
      },
      credentials: 'same-origin',
      body: JSON.stringify({
        user_id: userId,
        action: setActive ? 'unban' : 'ban'
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.error) {
      showToast(data.error, 'err');
      return;
    }

    // Update the row in the table
    const row = document.querySelector(`tr[data-user-id="${userId}"]`);
    if (row) {
      const statusCell = row.querySelector('td:nth-child(6)');
      const actionsCell = row.querySelector('td:nth-child(7)');
      
      if (statusCell) {
        statusCell.innerHTML = setActive ? 
          '<span class="bdg bdg-ok">Active</span>' : 
          '<span class="bdg bdg-err">Banned</span>';
      }
      
      if (actionsCell) {
        const displayName = userName || userId;
        actionsCell.innerHTML = `
          <div style="display:flex;gap:.4rem">
            ${setActive ?
              `<button class="btn btn-sm btn-ghost" onclick="confirmBanUser('${userId}','${displayName}')" title="Ban user">
                <i class="ti ti-ban"></i>
              </button>` :
              `<button class="btn btn-sm btn-ghost" onclick="confirmUnbanUser('${userId}','${displayName}')" title="Unban user">
                <i class="ti ti-lock-open"></i>
              </button>`
            }
            <button class="btn btn-sm btn-ghost" onclick="viewUserDetails('${userId}','${displayName}')" title="View details">
              <i class="ti ti-eye"></i>
            </button>
          </div>
        `;
      }
    }

    showToast(`User ${setActive ? 'unbanned' : 'banned'} successfully`, 'ok');
  } catch (error) {
    console.error('Failed to toggle user status:', error);
    showToast('Failed to update user status', 'err');
  }
}

// ── View user details (modal or alert) ──────────────────────
function viewUserDetails(userId, userName) {
  // TODO: Implement modal with full user details
  alert(`User Details\n\nID: ${userId}\nName: ${userName}\n\n(Full details panel coming soon)`);
}

// ── Export users to CSV ─────────────────────────────────────
async function exportUsers() {
  try {
    const search = document.getElementById('user-search')?.value || '';
    const plan = document.getElementById('user-type-filter')?.value || '';
    
    const params = new URLSearchParams({
      export: 'csv',
      ...(search && { search }),
      ...(plan && { plan })
    });

    const response = await fetch(`../api/users.php?${params}`, {
      method: 'GET',
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const blob = await response.blob();
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    window.URL.revokeObjectURL(url);

    showToast('Users exported successfully', 'ok');
  } catch (error) {
    console.error('Failed to export users:', error);
    showToast('Failed to export users', 'err');
  }
}

// ── Dropdown toggle helper ─────────────────────────────────
function toggleDropdown(id) {
  const el = document.getElementById(id);
  if (!el) return;
  
  const isHidden = el.style.display === 'none' || !el.style.display;
  
  // Close all other dropdowns
  document.querySelectorAll('[id$="-dd"]').forEach(dd => {
    if (dd.id !== id) dd.style.display = 'none';
  });
  
  el.style.display = isHidden ? 'block' : 'none';
}

// Close dropdowns when clicking outside
document.addEventListener('click', (e) => {
  if (!e.target.closest('.admin-avatar') && !e.target.closest('[id$="-dd"]')) {
    document.querySelectorAll('[id$="-dd"]').forEach(dd => dd.style.display = 'none');
  }
});

// ── Admin logout ────────────────────────────────────────────
async function adminLogout() {
  if (!confirm('Are you sure you want to sign out?')) return;
  
  try {
    const response = await fetch('../admin/logout.php', {
      method: 'POST',
      credentials: 'same-origin'
    });

    if (response.ok) {
      window.location.href = '../admin/login.php';
    } else {
      throw new Error('Logout failed');
    }
  } catch (error) {
    console.error('Logout error:', error);
    // Force redirect anyway
    window.location.href = '../admin/login.php';
  }
}

console.log('[TalebDZ] User management module loaded');
