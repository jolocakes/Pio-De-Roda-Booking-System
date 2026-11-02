<?php
/** Stage 4C.5 idempotent catalog correction seeder; test DB only, CLI only. */
if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "CLI execution required.\n");
    exit(1);
}
require_once __DIR__ . '/../config/db_stage4c5_test.php';

function assertCatalogTestDb(PDO $pdo): void
{
    if ($pdo->query('SELECT DATABASE()')->fetchColumn() !== 'pdrsystem_stage4c5_test') {
        throw new RuntimeException('Database isolation guard failed.');
    }
}

$display = [
    'catering-menu' => [
        'rates' => [['2 Main Courses','₱1,000 per head'],['3 Main Courses','₱1,100 per head'],['4 Main Courses','₱1,300 per head']],
        'mainCourses' => ['Chicken Recipe','Beef Recipe','Pork Recipe','Fish or Seafood Recipe'],
        'notes' => ['A minimum of 50 pax applies for this package.','Menu tasting may be arranged upon request, subject to availability.'],
        'inclusions' => ['Tables and chairs','Food and beverages','Basic decorations','Serving staff','Venue setup','Cleanup'],
    ],
    'venue' => [
        'rates' => [['Extension Hall (30 to 35 persons)','₱15,000'],['Extension Hall (36 - 50 Persons)','₱20,000'],['Main Hall (50 - 65 Persons)','₱25,000'],['Main Hall (66 - 100 Persons)','₱30,000']],
        'inclusions' => ['Use of air-conditioned venue for 4 hours','Basic sound system set-up (player, speaker and microphone)','Basic Catering Set-up','Decorative stage and throne','Elegantly skirted buffet table','Tables for cake and gifts','Round table with floor length table cover','Table numbers & centerpiece for each table','Tiffany Chairs with thematic ribbon','Complete silverware, glassware and flatware','Professionally trained and uniformed staff','Provision for parking space'],
        'notes' => [],
    ],
    'dinein' => [
        'rates' => [['Per Head','₱600'],['10 Persons','₱6,000'],['20 Persons','₱12,000'],['30 Persons','₱18,000']],
        'inclusions' => ['Dine-in at Al-Fresco Restaurant Area for 1-1.5 hours','Complete silverware, glassware,and flatware','Professionally trained and uniformed staff','Provision for parking space'],
        'notes' => [],
    ],
    'noncoffee' => [
        'rates' => [['Sparkling Refreshers','₱85 per cup'],['Creamy Milk Tea','₱85 per cup']],
        'inclusions' => ['Booth setup','Assigned staff','Assorted drinks menu'],
        'inclusionsTable' => ['headers' => ['Sparkling Refreshers','Creamy Milk Tea'], 'rows' => [['16 oz Iced Drinks','16 oz Iced Drinks'],['5 Flavors: Strawberry, Green Apple, Peach, Passion Fruit, Blue Raspberry','5 Flavors: Matcha, Cookies & Cream, Dark Chocolate, Salted Caramel, Okinawa']]],
        'notes' => ['Transportation Fee applies outside Indang, Cavite','50% Down Payment to book your event','50% Full Payment must be made 1 day before the event','Additional charge of 500.00 per hour for every succeeding hour','Ensure that there will be a nearby power outlet','Corkage or electricity fees shall be shouldered by the client'],
    ],
    'coffee' => [
        'rates' => [['Intimate','₱125 per cup'],['Classic','₱145 per cup'],['Ultimate','₱165 per cup']],
        'inclusions' => ['Premium Quality Coffee Beans','Premium Sauces and Syrups','Quality Espresso Machine and Grinder','Complete Coffee Booth Set-up','3-4 Hours of Service','2-3 Experienced and Uniformed Barista','Claiming Stub for the guests (optional)','Free Transportation for 100 cups and above within Indang, Trece, Mendez or Tagaytay'],
        'inclusionsTable' => ['headers' => ['Intimate','Classic','Ultimate'], 'rows' => [['12 oz Hot Drinks','12 oz Hot Drinks','12 oz Hot Drinks'],['12 oz Iced Drinks','12 oz Iced Drinks','12 oz Iced Drinks'],['4 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato','5 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte','7 Coffee Flavors: Americano, Caffe Latte, Cappuccino, Caramel Macchiato, Vanilla Latte, Spanish Latte, Mocha, 1 Non-Coffee Flavor: Chocolate']]],
        'notes' => ['Transportation Fee applies outside Indang, Cavite','50% Down Payment to book your event','50% Full Payment must be made 1 day before the event','Additional charge of 500.00 per hour for every succeeding hour','Ensure that there will be a nearby power outlet','Corkage or electricity fees shall be shouldered by the client'],
    ],
];

