<?php
declare(strict_types=1);

$activePage = 'chats';
$pageTitle = 'Chats — SkillSwap';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/proposals.php';

$myId = (int)$currentUser['id'];
$requestedPartnerId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

$convStmt = $pdo->prepare(
    'SELECT u.id, u.name, u.initials, u.avatar_color, u.availability, u.location, u.bio,
            (
              SELECT m.body FROM messages m
              WHERE (m.sender_id = :uid AND m.receiver_id = u.id)
                 OR (m.sender_id = u.id AND m.receiver_id = :uid)
              ORDER BY m.id DESC LIMIT 1
            ) AS last_message,
            (
              SELECT m.id FROM messages m
              WHERE (m.sender_id = :uid AND m.receiver_id = u.id)
                 OR (m.sender_id = u.id AND m.receiver_id = :uid)
              ORDER BY m.id DESC LIMIT 1
            ) AS last_msg_id
     FROM users u
     WHERE u.id != :uid AND u.status = "active" AND u.role != "admin"
       AND (
         EXISTS (SELECT 1 FROM messages m WHERE (m.sender_id = :uid AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = :uid))
         OR EXISTS (SELECT 1 FROM swap_proposals sp WHERE (sp.proposer_id = :uid AND sp.recipient_id = u.id) OR (sp.proposer_id = u.id AND sp.recipient_id = :uid))
         OR u.id = :req_id
       )
     ORDER BY COALESCE(last_msg_id, 0) DESC, u.id ASC'
);
$convStmt->execute([':uid' => $myId, ':req_id' => $requestedPartnerId]);
$conversations = $convStmt->fetchAll();

if (empty($conversations)) {
    $fallbackStmt = $pdo->prepare('SELECT id, name, initials, avatar_color, availability, location, bio, NULL AS last_message FROM users WHERE id != ? AND status = "active" AND role != "admin" ORDER BY id ASC LIMIT 3');
    $fallbackStmt->execute([$myId]);
    $conversations = $fallbackStmt->fetchAll();
}

$activePartner = null;
foreach ($conversations as $conv) {
    if ($requestedPartnerId > 0 && (int)$conv['id'] === $requestedPartnerId) {
        $activePartner = getUserWithSkills($pdo, (int)$conv['id']);
        break;
    }
}
if (!$activePartner && !empty($conversations)) {
    $activePartner = getUserWithSkills($pdo, (int)$conversations[0]['id']);
}
if ($activePartner) {
    $activePartner['reviews'] = getUserReviews($pdo, (int)$activePartner['id'], 5);
}

$messages = [];
$proposal = null;
if ($activePartner) {
    $partnerId = (int)$activePartner['id'];
    $messages = fetchThreadMessages($pdo, $myId, $partnerId, 0);
    $proposal = fetchLatestThreadProposal($pdo, $myId, $partnerId);
}

$partnerFirstName = $activePartner ? strtoupper(explode(' ', $activePartner['name'])[0]) : 'PARTNER';
$extraScripts = ['assets/js/chats.js'];
?>

