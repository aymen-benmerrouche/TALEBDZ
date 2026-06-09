// ============================================================
// admin/announcements.js — Ads & Videos Management
// ============================================================

console.log('[Announcements.js] Loading module...');

// ── Global State ──────────────────────────────────────────────
let currentEditingAdId = null;
let currentEditingVideoId = null;
let adsCache = [];
let videosCache = [];

// ── Auth Fetch Helper ─────────────────────────────────────────
// Helper function for authenticated API requests
function authFetch(url, options = {}) {
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  
  const defaultOptions = {
    credentials: 'same-origin',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken,
      'X-Requested-With': 'XMLHttpRequest',
    }
  };
  
  // Merge options
  const mergedOptions = {
    ...defaultOptions,
    ...options,
    headers: {
      ...defaultOptions.headers,
      ...(options.headers || {})
    }
  };
  
  return fetch(url, mergedOptions);
}

// ── Tab Switching ─────────────────────────────────────────────
window.switchAnnouncementTab = function(tab) {
  console.log('[Announcements] Switching to tab:', tab);
  
  // Update tab buttons
  document.querySelectorAll('.tab-btn').forEach(btn => {
    btn.classList.toggle('active', btn.dataset.tab === tab);
  });
  
  // Update tab content
  document.querySelectorAll('.announcement-tab').forEach(content => {
    content.classList.toggle('active', content.id === `tab-${tab}`);
    content.style.display = content.id === `tab-${tab}` ? 'block' : 'none';
  });
  
  // Load data for the active tab
  if (tab === 'ads') {
    console.log('[Announcements] Tab switched to ads, calling loadAds()');
    loadAds();
  } else if (tab === 'videos') {
    console.log('[Announcements] Tab switched to videos, calling loadVideos()');
    loadVideos();
  }
};

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// ADS MANAGEMENT
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

async function loadAds() {
  console.log('[Announcements] Loading ads...');
  try {
    const res = await authFetch('../api/announcements.php/ads?include_inactive=true');
    if (!res.ok) {
      console.error('[Announcements] Failed to load ads:', res.status);
      throw new Error('Failed to load ads');
    }
    
    adsCache = await res.json();
    console.log('[Announcements] Loaded', adsCache.length, 'ads');
    renderAds(adsCache);
  } catch (err) {
    console.error('loadAds error:', err);
    showToast('Failed to load ads', 'err');
  }
}

// Make globally accessible
window.loadAds = loadAds;

