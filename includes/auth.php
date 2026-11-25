<?php
declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    $sessionDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($sessionDir)) {
        @mkdir($sessionDir, 0775, true);
    }
    if (is_dir($sessionDir)) {
        session_save_path($sessionDir);
    }
    session_start();
}

function isLoggedIn(): bool
{
    return isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] > 0;
}

function getActiveUserId(): int
{
    if (isLoggedIn()) {
        return (int)$_SESSION['user_id'];
    }
    return 1;
}

function isAdminUser(array $user): bool
{
    return ($user['role'] ?? 'user') === 'admin';
}

/**
 * Fetches a user row along with teach/learn skills and review rating stats.
 */
function getUserWithSkills(PDO $pdo, int $userId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id, name, email, initials, avatar_color, availability, location, bio, role, status, created_at
         FROM users WHERE id = ?'
    );
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    if (!$user) {
        return null;
    }

    $skillStmt = $pdo->prepare(
        'SELECT skill_name, skill_type FROM user_skills WHERE user_id = ? ORDER BY sort_order ASC, id ASC'
    );
    $skillStmt->execute([$userId]);
    $teach = [];
    $learn = [];
    foreach ($skillStmt->fetchAll() as $row) {
        if ($row['skill_type'] === 'teach') {
            $teach[] = $row['skill_name'];
        } else {
            $learn[] = $row['skill_name'];
        }
    }

    $revStmt = $pdo->prepare(
        'SELECT COUNT(*) AS cnt, COALESCE(ROUND(AVG(rating), 1), 0) AS avg_rating
         FROM reviews WHERE reviewee_id = ?'
    );
    $revStmt->execute([$userId]);
    $revStats = $revStmt->fetch() ?: ['cnt' => 0, 'avg_rating' => 0];

    $user['teach_skills'] = $teach;
    $user['learn_skills'] = $learn;
    $user['review_count'] = (int)$revStats['cnt'];
    $user['avg_rating'] = (float)$revStats['avg_rating'];

    return $user;
}

function getUserReviews(PDO $pdo, int $userId, int $limit = 10): array
{
    $stmt = $pdo->prepare(
        'SELECT r.*, u.name AS reviewer_name, u.initials AS reviewer_initials, u.avatar_color AS reviewer_color
         FROM reviews r
         JOIN users u ON u.id = r.reviewer_id
         WHERE r.reviewee_id = ?
         ORDER BY r.id DESC
         LIMIT ?'
    );
    $stmt->execute([$userId, $limit]);
    return $stmt->fetchAll();
}

function getCurrentUser(PDO $pdo): array
{
    $user = getUserWithSkills($pdo, getActiveUserId());
    if (!$user || $user['status'] === 'suspended') {
        $_SESSION['user_id'] = 1;
        $user = getUserWithSkills($pdo, 1);
    }
    return $user;
}

function makeInitials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    if (count($parts) >= 2) {
        return strtoupper(mb_substr($parts[0], 0, 1) . mb_substr($parts[count($parts) - 1], 0, 1));
    }
    return strtoupper(mb_substr(trim($name), 0, 2));
}

function pickAvatarColor(string $seed): string
{
    $palette = ['#503CE8', '#0F766E', '#9A3412', '#4338CA', '#047857', '#7C2D12'];
    $index = abs(crc32($seed)) % count($palette);
    return $palette[$index];
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}
