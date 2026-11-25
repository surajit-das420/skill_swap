<?php
declare(strict_types=1);

$activePage = 'admin';
$pageTitle = 'Admin Dashboard — SkillSwap';
require_once __DIR__ . '/includes/header.php';

$isAuthorizedAdmin = $loggedIn && isAdminUser($currentUser);
$extraScripts = ['assets/js/admin.js'];
?>

<main class="container-xxl px-4 discover-layout">
  <?php if (!$isAuthorizedAdmin): ?>
    <div class="row justify-content-center py-5">
      <div class="col-md-7 col-lg-5">
        <div class="ss-card p-4 p-md-5 text-center">
          <div class="ss-section-label mb-2" style="color: var(--ss-primary);">Admin access</div>
          <h1 class="h4 fw-bold mb-2">Sign in as an Administrator</h1>
          <p class="text-muted small mb-4">
            Manage community members, monitor skill exchange requests, moderate reviews, and view platform skill trends.
          </p>
          <div class="d-grid gap-2">
            <button type="button" class="btn btn-ss-primary" id="enterDemoAdminBtn">Enter Demo Admin (Maya Kapoor)</button>
            <button type="button" class="btn btn-ss-outline" data-bs-toggle="modal" data-bs-target="#loginModal">Log in manually</button>
          </div>
          <div class="text-muted small mt-3">
            Credentials: <code>admin@skillswap.local</code> / <code>admin123</code>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <?php
      $totalUsers = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
      $activeUsers = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
      $totalRequests = (int)$pdo->query('SELECT COUNT(*) FROM swap_proposals')->fetchColumn();
      $completedSwaps = (int)$pdo->query("SELECT COUNT(*) FROM swap_proposals WHERE status IN ('accepted', 'completed')")->fetchColumn();
      $totalMessages = (int)$pdo->query('SELECT COUNT(*) FROM messages')->fetchColumn();
      $reviewStats = $pdo->query('SELECT COUNT(*) AS cnt, COALESCE(ROUND(AVG(rating), 1), 0) AS avg FROM reviews')->fetch();

      $allUsersRaw = $pdo->query('SELECT id FROM users ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN);
      $allUsers = [];
      foreach ($allUsersRaw as $uid) {
          $u = getUserWithSkills($pdo, (int)$uid);
          if ($u) {
              $allUsers[] = $u;
          }
      }

      $allRequests = $pdo->query(
          'SELECT sp.*, p.name AS proposer_name, r.name AS recipient_name
           FROM swap_proposals sp
           JOIN users p ON p.id = sp.proposer_id
           JOIN users r ON r.id = sp.recipient_id
           ORDER BY sp.id DESC'
      )->fetchAll();

      $allReviews = $pdo->query(
          'SELECT rev.*, u1.name AS reviewer_name, u2.name AS reviewee_name
           FROM reviews rev
           JOIN users u1 ON u1.id = rev.reviewer_id
           JOIN users u2 ON u2.id = rev.reviewee_id
           ORDER BY rev.id DESC'
      )->fetchAll();

      $topTeach = $pdo->query(
          "SELECT skill_name, COUNT(*) AS cnt FROM user_skills WHERE skill_type = 'teach' GROUP BY skill_name ORDER BY cnt DESC, skill_name ASC LIMIT 8"
      )->fetchAll();
      $topLearn = $pdo->query(
          "SELECT skill_name, COUNT(*) AS cnt FROM user_skills WHERE skill_type = 'learn' GROUP BY skill_name ORDER BY cnt DESC, skill_name ASC LIMIT 8"
      )->fetchAll();
    ?>

    <div class="d-flex flex-wrap align-items-end justify-content-between gap-3 mb-4">
      <div>
        <div class="ss-section-label mb-1" style="color: var(--ss-primary);">Admin Dashboard</div>
        <h1 class="discover-heading mb-0">Platform overview</h1>
      </div>
      <span class="pill-teach">Signed in as <?= e($currentUser['name']) ?> (Admin)</span>
    </div>

    <!-- KPI Cards -->
    <div class="row g-3 mb-4">
      <div class="col-sm-6 col-xl-3">
        <div class="ss-card kpi-card">
          <div class="ss-section-label mb-1">Members</div>
          <div class="kpi-value"><?= $totalUsers ?></div>
          <div class="text-muted small"><?= $activeUsers ?> active accounts</div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="ss-card kpi-card">
          <div class="ss-section-label mb-1">Exchange requests</div>
          <div class="kpi-value"><?= $totalRequests ?></div>
          <div class="text-muted small"><?= $completedSwaps ?> accepted or completed</div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="ss-card kpi-card">
          <div class="ss-section-label mb-1">Messages sent</div>
          <div class="kpi-value"><?= $totalMessages ?></div>
          <div class="text-muted small">Across all active chats</div>
        </div>
      </div>
      <div class="col-sm-6 col-xl-3">
        <div class="ss-card kpi-card">
          <div class="ss-section-label mb-1">Community rating</div>
          <div class="kpi-value"><?= number_format((float)$reviewStats['avg'], 1) ?> ★</div>
          <div class="text-muted small"><?= (int)$reviewStats['cnt'] ?> total reviews</div>
        </div>
      </div>
    </div>

    <!-- Admin Tabs -->
    <ul class="nav nav-pills gap-2 mb-3" id="adminTabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="btn btn-ss-primary btn-sm active" id="tab-users" data-bs-toggle="tab" data-bs-target="#pane-users" type="button" role="tab">Users (<?= count($allUsers) ?>)</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="btn btn-ss-outline btn-sm" id="tab-requests" data-bs-toggle="tab" data-bs-target="#pane-requests" type="button" role="tab">Exchange Requests (<?= count($allRequests) ?>)</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="btn btn-ss-outline btn-sm" id="tab-reviews" data-bs-toggle="tab" data-bs-target="#pane-reviews" type="button" role="tab">Reviews (<?= count($allReviews) ?>)</button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="btn btn-ss-outline btn-sm" id="tab-skills" data-bs-toggle="tab" data-bs-target="#pane-skills" type="button" role="tab">Top Skills</button>
      </li>
    </ul>

    <div class="tab-content">
      <!-- Users Management -->
      <div class="tab-pane fade show active" id="pane-users" role="tabpanel">
        <div class="ss-card p-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0">Community members</h2>
            <input type="search" class="form-control ss-input" id="adminUserSearch" placeholder="Filter users by name or email..." style="max-width: 260px;">
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr class="text-muted small">
                  <th>User</th>
                  <th>Skills</th>
                  <th>Rating</th>
                  <th>Role</th>
                  <th>Status</th>
                  <th class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody id="adminUsersTable">
                <?php foreach ($allUsers as $u): ?>
                  <tr data-user-row="<?= (int)$u['id'] ?>" data-search="<?= e(mb_strtolower($u['name'] . ' ' . $u['email'])) ?>">
                    <td>
                      <div class="d-flex align-items-center gap-2">
                        <span class="avatar-circle avatar-circle-sm" style="background-color: <?= e($u['avatar_color']) ?>;"><?= e($u['initials']) ?></span>
                        <div>
                          <div class="fw-semibold small"><?= e($u['name']) ?></div>
                          <div class="text-muted" style="font-size: 0.78rem;"><?= e($u['email']) ?></div>
                        </div>
                      </div>
                    </td>
                    <td>
                      <div class="d-flex flex-wrap gap-1">
                        <?php foreach (array_slice($u['teach_skills'], 0, 2) as $sk): ?>
                          <span class="pill-teach py-0 px-2" style="font-size: 0.72rem;"><?= e($sk) ?></span>
                        <?php endforeach; ?>
                        <?php foreach (array_slice($u['learn_skills'], 0, 2) as $sk): ?>
                          <span class="pill-learn py-0 px-2" style="font-size: 0.72rem;"><?= e($sk) ?></span>
                        <?php endforeach; ?>
                      </div>
                    </td>
                    <td class="small">
                      <?= $u['review_count'] > 0 ? '★ ' . number_format((float)$u['avg_rating'], 1) . ' (' . $u['review_count'] . ')' : '—' ?>
                    </td>
                    <td>
                      <span class="badge rounded-pill <?= $u['role'] === 'admin' ? 'text-bg-dark' : 'text-bg-light border text-dark' ?>">
                        <?= e($u['role']) ?>
                      </span>
                    </td>
                    <td>
                      <span class="status-pill status-<?= e($u['status']) ?>"><?= e($u['status']) ?></span>
                    </td>
                    <td class="text-end">
                      <?php if ((int)$u['id'] !== (int)$currentUser['id']): ?>
                        <div class="d-inline-flex gap-1">
                          <button type="button" class="btn btn-ss-outline btn-sm admin-toggle-status" data-id="<?= (int)$u['id'] ?>">
                            <?= $u['status'] === 'active' ? 'Suspend' : 'Activate' ?>
                          </button>
                          <button type="button" class="btn btn-ss-outline btn-sm admin-toggle-role" data-id="<?= (int)$u['id'] ?>">
                            <?= $u['role'] === 'admin' ? 'Make user' : 'Make admin' ?>
                          </button>
                          <button type="button" class="btn btn-ss-outline btn-sm text-danger admin-delete-user" data-id="<?= (int)$u['id'] ?>">
                            Delete
                          </button>
                        </div>
                      <?php else: ?>
                        <span class="text-muted small">Current admin</span>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Exchange Requests Monitor -->
      <div class="tab-pane fade" id="pane-requests" role="tabpanel">
        <div class="ss-card p-4">
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
            <h2 class="h6 fw-bold mb-0">All skill exchange requests</h2>
            <select class="form-select ss-input" id="adminReqStatusFilter" style="max-width: 190px;">
              <option value="all">All statuses</option>
              <option value="pending">Pending</option>
              <option value="accepted">Accepted</option>
              <option value="completed">Completed</option>
              <option value="declined">Declined</option>
              <option value="cancelled">Cancelled</option>
            </select>
          </div>
          <div class="table-responsive">
            <table class="table align-middle mb-0">
              <thead>
                <tr class="text-muted small">
                  <th>Swap</th>
                  <th>Participants</th>
                  <th>Schedule</th>
                  <th>Status</th>
                  <th class="text-end">Manage</th>
                </tr>
              </thead>
              <tbody id="adminRequestsTable">
                <?php foreach ($allRequests as $req): ?>
                  <tr data-req-row="<?= (int)$req['id'] ?>" data-status="<?= e($req['status']) ?>">
                    <td class="fw-semibold small"><?= e($req['title']) ?></td>
                    <td class="small text-secondary"><?= e($req['proposer_name']) ?> &rarr; <?= e($req['recipient_name']) ?></td>
                    <td class="small text-muted"><?= e($req['schedule_text']) ?></td>
                    <td><span class="status-pill status-<?= e($req['status']) ?>"><?= e($req['status']) ?></span></td>
                    <td class="text-end">
                      <div class="d-inline-flex gap-1">
                        <select class="form-select form-select-sm ss-input admin-req-status-select" data-id="<?= (int)$req['id'] ?>" style="width: auto;">
                          <?php foreach (['pending', 'accepted', 'completed', 'declined', 'cancelled'] as $opt): ?>
                            <option value="<?= $opt ?>" <?= $req['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                          <?php endforeach; ?>
                        </select>
                        <button type="button" class="btn btn-ss-outline btn-sm text-danger admin-delete-req" data-id="<?= (int)$req['id'] ?>">Delete</button>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Reviews Moderation -->
      <div class="tab-pane fade" id="pane-reviews" role="tabpanel">
        <div class="ss-card p-4">
          <h2 class="h6 fw-bold mb-3">Community reviews</h2>
          <?php if (empty($allReviews)): ?>
            <p class="text-muted small mb-0">No reviews recorded yet.</p>
          <?php else: ?>
            <div class="d-flex flex-column gap-2">
              <?php foreach ($allReviews as $rev): ?>
                <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 p-3 rounded-3 border" style="border-color: var(--ss-border) !important;" data-review-row="<?= (int)$rev['id'] ?>">
                  <div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                      <span class="fw-semibold small"><?= e($rev['reviewer_name']) ?></span>
                      <span class="text-muted small">reviewed</span>
                      <span class="fw-semibold small"><?= e($rev['reviewee_name']) ?></span>
                      <span class="rating-badge"><?= str_repeat('★', (int)$rev['rating']) ?></span>
                      <?php if ($rev['skill_practiced'] !== ''): ?>
                        <span class="pill-teach py-0 px-2" style="font-size: 0.72rem;"><?= e($rev['skill_practiced']) ?></span>
                      <?php endif; ?>
                    </div>
                    <div class="text-secondary small"><?= e($rev['comment']) ?></div>
                  </div>
                  <button type="button" class="btn btn-ss-outline btn-sm text-danger admin-delete-review" data-id="<?= (int)$rev['id'] ?>">Remove</button>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>

      <!-- Top Skills -->
      <div class="tab-pane fade" id="pane-skills" role="tabpanel">
        <div class="row g-4">
          <div class="col-md-6">
            <div class="ss-card p-4 h-100">
              <h2 class="h6 fw-bold mb-3">Most offered skills (Teach)</h2>
              <?php $maxTeach = max(1, (int)($topTeach[0]['cnt'] ?? 1)); ?>
              <?php foreach ($topTeach as $sk): ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-semibold"><?= e($sk['skill_name']) ?></span>
                    <span class="text-muted"><?= (int)$sk['cnt'] ?> member(s)</span>
                  </div>
                  <div class="progress" style="height: 8px; background-color: #eef2ff;">
                    <div class="progress-bar" style="width: <?= round(($sk['cnt'] / $maxTeach) * 100) ?>%; background-color: var(--ss-primary);"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-md-6">
            <div class="ss-card p-4 h-100">
              <h2 class="h6 fw-bold mb-3">Most requested skills (Learn)</h2>
              <?php $maxLearn = max(1, (int)($topLearn[0]['cnt'] ?? 1)); ?>
              <?php foreach ($topLearn as $sk): ?>
                <div class="mb-3">
                  <div class="d-flex justify-content-between small mb-1">
                    <span class="fw-semibold"><?= e($sk['skill_name']) ?></span>
                    <span class="text-muted"><?= (int)$sk['cnt'] ?> member(s)</span>
                  </div>
                  <div class="progress" style="height: 8px; background-color: #d6f3eb;">
                    <div class="progress-bar" style="width: <?= round(($sk['cnt'] / $maxLearn) * 100) ?>%; background-color: #0F766E;"></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