function renderAds(ads) {
  const tbody = document.getElementById('ads-tbody');
  
  if (!ads || ads.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="ti ti-ad-off"></i>
            <p>No ads found. Create your first ad above.</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }
  
  tbody.innerHTML = ads.map(ad => {
    const startDate = new Date(ad.start_date);
    const endDate = new Date(ad.end_date);
    const now = new Date();
    
    let status = 'Scheduled';
    let statusClass = 'bdg-warn';
    
    if (!ad.is_active) {
      status = 'Inactive';
      statusClass = 'bdg-err';
    } else if (now >= startDate && now <= endDate) {
      status = 'Active';
      statusClass = 'bdg-ok';
    } else if (now > endDate) {
      status = 'Expired';
      statusClass = 'bdg-err';
    }
    
    // Support both impressions_count and views_count for backwards compatibility
    const viewCount = ad.impressions_count || ad.views_count || 0;
    
    return `
      <tr data-ad-id="${ad.id}">
        <td><strong>${escapeHtml(ad.title)}</strong></td>
        <td style="color:var(--text3);max-width:250px;overflow:hidden;text-overflow:ellipsis">
          ${escapeHtml(ad.description || '—')}
        </td>
        <td style="color:var(--text3);font-size:.85rem">
          ${formatDate(ad.start_date)}
        </td>
        <td style="color:var(--text3);font-size:.85rem">
          ${formatDate(ad.end_date)}
        </td>
        <td><span class="bdg ${statusClass}">${status}</span></td>
        <td style="color:var(--text3)">${viewCount}</td>
        <td>
          <div style="display:flex;gap:.5rem">
            <button class="btn-ico" onclick="editAd('${ad.id}')" title="Edit">
              <i class="ti ti-edit"></i>
            </button>
            <button class="btn-ico" onclick="toggleAdStatus('${ad.id}', ${ad.is_active})" 
                    title="${ad.is_active ? 'Deactivate' : 'Activate'}">
              <i class="ti ti-${ad.is_active ? 'eye-off' : 'eye'}"></i>
            </button>
            <button class="btn-ico" onclick="deleteAd('${ad.id}')" title="Delete" style="color:#f87171">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

async function saveAd() {
  console.log('[Announcements] Saving ad...');
  const title = document.getElementById('ad-title').value.trim();
  const description = document.getElementById('ad-description').value.trim();
  const driveUrl = document.getElementById('ad-drive-url').value.trim();
  const startDate = document.getElementById('ad-start-date').value;
  const endDate = document.getElementById('ad-end-date').value;
  const isActive = document.getElementById('ad-is-active').checked;
  
  // Validation
  if (!title || !driveUrl || !startDate || !endDate) {
    showToast('Please fill in all required fields', 'err');
    return;
  }
  
  if (new Date(startDate) > new Date(endDate)) {
    showToast('End date must be after start date', 'err');
    return;
  }
  
  try {
    const isEdit = !!currentEditingAdId;
    const url = isEdit 
      ? `../api/announcements.php/ads/${currentEditingAdId}`
      : '../api/announcements.php/ads';
    
    const method = isEdit ? 'PUT' : 'POST';
    
    const res = await authFetch(url, {
      method,
      body: JSON.stringify({
        title,
        description,
        drive_url: driveUrl, // ads table uses drive_url (not google_drive_url)
        start_date: startDate,
        end_date: endDate,
        is_active: isActive
      })
    });
    
    if (!res.ok) throw new Error('Failed to save ad');
    
    showToast(`Ad ${isEdit ? 'updated' : 'created'} successfully`, 'ok');
    resetAdForm();
    loadAds();
  } catch (err) {
    console.error('saveAd error:', err);
    showToast('Failed to save ad', 'err');
  }
}

// Make globally accessible
window.saveAd = saveAd;
window.editAd = editAd;
window.toggleAdStatus = toggleAdStatus;
window.deleteAd = deleteAd;

function editAd(id) {
  const ad = adsCache.find(a => a.id === id);
  if (!ad) return;
  
  currentEditingAdId = id;
  
  // Support both link_url and drive_url field names
  const driveUrl = ad.link_url || ad.drive_url || ad.google_drive_url || '';
  
  document.getElementById('ad-title').value = ad.title;
  document.getElementById('ad-description').value = ad.description || '';
  document.getElementById('ad-drive-url').value = driveUrl;
  document.getElementById('ad-start-date').value = formatDateForInput(ad.start_date);
  document.getElementById('ad-end-date').value = formatDateForInput(ad.end_date);
  document.getElementById('ad-is-active').checked = ad.is_active;
  
  // Update button text
  const btn = document.querySelector('#tab-ads .btn-grad');
  btn.innerHTML = '<i class="ti ti-check"></i> Update Ad';
  
  // Scroll to form
  document.querySelector('#tab-ads .settings-card').scrollIntoView({ behavior: 'smooth' });
}

function resetAdForm() {
  currentEditingAdId = null;
  document.getElementById('ad-title').value = '';
  document.getElementById('ad-description').value = '';
  document.getElementById('ad-drive-url').value = '';
  document.getElementById('ad-start-date').value = '';
  document.getElementById('ad-end-date').value = '';
  document.getElementById('ad-is-active').checked = true;
  
  const btn = document.querySelector('#tab-ads .btn-grad');
  btn.innerHTML = '<i class="ti ti-plus"></i> Add Ad';
}

async function toggleAdStatus(id, currentStatus) {
  try {
    const res = await authFetch(`../api/announcements.php/ads/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ is_active: !currentStatus })
    });
    
    if (!res.ok) throw new Error('Failed to toggle ad status');
    
    showToast(`Ad ${!currentStatus ? 'activated' : 'deactivated'}`, 'ok');
    loadAds();
  } catch (err) {
    console.error('toggleAdStatus error:', err);
    showToast('Failed to update ad status', 'err');
  }
}

