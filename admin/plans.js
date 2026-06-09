/* ============================================================
   plans.js — Subscription Plans Management
   Handles: create, edit, delete subscription plans
   ============================================================ */

/**
 * Get CSRF token from meta tag
 */
function getCsrfToken() {
  return document.querySelector('meta[name="csrf-token"]')?.content || '';
}

/**
 * Show toast notification
 */
function showToast(message, type = 'ok') {
  // Remove any existing toast
  const existing = document.querySelector('.toast');
  if (existing) existing.remove();

  const toast = document.createElement('div');
  toast.className = `toast toast-${type}`;
  toast.innerHTML = `
    <i class="ti ti-${type === 'ok' ? 'check' : 'alert-circle'}"></i>
    <span>${message}</span>
  `;
  document.body.appendChild(toast);

  // Trigger animation
  setTimeout(() => toast.classList.add('show'), 10);

  // Auto-remove after 4 seconds
  setTimeout(() => {
    toast.classList.remove('show');
    setTimeout(() => toast.remove(), 300);
  }, 4000);
}

/**
 * Open modal to create a new plan
 */
function openCreatePlanModal() {
  document.getElementById('plan-modal-title').textContent = 'Create Subscription Plan';
  document.getElementById('plan-save-text').textContent = 'Create Plan';
  document.getElementById('plan-id').value = '';
  document.getElementById('plan-form').reset();
  document.getElementById('plan-active').checked = true;
  openModal('plan-modal');
}

/**
 * Open modal to edit an existing plan
 */
async function openEditPlanModal(planId) {
  console.log('[Plans] Opening edit modal for plan:', planId);
  
  if (!planId) {
    showToast('Invalid plan ID', 'err');
    return;
  }

  try {
    // Fetch plan details
    const url = `../api/admin-plans.php?action=get&plan_id=${encodeURIComponent(planId)}`;
    console.log('[Plans] Fetching plan from:', url);
    
    const response = await fetch(url, {
      headers: {
        'X-CSRF-Token': getCsrfToken()
      }
    });

    console.log('[Plans] Response status:', response.status);
    const data = await response.json();
    console.log('[Plans] Response data:', data);

    if (!response.ok || !data.success) {
      const errorMsg = data.error || 'Failed to load plan details';
      console.error('[Plans] Error:', errorMsg);
      showToast(errorMsg, 'err');
      return;
    }

    const plan = data.data;
    console.log('[Plans] Plan data:', plan);

    if (!plan) {
      showToast('Plan not found', 'err');
      return;
    }

    // Populate form
    document.getElementById('plan-modal-title').textContent = 'Edit Subscription Plan';
    document.getElementById('plan-save-text').textContent = 'Update Plan';
    document.getElementById('plan-id').value = plan.id || '';
    document.getElementById('plan-name').value = plan.name || '';
    document.getElementById('plan-code').value = plan.plan_code || '';
    document.getElementById('plan-desc').value = plan.description || '';
    document.getElementById('plan-price').value = parseFloat(plan.price) || 0;
    document.getElementById('plan-currency').value = plan.currency || 'DZD';
    document.getElementById('plan-duration').value = parseInt(plan.duration_months) || 1;
    document.getElementById('plan-order').value = parseInt(plan.display_order) || 0;
    
    // Handle features - can be array, JSON string, or comma-separated string
    let features = plan.features || [];
    if (typeof features === 'string') {
      try {
        // Try to parse as JSON first
        features = JSON.parse(features);
      } catch (e) {
        // If not JSON, treat as comma-separated string
        features = features.split(',').map(f => f.trim()).filter(f => f);
      }
    }
    document.getElementById('plan-features').value = Array.isArray(features) ? features.join(', ') : '';
    
    // Handle boolean values - PostgreSQL may return 't'/'f' or true/false
    const isActive = plan.is_active === true || plan.is_active === 't' || plan.is_active === 1;
    const isPopular = plan.is_popular === true || plan.is_popular === 't' || plan.is_popular === 1;
    
    document.getElementById('plan-active').checked = isActive;
    document.getElementById('plan-popular').checked = isPopular;

    console.log('[Plans] Form populated, opening modal');
    openModal('plan-modal');

  } catch (error) {
    console.error('[Plans] Error loading plan:', error);
    showToast('Network error loading plan: ' + error.message, 'err');
  }
}

/**
 * Save plan (create or update)
 */
async function savePlan(event) {
  event.preventDefault();

  const planId = document.getElementById('plan-id').value;
  const isEdit = !!planId;

  const planData = {
    action: isEdit ? 'update' : 'create',
    name: document.getElementById('plan-name').value.trim(),
    plan_code: document.getElementById('plan-code').value.trim() || undefined,
    description: document.getElementById('plan-desc').value.trim() || null,
    price: parseFloat(document.getElementById('plan-price').value) || 0,
    currency: document.getElementById('plan-currency').value || 'DZD',
    duration_months: parseInt(document.getElementById('plan-duration').value) || 1,
    display_order: parseInt(document.getElementById('plan-order').value) || 0,
    features: document.getElementById('plan-features').value.split(',').map(f => f.trim()).filter(f => f),
    is_active: document.getElementById('plan-active').checked,
    is_popular: document.getElementById('plan-popular').checked
  };

  if (isEdit) {
    planData.plan_id = planId;
  }

  // Validate
  if (!planData.name) {
    showToast('Plan name is required', 'err');
    return;
  }

  if (planData.price < 0) {
    showToast('Price must be 0 or greater', 'err');
    return;
  }

  if (planData.duration_months < 1) {
    showToast('Duration must be at least 1 month', 'err');
    return;
  }

  // Disable submit button
  const saveBtn = document.getElementById('plan-save-btn');
  const originalText = saveBtn.innerHTML;
  saveBtn.disabled = true;
  saveBtn.innerHTML = '<i class="ti ti-loader"></i> Saving...';

  try {
    const response = await fetch('../api/admin-plans.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify(planData)
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      showToast(data.error || 'Failed to save plan', 'err');
      saveBtn.disabled = false;
      saveBtn.innerHTML = originalText;
      return;
    }

    showToast(isEdit ? 'Plan updated successfully' : 'Plan created successfully', 'ok');
    closeModal('plan-modal');
    
    // Reload the page to show updated plans
    setTimeout(() => {
      window.location.reload();
    }, 500);

  } catch (error) {
    console.error('Error saving plan:', error);
    showToast('Network error while saving plan', 'err');
    saveBtn.disabled = false;
    saveBtn.innerHTML = originalText;
  }
}

