<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/matching.php';

$pdo = getDb();
$myId = getActiveUserId();
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $learnRaw = (string)($input['learn_skills'] ?? '');
    $teachRaw = (string)($input['teach_skills'] ?? '');
    $availability = trim((string)($input['availability'] ?? 'Weekends and evenings'));

    $learnList = array_values(array_unique(array_filter(array_map('trim', explode(',', $learnRaw)))));
    $teachList = array_values(array_unique(array_filter(array_map('trim', explode(',', $teachRaw)))));

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE users SET availability = ? WHERE id = ?')->execute([$availability ?: 'Flexible', $myId]);
        $pdo->prepare('DELETE FROM user_skills WHERE user_id = ?')->execute([$myId]);

        $ins = $pdo->prepare('INSERT INTO user_skills (user_id, skill_name, skill_type, sort_order) VALUES (?, ?, ?, ?)');
        foreach ($teachList as $idx => $sk) {
            $ins->execute([$myId, $sk, 'teach', $idx]);
        }
        foreach ($learnList as $idx => $sk) {
            $ins->execute([$myId, $sk, 'learn', $idx]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        jsonResponse(['ok' => false, 'error' => 'Failed to update filters.'], 500);
    }
}

$me = getCurrentUser($pdo);
$filters = [
    'q' => $_GET['q'] ?? ($input['q'] ?? ''),
    'teach' => $_GET['teach'] ?? ($input['teach'] ?? ''),
    'learn' => $_GET['learn'] ?? ($input['learn'] ?? ''),
    'availability' => $_GET['availability'] ?? ($input['availability'] ?? ''),
    'mode' => $_GET['mode'] ?? ($input['mode'] ?? 'best'),
];

$matches = searchAndMatchUsers($pdo, $me, $filters);

jsonResponse([
    'ok' => true,
    'user' => [
        'learn_skills' => $me['learn_skills'],
        'teach_skills' => $me['teach_skills'],
        'availability' => $me['availability'],
    ],
    'matches' => $matches,
]);
