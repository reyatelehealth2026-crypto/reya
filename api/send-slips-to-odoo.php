<?php
/**
 * Send Pending Slips to Odoo API
 *
 * Reads all pending slips from odoo_slip_uploads, uploads each one
 * to Odoo via multipart/form-data (POST /reya/slip/upload), and
 * updates the status to matched/failed.
 *
 * POST body (JSON):
 *   ids?      – array of specific slip IDs to send (optional; sends all pending if omitted)
 *   dry_run?  – bool, if true just returns what would be sent without sending
 */

ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/OdooAPIClient.php';

use Modules\Core\Database;

try {
    $db = Database::getInstance()->getConnection();

    $input  = json_decode(file_get_contents('php://input'), true) ?? [];
    $ids    = $input['ids']    ?? null;   // array|null
    $dryRun = !empty($input['dry_run']);

    // ------------------------------------------------------------------ //
    // 1. Fetch pending slips (optionally filtered by IDs)
    // ------------------------------------------------------------------ //
    $allowRetry = !empty($input['retry']); // if true, also retry failed slips
    $statusFilter = $allowRetry ? "s.status IN ('pending','failed')" : "s.status = 'pending'";
    $where  = "$statusFilter AND s.image_path IS NOT NULL";
    $params = [];

    if (!empty($ids) && is_array($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $where       .= " AND s.id IN ($placeholders)";
        $params       = array_map('intval', $ids);
    }

    $stmt = $db->prepare("
        SELECT
            s.id,
            s.line_user_id,
            s.line_account_id,
            s.image_path,
            s.amount,
            s.transfer_date,
            s.invoice_id,
            s.order_id,
            s.bdo_id
        FROM odoo_slip_uploads s
        WHERE $where
        ORDER BY s.uploaded_at ASC
        LIMIT 100
    ");
    $stmt->execute($params);
    $slips = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($slips)) {
        echo json_encode([
            'success' => true,
            'message' => 'ไม่มีสลิปที่รอส่ง',
            'data'    => ['sent' => 0, 'failed' => 0, 'results' => []],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($dryRun) {
        echo json_encode([
            'success' => true,
            'message' => 'dry_run: พบ ' . count($slips) . ' สลิปที่จะส่ง',
            'data'    => ['slips' => $slips],
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // ------------------------------------------------------------------ //
    // 2. Group slips by line_account_id so we reuse OdooAPIClient per account
    // ------------------------------------------------------------------ //
    $byAccount = [];
    foreach ($slips as $slip) {
        $byAccount[$slip['line_account_id']][] = $slip;
    }

    $sent    = 0;
    $failed  = 0;
    $results = [];

    foreach ($byAccount as $accountId => $accountSlips) {
        // Initialise Odoo client for this LINE account
        try {
            $odoo = new OdooAPIClient($db, $accountId);
        } catch (Exception $e) {
            foreach ($accountSlips as $slip) {
                $failed++;
                $results[] = ['id' => $slip['id'], 'success' => false, 'error' => 'OdooAPIClient init: ' . $e->getMessage()];
                _markSlip($db, $slip['id'], 'failed', 'OdooAPIClient init failed: ' . $e->getMessage());
            }
            continue;
        }

        foreach ($accountSlips as $slip) {
            $slipId = (int) $slip['id'];
            try {
                // Read image file from disk
                $fullPath = __DIR__ . '/../' . ltrim($slip['image_path'], '/');
                if (!file_exists($fullPath)) {
                    throw new Exception('Image file not found: ' . $slip['image_path']);
                }
                $imageData = file_get_contents($fullPath);
                if (!$imageData || strlen($imageData) < 100) {
                    throw new Exception('Image file is empty or too small');
                }

                $base64 = base64_encode($imageData);

                $options = [];
                if ($slip['amount']        !== null) $options['amount']        = (float) $slip['amount'];
                if ($slip['transfer_date'] !== null) $options['transfer_date'] = $slip['transfer_date'];
                if ($slip['invoice_id']    !== null) $options['invoice_id']    = (int) $slip['invoice_id'];
                if ($slip['order_id']      !== null) $options['order_id']      = (int) $slip['order_id'];

                // Use JSON-RPC uploadSlip (base64) — avoids CSRF rejection from multipart POST
                $odooResult = $odoo->uploadSlip($slip['line_user_id'], $base64, $options);

                // Extract odoo_slip_id from response (Odoo may return id, slip_id, or result.id)
                $odooSlipId = $odooResult['id'] ?? $odooResult['slip_id'] ?? $odooResult['result']['id'] ?? null;
                $odooOrderId = $odooResult['order_id'] ?? $odooResult['result']['order_id'] ?? null;
                $odooInvoiceId = $odooResult['invoice_id'] ?? $odooResult['result']['invoice_id'] ?? null;

                // Mark as matched
                _markSlip($db, $slipId, 'matched', 'Sent to Odoo: ' . json_encode($odooResult), $odooSlipId, $odooOrderId, $odooInvoiceId);
                $sent++;
                $results[] = [
                    'id'      => $slipId,
                    'success' => true,
                    'odoo'    => $odooResult,
                    'odoo_slip_id' => $odooSlipId,
                ];

            } catch (Exception $e) {
                $failed++;
                $errDetail = $e->getMessage();
                $results[] = ['id' => $slipId, 'success' => false, 'error' => $errDetail];
                _markSlip($db, $slipId, 'failed', $errDetail);
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => "ส่งสำเร็จ $sent / " . count($slips) . " รายการ" . ($failed > 0 ? " (ล้มเหลว $failed)" : ''),
        'data'    => [
            'sent'    => $sent,
            'failed'  => $failed,
            'results' => $results,
        ],
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => $e->getMessage(),
    ], JSON_UNESCAPED_UNICODE);
}

function _markSlip(PDO $db, int $id, string $status, string $reason, ?int $odooSlipId = null, $odooOrderId = null, $odooInvoiceId = null): void
{
    $db->prepare("
        UPDATE odoo_slip_uploads
        SET status = ?, match_reason = ?, matched_at = NOW(),
            odoo_slip_id  = COALESCE(?, odoo_slip_id),
            order_id      = COALESCE(?, order_id),
            invoice_id    = COALESCE(?, invoice_id)
        WHERE id = ?
    ")->execute([$status, mb_substr($reason, 0, 500), $odooSlipId, $odooOrderId ? (int)$odooOrderId : null, $odooInvoiceId ? (int)$odooInvoiceId : null, $id]);
}

/**
 * Wraps uploadSlipMultipart and captures raw HTTP details on failure
 * so the caller can show a meaningful error message.
 */
function _uploadSlipWithDetail(
    OdooAPIClient $odoo,
    string $lineUserId,
    string $imageData,
    string $filename,
    string $mimeType,
    array  $options
): array {
    try {
        return $odoo->uploadSlipMultipart($lineUserId, $imageData, $filename, $mimeType, $options);
    } catch (Exception $e) {
        // Re-throw with original message — OdooAPIClient already captures HTTP code + body
        throw new Exception($e->getMessage());
    }
}
