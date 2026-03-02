<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityLogger.php';

class LinkTrackingService
{
    private const SHORT_CODE_LENGTH = 8;
    private const MAX_LINKS_PER_ACCOUNT = 500;

    private $db;
    private $lineAccountId;
    private $logger;

    public function __construct($db = null, $lineAccountId = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->lineAccountId = $lineAccountId;
        $this->logger = ActivityLogger::getInstance($this->db);
        $this->ensureTable();
    }

    public function getLinks(array $filters = []): array
    {
        $sql = "SELECT * FROM tracked_links WHERE (line_account_id = :account OR line_account_id IS NULL)";
        $params = [':account' => $this->lineAccountId];

        if (!empty($filters['search'])) {
            $sql .= " AND (title LIKE :search OR original_url LIKE :search)";
            $params[':search'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['date_from'])) {
            $sql .= " AND created_at >= :date_from";
            $params[':date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $sql .= " AND created_at <= :date_to";
            $params[':date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getStats(): array
    {
        $stmt = $this->db->prepare("SELECT 
                COUNT(*) as total_links,
                SUM(click_count) as total_clicks,
                SUM(unique_clicks) as total_unique,
                MAX(last_clicked_at) as last_clicked_at
            FROM tracked_links
            WHERE line_account_id = ? OR line_account_id IS NULL");
        $stmt->execute([$this->lineAccountId]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_links' => (int) ($data['total_links'] ?? 0),
            'total_clicks' => (int) ($data['total_clicks'] ?? 0),
            'total_unique' => (int) ($data['total_unique'] ?? 0),
            'last_clicked_at' => $data['last_clicked_at'] ?? null,
        ];
    }

    public function createLink(string $url, ?string $title = null): array
    {
        $this->ensureCapacity();
        $shortCode = $this->generateUniqueShortCode();

        $stmt = $this->db->prepare("INSERT INTO tracked_links (line_account_id, short_code, original_url, title) VALUES (?, ?, ?, ?)");
        $stmt->execute([$this->lineAccountId, $shortCode, $url, $title]);

        $link = $this->getLink($this->db->lastInsertId());
        $this->logger->logData(ActivityLogger::ACTION_CREATE, 'สร้าง tracked link', [
            'entity_type' => 'tracked_link',
            'entity_id' => $link['id'],
            'new_value' => $link,
        ]);

        return $link;
    }

    public function updateLink(int $linkId, string $url, ?string $title = null): array
    {
        $link = $this->getEditableLink($linkId);
        $stmt = $this->db->prepare("UPDATE tracked_links SET original_url = ?, title = ? WHERE id = ?");
        $stmt->execute([$url, $title, $linkId]);

        $updated = $this->getLink($linkId);
        $this->logger->logData(ActivityLogger::ACTION_UPDATE, 'แก้ไข tracked link', [
            'entity_type' => 'tracked_link',
            'entity_id' => $linkId,
            'old_value' => $link,
            'new_value' => $updated,
        ]);

        return $updated;
    }

    public function deleteLink(int $linkId): bool
    {
        $link = $this->getEditableLink($linkId);
        $stmt = $this->db->prepare("DELETE FROM tracked_links WHERE id = ?");
        $stmt->execute([$linkId]);

        $this->logger->logData(ActivityLogger::ACTION_DELETE, 'ลบ tracked link', [
            'entity_type' => 'tracked_link',
            'entity_id' => $linkId,
            'old_value' => $link,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function deleteLinks(array $linkIds): int
    {
        $linkIds = array_map('intval', array_filter($linkIds));
        if (empty($linkIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($linkIds), '?'));
        $params = $linkIds;
        array_unshift($params, $this->lineAccountId);

        $sql = "SELECT id FROM tracked_links WHERE (line_account_id = ? OR line_account_id IS NULL) AND id IN ($placeholders)";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $allowedIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'id');

        if (empty($allowedIds)) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($allowedIds), '?'));
        $stmt = $this->db->prepare("DELETE FROM tracked_links WHERE id IN ($placeholders)");
        $stmt->execute($allowedIds);

        $this->logger->logData(ActivityLogger::ACTION_DELETE, 'ลบ tracked link แบบกลุ่ม', [
            'entity_type' => 'tracked_link_batch',
            'extra_data' => ['ids' => $allowedIds],
        ]);

        return $stmt->rowCount();
    }

    public function getUsageRatio(): float
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tracked_links WHERE line_account_id = ?");
        $stmt->execute([$this->lineAccountId]);
        $count = (int) $stmt->fetchColumn();
        return $count / self::MAX_LINKS_PER_ACCOUNT;
    }

    private function getLink(int $linkId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM tracked_links WHERE id = ?");
        $stmt->execute([$linkId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function getEditableLink(int $linkId): array
    {
        $link = $this->getLink($linkId);
        if (!$link) {
            throw new Exception('ไม่พบลิงก์');
        }
        if ($link['line_account_id'] && $link['line_account_id'] != $this->lineAccountId) {
            throw new Exception('ไม่สามารถแก้ไขลิงก์นี้ได้');
        }
        return $link;
    }

    private function ensureCapacity(): void
    {
        if (!$this->lineAccountId) {
            throw new Exception('ไม่พบบัญชี LINE');
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM tracked_links WHERE line_account_id = ?");
        $stmt->execute([$this->lineAccountId]);
        if ((int) $stmt->fetchColumn() >= self::MAX_LINKS_PER_ACCOUNT) {
            throw new Exception('ลิงก์ถึงขีดจำกัดแล้ว กรุณาลบลิงก์ที่ไม่ใช้');
        }
    }

    private function generateUniqueShortCode(): string
    {
        $tries = 0;
        do {
            $shortCode = $this->randomString(self::SHORT_CODE_LENGTH);
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM tracked_links WHERE short_code = ?");
            $stmt->execute([$shortCode]);
            $exists = (int) $stmt->fetchColumn() > 0;
            $tries++;
            if ($tries > 5 && $this->lineAccountId) {
                $shortCode = substr(md5($this->lineAccountId . microtime(true)), 0, self::SHORT_CODE_LENGTH);
                $exists = false;
            }
        } while ($exists);

        return $shortCode;
    }

    private function randomString(int $length): string
    {
        $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
        $result = '';
        for ($i = 0; $i < $length; $i++) {
            $result .= $characters[random_int(0, strlen($characters) - 1)];
        }
        return $result;
    }

    private function ensureTable(): void
    {
        try {
            $this->db->query('SELECT 1 FROM tracked_links LIMIT 1');
        } catch (Exception $e) {
            $this->db->exec("CREATE TABLE IF NOT EXISTS tracked_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                line_account_id INT DEFAULT NULL,
                short_code VARCHAR(20) NOT NULL UNIQUE,
                original_url TEXT NOT NULL,
                title VARCHAR(255),
                click_count INT DEFAULT 0,
                unique_clicks INT DEFAULT 0,
                last_clicked_at TIMESTAMP NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_short_code (short_code),
                INDEX idx_account (line_account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        }
    }
}
