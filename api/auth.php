<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = getDb();
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? ($_GET['action'] ?? '');

if ($action === 'login') {
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');

    if ($email === '' || $password === '') {
        jsonResponse(['ok' => false, 'error' => 'Email and password are required.'], 422);
    }

    $stmt = $pdo->prepare('SELECT id, password_hash, role, status FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(['ok' => false, 'error' => 'Invalid email or password.'], 401);
    }

    if (($user['status'] ?? 'active') === 'suspended') {
        jsonResponse(['ok' => false, 'error' => 'This account is suspended. Contact an administrator.'], 403);
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    jsonResponse(['ok' => true, 'role' => $user['role']]);
}

if ($action === 'demo_admin') {
    $stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active' ORDER BY id ASC LIMIT 1");
    $adminId = (int)$stmt->fetchColumn();
    if ($adminId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Demo admin account unavailable.'], 404);
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $adminId;
    jsonResponse(['ok' => true]);
}

if ($action === 'signup') {
    $name = trim((string)($input['name'] ?? ''));
    $email = trim((string)($input['email'] ?? ''));
    $password = (string)($input['password'] ?? '');
    $teachRaw = trim((string)($input['teach_skills'] ?? ''));
    $learnRaw = trim((string)($input['learn_skills'] ?? ''));
    $availability = trim((string)($input['availability'] ?? 'Weekends and evenings'));

    if ($name === '' || $email === '' || strlen($password) < 6) {
        jsonResponse(['ok' => false, 'error' => 'Please enter a name, valid email, and password (6+ chars).'], 422);
    }

    $check = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(?) LIMIT 1');
    $check->execute([$email]);
    if ($check->fetch()) {
        jsonResponse(['ok' => false, 'error' => 'An account with that email already exists.'], 409);
    }

    $initials = makeInitials($name);
    $color = pickAvatarColor($email);
    $hash = password_hash($password, PASSWORD_DEFAULT);

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, password_hash, initials, avatar_color, availability, location, bio, role, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, "user", "active")'
        );
        $stmt->execute([$name, $email, $hash, $initials, $color, $availability ?: 'Weekends and evenings', 'Online', 'New SkillSwap member ready to learn and teach.']);
        $newId = (int)$pdo->lastInsertId();

        $insertSkill = $pdo->prepare('INSERT OR IGNORE INTO user_skills (user_id, skill_name, skill_type, sort_order) VALUES (?, ?, ?, ?)');
        $teachList = array_values(array_filter(array_map('trim', explode(',', $teachRaw))));
        $learnList = array_values(array_filter(array_map('trim', explode(',', $learnRaw))));

        foreach ($teachList as $idx => $sk) {
            $insertSkill->execute([$newId, $sk, 'teach', $idx]);
        }
        foreach ($learnList as $idx => $sk) {
            $insertSkill->execute([$newId, $sk, 'learn', $idx]);
        }

        $pdo->commit();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $newId;
        jsonResponse(['ok' => true, 'user_id' => $newId]);
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Could not create account.'], 500);
    }
}

if ($action === 'logout') {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
