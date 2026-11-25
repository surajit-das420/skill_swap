<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = getDb();
$myId = getActiveUserId();
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'update_details') {
    $name = trim((string)($input['name'] ?? ''));
    $availability = trim((string)($input['availability'] ?? ''));
    $location = trim((string)($input['location'] ?? ''));
    $bio = trim((string)($input['bio'] ?? ''));

    if ($name === '' || $availability === '') {
        jsonResponse(['ok' => false, 'error' => 'Name and availability are required.'], 422);
    }

    $initials = makeInitials($name);
    $stmt = $pdo->prepare('UPDATE users SET name = ?, initials = ?, availability = ?, location = ?, bio = ? WHERE id = ?');
    $stmt->execute([$name, $initials, $availability, $location, $bio, $myId]);

    jsonResponse(['ok' => true, 'user' => getUserWithSkills($pdo, $myId)]);
}

if ($action === 'add_skill') {
    $skill = trim((string)($input['skill'] ?? ''));
    $type = ($input['type'] ?? '') === 'teach' ? 'teach' : 'learn';

    if ($skill === '') {
        jsonResponse(['ok' => false, 'error' => 'Skill name is required.'], 422);
    }

    $stmt = $pdo->prepare('INSERT OR IGNORE INTO user_skills (user_id, skill_name, skill_type, sort_order) VALUES (?, ?, ?, 99)');
    $stmt->execute([$myId, $skill, $type]);

    jsonResponse(['ok' => true, 'user' => getUserWithSkills($pdo, $myId)]);
}

if ($action === 'remove_skill') {
    $skill = trim((string)($input['skill'] ?? ''));
    $type = ($input['type'] ?? '') === 'teach' ? 'teach' : 'learn';

    $stmt = $pdo->prepare('DELETE FROM user_skills WHERE user_id = ? AND skill_name = ? AND skill_type = ?');
    $stmt->execute([$myId, $skill, $type]);

    jsonResponse(['ok' => true, 'user' => getUserWithSkills($pdo, $myId)]);
}

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
