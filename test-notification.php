<?php
/**
 * Odoo Notification Simulator - จำลอง webhook ผ่าน pipeline จริง
 */
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';

// ===== AJAX handler =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'send') {
    header('Content-Type: application/json; charset=utf-8');
    $partnerId   = (int) ($_POST['partner_id'] ?? 0);
    $event       = trim($_POST['event_type'] ?? '');
    $orderName   = trim($_POST['order_name'] ?? '');
    $orderId     = (int) ($_POST['order_id'] ?? 0);
    $amountTotal = (float) ($_POST['amount_total'] ?? 0);
    $carrier     = trim($_POST['carrier'] ?? '');
    $tracking    = trim($_POST['tracking'] ?? '');
    $stateDisp   = trim($_POST['state_display'] ?? '');

    $manualLineId = trim($_POST['manual_line_id'] ?? '');
    $manualName   = trim($_POST['manual_name'] ?? '');

    if (!$event) { echo json_encode(['success'=>false,'error'=>'กรุณาเลือก event']); exit; }
    if (!$partnerId && !$manualLineId) { echo json_encode(['success'=>false,'error'=>'กรุณาเลือกลูกค้าหรือระบุ LINE User ID']); exit; }

    try {
        $pdo = Database::getInstance()->getConnection();

        // ถ้ามี manual_line_id ส่งมาพร้อม partner_id (จาก lookup) — ใช้ได้เลยไม่ต้อง query DB
        if ($manualLineId) {
            $cust = [
                'odoo_partner_id'           => $partnerId ?: 0,
                'line_user_id'              => $manualLineId,
                'line_notification_enabled' => 1,
                'cust_name'                 => $manualName ?: ('Partner #' . $partnerId),
                'cust_ref'                  => '',
            ];
        } else {
            $stmt = $pdo->prepare("SELECT olu.odoo_partner_id,
                COALESCE(olu.line_user_id, u.line_user_id, '') as line_user_id,
                olu.line_notification_enabled,
                COALESCE(olu.odoo_partner_name, u.display_name, CONCAT('Partner #', olu.odoo_partner_id)) as cust_name,
                COALESCE(olu.odoo_customer_code, '') as cust_ref
                FROM odoo_line_users olu
                LEFT JOIN users u ON u.line_user_id = olu.line_user_id
                WHERE olu.odoo_partner_id = ? LIMIT 1");
            $stmt->execute([$partnerId]);
            $cust = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$cust) {
                // Fallback: สร้าง minimal record จาก partner_id เพื่อให้ handler ดึง line_user_id เอง
                $cust = [
                    'odoo_partner_id'           => $partnerId,
                    'line_user_id'              => '',
                    'line_notification_enabled' => 1,
                    'cust_name'                 => 'Partner #' . $partnerId,
                    'cust_ref'                  => '',
                ];
            }
        }

        $deliveryId  = 'wh_test_' . time() . '_' . substr(md5((string)rand()), 0, 8);
        $timestamp   = time();
        $payload     = [
            'event' => $event,
            'data'  => [
                'customer'         => ['id'=>(int)$cust['odoo_partner_id'],'partner_id'=>(int)$cust['odoo_partner_id'],'name'=>$cust['cust_name'],'ref'=>$cust['cust_ref'],'line_user_id'=>$cust['line_user_id']],
                'order_id'         => $orderId ?: null,
                'order_name'       => $orderName ?: null,
                'state'            => explode('.', $event)[1] ?? 'done',
                'new_state_display'=> $stateDisp ?: null,
                'amount_total'     => $amountTotal ?: null,
                'delivery'         => ['carrier'=>$carrier ?: null,'tracking_number'=>$tracking ?: null],
                'tracking_number'  => $tracking ?: null,
            ],
            'notify' => ['customer'=>true,'salesperson'=>false],
        ];
        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
        $secret      = defined('ODOO_WEBHOOK_SECRET') ? ODOO_WEBHOOK_SECRET : '';
        $signature   = 'sha256=' . hash_hmac('sha256', $payloadJson, $secret);

        $scheme  = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host    = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $baseDir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $url     = $scheme . '://' . $host . $baseDir . '/api/webhook/odoo.php';

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$payloadJson, CURLOPT_RETURNTRANSFER=>true,
            CURLOPT_TIMEOUT=>30, CURLOPT_SSL_VERIFYPEER=>false,
            CURLOPT_HTTPHEADER=>[
                'Content-Type: application/json',
                'X-Odoo-Delivery-Id: '.$deliveryId,
                'X-Odoo-Event: '.$event,
                'X-Odoo-Signature: '.$signature,
                'X-Odoo-Timestamp: '.$timestamp,
            ],
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Wait for LINE API call + DB writes to complete
        usleep(800000);

        $wl = $pdo->prepare("SELECT status, error_message, processed_at FROM odoo_webhooks_log WHERE delivery_id = ? LIMIT 1");
        $wl->execute([$deliveryId]); $webhookLog = $wl->fetch(PDO::FETCH_ASSOC);

        $nl = $pdo->prepare("SELECT status, skip_reason, error_message, sent_at FROM odoo_notification_log WHERE delivery_id = ? ORDER BY id DESC LIMIT 5");
        $nl->execute([$deliveryId]); $notifLogs = $nl->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode(['success'=>($httpCode===200),'http_code'=>$httpCode,'curl_error'=>$curlError?:null,
            'response'=>json_decode($response,true),'delivery_id'=>$deliveryId,
            'webhook_log'=>$webhookLog,'notif_logs'=>$notifLogs,'payload_sent'=>$payload,
            'url'=>$url,'signature_prefix'=>substr($signature,0,20).'...','secret_set'=>!empty($secret)
        ], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    } catch (Exception $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ===== AJAX: lookup order จากเลขออเดอร์ — ดึงข้อมูลจริงจาก DB =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'lookup_order') {
    header('Content-Type: application/json; charset=utf-8');
    $orderName = trim($_POST['order_name'] ?? '');
    if (!$orderName) { echo json_encode(['success'=>false,'error'=>'กรุณาระบุเลขออเดอร์']); exit; }
    try {
        $pdo = Database::getInstance()->getConnection();
        // ดึงจาก odoo_webhooks_log — เอา record ล่าสุดของออเดอร์นี้
        $row = null;

        // Query 1: ดึงจาก payload JSON — order_name อยู่ใน payload.data.order_name หรือ payload.order_name
        try {
            $s = $pdo->prepare("
                SELECT wl.id, wl.order_id, wl.line_user_id, wl.payload,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.order_name')),
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.order_name')),
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.order_ref'))
                       ) AS extracted_order_name,
                       COALESCE(
                           CAST(JSON_EXTRACT(wl.payload, '$.data.customer.id') AS UNSIGNED),
                           CAST(JSON_EXTRACT(wl.payload, '$.data.customer.partner_id') AS UNSIGNED)
                       ) AS extracted_partner_id,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.customer.name')),
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.customer_name'))
                       ) AS extracted_customer_name,
                       COALESCE(
                           JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.customer.line_user_id')),
                           wl.line_user_id
                       ) AS extracted_line_user_id,
                       CAST(JSON_EXTRACT(wl.payload, '$.data.amount_total') AS DECIMAL(14,2)) AS extracted_amount,
                       olu.odoo_partner_id, olu.odoo_partner_name,
                       COALESCE(olu.line_user_id, wl.line_user_id) AS resolved_line_user_id
                FROM odoo_webhooks_log AS wl
                LEFT JOIN odoo_line_users AS olu ON olu.odoo_partner_id = COALESCE(
                    CAST(JSON_EXTRACT(wl.payload, '$.data.customer.id') AS UNSIGNED),
                    CAST(JSON_EXTRACT(wl.payload, '$.data.customer.partner_id') AS UNSIGNED)
                )
                WHERE (
                    JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.order_name')) = ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.data.order_ref')) = ?
                    OR JSON_UNQUOTE(JSON_EXTRACT(wl.payload, '$.order_name')) = ?
                )
                ORDER BY wl.processed_at DESC LIMIT 1
            ");
            $s->execute([$orderName, $orderName, $orderName]);
            $row = $s->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $eq) {
            // JSON_EXTRACT ไม่รองรับ — fallback ใช้ LIKE บน payload text
            try {
                $s2 = $pdo->prepare("
                    SELECT wl.id, wl.order_id, wl.line_user_id, wl.payload
                    FROM odoo_webhooks_log AS wl
                    WHERE wl.payload LIKE ?
                    ORDER BY wl.processed_at DESC LIMIT 1
                ");
                $s2->execute(['%' . $orderName . '%']);
                $raw = $s2->fetch(PDO::FETCH_ASSOC);
                if ($raw) {
                    $p = json_decode($raw['payload'], true);
                    $d = $p['data'] ?? $p;
                    $row = [
                        'order_id'              => $raw['order_id'] ?? ($d['order_id'] ?? null),
                        'line_user_id'          => $raw['line_user_id'],
                        'extracted_order_name'  => $d['order_name'] ?? ($d['order_ref'] ?? $orderName),
                        'extracted_partner_id'  => $d['customer']['id'] ?? ($d['customer']['partner_id'] ?? null),
                        'extracted_customer_name'=> $d['customer']['name'] ?? null,
                        'extracted_line_user_id'=> $d['customer']['line_user_id'] ?? $raw['line_user_id'],
                        'extracted_amount'      => $d['amount_total'] ?? null,
                        'odoo_partner_id'       => null,
                        'odoo_partner_name'     => null,
                        'resolved_line_user_id' => $d['customer']['line_user_id'] ?? $raw['line_user_id'],
                    ];
                }
            } catch (Exception $eq2) { /* ignore */ }
        }

        // Query 2: odoo_order_projection fallback
        if (!$row) {
            try {
                $s3 = $pdo->prepare("
                    SELECT op.order_id, op.order_name AS extracted_order_name, op.amount_total AS extracted_amount,
                           op.line_user_id AS extracted_line_user_id,
                           op.line_user_id AS resolved_line_user_id,
                           olu.odoo_partner_id, olu.odoo_partner_name,
                           NULL AS extracted_partner_id, NULL AS extracted_customer_name, NULL AS line_user_id
                    FROM odoo_order_projection AS op
                    LEFT JOIN odoo_line_users AS olu ON olu.line_user_id = op.line_user_id
                    WHERE op.order_name = ?
                    ORDER BY op.updated_at DESC LIMIT 1
                ");
                $s3->execute([$orderName]);
                $row = $s3->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $eq3) { /* ignore */ }
        }

        if (!$row) { echo json_encode(['success'=>false,'error'=>'ไม่พบออเดอร์ '.$orderName.' ใน webhook log หรือ order projection']); exit; }

        $partnerId   = $row['odoo_partner_id'] ?? $row['extracted_partner_id'];
        $custName    = $row['odoo_partner_name'] ?? $row['extracted_customer_name'];
        $lineUserId  = $row['resolved_line_user_id'] ?? $row['extracted_line_user_id'] ?? $row['line_user_id'];
        $amountTotal = $row['extracted_amount'] ?? null;

        echo json_encode([
            'success'       => true,
            'order_id'      => $row['order_id'],
            'order_name'    => $row['extracted_order_name'] ?? $orderName,
            'partner_id'    => $partnerId,
            'customer_name' => $custName,
            'line_user_id'  => $lineUserId,
            'amount_total'  => $amountTotal,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) { echo json_encode(['success'=>false,'error'=>$e->getMessage()]); }
    exit;
}

// ===== AJAX: โหลดออเดอร์ของลูกค้า =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'orders') {
    header('Content-Type: application/json; charset=utf-8');
    $pid = (int)($_POST['partner_id'] ?? 0);
    try {
        $pdo = Database::getInstance()->getConnection();
        $stmt = $pdo->prepare("SELECT DISTINCT order_name, order_id, amount_total FROM odoo_webhooks_log
            WHERE customer_ref IS NOT NULL AND order_name IS NOT NULL AND order_name != '' AND order_name != 'null'
            AND (customer_ref = (SELECT ref FROM odoo_customers WHERE partner_id = ? LIMIT 1) OR order_id IN (SELECT order_id FROM odoo_order_projection WHERE line_user_id = (SELECT line_user_id FROM odoo_line_users WHERE odoo_partner_id = ? LIMIT 1)))
            ORDER BY processed_at DESC LIMIT 30");
        $stmt->execute([$pid, $pid]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            // fallback: just recent orders
            $stmt2 = $pdo->query("SELECT DISTINCT order_name, order_id, amount_total FROM odoo_webhooks_log WHERE order_name IS NOT NULL AND order_name != '' AND order_name != 'null' ORDER BY processed_at DESC LIMIT 20");
            $rows = $stmt2->fetchAll(PDO::FETCH_ASSOC);
        }
        echo json_encode(['success'=>true,'orders'=>$rows], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) { echo json_encode(['success'=>false,'orders'=>[]]); }
    exit;
}

// ===== โหลดหน้าปกติ =====
$customers = [];
try {
    $pdo = Database::getInstance()->getConnection();
    $customers = $pdo->query("SELECT olu.odoo_partner_id,
        COALESCE(olu.line_user_id, u.line_user_id, '') as line_user_id,
        olu.line_notification_enabled,
        COALESCE(olu.odoo_partner_name, u.display_name, CONCAT('Partner #', olu.odoo_partner_id)) as cust_name,
        COALESCE(olu.odoo_customer_code, '') as cust_ref
        FROM odoo_line_users olu
        LEFT JOIN users u ON u.line_user_id = olu.line_user_id
        WHERE olu.odoo_partner_id IS NOT NULL AND olu.odoo_partner_id > 0
        ORDER BY olu.line_notification_enabled DESC, cust_name ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { $customers = []; $diagMsg = 'Main query error: ' . $e->getMessage(); }

// Diagnostic + fallback
if (empty($diagMsg)) $diagMsg = '';
if (empty($customers)) {
    try {
        $pdo2 = Database::getInstance()->getConnection();
        $cnt      = $pdo2->query("SELECT COUNT(*) FROM odoo_line_users")->fetchColumn();
        $cntWithP = $pdo2->query("SELECT COUNT(*) FROM odoo_line_users WHERE odoo_partner_id > 0")->fetchColumn();
        $cntWithL = $pdo2->query("SELECT COUNT(*) FROM odoo_line_users WHERE line_user_id IS NOT NULL AND line_user_id != ''")->fetchColumn();
        $sample   = $pdo2->query("SELECT odoo_partner_id, line_user_id, odoo_partner_name, odoo_customer_code FROM odoo_line_users LIMIT 3")->fetchAll(PDO::FETCH_ASSOC);
        $diagMsg  = "total={$cnt}, partner_id>0={$cntWithP}, has_line_id={$cntWithL} | sample: " . json_encode($sample, JSON_UNESCAPED_UNICODE);

        // Fallback 1: JOIN users ผ่าน line_user_id (ไม่ filter line_user_id)
        if ($cntWithP > 0) {
            $customers = $pdo2->query("SELECT olu.odoo_partner_id,
                COALESCE(olu.line_user_id, u.line_user_id, '') as line_user_id,
                olu.line_notification_enabled,
                COALESCE(olu.odoo_partner_name, u.display_name, CONCAT('Partner #', olu.odoo_partner_id)) as cust_name,
                COALESCE(olu.odoo_customer_code, '') as cust_ref
                FROM odoo_line_users olu
                LEFT JOIN users u ON u.line_user_id = olu.line_user_id
                WHERE olu.odoo_partner_id > 0
                ORDER BY olu.line_notification_enabled DESC, cust_name ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        }

    } catch (Exception $e2) { $diagMsg .= ' | Fallback1 error: ' . $e2->getMessage(); }

    // Fallback 2: users ทั้งหมดที่มี LINE (ไม่ต้องมี partner — ใช้ manual mode ระบุ partner_id)
    if (empty($customers)) {
        try {
            $pdo3 = Database::getInstance()->getConnection();
            $customers = $pdo3->query("SELECT
                0 as odoo_partner_id,
                u.line_user_id,
                1 as line_notification_enabled,
                COALESCE(u.display_name, u.real_name, u.line_user_id) as cust_name,
                '' as cust_ref
                FROM users u
                WHERE u.line_user_id IS NOT NULL AND u.line_user_id != ''
                ORDER BY u.display_name ASC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e3) { $diagMsg .= ' | Fallback2 error: ' . $e3->getMessage(); }
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>ทดสอบส่งแจ้งเตือน Odoo</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
<style>
*{box-sizing:border-box;margin:0;padding:0;}
body{font-family:'Segoe UI',sans-serif;background:#f8fafc;color:#1e293b;padding:1.5rem;}
.wrap{max-width:860px;margin:0 auto;}
h1{font-size:1.4rem;font-weight:700;color:#0f172a;}
.sub{color:#64748b;font-size:0.85rem;margin-bottom:1.5rem;margin-top:0.25rem;}
.card{background:white;border-radius:12px;padding:1.5rem;margin-bottom:1.25rem;border:1px solid #e2e8f0;box-shadow:0 1px 3px rgba(0,0,0,0.04);}
.ct{font-weight:600;font-size:0.95rem;margin-bottom:0.85rem;display:flex;align-items:center;gap:0.5rem;}
.fg{display:flex;flex-direction:column;gap:0.35rem;margin-bottom:0.75rem;}
.fg label{font-size:0.75rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:0.04em;}
.fg select,.fg input{padding:0.55rem 0.75rem;border:1px solid #cbd5e1;border-radius:8px;font-size:0.9rem;color:#1e293b;background:white;width:100%;}
.fg select:focus,.fg input:focus{outline:none;border-color:#6366f1;box-shadow:0 0 0 3px rgba(99,102,241,0.12);}
.g2{display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;}
.evgrid{display:grid;grid-template-columns:repeat(3,1fr);gap:0.5rem;}
.ev{padding:0.65rem 0.5rem;border:2px solid #e2e8f0;border-radius:8px;cursor:pointer;font-size:0.82rem;text-align:center;background:white;color:#475569;transition:all 0.12s;line-height:1.4;}
.ev:hover{border-color:#a5b4fc;background:#f0f4ff;}
.ev.active{border-color:#4f46e5;background:#eef2ff;color:#3730a3;font-weight:700;}
.btn{background:#4f46e5;color:white;border:none;padding:0.8rem;border-radius:8px;font-size:0.95rem;font-weight:600;cursor:pointer;width:100%;margin-top:0.5rem;}
.btn:hover{background:#4338ca;} .btn:disabled{background:#94a3b8;cursor:not-allowed;}
.info{font-size:0.8rem;color:#64748b;margin-top:0.3rem;padding:0.5rem 0.6rem;background:#f8fafc;border-radius:6px;}
.info.warn{background:#fef3c7;color:#92400e;}
.badge{padding:2px 10px;border-radius:50px;font-size:0.75rem;font-weight:600;display:inline-block;}
.bg-ok{background:#dcfce7;color:#16a34a;} .bg-fail{background:#fee2e2;color:#dc2626;} .bg-skip{background:#f1f5f9;color:#64748b;}
.rrow{display:flex;justify-content:space-between;align-items:center;padding:0.5rem 0;border-bottom:1px solid #f1f5f9;font-size:0.85rem;}
.rrow:last-child{border:none;}
pre.json{background:#0f172a;color:#93c5fd;border-radius:8px;padding:1rem;font-size:0.72rem;overflow:auto;max-height:280px;margin-top:0.75rem;}
@keyframes spin{to{transform:rotate(360deg);}} .spin{animation:spin 0.7s linear infinite;display:inline-block;}
@media(max-width:580px){.g2,.evgrid{grid-template-columns:1fr;}}
</style>
</head>
<body>
<div class="wrap">
<h1><i class="bi bi-send-check-fill"></i> ทดสอบส่งแจ้งเตือน Odoo</h1>
<p class="sub">จำลอง webhook event จาก Odoo → ผ่าน pipeline จริงทั้งหมด (HMAC signature → log → notification → LINE)</p>

<div class="card">
    <div class="ct"><i class="bi bi-person-lines-fill"></i> ลูกค้า</div>
    <div class="fg">
        <label>เลือกลูกค้า (มี LINE account)</label>
        <select id="custSel" onchange="onCust()">
            <option value="">-- เลือกลูกค้า --</option>
            <?php if (empty($customers)): ?>
            <option value="" disabled>⚠ ไม่พบลูกค้า — ดูข้อมูล debug ด้านล่าง</option>
            <?php else: ?>
            <?php foreach ($customers as $c): ?>
            <option value="<?= $c['odoo_partner_id'] ?>"
                data-line="<?= htmlspecialchars($c['line_user_id']) ?>"
                data-notif="<?= $c['line_notification_enabled'] ? '1' : '0' ?>"
                data-name="<?= htmlspecialchars($c['cust_name']) ?>">
                <?= htmlspecialchars($c['cust_name']) ?><?= $c['cust_ref'] ? ' ('.$c['cust_ref'].')' : '' ?><?= $c['line_notification_enabled'] ? ' ✓' : ' ⚠ ปิดแจ้งเตือน' ?>
            </option>
            <?php endforeach; ?>
            <?php endif; ?>
        </select>
        <div id="custInfo"></div>
    </div>
    <div class="fg" id="partnerIdRow" style="display:none;">
        <label>Odoo Partner ID <span style="color:#dc2626;">*</span> (ลูกค้านี้ยังไม่ได้ link กับ Odoo)</label>
        <input type="number" id="overridePartnerId" placeholder="เช่น 1234" oninput="checkBtn()">
    </div>
</div>

<?php if (!empty($diagMsg) || empty($customers)): ?>
<div class="card" style="border-color:#fde68a;background:#fffbeb;">
    <div class="ct" style="color:#92400e;"><i class="bi bi-exclamation-triangle-fill"></i> Debug Info</div>
    <?php if (!empty($diagMsg)): ?>
    <div style="font-size:0.85rem;color:#78350f;margin-bottom:0.5rem;"><?= htmlspecialchars($diagMsg) ?></div>
    <?php endif; ?>
    <?php if (empty($customers)): ?>
    <div style="font-size:0.85rem;color:#92400e;">
        <b>ไม่พบลูกค้าใน odoo_line_users</b><br>
        ตาราง <code>odoo_line_users</code> ว่างเปล่า หรือยังไม่ได้ link LINE กับ Odoo partner<br><br>
        <b>วิธีแก้:</b> ไปที่ <a href="users.php" style="color:#1d4ed8;">users.php</a> แล้ว link LINE user กับ Odoo partner ID
        หรือถ้าต้องการทดสอบโดยตรง ให้ระบุ LINE User ID ด้านล่างนี้:
    </div>
    <div style="margin-top:0.75rem;display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.5rem;">
        <div class="fg"><label>LINE User ID</label><input type="text" id="manualLineId" placeholder="Uxxxxxxxx"></div>
        <div class="fg"><label>Odoo Partner ID</label><input type="number" id="manualPartnerId" placeholder="เช่น 100"></div>
        <div class="fg"><label>ชื่อลูกค้า</label><input type="text" id="manualName" placeholder="เช่น คุณสมชาย"></div>
    </div>
    <button onclick="useManual()" style="margin-top:0.5rem;padding:0.5rem 1rem;background:#4f46e5;color:white;border:none;border-radius:6px;cursor:pointer;font-size:0.85rem;">ใช้ข้อมูลนี้</button>
    <?php endif; ?>
</div>
<?php endif; ?>

<div class="card">
    <div class="ct"><i class="bi bi-lightning-charge-fill"></i> Event / สถานะออเดอร์</div>
    <div class="evgrid" id="evGrid">
        <div class="ev" onclick="selEv('order.validated','ยืนยันออเดอร์')" data-ev="order.validated">🛒 ยืนยันออเดอร์</div>
        <div class="ev" onclick="selEv('order.picking','เริ่มจัดเตรียม')" data-ev="order.picking">📦 เริ่มจัดเตรียม</div>
        <div class="ev" onclick="selEv('order.packed','แพ็คเสร็จแล้ว')" data-ev="order.packed">� แพ็คเสร็จแล้ว</div>
        <div class="ev" onclick="selEv('order.in_delivery','กำลังจัดส่ง')" data-ev="order.in_delivery" style="border-color:#4f46e5;background:#eef2ff;color:#3730a3;font-weight:700;">🚚 กำลังจัดส่ง ★</div>
        <div class="ev" onclick="selEv('order.delivered','จัดส่งสำเร็จ')" data-ev="order.delivered">✅ จัดส่งสำเร็จ</div>
        <div class="ev" onclick="selEv('order.awaiting_payment','รอชำระเงิน')" data-ev="order.awaiting_payment">💰 รอชำระเงิน</div>
        <div class="ev" onclick="selEv('invoice.created','ออกใบแจ้งหนี้')" data-ev="invoice.created">🧾 ออกใบแจ้งหนี้</div>
        <div class="ev" onclick="selEv('bdo.confirmed','ยืนยัน BDO')" data-ev="bdo.confirmed">🏦 ยืนยัน BDO</div>
        <div class="ev" onclick="selEv('invoice.overdue','ใบแจ้งหนี้เกินกำหนด')" data-ev="invoice.overdue">⚠️ เกินกำหนด</div>
    </div>
    <input type="hidden" id="selEvent">
    <input type="hidden" id="selState">
</div>

<div class="card">
    <div class="ct"><i class="bi bi-bag-check-fill"></i> รายละเอียดออเดอร์</div>
    <div class="fg" style="margin-bottom:0.5rem;">
        <label>เลขออเดอร์ <span style="color:#4f46e5;font-weight:700;">← ใส่แล้วกด Enter เพื่อดึงข้อมูลจริง</span></label>
        <div style="display:flex;gap:0.5rem;">
            <input type="text" id="orderName" placeholder="เช่น SO2602-04582" list="ordList" style="flex:1;" onkeydown="if(event.key==='Enter'){lookupOrder();event.preventDefault();}">
            <button type="button" onclick="lookupOrder()" style="padding:0.55rem 1rem;background:#4f46e5;color:white;border:none;border-radius:8px;cursor:pointer;font-size:0.85rem;white-space:nowrap;">🔍 ดึงข้อมูล</button>
        </div>
        <datalist id="ordList"></datalist>
        <div id="orderLookupInfo" style="margin-top:0.35rem;"></div>
    </div>
    <div class="g2">
        <div class="fg">
            <label>Order ID (ดึงอัตโนมัติ)</label>
            <input type="number" id="orderId" placeholder="เช่น 12345">
        </div>
        <div class="fg">
            <label>ยอดเงิน (฿)</label>
            <input type="number" id="amtTotal" placeholder="เช่น 5000" step="0.01">
        </div>
        <div class="fg">
            <label>ผู้ขนส่ง</label>
            <select id="carrier">
                <option value="">-- ไม่ระบุ --</option>
                <option>Flash Express</option><option>Kerry Express</option>
                <option>J&amp;T Express</option><option>Thailand Post</option>
                <option>SCG Express</option><option>Ninja Van</option>
            </select>
        </div>
        <div class="fg" style="grid-column:1/-1;">
            <label>เลขติดตามพัสดุ</label>
            <input type="text" id="tracking" placeholder="เช่น FX12345678TH">
        </div>
    </div>
    <button class="btn" id="btnSend" onclick="doSend()" disabled>
        <i class="bi bi-send"></i> ส่งทดสอบ (ผ่าน Pipeline จริง)
    </button>
</div>

<div class="card" id="resCard" style="display:none;">
    <div class="ct"><i class="bi bi-clipboard-data-fill"></i> ผลลัพธ์</div>
    <div id="resContent"></div>
</div>
</div>
<script>
let manualCust = null;

function useManual(){
    const lineId = (document.getElementById('manualLineId')||{}).value||'';
    const pid    = parseInt((document.getElementById('manualPartnerId')||{}).value||'0');
    const name   = (document.getElementById('manualName')||{}).value||('Partner #'+pid);
    if(!lineId || !pid){ alert('กรุณาระบุ LINE User ID และ Odoo Partner ID'); return; }
    manualCust = {line_user_id:lineId, partner_id:pid, name:name};
    const info = document.getElementById('custInfo');
    if(info) info.innerHTML='<div class="info">Manual: <b>'+name+'</b> | LINE: <b>'+lineId+'</b></div>';
    // inject fake option
    const sel = document.getElementById('custSel');
    let opt = document.getElementById('manualOpt');
    if(!opt){ opt=document.createElement('option'); opt.id='manualOpt'; sel.appendChild(opt); }
    opt.value=pid; opt.dataset.line=lineId; opt.dataset.notif='1'; opt.dataset.name=name;
    opt.textContent='[Manual] '+name;
    sel.value=pid;
    checkBtn();
}

function onCust(){
    const sel=document.getElementById('custSel');
    const opt=sel.options[sel.selectedIndex];
    const info=document.getElementById('custInfo');
    const pidRow=document.getElementById('partnerIdRow');
    if(!opt.value){info.innerHTML='';if(pidRow)pidRow.style.display='none';checkBtn();return;}
    const notif=opt.dataset.notif==='1';
    const pid=parseInt(opt.value||'0');
    if(pidRow) pidRow.style.display=(pid===0)?'flex':'none';
    info.innerHTML='<div class="info'+(notif?'':' warn')+'">LINE: <b>'+(opt.dataset.line||'ไม่มี')+'</b>'
        +(notif?' &nbsp;✓ แจ้งเตือนเปิดอยู่':' &nbsp;⚠ แจ้งเตือนปิด — webhook จะถูก skip แต่ยังบันทึก log')+'</div>';
    // โหลดออเดอร์ของลูกค้านี้
    fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},
        body:'action=orders&partner_id='+opt.value})
        .then(r=>r.json()).then(data=>{
            const dl=document.getElementById('ordList');
            dl.innerHTML='';
            (data.orders||[]).forEach(o=>{
                const op=document.createElement('option');
                op.value=o.order_name;
                op.setAttribute('data-id',o.order_id||'');
                op.setAttribute('data-amt',o.amount_total||'');
                dl.appendChild(op);
            });
        }).catch(()=>{});
    checkBtn();
}

document.getElementById('orderName').addEventListener('input',function(){
    const dl=document.getElementById('ordList');
    for(let op of dl.options){
        if(op.value===this.value){
            if(op.getAttribute('data-id')) document.getElementById('orderId').value=op.getAttribute('data-id');
            if(op.getAttribute('data-amt')) document.getElementById('amtTotal').value=op.getAttribute('data-amt');
            break;
        }
    }
});

async function lookupOrder(){
    const orderName = document.getElementById('orderName').value.trim();
    if(!orderName){ alert('กรุณาใส่เลขออเดอร์ก่อน'); return; }
    const infoEl = document.getElementById('orderLookupInfo');
    infoEl.innerHTML = '<span style="color:#64748b;font-size:0.8rem;">⏳ กำลังดึงข้อมูล...</span>';
    try {
        const res = await fetch('', {method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'},
            body: 'action=lookup_order&order_name=' + encodeURIComponent(orderName)});
        const d = await res.json();
        if(!d.success){
            infoEl.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">❌ ' + (d.error||'ไม่พบออเดอร์') + '</span>';
            return;
        }
        // ใส่ข้อมูลลงฟอร์ม
        if(d.order_id) document.getElementById('orderId').value = d.order_id;
        if(d.amount_total) document.getElementById('amtTotal').value = d.amount_total;

        // ถ้ามี partner_id — เลือก customer ใน dropdown อัตโนมัติ
        let custMatched = false;
        if(d.partner_id){
            const sel = document.getElementById('custSel');
            for(let opt of sel.options){
                if(parseInt(opt.value) === parseInt(d.partner_id)){
                    sel.value = opt.value;
                    onCust();
                    custMatched = true;
                    break;
                }
            }
        }

        // ถ้าไม่พบใน dropdown แต่มี line_user_id — inject manual option
        if(!custMatched && d.line_user_id){
            manualCust = {line_user_id: d.line_user_id, partner_id: d.partner_id||0, name: d.customer_name||orderName};
            const sel = document.getElementById('custSel');
            let opt = document.getElementById('manualOpt');
            if(!opt){ opt=document.createElement('option'); opt.id='manualOpt'; sel.appendChild(opt); }
            opt.value = d.partner_id||0;
            opt.dataset.line = d.line_user_id;
            opt.dataset.notif = '1';
            opt.dataset.name = d.customer_name||orderName;
            opt.textContent = '[จาก Webhook] ' + (d.customer_name||orderName);
            sel.value = opt.value;
            onCust();
        }

        infoEl.innerHTML = '<div class="info">✅ พบออเดอร์: <b>' + orderName + '</b>'
            + (d.partner_id ? ' | Partner: <b>' + d.partner_id + '</b>' : '')
            + (d.customer_name ? ' | ลูกค้า: <b>' + d.customer_name + '</b>' : '')
            + (d.line_user_id ? ' | LINE: <b>' + d.line_user_id.substring(0,12) + '...</b>' : ' | <span style="color:#dc2626;">ไม่มี LINE ID</span>')
            + (d.amount_total ? ' | ฿' + parseFloat(d.amount_total).toLocaleString() : '')
            + '</div>';
        checkBtn();
    } catch(e){
        infoEl.innerHTML = '<span style="color:#dc2626;font-size:0.8rem;">❌ Error: ' + e.message + '</span>';
    }
}

function selEv(ev,state){
    document.querySelectorAll('.ev').forEach(b=>b.classList.remove('active'));
    const b=document.querySelector('.ev[data-ev="'+ev+'"]');
    if(b)b.classList.add('active');
    document.getElementById('selEvent').value=ev;
    document.getElementById('selState').value=state;
    checkBtn();
}

function checkBtn(){
    const pid=parseInt(document.getElementById('custSel').value||'0');
    const ev=document.getElementById('selEvent').value;
    const overridePid=parseInt((document.getElementById('overridePartnerId')||{}).value||'0');
    const pidOk = pid>0 || overridePid>0 || (manualCust&&manualCust.line_user_id);
    document.getElementById('btnSend').disabled=!(pidOk&&ev);
}

async function doSend(){
    const btn=document.getElementById('btnSend');
    btn.disabled=true;
    btn.innerHTML='<span><i class="bi bi-arrow-repeat spin"></i> กำลังส่งผ่าน pipeline...</span>';
    const sel=document.getElementById('custSel');
    const selOpt=sel.options[sel.selectedIndex];
    const selPid=parseInt(sel.value||'0');
    const overridePid=parseInt((document.getElementById('overridePartnerId')||{}).value||'0');
    const effectivePid=selPid>0?selPid:overridePid;
    // ถ้า partner_id=0 (fallback users) ให้ส่ง line_user_id จาก option
    const selLineId=(selOpt&&selOpt.dataset.line)||'';
    const selName=(selOpt&&selOpt.dataset.name)||'';
    // ส่ง line_user_id เสมอ (ใช้เป็น fallback ใน handler เมื่อ DB ไม่มี line_user_id)
    const sendLineId = manualCust ? manualCust.line_user_id : selLineId;
    const sendName   = manualCust ? manualCust.name : selName;
    const body=new URLSearchParams({
        action:'send',
        partner_id:effectivePid,
        event_type:document.getElementById('selEvent').value,
        state_display:document.getElementById('selState').value,
        order_name:document.getElementById('orderName').value,
        order_id:document.getElementById('orderId').value,
        amount_total:document.getElementById('amtTotal').value,
        carrier:document.getElementById('carrier').value,
        tracking:document.getElementById('tracking').value,
        manual_line_id:sendLineId,
        manual_name:sendName,
    });
    try{
        const res=await fetch('',{method:'POST',headers:{'Content-Type':'application/x-www-form-urlencoded'},body});
        const d=await res.json();
        showResult(d);
    }catch(e){showResult({success:false,error:e.message});}
    btn.disabled=false;
    btn.innerHTML='<i class="bi bi-send"></i> ส่งทดสอบ (ผ่าน Pipeline จริง)';
}

function esc(s){return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');}

function showResult(d){
    const card=document.getElementById('resCard'),c=document.getElementById('resContent');
    card.style.display='block';card.scrollIntoView({behavior:'smooth',block:'start'});

    const ok=d.success;
    let h='<div style="background:'+(ok?'#dcfce7':'#fee2e2')+';padding:0.75rem 1rem;border-radius:8px;margin-bottom:1rem;font-weight:600;color:'+(ok?'#16a34a':'#dc2626')+';">'
        +(ok?'✅ Webhook ส่งสำเร็จ ผ่าน pipeline จริง':'❌ เกิดข้อผิดพลาด')+'</div>';

    h+='<div class="rrow"><span>HTTP Status</span><span class="badge '+(d.http_code===200?'bg-ok':'bg-fail')+'">'+(d.http_code||'-')+'</span></div>';
    h+='<div class="rrow"><span>Delivery ID</span><small style="color:#64748b;">'+esc(d.delivery_id||'-')+'</small></div>';
    h+='<div class="rrow"><span>URL ที่ POST</span><small style="color:#64748b;">'+esc(d.url||'-')+'</small></div>';
    h+='<div class="rrow"><span>Signature</span><small style="color:#64748b;">'+esc(d.signature_prefix||'-')+'</small></div>';
    h+='<div class="rrow"><span>Secret ตั้งค่าไว้</span><span class="badge '+(d.secret_set?'bg-ok':'bg-fail')+'">'+(d.secret_set?'ใช่':'ไม่ได้ตั้งค่า')+'</span></div>';

    if(d.curl_error) h+='<div class="rrow"><span>cURL Error</span><span style="color:#dc2626;">'+esc(d.curl_error)+'</span></div>';
    if(d.error)      h+='<div class="rrow"><span>Error</span><span style="color:#dc2626;">'+esc(d.error)+'</span></div>';

    // Webhook log
    if(d.webhook_log){
        const wl=d.webhook_log;
        const wSt=String(wl.status||'').toLowerCase();
        const wBg=wSt==='success'?'bg-ok':wSt.includes('fail')||wSt==='dead_letter'?'bg-fail':'bg-skip';
        h+='<div style="margin-top:1rem;font-weight:600;font-size:0.85rem;color:#475569;">📋 Webhook Log</div>';
        h+='<div class="rrow"><span>สถานะ Webhook</span><span class="badge '+wBg+'">'+esc(wl.status||'-')+'</span></div>';
        if(wl.error_message) h+='<div class="rrow"><span>Error</span><span style="color:#dc2626;">'+esc(wl.error_message)+'</span></div>';
    } else {
        h+='<div class="rrow"><span>Webhook Log</span><span style="color:#94a3b8;">ยังไม่พบ (อาจยังประมวลผลอยู่)</span></div>';
    }

    // Notification logs
    h+='<div style="margin-top:1rem;font-weight:600;font-size:0.85rem;color:#475569;">🔔 Notification Log</div>';
    if(d.notif_logs&&d.notif_logs.length){
        d.notif_logs.forEach(nl=>{
            const nSt=String(nl.status||'').toLowerCase();
            const nBg=nSt==='sent'||nSt==='success'?'bg-ok':nSt==='skipped'?'bg-skip':'bg-fail';
            const reason=nl.skip_reason||nl.error_message||'';
            const skipMap={disabled:'ปิดการแจ้งเตือน',no_line_user:'ไม่มี LINE',duplicate:'ซ้ำ',preference:'ตั้งค่าไม่รับ',throttle:'จำกัดความถี่'};
            const reasonTh=skipMap[reason]||reason;
            h+='<div class="rrow"><span>สถานะแจ้งเตือน</span><span class="badge '+nBg+'">'+esc(nl.status||'-')+'</span></div>';
            if(reasonTh) h+='<div class="rrow"><span>เหตุผล</span><span style="color:#92400e;">'+esc(reasonTh)+'</span></div>';
        });
    } else {
        h+='<div style="color:#94a3b8;font-size:0.85rem;padding:0.5rem 0;">ยังไม่พบ log (ลองดูที่หน้า Dashboard → Notification Log)</div>';
    }

    // Response จาก endpoint
    if(d.response){
        const resp=d.response;
        h+='<div style="margin-top:1rem;font-weight:600;font-size:0.85rem;color:#475569;">📨 Response จาก Webhook Endpoint</div>';
        h+='<div class="rrow"><span>Status</span><span>'+esc(resp.status||'-')+'</span></div>';
        if(resp.sent_to&&resp.sent_to.length) h+='<div class="rrow"><span>ส่งถึง</span><b style="color:#16a34a;">'+resp.sent_to.join(', ')+'</b></div>';
        if(resp.duration_ms) h+='<div class="rrow"><span>เวลาประมวลผล</span><span>'+resp.duration_ms+' ms</span></div>';
        if(resp.error) h+='<div class="rrow"><span>Error</span><span style="color:#dc2626;">'+esc(resp.error)+'</span></div>';
    }

    // Payload ที่ส่งไป
    if(d.payload_sent){
        h+='<details style="margin-top:1rem;"><summary style="cursor:pointer;font-size:0.85rem;color:#64748b;font-weight:600;">📦 Payload ที่ส่งไป (คลิกดู)</summary>';
        h+='<pre class="json">'+esc(JSON.stringify(d.payload_sent,null,2))+'</pre></details>';
    }

    c.innerHTML=h;
}
</script>
</body>
</html>
