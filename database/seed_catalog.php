<?php
require_once __DIR__ . '/../config/db.php';

$catalog = [
    [
        'id' => 'catering-menu',
        'name' => 'Catering with Menu',
        'category' => 'Catering',
        'base_price' => 1000.00,
        'price_display' => '₱1,000 per head',
        'per_head' => null,
        'per_head_by_pkg' => 1,
        'max_cap' => 150,
        'description' => 'Full-course catering with a customizable menu for any occasion.',
        'inclusions' => json_encode(["Tables and chairs", "Food and beverages", "Basic decorations"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'cat-2-courses', 'name' => '2 Main Courses', 'price' => 1000.00],
            ['code' => 'cat-3-courses', 'name' => '3 Main Courses', 'price' => 1100.00],
            ['code' => 'cat-4-courses', 'name' => '4 Main Courses', 'price' => 1300.00],
        ],
        'menus' => []
    ],
    [
        'id' => 'venue',
        'name' => 'Venue Rentals',
        'category' => 'Venue',
        'base_price' => 15000.00,
        'price_display' => '₱15,000',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => 120,
        'description' => 'A comfortable and welcoming space for celebrations.',
        'inclusions' => json_encode(["Air-conditioned venue"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'venue-ext-30-35', 'name' => 'Extension Hall (30-35 Persons)', 'price' => 15000.00],
            ['code' => 'venue-ext-36-50', 'name' => 'Extension Hall (36-50 Persons)', 'price' => 20000.00],
            ['code' => 'venue-main-50-65', 'name' => 'Main Hall (50-65 Persons)', 'price' => 25000.00],
            ['code' => 'venue-main-66-100', 'name' => 'Main Hall (66-100 Persons)', 'price' => 30000.00],
        ],
        'menus' => []
    ],
    [
        'id' => 'dinein',
        'name' => 'Dine-In Menu',
        'category' => 'Restaurant',
        'base_price' => 600.00,
        'price_display' => '600',
        'per_head' => 600.00,
        'per_head_by_pkg' => 0,
        'max_cap' => null,
        'description' => 'Delicious meals served fresh for all dine-in guests.',
        'inclusions' => json_encode(["Al-Fresco Area"]),
        'guidelines' => json_encode([]),
        'packages' => [],
        'menus' => [
            ['code' => 'dinein-menu-a', 'category' => 'Menu A', 'item_name' => 'Menu A', 'price' => 600.00, 'dishes' => ["Plain Rice", "Soup", "Chicken"]]
        ]
    ]
,
    [
        'id' => 'noncoffee',
        'name' => 'Non-Coffee Booth Package',
        'category' => 'Booth',
        'base_price' => 4250.00,
        'price_display' => '₱85 per cup',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => 80,
        'description' => 'A refreshment booth serving fruit teas and milk drinks.',
        'inclusions' => json_encode(["Booth setup", "Assigned staff"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'noncoffee-sparkling-50', 'name' => 'Sparkling Refreshers (50 Cups)', 'price' => 4250.00],
            ['code' => 'noncoffee-sparkling-100', 'name' => 'Sparkling Refreshers (100 Cups)', 'price' => 8500.00],
            ['code' => 'noncoffee-milktea-50', 'name' => 'Creamy Milk Tea (50 Cups)', 'price' => 4250.00],
            ['code' => 'noncoffee-milktea-100', 'name' => 'Creamy Milk Tea (100 Cups)', 'price' => 8500.00],
        ],
        'menus' => []
    ]
,
    [
        'id' => 'coffee',
        'name' => 'Coffee Booth Package',
        'category' => 'Booth',
        'base_price' => 6250.00,
        'price_display' => '₱125 per cup',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => 80,
        'description' => 'Mobile coffee bar serving premium beverages.',
        'inclusions' => json_encode(["Booth setup", "Assigned staff"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'coffee-intimate-50', 'name' => 'Intimate Package (50 Cups)', 'price' => 6250.00],
            ['code' => 'coffee-intimate-100', 'name' => 'Intimate Package (100 Cups)', 'price' => 12500.00],
        ],
        'menus' => []
    ]
,
    [
        'id' => 'pizza',
        'name' => 'Pizza Booth',
        'category' => 'Booth',
        'base_price' => 7500.00,
        'price_display' => '₱7,500',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => 60,
        'description' => 'Freshly baked artisanal pizzas prepared live.',
        'inclusions' => json_encode(["Live station setup", "Assigned staff"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'pizza-intimate-15', 'name' => 'Intimate Package (50 Persons) with 15 Pizza', 'price' => 7500.00],
            ['code' => 'pizza-intimate-20', 'name' => 'Intimate Package (50 Persons) with 20 Pizza', 'price' => 10000.00],
        ],
        'menus' => []
    ],
    [
        'id' => 'intimate',
        'name' => 'Intimate Celebration Package',
        'category' => 'Celebration',
        'base_price' => 4000.00,
        'price_display' => '₱4,000',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => 50,
        'description' => 'Complete intimate celebration packages.',
        'inclusions' => json_encode(["Thematic setup", "Basic lighting"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'intimate-package-std', 'name' => 'Intimate Package', 'price' => 4000.00],
            ['code' => 'intimate-package-ultimate', 'name' => 'Ultimate Package', 'price' => 14000.00],
        ],
        'menus' => []
    ],
    [
        'id' => 'equipment',
        'name' => 'Equipment Rental',
        'category' => 'Equipment',
        'base_price' => 300.00,
        'price_display' => '300',
        'per_head' => null,
        'per_head_by_pkg' => 0,
        'max_cap' => null,
        'description' => 'Rental of high-quality event equipment.',
        'inclusions' => json_encode(["Delivery within service area", "Basic setup"]),
        'guidelines' => json_encode([]),
        'packages' => [
            ['code' => 'equipment-standard', 'name' => 'Standard Rate', 'price' => 300.00],
        ],
        'menus' => []
    ]
];




