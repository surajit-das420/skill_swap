<?php
declare(strict_types=1);

/**
 * Shared helpers for swap proposals and linked chat acceptance messages.
 */

function fetchEnrichedProposal(PDO $pdo, int $proposalId, int $viewerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT sp.*,
                p.name AS proposer_name,
                r.name AS recipient_name
         FROM swap_proposals sp
         JOIN users p ON p.id = sp.proposer_id
         JOIN users r ON r.id = sp.recipient_id
         WHERE sp.id = ?'
    );
    $stmt->execute([$proposalId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }

    $row['is_proposer'] = ((int)$row['proposer_id'] === $viewerId);
    $row['is_recipient'] = ((int)$row['recipient_id'] === $viewerId);
    return $row;
}

function fetchLatestThreadProposal(PDO $pdo, int $userId, int $partnerId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT id FROM swap_proposals
         WHERE (proposer_id = ? AND recipient_id = ?) OR (proposer_id = ? AND recipient_id = ?)
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$userId, $partnerId, $partnerId, $userId]);
    $id = (int)$stmt->fetchColumn();
    if ($id <= 0) {
        return null;
    }
    return fetchEnrichedProposal($pdo, $id, $userId);
}

function fetchThreadMessages(PDO $pdo, int $userId, int $partnerId, int $afterId = 0): array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_id, m.receiver_id, m.body, m.message_type, m.proposal_id, m.created_at,
                sp.title AS proposal_title, sp.schedule_text AS proposal_schedule, sp.note AS proposal_note,
                sp.status AS proposal_status, sp.proposer_id AS proposal_proposer_id, sp.recipient_id AS proposal_recipient_id
         FROM messages m
         LEFT JOIN swap_proposals sp ON sp.id = m.proposal_id
         WHERE ((m.sender_id = ? AND m.receiver_id = ?) OR (m.sender_id = ? AND m.receiver_id = ?))
           AND m.id > ?
         ORDER BY m.id ASC'
    );
    $stmt->execute([$userId, $partnerId, $partnerId, $userId, $afterId]);
    return $stmt->fetchAll();
}

function fetchMessageById(PDO $pdo, int $messageId): ?array
{
    $stmt = $pdo->prepare(
        'SELECT m.id, m.sender_id, m.receiver_id, m.body, m.message_type, m.proposal_id, m.created_at,
                sp.title AS proposal_title, sp.schedule_text AS proposal_schedule, sp.note AS proposal_note,
                sp.status AS proposal_status, sp.proposer_id AS proposal_proposer_id, sp.recipient_id AS proposal_recipient_id
         FROM messages m
         LEFT JOIN swap_proposals sp ON sp.id = m.proposal_id
         WHERE m.id = ?'
    );
    $stmt->execute([$messageId]);
    return $stmt->fetch() ?: null;
}

/**
 * Creates or updates an active swap proposal and sends an acceptance-request message to the recipient.
 */
function createOrUpdateProposalWithMessage(
    PDO $pdo,
    int $proposerId,
    int $recipientId,
    string $title,
    string $scheduleText,
    string $teachSkill = '',
    string $learnSkill = '',
    string $note = ''
): array {
    $pdo->beginTransaction();
    try {
        $existingStmt = $pdo->prepare(
            'SELECT id FROM swap_proposals
             WHERE ((proposer_id = ? AND recipient_id = ?) OR (proposer_id = ? AND recipient_id = ?))
               AND status IN ("pending", "accepted")
             ORDER BY id DESC LIMIT 1'
        );
        $existingStmt->execute([$proposerId, $recipientId, $recipientId, $proposerId]);
        $existingId = (int)$existingStmt->fetchColumn();

        if ($existingId > 0) {
            $upd = $pdo->prepare(
                'UPDATE swap_proposals
                 SET proposer_id = ?, recipient_id = ?, title = ?, teach_skill = ?, learn_skill = ?,
                     schedule_text = ?, note = ?, status = "pending", updated_at = CURRENT_TIMESTAMP
                 WHERE id = ?'
            );
            $upd->execute([$proposerId, $recipientId, $title, $teachSkill, $learnSkill, $scheduleText, $note, $existingId]);
            $proposalId = $existingId;
        } else {
            $ins = $pdo->prepare(
                'INSERT INTO swap_proposals (proposer_id, recipient_id, title, teach_skill, learn_skill, schedule_text, note, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, "pending")'
            );
            $ins->execute([$proposerId, $recipientId, $title, $teachSkill, $learnSkill, $scheduleText, $note]);
            $proposalId = (int)$pdo->lastInsertId();
        }

        $msgBody = "Swap proposal: {$title} — {$scheduleText}. Please accept or suggest a different time.";
        $msgStmt = $pdo->prepare(
            'INSERT INTO messages (sender_id, receiver_id, body, message_type, proposal_id)
             VALUES (?, ?, ?, "proposal", ?)'
        );
        $msgStmt->execute([$proposerId, $recipientId, $msgBody, $proposalId]);
        $messageId = (int)$pdo->lastInsertId();

        $pdo->commit();

        return [
            'proposal' => fetchEnrichedProposal($pdo, $proposalId, $proposerId),
            'message' => fetchMessageById($pdo, $messageId),
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

/**
 * Updates proposal status and posts a confirmation message to the other participant.
 */
function transitionProposalStatusWithMessage(PDO $pdo, int $proposalId, int $actorId, string $newStatus): array
{
    $stmt = $pdo->prepare('SELECT * FROM swap_proposals WHERE id = ? AND (proposer_id = ? OR recipient_id = ?)');
    $stmt->execute([$proposalId, $actorId, $actorId]);
    $proposal = $stmt->fetch();

    if (!$proposal) {
        throw new RuntimeException('Exchange request not found.', 404);
    }

    $isRecipient = ((int)$proposal['recipient_id'] === $actorId);
    $isProposer = ((int)$proposal['proposer_id'] === $actorId);

    if (in_array($newStatus, ['accepted', 'declined'], true) && !$isRecipient) {
        throw new RuntimeException('Only the recipient can accept or decline this swap proposal.', 403);
    }
    if ($newStatus === 'cancelled' && !$isProposer) {
        throw new RuntimeException('Only the proposer can cancel this swap proposal.', 403);
    }

    $otherUserId = $isProposer ? (int)$proposal['recipient_id'] : (int)$proposal['proposer_id'];

    $pdo->beginTransaction();
    try {
        $pdo->prepare('UPDATE swap_proposals SET status = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')
            ->execute([$newStatus, $proposalId]);

        $statusTextMap = [
            'accepted' => "Accepted swap proposal: {$proposal['title']} ({$proposal['schedule_text']}).",
            'declined' => "Declined swap proposal: {$proposal['title']}.",
            'completed' => "Marked swap as completed: {$proposal['title']}.",
            'cancelled' => "Cancelled swap proposal: {$proposal['title']}.",
        ];

        $messageId = 0;
        if (isset($statusTextMap[$newStatus])) {
            $insMsg = $pdo->prepare(
                'INSERT INTO messages (sender_id, receiver_id, body, message_type, proposal_id)
                 VALUES (?, ?, ?, "system", ?)'
            );
            $insMsg->execute([$actorId, $otherUserId, $statusTextMap[$newStatus], $proposalId]);
            $messageId = (int)$pdo->lastInsertId();
        }

        $pdo->commit();

        return [
            'proposal' => fetchEnrichedProposal($pdo, $proposalId, $actorId),
            'message' => $messageId > 0 ? fetchMessageById($pdo, $messageId) : null,
        ];
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
