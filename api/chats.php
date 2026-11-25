<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/proposals.php';

$pdo = getDb();
$myId = getActiveUserId();

function getConversationList(PDO $pdo, int $myId, int $ensurePartnerId = 0): array
{
    $convStmt = $pdo->prepare(
        'SELECT u.id, u.name, u.initials, u.avatar_color, u.availability, u.location, u.bio,
                (
                  SELECT m.body FROM messages m
                  WHERE (m.sender_id = :uid AND m.receiver_id = u.id)
                     OR (m.sender_id = u.id AND m.receiver_id = :uid)
                  ORDER BY m.id DESC LIMIT 1
                ) AS last_message,
                (
                  SELECT m.id FROM messages m
                  WHERE (m.sender_id = :uid AND m.receiver_id = u.id)
                     OR (m.sender_id = u.id AND m.receiver_id = :uid)
                  ORDER BY m.id DESC LIMIT 1
                ) AS last_msg_id
         FROM users u
         WHERE u.id != :uid AND u.status = "active" AND u.role != "admin"
           AND (
             EXISTS (SELECT 1 FROM messages m WHERE (m.sender_id = :uid AND m.receiver_id = u.id) OR (m.sender_id = u.id AND m.receiver_id = :uid))
             OR EXISTS (SELECT 1 FROM swap_proposals sp WHERE (sp.proposer_id = :uid AND sp.recipient_id = u.id) OR (sp.proposer_id = u.id AND sp.recipient_id = :uid))
             OR u.id = :req_id
           )
         ORDER BY COALESCE(last_msg_id, 0) DESC, u.id ASC'
    );
    $convStmt->execute([':uid' => $myId, ':req_id' => $ensurePartnerId]);
    return $convStmt->fetchAll();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $partnerId = isset($_GET['partner_id']) ? (int)$_GET['partner_id'] : 0;
    $afterId = isset($_GET['after_id']) ? (int)$_GET['after_id'] : 0;
    $mode = $_GET['action'] ?? 'poll';

    if ($partnerId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid partner_id.'], 400);
    }

    $messages = fetchThreadMessages($pdo, $myId, $partnerId, $afterId);
    $proposal = fetchLatestThreadProposal($pdo, $myId, $partnerId);

    $payload = [
        'ok' => true,
        'messages' => $messages,
        'proposal' => $proposal,
    ];

    if ($mode === 'thread') {
        $partner = getUserWithSkills($pdo, $partnerId);
        if ($partner) {
            $partner['reviews'] = getUserReviews($pdo, $partnerId, 5);
        }
        $payload['partner'] = $partner;
        $payload['conversations'] = getConversationList($pdo, $myId, $partnerId);
    }

    jsonResponse($payload);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'send_message') {
    $partnerId = (int)($input['partner_id'] ?? 0);
    $body = trim((string)($input['body'] ?? ''));

    if ($partnerId <= 0 || $body === '') {
        jsonResponse(['ok' => false, 'error' => 'Message cannot be empty.'], 422);
    }

    $stmt = $pdo->prepare('INSERT INTO messages (sender_id, receiver_id, body, message_type) VALUES (?, ?, ?, "text")');
    $stmt->execute([$myId, $partnerId, $body]);
    $newId = (int)$pdo->lastInsertId();

    jsonResponse([
        'ok' => true,
        'message' => fetchMessageById($pdo, $newId),
    ]);
}

if (in_array($action, ['accept_proposal', 'decline_proposal', 'complete_proposal', 'cancel_proposal'], true)) {
    $proposalId = (int)($input['proposal_id'] ?? 0);
    if ($proposalId <= 0) {
        jsonResponse(['ok' => false, 'error' => 'Invalid proposal_id.'], 400);
    }

    $statusMap = [
        'accept_proposal' => 'accepted',
        'decline_proposal' => 'declined',
        'complete_proposal' => 'completed',
        'cancel_proposal' => 'cancelled',
    ];

    try {
        $result = transitionProposalStatusWithMessage($pdo, $proposalId, $myId, $statusMap[$action]);
        jsonResponse([
            'ok' => true,
            'proposal' => $result['proposal'],
            'message' => $result['message'],
        ]);
    } catch (RuntimeException $e) {
        $code = $e->getCode() >= 400 ? $e->getCode() : 400;
        jsonResponse(['ok' => false, 'error' => $e->getMessage()], $code);
    }
}

if ($action === 'save_proposal') {
    $partnerId = (int)($input['partner_id'] ?? 0);
    $title = trim((string)($input['title'] ?? ''));
    $scheduleText = trim((string)($input['schedule_text'] ?? ''));
    $note = trim((string)($input['note'] ?? ''));

    if ($partnerId <= 0 || $partnerId === $myId || $title === '' || $scheduleText === '') {
        jsonResponse(['ok' => false, 'error' => 'Swap summary and schedule are required.'], 422);
    }

    try {
        $result = createOrUpdateProposalWithMessage($pdo, $myId, $partnerId, $title, $scheduleText, '', '', $note);
        jsonResponse([
            'ok' => true,
            'proposal' => $result['proposal'],
            'message' => $result['message'],
        ]);
    } catch (Throwable $e) {
        jsonResponse(['ok' => false, 'error' => 'Could not send swap proposal.'], 500);
    }
}

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
