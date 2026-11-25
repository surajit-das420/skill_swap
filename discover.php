<?php
declare(strict_types=1);

$activePage = 'discover';
$pageTitle = 'Discover Matches — SkillSwap';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/matching.php';

$matches = searchAndMatchUsers($pdo, $currentUser, [
    'q' => $_GET['q'] ?? '',
    'teach' => $_GET['teach'] ?? '',
    'learn' => $_GET['learn'] ?? '',
    'availability' => $_GET['availability'] ?? '',
    'mode' => $_GET['mode'] ?? 'best',
]);

$extraScripts = ['assets/js/discover.js'];
?>

<main class="container-xxl px-4 discover-layout">
  <div class="row g-4">
    <!-- Left Filter Sidebar -->
    <div class="col-lg-3">
      <aside class="ss-card filter-sidebar">
        <div class="mb-4">
          <div class="d-flex align-items-center justify-content-between mb-2">
            <div class="ss-section-label">I want to learn</div>
            <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none d-none" id="clearSkillFilterBtn" style="font-size: 0.75rem; color: var(--ss-primary);">Clear</button>
          </div>
          <div class="d-flex flex-wrap gap-2" id="sidebarLearnPills">
            <?php if (empty($currentUser['learn_skills'])): ?>
              <span class="text-muted small">No skills selected</span>
            <?php else: ?>
              <?php foreach ($currentUser['learn_skills'] as $skill): ?>
                <button type="button" class="pill-learn pill-filter-btn" data-filter-type="teach" data-skill="<?= e($skill) ?>" title="Filter people who teach <?= e($skill) ?>">
                  <?= e($skill) ?>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="mb-4">
          <div class="ss-section-label mb-2">I can teach</div>
          <div class="d-flex flex-wrap gap-2" id="sidebarTeachPills">
            <?php if (empty($currentUser['teach_skills'])): ?>
              <span class="text-muted small">No skills selected</span>
            <?php else: ?>
              <?php foreach ($currentUser['teach_skills'] as $skill): ?>
                <button type="button" class="pill-teach pill-filter-btn" data-filter-type="learn" data-skill="<?= e($skill) ?>" title="Filter people who want <?= e($skill) ?>">
                  <?= e($skill) ?>
                </button>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <div class="mb-4">
          <div class="ss-section-label mb-2">Availability</div>
          <div class="text-secondary small mb-2" id="sidebarAvailability"><?= e($currentUser['availability']) ?></div>
          <select class="form-select form-select-sm ss-input" id="availabilityFilterSelect" aria-label="Filter by availability">
            <option value="">Any schedule</option>
            <option value="weekends">Weekends</option>
            <option value="evenings">Evenings</option>
            <option value="weekdays">Weekdays</option>
            <option value="online">Online</option>
          </select>
        </div>

        <button type="button" class="btn btn-ss-outline w-100" data-bs-toggle="modal" data-bs-target="#filtersModal">
          Update filters
        </button>
      </aside>
    </div>

    <!-- Matches Grid -->
    <div class="col-lg-9">
      <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-3">
        <div>
          <h1 class="discover-heading">Matches for you</h1>
          <p class="discover-subheading mb-0">People who teach what you want and want what you teach</p>
        </div>
        <div class="d-flex flex-wrap align-items-center gap-2">
          <select class="form-select ss-input" id="matchModeSelect" style="width: auto;" aria-label="Match mode">
            <option value="best" selected>Best matches</option>
            <option value="all">All community</option>
          </select>
          <div style="min-width: 230px;">
            <input type="search" class="form-control ss-input" id="discoverSearchInput" placeholder="Search skill, name, city...">
          </div>
        </div>
      </div>

      <div class="d-flex align-items-center justify-content-between mb-2">
        <span class="small text-muted" id="matchesCountLabel"><?= count($matches) ?> match<?= count($matches) === 1 ? '' : 'es' ?> found</span>
      </div>

      <div class="row g-3" id="matchesGrid">
        <?php if (empty($matches)): ?>
          <div class="col-12">
            <div class="ss-card p-4 text-center text-muted">
              No exact matches yet. Try switching to <strong>All community</strong> or clicking <strong>Update filters</strong>.
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($matches as $m): ?>
            <div class="col-md-6 match-card-col">
              <div class="ss-card match-card">
                <div>
                  <div class="d-flex align-items-start justify-content-between gap-2 mb-3">
                    <div class="d-flex align-items-center gap-3">
                      <span class="avatar-circle" style="background-color: <?= e($m['avatar_color']) ?>;">
                        <?= e($m['initials']) ?>
                      </span>
                      <div>
                        <div class="match-card-name"><?= e($m['name']) ?></div>
                        <div class="match-card-meta"><?= e($m['availability']) ?></div>
                      </div>
                    </div>
                    <?php if ($m['review_count'] > 0): ?>
                      <span class="rating-badge" title="<?= (int)$m['review_count'] ?> review(s)">
                        ★ <?= number_format((float)$m['avg_rating'], 1) ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                    <?php if ($m['primary_teach'] !== ''): ?>
                      <span class="pill-teach">Teaches <?= e($m['primary_teach']) ?></span>
                    <?php endif; ?>
                    <?php if ($m['primary_wants'] !== ''): ?>
                      <span class="pill-learn">Wants <?= e($m['primary_wants']) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($m['is_mutual'])): ?>
                      <span class="match-badge">2-way match</span>
                    <?php endif; ?>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-2 pt-1">
                  <a href="chats.php?user_id=<?= (int)$m['id'] ?>" class="btn btn-ss-primary flex-grow-1">Message</a>
                  <button
                    type="button"
                    class="btn btn-ss-outline quick-request-btn"
                    data-user='<?= e(json_encode($m, JSON_UNESCAPED_UNICODE)) ?>'
                    title="Send exchange request"
                  >
                    Request
                  </button>
                  <button
                    type="button"
                    class="btn btn-ss-outline view-profile-btn"
                    data-user='<?= e(json_encode($m, JSON_UNESCAPED_UNICODE)) ?>'
                  >
                    View
                  </button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>
