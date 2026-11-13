<?php
/**
 * Stage 4E — Phase C: Production catalog data migration script.
 * CLI only, target database `pdrsystem` only.
 * Requires explicit `--confirm-stage4e-phase-c` argument to execute.
 */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI execution required.\n");
    exit(1);
}

if (!in_array('--confirm-stage4e-phase-c', $argv ?? [], true)) {
    fwrite(
        STDERR,
        "Refusing execution: pass --confirm-stage4e-phase-c to run the Stage 4E Phase C production catalog data migration.\n"
    );
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

function assertStage4eProdDataDb(PDO $pdo): void
{
    $activeDb = $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($activeDb !== 'pdrsystem') {
        throw new RuntimeException("Production database guard failed: expected 'pdrsystem', got '{$activeDb}'.");
    }
}

function captureNonPiiSnapshot(PDO $pdo): array
{
    assertStage4eProdDataDb($pdo);

    $servicesImmutable = $pdo->query(
        "SELECT id, name, category, base_price, price_display, per_head, per_head_by_pkg, max_cap, description, guidelines, is_active, sort_order
         FROM services ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $servicesCatalog = $pdo->query(
        "SELECT id, inclusions, customer_display FROM services ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $packages = $pdo->query(
        "SELECT service_id, package_code, package_name, price, is_active, sort_order, max_cap
         FROM service_packages ORDER BY service_id, package_code"
    )->fetchAll(PDO::FETCH_ASSOC);

    $menus = $pdo->query(
        "SELECT service_id, menu_code, category_name, item_name, price, dishes, is_active
         FROM service_menus ORDER BY service_id, menu_code"
    )->fetchAll(PDO::FETCH_ASSOC);

    $addons = $pdo->query(
        "SELECT id, service_id, name, description, price_from, fixed_price, allow_qty, is_active
         FROM service_addons ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $guidelines = $pdo->query(
        "SELECT id, heading, items, sort_order, is_active
         FROM catalog_guidelines ORDER BY sort_order, id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $reservationsNonPii = $pdo->query(
        "SELECT id, reservation_id, booking_pin_hash, status, service_id, main_service, event_date, event_time,
                event_type, guests, selected_package, main_courses, additional_services, estimated_total, payment_method, submitted_at
         FROM reservations ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $paymentsRows = $pdo->query("SELECT * FROM payments ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $reviewsRows = $pdo->query("SELECT id, reservation_id, rating, feedback_text, archived, created_at FROM reviews ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    $adminUsersRows = $pdo->query("SELECT id, username, password_hash, role, created_at FROM admin_users ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);

    return [
        'services_immutable' => $servicesImmutable,
        'services_catalog' => $servicesCatalog,
        'packages' => $packages,
        'menus' => $menus,
        'addons' => $addons,
        'guidelines' => $guidelines,
        'reservations_count' => count($reservationsNonPii),
        'reservations_hash' => hash('sha256', json_encode($reservationsNonPii, JSON_UNESCAPED_UNICODE)),
        'payments_count' => count($paymentsRows),
        'payments_hash' => hash('sha256', json_encode($paymentsRows, JSON_UNESCAPED_UNICODE)),
        'reviews_count' => count($reviewsRows),
        'reviews_hash' => hash('sha256', json_encode($reviewsRows, JSON_UNESCAPED_UNICODE)),
        'admin_users_count' => count($adminUsersRows),
        'admin_users_hash' => hash('sha256', json_encode($adminUsersRows, JSON_UNESCAPED_UNICODE)),
    ];
}

$display = [
    'catering-menu' => [
        'rates' => [
            ['2 Main Courses', '₱1,000 per head'],
            ['3 Main Courses', '₱1,100 per head'],
            ['4 Main Courses', '₱1,300 per head'],
        ],
        'mainCourses' => ['Chicken Recipe', 'Beef Recipe', 'Pork Recipe', 'Fish or Seafood Recipe'],
        'notes' => [
            'A minimum of 50 pax applies for this package.',
            'Menu tasting may be arranged upon request, subject to availability.',
        ],
        'inclusions' => [
            'Tables and chairs',
            'Food and beverages',
            'Basic decorations',
            'Serving staff',
            'Venue setup',
            'Cleanup',
        ],
    ],
    'venue' => [
        'rates' => [
            ['Extension Hall (30 to 35 persons)', '₱15,000'],
            ['Extension Hall (36 - 50 Persons)', '₱20,000'],
            ['Main Hall (50 - 65 Persons)', '₱25,000'],
            ['Main Hall (66 - 100 Persons)', '₱30,000'],
        ],
        'inclusions' => [
            'Use of air-conditioned venue for 4 hours',
            'Basic sound system set-up (player, speaker and microphone)',
            'Basic Catering Set-up',
            'Decorative stage and throne',
            'Elegantly skirted buffet table',
            'Tables for cake and gifts',
            'Round table with floor length table cover',
            'Table numbers & centerpiece for each table',
            'Tiffany Chairs with thematic ribbon',
            'Complete silverware, glassware and flatware',
            'Professionally trained and uniformed staff',
            'Provision for parking space',
        ],
        'notes' => [],
    ],
    'dinein' => [
        'rates' => [
            ['Per Head', '₱600'],
            ['10 Persons', '₱6,000'],
            ['20 Persons', '₱12,000'],
            ['30 Persons', '₱18,000'],
        ],
        'inclusions' => [
            'Dine-in at Al-Fresco Restaurant Area for 1-1.5 hours',
            'Complete silverware, glassware,and flatware',
            'Professionally trained and uniformed staff',
            'Provision for parking space',
        ],
        'notes' => [],
    ],
    'noncoffee' => [
        'rates' => [
            ['Sparkling Refreshers', '₱85 per cup'],
            ['Creamy Milk Tea', '₱85 per cup'],
        ],
        'inclusions' => ['Booth setup', 'Assigned staff', 'Assorted drinks menu'],
        'inclusionsTable' => [
            'headers' => ['Sparkling Refreshers', 'Creamy Milk Tea'],
            'rows' => [
                ['16 oz Iced Drinks', '16 oz Iced Drinks'],
                [
                    '5 Flavors: Strawberry, Green Apple, Peach, Passion Fruit, Blue Raspberry',
                    '5 Flavors: Matcha, Cookies & Cream, Dark Chocolate, Salted Caramel, Okinawa',
                ],
            ],
        ],
        'notes' => [
            'Transportation Fee applies outside Indang, Cavite',
            '50% Down Payment to book your event',
            '50% Full Payment must be made 1 day before the event',
            'Additional charge of 500.00 per hour for every succeeding hour',
            'Ensure that there will be a nearby power outlet',
            'Corkage or electricity fees shall be shouldered by the client',
        ],
    ],
    'coffee' => [
        'rates' => [
            ['Intimate', '₱125 per cup'],
            ['Classic', '₱145 per cup'],
            ['Ultimate', '₱165 per cup'],
        ],
        'inclusions' => [
            'Premium Quality Coffee Beans',
            'Premium Sauces and Syrups',
            'Quality Espresso Machine and Grinder',
            'Complete Coffee Booth Set-up',
            '3-4 Hours of Service',
            '2-3 Experienced and Uniformed Barista',
            'Claiming Stub for the guests (optional)',
            'Free Transportation for 100 cups and above within Indang, Trece, Mendez or Tagaytay',
        ],
        'inclusionsTable' => [
            'headers' => ['Intimate', 'Classic', 'Ultimate'],
            'rows' => [
                ['12 oz Hot Drinks', '12 oz Hot Drinks', '12 oz Hot Drinks'],
                ['12 oz Iced Drinks', '12 oz Iced Drinks', '12 oz Iced Drinks'],
                [
                    '4 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato',
                    '5 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte',
                    '7 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte, Spanish Latte, Mocha, 1 Non-Coffee Flavor: Chocolate',
                ],
            ],
        ],
        'notes' => [
            'Transportation Fee applies outside Indang, Cavite',
            '50% Down Payment to book your event',
            '50% Full Payment must be made 1 day before the event',
            'Additional charge of 500.00 per hour for every succeeding hour',
            'Ensure that there will be a nearby power outlet',
            'Corkage or electricity fees shall be shouldered by the client',
        ],
    ],
    'pizza' => [
        'rates' => [
            ['15 Pizza', '₱7,500'],
            ['20 Pizza', '₱10,000'],
            ['25 Pizza', '₱12,000'],
            ['30 Pizza', '₱14,500'],
        ],
        'inclusions' => [
            'Freshly Made Pizza Dough',
            'Premium Quality Mozarella Cheese, Sauces, & Ingredients',
            'Pugon-Quality Pizza Oven for Authentic Wood-Fired Flavor',
            'Complete Pizza Booth Set-up',
            '2-3 hours of Service',
            '2-3 Experienced and Uniformed Pizza Masters',
            'Claiming Stub for Guests (optional)',
            'Free Transportation within Indang, Cavite',
            'Minimal Transportation for Trece, Amadeo, Mendez, Tagaytay, Silang, and Alfonso',
        ],
        'notes' => [
            '50% Down Payment to book your event',
            '50% Full Payment must be made 1 day before the event',
            'Additional charge of ₱500 per hour for every succeeding hour',
            'Ensure that the venue allows LPG tank for the pugon pizza oven',
            'In such case, corkage or electricity fees shall be shouldered by the client',
        ],
    ],
    'intimate' => [
        'rates' => [
            ['Intimate', '₱4,000'],
            ['Ultimate', '₱14,000'],
            ['Classic', '₱8,000'],
        ],
        'inclusionsTable' => [
            'headers' => ['Intimate', 'Ultimate', 'Classic'],
            'rows' => [
                ['Coffee Shop', 'Extension Hall with 25-35 Persons', 'Kamayan 1'],
                ['6-8 Persons', 'Air-Conditioned', '15-20 Persons'],
                ['Air-Conditioned', 'Basic Sound System', 'Al-Fresco Area'],
                [
                    '2 Hours Exclusive Events Place',
                    '2 Hours Exclusive Events Place',
                    '2 Hours Exclusive Events Place',
                ],
                [
                    'Consumable Drinks and Food',
                    'Consumable Drinks and Food',
                    'Consumable Drinks and Food',
                ],
            ],
        ],
        'notes' => [
            'For intimate celebrations such as Bridal Shower, Gender Reveal, Baby SHower, and small birthday parties only.',
            'Provision of one (1) rectangular table with table cover is included for buffet set-up.',
            'Outside food and drinks are highly discouraged. Food corkage will be implmented.',
            'Booking will be confrimed upon receipt of a 50% downpayment.',
            'Additional charges apply for themed set-ups. Guests may bring their own decor, but only limited decorations are allowed.',
            'Use of nails, glue, double sided tape, confetti, or any materials that may damage the property is strictly prohibited.',
            'Standard party duration is 2 hours. Extension is subject to availability and additional charges.',
            'Use of venue equipment (e.g. sound system) must be requested in advance.',
        ],
    ],
    'equipment' => [
        'rates' => [['Per Item', '₱300']],
        'inclusions' => ['Delivery within service area', 'Basic setup'],
        'notes' => [],
    ],
];

$newPackages = [
    ['coffee', 'coffee-classic-50', 'Classic Package (50 Cups)', 7250.00, 3, null],
    ['coffee', 'coffee-classic-100', 'Classic Package (100 Cups)', 14500.00, 4, null],
    ['coffee', 'coffee-ultimate-50', 'Ultimate Package (50 Cups)', 8250.00, 5, null],
    ['coffee', 'coffee-ultimate-100', 'Ultimate Package (100 Cups)', 16500.00, 6, null],
    ['pizza', 'pizza-classic-25', 'Classic Package (80 Persons) with 25 Pizza', 12000.00, 3, 80],
    ['pizza', 'pizza-classic-30', 'Classic Package (80 Persons) with 30 Pizza', 14500.00, 4, 80],
    ['intimate', 'intimate-package-classic', 'Classic Package', 8000.00, 3, null],
];

$baselinePackages = [
    'catering-menu' => [
        'cat-2-courses' => ['2 Main Courses', 1000.00, 1],
        'cat-3-courses' => ['3 Main Courses', 1100.00, 2],
        'cat-4-courses' => ['4 Main Courses', 1300.00, 3],
    ],
    'venue' => [
        'venue-ext-30-35' => ['Extension Hall (30-35 Persons)', 15000.00, 1],
        'venue-ext-36-50' => ['Extension Hall (36-50 Persons)', 20000.00, 2],
        'venue-main-50-65' => ['Main Hall (50-65 Persons)', 25000.00, 3],
        'venue-main-66-100' => ['Main Hall (66-100 Persons)', 30000.00, 4],
    ],
    'coffee' => [
        'coffee-intimate-50' => ['Intimate Package (50 Cups)', 6250.00, 1],
        'coffee-intimate-100' => ['Intimate Package (100 Cups)', 12500.00, 2],
    ],
    'noncoffee' => [
        'noncoffee-sparkling-50' => ['Sparkling Refreshers (50 Cups)', 4250.00, 1],
        'noncoffee-sparkling-100' => ['Sparkling Refreshers (100 Cups)', 8500.00, 2],
        'noncoffee-milktea-50' => ['Creamy Milk Tea (50 Cups)', 4250.00, 3],
        'noncoffee-milktea-100' => ['Creamy Milk Tea (100 Cups)', 8500.00, 4],
    ],
    'pizza' => [
        'pizza-intimate-15' => ['Intimate Package (50 Persons) with 15 Pizza', 7500.00, 1],
        'pizza-intimate-20' => ['Intimate Package (50 Persons) with 20 Pizza', 10000.00, 2],
    ],
    'intimate' => [
        'intimate-package-std' => ['Intimate Package', 4000.00, 1],
        'intimate-package-ultimate' => ['Ultimate Package', 14000.00, 2],
    ],
    'equipment' => [
        'equipment-standard' => ['Standard Rate', 300.00, 1],
    ],
];

$inclusionValues = [
    'catering-menu' => ['Tables and chairs', 'Food and beverages', 'Basic decorations', 'Serving staff', 'Venue setup', 'Cleanup'],
    'venue' => [
        'Use of air-conditioned venue for 4 hours',
        'Basic sound system set-up (player, speaker and microphone)',
        'Basic Catering Set-up',
        'Decorative stage and throne',
        'Elegantly skirted buffet table',
        'Tables for cake and gifts',
        'Round table with floor length table cover',
        'Table numbers & centerpiece for each table',
        'Tiffany Chairs with thematic ribbon',
        'Complete silverware, glassware and flatware',
        'Professionally trained and uniformed staff',
        'Provision for parking space',
    ],
    'dinein' => [
        'Dine-in at Al-Fresco Restaurant Area for 1-1.5 hours',
        'Complete silverware, glassware,and flatware',
        'Professionally trained and uniformed staff',
        'Provision for parking space',
    ],
    'noncoffee' => ['Booth setup', 'Assigned staff', 'Assorted drinks menu'],
    'coffee' => [
        'Premium Quality Coffee Beans',
        'Premium Sauces and Syrups',
        'Quality Espresso Machine and Grinder',
        'Complete Coffee Booth Set-up',
        '3-4 Hours of Service',
        '2-3 Experienced and Uniformed Barista',
        'Claiming Stub for the guests (optional)',
        'Free Transportation for 100 cups and above within Indang, Trece, Mendez or Tagaytay',
    ],
    'pizza' => [
        'Freshly Made Pizza Dough',
        'Premium Quality Mozarella Cheese, Sauces, & Ingredients',
        'Pugon-Quality Pizza Oven for Authentic Wood-Fired Flavor',
        'Complete Pizza Booth Set-up',
        '2-3 hours of Service',
        '2-3 Experienced and Uniformed Pizza Masters',
        'Claiming Stub for Guests (optional)',
        'Free Transportation within Indang, Cavite',
        'Minimal Transportation for Trece, Amadeo, Mendez, Tagaytay, Silang, and Alfonso',
    ],
    'intimate' => ['Thematic setup', 'Basic lighting'],
    'equipment' => ['Delivery within service area', 'Basic setup'],
];

$legacyInclusions = [
    'catering-menu' => ['Tables and chairs', 'Food and beverages', 'Basic decorations'],
    'venue' => ['Air-conditioned venue'],
    'dinein' => ['Al-Fresco Area'],
    'noncoffee' => ['Booth setup', 'Assigned staff'],
    'coffee' => ['Booth setup', 'Assigned staff'],
    'pizza' => ['Live station setup', 'Assigned staff'],
    'intimate' => ['Thematic setup', 'Basic lighting'],
    'equipment' => ['Delivery within service area', 'Basic setup'],
];

$menuA = [
    'Plain Rice',
    'Crab & Corn Soup',
    'Creamy Mixed Veggies w/ Quail Eggs',
    'Garlic Chicken w/ Gravy Sauce',
    'Pork w/ Mushroom Sauce',
    'Lemon Iced Tea',
    'Peach Mango Graham',
];
$menuB = [
    'Plain Rice',
    'Creamy Mushroom Soup',
    'Special Chopseuy w/ Quail Eggs',
    'Chicken Cordon Bleu w/ White Sauce',
    'Pork w/ Oyster Sauce & Brocolli',
    'Blue Lemonade',
    'Frozen Cookies & Cream',
];
$includedDishes = [
    'Chips for Pica-Pica',
    'Steamed Pandan Rice',
    'Soup',
    'Vegetable Recipe',
    'Beverages',
    'Dessert',
    'Pancit/Pasta Recipe',
];

$guidelines = [
    [
        'booking-reservation',
        'Booking & Reservation',
        [
            'In order to reserve your chosen date, time and venue, we are requiring a non-refundable deposit of ₱2,000.00 which will be deducted from your final bill. No booking or reservation will be considered confirmed until a deposit is made.',
            'Client should finalize the details of their event at least 2 weeks before the event and make a partial payment of 50% of their total bill.',
            'A final confirmation of the total number of guests shall be made no later than 5 working days prior to the date of the event. This confirmed number will be the minimum number which will be charged. Additional guests shall be served and charged accordingly on a per plate / per head basis.',
            'The remaining 50% shall be paid in full 1 day before the day of the event.',
            'Additional and other incidental charges shall be settled on the day of the event, right after the said event.',
            'Only the reserved venue shall be used for your event. Additional charges shall be imposed for the use of other venues.',
        ],
    ],
    [
        'cancellation-changes',
        'Cancellation, Changes to the Reservation',
        [
            'All changes to your reservation must be made no later than 5 working days prior to the date of the event. All changes will be considered; however, we hold the right not to accept the change if prior preparation is already done.',
        ],
    ],
    [
        'food-catering-setup',
        'Food, Catering Set-Up and Others',
        [
            'All allocations are based on experience in the food industry. We usually cater for more than necessary, but we cannot account for over average eaters.',
            'Client may bring their own food to be served during their event provided prior arrangements are done. Though, we will not take responsibility for any other food and drinks not provided by Pio de Roda Café on the day of the event.',
            'Pio de Roda Café will assist with any additional items during the event. However, we are not responsible for any items provided by the client for the event such as but not limited to flowers, cakes, souvenirs, etc.',
            'Pio De Roda Café will serve you with our basic catering set-up which includes celebrant\'s stage and throne, buffet table, cake and gift tables, tables and chairs, complete silverware, flatware and dinnerware, basic sound system, and trained waiters. However, should you require more intricate details it will be under our event styling arrangements which will be on a separate transaction.',
            'Outside providers or suppliers such as lights and sound system, projectors, LED screens, food carts, etc. shall have a corkage for the energy consumption.',
            'Pio De Roda Café is practicing a per head and plate basis, however, children below 7 years old are free of charge. Please refrain from using another plate. Saucers will be provided for your desserts and other food use.',
            'We are following the IATF regulation to limit the guests for every event. Senior Citizens and children are encouraged to take precautionary measures for their own safety. Please limit their exposure as well. Children shall be limited to a maximum of 5 kids only.',
            'Remind your guests to bring and wear their face masks and face shields at all times. And also bring their vaccination cards and present at the contact tracing booth.',
        ],
    ],
];

try {
    assertStage4eProdDataDb($pdo);

    // 1. Verify required Phase B schema objects exist before starting transaction
    $requiredTables = [
        'admin_users',
        'payments',
        'reservations',
        'reviews',
        'services',
        'service_packages',
        'service_menus',
        'service_addons',
        'catalog_guidelines',
    ];
    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
    foreach ($requiredTables as $t) {
        $tableCheck->execute([$t]);
        if ((int)$tableCheck->fetchColumn() !== 1) {
            throw new RuntimeException("Required Phase B table '{$t}' is missing; stopping.");
        }
    }

    $colCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = ? AND column_name = ?");
    $colCheck->execute(['service_packages', 'max_cap']);
    if ((int)$colCheck->fetchColumn() !== 1) {
        throw new RuntimeException("Required Phase B column 'service_packages.max_cap' is missing; stopping.");
    }
    $colCheck->execute(['services', 'customer_display']);
    if ((int)$colCheck->fetchColumn() !== 1) {
        throw new RuntimeException("Required Phase B column 'services.customer_display' is missing; stopping.");
    }

    // 2. Begin transaction and capture pre-migration snapshot
    $pdo->beginTransaction();
    assertStage4eProdDataDb($pdo);

    $preSnapshot = captureNonPiiSnapshot($pdo);

    // Audited baseline was 7 reservations; production has since grown through
    // real bookings (14 at time of running). Require at least the audited
    // baseline so deletion below it still aborts. Non-modification of
    // reservations is enforced by the pre/post hash comparison below.
    if ($preSnapshot['reservations_count'] < 7) {
        throw new RuntimeException("Expected at least the 7 audited production reservations, found {$preSnapshot['reservations_count']}; stopping.");
    }
    if ($preSnapshot['payments_count'] !== 0 || $preSnapshot['reviews_count'] !== 0 || $preSnapshot['admin_users_count'] !== 1) {
        throw new RuntimeException('Unexpected baseline count in payments, reviews, or admin_users; stopping.');
    }

    // 3. Validate service baseline (IDs, base_price, max_cap, is_active)
    $expectedServiceSpec = [
        'catering-menu' => [1000.00, 150],
        'coffee' => [6250.00, 80],
        'dinein' => [600.00, null],
        'equipment' => [300.00, null],
        'intimate' => [4000.00, 50],
        'noncoffee' => [4250.00, 80],
        'pizza' => [7500.00, 60],
        'venue' => [15000.00, 120],
    ];
    if (count($preSnapshot['services_immutable']) !== 8) {
        throw new RuntimeException('Expected 8 services in production baseline; stopping.');
    }
    foreach ($preSnapshot['services_immutable'] as $sRow) {
        $sid = $sRow['id'];
        if (!isset($expectedServiceSpec[$sid])) {
            throw new RuntimeException("Unexpected service ID '{$sid}' in production; stopping.");
        }
        [$expPrice, $expCap] = $expectedServiceSpec[$sid];
        $actualCap = $sRow['max_cap'] === null ? null : (int)$sRow['max_cap'];
        if ((float)$sRow['base_price'] !== $expPrice || (int)$sRow['is_active'] !== 1 || $actualCap !== $expCap) {
            throw new RuntimeException("Service '{$sid}' base_price, is_active, or max_cap differs from audited baseline; refusing overwrite.");
        }
    }

    // 4. Validate add-on baseline
    $expectedAddonBaseline = [
        'catering-menu-addon' => ['catering-menu', 'catering-menu', 'catering-menu', 0, 0],
        'coffee-booth-addon' => ['coffee', 'coffee', 'coffee', 0, 0],
        'dinein-addon' => ['dinein', null, 'dinein', 0, 0],
        'equipment-addon' => ['equipment', null, 'equipment', 0, 1],
        'intimate-addon' => ['intimate', 'intimate', 'intimate', 0, 0],
        'noncoffee-booth-addon' => ['noncoffee', 'noncoffee', 'noncoffee', 0, 0],
        'pizza-booth-addon' => ['pizza', 'pizza', 'pizza', 0, 0],
        'venue-addon' => ['venue', 'venue', 'venue', 0, 0],
    ];
    if (count($preSnapshot['addons']) !== 8) {
        throw new RuntimeException('Expected 8 add-ons in production baseline; stopping.');
    }
    foreach ($preSnapshot['addons'] as $aRow) {
        $aid = $aRow['id'];
        if (!isset($expectedAddonBaseline[$aid])) {
            throw new RuntimeException("Unexpected add-on ID '{$aid}'; stopping.");
        }
        [$expSvc, $origFrom, $corrFrom, $origQty, $corrQty] = $expectedAddonBaseline[$aid];
        if ($aRow['service_id'] !== $expSvc || $aRow['fixed_price'] !== null || (int)$aRow['is_active'] !== 1) {
            throw new RuntimeException("Add-on '{$aid}' parent, fixed_price, or active status differs from baseline; refusing overwrite.");
        }
        $isOrig = ($aRow['price_from'] === $origFrom && (int)$aRow['allow_qty'] === $origQty);
        $isCorr = ($aRow['price_from'] === $corrFrom && (int)$aRow['allow_qty'] === $corrQty);
        if (!$isOrig && !$isCorr) {
            throw new RuntimeException("Add-on '{$aid}' price_from/allow_qty differs from recognized original or corrected state.");
        }
    }

    // 5. Validate package baseline & insert 7 missing packages
    $pkgCount = count($preSnapshot['packages']);
    if ($pkgCount !== 18 && $pkgCount !== 25) {
        throw new RuntimeException("Unexpected package baseline count ({$pkgCount}); stopping.");
    }
    foreach ($preSnapshot['packages'] as $pRow) {
        $sid = $pRow['service_id'];
        $code = $pRow['package_code'];
        if (isset($baselinePackages[$sid][$code])) {
            [$expName, $expPrice, $expOrder] = $baselinePackages[$sid][$code];
            if (
                $pRow['package_name'] !== $expName ||
                (float)$pRow['price'] !== $expPrice ||
                (int)$pRow['is_active'] !== 1 ||
                (int)$pRow['sort_order'] !== $expOrder ||
                $pRow['max_cap'] !== null
            ) {
                throw new RuntimeException("Existing baseline package '{$sid}|{$code}' differs from audited values; refusing overwrite.");
            }
        } else {
            $matchedNew = false;
            foreach ($newPackages as [$nSid, $nCode, $nName, $nPrice, $nOrder, $nCap]) {
                $actualCap = $pRow['max_cap'] === null ? null : (int)$pRow['max_cap'];
                if (
                    $sid === $nSid &&
                    $code === $nCode &&
                    $pRow['package_name'] === $nName &&
                    (float)$pRow['price'] === (float)$nPrice &&
                    (int)$pRow['is_active'] === 1 &&
                    (int)$pRow['sort_order'] === $nOrder &&
                    $actualCap === $nCap
                ) {
                    $matchedNew = true;
                    break;
                }
            }
            if (!$matchedNew) {
                throw new RuntimeException("Unexpected package row '{$sid}|{$code}' in production; refusing overwrite.");
            }
        }
    }

    $findPkgStmt = $pdo->prepare(
        "SELECT package_name, price, is_active, sort_order, max_cap
         FROM service_packages WHERE service_id = ? AND package_code = ?"
    );
    $insertPkgStmt = $pdo->prepare(
        "INSERT INTO service_packages (service_id, package_code, package_name, price, is_active, sort_order, max_cap)
         VALUES (?, ?, ?, ?, 1, ?, ?)"
    );
    $insertedPackagesCount = 0;
    foreach ($newPackages as [$serviceId, $code, $name, $price, $order, $cap]) {
        assertStage4eProdDataDb($pdo);
        $findPkgStmt->execute([$serviceId, $code]);
        $existingPkg = $findPkgStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingPkg) {
            assertStage4eProdDataDb($pdo);
            $insertPkgStmt->execute([$serviceId, $code, $name, $price, $order, $cap]);
            $insertedPackagesCount++;
        } else {
            $actualCap = $existingPkg['max_cap'] === null ? null : (int)$existingPkg['max_cap'];
            if (
                $existingPkg['package_name'] !== $name ||
                (float)$existingPkg['price'] !== (float)$price ||
                (int)$existingPkg['is_active'] !== 1 ||
                (int)$existingPkg['sort_order'] !== $order ||
                $actualCap !== $cap
            ) {
                throw new RuntimeException("Existing package '{$serviceId}|{$code}' conflicts with expected Stage 4E target state.");
            }
        }
    }

    // 6. Validate and update service inclusions
    $readInclusionsStmt = $pdo->prepare("SELECT inclusions FROM services WHERE id = ?");
    $updateInclusionsStmt = $pdo->prepare("UPDATE services SET inclusions = ? WHERE id = ? AND inclusions = ?");
    $updatedInclusionsCount = 0;
    foreach ($inclusionValues as $serviceId => $confirmedInclusions) {
        assertStage4eProdDataDb($pdo);
        $readInclusionsStmt->execute([$serviceId]);
        $currentJson = $readInclusionsStmt->fetchColumn();
        $currentDecoded = json_decode((string)$currentJson, true);
        if ($currentDecoded === $confirmedInclusions) {
            continue;
        }
        if ($currentDecoded !== $legacyInclusions[$serviceId]) {
            throw new RuntimeException("Service '{$serviceId}' inclusions differ from recognized legacy/confirmed state; refusing overwrite.");
        }
        $targetJson = json_encode($confirmedInclusions, JSON_UNESCAPED_UNICODE);
        assertStage4eProdDataDb($pdo);
        $updateInclusionsStmt->execute([$targetJson, $serviceId, $currentJson]);
        if ($updateInclusionsStmt->rowCount() !== 1) {
            throw new RuntimeException("Service inclusion update for '{$serviceId}' did not affect exactly 1 row.");
        }
        $updatedInclusionsCount++;
    }

    // 7. Validate and update services.customer_display
    $readDisplayStmt = $pdo->prepare("SELECT customer_display FROM services WHERE id = ?");
    $updateDisplayStmt = $pdo->prepare("UPDATE services SET customer_display = ? WHERE id = ? AND customer_display IS NULL");
    $updatedDisplayCount = 0;
    foreach ($display as $serviceId => $customerDisplayData) {
        assertStage4eProdDataDb($pdo);
        $readDisplayStmt->execute([$serviceId]);
        $existingDisplay = $readDisplayStmt->fetchColumn();
        $targetJson = json_encode($customerDisplayData, JSON_UNESCAPED_UNICODE);
        if ($existingDisplay === null) {
            assertStage4eProdDataDb($pdo);
            $updateDisplayStmt->execute([$targetJson, $serviceId]);
            if ($updateDisplayStmt->rowCount() !== 1) {
                throw new RuntimeException("customer_display update for '{$serviceId}' did not affect exactly 1 row.");
            }
            $updatedDisplayCount++;
        } elseif ($existingDisplay !== $targetJson) {
            throw new RuntimeException("Existing customer_display for '{$serviceId}' differs from approved target; refusing overwrite.");
        }
    }

    // 8. Validate and migrate service_menus (Dine-In Menu A update + Menu B and Catering Included Dishes inserts)
    $menuCount = count($preSnapshot['menus']);
    if ($menuCount !== 1 && $menuCount !== 3) {
        throw new RuntimeException("Unexpected service_menus baseline count ({$menuCount}); stopping.");
    }
    $legacyMenuAJson = json_encode(['Plain Rice', 'Soup', 'Chicken'], JSON_UNESCAPED_UNICODE);
    $confirmedMenuAJson = json_encode($menuA, JSON_UNESCAPED_UNICODE);
    $findMenuStmt = $pdo->prepare(
        "SELECT category_name, item_name, price, dishes, is_active
         FROM service_menus WHERE service_id = ? AND menu_code = ?"
    );
    $insertMenuStmt = $pdo->prepare(
        "INSERT INTO service_menus (service_id, menu_code, category_name, item_name, price, dishes, is_active)
         VALUES (?, ?, ?, ?, NULL, ?, 1)"
    );
    $updateMenuAStmt = $pdo->prepare(
        "UPDATE service_menus SET dishes = ?, price = NULL
         WHERE service_id = 'dinein' AND menu_code = 'dinein-menu-a' AND dishes = ? AND price = 600.00"
    );

    $menuTargets = [
        ['dinein', 'dinein-menu-a', 'Menu A', 'Menu A', $menuA],
        ['dinein', 'dinein-menu-b', 'Menu B', 'Menu B', $menuB],
        ['catering-menu', 'catering-included-dishes', 'Included Dishes', 'Included Dishes', $includedDishes],
    ];
    $insertedMenusCount = 0;
    $updatedMenusCount = 0;
    foreach ($menuTargets as [$sid, $code, $categoryName, $itemName, $dishesList]) {
        assertStage4eProdDataDb($pdo);
        $dishesJson = json_encode($dishesList, JSON_UNESCAPED_UNICODE);
        $findMenuStmt->execute([$sid, $code]);
        $existingMenu = $findMenuStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingMenu) {
            if ($code === 'dinein-menu-a') {
                throw new RuntimeException("Expected baseline 'dinein-menu-a' row is missing; stopping.");
            }
            assertStage4eProdDataDb($pdo);
            $insertMenuStmt->execute([$sid, $code, $categoryName, $itemName, $dishesJson]);
            $insertedMenusCount++;
        } elseif ($code === 'dinein-menu-a' && $existingMenu['dishes'] === $legacyMenuAJson && (float)$existingMenu['price'] === 600.00) {
            assertStage4eProdDataDb($pdo);
            $updateMenuAStmt->execute([$confirmedMenuAJson, $legacyMenuAJson]);
            if ($updateMenuAStmt->rowCount() !== 1) {
                throw new RuntimeException("Update for 'dinein-menu-a' did not affect exactly 1 row.");
            }
            $updatedMenusCount++;
        } elseif (
            $existingMenu['category_name'] !== $categoryName ||
            $existingMenu['item_name'] !== $itemName ||
            $existingMenu['dishes'] !== $dishesJson ||
            $existingMenu['price'] !== null ||
            (int)$existingMenu['is_active'] !== 1
        ) {
            throw new RuntimeException("Existing menu '{$sid}|{$code}' differs from approved state; refusing overwrite.");
        }
    }

    // 9. Validate and insert catalog_guidelines
    $findGuidelineStmt = $pdo->prepare(
        "SELECT heading, items, sort_order, is_active FROM catalog_guidelines WHERE id = ?"
    );
    $insertGuidelineStmt = $pdo->prepare(
        "INSERT INTO catalog_guidelines (id, heading, items, sort_order, is_active)
         VALUES (?, ?, ?, ?, 1)"
    );
    $insertedGuidelinesCount = 0;
    foreach ($guidelines as $idx => [$gid, $heading, $items]) {
        assertStage4eProdDataDb($pdo);
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);
        $sortOrder = $idx + 1;
        $findGuidelineStmt->execute([$gid]);
        $existingGuideline = $findGuidelineStmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingGuideline) {
            assertStage4eProdDataDb($pdo);
            $insertGuidelineStmt->execute([$gid, $heading, $itemsJson, $sortOrder]);
            $insertedGuidelinesCount++;
        } elseif (
            $existingGuideline['heading'] !== $heading ||
            $existingGuideline['items'] !== $itemsJson ||
            (int)$existingGuideline['sort_order'] !== $sortOrder ||
            (int)$existingGuideline['is_active'] !== 1
        ) {
            throw new RuntimeException("Existing guideline '{$gid}' differs from approved data; refusing overwrite.");
        }
    }

    // 10. Validate and update service_addons (dinein-addon and equipment-addon)
    $updatedAddonsCount = 0;
    $dineinAddonRow = $pdo->query(
        "SELECT price_from, allow_qty, fixed_price, is_active
         FROM service_addons WHERE id = 'dinein-addon' AND service_id = 'dinein'"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$dineinAddonRow || $dineinAddonRow['fixed_price'] !== null || (int)$dineinAddonRow['is_active'] !== 1) {
        throw new RuntimeException("Baseline 'dinein-addon' missing or invalid.");
    }
    if ($dineinAddonRow['price_from'] === null && (int)$dineinAddonRow['allow_qty'] === 0) {
        assertStage4eProdDataDb($pdo);
        $updDineinAddon = $pdo->prepare(
            "UPDATE service_addons SET price_from = ?
             WHERE id = 'dinein-addon' AND service_id = 'dinein' AND price_from IS NULL AND allow_qty = 0 AND fixed_price IS NULL"
        );
        $updDineinAddon->execute(['dinein']);
        if ($updDineinAddon->rowCount() !== 1) {
            throw new RuntimeException("Update for 'dinein-addon' did not affect exactly 1 row.");
        }
        $updatedAddonsCount++;
    } elseif ($dineinAddonRow['price_from'] !== 'dinein' || (int)$dineinAddonRow['allow_qty'] !== 0) {
        throw new RuntimeException("'dinein-addon' differs from recognized original/corrected state.");
    }

    $equipAddonRow = $pdo->query(
        "SELECT price_from, allow_qty, fixed_price, is_active
         FROM service_addons WHERE id = 'equipment-addon' AND service_id = 'equipment'"
    )->fetch(PDO::FETCH_ASSOC);
    if (!$equipAddonRow || $equipAddonRow['fixed_price'] !== null || (int)$equipAddonRow['is_active'] !== 1) {
        throw new RuntimeException("Baseline 'equipment-addon' missing or invalid.");
    }
    if ($equipAddonRow['price_from'] === null && (int)$equipAddonRow['allow_qty'] === 0) {
        assertStage4eProdDataDb($pdo);
        $updEquipAddon = $pdo->prepare(
            "UPDATE service_addons SET price_from = ?, allow_qty = 1
             WHERE id = 'equipment-addon' AND service_id = 'equipment' AND price_from IS NULL AND allow_qty = 0 AND fixed_price IS NULL"
        );
        $updEquipAddon->execute(['equipment']);
        if ($updEquipAddon->rowCount() !== 1) {
            throw new RuntimeException("Update for 'equipment-addon' did not affect exactly 1 row.");
        }
        $updatedAddonsCount++;
    } elseif ($equipAddonRow['price_from'] !== 'equipment' || (int)$equipAddonRow['allow_qty'] !== 1) {
        throw new RuntimeException("'equipment-addon' differs from recognized original/corrected state.");
    }

    // 11. Final Pre-Commit Verification
    assertStage4eProdDataDb($pdo);
    $postSnapshot = captureNonPiiSnapshot($pdo);

    $finalCounts = [
        'services' => (int)$pdo->query("SELECT COUNT(*) FROM services")->fetchColumn(),
        'service_packages' => (int)$pdo->query("SELECT COUNT(*) FROM service_packages")->fetchColumn(),
        'service_menus' => (int)$pdo->query("SELECT COUNT(*) FROM service_menus")->fetchColumn(),
        'service_addons' => (int)$pdo->query("SELECT COUNT(*) FROM service_addons")->fetchColumn(),
        'catalog_guidelines' => (int)$pdo->query("SELECT COUNT(*) FROM catalog_guidelines")->fetchColumn(),
        'reservations' => (int)$pdo->query("SELECT COUNT(*) FROM reservations")->fetchColumn(),
        'payments' => (int)$pdo->query("SELECT COUNT(*) FROM payments")->fetchColumn(),
        'reviews' => (int)$pdo->query("SELECT COUNT(*) FROM reviews")->fetchColumn(),
    ];
    $expectedFinalCounts = [
        'services' => 8,
        'service_packages' => 25,
        'service_menus' => 3,
        'service_addons' => 8,
        'catalog_guidelines' => 3,
        'reservations' => $preSnapshot['reservations_count'],
        'payments' => 0,
        'reviews' => 0,
    ];
    if ($finalCounts !== $expectedFinalCounts) {
        throw new RuntimeException('Post-migration table counts do not match expected final counts.');
    }

    // Verify immutable service columns and non-catalog tables were not modified
    if ($postSnapshot['services_immutable'] !== $preSnapshot['services_immutable']) {
        throw new RuntimeException('Unrelated service columns changed during migration; rolling back.');
    }
    if (
        $postSnapshot['reservations_count'] !== $preSnapshot['reservations_count'] ||
        $postSnapshot['reservations_hash'] !== $preSnapshot['reservations_hash'] ||
        $postSnapshot['payments_hash'] !== $preSnapshot['payments_hash'] ||
        $postSnapshot['reviews_hash'] !== $preSnapshot['reviews_hash'] ||
        $postSnapshot['admin_users_hash'] !== $preSnapshot['admin_users_hash']
    ) {
        throw new RuntimeException('Reservations, payments, reviews, or admin_users changed during migration; rolling back.');
    }

    // Verify capacity rules and NULL menu prices
    $capVerify = $pdo->query(
        "SELECT
            (SELECT max_cap FROM services WHERE id = 'intimate') AS intimate_cap,
            (SELECT max_cap FROM services WHERE id = 'pizza') AS pizza_cap,
            (SELECT COUNT(*) FROM service_packages WHERE package_code IN ('pizza-classic-25', 'pizza-classic-30') AND max_cap = 80) AS pizza_classic_caps,
            (SELECT COUNT(*) FROM service_packages WHERE package_code NOT IN ('pizza-classic-25', 'pizza-classic-30') AND max_cap IS NOT NULL) AS other_pkg_caps_non_null,
            (SELECT COUNT(*) FROM service_menus WHERE price IS NOT NULL) AS non_null_menu_prices,
            (SELECT COUNT(*) FROM service_addons WHERE fixed_price IS NOT NULL) AS non_null_addon_fixed_prices"
    )->fetch(PDO::FETCH_ASSOC);

    if (
        (int)$capVerify['intimate_cap'] !== 50 ||
        (int)$capVerify['pizza_cap'] !== 60 ||
        (int)$capVerify['pizza_classic_caps'] !== 2 ||
        (int)$capVerify['other_pkg_caps_non_null'] !== 0 ||
        (int)$capVerify['non_null_menu_prices'] !== 0 ||
        (int)$capVerify['non_null_addon_fixed_prices'] !== 0
    ) {
        throw new RuntimeException('Capacity or NULL-pricing verification failed; rolling back.');
    }

    // Verify JSON validity across all JSON columns
    foreach ($postSnapshot['services_catalog'] as $sCat) {
        if (
            !is_array(json_decode((string)$sCat['inclusions'], true)) ||
            !is_array(json_decode((string)$sCat['customer_display'], true))
        ) {
            throw new RuntimeException("Invalid JSON in services row '{$sCat['id']}'.");
        }
    }
    foreach ($postSnapshot['menus'] as $mRow) {
        if (!is_array(json_decode((string)$mRow['dishes'], true))) {
            throw new RuntimeException("Invalid JSON in service_menus row '{$mRow['menu_code']}'.");
        }
    }
    foreach ($postSnapshot['guidelines'] as $gRow) {
        if (!is_array(json_decode((string)$gRow['items'], true))) {
            throw new RuntimeException("Invalid JSON in catalog_guidelines row '{$gRow['id']}'.");
        }
    }

    // Read-only parity verification against pdrsystem_stage4c5_test catalog tables
    $pdoTestReadOnly = new PDO(
        'mysql:host=localhost;dbname=pdrsystem_stage4c5_test;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $testServices = $pdoTestReadOnly->query(
        "SELECT id, name, category, base_price, price_display, per_head, per_head_by_pkg, max_cap, description, inclusions, guidelines, customer_display, is_active, sort_order
         FROM services ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);
    $prodServicesNow = $pdo->query(
        "SELECT id, name, category, base_price, price_display, per_head, per_head_by_pkg, max_cap, description, inclusions, guidelines, customer_display, is_active, sort_order
         FROM services ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $testPackages = $pdoTestReadOnly->query(
        "SELECT service_id, package_code, package_name, price, is_active, sort_order, max_cap
         FROM service_packages ORDER BY service_id, package_code"
    )->fetchAll(PDO::FETCH_ASSOC);

    $testMenus = $pdoTestReadOnly->query(
        "SELECT service_id, menu_code, category_name, item_name, price, dishes, is_active
         FROM service_menus ORDER BY service_id, menu_code"
    )->fetchAll(PDO::FETCH_ASSOC);

    $testAddons = $pdoTestReadOnly->query(
        "SELECT id, service_id, name, description, price_from, fixed_price, allow_qty, is_active
         FROM service_addons ORDER BY id"
    )->fetchAll(PDO::FETCH_ASSOC);

    $testGuidelines = $pdoTestReadOnly->query(
        "SELECT id, heading, items, sort_order, is_active
         FROM catalog_guidelines ORDER BY sort_order, id"
    )->fetchAll(PDO::FETCH_ASSOC);

    if (
        $prodServicesNow !== $testServices ||
        $postSnapshot['packages'] !== $testPackages ||
        $postSnapshot['menus'] !== $testMenus ||
        $postSnapshot['addons'] !== $testAddons ||
        $postSnapshot['guidelines'] !== $testGuidelines
    ) {
        throw new RuntimeException('Post-migration catalog parity check against pdrsystem_stage4c5_test failed; rolling back.');
    }

    assertStage4eProdDataDb($pdo);
    $pdo->commit();

    echo json_encode([
        'ok' => true,
        'database' => 'pdrsystem',
        'final_counts' => $finalCounts,
        'changes_this_run' => [
            'inserted_packages' => $insertedPackagesCount,
            'updated_service_inclusions' => $updatedInclusionsCount,
            'updated_customer_display' => $updatedDisplayCount,
            'updated_menus' => $updatedMenusCount,
            'inserted_menus' => $insertedMenusCount,
            'inserted_guidelines' => $insertedGuidelinesCount,
            'updated_addons' => $updatedAddonsCount,
        ],
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Stage 4E Phase C production data migration failed: ' . $e->getMessage());
    fwrite(STDERR, "Migration failed; transaction rolled back: " . $e->getMessage() . "\n");
    exit(1);
}