async function deleteAd(id) {
  if (!confirm('Are you sure you want to delete this ad? This action cannot be undone.')) {
    return;
  }
  
  try {
    const res = await authFetch(`../api/announcements.php/ads/${id}`, {
      method: 'DELETE'
    });
    
    if (!res.ok) throw new Error('Failed to delete ad');
    
    showToast('Ad deleted successfully', 'ok');
    loadAds();
  } catch (err) {
    console.error('deleteAd error:', err);
    showToast('Failed to delete ad', 'err');
  }
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// VIDEOS MANAGEMENT
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

async function loadVideos() {
  try {
    const res = await authFetch('../api/announcements.php/videos?include_inactive=true');
    if (!res.ok) throw new Error('Failed to load videos');
    
    videosCache = await res.json();
    renderVideos(videosCache);
  } catch (err) {
    console.error('loadVideos error:', err);
    showToast('Failed to load videos', 'err');
  }
}

function renderVideos(videos) {
  const tbody = document.getElementById('videos-tbody');
  
  if (!videos || videos.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7">
          <div class="empty-state">
            <i class="ti ti-video-off"></i>
            <p>No videos found. Create your first video above.</p>
          </div>
        </td>
      </tr>
    `;
    return;
  }
  
  tbody.innerHTML = videos.map(video => {
    const statusClass = video.is_active ? 'bdg-ok' : 'bdg-err';
    const status = video.is_active ? 'Active' : 'Inactive';
    
    // Parse tags (PostgreSQL array format: {tag1,tag2})
    let tags = [];
    if (video.tags) {
      const tagsStr = video.tags.toString();
      tags = tagsStr.replace(/[{}]/g, '').split(',').filter(t => t.trim());
    }
    
    return `
      <tr data-video-id="${video.id}">
        <td><strong>${escapeHtml(video.title)}</strong></td>
        <td style="color:var(--text3);max-width:250px;overflow:hidden;text-overflow:ellipsis">
          ${escapeHtml(video.description || '—')}
        </td>
        <td style="color:var(--text3)">${escapeHtml(video.category || '—')}</td>
        <td style="color:var(--text3);font-size:.8rem">
          ${tags.length > 0 ? tags.slice(0, 3).map(t => `<span class="bdg bdg-inf">${escapeHtml(t)}</span>`).join(' ') : '—'}
        </td>
        <td><span class="bdg ${statusClass}">${status}</span></td>
        <td style="color:var(--text3)">${video.views_count || 0}</td>
        <td>
          <div style="display:flex;gap:.5rem">
            <button class="btn-ico" onclick="editVideo('${video.id}')" title="Edit">
              <i class="ti ti-edit"></i>
            </button>
            <button class="btn-ico" onclick="toggleVideoStatus('${video.id}', ${video.is_active})" 
                    title="${video.is_active ? 'Deactivate' : 'Activate'}">
              <i class="ti ti-${video.is_active ? 'eye-off' : 'eye'}"></i>
            </button>
            <button class="btn-ico" onclick="deleteVideo('${video.id}')" title="Delete" style="color:#f87171">
              <i class="ti ti-trash"></i>
            </button>
          </div>
        </td>
      </tr>
    `;
  }).join('');
}

async function saveVideo() {
  const title = document.getElementById('video-title').value.trim();
  const description = document.getElementById('video-description').value.trim();
  const driveUrl = document.getElementById('video-drive-url').value.trim();
  const category = document.getElementById('video-category').value.trim();
  const tagsStr = document.getElementById('video-tags').value.trim();
  const isActive = document.getElementById('video-is-active').checked;
  
  // Validation
  if (!title || !driveUrl) {
    showToast('Please fill in title and drive URL', 'err');
    return;
  }
  
  // Parse tags
  const tags = tagsStr ? tagsStr.split(',').map(t => t.trim()).filter(t => t) : [];
  
  try {
    const isEdit = !!currentEditingVideoId;
    const url = isEdit 
      ? `../api/announcements.php/videos/${currentEditingVideoId}`
      : '../api/announcements.php/videos';
    
    const method = isEdit ? 'PUT' : 'POST';
    
    const res = await authFetch(url, {
      method,
      body: JSON.stringify({
        title,
        description,
        google_drive_url: driveUrl, // Changed from drive_url to google_drive_url to match database schema
        category: category || null,
        tags,
        is_active: isActive
      })
    });
    
    if (!res.ok) throw new Error('Failed to save video');
    
    showToast(`Video ${isEdit ? 'updated' : 'created'} successfully`, 'ok');
    resetVideoForm();
    loadVideos();
  } catch (err) {
    console.error('saveVideo error:', err);
    showToast('Failed to save video', 'err');
  }
}

function editVideo(id) {
  const video = videosCache.find(v => v.id === id);
  if (!video) return;
  
  currentEditingVideoId = id;
  
  document.getElementById('video-title').value = video.title;
  document.getElementById('video-description').value = video.description || '';
  document.getElementById('video-drive-url').value = video.google_drive_url || video.drive_url || ''; // Support both field names
  document.getElementById('video-category').value = video.category || '';
  
  // Parse tags
  let tags = [];
  if (video.tags) {
    const tagsStr = video.tags.toString();
    tags = tagsStr.replace(/[{}]/g, '').split(',').filter(t => t.trim());
  }
  document.getElementById('video-tags').value = tags.join(', ');
  
  document.getElementById('video-is-active').checked = video.is_active;
  
  // Update button text
  const btn = document.querySelector('#tab-videos .btn-grad');
  btn.innerHTML = '<i class="ti ti-check"></i> Update Video';
  
  // Scroll to form
  document.querySelector('#tab-videos .settings-card').scrollIntoView({ behavior: 'smooth' });
}

function resetVideoForm() {
  currentEditingVideoId = null;
  document.getElementById('video-title').value = '';
  document.getElementById('video-description').value = '';
  document.getElementById('video-drive-url').value = '';
  document.getElementById('video-category').value = '';
  document.getElementById('video-tags').value = '';
  document.getElementById('video-is-active').checked = true;
  
  const btn = document.querySelector('#tab-videos .btn-grad');
  btn.innerHTML = '<i class="ti ti-plus"></i> Add Video';
}

async function toggleVideoStatus(id, currentStatus) {
  try {
    const res = await authFetch(`../api/announcements.php/videos/${id}`, {
      method: 'PUT',
      body: JSON.stringify({ is_active: !currentStatus })
    });
    
    if (!res.ok) throw new Error('Failed to toggle video status');
    
    showToast(`Video ${!currentStatus ? 'activated' : 'deactivated'}`, 'ok');
    loadVideos();
  } catch (err) {
    console.error('toggleVideoStatus error:', err);
    showToast('Failed to update video status', 'err');
  }
}

async function deleteVideo(id) {
  if (!confirm('Are you sure you want to delete this video? This action cannot be undone.')) {
    return;
  }
  
  try {
    const res = await authFetch(`../api/announcements.php/videos/${id}`, {
      method: 'DELETE'
    });
    
    if (!res.ok) throw new Error('Failed to delete video');
    
    showToast('Video deleted successfully', 'ok');
    loadVideos();
  } catch (err) {
    console.error('deleteVideo error:', err);
    showToast('Failed to delete video', 'err');
  }
}

// Make globally accessible
window.loadVideos = loadVideos;
window.saveVideo = saveVideo;
window.editVideo = editVideo;
window.toggleVideoStatus = toggleVideoStatus;
window.deleteVideo = deleteVideo;

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// HELPER FUNCTIONS
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

function escapeHtml(text) {
  const div = document.createElement('div');
  div.textContent = text;
  return div.innerHTML;
}

function formatDate(dateStr) {
  if (!dateStr) return '—';
  const date = new Date(dateStr);
  return date.toLocaleDateString('en-US', { 
    year: 'numeric', 
    month: 'short', 
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
}

function formatDateForInput(dateStr) {
  if (!dateStr) return '';
  const date = new Date(dateStr);
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  const hours = String(date.getHours()).padStart(2, '0');
  const minutes = String(date.getMinutes()).padStart(2, '0');
  return `${year}-${month}-${day}T${hours}:${minutes}`;
}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// INITIALIZATION
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  console.log('[Announcements] Module loaded and DOM ready');
  
  // Check if we're on the announcements panel initially
  const announcePanel = document.getElementById('p-announce');
  if (announcePanel) {
    console.log('[Announcements] Announcement panel found in DOM');
    if (announcePanel.classList.contains('active')) {
      console.log('[Announcements] Panel is active on load, loading ads');
      loadAds();
    }
  } else {
    console.error('[Announcements] ERROR: Announcement panel #p-announce not found in DOM!');
  }
  
  // Also set up the hook immediately
  setupPanelHook();
});

// Set up hook for panel switches
function setupPanelHook() {
  console.log('[Announcements] Setting up panel switch hook...');
  
  // Store reference to original swPanel if it exists
  if (typeof window.swPanel === 'function') {
    console.log('[Announcements] Found existing swPanel function, hooking into it');
    const originalSwPanel = window.swPanel;
    
    window.swPanel = function(panelId, element) {
      console.log('[Announcements Hook] swPanel called with panelId:', panelId);
      
      // Call original function first
      originalSwPanel(panelId, element);
      
      // If switching to announcements panel, load ads
      if (panelId === 'announce') {
        console.log('[Announcements] Panel activated via swPanel, loading ads in 100ms');
        setTimeout(() => {
          console.log('[Announcements] Executing loadAds...');
          loadAds();
        }, 100);
      }
    };
    console.log('[Announcements] Hook successfully installed');
  } else {
    console.warn('[Announcements] WARNING: swPanel function not found yet, will retry in 500ms');
    // If swPanel doesn't exist yet, try again in 500ms
    setTimeout(setupPanelHook, 500);
  }
}