<div class="chats-shell" data-current-user-id="<?= $myId ?>" data-partner-id="<?= $activePartner ? (int)$activePartner['id'] : 0 ?>">
  <!-- Left Column: Conversations -->
  <aside class="chats-list-pane">
    <div class="ss-section-label px-2 mb-3">Chats</div>
    <div class="d-flex flex-column" id="chatThreadList">
      <?php foreach ($conversations as $conv): ?>
        <?php $isActive = $activePartner && (int)$conv['id'] === (int)$activePartner['id']; ?>
        <a
          href="chats.php?user_id=<?= (int)$conv['id'] ?>"
          class="chat-thread-item <?= $isActive ? 'active' : '' ?>"
          data-thread-user-id="<?= (int)$conv['id'] ?>"
        >
          <span class="avatar-circle" style="background-color: <?= e($conv['avatar_color']) ?>;">
            <?= e($conv['initials']) ?>
          </span>
          <div class="min-w-0 flex-grow-1">
            <div class="chat-thread-name text-truncate"><?= e($conv['name']) ?></div>
            <div class="chat-thread-preview" data-preview-for="<?= (int)$conv['id'] ?>">
              <?= e($conv['last_message'] ?: 'Start a skill swap conversation') ?>
            </div>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <!-- Center Column: Active Thread -->
  <section class="chats-main-pane">
    <?php if ($activePartner): ?>
      <header class="chat-main-header" id="chatMainHeader">
        <div class="d-flex flex-wrap align-items-center gap-2">
          <span class="fw-bold fs-6 me-1" id="chatPartnerName"><?= e($activePartner['name']) ?></span>
          <span id="chatPartnerSkills" class="d-inline-flex flex-wrap gap-2">
            <?php if (!empty($activePartner['teach_skills'])): ?>
              <span class="pill-teach"><?= e($activePartner['teach_skills'][0]) ?></span>
            <?php endif; ?>
            <?php if (!empty($activePartner['learn_skills'])): ?>
              <span class="pill-learn"><?= e($activePartner['learn_skills'][0]) ?></span>
            <?php endif; ?>
          </span>
        </div>
        <span class="small text-muted d-none d-sm-inline" id="ajaxStatusHint">Live AJAX chat</span>
      </header>

      <div class="chat-messages-scroll" id="chatMessagesBox">
        <?php if (empty($messages)): ?>
          <div class="text-center text-muted small my-auto py-4" id="emptyChatHint">
            Say hello to <?= e($activePartner['name']) ?> and propose a time for your skill swap!
          </div>
        <?php else: ?>
          <?php foreach ($messages as $msg): ?>
            <?php
              $isMine = (int)$msg['sender_id'] === $myId;
              $mType = $msg['message_type'] ?? 'text';
            ?>
            <?php if ($mType === 'proposal' && !empty($msg['proposal_id'])): ?>
              <?php
                $pSt = $msg['proposal_status'] ?? 'pending';
                $canAccept = ($pSt === 'pending' && (int)$msg['proposal_recipient_id'] === $myId);
                $isProposerMsg = ((int)$msg['proposal_proposer_id'] === $myId);
              ?>
              <div class="chat-proposal-card <?= $isMine ? 'mine' : 'theirs' ?>" data-message-id="<?= (int)$msg['id'] ?>" data-inline-proposal-id="<?= (int)$msg['proposal_id'] ?>">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                  <span class="ss-section-label" style="color: var(--ss-primary);">Swap proposal</span>
                  <span class="status-pill status-<?= e($pSt) ?>" data-inline-status-badge="<?= (int)$msg['proposal_id'] ?>"><?= e($pSt) ?></span>
                </div>
                <div class="fw-bold small mb-1"><?= e($msg['proposal_title'] ?? $msg['body']) ?></div>
                <?php if (!empty($msg['proposal_schedule'])): ?>
                  <div class="text-muted small mb-2"><?= e($msg['proposal_schedule']) ?></div>
                <?php endif; ?>
                <?php if (!empty($msg['proposal_note'])): ?>
                  <div class="text-secondary small mb-2"><?= e($msg['proposal_note']) ?></div>
                <?php endif; ?>

                <div data-inline-proposal-actions="<?= (int)$msg['proposal_id'] ?>">
                  <?php if ($canAccept): ?>
                    <div class="d-flex gap-2 mt-2">
                      <button type="button" class="btn btn-ss-primary btn-sm flex-grow-1 proposal-action-btn" data-action="accept_proposal" data-proposal-id="<?= (int)$msg['proposal_id'] ?>">Accept</button>
                      <button type="button" class="btn btn-ss-outline btn-sm text-danger proposal-action-btn" data-action="decline_proposal" data-proposal-id="<?= (int)$msg['proposal_id'] ?>">Decline</button>
                    </div>
                  <?php elseif ($pSt === 'pending' && $isProposerMsg): ?>
                    <div class="text-muted" style="font-size: 0.78rem;">Sent for acceptance</div>
                  <?php endif; ?>
                </div>
              </div>
            <?php elseif ($mType === 'system'): ?>
              <div class="chat-system-notice" data-message-id="<?= (int)$msg['id'] ?>">
                <?= e($msg['body']) ?>
              </div>
            <?php else: ?>
              <div class="chat-bubble <?= $isMine ? 'chat-bubble-out' : 'chat-bubble-in' ?>" data-message-id="<?= (int)$msg['id'] ?>">
                <?= e($msg['body']) ?>
              </div>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <form class="chat-composer" id="chatMessageForm" autocomplete="off">
        <div class="d-flex align-items-center gap-2">
          <input type="text" class="form-control" id="chatMessageInput" placeholder="Write a message" required>
          <button type="submit" class="btn btn-ss-primary px-4" id="chatSendBtn">Send</button>
        </div>
      </form>
    <?php else: ?>
      <div class="m-auto text-center text-muted p-4">
        Select a match from <a href="discover.php">Discover</a> to start chatting.
      </div>
    <?php endif; ?>
  </section>

  <!-- Right Column: Swap Proposal & About -->
  <?php if ($activePartner): ?>
    <aside class="chats-right-pane">
      <div class="swap-proposal-card <?= ($proposal && in_array($proposal['status'], ['accepted', 'completed'], true)) ? 'accepted' : '' ?>" id="swapProposalCard">
        <?php if ($proposal): ?>
          <?php $st = $proposal['status']; ?>
          <div class="d-flex align-items-center justify-content-between mb-2">
            <span class="ss-section-label" style="color: <?= in_array($st, ['accepted', 'completed'], true) ? '#0F766E' : ($st === 'declined' ? '#991B1B' : 'var(--ss-primary)') ?>;" id="proposalStatusLabel">
              <?= $st === 'accepted' ? 'Swap accepted' : ($st === 'completed' ? 'Swap completed' : ($st === 'declined' ? 'Swap declined' : 'Swap proposal')) ?>
            </span>
            <span class="status-pill status-<?= e($st) ?>"><?= e($st) ?></span>
          </div>

          <div class="fw-bold mb-1" id="proposalTitle"><?= e($proposal['title']) ?></div>
          <div class="text-muted small mb-2" id="proposalSchedule"><?= e($proposal['schedule_text']) ?></div>
          <?php if (!empty($proposal['note'])): ?>
            <div class="text-secondary small mb-3"><?= e($proposal['note']) ?></div>
          <?php endif; ?>

          <div class="d-grid gap-2 mt-2" id="proposalActions">
            <?php if ($st === 'pending'): ?>
              <?php if (!empty($proposal['is_recipient'])): ?>
                <button type="button" class="btn btn-ss-primary w-100 proposal-action-btn" data-action="accept_proposal" data-proposal-id="<?= (int)$proposal['id'] ?>">
                  Accept
                </button>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-ss-outline flex-grow-1" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
                    Change time
                  </button>
                  <button type="button" class="btn btn-ss-outline text-danger proposal-action-btn" data-action="decline_proposal" data-proposal-id="<?= (int)$proposal['id'] ?>">
                    Decline
                  </button>
                </div>
              <?php else: ?>
                <div class="small text-muted bg-light rounded-3 px-3 py-2 text-center">
                  Waiting for <?= e(explode(' ', $activePartner['name'])[0]) ?> to accept
                </div>
                <div class="d-flex gap-2">
                  <button type="button" class="btn btn-ss-outline flex-grow-1" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
                    Change time
                  </button>
                  <button type="button" class="btn btn-ss-outline text-danger proposal-action-btn" data-action="cancel_proposal" data-proposal-id="<?= (int)$proposal['id'] ?>">
                    Cancel
                  </button>
                </div>
              <?php endif; ?>
            <?php elseif ($st === 'accepted'): ?>
              <button type="button" class="btn btn-ss-primary w-100 proposal-action-btn" data-action="complete_proposal" data-proposal-id="<?= (int)$proposal['id'] ?>">
                Mark completed
              </button>
              <button type="button" class="btn btn-ss-outline w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
                Change time
              </button>
            <?php elseif ($st === 'completed'): ?>
              <button
                type="button"
                class="btn btn-ss-primary w-100 open-review-btn"
                data-reviewee-id="<?= (int)$activePartner['id'] ?>"
                data-reviewee-name="<?= e($activePartner['name']) ?>"
                data-proposal-id="<?= (int)$proposal['id'] ?>"
                data-skill="<?= e($activePartner['teach_skills'][0] ?? '') ?>"
              >
                Leave a review
              </button>
              <button type="button" class="btn btn-ss-outline w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
                Propose next swap
              </button>
            <?php else: ?>
              <button type="button" class="btn btn-ss-primary w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
                Propose new swap
              </button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="ss-section-label mb-2" style="color: var(--ss-primary);">Swap proposal</div>
          <p class="text-muted small mb-3">No time proposed yet. Suggest a 1-on-1 lesson swap with <?= e(explode(' ', $activePartner['name'])[0]) ?>.</p>
          <button type="button" class="btn btn-ss-primary w-100" data-bs-toggle="modal" data-bs-target="#changeTimeModal">
            Propose swap
          </button>
        <?php endif; ?>
      </div>

      <div class="about-partner-card" id="aboutPartnerCard">
        <div class="d-flex align-items-center justify-content-between mb-2">
          <div class="ss-section-label" id="aboutPartnerHeading">About <?= e($partnerFirstName) ?></div>
          <?php if ($activePartner['review_count'] > 0): ?>
            <span class="rating-badge" id="aboutPartnerRating">★ <?= number_format((float)$activePartner['avg_rating'], 1) ?></span>
          <?php else: ?>
            <span class="rating-badge d-none" id="aboutPartnerRating"></span>
          <?php endif; ?>
        </div>
        <div class="text-secondary small mb-3" id="aboutPartnerAvail"><?= e($activePartner['availability']) ?></div>
        <button
          type="button"
          class="btn btn-ss-outline w-100 view-profile-btn"
          id="aboutViewProfileBtn"
          data-user='<?= e(json_encode($activePartner, JSON_UNESCAPED_UNICODE)) ?>'
        >
          View profile
        </button>
      </div>
    </aside>
  <?php endif; ?>
