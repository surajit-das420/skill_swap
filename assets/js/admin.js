(function () {
  'use strict';

  // Enter Demo Admin button
  const demoAdminBtn = document.getElementById('enterDemoAdminBtn');
  if (demoAdminBtn) {
    demoAdminBtn.addEventListener('click', async function () {
      try {
        await window.SkillSwap.postJson('api/auth.php', { action: 'demo_admin' });
        window.location.reload();
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }

  // Style active admin tab pills nicely
  document.querySelectorAll('#adminTabs button[data-bs-toggle="tab"]').forEach((tabBtn) => {
    tabBtn.addEventListener('shown.bs.tab', function () {
      document.querySelectorAll('#adminTabs button').forEach((btn) => {
        const isActive = btn === tabBtn;
        btn.classList.toggle('btn-ss-primary', isActive);
        btn.classList.toggle('btn-ss-outline', !isActive);
      });
    });
  });

  // Search users table
  const userSearch = document.getElementById('adminUserSearch');
  if (userSearch) {
    userSearch.addEventListener('input', function () {
      const q = userSearch.value.trim().toLowerCase();
      document.querySelectorAll('#adminUsersTable tr').forEach((row) => {
        const hay = row.getAttribute('data-search') || '';
        row.style.display = hay.includes(q) ? '' : 'none';
      });
    });
  }

  // Filter requests table by status
  const reqFilter = document.getElementById('adminReqStatusFilter');
  if (reqFilter) {
    reqFilter.addEventListener('change', function () {
      const st = reqFilter.value;
      document.querySelectorAll('#adminRequestsTable tr').forEach((row) => {
        row.style.display = st === 'all' || row.getAttribute('data-status') === st ? '' : 'none';
      });
    });
  }

  // Delegated actions
  document.addEventListener('click', async function (e) {
    const statusBtn = e.target.closest('.admin-toggle-status');
    if (statusBtn) {
      const userId = Number(statusBtn.getAttribute('data-id') || 0);
      try {
        await window.SkillSwap.postJson('api/admin.php', { action: 'toggle_user_status', user_id: userId });
        window.SkillSwap.showToast('User status updated');
        setTimeout(() => window.location.reload(), 450);
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
      return;
    }

    const roleBtn = e.target.closest('.admin-toggle-role');
    if (roleBtn) {
      const userId = Number(roleBtn.getAttribute('data-id') || 0);
      try {
        await window.SkillSwap.postJson('api/admin.php', { action: 'toggle_user_role', user_id: userId });
        window.SkillSwap.showToast('User role updated');
        setTimeout(() => window.location.reload(), 450);
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
      return;
    }

    const delUserBtn = e.target.closest('.admin-delete-user');
    if (delUserBtn) {
      if (!window.confirm('Delete this user and their swaps/messages?')) return;
      const userId = Number(delUserBtn.getAttribute('data-id') || 0);
      try {
        await window.SkillSwap.postJson('api/admin.php', { action: 'delete_user', user_id: userId });
        document.querySelector(`[data-user-row="${userId}"]`)?.remove();
        window.SkillSwap.showToast('User deleted');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
      return;
    }

    const delReqBtn = e.target.closest('.admin-delete-req');
    if (delReqBtn) {
      const proposalId = Number(delReqBtn.getAttribute('data-id') || 0);
      try {
        await window.SkillSwap.postJson('api/admin.php', { action: 'delete_request', proposal_id: proposalId });
        document.querySelector(`[data-req-row="${proposalId}"]`)?.remove();
        window.SkillSwap.showToast('Exchange request removed');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
      return;
    }

    const delRevBtn = e.target.closest('.admin-delete-review');
    if (delRevBtn) {
      const reviewId = Number(delRevBtn.getAttribute('data-id') || 0);
      try {
        await window.SkillSwap.postJson('api/admin.php', { action: 'delete_review', review_id: reviewId });
        document.querySelector(`[data-review-row="${reviewId}"]`)?.remove();
        window.SkillSwap.showToast('Review removed');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    }
  });

  // Update request status from dropdown
  document.addEventListener('change', async function (e) {
    const selectEl = e.target.closest('.admin-req-status-select');
    if (!selectEl) return;
    const proposalId = Number(selectEl.getAttribute('data-id') || 0);
    const status = selectEl.value;
    try {
      await window.SkillSwap.postJson('api/admin.php', {
        action: 'update_request_status',
        proposal_id: proposalId,
        status: status,
      });
      const row = document.querySelector(`[data-req-row="${proposalId}"]`);
      if (row) {
        row.setAttribute('data-status', status);
        const badge = row.querySelector('.status-pill');
        if (badge) {
          badge.className = `status-pill status-${status}`;
          badge.textContent = status;
        }
      }
      window.SkillSwap.showToast(`Request updated to ${status}`);
    } catch (err) {
      window.SkillSwap.showToast(err.message);
    }
  });
})();
