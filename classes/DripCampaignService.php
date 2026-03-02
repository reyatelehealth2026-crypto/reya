<?php

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/ActivityLogger.php';
require_once __DIR__ . '/CRMManager.php';

class DripCampaignService
{
    private $db;
    private $lineAccountId;
    private $logger;
    private $crm;

    public function __construct($db = null, $lineAccountId = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->lineAccountId = $lineAccountId;
        $this->logger = ActivityLogger::getInstance($this->db);
        $this->crm = new CRMManager($this->db, $lineAccountId);
    }

    public function listCampaignsWithStats(): array
    {
        $campaigns = $this->crm->getCampaigns();
        if (empty($campaigns)) {
            return [];
        }

        $stats = $this->fetchProgressStats(array_column($campaigns, 'id'));
        foreach ($campaigns as &$campaign) {
            $campaignId = $campaign['id'];
            $campaign['pending_contacts'] = (int) ($stats[$campaignId]['pending_contacts'] ?? 0);
            $campaign['due_contacts'] = (int) ($stats[$campaignId]['due_contacts'] ?? 0);
            $campaign['stalled_contacts'] = (int) ($stats[$campaignId]['stalled_contacts'] ?? 0);
            $campaign['next_send_at'] = $stats[$campaignId]['next_send_at'] ?? null;
        }

        return $campaigns;
    }

