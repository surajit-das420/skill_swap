(function () {
  'use strict';

  const shell = document.querySelector('.chats-shell');
  if (!shell) return;

  const currentUserId = Number(shell.dataset.currentUserId || 0);
  let partnerId = Number(shell.dataset.partnerId || 0);
  let lastProposalKey = '';

  const messagesBox = document.getElementById('chatMessagesBox');
  const messageForm = document.getElementById('chatMessageForm');
  const messageInput = document.getElementById('chatMessageInput');
  const sendBtn = document.getElementById('chatSendBtn');
  const proposalForm = document.getElementById('proposalForm');
  const swapCard = document.getElementById('swapProposalCard');
  const threadList = document.getElementById('chatThreadList');

  function scrollToBottom() {
    if (messagesBox) {
      messagesBox.scrollTop = messagesBox.scrollHeight;
    }
  }
  scrollToBottom();

  function getLastMessageId() {
    if (!messagesBox) return 0;
    const bubbles = messagesBox.querySelectorAll('[data-message-id]');
    if (!bubbles.length) return 0;
    return Number(bubbles[bubbles.length - 1].getAttribute('data-message-id') || 0);
  }

  function syncInlineProposalState(proposal) {
    if (!proposal || !messagesBox) return;
    const propId = Number(proposal.id);
    const st = proposal.status || 'pending';

    messagesBox.querySelectorAll(`[data-inline-status-badge="${propId}"]`).forEach((badge) => {
      badge.className = `status-pill status-${st}`;
      badge.textContent = st;
    });

    messagesBox.querySelectorAll(`[data-inline-proposal-actions="${propId}"]`).forEach((box) => {
      const canAccept = st === 'pending' && Number(proposal.recipient_id) === currentUserId;
      const isProposer = Number(proposal.proposer_id) === currentUserId;
      if (canAccept) {
        box.innerHTML = `
          <div class="d-flex gap-2 mt-2">
            <button type="button" class="btn btn-ss-primary btn-sm flex-grow-1 proposal-action-btn" data-action="accept_proposal" data-proposal-id="${propId}">Accept</button>
            <button type="button" class="btn btn-ss-outline btn-sm text-danger proposal-action-btn" data-action="decline_proposal" data-proposal-id="${propId}">Decline</button>
          </div>`;
      } else if (st === 'pending' && isProposer) {
        box.innerHTML = `<div class="text-muted" style="font-size: 0.78rem;">Sent for acceptance</div>`;
      } else {
        box.innerHTML = '';
      }
    });
  }

  function appendMessageBubble(msg) {
    if (!messagesBox || !msg) return;
    if (messagesBox.querySelector(`[data-message-id="${msg.id}"]`)) return;

    const emptyHint = document.getElementById('emptyChatHint');
    if (emptyHint) emptyHint.remove();

    const esc = window.SkillSwap.escapeHtml;
    const isMine = Number(msg.sender_id) === currentUserId;
    const mType = msg.message_type || 'text';
    const el = document.createElement('div');

    if (mType === 'proposal' && msg.proposal_id) {
      const propId = Number(msg.proposal_id);
      const pSt = msg.proposal_status || 'pending';
      const canAccept = pSt === 'pending' && Number(msg.proposal_recipient_id) === currentUserId;
      const isProposer = Number(msg.proposal_proposer_id) === currentUserId;

      el.className = `chat-proposal-card ${isMine ? 'mine' : 'theirs'}`;
      el.setAttribute('data-message-id', String(msg.id));
      el.setAttribute('data-inline-proposal-id', String(propId));
      el.innerHTML = `
        <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
          <span class="ss-section-label" style="color: var(--ss-primary);">Swap proposal</span>
          <span class="status-pill status-${esc(pSt)}" data-inline-status-badge="${propId}">${esc(pSt)}</span>
        </div>
        <div class="fw-bold small mb-1">${esc(msg.proposal_title || msg.body)}</div>
        ${msg.proposal_schedule ? `<div class="text-muted small mb-2">${esc(msg.proposal_schedule)}</div>` : ''}
        ${msg.proposal_note ? `<div class="text-secondary small mb-2">${esc(msg.proposal_note)}</div>` : ''}
        <div data-inline-proposal-actions="${propId}">
          ${
            canAccept
              ? `<div class="d-flex gap-2 mt-2">
                   <button type="button" class="btn btn-ss-primary btn-sm flex-grow-1 proposal-action-btn" data-action="accept_proposal" data-proposal-id="${propId}">Accept</button>
                   <button type="button" class="btn btn-ss-outline btn-sm text-danger proposal-action-btn" data-action="decline_proposal" data-proposal-id="${propId}">Decline</button>
                 </div>`
              : pSt === 'pending' && isProposer
              ? `<div class="text-muted" style="font-size: 0.78rem;">Sent for acceptance</div>`
              : ''
          }
        </div>`;
    } else if (mType === 'system') {
      el.className = 'chat-system-notice';
      el.setAttribute('data-message-id', String(msg.id));
      el.textContent = msg.body;
    } else {
      el.className = `chat-bubble ${isMine ? 'chat-bubble-out' : 'chat-bubble-in'}`;
      el.setAttribute('data-message-id', String(msg.id));
      el.textContent = msg.body;
    }

    messagesBox.appendChild(el);

    const preview = document.querySelector(`[data-preview-for="${partnerId}"]`);
    if (preview) {
      preview.textContent = msg.body;
    }
    scrollToBottom();
  }

  function renderProposalCard(proposal, partner) {
    if (!swapCard) return;
    const esc = window.SkillSwap.escapeHtml;

    if (!proposal) {
      lastProposalKey = 'none';
      swapCard.classList.remove('accepted');
      const first = (partner?.name || 'partner').split(' ')[0];
      swapCard.innerHTML = `
        <div class="ss-section-label mb-2" style="color: var(--ss-primary);">Swap proposal</div>
        <p class="text-muted small mb-3">No time proposed yet. Suggest a 1-on-1 lesson swap with ${esc(first)}.</p>
        <button type="button" class="btn btn-ss-primary w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
          Propose swap
        </button>`;
      return;
    }

    lastProposalKey = `${proposal.id}:${proposal.status}:${proposal.proposer_id}:${proposal.schedule_text}:${proposal.title}`;
    const st = proposal.status || 'pending';
    const isPositive = st === 'accepted' || st === 'completed';
    const isRecipient = Number(proposal.recipient_id) === currentUserId;
    const partnerFirst = (
      partner?.name ||
      document.getElementById('chatPartnerName')?.textContent ||
      'Partner'
    ).split(' ')[0];

    swapCard.classList.toggle('accepted', isPositive);

    const labelText =
      st === 'accepted'
        ? 'Swap accepted'
        : st === 'completed'
        ? 'Swap completed'
        : st === 'declined'
        ? 'Swap declined'
        : st === 'cancelled'
        ? 'Swap cancelled'
        : 'Swap proposal';
    const labelColor = isPositive
      ? '#0F766E'
      : st === 'declined' || st === 'cancelled'
      ? '#991B1B'
      : 'var(--ss-primary)';

    let actionsHtml = '';
    if (st === 'pending') {
      if (isRecipient) {
        actionsHtml = `
          <button type="button" class="btn btn-ss-primary w-100 proposal-action-btn" data-action="accept_proposal" data-proposal-id="${Number(proposal.id)}">Accept</button>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ss-outline flex-grow-1" data-bs-toggle="modal" data-bs-target="#changeTimeModal">Change time</button>
            <button type="button" class="btn btn-ss-outline text-danger proposal-action-btn" data-action="decline_proposal" data-proposal-id="${Number(proposal.id)}">Decline</button>
          </div>`;
      } else {
        actionsHtml = `
          <div class="small text-muted bg-light rounded-3 px-3 py-2 text-center">
            Waiting for ${esc(partnerFirst)} to accept
          </div>
          <div class="d-flex gap-2">
            <button type="button" class="btn btn-ss-outline flex-grow-1" data-bs-toggle="modal" data-bs-target="#changeTimeModal">Change time</button>
            <button type="button" class="btn btn-ss-outline text-danger proposal-action-btn" data-action="cancel_proposal" data-proposal-id="${Number(proposal.id)}">Cancel</button>
          </div>`;
      }
    } else if (st === 'accepted') {
      actionsHtml = `
        <button type="button" class="btn btn-ss-primary w-100 proposal-action-btn" data-action="complete_proposal" data-proposal-id="${Number(proposal.id)}">Mark completed</button>
        <button type="button" class="btn btn-ss-outline w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">Change time</button>`;
    } else if (st === 'completed') {
      const pName = partner?.name || document.getElementById('chatPartnerName')?.textContent || 'Partner';
      const pSkill = (partner?.teach_skills && partner.teach_skills[0]) || '';
      actionsHtml = `
        <button type="button" class="btn btn-ss-primary w-100 open-review-btn"
          data-reviewee-id="${partnerId}"
          data-reviewee-name="${esc(pName)}"
          data-proposal-id="${Number(proposal.id)}"
          data-skill="${esc(pSkill)}">
          Leave a review
        </button>
        <button type="button" class="btn btn-ss-outline w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">Propose next swap</button>`;
    } else {
      actionsHtml = `<button type="button" class="btn btn-ss-primary w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">Propose new swap</button>`;
    }

    swapCard.innerHTML = `
      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="ss-section-label" style="color: ${labelColor};" id="proposalStatusLabel">${labelText}</span>
        <span class="status-pill status-${esc(st)}">${esc(st)}</span>
      </div>
      <div class="fw-bold mb-1" id="proposalTitle">${esc(proposal.title)}</div>
      <div class="text-muted small mb-2" id="proposalSchedule">${esc(proposal.schedule_text)}</div>
      ${proposal.note ? `<div class="text-secondary small mb-3">${esc(proposal.note)}</div>` : ''}
      <div class="d-grid gap-2 mt-2" id="proposalActions">${actionsHtml}</div>
    `;

    syncInlineProposalState(proposal);
  }

  async function switchConversation(newPartnerId) {
    if (!newPartnerId || newPartnerId === partnerId) return;
    try {
      const resp = await fetch(`api/chats.php?action=thread&partner_id=${encodeURIComponent(newPartnerId)}&after_id=0`);
      const data = await resp.json();
      if (!data.ok || !data.partner) return;

      partnerId = Number(newPartnerId);
      shell.dataset.partnerId = String(partnerId);
      const partner = data.partner;
      const esc = window.SkillSwap.escapeHtml;

      document.querySelectorAll('.chat-thread-item').forEach((item) => {
        item.classList.toggle('active', Number(item.getAttribute('data-thread-user-id')) === partnerId);
      });

      const nameEl = document.getElementById('chatPartnerName');
      const skillsEl = document.getElementById('chatPartnerSkills');
      if (nameEl) nameEl.textContent = partner.name;
      if (skillsEl) {
        const teachPill = partner.teach_skills?.[0] ? `<span class="pill-teach">${esc(partner.teach_skills[0])}</span>` : '';
        const learnPill = partner.learn_skills?.[0] ? `<span class="pill-learn">${esc(partner.learn_skills[0])}</span>` : '';
        skillsEl.innerHTML = teachPill + learnPill;
      }

      if (messagesBox) {
        if (!data.messages?.length) {
          messagesBox.innerHTML = `<div class="text-center text-muted small my-auto py-4" id="emptyChatHint">Say hello to ${esc(partner.name)} and propose a time for your skill swap!</div>`;
        } else {
          messagesBox.innerHTML = '';
          data.messages.forEach(appendMessageBubble);
        }
      }

      renderProposalCard(data.proposal, partner);

      const aboutHeading = document.getElementById('aboutPartnerHeading');
      const aboutAvail = document.getElementById('aboutPartnerAvail');
      const aboutRating = document.getElementById('aboutPartnerRating');
      const aboutBtn = document.getElementById('aboutViewProfileBtn');

      if (aboutHeading) aboutHeading.textContent = `About ${partner.name.split(' ')[0].toUpperCase()}`;
      if (aboutAvail) aboutAvail.textContent = partner.availability || '';
      if (aboutRating) {
        if (Number(partner.review_count || 0) > 0) {
          aboutRating.textContent = `★ ${Number(partner.avg_rating).toFixed(1)}`;
          aboutRating.classList.remove('d-none');
        } else {
          aboutRating.classList.add('d-none');
        }
      }
      if (aboutBtn) {
        aboutBtn.setAttribute('data-user', JSON.stringify(partner));
      }

      const propPartnerInput = document.getElementById('proposalPartnerIdInput');
      const propTitleInput = document.getElementById('proposalTitleInput');
      const propSchedInput = document.getElementById('proposalScheduleInput');
      const propNoteInput = document.getElementById('proposalNoteInput');
      if (propPartnerInput) propPartnerInput.value = String(partnerId);
      if (propTitleInput) {
        propTitleInput.value = data.proposal?.title || `1 hr ${partner.teach_skills?.[0] || 'Skill'} for 1 hr Swap`;
      }
      if (propSchedInput) {
        propSchedInput.value = data.proposal?.schedule_text || 'Sunday, 5:00 PM, video call';
      }
      if (propNoteInput) {
        propNoteInput.value = data.proposal?.note || '';
      }

      window.history.pushState({ partnerId }, '', `chats.php?user_id=${partnerId}`);
    } catch {
      window.location.href = `chats.php?user_id=${newPartnerId}`;
    }
  }

  // AJAX conversation switching
  if (threadList) {
    threadList.addEventListener('click', function (e) {
      const link = e.target.closest('.chat-thread-item');
      if (!link) return;
      e.preventDefault();
      const targetId = Number(link.getAttribute('data-thread-user-id') || 0);
      switchConversation(targetId);
    });
  }

  // Send message via AJAX
  if (messageForm && partnerId > 0) {
    messageForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const body = (messageInput?.value || '').trim();
      if (!body) return;

      messageInput.value = '';
      if (sendBtn) sendBtn.disabled = true;
      try {
        const res = await window.SkillSwap.postJson('api/chats.php', {
          action: 'send_message',
          partner_id: partnerId,
          body: body,
        });
        appendMessageBubble(res.message);
      } catch (err) {
        messageInput.value = body;
        window.SkillSwap.showToast(err.message);
      } finally {
        if (sendBtn) sendBtn.disabled = false;
        messageInput?.focus();
      }
    });
  }

  // Proposal status actions (both in right sidebar AND inline chat proposal cards)
  document.addEventListener('click', async function (e) {
    const btn = e.target.closest('.proposal-action-btn');
    if (!btn) return;

    const action = btn.getAttribute('data-action') || '';
    const proposalId = Number(btn.getAttribute('data-proposal-id') || 0);
    if (!action || !proposalId) return;

    const toastMap = {
      accept_proposal: 'Swap proposal accepted!',
      decline_proposal: 'Swap proposal declined.',
      complete_proposal: 'Swap marked completed! You can now leave a review.',
      cancel_proposal: 'Swap proposal cancelled.',
    };

    try {
      const res = await window.SkillSwap.postJson('api/chats.php', {
        action: action,
        proposal_id: proposalId,
      });
      renderProposalCard(res.proposal, null);
      if (res.message) {
        appendMessageBubble(res.message);
      }
      window.SkillSwap.showToast(toastMap[action] || 'Updated');
    } catch (err) {
      window.SkillSwap.showToast(err.message);
    }
  });

  // Change time / Propose swap
  if (proposalForm) {
    proposalForm.addEventListener('submit', async function (e) {
      e.preventDefault();
      const formData = new FormData(proposalForm);
      try {
        const res = await window.SkillSwap.postJson('api/chats.php', {
          action: 'save_proposal',
          partner_id: Number(formData.get('partner_id') || partnerId),
          title: formData.get('title'),
          schedule_text: formData.get('schedule_text'),
          note: formData.get('note') || '',
        });
        renderProposalCard(res.proposal, null);
        if (res.message) {
          appendMessageBubble(res.message);
        }

        const modalEl = document.getElementById('changeTimeModal');
        if (modalEl && typeof bootstrap !== 'undefined') {
          bootstrap.Modal.getOrCreateInstance(modalEl).hide();
        }
        window.SkillSwap.showToast('Swap proposal sent for acceptance');
      } catch (err) {
        window.SkillSwap.showToast(err.message);
      }
    });
  }

  // Live AJAX polling for new messages AND proposal state changes
  if (partnerId > 0) {
    setInterval(async function () {
      if (document.hidden || partnerId <= 0) return;
      try {
        const afterId = getLastMessageId();
        const response = await fetch(
          `api/chats.php?partner_id=${encodeURIComponent(partnerId)}&after_id=${encodeURIComponent(afterId)}`
        );
        if (!response.ok) return;
        const data = await response.json();
        if (data.ok) {
          if (Array.isArray(data.messages)) {
            data.messages.forEach(appendMessageBubble);
          }
          if (data.proposal) {
            const nextKey = `${data.proposal.id}:${data.proposal.status}:${data.proposal.proposer_id}:${data.proposal.schedule_text}:${data.proposal.title}`;
            if (lastProposalKey !== '' && nextKey !== lastProposalKey) {
              renderProposalCard(data.proposal, null);
            } else {
              lastProposalKey = nextKey;
              syncInlineProposalState(data.proposal);
            }
          }
        }
      } catch {
        // ignore transient polling failure
      }
    }, 3500);
  }
})();
