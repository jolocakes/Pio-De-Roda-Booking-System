<?php
/**
 * API: Admin Services Management
 * Pio De Roda Hospitality Reservation and Event Booking System
 *
 * Endpoints
 *   GET  — full catalog for the admin page (all services, active AND inactive,
 *          with their packages, menus and add-ons) + CSRF token.
 *   PUT  — partial update of a service. Recognised fields: name, description,
 *          base_price, price_display, per_head, max_cap, is_active, sort_order
 *          and (partially) customer_display. Service IDs are immutable.
 *   POST — catalog-item management, selected by "action":
 *          package_save | menu_save | addon_save | set_active
 *
 * Safety conventions:
 *   - Nothing is ever permanently deleted here: deactivation is done through
 *     is_active (soft delete), so reservations keep their historical catalog
 *     references.
 *   - Every write is CSRF-protected and validated server-side.
 *   - Errors are generic to the client; details go to the PHP error log.
 */

header('Content-Type: application/json');
header('Cache-Control: no-store');
require_once __DIR__ . '/../../config/db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// API authentication check returning JSON 401 instead of redirecting.
if (!isset($_SESSION['admin_id']) || !isset($_SESSION['admin_username'])) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'reason' => 'UNAUTHORIZED']);
    exit();
}
// Ensure session CSRF token exists.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

/* ------------------------------------------------------------------ helpers */

function pdrJsonOut(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit();
}

function pdrFail(string $reason, int $status = 400, array $extra = []): void
{
    pdrJsonOut(array_merge(['ok' => false, 'reason' => $reason], $extra), $status);
}

/** URL-safe slug limited to 50 characters (fits every catalog code column). */
function pdrSlug(string $text): string
{
    $slug = strtolower(trim($text));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
    $slug = trim((string)$slug, '-');
    if ($slug === '') {
        $slug = 'item';
    }
    return substr($slug, 0, 50);
}

/** Format an amount for the customer-facing price display (no trailing .00). */
function pdrPeso(float $amount): string
{
    $decimals = (floor($amount) === $amount) ? 0 : 2;
    return '₱' . number_format($amount, $decimals);
}

function pdrDecodeJsonArray($raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $decoded = json_decode($raw, true);
    return is_array($decoded) ? $decoded : [];
}

/** Validated list of plain strings (notes / main courses). */
function pdrStringList($value, int $maxItems, int $maxLength): array
{
    if (!is_array($value)) {
        pdrFail('INVALID_CUSTOMER_DISPLAY');
    }
    $out = [];
    foreach ($value as $item) {
        if (!is_string($item)) {
            pdrFail('INVALID_CUSTOMER_DISPLAY');
        }
        $item = trim($item);
        if ($item === '') {
            continue;
        }
        if (mb_strlen($item) > $maxLength) {
            pdrFail('INVALID_CUSTOMER_DISPLAY');
        }
        $out[] = $item;
    }
    if (count($out) > $maxItems) {
        pdrFail('INVALID_CUSTOMER_DISPLAY');
    }
    return array_values($out);
}

/** Validated RATES rows: [label, value] pairs. */
function pdrRatesList($value): array
{
    if (!is_array($value)) {
        pdrFail('INVALID_CUSTOMER_DISPLAY');
    }
    $out = [];
    foreach ($value as $row) {
        if (!is_array($row) || count($row) < 2) {
            pdrFail('INVALID_CUSTOMER_DISPLAY');
        }
        $values = array_values($row);
        $label = is_string($values[0]) ? trim($values[0]) : '';
        $amount = is_string($values[1]) ? trim($values[1]) : '';
        if ($label === '' && $amount === '') {
            continue;
        }
        if ($label === '' || $amount === '') {
            pdrFail('INVALID_CUSTOMER_DISPLAY');
        }
        if (mb_strlen($label) > 80 || mb_strlen($amount) > 80) {
            pdrFail('INVALID_CUSTOMER_DISPLAY');
        }
        $out[] = [$label, $amount];
    }
    if (count($out) > 40) {
        pdrFail('INVALID_CUSTOMER_DISPLAY');
    }
    return $out;
}

