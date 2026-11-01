<?php
/**
 * API: Public Customer Services Catalog
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

try {
    // Fetch only active services ordered by sort_order
    $servicesStmt = $pdo->query("SELECT * FROM services WHERE is_active = 1 ORDER BY sort_order ASC, id ASC");
    $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

    $result = [];
    foreach ($services as $svc) {
        $svcId = $svc['id'];

        // Fetch active packages for this service
        $pkgStmt = $pdo->prepare("SELECT package_code, package_name, price, sort_order FROM service_packages WHERE service_id = ? AND is_active = 1 ORDER BY sort_order ASC");
        $pkgStmt->execute([$svcId]);
        $packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($packages as &$pkg) {
            $pkg['price'] = $pkg['price'] !== null ? floatval($pkg['price']) : null;
            $pkg['sort_order'] = intval($pkg['sort_order']);
        }
        unset($pkg);

        // Fetch active menus for this service
        $menuStmt = $pdo->prepare("SELECT menu_code, category_name, item_name, price, dishes FROM service_menus WHERE service_id = ? AND is_active = 1");
        $menuStmt->execute([$svcId]);
        $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($menus as &$m) {
            $m['price'] = $m['price'] !== null ? floatval($m['price']) : null;
            if (!empty($m['dishes'])) {
                $m['dishes'] = json_decode($m['dishes'], true);
            } else {
                $m['dishes'] = [];
            }
        }
        unset($m);

        // Fetch active add-ons for this service
        $addonStmt = $pdo->prepare("SELECT id, name, description, price_from, fixed_price, allow_qty FROM service_addons WHERE service_id = ? AND is_active = 1");
        $addonStmt->execute([$svcId]);
        $addons = $addonStmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($addons as &$a) {
            $a['fixed_price'] = $a['fixed_price'] !== null ? floatval($a['fixed_price']) : null;
            $a['allow_qty'] = (bool)$a['allow_qty'];
        }
        unset($a);

        $result[] = [
            'id' => $svc['id'],
            'name' => $svc['name'],
            'category' => $svc['category'],
            'base_price' => floatval($svc['base_price']),
            'price_display' => $svc['price_display'],
            'per_head' => $svc['per_head'] !== null ? floatval($svc['per_head']) : null,
            'per_head_by_pkg' => (bool)$svc['per_head_by_pkg'],
            'max_cap' => $svc['max_cap'] !== null ? intval($svc['max_cap']) : null,
            'description' => $svc['description'],
            'inclusions' => !empty($svc['inclusions']) ? json_decode($svc['inclusions'], true) : [],
            'guidelines' => !empty($svc['guidelines']) ? json_decode($svc['guidelines'], true) : [],
            'sort_order' => intval($svc['sort_order']),
            'packages' => $packages,
            'menus' => $menus,
            'addons' => $addons
        ];
    }

    echo json_encode(['ok' => true, 'services' => $result]);
    exit();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
