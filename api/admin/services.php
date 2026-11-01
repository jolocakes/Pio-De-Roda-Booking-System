<?php
/**
 * API: Admin Services Management
 * Pio De Roda Hospitality Reservation and Event Booking System
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API Authentication check returning JSON 401 instead of redirecting
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}
// Ensure session CSRF token exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


$method = $_SERVER['REQUEST_METHOD'];

try {
    if ($method === 'GET') {
        $servicesStmt = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC, id ASC");
        $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

        $result = [];
        foreach ($services as $svc) {
            $svcId = $svc['id'];

            $pkgStmt = $pdo->prepare("SELECT package_code, package_name, price, is_active, sort_order FROM service_packages WHERE service_id = ? ORDER BY sort_order ASC");
            $pkgStmt->execute([$svcId]);
            $packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);

            $menuStmt = $pdo->prepare("SELECT menu_code, category_name, item_name, price, dishes, is_active FROM service_menus WHERE service_id = ?");
            $menuStmt->execute([$svcId]);
            $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($menus as &$m) {
                if (!empty($m['dishes'])) {
                    $m['dishes'] = json_decode($m['dishes'], true);
                }
            }
            unset($m);

            $addonStmt = $pdo->prepare("SELECT id, name, description, price_from, fixed_price, allow_qty, is_active FROM service_addons WHERE service_id = ?");
            $addonStmt->execute([$svcId]);
            $addons = $addonStmt->fetchAll(PDO::FETCH_ASSOC);

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
                'is_active' => (bool)$svc['is_active'],
                'sort_order' => intval($svc['sort_order']),
                'packages' => $packages,
                'menus' => $menus,
                'addons' => $addons
            ];
        }

        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        echo json_encode(['ok' => true, 'csrf_token' => $_SESSION['csrf_token'], 'services' => $result]);
        exit();
    }


    if ($method === 'POST' || $method === 'PUT') {
        // Validate CSRF token for state-changing requests (strict header check)
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $clientToken = '';
        foreach ($headers as $name => $value) {
            if (strcasecmp($name, 'X-CSRF-Token') === 0) {
                $clientToken = $value;
                break;
            }
        }
        // Fallback for $_SERVER HTTP_X_CSRF_TOKEN if getallheaders() is absent
        if (empty($clientToken) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        if (empty($clientToken) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'reason' => 'INVALID_CSRF_TOKEN']);
            exit();
        }


        $rawInput = file_get_contents('php://input');
        $data = json_decode($rawInput, true);

        $serviceId = trim($data['id'] ?? '');
        $basePrice = $data['base_price'] ?? null;
        $isActive = isset($data['is_active']) ? (int)$data['is_active'] : null;

        if (empty($serviceId) || $basePrice === null || !is_numeric($basePrice) || floatval($basePrice) < 0) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'reason' => 'INVALID_INPUT']);
            exit();
        }

        // Verify service exists
        $check = $pdo->prepare("SELECT id FROM services WHERE id = ?");
        $check->execute([$serviceId]);
        if (!$check->fetch()) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'reason' => 'NOT_FOUND']);
            exit();
        }

        // Update service base price and optionally is_active if provided
        if ($isActive !== null) {
            $update = $pdo->prepare("UPDATE services SET base_price = ?, is_active = ? WHERE id = ?");
            $update->execute([floatval($basePrice), $isActive, $serviceId]);
        } else {
            $update = $pdo->prepare("UPDATE services SET base_price = ? WHERE id = ?");
            $update->execute([floatval($basePrice), $serviceId]);
        }

        echo json_encode(['ok' => true, 'id' => $serviceId, 'base_price' => floatval($basePrice)]);
        exit();
    }

    http_response_code(405);
    echo json_encode(['ok' => false, 'reason' => 'METHOD_NOT_ALLOWED']);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'reason' => 'SERVER_ERROR']);
}