$display['pizza'] = [
    'rates' => [['15 Pizza','₱7,500'],['20 Pizza','₱10,000'],['25 Pizza','₱12,000'],['30 Pizza','₱14,500']],
    'inclusions' => ['Freshly Made Pizza Dough','Premium Quality Mozarella Cheese, Sauces, & Ingredients','Pugon-Quality Pizza Oven for Authentic Wood-Fired Flavor','Complete Pizza Booth Set-up','2-3 hours of Service','2-3 Experienced and Uniformed Pizza Masters','Claiming Stub for Guests (optional)','Free Transportation within Indang, Cavite','Minimal Transportation for Trece, Amadeo, Mendez, Tagaytay, Silang, and Alfonso'],
    'notes' => ['50% Down Payment to book your event','50% Full Payment must be made 1 day before the event','Additional charge of ₱500 per hour for every succeeding hour','Ensure that the venue allows LPG tank for the pugon pizza oven','In such case, corkage or electricity fees shall be shouldered by the client'],
];
$display['intimate'] = [
    'rates' => [['Intimate','₱4,000'],['Ultimate','₱14,000'],['Classic','₱8,000']],
    'inclusionsTable' => ['headers' => ['Intimate','Ultimate','Classic'], 'rows' => [['Coffee Shop','Extension Hall with 25-35 Persons','Kamayan 1'],['6-8 Persons','Air-Conditioned','15-20 Persons'],['Air-Conditioned','Basic Sound System','Al-Fresco Area'],['2 Hours Exclusive Events Place','2 Hours Exclusive Events Place','2 Hours Exclusive Events Place'],['Consumable Drinks and Food','Consumable Drinks and Food','Consumable Drinks and Food']]],
    'notes' => ['For intimate celebrations such as Bridal Shower, Gender Reveal, Baby SHower, and small birthday parties only.','Provision of one (1) rectangular table with table cover is included for buffet set-up.','Outside food and drinks are highly discouraged. Food corkage will be implmented.','Booking will be confrimed upon receipt of a 50% downpayment.','Additional charges apply for themed set-ups. Guests may bring their own decor, but only limited decorations are allowed.','Use of nails, glue, double sided tape, confetti, or any materials that may damage the property is strictly prohibited.','Standard party duration is 2 hours. Extension is subject to availability and additional charges.','Use of venue equipment (e.g. sound system) must be requested in advance.'],
];
$display['equipment'] = ['rates' => [['Per Item','₱300']], 'inclusions' => ['Delivery within service area','Basic setup'], 'notes' => []];

$packages = [
    ['coffee','coffee-classic-50','Classic Package (50 Cups)',7250.00,3,null],
    ['coffee','coffee-classic-100','Classic Package (100 Cups)',14500.00,4,null],
    ['coffee','coffee-ultimate-50','Ultimate Package (50 Cups)',8250.00,5,null],
    ['coffee','coffee-ultimate-100','Ultimate Package (100 Cups)',16500.00,6,null],
    ['pizza','pizza-classic-25','Classic Package (80 Persons) with 25 Pizza',12000.00,3,80],
    ['pizza','pizza-classic-30','Classic Package (80 Persons) with 30 Pizza',14500.00,4,80],
    ['intimate','intimate-package-classic','Classic Package',8000.00,3,null],
];