    public function getQueueSummary(int $stalledMinutes = 120): array
    {
        if (!$this->lineAccountId) {
            return [
                'active_campaigns' => 0,
                'active_contacts' => 0,
                'due_contacts' => 0,
                'stalled_contacts' => 0,
                'next_send_at' => null,
            ];
        }

        $stalledThreshold = date('Y-m-d H:i:s', time() - ($stalledMinutes * 60));
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(DISTINCT CASE WHEN c.is_active = 1 THEN c.id END) as active_campaigns,
                SUM(CASE WHEN p.status = 'active' THEN 1 ELSE 0 END) as active_contacts,
                SUM(CASE WHEN p.status = 'active' AND p.next_send_at <= NOW() THEN 1 ELSE 0 END) as due_contacts,
                MIN(CASE WHEN p.status = 'active' THEN p.next_send_at END) as next_send_at,
                SUM(CASE WHEN p.status = 'active' AND p.next_send_at < ? THEN 1 ELSE 0 END) as stalled_contacts
            FROM drip_campaigns c
            LEFT JOIN drip_campaign_progress p ON p.campaign_id = c.id
            WHERE c.line_account_id = ?"
        );
        $stmt->execute([$stalledThreshold, $this->lineAccountId]);
        $summary = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'active_campaigns' => (int) ($summary['active_campaigns'] ?? 0),
            'active_contacts' => (int) ($summary['active_contacts'] ?? 0),
            'due_contacts' => (int) ($summary['due_contacts'] ?? 0),
            'stalled_contacts' => (int) ($summary['stalled_contacts'] ?? 0),
            'next_send_at' => $summary['next_send_at'] ?? null,
        ];
    }

    public function createCampaign(string $name, string $triggerType, $triggerConfig = null): array
    {
        $this->requireLineAccount();
        $campaignId = $this->crm->createCampaign($name, $triggerType, $triggerConfig);
        $campaign = $this->getCampaign($campaignId);

        $this->logger->logData(ActivityLogger::ACTION_CREATE, 'สร้าง Drip Campaign', [
            'entity_type' => 'drip_campaign',
            'entity_id' => $campaignId,
            'new_value' => $campaign,
        ]);

        return $campaign;
    }

    public function updateCampaign(int $campaignId, array $payload): array
    {
        $campaign = $this->getWritableCampaign($campaignId);
        $newName = trim($payload['name'] ?? $campaign['name']);
        $triggerType = $payload['trigger_type'] ?? $campaign['trigger_type'];

        $stmt = $this->db->prepare("UPDATE drip_campaigns SET name = ?, trigger_type = ? WHERE id = ?");
        $stmt->execute([$newName, $triggerType, $campaignId]);

        $updated = $this->getCampaign($campaignId);
        $this->logger->logData(ActivityLogger::ACTION_UPDATE, 'อัปเดต Drip Campaign', [
            'entity_type' => 'drip_campaign',
            'entity_id' => $campaignId,
            'old_value' => $campaign,
            'new_value' => $updated,
        ]);

        return $updated;
    }

    public function toggleCampaign(int $campaignId): array
    {
        $campaign = $this->getWritableCampaign($campaignId);
        $stmt = $this->db->prepare("UPDATE drip_campaigns SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$campaignId]);

        $updated = $this->getCampaign($campaignId);
        $this->logger->logData(ActivityLogger::ACTION_UPDATE, 'เปลี่ยนสถานะ Drip Campaign', [
            'entity_type' => 'drip_campaign',
            'entity_id' => $campaignId,
            'old_value' => ['is_active' => $campaign['is_active']],
            'new_value' => ['is_active' => $updated['is_active']],
        ]);

        return $updated;
    }

    public function deleteCampaign(int $campaignId): array
    {
        $campaign = $this->getWritableCampaign($campaignId);

        $this->db->beginTransaction();
        try {
            $this->db->prepare("UPDATE drip_campaign_progress SET status = 'archived', completed_at = NOW() WHERE campaign_id = ?")
                ->execute([$campaignId]);
            $this->db->prepare("DELETE FROM drip_campaign_steps WHERE campaign_id = ?")
                ->execute([$campaignId]);
            $this->db->prepare("DELETE FROM drip_campaigns WHERE id = ?")
                ->execute([$campaignId]);
            $this->db->commit();
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }

        $this->logger->logData(ActivityLogger::ACTION_DELETE, 'ลบ Drip Campaign', [
            'entity_type' => 'drip_campaign',
            'entity_id' => $campaignId,
            'old_value' => $campaign,
        ]);

        return $campaign;
    }

    public function addStep(int $campaignId, array $payload): array
    {
        $this->getWritableCampaign($campaignId);

        $content = trim($payload['content'] ?? '');
        if ($content === '') {
            throw new Exception('กรุณากรอกเนื้อหา');
        }

        $delayMinutes = (int) ($payload['delay_minutes'] ?? 0);
        $messageType = $payload['message_type'] ?? 'text';
        $stepOrder = isset($payload['step_order']) ? (int) $payload['step_order'] : $this->getNextStepOrder($campaignId);

        $conditions = $payload['conditions'] ?? null;
        if (is_string($conditions)) {
            $decoded = json_decode($conditions, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $conditions = $decoded;
            }
        }

        $stepId = $this->crm->addCampaignStep($campaignId, $stepOrder, $delayMinutes, $messageType, $content, $conditions);
        $step = $this->getStep($stepId);

        $this->logger->logData(ActivityLogger::ACTION_CREATE, 'เพิ่ม Step ใน Drip Campaign', [
            'entity_type' => 'drip_campaign_step',
            'entity_id' => $stepId,
            'extra_data' => ['campaign_id' => $campaignId],
            'new_value' => $step,
        ]);

        $this->resequenceSteps($campaignId);

        return $step;
    }

    public function deleteStep(int $campaignId, int $stepId): bool
    {
        $this->getWritableCampaign($campaignId);

        $stmt = $this->db->prepare("DELETE FROM drip_campaign_steps WHERE id = ? AND campaign_id = ?");
        $stmt->execute([$stepId, $campaignId]);

        $this->resequenceSteps($campaignId);

        $this->logger->logData(ActivityLogger::ACTION_DELETE, 'ลบ Step ใน Drip Campaign', [
            'entity_type' => 'drip_campaign_step',
            'entity_id' => $stepId,
            'extra_data' => ['campaign_id' => $campaignId],
        ]);

        return $stmt->rowCount() > 0;
    }

    public function getCampaign(int $campaignId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM drip_campaigns WHERE id = ?");
        $stmt->execute([$campaignId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function getCampaignSteps(int $campaignId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM drip_campaign_steps WHERE campaign_id = ? ORDER BY step_order ASC");
        $stmt->execute([$campaignId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getWritableCampaign(int $campaignId): array
    {
        $campaign = $this->getCampaign($campaignId);
        if (!$campaign) {
            throw new Exception('ไม่พบ Campaign');
        }
        if (!$this->lineAccountId || (int) $campaign['line_account_id'] !== (int) $this->lineAccountId) {
            throw new Exception('ไม่สามารถแก้ไข Campaign นี้ได้');
        }
        return $campaign;
    }

    private function requireLineAccount(): void
    {
        if (!$this->lineAccountId) {
            throw new Exception('กรุณาเลือก LINE Account ก่อนใช้งาน Drip Campaigns');
        }
    }

    private function getNextStepOrder(int $campaignId): int
    {
        $stmt = $this->db->prepare("SELECT COALESCE(MAX(step_order), 0) + 1 FROM drip_campaign_steps WHERE campaign_id = ?");
        $stmt->execute([$campaignId]);
        return (int) $stmt->fetchColumn();
    }

    private function resequenceSteps(int $campaignId): void
    {
        $steps = $this->getCampaignSteps($campaignId);
        $order = 1;
        $stmt = $this->db->prepare("UPDATE drip_campaign_steps SET step_order = ? WHERE id = ?");
        foreach ($steps as $step) {
            if ((int) $step['step_order'] !== $order) {
                $stmt->execute([$order, $step['id']]);
            }
            $order++;
        }
    }

    private function getStep(int $stepId): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM drip_campaign_steps WHERE id = ?");
        $stmt->execute([$stepId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    private function fetchProgressStats(array $campaignIds): array
    {
        if (empty($campaignIds)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($campaignIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT campaign_id,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as pending_contacts,
                    SUM(CASE WHEN status = 'active' AND next_send_at <= NOW() THEN 1 ELSE 0 END) as due_contacts,
                    SUM(CASE WHEN status = 'active' AND next_send_at < DATE_SUB(NOW(), INTERVAL 2 HOUR) THEN 1 ELSE 0 END) as stalled_contacts,
                    MIN(CASE WHEN status = 'active' THEN next_send_at END) as next_send_at
             FROM drip_campaign_progress
             WHERE campaign_id IN ($placeholders)
             GROUP BY campaign_id"
        );
        $stmt->execute($campaignIds);

        $stats = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $stats[$row['campaign_id']] = $row;
        }

        return $stats;
    }
}
