(function () {
  'use strict';

  const detailsForm = document.getElementById('profileDetailsForm');
  const addTeachBtn = document.getElementById('addTeachBtn');
  const addTeachInput = document.getElementById('addTeachInput');
  const addLearnBtn = document.getElementById('addLearnBtn');
  const addLearnInput = document.getElementById('addLearnInput');

  function syncSkillsUI(user) {
    const esc = window.SkillSwap.escapeHtml;
    const teachContainer = document.getElementById('teachTagsContainer');
    const learnContainer = document.getElementById('learnTagsContainer');
    const teachSummary = document.getElementById('profileTeachSummary');
    const learnSummary = document.getElementById('profileLearnSummary');

    if (teachContainer) {
      teachContainer.innerHTML = (user.teach_skills || [])
        .map(
          (sk) => `
          <span class="pill-teach pill-removable" data-skill="${esc(sk)}" data-type="teach">
            ${esc(sk)}
            <button type="button" class="pill-remove-btn" aria-label="Remove ${esc(sk)}">&times;</button>
          </span>`
        )
        .join('');
    }
    if (learnContainer) {
      learnContainer.innerHTML = (user.learn_skills || [])
        .map(
          (sk) => `
          <span class="pill-learn pill-removable" data-skill="${esc(sk)}" data-type="learn">
            ${esc(sk)}
            <button type="button" class="pill-remove-btn" aria-label="Remove ${esc(sk)}">&times;</button>
          </span>`
        )
        .join('');
    }
    if (teachSummary) {
      teachSummary.innerHTML = (user.teach_skills || [])
        .map((sk) => `<span class="pill-teach">${esc(sk)}</span>`)
        .join('');
    }
    if (learnSummary) {
      learnSummary.innerHTML = (user.learn_skills || [])
        .map((sk) => `<span class="pill-learn">${esc(sk)}</span>`)
        .join('');
    }
  }

  async function addSkill(type, inputEl) {
    const val = (inputEl?.value || '').trim();
    if (!val) return;
    try {
      const res = await window.SkillSwap.postJson('api/profile.php', {
        action: 'add_skill',
        type: type,
        skill: val,
      });
      inputEl.value = '';
      syncSkillsUI(res.user);
      window.SkillSwap.showToast(`Added ${val}`);
    } catch (err) {
      window.SkillSwap.showToast(err.message);
    }
  }

  if (addTeachBtn && addTeachInput) {
    addTeachBtn.addEventListener('click', () => addSkill('teach', addTeachInput));
    addTeachInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addSkill('teach', addTeachInput);
      }
    });
  }

  if (addLearnBtn && addLearnInput) {
    addLearnBtn.addEventListener('click', () => addSkill('learn', addLearnInput));
    addLearnInput.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        addSkill('learn', addLearnInput);
      }
    });
  }

  document.addEventListener('click', async function (e) {
    const removeBtn = e.target.closest('.pill-remove-btn');
    if (removeBtn) {
      const pill = removeBtn.closest('.pill-removable');
      if (!pill) return;

      const skill = pill.getAttribute('data-skill') || '';
      const type = pill.getAttribute('data-type') || 'learn';
      try {
        const res = await window.SkillSwap.postJson('api/profile.php', {
          action: 'remove_skill',
          type: type,
          skill: skill,
        });
        syncSkillsUI(res.user);
        window.SkillSwap.showToast(`Removed ${skill}`);
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
      return;
    }

    const reqStatusBtn = e.target.closest('.req-status-btn');
    if (reqStatusBtn) {
      const id = Number(reqStatusBtn.getAttribute('data-id') || 0);
      const status = reqStatusBtn.getAttribute('data-status') || '';
      if (!id || !status) return;

      try {
        await window.SkillSwap.postJson('api/requests.php', {
          action: 'update_status',
          proposal_id: id,
          status: status,
        });
        window.SkillSwap.showToast(`Request marked ${status}`);
        setTimeout(() => window.location.reload(), 500);
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    }
  });

  if (detailsForm) {
    detailsForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const formData = new FormData(detailsForm);
      try {
        const res = await window.SkillSwap.postJson('api/profile.php', {
          action: 'update_details',
          name: formData.get('name'),
          availability: formData.get('availability'),
          location: formData.get('location'),
          bio: formData.get('bio'),
        });
        const u = res.user;
        const avatar = document.getElementById('profileAvatarPreview');
        const nameEl = document.getElementById('profileNamePreview');
        const availEl = document.getElementById('profileAvailPreview');
        const bioEl = document.getElementById('profileBioPreview');

        if (avatar) avatar.textContent = u.initials;
        if (nameEl) nameEl.textContent = u.name;
        if (availEl) availEl.textContent = u.availability;
        if (bioEl) bioEl.textContent = u.bio || '';

        window.SkillSwap.showToast('Profile saved');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }
})();
