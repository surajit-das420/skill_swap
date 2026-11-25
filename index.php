<?php
declare(strict_types=1);

$activePage = 'home';
$pageTitle = 'SkillSwap — Trade what you know for what you want to learn';
require_once __DIR__ . '/includes/header.php';

// Pull featured swap in progress for the hero preview card
$featuredStmt = $pdo->prepare(
    'SELECT sp.title, sp.teach_skill, sp.learn_skill, sp.schedule_text,
            u.name AS partner_name, u.initials AS partner_initials, u.avatar_color AS partner_color
     FROM swap_proposals sp
     JOIN users u ON u.id = CASE WHEN sp.proposer_id = :uid THEN sp.recipient_id ELSE sp.proposer_id END
     WHERE sp.proposer_id = :uid OR sp.recipient_id = :uid
     ORDER BY CASE WHEN sp.status = "accepted" THEN 0 ELSE 1 END, sp.id ASC
     LIMIT 1'
);
$featuredStmt->execute([':uid' => $currentUser['id']]);
$featuredSwap = $featuredStmt->fetch();

$partnerFirstName = $featuredSwap ? explode(' ', $featuredSwap['partner_name'])[0] : 'Aanya';
$partnerInitials = $featuredSwap['partner_initials'] ?? 'AS';
$partnerColor = $featuredSwap['partner_color'] ?? '#503CE8';
$partnerSkill = !empty($featuredSwap['teach_skill']) ? $featuredSwap['teach_skill'] : 'Python';
$yourSkill = !empty($featuredSwap['learn_skill']) ? $featuredSwap['learn_skill'] : ($currentUser['teach_skills'][0] ?? 'Guitar');
$swapSchedule = $featuredSwap['schedule_text'] ?? 'Saturday, 4:00 PM, video call';
?>

<main class="container-xxl px-4">
  <section class="hero-section">
    <div class="row align-items-center g-5">
      <div class="col-lg-6">
        <h1 class="hero-title">Trade what you know for what you want to learn</h1>
        <p class="hero-subtitle">
          Match with people who teach your next skill and want to learn yours. Chat, agree on a swap, and learn together.
        </p>
        <div class="d-flex flex-wrap align-items-center gap-3">
          <a href="discover.php" class="btn btn-ss-primary px-4 py-2">Get started</a>
          <a href="#how-it-works" class="btn btn-ss-outline px-4 py-2">See how it works</a>
        </div>
      </div>

      <div class="col-lg-5 offset-lg-1">
        <div class="ss-card hero-swap-card">
          <div class="ss-section-label mb-3">A swap in progress</div>

          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="avatar-circle" style="background-color: <?= e($partnerColor) ?>;">
              <?= e($partnerInitials) ?>
            </span>
            <span class="fw-bold text-dark"><?= e($partnerFirstName) ?></span>
            <span class="pill-teach">Teaches <?= e($partnerSkill) ?></span>
          </div>

          <div class="d-flex align-items-center gap-3 mb-3">
            <span class="avatar-circle" style="background-color: <?= e($currentUser['avatar_color']) ?>;">
              <?= e($currentUser['initials']) ?>
            </span>
            <span class="fw-bold text-dark">You</span>
            <span class="pill-learn">Teach <?= e($yourSkill) ?></span>
          </div>

          <div class="text-muted small pt-1"><?= e($swapSchedule) ?></div>
        </div>
      </div>
    </div>
  </section>

  <section id="how-it-works" class="pb-5 pt-2">
    <div class="row g-4">
      <div class="col-md-4">
        <div class="ss-card step-card">
          <h3>1. Pick your skills</h3>
          <p>List what you can teach and what you want to learn.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ss-card step-card">
          <h3>2. Get matched</h3>
          <p>See people whose skills fit yours both ways.</p>
        </div>
      </div>
      <div class="col-md-4">
        <div class="ss-card step-card">
          <h3>3. Chat and swap</h3>
          <p>Message, propose a time, and trade lessons.</p>
        </div>
      </div>
    </div>
  </section>
</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
