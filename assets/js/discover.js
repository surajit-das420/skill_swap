(function () {
  'use strict';

  const matchesGrid = document.getElementById('matchesGrid');
  const countLabel = document.getElementById('matchesCountLabel');
  const searchInput = document.getElementById('discoverSearchInput');
  const modeSelect = document.getElementById('matchModeSelect');
  const availSelect = document.getElementById('availabilityFilterSelect');
  const clearSkillBtn = document.getElementById('clearSkillFilterBtn');
  const filtersForm = document.getElementById('filtersForm');

  let activeTeachFilter = '';
  let activeLearnFilter = '';
  let searchTimer = null;

  function renderMatches(list) {
    if (!matchesGrid) return;
    const esc = window.SkillSwap.escapeHtml;

    if (countLabel) {
      countLabel.textContent = `${list.length} match${list.length === 1 ? '' : 'es'} found`;
    }

    if (!list.length) {
      matchesGrid.innerHTML = `
        <div class="col-12">
          <div class="ss-card p-4 text-center text-muted">
            No matching people found. Try switching to <strong>All community</strong> or clearing active filters.
          </div>
        </div>`;
      return;
    }

    matchesGrid.innerHTML = list
      .map((m) => {
        const userJson = esc(JSON.stringify(m));
        const teachPill = m.primary_teach
          ? `<span class="pill-teach">Teaches ${esc(m.primary_teach)}</span>`
          : '';
        const wantsPill = m.primary_wants
          ? `<span class="pill-learn">Wants ${esc(m.primary_wants)}</span>`
          : '';
        const mutualBadge = m.is_mutual
          ? `<span class="match-badge">2-way match</span>`
          : '';
        const ratingBadge =
          Number(m.review_count || 0) > 0
            ? `<span class="rating-badge" title="${Number(m.review_count)} review(s)">★ ${Number(m.avg_rating).toFixed(1)}</span>`
            : '';

        return `
          <div class="col-md-6 match-card-col">
            <div class="ss-card match-card">
              <div>
                <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                  <div class="d-flex align-items-center gap-3">
                    <span class="avatar-circle" style="background-color: ${esc(m.avatar_color)};">
                      ${esc(m.initials)}
                    </span>
                    <div>
                      <div class="match-card-name">${esc(m.name)}</div>
                      <div class="match-card-meta">${esc(m.availability)}</div>
                    </div>
                  </div>
                  ${ratingBadge}
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                  ${teachPill}
                  ${wantsPill}
                  ${mutualBadge}
                </div>
              </div>
              <div class="d-flex align-items-center gap-2 pt-1">
                <a href="chats.php?user_id=${encodeURIComponent(m.id)}" class="btn btn-ss-primary flex-grow-1">Message</a>
                <button type="button" class="btn btn-ss-outline quick-request-btn" data-user="${userJson}">Request</button>
                <button type="button" class="btn btn-ss-outline view-profile-btn" data-user="${userJson}">View</button>
              </div>
            </div>
          </div>`;
      })
      .join('');
  }

  async function fetchMatchesFromApi() {
    const params = new URLSearchParams({
      q: (searchInput?.value || '').trim(),
      teach: activeTeachFilter,
      learn: activeLearnFilter,
      availability: availSelect?.value || '',
      mode: modeSelect?.value || 'best',
    });

    try {
      const response = await fetch(`api/matches.php?${params.toString()}`);
      const data = await response.json();
      if (data.ok) {
        renderMatches(data.matches || []);
      }
    } catch {
      window.SkillSwap.showToast('Could not refresh matches.');
    }
  }

  function updateClearButton() {
    if (!clearSkillBtn) return;
    const hasFilter = activeTeachFilter !== '' || activeLearnFilter !== '';
    clearSkillBtn.classList.toggle('d-none', !hasFilter);
  }

  // Clickable sidebar skill pills
  document.addEventListener('click', function (e) {
    const pill = e.target.closest('.pill-filter-btn');
    if (!pill) return;

    const type = pill.getAttribute('data-filter-type');
    const skill = pill.getAttribute('data-skill') || '';
    const wasActive = pill.classList.contains('active');

    document.querySelectorAll('.pill-filter-btn').forEach((el) => el.classList.remove('active'));
    activeTeachFilter = '';
    activeLearnFilter = '';

    if (!wasActive) {
      pill.classList.add('active');
      if (type === 'teach') activeTeachFilter = skill;
      if (type === 'learn') activeLearnFilter = skill;
    }

    updateClearButton();
    fetchMatchesFromApi();
  });

  if (clearSkillBtn) {
    clearSkillBtn.addEventListener('click', function () {
      document.querySelectorAll('.pill-filter-btn').forEach((el) => el.classList.remove('active'));
      activeTeachFilter = '';
      activeLearnFilter = '';
      updateClearButton();
      fetchMatchesFromApi();
    });
  }

  if (searchInput) {
    searchInput.addEventListener('input', function () {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(fetchMatchesFromApi, 200);
    });
  }

  if (modeSelect) {
    modeSelect.addEventListener('change', fetchMatchesFromApi);
  }

  if (availSelect) {
    availSelect.addEventListener('change', fetchMatchesFromApi);
  }

  if (filtersForm) {
    filtersForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const formData = new FormData(filtersForm);
      try {
        const res = await window.SkillSwap.postJson('api/matches.php', {
          learn_skills: formData.get('learn_skills'),
          teach_skills: formData.get('teach_skills'),
          availability: formData.get('availability'),
          q: (searchInput?.value || '').trim(),
          mode: modeSelect?.value || 'best',
          availability_filter: availSelect?.value || '',
        });

        const esc = window.SkillSwap.escapeHtml;
        const learnPills = document.getElementById('sidebarLearnPills');
        const teachPills = document.getElementById('sidebarTeachPills');
        const availEl = document.getElementById('sidebarAvailability');

        if (learnPills) {
          learnPills.innerHTML = res.user.learn_skills.length
            ? res.user.learn_skills
                .map(
                  (s) =>
                    `<button type="button" class="pill-learn pill-filter-btn" data-filter-type="teach" data-skill="${esc(s)}">${esc(s)}</button>`
                )
                .join('')
            : '<span class="text-muted small">No skills selected</span>';
        }
        if (teachPills) {
          teachPills.innerHTML = res.user.teach_skills.length
            ? res.user.teach_skills
                .map(
                  (s) =>
                    `<button type="button" class="pill-teach pill-filter-btn" data-filter-type="learn" data-skill="${esc(s)}">${esc(s)}</button>`
                )
                .join('')
            : '<span class="text-muted small">No skills selected</span>';
        }
        if (availEl) {
          availEl.textContent = res.user.availability;
        }

        activeTeachFilter = '';
        activeLearnFilter = '';
        updateClearButton();
        renderMatches(res.matches || []);

        const modalEl = document.getElementById('filtersModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        window.SkillSwap.showToast('Filters updated');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }
})();
