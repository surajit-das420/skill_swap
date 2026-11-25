(function () {
  'use strict';

  window.SkillSwap = window.SkillSwap || {};

  window.SkillSwap.showToast = function (message) {
    const toastEl = document.getElementById('ssToast');
    const bodyEl = document.getElementById('ssToastBody');
    if (!toastEl || !bodyEl || typeof bootstrap === 'undefined') return;
    bodyEl.textContent = message;
    bootstrap.Toast.getOrCreateInstance(toastEl, { delay: 2800 }).show();
  };

  window.SkillSwap.escapeHtml = function (str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  };

  window.SkillSwap.postJson = async function (url, data) {
    const response = await fetch(url, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data),
    });
    const payload = await response.json();
    if (!response.ok || !payload.ok) {
      throw new Error(payload.error || 'Request failed');
    }
    return payload;
  };

  let activeModalUser = null;

  function renderProfileReviews(reviews) {
    const esc = window.SkillSwap.escapeHtml;
    const container = document.getElementById('modalProfileReviews');
    const countEl = document.getElementById('modalProfileReviewCount');
    if (!container) return;

    if (countEl) {
      countEl.textContent = reviews.length ? `${reviews.length} recent review(s)` : 'No reviews yet';
    }

    if (!reviews.length) {
      container.innerHTML = `<div class="text-muted small py-2">No reviews yet. Complete a swap and be the first to leave one!</div>`;
      return;
    }

    container.innerHTML = reviews
      .map(
        (r) => `
        <div class="review-item">
          <div class="d-flex align-items-center justify-content-between mb-1">
            <div class="d-flex align-items-center gap-2">
              <span class="fw-semibold small">${esc(r.reviewer_name)}</span>
              ${r.skill_practiced ? `<span class="pill-teach py-0 px-2" style="font-size: 0.72rem;">${esc(r.skill_practiced)}</span>` : ''}
            </div>
            <span class="rating-badge">${'★'.repeat(Number(r.rating || 5))}</span>
          </div>
          <div class="text-secondary small">${esc(r.comment)}</div>
        </div>`
      )
      .join('');
  }

  function openExchangeRequestModal(user) {
    if (!user) return;
    document.getElementById('reqPartnerId').value = user.id || '';
    document.getElementById('exchangeRequestSubtitle').textContent = `Propose a 1-on-1 skill swap with ${user.name}`;
    document.getElementById('reqLearnSkill').value = user.primary_teach || (user.teach_skills && user.teach_skills[0]) || '';
    document.getElementById('reqTeachSkill').value = user.primary_wants || (user.learn_skills && user.learn_skills[0]) || '';
    document.getElementById('reqNote').value = '';

    const profileEl = document.getElementById('partnerProfileModal');
    if (profileEl && typeof bootstrap !== 'undefined') {
      const inst = bootstrap.Modal.getInstance(profileEl);
      if (inst) inst.hide();
    }

    const reqEl = document.getElementById('exchangeRequestModal');
    if (reqEl && typeof bootstrap !== 'undefined') {
      bootstrap.Modal.getOrCreateInstance(reqEl).show();
    }
  }

  function openReviewModal(opts) {
    document.getElementById('revRevieweeId').value = opts.revieweeId || '';
    document.getElementById('revProposalId').value = opts.proposalId || '';
    document.getElementById('reviewModalSubtitle').textContent = `Share feedback for ${opts.revieweeName || 'your swap partner'}`;
    document.getElementById('revSkill').value = opts.skill || '';
    document.getElementById('revComment').value = '';
    const star5 = document.getElementById('star5');
    if (star5) star5.checked = true;

    const profileEl = document.getElementById('partnerProfileModal');
    if (profileEl && typeof bootstrap !== 'undefined') {
      const inst = bootstrap.Modal.getInstance(profileEl);
      if (inst) inst.hide();
    }

    const revEl = document.getElementById('reviewModal');
    if (revEl && typeof bootstrap !== 'undefined') {
      bootstrap.Modal.getOrCreateInstance(revEl).show();
    }
  }

  // Delegated click handlers
  document.addEventListener('click', async function (event) {
    const viewBtn = event.target.closest('.view-profile-btn');
    if (viewBtn) {
      let user;
      try {
        user = JSON.parse(viewBtn.getAttribute('data-user') || '{}');
      } catch {
        return;
      }
      activeModalUser = user;

      const avatar = document.getElementById('modalProfileAvatar');
      const name = document.getElementById('modalProfileName');
      const meta = document.getElementById('modalProfileMeta');
      const bio = document.getElementById('modalProfileBio');
      const teach = document.getElementById('modalProfileTeach');
      const learn = document.getElementById('modalProfileLearn');
      const ratingEl = document.getElementById('modalProfileRating');
      const chatBtn = document.getElementById('modalProfileChatBtn');

      if (avatar) {
        avatar.textContent = user.initials || 'SS';
        avatar.style.backgroundColor = user.avatar_color || '#503CE8';
      }
      if (name) name.textContent = user.name || 'Member';
      if (meta) meta.textContent = user.availability || '';
      if (bio) bio.textContent = user.bio || 'Ready to collaborate on a 1-on-1 skill swap.';
      if (teach) {
        teach.innerHTML = (user.teach_skills || [])
          .map((s) => `<span class="pill-teach">${window.SkillSwap.escapeHtml(s)}</span>`)
          .join('');
      }
      if (learn) {
        learn.innerHTML = (user.learn_skills || [])
          .map((s) => `<span class="pill-learn">${window.SkillSwap.escapeHtml(s)}</span>`)
          .join('');
      }
      if (ratingEl) {
        if (Number(user.review_count || 0) > 0) {
          ratingEl.textContent = `★ ${Number(user.avg_rating).toFixed(1)} (${user.review_count})`;
          ratingEl.classList.remove('d-none');
        } else {
          ratingEl.classList.add('d-none');
        }
      }
      if (chatBtn && user.id) {
        chatBtn.href = `chats.php?user_id=${encodeURIComponent(user.id)}`;
      }

      renderProfileReviews(user.reviews || []);

      const modalEl = document.getElementById('partnerProfileModal');
      if (modalEl && typeof bootstrap !== 'undefined') {
        bootstrap.Modal.getOrCreateInstance(modalEl).show();
      }

      // Fetch freshest reviews via AJAX
      if (user.id) {
        try {
          const resp = await fetch(`api/reviews.php?user_id=${encodeURIComponent(user.id)}`);
          const data = await resp.json();
          if (data.ok) {
            renderProfileReviews(data.reviews || []);
            if (ratingEl && Number(data.review_count || 0) > 0) {
              ratingEl.textContent = `★ ${Number(data.avg_rating).toFixed(1)} (${data.review_count})`;
              ratingEl.classList.remove('d-none');
            }
          }
        } catch {
          // keep cached reviews
        }
      }
      return;
    }

    const reqBtn = event.target.closest('.quick-request-btn');
    if (reqBtn) {
      try {
        const user = JSON.parse(reqBtn.getAttribute('data-user') || '{}');
        openExchangeRequestModal(user);
      } catch {
        // ignore
      }
      return;
    }

    const revBtn = event.target.closest('.open-review-btn');
    if (revBtn) {
      openReviewModal({
        revieweeId: revBtn.getAttribute('data-reviewee-id'),
        revieweeName: revBtn.getAttribute('data-reviewee-name'),
        proposalId: revBtn.getAttribute('data-proposal-id') || '',
        skill: revBtn.getAttribute('data-skill') || '',
      });
    }
  });

  const modalRequestBtn = document.getElementById('modalRequestSwapBtn');
  if (modalRequestBtn) {
    modalRequestBtn.addEventListener('click', () => openExchangeRequestModal(activeModalUser));
  }

  const modalReviewBtn = document.getElementById('modalOpenReviewBtn');
  if (modalReviewBtn) {
    modalReviewBtn.addEventListener('click', () => {
      if (!activeModalUser) return;
      openReviewModal({
        revieweeId: activeModalUser.id,
        revieweeName: activeModalUser.name,
        skill: (activeModalUser.teach_skills && activeModalUser.teach_skills[0]) || '',
      });
    });
  }

  // Submit Exchange Request
  const reqForm = document.getElementById('exchangeRequestForm');
  if (reqForm) {
    reqForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const fd = new FormData(reqForm);
      const partnerId = Number(fd.get('partner_id') || 0);
      try {
        await window.SkillSwap.postJson('api/requests.php', {
          action: 'create_request',
          partner_id: partnerId,
          learn_skill: fd.get('learn_skill'),
          teach_skill: fd.get('teach_skill'),
          schedule_text: fd.get('schedule_text'),
          note: fd.get('note'),
        });
        const modalEl = document.getElementById('exchangeRequestModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        window.SkillSwap.showToast('Exchange request sent! Opening chat...');
        setTimeout(() => {
          window.location.href = `chats.php?user_id=${encodeURIComponent(partnerId)}`;
        }, 700);
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }

  // Submit Review
  const revForm = document.getElementById('reviewForm');
  if (revForm) {
    revForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const fd = new FormData(revForm);
      try {
        await window.SkillSwap.postJson('api/reviews.php', {
          action: 'create_review',
          reviewee_id: Number(fd.get('reviewee_id') || 0),
          proposal_id: fd.get('proposal_id') ? Number(fd.get('proposal_id')) : null,
          rating: Number(fd.get('rating') || 5),
          skill_practiced: fd.get('skill_practiced'),
          comment: fd.get('comment'),
        });
        const modalEl = document.getElementById('reviewModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        window.SkillSwap.showToast('Review submitted! Thank you.');
        if (window.location.pathname.endsWith('profile.php')) {
          setTimeout(() => window.location.reload(), 650);
        }
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }

  // Demo Login Helpers
  const fillDemoBtn = document.getElementById('fillDemoLogin');
  if (fillDemoBtn) {
    fillDemoBtn.addEventListener('click', function () {
      document.getElementById('loginEmail').value = 'sam@skillswap.local';
      document.getElementById('loginPassword').value = 'password123';
    });
  }

  const fillAdminBtn = document.getElementById('fillAdminLogin');
  if (fillAdminBtn) {
    fillAdminBtn.addEventListener('click', function () {
      document.getElementById('loginEmail').value = 'admin@skillswap.local';
      document.getElementById('loginPassword').value = 'admin123';
    });
  }

  // Login Form
  const loginForm = document.getElementById('loginForm');
  if (loginForm) {
    loginForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const errBox = document.getElementById('loginError');
      if (errBox) errBox.classList.add('d-none');

      const formData = new FormData(loginForm);
      try {
        const res = await window.SkillSwap.postJson('api/auth.php', {
          action: 'login',
          email: formData.get('email'),
          password: formData.get('password'),
        });
        if (res.role === 'admin') {
          window.location.href = 'admin.php';
        } else {
          window.location.reload();
        }
      } catch (err) {
        if (errBox) {
          errBox.textContent = err.message;
          errBox.classList.remove('d-none');
        }
      }
    });
  }

  // Signup Form
  const signupForm = document.getElementById('signupForm');
  if (signupForm) {
    signupForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const errBox = document.getElementById('signupError');
      if (errBox) errBox.classList.add('d-none');

      const formData = new FormData(signupForm);
      try {
        await window.SkillSwap.postJson('api/auth.php', {
          action: 'signup',
          name: formData.get('name'),
          email: formData.get('email'),
          password: formData.get('password'),
          teach_skills: formData.get('teach_skills'),
          learn_skills: formData.get('learn_skills'),
          availability: formData.get('availability'),
        });
        window.location.href = 'discover.php';
      } catch (err) {
        if (errBox) {
          errBox.textContent = err.message;
          errBox.classList.remove('d-none');
        }
      }
    });
  }

  // Logout Button
  const logoutBtn = document.getElementById('logoutBtn');
  if (logoutBtn) {
    logoutBtn.addEventListener('click', async function () {
      try {
        await window.SkillSwap.postJson('api/auth.php', { action: 'logout' });
        window.location.href = 'index.php';
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }
})();
