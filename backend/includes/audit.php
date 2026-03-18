<?php
if (!defined('APP_RUNNING')) exit;

function logAction(string $action, string $entity, int $entityId, array $details = []): void {
    if (!isLoggedIn()) return;
    getDB()->prepare(
        'INSERT INTO audit_log (user_id,action,entity,entity_id,details) VALUES (?,?,?,?,?)'
    )->execute([
        $_SESSION['user_id'],
        strtoupper($action),
        $entity,
        $entityId,
        json_encode($details),
    ]);
}

function getRecentAudit(int $limit = 10): array {
    $s = getDB()->prepare(
        'SELECT a.*,u.name AS user_name FROM audit_log a
         JOIN users u ON u.id=a.user_id
         ORDER BY a.created_at DESC LIMIT ?'
    );
    $s->execute([$limit]);
    return $s->fetchAll();
}