</div>

<?php if ($activePartner): ?>
<!-- Change Time / Propose Swap Modal -->
<div class="modal fade" id="changeTimeModal" tabindex="-1" aria-labelledby="changeTimeModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header border-0 px-4 pt-4 pb-1">
        <h5 class="modal-title fw-bold" id="changeTimeModalLabel">
          <?= $proposal ? 'Update swap proposal' : 'Propose a skill swap' ?>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="proposalForm">
        <input type="hidden" name="partner_id" id="proposalPartnerIdInput" value="<?= (int)$activePartner['id'] ?>">
        <div class="modal-body px-4 py-3">
          <div class="mb-3">
            <label for="proposalTitleInput" class="form-label small fw-semibold">Swap summary</label>
            <input
              type="text"
              class="form-control ss-input"
              id="proposalTitleInput"
              name="title"
              value="<?= e($proposal['title'] ?? ('1 hr ' . ($activePartner['teach_skills'][0] ?? 'Skill') . ' for 1 hr ' . ($currentUser['teach_skills'][0] ?? 'Skill'))) ?>"
              required
            >
          </div>
          <div class="mb-3">
            <label for="proposalScheduleInput" class="form-label small fw-semibold">Day, time &amp; format</label>
            <input
              type="text"
              class="form-control ss-input"
              id="proposalScheduleInput"
              name="schedule_text"
              value="<?= e($proposal['schedule_text'] ?? 'Sunday, 5:00 PM, video call') ?>"
              placeholder="e.g., Sunday, 5:00 PM, video call"
              required
            >
          </div>
          <div class="mb-1">
            <label for="proposalNoteInput" class="form-label small fw-semibold">Note (optional)</label>
            <input
              type="text"
              class="form-control ss-input"
              id="proposalNoteInput"
              name="note"
              value="<?= e($proposal['note'] ?? '') ?>"
              placeholder="Topics to focus on..."
            >
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-1">
          <button type="button" class="btn btn-ss-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-ss-primary">Send proposal</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
