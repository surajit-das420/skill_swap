<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/auth.php';
require_once dirname(__DIR__) . '/includes/proposals.php';

$pdo = getDb();
$myId = getActiveUserId();

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    $stmt = $pdo->prepare(
        'SELECT sp.*,
                p.name AS proposer_name, p.initials AS proposer_initials, p.avatar_color AS proposer_color,
                r.name AS recipient_name, r.initials AS recipient_initials, r.avatar_color AS recipient_color
         FROM swap_proposals sp
         JOIN users p ON p.id = sp.proposer_id
         JOIN users r ON r.id = sp.recipient_id
         WHERE sp.proposer_id = ? OR sp.recipient_id = ?
         ORDER BY sp.id DESC'
    );
    $stmt->execute([$myId, $myId]);
    jsonResponse(['ok' => true, 'requests' => $stmt->fetchAll()]);
}

$input = json_decode(file_get_contents('php://input') ?: '{}', true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = $input['action'] ?? '';

if ($action === 'create_request') {
    $partnerId = (int)($input['partner_id'] ?? 0);
    $learnSkill = trim((string)($input['learn_skill'] ?? ''));
    $teachSkill = trim((string)($input['teach_skill'] ?? ''));
    $scheduleText = trim((string)($input['schedule_text'] ?? ''));
    $note = trim((string)($input['note'] ?? ''));

    if ($partnerId <= 0 || $partnerId === $myId || $learnSkill === '' || $teachSkill === '' || $scheduleText === '') {
        jsonResponse(['ok' => false, 'error' => 'Partner, both skills, and schedule are required.'], 422);
    }

    $title = "1 hr {$learnSkill} for 1 hr {$teachSkill}";

    try {
        $result = createOrUpdateProposalWithMessage($pdo, $myId, $partnerId, $title, $scheduleText, $teachSkill, $learnSkill, $note);
        jsonResponse([
            'ok' => true,
            'proposal' => $result['proposal'],
            'message' => $result['message'],
        ]);
    } catch (Throwable $e) {
        jsonResponse(['ok' => false, 'error' => 'Could not create exchange request.'], 500);
    }
}

if ($action === 'update_status') {
    $proposalId = (int)($input['proposal_id'] ?? 0);
    $newStatus = trim((string)($input['status'] ?? ''));
    $allowed = ['accepted', 'declined', 'completed', 'cancelled'];

    if ($proposalId <= 0 || !in_array($newStatus, $allowed, true)) {
        jsonResponse(['ok' => false, 'error' => 'Invalid request or status.'], 422);
    }

    try {
        $result = transitionProposalStatusWithMessage($pdo, $proposalId, $myId, $newStatus);
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

jsonResponse(['ok' => false, 'error' => 'Unsupported action.'], 400);