/**
 * Confirm plan deletion
 */
function confirmDeletePlan(planId, planName) {
  if (!planId) {
    showToast('Invalid plan ID', 'err');
    return;
  }

  const confirmed = confirm(
    `Are you sure you want to delete the plan "${planName}"?\n\n` +
    `This will soft-delete the plan (set as inactive). ` +
    `Users with existing subscriptions will not be affected.\n\n` +
    `Click OK to continue.`
  );

  if (confirmed) {
    deletePlan(planId);
  }
}

/**
 * Delete plan (soft delete)
 */
async function deletePlan(planId) {
  if (!planId) {
    showToast('Invalid plan ID', 'err');
    return;
  }

  try {
    const response = await fetch('../api/admin-plans.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify({
        action: 'delete',
        plan_id: planId
      })
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      showToast(data.error || 'Failed to delete plan', 'err');
      return;
    }

    showToast('Plan deleted successfully', 'ok');
    
    // Remove the row from table
    const row = document.querySelector(`tr[data-plan-id="${planId}"]`);
    if (row) {
      row.style.opacity = '0.5';
      row.style.transition = 'opacity 0.3s';
      setTimeout(() => {
        window.location.reload();
      }, 500);
    }

  } catch (error) {
    console.error('Error deleting plan:', error);
    showToast('Network error while deleting plan', 'err');
  }
}

/**
 * Permanently delete plan (with confirmation)
 */
function confirmPermanentDeletePlan(planId, planName) {
  if (!planId) {
    showToast('Invalid plan ID', 'err');
    return;
  }

  const confirmed = confirm(
    `⚠️ PERMANENT DELETE WARNING ⚠️\n\n` +
    `You are about to PERMANENTLY delete the plan "${planName}".\n\n` +
    `This action:\n` +
    `• Cannot be undone\n` +
    `• Will fail if any users have subscriptions to this plan\n` +
    `• Will remove all plan data from the database\n\n` +
    `Are you absolutely sure?`
  );

  if (confirmed) {
    const doubleConfirm = confirm(
      `Final confirmation: Type the plan name to confirm deletion.\n\n` +
      `Expected: "${planName}"\n\n` +
      `Press OK to continue with permanent deletion.`
    );

    if (doubleConfirm) {
      permanentDeletePlan(planId);
    }
  }
}

/**
 * Permanently delete plan
 */
async function permanentDeletePlan(planId) {
  if (!planId) {
    showToast('Invalid plan ID', 'err');
    return;
  }

  try {
    const response = await fetch('../api/admin-plans.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': getCsrfToken()
      },
      body: JSON.stringify({
        action: 'delete_permanent',
        plan_id: planId
      })
    });

    const data = await response.json();

    if (!response.ok || !data.success) {
      showToast(data.error || 'Failed to delete plan permanently', 'err');
      return;
    }

    showToast('Plan permanently deleted', 'ok');
    
    // Reload page
    setTimeout(() => {
      window.location.reload();
    }, 500);

  } catch (error) {
    console.error('Error permanently deleting plan:', error);
    showToast('Network error while deleting plan', 'err');
  }
}

/**
 * Filter plans table by plan code
 */
function filterPlan(button, planCode) {
  // Update active tab
  document.querySelectorAll('.plan-tab').forEach(tab => tab.classList.remove('active'));
  button.classList.add('active');

  // Filter table rows in plans table
  const plansTable = document.getElementById('plans-tbody');
  if (plansTable) {
    const rows = plansTable.querySelectorAll('tr');
    rows.forEach(row => {
      const rowPlanCode = row.querySelector('code')?.textContent?.trim();
      if (planCode === 'all' || rowPlanCode === planCode) {
        row.style.display = '';
      } else {
        row.style.display = 'none';
      }
    });
  }

  // Filter transaction rows
  const txnRows = document.querySelectorAll('.plan-row');
  txnRows.forEach(row => {
    const rowPlan = row.dataset.plan;
    if (planCode === 'all' || rowPlan === planCode) {
      row.style.display = '';
    } else {
      row.style.display = 'none';
    }
  });
}

/**
 * Export transactions to CSV
 */
function exportTransactions() {
  showToast('Transaction export feature coming soon', 'ok');
  // TODO: Implement CSV export
}

/**
 * Initialize plans management
 */
document.addEventListener('DOMContentLoaded', () => {
  console.log('[Plans] Plans management initialized');
});