try {
    assertCatalogTestDb($pdo);
    $pdo->beginTransaction();

    $expectedServiceIds = ['catering-menu','coffee','dinein','equipment','intimate','noncoffee','pizza','venue'];
    $actualServiceIds = $pdo->query("SELECT id FROM services ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);
    if ($actualServiceIds !== $expectedServiceIds) throw new RuntimeException('Service IDs differ from approved baseline; stopping.');
    $approvedBasePrices = ['catering-menu'=>1000.00,'coffee'=>6250.00,'dinein'=>600.00,'equipment'=>300.00,'intimate'=>4000.00,'noncoffee'=>4250.00,'pizza'=>7500.00,'venue'=>15000.00];
    $basePriceRows = $pdo->query("SELECT id,base_price,is_active,max_cap FROM services ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($basePriceRows as $baseRow) {
        if ((float)$baseRow['base_price'] !== $approvedBasePrices[$baseRow['id']] || (int)$baseRow['is_active'] !== 1) throw new RuntimeException('Service base price or active flag differs from audited test copy; refusing overwrite.');
        if ($baseRow['id'] === 'intimate' && (int)$baseRow['max_cap'] !== 50) throw new RuntimeException('Intimate service capacity is not the confirmed 50.');
        if ($baseRow['id'] === 'pizza' && (int)$baseRow['max_cap'] !== 60) throw new RuntimeException('Pizza service capacity changed from preserved 60.');
    }

    $expectedAddonBaseline = [
        'catering-menu-addon' => ['catering-menu','catering-menu',0],
        'venue-addon' => ['venue','venue',0],
        'dinein-addon' => ['dinein',null,0],
        'noncoffee-booth-addon' => ['noncoffee','noncoffee',0],
        'coffee-booth-addon' => ['coffee','coffee',0],
        'pizza-booth-addon' => ['pizza','pizza',0],
        'intimate-addon' => ['intimate','intimate',0],
        'equipment-addon' => ['equipment',null,0],
    ];
    $actualAddons = $pdo->query("SELECT id,service_id,price_from,fixed_price,allow_qty FROM service_addons ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($actualAddons) !== count($expectedAddonBaseline)) throw new RuntimeException('Add-on baseline count differs; stopping.');
    $addonBaselineOriginal = true;
    foreach ($actualAddons as $addonRow) {
        $expected = $expectedAddonBaseline[$addonRow['id']] ?? null;
        if ($expected === null || $addonRow['service_id'] !== $expected[0] || $addonRow['fixed_price'] !== null) {
            throw new RuntimeException('Add-on ID, parent, or fixed price differs from audited copy; refusing overwrite.');
        }
        $correctedSource = $addonRow['id'] === 'dinein-addon' ? 'dinein' : ($addonRow['id'] === 'equipment-addon' ? 'equipment' : $expected[1]);
        $correctedQty = $addonRow['id'] === 'equipment-addon' ? 1 : 0;
        $isOriginal = $addonRow['price_from'] === $expected[1] && (int)$addonRow['allow_qty'] === $expected[2];
        $isCorrected = $addonRow['price_from'] === $correctedSource && (int)$addonRow['allow_qty'] === $correctedQty;
        if (!$isOriginal && !$isCorrected) throw new RuntimeException('Add-on pricing/quantity differs from recognized source/corrected state.');
        if (!$isOriginal) $addonBaselineOriginal = false;
    }


    $packageBaseline = $pdo->query("SELECT service_id,package_code,package_name,price,is_active,sort_order,max_cap FROM service_packages ORDER BY service_id,package_code")->fetchAll(PDO::FETCH_ASSOC);
    if (count($packageBaseline) !== 18 && count($packageBaseline) !== 25) throw new RuntimeException('Package baseline count differs; stopping.');
    $packageSource = [
        'catering-menu'=>['cat-2-courses'=>'2 Main Courses','cat-3-courses'=>'3 Main Courses','cat-4-courses'=>'4 Main Courses'],
        'venue'=>['venue-ext-30-35'=>'Extension Hall (30-35 Persons)','venue-ext-36-50'=>'Extension Hall (36-50 Persons)','venue-main-50-65'=>'Main Hall (50-65 Persons)','venue-main-66-100'=>'Main Hall (66-100 Persons)'],
        'coffee'=>['coffee-intimate-50'=>'Intimate Package (50 Cups)','coffee-intimate-100'=>'Intimate Package (100 Cups)'],
        'noncoffee'=>['noncoffee-sparkling-50'=>'Sparkling Refreshers (50 Cups)','noncoffee-sparkling-100'=>'Sparkling Refreshers (100 Cups)','noncoffee-milktea-50'=>'Creamy Milk Tea (50 Cups)','noncoffee-milktea-100'=>'Creamy Milk Tea (100 Cups)'],
        'pizza'=>['pizza-intimate-15'=>'Intimate Package (50 Persons) with 15 Pizza','pizza-intimate-20'=>'Intimate Package (50 Persons) with 20 Pizza'],
        'intimate'=>['intimate-package-std'=>'Intimate Package','intimate-package-ultimate'=>'Ultimate Package'],
        'equipment'=>['equipment-standard'=>'Standard Rate'],
    ];
    $expectedExistingCodes = [];
    foreach ($packageSource as $sid => $packagesForService) {
        foreach (array_keys($packagesForService) as $code) $expectedExistingCodes[$sid . '|' . $code] = true;
    }
    foreach ($packageBaseline as $pkgBase) {
        $key = $pkgBase['service_id'] . '|' . $pkgBase['package_code'];
        if (isset($expectedExistingCodes[$key])) {
            $expected = $packageSource[$pkgBase['service_id']][$pkgBase['package_code']];
            $baselinePrices = [
                'cat-2-courses'=>1000.00,'cat-3-courses'=>1100.00,'cat-4-courses'=>1300.00,
                'venue-ext-30-35'=>15000.00,'venue-ext-36-50'=>20000.00,'venue-main-50-65'=>25000.00,'venue-main-66-100'=>30000.00,
                'coffee-intimate-50'=>6250.00,'coffee-intimate-100'=>12500.00,
                'noncoffee-sparkling-50'=>4250.00,'noncoffee-sparkling-100'=>8500.00,'noncoffee-milktea-50'=>4250.00,'noncoffee-milktea-100'=>8500.00,
                'pizza-intimate-15'=>7500.00,'pizza-intimate-20'=>10000.00,
                'intimate-package-std'=>4000.00,'intimate-package-ultimate'=>14000.00,'equipment-standard'=>300.00,
            ];
            if ($pkgBase['package_name'] !== $expected || (float)$pkgBase['price'] !== $baselinePrices[$pkgBase['package_code']] || (int)$pkgBase['is_active'] !== 1) {
                throw new RuntimeException('Existing package name/price/status baseline differs; refusing overwrite.');
            }
        } else {
            $approvedCorrection = false;
            foreach ($packages as [$sid,$code,$name,$price,$order,$cap]) {
                if ($sid === $pkgBase['service_id'] && $code === $pkgBase['package_code'] && $name === $pkgBase['package_name'] && (float)$price === (float)$pkgBase['price'] && (int)$pkgBase['is_active'] === 1 && (int)$pkgBase['sort_order'] === $order && (($cap === null && $pkgBase['max_cap'] === null) || ($cap !== null && (int)$pkgBase['max_cap'] === $cap))) {
                    $approvedCorrection = true;
                    break;
                }
            }
            if (!$approvedCorrection) throw new RuntimeException('Existing package row conflicts with approved correction; refusing overwrite.');
            continue;
        }
    }
    $exists = $pdo->prepare("SELECT package_name,price,is_active,sort_order,max_cap FROM service_packages WHERE service_id=? AND package_code=?");
    $insert = $pdo->prepare("INSERT INTO service_packages (service_id,package_code,package_name,price,is_active,sort_order,max_cap) VALUES (?,?,?,?,1,?,?)");
    $updateCap = $pdo->prepare("UPDATE service_packages SET max_cap=? WHERE service_id=? AND package_code=? AND max_cap IS NULL");
    $insertPackageCount = 0;
    foreach ($packages as [$serviceId,$code,$name,$price,$order,$cap]) {
        assertCatalogTestDb($pdo);
        $exists->execute([$serviceId,$code]);
        $existingPackage = $exists->fetch(PDO::FETCH_ASSOC);
        if (!$existingPackage) {
            assertCatalogTestDb($pdo);
            $insert->execute([$serviceId,$code,$name,$price,$order,$cap]);
            $insertPackageCount++;
        } else {
            if ($existingPackage['package_name'] !== $name || (float)$existingPackage['price'] !== (float)$price) {
                throw new RuntimeException('Existing package key conflicts with approved name or price; refusing overwrite.');
            }
            if ($cap !== null) {
                if ($existingPackage['max_cap'] === null) {
                    assertCatalogTestDb($pdo);
                    $updateCap->execute([$cap,$serviceId,$code]);
                } elseif ((int)$existingPackage['max_cap'] !== $cap) {
                    throw new RuntimeException('Existing package capacity conflicts with approved capacity; refusing overwrite.');
                }
            }
        }
    }

    $inclusionValues = [
        'catering-menu' => ['Tables and chairs','Food and beverages','Basic decorations','Serving staff','Venue setup','Cleanup'],
        'venue' => ['Use of air-conditioned venue for 4 hours','Basic sound system set-up (player, speaker and microphone)','Basic Catering Set-up','Decorative stage and throne','Elegantly skirted buffet table','Tables for cake and gifts','Round table with floor length table cover','Table numbers & centerpiece for each table','Tiffany Chairs with thematic ribbon','Complete silverware, glassware and flatware','Professionally trained and uniformed staff','Provision for parking space'],
        'dinein' => ['Dine-in at Al-Fresco Restaurant Area for 1-1.5 hours','Complete silverware, glassware,and flatware','Professionally trained and uniformed staff','Provision for parking space'],
        'noncoffee' => ['Booth setup','Assigned staff','Assorted drinks menu'],
        'coffee' => ['Premium Quality Coffee Beans','Premium Sauces and Syrups','Quality Espresso Machine and Grinder','Complete Coffee Booth Set-up','3-4 Hours of Service','2-3 Experienced and Uniformed Barista','Claiming Stub for the guests (optional)','Free Transportation for 100 cups and above within Indang, Trece, Mendez or Tagaytay'],
        'pizza' => ['Freshly Made Pizza Dough','Premium Quality Mozarella Cheese, Sauces, & Ingredients','Pugon-Quality Pizza Oven for Authentic Wood-Fired Flavor','Complete Pizza Booth Set-up','2-3 hours of Service','2-3 Experienced and Uniformed Pizza Masters','Claiming Stub for Guests (optional)','Free Transportation within Indang, Cavite','Minimal Transportation for Trece, Amadeo, Mendez, Tagaytay, Silang, and Alfonso'],
        'intimate' => ['Thematic setup','Basic lighting'],
        'equipment' => ['Delivery within service area','Basic setup'],
    ];
    $legacyInclusions = [
        'catering-menu' => ['Tables and chairs','Food and beverages','Basic decorations'],
        'venue' => ['Air-conditioned venue'],
        'dinein' => ['Al-Fresco Area'],
        'noncoffee' => ['Booth setup','Assigned staff'],
        'coffee' => ['Booth setup','Assigned staff'],
        'pizza' => ['Live station setup','Assigned staff'],
        'intimate' => ['Thematic setup','Basic lighting'],
        'equipment' => ['Delivery within service area','Basic setup'],
    ];
    // Only proceed when all eight current values still match the verified test-copy baseline.
    $actualServices = $pdo->query("SELECT id,inclusions,customer_display FROM services ORDER BY id")->fetchAll(PDO::FETCH_ASSOC);
    if (count($actualServices) !== count($legacyInclusions)) throw new RuntimeException('Service baseline count differs; stopping without guessing.');
    $displayAlreadyApplied = true;
    $inclusionsAlreadyApplied = true;
    foreach ($actualServices as $serviceRow) {
        $sid = $serviceRow['id'];
        $actualInclusions = json_decode($serviceRow['inclusions'] ?? '[]', true);
        if (!isset($legacyInclusions[$sid])) throw new RuntimeException('Unexpected service ID in test DB.');
        if ($actualInclusions !== $inclusionValues[$sid]) $inclusionsAlreadyApplied = false;
        if ($actualInclusions !== $inclusionValues[$sid] && $actualInclusions !== $legacyInclusions[$sid]) {
            throw new RuntimeException('Service inclusions differ from recognized original/corrected state; refusing overwrite.');
        }
        if ($serviceRow['customer_display'] !== null) $displayAlreadyApplied = false;
    }
    $readInclusions = $pdo->prepare("SELECT inclusions FROM services WHERE id=?");
    $writeInclusions = $pdo->prepare("UPDATE services SET inclusions=? WHERE id=? AND inclusions=?");
    $correctedInclusions = true;
    foreach ($inclusionValues as $serviceId => $confirmedValues) {
        assertCatalogTestDb($pdo);
        $readInclusions->execute([$serviceId]);
        $currentJson = $readInclusions->fetchColumn();
        $currentValues = json_decode((string)$currentJson, true);
        if ($currentValues === $confirmedValues) continue;
        $correctedInclusions = false;
        if ($currentValues !== $legacyInclusions[$serviceId]) {
            throw new RuntimeException('Service inclusions differ from the audited baseline; refusing overwrite.');
        }
        assertCatalogTestDb($pdo);
        $writeInclusions->execute([json_encode($confirmedValues, JSON_UNESCAPED_UNICODE),$serviceId,$currentJson]);
        if ($writeInclusions->rowCount() !== 1) throw new RuntimeException('Conditional service inclusion correction did not affect exactly one expected row.');
    }


    $menuA = ['Plain Rice','Crab & Corn Soup','Creamy Mixed Veggies w/ Quail Eggs','Garlic Chicken w/ Gravy Sauce','Pork w/ Mushroom Sauce','Lemon Iced Tea','Peach Mango Graham'];
    $menuB = ['Plain Rice','Creamy Mushroom Soup','Special Chopseuy w/ Quail Eggs','Chicken Cordon Bleu w/ White Sauce','Pork w/ Oyster Sauce & Brocolli','Blue Lemonade','Frozen Cookies & Cream'];
    $includedDishes = ['Chips for Pica-Pica','Steamed Pandan Rice','Soup','Vegetable Recipe','Beverages','Dessert','Pancit/Pasta Recipe'];
    $findMenu = $pdo->prepare("SELECT category_name,item_name,price,dishes,is_active FROM service_menus WHERE service_id=? AND menu_code=?");
    $insertMenu = $pdo->prepare("INSERT INTO service_menus (service_id,menu_code,category_name,item_name,price,dishes,is_active) VALUES (?,?,?,?,NULL,?,1)");
    $insertMenuCount = 0;
    $menuRows = [
        ['dinein','dinein-menu-a','Menu A',$menuA],
        ['dinein','dinein-menu-b','Menu B',$menuB],
        ['catering-menu','catering-included-dishes','Included Dishes',$includedDishes],
    ];
    $actualMenuBaseline = $pdo->query("SELECT service_id,menu_code,category_name,item_name,price,dishes,is_active FROM service_menus ORDER BY service_id,menu_code")->fetchAll(PDO::FETCH_ASSOC);
    if (count($actualMenuBaseline) !== 1 && count($actualMenuBaseline) !== 3) throw new RuntimeException('Menu count is not original or fully corrected; stopping.');
    $menuBaselineIsOriginal = count($actualMenuBaseline) === 1 && $actualMenuBaseline[0]['service_id'] === 'dinein' && $actualMenuBaseline[0]['menu_code'] === 'dinein-menu-a' && $actualMenuBaseline[0]['category_name'] === 'Menu A' && $actualMenuBaseline[0]['item_name'] === 'Menu A' && (float)$actualMenuBaseline[0]['price'] === 600.0 && $actualMenuBaseline[0]['dishes'] === json_encode(['Plain Rice','Soup','Chicken'], JSON_UNESCAPED_UNICODE) && (int)$actualMenuBaseline[0]['is_active'] === 1;
    $menuBaselineIsCorrected = count($actualMenuBaseline) === 3;
    if ($menuBaselineIsCorrected) {
        $expectedMenus = ['dinein|dinein-menu-a','dinein|dinein-menu-b','catering-menu|catering-included-dishes'];
        $actualKeys = array_map(static fn($m) => $m['service_id'].'|'.$m['menu_code'], $actualMenuBaseline);
        sort($actualKeys); sort($expectedMenus);
        $menuBaselineIsCorrected = $actualKeys === $expectedMenus;
        foreach ($actualMenuBaseline as $menuRow) {
            if ((int)$menuRow['is_active'] !== 1 || $menuRow['price'] !== null) $menuBaselineIsCorrected = false;
            $expectedDishes = $menuRow['menu_code'] === 'dinein-menu-a' ? $menuA : ($menuRow['menu_code'] === 'dinein-menu-b' ? $menuB : ($menuRow['menu_code'] === 'catering-included-dishes' ? $includedDishes : null));
            if ($expectedDishes === null || $menuRow['dishes'] !== json_encode($expectedDishes, JSON_UNESCAPED_UNICODE)) $menuBaselineIsCorrected = false;
        }
    }
    if (!$menuBaselineIsOriginal && !$menuBaselineIsCorrected) {
        throw new RuntimeException('Menu baseline differs from recognized original/corrected test state; refusing overwrite.');
    }
    $legacyMenuAJson = json_encode(['Plain Rice','Soup','Chicken'], JSON_UNESCAPED_UNICODE);
    $confirmedMenuAJson = json_encode($menuA, JSON_UNESCAPED_UNICODE);
    $correctMenuA = $pdo->prepare("UPDATE service_menus SET dishes=?,price=NULL WHERE service_id='dinein' AND menu_code='dinein-menu-a' AND dishes=? AND price=600.00");
    $checkMenuA = $pdo->prepare("SELECT dishes,price FROM service_menus WHERE service_id='dinein' AND menu_code='dinein-menu-a'");
    foreach ($menuRows as [$sid,$code,$name,$dishes]) {
        assertCatalogTestDb($pdo);
        $category = $sid === 'catering-menu' ? 'Included Dishes' : $name;
        $dishesJson = json_encode($dishes, JSON_UNESCAPED_UNICODE);
        $findMenu->execute([$sid,$code]);
        $existingMenu = $findMenu->fetch(PDO::FETCH_ASSOC);
        if (!$existingMenu) {
            assertCatalogTestDb($pdo);
            $insertMenu->execute([$sid,$code,$category,$name,$dishesJson]);
            $insertMenuCount++;
        } elseif ($code === 'dinein-menu-a' && $existingMenu['dishes'] === $legacyMenuAJson && (float)$existingMenu['price'] === 600.0) {
            assertCatalogTestDb($pdo);
            $correctMenuA->execute([$confirmedMenuAJson,$legacyMenuAJson]);
            $checkMenuA->execute();
            $correctedMenuA = $checkMenuA->fetch(PDO::FETCH_ASSOC);
            if (!$correctedMenuA || $correctedMenuA['dishes'] !== $confirmedMenuAJson || $correctedMenuA['price'] !== null) {
                throw new RuntimeException('Menu A correction verification failed.');
            }
        } elseif ($existingMenu['category_name'] !== $category || $existingMenu['item_name'] !== $name || $existingMenu['dishes'] !== $dishesJson || $existingMenu['price'] !== null) {
            throw new RuntimeException('Existing menu differs from approved seed and was not overwritten.');
        }
    }

    $displayStmt = $pdo->prepare("UPDATE services SET customer_display=? WHERE id=? AND customer_display IS NULL");
    $readDisplay = $pdo->prepare("SELECT customer_display FROM services WHERE id=?");
    $displayAlreadyApplied = true;
    foreach ($display as $serviceId => $customerDisplay) {
        assertCatalogTestDb($pdo);
        $readDisplay->execute([$serviceId]);
        $existingDisplay = $readDisplay->fetchColumn();
        $jsonDisplay = json_encode($customerDisplay, JSON_UNESCAPED_UNICODE);
        if ($existingDisplay === null) {
            $displayAlreadyApplied = false;
            assertCatalogTestDb($pdo);
            $displayStmt->execute([$jsonDisplay,$serviceId]);
        } elseif ($existingDisplay !== $jsonDisplay) {
            throw new RuntimeException('Existing customer display differs from approved data; refusing overwrite.');
        }
    }

    $guidelines = [
        ['booking-reservation','Booking & Reservation',[
            'In order to reserve your chosen date, time and venue, we are requiring a non-refundable deposit of ₱2,000.00 which will be deducted from your final bill. No booking or reservation will be considered confirmed until a deposit is made.',
            'Client should finalize the details of their event at least 2 weeks before the event and make a partial payment of 50% of their total bill.',
            'A final confirmation of the total number of guests shall be made no later than 5 working days prior to the date of the event. This confirmed number will be the minimum number which will be charged. Additional guests shall be served and charged accordingly on a per plate / per head basis.',
            'The remaining 50% shall be paid in full 1 day before the day of the event.',
            'Additional and other incidental charges shall be settled on the day of the event, right after the said event.',
            'Only the reserved venue shall be used for your event. Additional charges shall be imposed for the use of other venues.'
        ]],
        ['cancellation-changes','Cancellation, Changes to the Reservation',[
            'All changes to your reservation must be made no later than 5 working days prior to the date of the event. All changes will be considered; however, we hold the right not to accept the change if prior preparation is already done.'
        ]],
        ['food-catering-setup','Food, Catering Set-Up and Others',[
            'All allocations are based on experience in the food industry. We usually cater for more than necessary, but we cannot account for over average eaters.',
            'Client may bring their own food to be served during their event provided prior arrangements are done. Though, we will not take responsibility for any other food and drinks not provided by Pio de Roda Café on the day of the event.',
            'Pio de Roda Café will assist with any additional items during the event. However, we are not responsible for any items provided by the client for the event such as but not limited to flowers, cakes, souvenirs, etc.',
            'Pio De Roda Café will serve you with our basic catering set-up which includes celebrant\'s stage and throne, buffet table, cake and gift tables, tables and chairs, complete silverware, flatware and dinnerware, basic sound system, and trained waiters. However, should you require more intricate details it will be under our event styling arrangements which will be on a separate transaction.',
            'Outside providers or suppliers such as lights and sound system, projectors, LED screens, food carts, etc. shall have a corkage for the energy consumption.',
            'Pio De Roda Café is practicing a per head and plate basis, however, children below 7 years old are free of charge. Please refrain from using another plate. Saucers will be provided for your desserts and other food use.',
            'We are following the IATF regulation to limit the guests for every event. Senior Citizens and children are encouraged to take precautionary measures for their own safety. Please limit their exposure as well. Children shall be limited to a maximum of 5 kids only.',
            'Remind your guests to bring and wear their face masks and face shields at all times. And also bring their vaccination cards and present at the contact tracing booth.'
        ]]
    ];
    $guidelineCountBefore = (int)$pdo->query("SELECT COUNT(*) FROM catalog_guidelines")->fetchColumn();
    $guidelineFind = $pdo->prepare("SELECT heading,items,sort_order,is_active FROM catalog_guidelines WHERE id=?");
    $guidelineInsert = $pdo->prepare("INSERT INTO catalog_guidelines (id,heading,items,sort_order,is_active) VALUES (?,?,?,?,1)");
    $insertGuidelineCount = 0;
    foreach ($guidelines as $index => [$id,$heading,$items]) {
        assertCatalogTestDb($pdo);
        $guidelineFind->execute([$id]);
        $existingGuideline = $guidelineFind->fetch(PDO::FETCH_ASSOC);
        $itemsJson = json_encode($items, JSON_UNESCAPED_UNICODE);
        if (!$existingGuideline) {
            assertCatalogTestDb($pdo);
            $guidelineInsert->execute([$id,$heading,$itemsJson,$index + 1]);
            $insertGuidelineCount++;
        } elseif ($existingGuideline['heading'] !== $heading || $existingGuideline['items'] !== $itemsJson || (int)$existingGuideline['sort_order'] !== $index + 1 || (int)$existingGuideline['is_active'] !== 1) {
            throw new RuntimeException('Shared guideline differs from approved data; refusing overwrite.');
        }
    }

    $addonBaselineCorrected = true;
    $addonRowsInsertedByThisRun = false;
    $dineinAddon = $pdo->query("SELECT price_from,allow_qty,fixed_price FROM service_addons WHERE id='dinein-addon' AND service_id='dinein'")->fetch(PDO::FETCH_ASSOC);
    $equipmentAddon = $pdo->query("SELECT price_from,allow_qty,fixed_price FROM service_addons WHERE id='equipment-addon' AND service_id='equipment'")->fetch(PDO::FETCH_ASSOC);
    if (!$dineinAddon || !$equipmentAddon || $dineinAddon['fixed_price'] !== null || $equipmentAddon['fixed_price'] !== null) throw new RuntimeException('Add-on baseline record missing or fixed-price unexpected.');
    if ($dineinAddon['price_from'] === null && (int)$dineinAddon['allow_qty'] === 0) {
        assertCatalogTestDb($pdo);
        $pdo->exec("UPDATE service_addons SET price_from='dinein' WHERE id='dinein-addon' AND service_id='dinein' AND price_from IS NULL AND allow_qty=0");
        $addonBaselineCorrected = false;
    } elseif ($dineinAddon['price_from'] !== 'dinein' || (int)$dineinAddon['allow_qty'] !== 0) {
        throw new RuntimeException('Dine-In add-on differs from allowed original/corrected state.');
    } else { $addonBaselineCorrected = false; }

    if ($equipmentAddon['price_from'] === null && (int)$equipmentAddon['allow_qty'] === 0) {
        assertCatalogTestDb($pdo);
        $pdo->exec("UPDATE service_addons SET price_from='equipment',allow_qty=1 WHERE id='equipment-addon' AND service_id='equipment' AND price_from IS NULL AND allow_qty=0");
        $addonBaselineCorrected = false;
    } elseif ($equipmentAddon['price_from'] !== 'equipment' || (int)$equipmentAddon['allow_qty'] !== 1) {
        throw new RuntimeException('Equipment add-on differs from allowed original/corrected state.');
    } else { $addonBaselineCorrected = false; }
    $addonCheck = $pdo->query("SELECT COUNT(*) FROM service_addons WHERE (id='dinein-addon' AND price_from='dinein' AND allow_qty=0 AND fixed_price IS NULL) OR (id='equipment-addon' AND price_from='equipment' AND allow_qty=1 AND fixed_price IS NULL)")->fetchColumn();
    if ((int)$addonCheck !== 2) throw new RuntimeException('Add-on reference or quantity precondition mismatch.');

    assertCatalogTestDb($pdo);
    $finalCounts = [
        'services' => (int)$pdo->query("SELECT COUNT(*) FROM services WHERE is_active=1")->fetchColumn(),
        'packages' => (int)$pdo->query("SELECT COUNT(*) FROM service_packages WHERE is_active=1")->fetchColumn(),
        'menus' => (int)$pdo->query("SELECT COUNT(*) FROM service_menus WHERE is_active=1")->fetchColumn(),
        'addons' => (int)$pdo->query("SELECT COUNT(*) FROM service_addons WHERE is_active=1")->fetchColumn(),
    ];
    $capacityCheck = $pdo->query("SELECT (SELECT max_cap FROM services WHERE id='intimate') intimate_cap,(SELECT max_cap FROM services WHERE id='pizza') pizza_cap,(SELECT COUNT(*) FROM service_packages WHERE package_code IN ('pizza-classic-25','pizza-classic-30') AND max_cap=80) pizza_classic_caps")->fetch(PDO::FETCH_ASSOC);
    if ($finalCounts !== ['services'=>8,'packages'=>25,'menus'=>3,'addons'=>8] || (int)$capacityCheck['intimate_cap'] !== 50 || (int)$capacityCheck['pizza_cap'] !== 60 || (int)$capacityCheck['pizza_classic_caps'] !== 2) {
        throw new RuntimeException('Post-seed verification invariant failed.');
    }
    assertCatalogTestDb($pdo);
    $pdo->commit();
    echo json_encode(['ok'=>true,'active_counts'=>$finalCounts,'new_packages_this_run'=>$insertPackageCount,'new_menus_this_run'=>$insertMenuCount,'new_guideline_groups_this_run'=>$insertGuidelineCount], JSON_PRETTY_PRINT) . "\n";
} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('Stage 4C.5 test seeder failed: ' . $e->getMessage());
    fwrite(STDERR, "Seeder failed; transaction rolled back.\n");
    exit(1);
}

