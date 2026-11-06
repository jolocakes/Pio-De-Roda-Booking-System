<?php
/** Build the active public catalog response from the supplied PDO connection. */
function pdrBuildCatalogResponse(PDO $pdo): array
{
    $activeDatabase = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $columnCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=? AND table_name=? AND column_name=?");
    $columnCheck->execute([$activeDatabase, 'services', 'customer_display']);
    $hasCustomerDisplay = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'service_packages', 'max_cap']);
    $hasPackageCapacity = (int)$columnCheck->fetchColumn() === 1;
    $tableCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=? AND table_name='catalog_guidelines'");
    $tableCheck->execute([$activeDatabase]);
    $hasSharedGuidelines = (int)$tableCheck->fetchColumn() === 1;

    $columnCheck->execute([$activeDatabase, 'services', 'price_display']);
    $hasPriceDisplay = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'per_head']);
    $hasPerHead = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'per_head_by_pkg']);
    $hasPerHeadByPkg = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'max_cap']);
    $hasMaxCap = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'inclusions']);
    $hasInclusions = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'guidelines']);
    $hasGuidelines = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'is_active']);
    $hasIsActive = (int)$columnCheck->fetchColumn() === 1;
    $columnCheck->execute([$activeDatabase, 'services', 'sort_order']);
    $hasSortOrder = (int)$columnCheck->fetchColumn() === 1;
    $displayColumn = $hasCustomerDisplay ? ',customer_display' : ',NULL AS customer_display';
    $priceDisplayColumn = $hasPriceDisplay ? ',price_display' : ',NULL AS price_display';
    $perHeadColumn = $hasPerHead ? ',per_head' : ',NULL AS per_head';
    $perHeadByPkgColumn = $hasPerHeadByPkg ? ',per_head_by_pkg' : ',0 AS per_head_by_pkg';
    $maxCapColumn = $hasMaxCap ? ',max_cap' : ',NULL AS max_cap';
    $inclusionsColumn = $hasInclusions ? ',inclusions' : ',NULL AS inclusions';
    $guidelinesColumn = $hasGuidelines ? ',guidelines' : ',NULL AS guidelines';
    $sortOrderColumn = $hasSortOrder ? ',sort_order' : ',0 AS sort_order';
    $whereActive = $hasIsActive ? ' WHERE is_active=1' : '';
    $orderBy = $hasSortOrder ? ' ORDER BY sort_order,id' : ' ORDER BY id';
    $servicesStmt = $pdo->query("SELECT id,name,category,base_price{$priceDisplayColumn}{$perHeadColumn}{$perHeadByPkgColumn}{$maxCapColumn},description{$inclusionsColumn}{$guidelinesColumn}{$sortOrderColumn}{$displayColumn} FROM services{$whereActive}{$orderBy}");
    $packageCapacityColumn = $hasPackageCapacity ? ',max_cap' : ',NULL AS max_cap';
    $services = [];
    $packageStmt = $pdo->prepare("SELECT package_code,package_name,price,is_active,sort_order{$packageCapacityColumn} FROM service_packages WHERE service_id=? AND is_active=1 ORDER BY sort_order,package_code");
    $menuStmt = $pdo->prepare("SELECT menu_code,category_name,item_name,price,dishes,is_active FROM service_menus WHERE service_id=? AND is_active=1 ORDER BY category_name,menu_code");
    $addonStmt = $pdo->prepare("SELECT id,service_id,name,description,price_from,fixed_price,allow_qty,is_active FROM service_addons WHERE service_id=? AND is_active=1 ORDER BY id");

    $servicesStmtRows = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);
    $allPackagesByService = [];
    $allActiveServices = [];
    foreach ($servicesStmtRows as $row) {
        $sid = $row['id'];
        $packageStmt->execute([$sid]);
        $allPackagesByService[$sid] = [];
        foreach ($packageStmt->fetchAll(PDO::FETCH_ASSOC) as $pkg) {
            $allPackagesByService[$sid][] = [
                $pkg['package_name'],
                $pkg['price'] === null ? null : (float)$pkg['price'],
                $pkg['package_code'],
                $pkg['max_cap'] === null ? null : (int)$pkg['max_cap'],
                null,
            ];
        }
        $allActiveServices[$sid] = [
            'perHead' => $row['per_head'] === null ? null : (float)$row['per_head'],
            'perHeadByPkg' => (bool)$row['per_head_by_pkg'],
        ];
    }

    foreach ($servicesStmtRows as $row) {
        $serviceId = $row['id'];
        $display = [];
        if (!empty($row['customer_display'])) {
            $decoded = json_decode($row['customer_display'], true);
            if (is_array($decoded)) $display = $decoded;
        }
        $packages = $allPackagesByService[$serviceId] ?? [];
        $menus = [];
        $menuGroups = [];
        $menuStmt->execute([$serviceId]);
        foreach ($menuStmt->fetchAll(PDO::FETCH_ASSOC) as $menuRow) {
            $dishes = json_decode($menuRow['dishes'] ?? '[]', true);
            if (!is_array($dishes)) $dishes = [];
            $menu = [
                'menu_code' => $menuRow['menu_code'], 'category_name' => $menuRow['category_name'],
                'item_name' => $menuRow['item_name'], 'price' => $menuRow['price'] === null ? null : (float)$menuRow['price'],
                'dishes' => $dishes, 'is_active' => (bool)$menuRow['is_active'],
            ];
            $menus[] = $menu;
            $category = $menuRow['category_name'];
            if (!isset($menuGroups[$category])) $menuGroups[$category] = ['category' => $category, 'items' => []];
            $menuGroups[$category]['items'][] = [
                'name' => $menuRow['item_name'], 'price' => $menu['price'], 'dishes' => $dishes,
                'menuCode' => $menuRow['menu_code'],
            ];
        }
        // Dine-In selection is represented by menu rows; retain NULL menu price and use service per-head rate.
        if ($serviceId === 'dinein' && !$packages) {
            foreach ($menus as $menu) {
                $packages[] = [
                    $menu['item_name'], null, $menu['menu_code'],
                    $row['max_cap'] === null ? null : (int)$row['max_cap'], $menu['menu_code'],
                ];
            }
        }
        $addons = [];
        $addonStmt->execute([$serviceId]);
        foreach ($addonStmt->fetchAll(PDO::FETCH_ASSOC) as $addonRow) {
            $linkedPackages = [];
            $linkedPerHead = null;
            $linkedPerHeadByPackage = false;
            if ($addonRow['price_from'] !== null && isset($allPackagesByService[$addonRow['price_from']])) {
                $linkedPackages = $allPackagesByService[$addonRow['price_from']];
                $linkedPerHead = $allActiveServices[$addonRow['price_from']]['perHead'] ?? null;
                $linkedPerHeadByPackage = $allActiveServices[$addonRow['price_from']]['perHeadByPkg'] ?? false;
            }
            $addons[] = [
                'id' => $addonRow['id'], 'service_id' => $addonRow['service_id'], 'serviceId' => $addonRow['service_id'],
                'name' => $addonRow['name'], 'description' => $addonRow['description'], 'desc' => $addonRow['description'],
                'price_from' => $addonRow['price_from'],
                'fixed_price' => $addonRow['fixed_price'] === null ? null : (float)$addonRow['fixed_price'],
                'allow_qty' => (bool)$addonRow['allow_qty'], 'qty' => (bool)$addonRow['allow_qty'],
                'is_active' => (bool)$addonRow['is_active'], 'pkgs' => $linkedPackages,
                'perHead' => $linkedPerHead, 'perHeadByPkg' => $linkedPerHeadByPackage,
                'price' => $addonRow['fixed_price'] === null ? null : (float)$addonRow['fixed_price'],
            ];
        }

        $inclusions = json_decode($row['inclusions'] ?? '[]', true);
        if (!is_array($inclusions)) $inclusions = [];
        $guidelines = json_decode($row['guidelines'] ?? '[]', true);
        if (!is_array($guidelines)) $guidelines = [];
        $item = [
            'id' => $serviceId, 'name' => $row['name'], 'category' => $row['category'],
            'base_price' => $row['base_price'] === null ? null : (float)$row['base_price'],
            'price_display' => $row['price_display'],
            'per_head' => $row['per_head'] === null ? null : (float)$row['per_head'],
            'per_head_by_pkg' => (bool)$row['per_head_by_pkg'],
            'max_cap' => $row['max_cap'] === null ? null : (int)$row['max_cap'],
            'description' => $row['description'], 'inclusions' => $inclusions,
            'guidelines' => $guidelines, 'sort_order' => (int)$row['sort_order'],
            'packages' => $packages, 'menus' => $menus, 'menu' => array_values($menuGroups),
            'addons' => $addons,
        ];
        foreach (['rates','mainCourses','inclusionsTable','notes'] as $field) {
            if (array_key_exists($field, $display)) $item[$field] = $display[$field];
        }
        $services[] = $item;
    }

    $sharedGuidelines = [];
    if ($hasSharedGuidelines) {
        $guidelineStmt = $pdo->query("SELECT id,heading,items,sort_order FROM catalog_guidelines WHERE is_active=1 ORDER BY sort_order,id");
        foreach ($guidelineStmt->fetchAll(PDO::FETCH_ASSOC) as $g) {
            $items = json_decode($g['items'], true);
            $sharedGuidelines[] = ['id' => $g['id'], 'heading' => $g['heading'], 'items' => is_array($items) ? $items : [], 'sort_order' => (int)$g['sort_order']];
        }
    }

    return ['ok' => true, 'services' => $services, 'guidelines' => $sharedGuidelines];
}