function pdrServiceExists(PDO $pdo, string $serviceId): bool
{
    $stmt = $pdo->prepare('SELECT 1 FROM services WHERE id = ?');
    $stmt->execute([$serviceId]);
    return (bool)$stmt->fetchColumn();
}

/** Strict CSRF check for every state-changing request (header only). */
function pdrRequireCsrf(): void
{
    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $clientToken = '';
    foreach ($headers as $name => $value) {
        if (strcasecmp($name, 'X-CSRF-Token') === 0) {
            $clientToken = $value;
            break;
        }
    }
    if (empty($clientToken) && isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
        $clientToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
    }
    if (empty($clientToken) || !hash_equals($_SESSION['csrf_token'], $clientToken)) {
        pdrFail('INVALID_CSRF_TOKEN', 403);
    }
}

/* ------------------------------------------------------------------- router */

try {
    if ($method === 'GET') {
        $servicesStmt = $pdo->query('SELECT * FROM services ORDER BY sort_order ASC, id ASC');
        $services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

        $pkgStmt = $pdo->prepare(
            'SELECT id, package_code, package_name, price, max_cap, is_active, sort_order
               FROM service_packages WHERE service_id = ? ORDER BY sort_order ASC, id ASC'
        );
        $menuStmt = $pdo->prepare(
            'SELECT id, menu_code, category_name, item_name, price, dishes, is_active
               FROM service_menus WHERE service_id = ? ORDER BY menu_code ASC, id ASC'
        );
        $addonStmt = $pdo->prepare(
            'SELECT id, service_id, name, description, price_from, fixed_price, allow_qty, is_active
               FROM service_addons WHERE service_id = ? ORDER BY id ASC'
        );

        $result = [];
        foreach ($services as $svc) {
            $svcId = $svc['id'];

            $pkgStmt->execute([$svcId]);
            $packages = $pkgStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($packages as &$pkg) {
                $pkg['price'] = $pkg['price'] === null ? null : (float)$pkg['price'];
                $pkg['max_cap'] = $pkg['max_cap'] === null ? null : (int)$pkg['max_cap'];
                $pkg['is_active'] = (bool)$pkg['is_active'];
                $pkg['sort_order'] = (int)$pkg['sort_order'];
            }
            unset($pkg);

            $menuStmt->execute([$svcId]);
            $menus = $menuStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($menus as &$menu) {
                $menu['price'] = $menu['price'] === null ? null : (float)$menu['price'];
                $menu['dishes'] = pdrDecodeJsonArray($menu['dishes'] ?? null);
                $menu['is_active'] = (bool)$menu['is_active'];
            }
            unset($menu);

            $addonStmt->execute([$svcId]);
            $addons = $addonStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($addons as &$addon) {
                $addon['fixed_price'] = $addon['fixed_price'] === null ? null : (float)$addon['fixed_price'];
                $addon['allow_qty'] = (bool)$addon['allow_qty'];
                $addon['is_active'] = (bool)$addon['is_active'];
            }
            unset($addon);

            $result[] = [
                'id' => $svc['id'],
                'name' => $svc['name'],
                'category' => $svc['category'],
                'base_price' => $svc['base_price'] === null ? null : (float)$svc['base_price'],
                'price_display' => $svc['price_display'],
                'per_head' => $svc['per_head'] === null ? null : (float)$svc['per_head'],
                'per_head_by_pkg' => (bool)$svc['per_head_by_pkg'],
                'max_cap' => $svc['max_cap'] === null ? null : (int)$svc['max_cap'],
                'description' => $svc['description'],
                'inclusions' => pdrDecodeJsonArray($svc['inclusions'] ?? null),
                'guidelines' => pdrDecodeJsonArray($svc['guidelines'] ?? null),
                'customer_display' => pdrDecodeJsonArray($svc['customer_display'] ?? null),
                'is_active' => (bool)$svc['is_active'],
                'sort_order' => (int)$svc['sort_order'],
                'updated_at' => $svc['updated_at'] ?? null,
                'packages' => $packages,
                'menus' => $menus,
                'addons' => $addons,
            ];
        }

        header('X-CSRF-Token: ' . $_SESSION['csrf_token']);
        pdrJsonOut(['ok' => true, 'csrf_token' => $_SESSION['csrf_token'], 'services' => $result]);
    }

    if ($method !== 'PUT' && $method !== 'POST') {
        pdrFail('METHOD_NOT_ALLOWED', 405);
    }

    pdrRequireCsrf();

    $data = json_decode(file_get_contents('php://input'), true);
    if (!is_array($data)) {
        pdrFail('INVALID_INPUT');
    }

    /* ------------------------------------------------------- service update */
    if ($method === 'PUT') {
        $serviceId = trim((string)($data['id'] ?? ''));
        if ($serviceId === '' || strlen($serviceId) > 50) {
            pdrFail('INVALID_INPUT');
        }

        $rowStmt = $pdo->prepare('SELECT * FROM services WHERE id = ?');
        $rowStmt->execute([$serviceId]);
        $row = $rowStmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            pdrFail('NOT_FOUND', 404);
        }

        $set = [];
        $params = [];

        if (array_key_exists('name', $data)) {
            $value = is_string($data['name']) ? trim($data['name']) : '';
            if ($value === '' || mb_strlen($value) > 100) {
                pdrFail('INVALID_NAME');
            }
            $set[] = 'name = ?';
            $params[] = $value;
        }

        if (array_key_exists('description', $data)) {
            $value = is_string($data['description']) ? trim($data['description']) : '';
            if (mb_strlen($value) > 5000) {
                pdrFail('INVALID_DESCRIPTION');
            }
            $set[] = 'description = ?';
            $params[] = $value === '' ? null : $value;
        }

        $baseProvided = array_key_exists('base_price', $data);
        if ($baseProvided) {
            if (!is_numeric($data['base_price']) || (float)$data['base_price'] < 0 || (float)$data['base_price'] > 99999999.99) {
                pdrFail('INVALID_BASE_PRICE');
            }
            $set[] = 'base_price = ?';
            $params[] = (float)$data['base_price'];
        }

        if (array_key_exists('price_display', $data)) {
            $value = is_string($data['price_display']) ? trim($data['price_display']) : '';
            if (mb_strlen($value) > 50) {
                pdrFail('INVALID_PRICE_DISPLAY');
            }
            $set[] = 'price_display = ?';
            $params[] = $value === '' ? null : $value;
        } elseif ($baseProvided) {
            // Keep the customer-facing display amount in step with the base price
            // unless the admin deliberately supplied their own display text.
            $existing = (string)($row['price_display'] ?? '');
            $newDisplay = '';
            $replaced = 0;
            if ($existing !== '') {
                $newDisplay = preg_replace(
                    '/₱\s*[\d,]+(?:\.\d+)?/u',
                    pdrPeso((float)$data['base_price']),
                    $existing,
                    1,
                    $replaced
                );
            }
            if ($existing === '' || $replaced === 0 || $newDisplay === null) {
                $newDisplay = pdrPeso((float)$data['base_price']);
            }
            $set[] = 'price_display = ?';
            $params[] = mb_substr($newDisplay, 0, 50);
        }

        if (array_key_exists('per_head', $data)) {
            $value = $data['per_head'];
            if ($value === null || $value === '') {
                $set[] = 'per_head = NULL';
            } elseif (!is_numeric($value) || (float)$value < 0) {
                pdrFail('INVALID_PER_HEAD');
            } else {
                $set[] = 'per_head = ?';
                $params[] = (float)$value;
            }
        }

        if (array_key_exists('max_cap', $data)) {
            $value = $data['max_cap'];
            if ($value === null || $value === '') {
                $set[] = 'max_cap = NULL';
            } elseif (!is_numeric($value) || (int)$value < 1) {
                pdrFail('INVALID_MAX_CAP');
            } else {
                $set[] = 'max_cap = ?';
                $params[] = (int)$value;
            }
        }

        if (array_key_exists('is_active', $data)) {
            $set[] = 'is_active = ?';
            $params[] = empty($data['is_active']) ? 0 : 1;
        }

        if (array_key_exists('sort_order', $data)) {
            if (!is_numeric($data['sort_order']) || (int)$data['sort_order'] < 0) {
                pdrFail('INVALID_SORT_ORDER');
            }
            $set[] = 'sort_order = ?';
            $params[] = (int)$data['sort_order'];
        }

        if (array_key_exists('customer_display', $data)) {
            if (!is_array($data['customer_display'])) {
                pdrFail('INVALID_CUSTOMER_DISPLAY');
            }
            $current = pdrDecodeJsonArray($row['customer_display'] ?? null);
            $incoming = $data['customer_display'];
            if (array_key_exists('rates', $incoming)) {
                $current['rates'] = pdrRatesList($incoming['rates']);
            }
            if (array_key_exists('notes', $incoming)) {
                $current['notes'] = pdrStringList($incoming['notes'], 50, 300);
            }
            if (array_key_exists('mainCourses', $incoming)) {
                $current['mainCourses'] = pdrStringList($incoming['mainCourses'], 30, 120);
            }
            // Any other key already present (e.g. inclusionsTable) is preserved.
            $set[] = 'customer_display = ?';
            $params[] = empty($current) ? null : json_encode($current, JSON_UNESCAPED_UNICODE);
        }

        if (empty($set)) {
            pdrFail('NOTHING_TO_UPDATE');
        }

        $params[] = $serviceId;
        $update = $pdo->prepare('UPDATE services SET ' . implode(', ', $set) . ' WHERE id = ?');
        $update->execute($params);

        pdrJsonOut(['ok' => true, 'id' => $serviceId]);
    }

    /* ------------------------------------------------------- catalog writes */
    $action = trim((string)($data['action'] ?? ''));

    if ($action === 'set_active') {
        $kind = trim((string)($data['kind'] ?? ''));
        if (!array_key_exists('is_active', $data)) {
            pdrFail('INVALID_INPUT');
        }
        $active = empty($data['is_active']) ? 0 : 1;
        $itemId = $data['id'] ?? null;

        if ($kind === 'service') {
            $serviceId = trim((string)$itemId);
            if (!pdrServiceExists($pdo, $serviceId)) {
                pdrFail('NOT_FOUND', 404);
            }
            $stmt = $pdo->prepare('UPDATE services SET is_active = ? WHERE id = ?');
            $stmt->execute([$active, $serviceId]);
            pdrJsonOut(['ok' => true, 'kind' => $kind, 'id' => $serviceId, 'is_active' => $active]);
        }
        if ($kind === 'package' || $kind === 'menu') {
            $numericId = (int)$itemId;
            if ($numericId < 1) {
                pdrFail('INVALID_INPUT');
            }
            $table = $kind === 'package' ? 'service_packages' : 'service_menus';
            $check = $pdo->prepare("SELECT 1 FROM {$table} WHERE id = ?");
            $check->execute([$numericId]);
            if (!$check->fetchColumn()) {
                pdrFail('NOT_FOUND', 404);
            }
            $stmt = $pdo->prepare("UPDATE {$table} SET is_active = ? WHERE id = ?");
            $stmt->execute([$active, $numericId]);
            pdrJsonOut(['ok' => true, 'kind' => $kind, 'id' => $numericId, 'is_active' => $active]);
        }
        if ($kind === 'addon') {
            $addonId = trim((string)$itemId);
            $check = $pdo->prepare('SELECT 1 FROM service_addons WHERE id = ?');
            $check->execute([$addonId]);
            if (!$check->fetchColumn()) {
                pdrFail('NOT_FOUND', 404);
            }
            $stmt = $pdo->prepare('UPDATE service_addons SET is_active = ? WHERE id = ?');
            $stmt->execute([$active, $addonId]);
            pdrJsonOut(['ok' => true, 'kind' => $kind, 'id' => $addonId, 'is_active' => $active]);
        }
        pdrFail('UNKNOWN_KIND');
    }

    if ($action === 'package_save') {
        $serviceId = trim((string)($data['service_id'] ?? ''));
        if (!pdrServiceExists($pdo, $serviceId)) {
            pdrFail('NOT_FOUND', 404);
        }

        $packageId = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : 0;
        $existing = null;
        if ($packageId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM service_packages WHERE id = ?');
            $stmt->execute([$packageId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing || $existing['service_id'] !== $serviceId) {
                pdrFail('NOT_FOUND', 404);
            }
        }

        $name = is_string($data['package_name'] ?? null) ? trim($data['package_name']) : '';
        if ($name === '' || mb_strlen($name) > 150) {
            pdrFail('INVALID_PACKAGE_NAME');
        }

        $priceRaw = array_key_exists('price', $data) ? $data['price'] : ($existing['price'] ?? null);
        if (!is_numeric($priceRaw) || (float)$priceRaw < 0 || (float)$priceRaw > 99999999.99) {
            pdrFail('INVALID_PRICE');
        }

        $codeInput = is_string($data['package_code'] ?? null) ? trim($data['package_code']) : '';
        if ($codeInput !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/i', $codeInput)) {
            pdrFail('INVALID_PACKAGE_CODE');
        }

        $maxCapRaw = array_key_exists('max_cap', $data) ? $data['max_cap'] : ($existing['max_cap'] ?? null);
        if ($maxCapRaw === null || $maxCapRaw === '') {
            $maxCap = null;
        } elseif (!is_numeric($maxCapRaw) || (int)$maxCapRaw < 1) {
            pdrFail('INVALID_MAX_CAP');
        } else {
            $maxCap = (int)$maxCapRaw;
        }

        $sortProvided = array_key_exists('sort_order', $data)
            && $data['sort_order'] !== null
            && $data['sort_order'] !== '';
        if ($sortProvided && (!is_numeric($data['sort_order']) || (int)$data['sort_order'] < 0)) {
            pdrFail('INVALID_SORT_ORDER');
        }
        $sortOrder = $sortProvided ? (int)$data['sort_order'] : null;
        if ($sortOrder === null) {
            if ($existing) {
                $sortOrder = (int)$existing['sort_order'];
            } else {
                $maxSort = $pdo->prepare('SELECT COALESCE(MAX(sort_order), 0) FROM service_packages WHERE service_id = ?');
                $maxSort->execute([$serviceId]);
                $sortOrder = (int)$maxSort->fetchColumn() + 1;
            }
        }

        $isActive = array_key_exists('is_active', $data)
            ? (empty($data['is_active']) ? 0 : 1)
            : ($existing ? (int)$existing['is_active'] : 1);

        // Generate a code when none is given; keep user-supplied codes stable.
        $code = $codeInput !== '' ? $codeInput : pdrSlug($name);
        if ($codeInput === '') {
            $base = $code;
            $suffix = 2;
            $dup = $pdo->prepare('SELECT 1 FROM service_packages WHERE service_id = ? AND package_code = ? AND id <> ?');
            while (true) {
                $dup->execute([$serviceId, $code, $packageId]);
                if (!$dup->fetchColumn()) {
                    break;
                }
                $code = substr($base, 0, 95) . '-' . $suffix;
                $suffix++;
            }
        } else {
            $dup = $pdo->prepare('SELECT 1 FROM service_packages WHERE service_id = ? AND package_code = ? AND id <> ?');
            $dup->execute([$serviceId, $code, $packageId]);
            if ($dup->fetchColumn()) {
                pdrFail('DUPLICATE_CODE');
            }
        }

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE service_packages
                    SET package_code = ?, package_name = ?, price = ?, max_cap = ?, is_active = ?, sort_order = ?
                  WHERE id = ?'
            );
            $stmt->execute([$code, $name, (float)$priceRaw, $maxCap, $isActive, $sortOrder, $packageId]);
            pdrJsonOut(['ok' => true, 'id' => $packageId]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO service_packages (service_id, package_code, package_name, price, max_cap, is_active, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$serviceId, $code, $name, (float)$priceRaw, $maxCap, $isActive, $sortOrder]);
        pdrJsonOut(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'menu_save') {
        $serviceId = trim((string)($data['service_id'] ?? ''));
        if (!pdrServiceExists($pdo, $serviceId)) {
            pdrFail('NOT_FOUND', 404);
        }

        $menuId = isset($data['id']) && $data['id'] !== '' ? (int)$data['id'] : 0;
        $existing = null;
        if ($menuId > 0) {
            $stmt = $pdo->prepare('SELECT * FROM service_menus WHERE id = ?');
            $stmt->execute([$menuId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing || $existing['service_id'] !== $serviceId) {
                pdrFail('NOT_FOUND', 404);
            }
        }

        $itemName = is_string($data['item_name'] ?? null) ? trim($data['item_name']) : '';
        if ($itemName === '' || mb_strlen($itemName) > 150) {
            pdrFail('INVALID_ITEM_NAME');
        }
        $categoryName = is_string($data['category_name'] ?? null) ? trim($data['category_name']) : '';
        if ($categoryName === '' || mb_strlen($categoryName) > 100) {
            pdrFail('INVALID_CATEGORY_NAME');
        }

        $priceRaw = array_key_exists('price', $data) ? $data['price'] : ($existing['price'] ?? null);
        if ($priceRaw === null || $priceRaw === '') {
            $price = null;
        } elseif (!is_numeric($priceRaw) || (float)$priceRaw < 0 || (float)$priceRaw > 99999999.99) {
            pdrFail('INVALID_PRICE');
        } else {
            $price = (float)$priceRaw;
        }

        $dishesRaw = array_key_exists('dishes', $data)
            ? $data['dishes']
            : pdrDecodeJsonArray($existing['dishes'] ?? null);
        if (!is_array($dishesRaw)) {
            pdrFail('INVALID_DISHES');
        }
        $dishes = [];
        foreach ($dishesRaw as $dish) {
            if (!is_string($dish)) {
                pdrFail('INVALID_DISHES');
            }
            $dish = trim($dish);
            if ($dish === '') {
                continue;
            }
            if (mb_strlen($dish) > 200) {
                pdrFail('INVALID_DISHES');
            }
            $dishes[] = $dish;
        }
        if (count($dishes) > 100) {
            pdrFail('INVALID_DISHES');
        }

        $codeInput = is_string($data['menu_code'] ?? null) ? trim($data['menu_code']) : '';
        if ($codeInput !== '' && !preg_match('/^[a-z0-9][a-z0-9-]{0,99}$/i', $codeInput)) {
            pdrFail('INVALID_MENU_CODE');
        }
        $code = $codeInput !== '' ? $codeInput : pdrSlug($itemName);
        if ($codeInput === '') {
            $base = $code;
            $suffix = 2;
            $dup = $pdo->prepare('SELECT 1 FROM service_menus WHERE service_id = ? AND menu_code = ? AND id <> ?');
            while (true) {
                $dup->execute([$serviceId, $code, $menuId]);
                if (!$dup->fetchColumn()) {
                    break;
                }
                $code = substr($base, 0, 95) . '-' . $suffix;
                $suffix++;
            }
        } else {
            $dup = $pdo->prepare('SELECT 1 FROM service_menus WHERE service_id = ? AND menu_code = ? AND id <> ?');
            $dup->execute([$serviceId, $code, $menuId]);
            if ($dup->fetchColumn()) {
                pdrFail('DUPLICATE_CODE');
            }
        }

        $isActive = array_key_exists('is_active', $data)
            ? (empty($data['is_active']) ? 0 : 1)
            : ($existing ? (int)$existing['is_active'] : 1);

        $dishesJson = empty($dishes) ? null : json_encode($dishes, JSON_UNESCAPED_UNICODE);

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE service_menus
                    SET menu_code = ?, category_name = ?, item_name = ?, price = ?, dishes = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([$code, $categoryName, $itemName, $price, $dishesJson, $isActive, $menuId]);
            pdrJsonOut(['ok' => true, 'id' => $menuId]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO service_menus (service_id, menu_code, category_name, item_name, price, dishes, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$serviceId, $code, $categoryName, $itemName, $price, $dishesJson, $isActive]);
        pdrJsonOut(['ok' => true, 'id' => (int)$pdo->lastInsertId()]);
    }

    if ($action === 'addon_save') {
        $serviceId = trim((string)($data['service_id'] ?? ''));
        if (!pdrServiceExists($pdo, $serviceId)) {
            pdrFail('NOT_FOUND', 404);
        }

        $addonId = is_string($data['id'] ?? null) ? trim($data['id']) : '';
        if (strlen($addonId) > 50) {
            pdrFail('INVALID_INPUT');
        }
        $existing = null;
        if ($addonId !== '') {
            $stmt = $pdo->prepare('SELECT * FROM service_addons WHERE id = ?');
            $stmt->execute([$addonId]);
            $existing = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$existing || $existing['service_id'] !== $serviceId) {
                pdrFail('NOT_FOUND', 404);
            }
        }

        $name = is_string($data['name'] ?? null) ? trim($data['name']) : '';
        if ($name === '' || mb_strlen($name) > 100) {
            pdrFail('INVALID_ADDON_NAME');
        }

        $description = is_string($data['description'] ?? null) ? trim($data['description']) : '';
        if (mb_strlen($description) > 2000) {
            pdrFail('INVALID_DESCRIPTION');
        }

        $priceFrom = is_string($data['price_from'] ?? null) ? trim($data['price_from']) : '';
        $fixedRaw = array_key_exists('fixed_price', $data) ? $data['fixed_price'] : null;
        if ($fixedRaw === '' || $fixedRaw === null) {
            $fixedPrice = null;
        } elseif (!is_numeric($fixedRaw) || (float)$fixedRaw < 0 || (float)$fixedRaw > 99999999.99) {
            pdrFail('INVALID_FIXED_PRICE');
        } else {
            $fixedPrice = (float)$fixedRaw;
        }

        if ($priceFrom === '' && $fixedPrice === null) {
            pdrFail('UNPRICEABLE_ADDON');
        }
        if ($priceFrom !== '' && $fixedPrice !== null) {
            pdrFail('AMBIGUOUS_PRICING');
        }
        if ($priceFrom !== '' && !pdrServiceExists($pdo, $priceFrom)) {
            pdrFail('INVALID_PRICE_FROM');
        }

        $allowQty = empty($data['allow_qty']) ? 0 : 1;
        $isActive = array_key_exists('is_active', $data)
            ? (empty($data['is_active']) ? 0 : 1)
            : ($existing ? (int)$existing['is_active'] : 1);

        if ($addonId === '') {
            $base = pdrSlug($name);
            $addonId = $base;
            $suffix = 2;
            $dup = $pdo->prepare('SELECT 1 FROM service_addons WHERE id = ?');
            while (true) {
                $dup->execute([$addonId]);
                if (!$dup->fetchColumn()) {
                    break;
                }
                $addonId = substr($base, 0, 45) . '-' . $suffix;
                $suffix++;
            }
        }

        $descriptionValue = $description === '' ? null : $description;

        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE service_addons
                    SET name = ?, description = ?, price_from = ?, fixed_price = ?, allow_qty = ?, is_active = ?
                  WHERE id = ?'
            );
            $stmt->execute([$name, $descriptionValue, $priceFrom === '' ? null : $priceFrom, $fixedPrice, $allowQty, $isActive, $addonId]);
            pdrJsonOut(['ok' => true, 'id' => $addonId]);
        }

        $stmt = $pdo->prepare(
            'INSERT INTO service_addons (id, service_id, name, description, price_from, fixed_price, allow_qty, is_active)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$addonId, $serviceId, $name, $descriptionValue, $priceFrom === '' ? null : $priceFrom, $fixedPrice, $allowQty, $isActive]);
        pdrJsonOut(['ok' => true, 'id' => $addonId]);
    }

    pdrFail('UNKNOWN_ACTION', 400);

} catch (Throwable $e) {
    // Never leak SQL details to the client; keep the detail in the server log.
    error_log('Admin services API failure: ' . $e->getMessage());
    pdrFail('SERVER_ERROR', 500);
}