</main>

<!-- Update Filters Modal -->
<div class="modal fade" id="filtersModal" tabindex="-1" aria-labelledby="filtersModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content border-0 rounded-4 shadow">
      <div class="modal-header border-0 px-4 pt-4 pb-1">
        <div>
          <h5 class="modal-title fw-bold" id="filtersModalLabel">Update skill filters</h5>
          <p class="text-muted small mb-0">Customize what you want to learn, teach, and when you're free.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="filtersForm">
        <div class="modal-body px-4 py-3">
          <div class="mb-3">
            <label for="filterLearnInput" class="form-label small fw-semibold">I want to learn (comma-separated)</label>
            <input type="text" class="form-control ss-input" id="filterLearnInput" name="learn_skills" value="<?= e(implode(', ', $currentUser['learn_skills'])) ?>" placeholder="Python, UI design, French">
          </div>
          <div class="mb-3">
            <label for="filterTeachInput" class="form-label small fw-semibold">I can teach (comma-separated)</label>
            <input type="text" class="form-control ss-input" id="filterTeachInput" name="teach_skills" value="<?= e(implode(', ', $currentUser['teach_skills'])) ?>" placeholder="Guitar, Photography">
          </div>
          <div class="mb-1">
            <label for="filterAvailabilityInput" class="form-label small fw-semibold">Availability</label>
            <input type="text" class="form-control ss-input" id="filterAvailabilityInput" name="availability" value="<?= e($currentUser['availability']) ?>" placeholder="Weekends and evenings">
          </div>
        </div>
        <div class="modal-footer border-0 px-4 pb-4 pt-1">
          <button type="button" class="btn btn-ss-outline" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-ss-primary">Save &amp; update matches</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