$addons = [
    ['id' => 'catering-menu-addon', 'service_id' => 'catering-menu', 'name' => 'Catering with Menu', 'description' => 'Add catering', 'price_from' => 'catering-menu', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'venue-addon', 'service_id' => 'venue', 'name' => 'Venue Rentals', 'description' => 'Add venue', 'price_from' => 'venue', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'dinein-addon', 'service_id' => 'dinein', 'name' => 'Dine-In Menu', 'description' => 'Add dine-in', 'price_from' => null, 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'noncoffee-booth-addon', 'service_id' => 'noncoffee', 'name' => 'Non-Coffee Booth Package', 'description' => 'Add non-coffee booth', 'price_from' => 'noncoffee', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'coffee-booth-addon', 'service_id' => 'coffee', 'name' => 'Coffee Booth Package', 'description' => 'Add coffee booth', 'price_from' => 'coffee', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'pizza-booth-addon', 'service_id' => 'pizza', 'name' => 'Pizza Booth', 'description' => 'Add pizza booth', 'price_from' => 'pizza', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'intimate-addon', 'service_id' => 'intimate', 'name' => 'Intimate Celebration Package', 'description' => 'Add intimate package', 'price_from' => 'intimate', 'fixed_price' => null, 'allow_qty' => 0],
    ['id' => 'equipment-addon', 'service_id' => 'equipment', 'name' => 'Equipment Rental', 'description' => 'Add equipment', 'price_from' => null, 'fixed_price' => null, 'allow_qty' => 0],
];

try {
    $pdo->beginTransaction();
    $servicesCount = 0; $packagesCount = 0; $menusCount = 0; $addonsCount = 0;

    foreach ($catalog as $s) {
        $stmt = $pdo->prepare("SELECT base_price, is_active FROM services WHERE id = ?");
        $stmt->execute([$s['id']]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        $basePrice = $existing ? $existing['base_price'] : $s['base_price'];
        $isActive = $existing ? $existing['is_active'] : 1;

        $upsertSvc = $pdo->prepare("INSERT INTO services (id, name, category, base_price, price_display, per_head, per_head_by_pkg, max_cap, description, inclusions, guidelines, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), category=VALUES(category), price_display=VALUES(price_display), per_head=VALUES(per_head), per_head_by_pkg=VALUES(per_head_by_pkg), max_cap=VALUES(max_cap), description=VALUES(description), inclusions=VALUES(inclusions), guidelines=VALUES(guidelines)");
        $upsertSvc->execute([$s['id'], $s['name'], $s['category'], $basePrice, $s['price_display'], $s['per_head'], $s['per_head_by_pkg'], $s['max_cap'], $s['description'], $s['inclusions'], $s['guidelines'], $isActive]);
        $servicesCount++;

        foreach ($s['packages'] as $idx => $pkg) {
            $upsertPkg = $pdo->prepare("INSERT INTO service_packages (service_id, package_code, package_name, price, sort_order) VALUES (?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE package_name=VALUES(package_name), price=VALUES(price), sort_order=VALUES(sort_order)");
            $upsertPkg->execute([$s['id'], $pkg['code'], $pkg['name'], $pkg['price'], $idx + 1]);
            $packagesCount++;
        }

        foreach ($s['menus'] as $m) {
            $upsertMenu = $pdo->prepare("INSERT INTO service_menus (service_id, menu_code, category_name, item_name, price, dishes) VALUES (?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE category_name=VALUES(category_name), item_name=VALUES(item_name), price=VALUES(price), dishes=VALUES(dishes)");
            $upsertMenu->execute([$s['id'], $m['code'], $m['category'], $m['item_name'], $m['price'], json_encode($m['dishes'])]);
            $menusCount++;
        }
    }

    foreach ($addons as $a) {
        $upsertAddon = $pdo->prepare("INSERT INTO service_addons (id, service_id, name, description, price_from, fixed_price, allow_qty) VALUES (?, ?, ?, ?, ?, ?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name), description=VALUES(description), price_from=VALUES(price_from), fixed_price=VALUES(fixed_price), allow_qty=VALUES(allow_qty)");
        $upsertAddon->execute([$a['id'], $a['service_id'], $a['name'], $a['description'], $a['price_from'], $a['fixed_price'], $a['allow_qty']]);
        $addonsCount++;
    }

    $pdo->commit();
    echo json_encode(['status' => 'success', 'services' => $servicesCount, 'packages' => $packagesCount, 'menus' => $menusCount, 'addons' => $addonsCount], JSON_PRETTY_PRINT) . "\n";
} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "Seeding failed: " . $e->getMessage() . "\n";
}
