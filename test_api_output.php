<?php
require_once __DIR__ . '/config/db.php';
$_SERVER['REQUEST_METHOD'] = 'GET';
ob_start();
require __DIR__ . '/api/services.php';
$output = ob_get_clean();
$data = json_decode($output, true);
echo "JSON Valid: " . (json_last_error() === JSON_ERROR_NONE ? 'YES' : 'NO') . "\n";
echo "OK: " . ($data['ok'] ? 'true' : 'false') . "\n";
$services = $data['services'] ?? [];
echo "Services Count: " . count($services) . "\n";
$totalPkgs = 0; $totalMenus = 0; $totalAddons = 0;
foreach ($services as $svc) {
    $totalPkgs += count($svc['packages']);
    $totalMenus += count($svc['menus']);
    $totalAddons += count($svc['addons']);
}
echo "Packages Count: {$totalPkgs}\n";
echo "Menus Count: {$totalMenus}\n";
echo "Addons Count: {$totalAddons}\n";
echo "Sample Service ID: " . ($services[0]['id'] ?? 'none') . "\n";
echo "Sample Service Name: " . ($services[0]['name'] ?? 'none') . "\n";
echo "Sample Package Count: " . count($services[0]['packages']) . "\n";
