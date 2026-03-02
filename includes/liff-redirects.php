<?php
/**
 * LIFF Redirect Rules
 * จัดการ redirect สำหรับ LIFF URLs เก่าไปยัง SPA
 */

require_once __DIR__ . '/redirects.php';

// LIFF specific redirect map if not already in redirects.php
$liffRedirects = [
    'liff-app.php' => 'liff/index.php?page=app',
    'liff-appointment.php' => 'liff/index.php?page=appointment',
    'liff-checkout.php' => 'liff/index.php?page=checkout',
    'liff-consent.php' => 'liff/index.php?page=consent',
    'liff-main.php' => 'liff/index.php?page=main',
    'liff-member-card.php' => 'liff/index.php?page=member',
    'liff-my-appointments.php' => 'liff/index.php?page=appointments',
    'liff-my-orders.php' => 'liff/index.php?page=orders',
    'liff-order-detail.php' => 'liff/index.php?page=order-detail',
    'liff-pharmacy-consult.php' => 'liff/index.php?page=consult',
    'liff-points-history.php' => 'liff/index.php?page=points',
    'liff-points-rules.php' => 'liff/index.php?page=points-rules',
    'liff-product-detail.php' => 'liff/index.php?page=product',
    'liff-promotions.php' => 'liff/index.php?page=promotions',
    'liff-redeem-points.php' => 'liff/index.php?page=redeem',
    'liff-register.php' => 'liff/index.php?page=register',
    'liff-settings.php' => 'liff/index.php?page=settings',
    'liff-share.php' => 'liff/index.php?page=share',
    'liff-shop.php' => 'liff/index.php?page=shop',
    'liff-shop-v3.php' => 'liff/index.php?page=shop',
    'liff-symptom-assessment.php' => 'liff/index.php?page=symptom',
    'liff-video-call.php' => 'liff/index.php?page=video-call',
    'liff-video-call-pro.php' => 'liff/index.php?page=video-call',
    'liff-wishlist.php' => 'liff/index.php?page=wishlist',
];

// Merge with main redirect map if needed
foreach ($liffRedirects as $old => $new) {
    if (!isset($redirectMap[$old])) {
        addRedirect($old, $new);
    }
}
