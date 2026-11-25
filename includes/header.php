<?php
declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$pdo = getDb();
$currentUser = getCurrentUser($pdo);
$loggedIn = isLoggedIn();
$activePage = $activePage ?? '';
$pageTitle = $pageTitle ?? 'SkillSwap';

$pendingIncomingStmt = $pdo->prepare('SELECT COUNT(*) FROM swap_proposals WHERE recipient_id = ? AND status = "pending"');
$pendingIncomingStmt->execute([(int)$currentUser['id']]);
$pendingIncomingCount = (int)$pendingIncomingStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= e($pageTitle) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
  <nav class="navbar navbar-expand-md ss-navbar sticky-top">
    <div class="container-xxl px-4">
      <div class="d-flex align-items-center gap-4">
        <a class="ss-brand" href="index.php">SkillSwap</a>
        <div class="d-none d-md-flex align-items-center gap-3 ms-2">
          <a class="ss-nav-link <?= $activePage === 'discover' ? 'active' : '' ?>" href="discover.php">Discover</a>
          <a class="ss-nav-link <?= $activePage === 'chats' ? 'active' : '' ?>" href="chats.php">
            Chats<?php if ($pendingIncomingCount > 0): ?><span class="badge rounded-pill ms-1" style="background-color: var(--ss-lavender-bg); color: var(--ss-primary); font-size: 0.72rem;"><?= $pendingIncomingCount ?></span><?php endif; ?>
          </a>
          <a class="ss-nav-link <?= $activePage === 'profile' ? 'active' : '' ?>" href="profile.php">Profile</a>
          <a class="ss-nav-link <?= $activePage === 'admin' ? 'active' : '' ?>" href="admin.php">Admin</a>
        </div>
      </div>

      <button class="navbar-toggler border-0 shadow-none" type="button" data-bs-toggle="collapse" data-bs-target="#ssNavMenu" aria-controls="ssNavMenu" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse justify-content-end" id="ssNavMenu">
        <div class="d-flex d-md-none flex-column gap-2 pt-3 pb-2">
          <a class="ss-nav-link <?= $activePage === 'discover' ? 'active' : '' ?>" href="discover.php">Discover</a>
          <a class="ss-nav-link <?= $activePage === 'chats' ? 'active' : '' ?>" href="chats.php">
            Chats<?php if ($pendingIncomingCount > 0): ?><span class="badge rounded-pill ms-1" style="background-color: var(--ss-lavender-bg); color: var(--ss-primary); font-size: 0.72rem;"><?= $pendingIncomingCount ?></span><?php endif; ?>
          </a>
          <a class="ss-nav-link <?= $activePage === 'profile' ? 'active' : '' ?>" href="profile.php">Profile</a>
          <a class="ss-nav-link <?= $activePage === 'admin' ? 'active' : '' ?>" href="admin.php">Admin</a>
        </div>

        <div class="d-flex align-items-center gap-2 mt-2 mt-md-0">
          <?php if ($loggedIn): ?>
            <a href="profile.php" class="d-inline-flex align-items-center gap-2 text-decoration-none me-1 px-2 py-1 rounded-3 border bg-white" style="border-color: var(--ss-border) !important;">
              <span class="avatar-circle avatar-circle-sm" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
                <?= e($currentUser['initials']) ?>
              </span>
              <span class="fw-semibold text-dark small"><?= e($currentUser['name']) ?></span>
            </a>
            <button type="button" class="btn btn-ss-outline btn-ss-nav" id="logoutBtn">Log out</button>
          <?php else: ?>
            <button type="button" class="btn btn-ss-outline btn-ss-nav" data-bs-toggle="modal" data-bs-target="#loginModal">Log in</button>
            <button type="button" class="btn btn-ss-primary btn-ss-nav" data-bs-toggle="modal" data-bs-target="#signupModal">Sign up</button>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>
