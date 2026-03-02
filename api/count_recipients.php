<?php
/**
 * API: Count recipients for a broadcast target
 * GET params: type, tag_ids (comma-separated), segment_id, group_id, limit_count
 */
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    $currentBotId = $_SESSION['current_bot_id'] ?? $_SESSION['line_account_id'] ?? null;
    $type = $_GET['type'] ?? 'database';
    $count = 0;

    if ($type === 'database' || $type === 'all') {
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE is_blocked = 0 AND (line_account_id = ? OR line_account_id IS NULL)");
        $stmt->execute([$currentBotId]);
        $count = (int) $stmt->fetchColumn();

    } elseif ($type === 'limit') {
        $count = (int) ($_GET['limit_count'] ?? 100);

    } elseif ($type === 'tag') {
        $tagIds = array_filter(array_map('intval', explode(',', $_GET['tag_ids'] ?? '')));
        if (!empty($tagIds)) {
            $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
            $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u
                JOIN user_tag_assignments a ON u.id = a.user_id
                WHERE a.tag_id IN ($placeholders) AND u.is_blocked = 0");
            $stmt->execute($tagIds);
            $count = (int) $stmt->fetchColumn();
        }

    } elseif ($type === 'segment') {
        $segmentId = (int) ($_GET['segment_id'] ?? 0);
        if ($segmentId) {
            $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u
                JOIN segment_members sm ON u.id = sm.user_id
                WHERE sm.segment_id = ? AND u.is_blocked = 0");
            $stmt->execute([$segmentId]);
            $count = (int) $stmt->fetchColumn();
        }

    } elseif ($type === 'group') {
        $groupId = (int) ($_GET['group_id'] ?? 0);
        if ($groupId) {
            $stmt = $db->prepare("SELECT COUNT(DISTINCT u.id) FROM users u
                JOIN user_groups ug ON u.id = ug.user_id
                WHERE ug.group_id = ? AND u.is_blocked = 0");
            $stmt->execute([$groupId]);
            $count = (int) $stmt->fetchColumn();
        }
    }

    echo json_encode(['success' => true, 'count' => $count]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'count' => 0, 'error' => $e->getMessage()]);
}
