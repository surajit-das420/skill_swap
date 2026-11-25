<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = getDb();
$myId = getActiveUserId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
    if ($userId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid user_id.'], 400);
    }

    $user = getUserWithSkills($pdo, $userId);
    if (!$user) {
        jsonResponse(['ok' => false, 'error' => 'User not found.'], 404);
    }

    $reviews = getUserReviews($pdo, $userId, 20);
    jsonResponse([
        'ok' => true,
        'avg_rating' => $user['avg_rating'],
        'review_count' => $user['review_count'],
        'reviews' => $reviews,
    ]);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? 'create_review';

if ($action === 'create_review') {
    $revieweeId = (int)($input['reviewee_id'] ?? 0);
    $proposalId = !empty($input['proposal_id']) ? (int)$input['proposal_id'] : null;
    $rating = (int)($input['rating'] ?? 0);
    $skillPracticed = trim((string)($input['skill_practiced'] ?? ''));
    $comment = trim((string)($input['comment'] ?? ''));

    if ($revieweeId <= 0 || $revieweeId === $myId || $rating < 1 || $rating > 5 || $comment === '') {
        jsonResponse(['ok' => false, 'error' => 'Please select a rating (1–5) and write a comment.'], 422);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO reviews (reviewer_id, reviewee_id, proposal_id, rating, skill_practiced, comment)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([$myId, $revieweeId, $proposalId, $rating, $skillPracticed, $comment]);

    $updatedUser = getUserWithSkills($pdo, $revieweeId);
    $reviews = getUserReviews($pdo, $revieweeId, 20);

    jsonResponse([
        'ok' => true,
        'avg_rating' => $updatedUser['avg_rating'] ?? 0,
        'review_count' => $updatedUser['review_count'] ?? 0,
        'reviews' => $reviews,
    ]);
}

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
