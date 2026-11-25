<?php
declare(strict_types=1);

$activePage = 'profile';
$pageTitle = 'Profile — SkillSwap';
require_once __DIR__ . '/includes/header.php';

$myId = (int)$currentUser['id'];
$swapsStmt = $pdo->prepare(
    'SELECT sp.*, u.id AS partner_id, u.name AS partner_name, u.initials AS partner_initials, u.avatar_color AS partner_color
     FROM swap_proposals sp
     JOIN users u ON u.id = CASE WHEN sp.proposer_id = :uid THEN sp.recipient_id ELSE sp.proposer_id END
     WHERE sp.proposer_id = :uid OR sp.recipient_id = :uid
     ORDER BY sp.id DESC'
);
$swapsStmt->execute([':uid' => $myId]);
$mySwaps = $swapsStmt->fetchAll();

$myReviews = getUserReviews($pdo, $myId, 15);
$extraScripts = ['assets/js/profile.js'];
?>

<main class="container-xxl px-4 discover-layout">
  <div class="row g-4">
    <!-- Left Summary Card -->
    <div class="col-lg-4">
      <div class="ss-card p-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <span class="avatar-circle avatar-circle-lg" id="profileAvatarPreview" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
            <?= e($currentUser['initials']) ?>
          </span>
          <div>
            <div class="d-flex align-items-center gap-2">
              <h1 class="h5 fw-bold mb-0" id="profileNamePreview"><?= e($currentUser['name']) ?></h1>
              <?php if ($currentUser['review_count'] > 0): ?>
                <span class="rating-badge">★ <?= number_format((float)$currentUser['avg_rating'], 1) ?></span>
              <?php endif; ?>
            </div>
            <div class="text-muted small" id="profileAvailPreview"><?= e($currentUser['availability']) ?></div>
          </div>
        </div>

        <p class="text-secondary small mb-4" id="profileBioPreview">
          <?= e($currentUser['bio'] ?: 'Add a short bio so matches know your learning goals and teaching style.') ?>
        </p>

        <div class="mb-3">
          <div class="ss-section-label mb-2">I can teach</div>
          <div class="d-flex flex-wrap gap-2" id="profileTeachSummary">
            <?php foreach ($currentUser['teach_skills'] as $sk): ?>
              <span class="pill-teach"><?= e($sk) ?></span>
            <?php endforeach; ?>
          </div>
        </div>

        <div>
          <div class="ss-section-label mb-2">I want to learn</div>
          <div class="d-flex flex-wrap gap-2" id="profileLearnSummary">
            <?php foreach ($currentUser['learn_skills'] as $sk): ?>
              <span class="pill-learn"><?= e($sk) ?></span>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- Reviews Received Card -->
      <div class="ss-card p-4 mt-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <h2 class="h6 fw-bold mb-0">Reviews received</h2>
          <span class="small text-muted"><?= count($myReviews) ?> total</span>
        </div>
        <?php if (empty($myReviews)): ?>
          <p class="text-muted small mb-0">Complete a skill exchange to start collecting reviews.</p>
        <?php else: ?>
          <div class="d-flex flex-column gap-2">
            <?php foreach ($myReviews as $rev): ?>
              <div class="review-item">
                <div class="d-flex align-items-center justify-content-between mb-1">
                  <span class="fw-semibold small"><?= e($rev['reviewer_name']) ?></span>
                  <span class="rating-badge"><?= str_repeat('★', (int)$rev['rating']) ?></span>
                </div>
                <?php if ($rev['skill_practiced'] !== ''): ?>
                  <div class="mb-1"><span class="pill-teach py-0 px-2" style="font-size: 0.72rem;"><?= e($rev['skill_practiced']) ?></span></div>
                <?php endif; ?>
                <div class="text-secondary small"><?= e($rev['comment']) ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Right Editor & Exchange Requests -->
    <div class="col-lg-8 d-flex flex-column gap-4">
      <!-- Skills Editor -->
      <div class="ss-card p-4">
        <h2 class="h5 fw-bold mb-1">Your skills</h2>
        <p class="text-muted small mb-4">Add or remove skills to update your Discover matches automatically.</p>

        <div class="row g-4">
          <div class="col-md-6">
            <div class="ss-section-label mb-2">I can teach</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="teachTagsContainer">
              <?php foreach ($currentUser['teach_skills'] as $sk): ?>
                <span class="pill-teach pill-removable" data-skill="<?= e($sk) ?>" data-type="teach">
                  <?= e($sk) ?>
                  <button type="button" class="pill-remove-btn" aria-label="Remove <?= e($sk) ?>">&times;</button>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control ss-input" id="addTeachInput" placeholder="Add a skill (e.g., Piano)">
              <button class="btn btn-ss-primary" type="button" id="addTeachBtn">Add</button>
            </div>
          </div>

          <div class="col-md-6">
            <div class="ss-section-label mb-2">I want to learn</div>
            <div class="d-flex flex-wrap gap-2 mb-3" id="learnTagsContainer">
              <?php foreach ($currentUser['learn_skills'] as $sk): ?>
                <span class="pill-learn pill-removable" data-skill="<?= e($sk) ?>" data-type="learn">
                  <?= e($sk) ?>
                  <button type="button" class="pill-remove-btn" aria-label="Remove <?= e($sk) ?>">&times;</button>
                </span>
              <?php endforeach; ?>
            </div>
            <div class="input-group input-group-sm">
              <input type="text" class="form-control ss-input" id="addLearnInput" placeholder="Add a skill (e.g., Spanish)">
              <button class="btn btn-ss-primary" type="button" id="addLearnBtn">Add</button>
            </div>
          </div>
        </div>
      </div>

      <!-- Exchange Requests Management -->
      <div class="ss-card p-4">
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div>
            <h2 class="h5 fw-bold mb-0">Exchange requests</h2>
            <p class="text-muted small mb-0">Accept, decline, complete swaps, and leave reviews.</p>
          </div>
          <a href="discover.php" class="small fw-semibold text-decoration-none" style="color: var(--ss-primary);">Find matches</a>
        </div>
        <?php if (empty($mySwaps)): ?>
          <p class="text-muted small mb-0">No exchange requests yet. Visit Discover to request a swap.</p>
        <?php else: ?>
          <div class="d-flex flex-column gap-2" id="profileRequestsList">
            <?php foreach ($mySwaps as $sw): ?>
              <?php
                $isIncoming = (int)$sw['recipient_id'] === $myId;
                $st = $sw['status'];
              ?>
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 rounded-3 border" style="border-color: var(--ss-border) !important;" data-request-row="<?= (int)$sw['id'] ?>">
                <div class="d-flex align-items-center gap-3">
                  <span class="avatar-circle" style="background-color: <?= e($sw['partner_color']) ?>;">
                    <?= e($sw['partner_initials']) ?>
                  </span>
                  <div>
                    <div class="fw-bold small">
                      <?= e($sw['title']) ?>
                      <span class="text-muted fw-normal">
                        <?= $isIncoming ? 'from ' : 'to ' ?><?= e($sw['partner_name']) ?>
                      </span>
                    </div>
                    <div class="text-muted small"><?= e($sw['schedule_text']) ?></div>
                    <?php if (!empty($sw['note'])): ?>
                      <div class="text-secondary small"><?= e($sw['note']) ?></div>
                    <?php endif; ?>
                  </div>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-2">
                  <span class="status-pill status-<?= e($st) ?>" data-status-badge="<?= (int)$sw['id'] ?>"><?= e($st) ?></span>

                  <?php if ($st === 'pending'): ?>
                    <?php if ($isIncoming): ?>
                      <button type="button" class="btn btn-ss-primary btn-sm req-status-btn" data-id="<?= (int)$sw['id'] ?>" data-status="accepted">Accept</button>
                      <button type="button" class="btn btn-ss-outline btn-sm text-danger req-status-btn" data-id="<?= (int)$sw['id'] ?>" data-status="declined">Decline</button>
                    <?php else: ?>
                      <button type="button" class="btn btn-ss-outline btn-sm text-danger req-status-btn" data-id="<?= (int)$sw['id'] ?>" data-status="cancelled">Cancel</button>
                    <?php endif; ?>
                  <?php elseif ($st === 'accepted'): ?>
                    <button type="button" class="btn btn-ss-primary btn-sm req-status-btn" data-id="<?= (int)$sw['id'] ?>" data-status="completed">Mark completed</button>
                  <?php elseif ($st === 'completed'): ?>
                    <button
                      type="button"
                      class="btn btn-ss-outline btn-sm open-review-btn"
                      data-reviewee-id="<?= (int)$sw['partner_id'] ?>"
                      data-reviewee-name="<?= e($sw['partner_name']) ?>"
                      data-proposal-id="<?= (int)$sw['id'] ?>"
                      data-skill="<?= e($sw['teach_skill'] ?: $sw['learn_skill']) ?>"
                    >
                      Leave review
                    </button>
                  <?php endif; ?>

                  <a href="chats.php?user_id=<?= (int)$sw['partner_id'] ?>" class="btn btn-ss-outline btn-sm">Chat</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>

      <!-- Profile Details Form -->
      <div class="ss-card p-4">
        <h2 class="h5 fw-bold mb-3">Profile details</h2>
        <form id="profileDetailsForm">
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label for="profileName" class="form-label small fw-semibold">Full name</label>
              <input type="text" class="form-control ss-input" id="profileName" name="name" value="<?= e($currentUser['name']) ?>" required>
            </div>
            <div class="col-md-6">
              <label for="profileAvailability" class="form-label small fw-semibold">Availability</label>
              <input type="text" class="form-control ss-input" id="profileAvailability" name="availability" value="<?= e($currentUser['availability']) ?>" required>
            </div>
          </div>
          <div class="mb-3">
            <label for="profileLocation" class="form-label small fw-semibold">Location / Mode</label>
            <input type="text" class="form-control ss-input" id="profileLocation" name="location" value="<?= e($currentUser['location']) ?>">
          </div>
          <div class="mb-3">
            <label for="profileBio" class="form-label small fw-semibold">Bio</label>
            <textarea class="form-control ss-input" id="profileBio" name="bio" rows="3"><?= e($currentUser['bio']) ?></textarea>
          </div>
          <div class="d-flex justify-content-end">
            <button type="submit" class="btn btn-ss-primary">Save profile</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
