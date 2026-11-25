<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';

$pdo = getDb();
$currentUser = getCurrentUser($pdo);

if (!isLoggedIn() || !isAdminUser($currentUser)) {
    jsonResponse(['ok' => false, 'error' => 'Admin privileges required.'], 403);
}

$myId = (int)$currentUser['id'];
$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'toggle_user_status') {
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0 || $userId === $myId) {
        jsonResponse(['ok' => false, 'error' => 'Cannot change status of this user.'], 422);
    }

    $cur = $pdo->prepare('SELECT status FROM users WHERE id = ?');
    $cur->execute([$userId]);
    $status = $cur->fetchColumn();
    if (!$status) {
        jsonResponse(['ok' => false, 'error' => 'User not found.'], 404);
    }

    $next = $status === 'active' ? 'suspended' : 'active';
    $pdo->prepare('UPDATE users SET status = ? WHERE id = ?')->execute([$next, $userId]);
    jsonResponse(['ok' => true, 'status' => $next]);
}

if ($action === 'toggle_user_role') {
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0 || $userId === $myId) {
        jsonResponse(['ok' => false, 'error' => 'Cannot change role of your own account.'], 422);
    }

    $cur = $pdo->prepare('SELECT role FROM users WHERE id = ?');
    $cur->execute([$userId]);
    $role = $cur->fetchColumn();
    if (!$role) {
        jsonResponse(['ok' => false, 'error' => 'User not found.'], 404);
    }

    $next = $role === 'admin' ? 'user' : 'admin';
    $pdo->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$next, $userId]);
    jsonResponse(['ok' => true, 'role' => $next]);
}

if ($action === 'delete_user') {
    $userId = (int)($input['user_id'] ?? 0);
    if ($userId <= 0 || $userId === $myId) {
        jsonResponse(['ok' => false, 'error' => 'Cannot delete your own admin account.'], 422);
    }
    $pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$userId]);
    jsonResponse(['ok' => true]);
}

if ($action === 'update_request_status') {
    $proposalId = (int)($input['proposal_id'] ?? 0);
    $status = trim((string)($input['status'] ?? ''));
    $allowed = ['pending', 'accepted', 'completed', 'declined', 'cancelled'];
    if ($proposalId <= 0 || !in_array($status, $allowed, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid request or status.'], 422);
    }
    $pdo->prepare('UPDATE swap_proposals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$status, $proposalId]);
    jsonResponse(['ok' => true, 'status' => $status]);
}

if ($action === 'delete_request') {
    $proposalId = (int)($input['proposal_id'] ?? 0);
    if ($proposalId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid proposal_id.'], 422);
    }
    $pdo->prepare('DELETE FROM swap_proposals WHERE id = ?')->execute([$proposalId]);
    jsonResponse(['ok' => true]);
}

if ($action === 'delete_review') {
    $reviewId = (int)($input['review_id'] ?? 0);
    if ($reviewId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid review_id.'], 422);
    }
    $pdo->prepare('DELETE FROM reviews WHERE id = ?')->execute([$reviewId]);
    jsonResponse(['ok' => true]);
}

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
