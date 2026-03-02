<?php
/**
 * Dashboard - Consolidated Dashboard Page
 * รวมหน้า Executive Dashboard และ CRM Dashboard เป็นหน้าเดียว
 * เมนูย้ายไปอยู่ใน Sidebar แล้ว
 * 
 * @package FileConsolidation
 * @version 2.0.0
 * 
 * Consolidates:
 * - executive-dashboard.php → ?tab=executive
 * - crm-dashboard.php → ?tab=crm
 * 
 * Requirements: 10.1, 10.2, 10.3, 10.4
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth_check.php';
require_once 'includes/shop-data-source.php';

$db = Database::getInstance()->getConnection();
$currentBotId = $_SESSION['current_bot_id'] ?? null;

$orderDataSource = getShopOrderDataSource($db, $currentBotId);
$isOdooMode = $orderDataSource === 'odoo';

// Get active tab from URL
$activeTab = $_GET['tab'] ?? ($isOdooMode ? 'odoo-overview' : 'executive');

// Validate tab
$validTabs = ['executive', 'crm', 'odoo-overview'];
if (!in_array($activeTab, $validTabs)) {
    $activeTab = $isOdooMode ? 'odoo-overview' : 'executive';
}

// Set page title based on active tab
$pageTitles = [
    'executive' => 'Executive Dashboard',
    'crm' => 'CRM Dashboard',
    'odoo-overview' => 'Odoo Overview'
];
$pageTitle = $pageTitles[$activeTab] ?? 'Dashboard';

require_once 'includes/header.php';
?>

<div class="space-y-6">
    <!-- Tab Content -->
    <div class="bg-white rounded-xl shadow-sm">
        <?php
        switch ($activeTab) {
            case 'odoo-overview':
                include 'includes/dashboard/odoo-overview.php';
                break;
            case 'crm':
                include 'includes/dashboard/crm.php';
                break;
            case 'executive':
            default:
                include 'includes/dashboard/executive.php';
                break;
        }
        ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
