<?php
/**
 * Shared reservation pricing.
 *
 * One implementation of "what does this booking cost", used by BOTH the public
 * booking endpoint and the admin edit endpoint. Keeping a single copy is what
 * stops an admin edit from silently leaving a stale `estimated_total` behind.
 *
 * Rules (unchanged from the original booking flow):
 *   - The selected package (or menu tier) must be an ACTIVE option of the
 *     service. A package rate is multiplied by the guest count only when the
 *     service is priced `per_head_by_pkg`; otherwise it is a flat price.
 *   - A menu-tiered pick uses the service's `per_head` rate.
 *   - Every add-on line is re-priced from the database. The browser's price is
 *     never accepted.
 *   - Unpriceable / unknown / duplicate add-ons are rejected, not zero-priced.
 *
 * Returns:
 *   ['ok' => true,  'total' => float, 'packageMaxCap' => ?int, 'addons' => array]
 *   ['ok' => false, 'field' => string, 'reason' => string]
 *
 * `addons` entries mirror the shape stored in reservations.additional_services:
 *   ['name', 'packageName', 'quantity', 'unitPrice', 'totalPrice']
 */

if (!function_exists('pdrPriceReservation')) {
    /**
     * @param array  $serviceRow      services row: id, name, per_head, per_head_by_pkg, max_cap
     * @param string $selectedPackage package name or menu tier name
     * @param int    $guests
     * @param array  $addonLines      each: ['name' => string, 'packageName' => string, 'quantity' => int]
     */
    function pdrPriceReservation(PDO $pdo, array $serviceRow, string $selectedPackage, int $guests, array $addonLines): array
    {
        $serviceId = (string) ($serviceRow['id'] ?? '');
        $serviceName = (string) ($serviceRow['name'] ?? '');
        $selectedPackage = trim($selectedPackage);

        if ($serviceId === '' || $selectedPackage === '') {
            return ['ok' => false, 'field' => 'selectedPackage', 'reason' => 'INVALID'];
        }

        $total = 0.0;
        $packageMaxCap = null;

        // --- Selected Food / Package: an ACTIVE package (or menu tier) of this service ---
        $pkgStmt = $pdo->prepare(
            "SELECT price, max_cap FROM service_packages
              WHERE service_id = ? AND package_name = ? AND is_active = 1
              ORDER BY sort_order ASC LIMIT 1"
        );
        $pkgStmt->execute([$serviceId, $selectedPackage]);
        $pkgRow = $pkgStmt->fetch(PDO::FETCH_ASSOC);

        if ($pkgRow) {
            $total = ((int) ($serviceRow['per_head_by_pkg'] ?? 0) === 1)
                ? (float) $pkgRow['price'] * $guests   // per-head package rate × guests
                : (float) $pkgRow['price'];            // flat package price

            if (isset($pkgRow['max_cap']) && $pkgRow['max_cap'] !== null && (int) $pkgRow['max_cap'] > 0) {
                $packageMaxCap = (int) $pkgRow['max_cap'];
            }
        } else {
            // Menu-tiered picks (e.g. Dine-In Menu A / Menu B) resolve through service_menus.
            $menuStmt = $pdo->prepare(
                "SELECT menu_code FROM service_menus
                  WHERE service_id = ? AND item_name = ? AND is_active = 1 LIMIT 1"
            );
            $menuStmt->execute([$serviceId, $selectedPackage]);
            if (!$menuStmt->fetch(PDO::FETCH_ASSOC)) {
                return ['ok' => false, 'field' => 'selectedPackage', 'reason' => 'INVALID'];
            }
            if ($serviceRow['per_head'] === null) {
                // Menu rate is unset and the service has no per-head rate — refusing
                // to store a reservation whose price cannot be resolved safely.
                return ['ok' => false, 'field' => 'selectedPackage', 'reason' => 'INVALID'];
            }
            $total = (float) $serviceRow['per_head'] * $guests;
        }

        // --- Additional Services: validate and re-price every line from the database ---
        $pricedAddons = [];
        if ($addonLines) {
            $lookupAddon = $pdo->prepare(
                "SELECT sa.name, sa.service_id, sa.price_from, sa.fixed_price, sa.allow_qty
                   FROM service_addons sa
                   JOIN services svc ON svc.id = sa.service_id
                  WHERE sa.name = ? AND sa.is_active = 1 AND svc.is_active = 1
                  ORDER BY sa.id ASC LIMIT 1"
            );
            $lookupLinked = $pdo->prepare(
                "SELECT id, per_head, per_head_by_pkg FROM services WHERE id = ? AND is_active = 1 LIMIT 1"
            );
            $lookupLinkedPkg = $pdo->prepare(
                "SELECT price FROM service_packages
                  WHERE service_id = ? AND package_name = ? AND is_active = 1
                  ORDER BY sort_order ASC LIMIT 1"
            );
            $checkAddonName = $pdo->prepare(
                "SELECT (SELECT COUNT(*) FROM service_packages WHERE service_id = ? AND package_name = ? AND is_active = 1)
                       + (SELECT COUNT(*) FROM service_menus    WHERE service_id = ? AND item_name    = ? AND is_active = 1) AS hits"
            );

            $seenAddonNames = [];
            foreach ($addonLines as $entry) {
                $addonName = trim((string) ($entry['name'] ?? ''));
                $aPkgName  = trim((string) ($entry['packageName'] ?? ''));
                $addonQty  = max(1, (int) ($entry['quantity'] ?? 1));

                if ($addonName === '' || $aPkgName === '') {
                    return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                }
                if ($addonName === $serviceName) {
                    // Mirrors the booking-page rule: the main service cannot also be an add-on.
                    return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                }
                if (isset($seenAddonNames[$addonName])) {
                    return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                }
                $seenAddonNames[$addonName] = true;

                $lookupAddon->execute([$addonName]);
                $addon = $lookupAddon->fetch(PDO::FETCH_ASSOC);
                if (!$addon) {
                    // Unknown add-on, inactive add-on, or an add-on whose parent service was deactivated.
                    return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                }

                if ($addon['price_from'] !== null) {
                    // Rates derive from the add-on's linked catalog service.
                    $lookupLinked->execute([$addon['price_from']]);
                    $linked = $lookupLinked->fetch(PDO::FETCH_ASSOC);
                    if (!$linked) {
                        return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                    }
                    if ((int) $linked['per_head_by_pkg'] === 1) {
                        $lookupLinkedPkg->execute([$linked['id'], $aPkgName]);
                        $linkedPkg = $lookupLinkedPkg->fetch(PDO::FETCH_ASSOC);
                        if (!$linkedPkg) {
                            return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                        }
                        $addonRate = (float) $linkedPkg['price'];
                        $addonPerHead = true;
                    } elseif ($linked['per_head'] !== null) {
                        $addonRate = (float) $linked['per_head'];
                        $addonPerHead = true;
                    } else {
                        $lookupLinkedPkg->execute([$linked['id'], $aPkgName]);
                        $linkedPkg = $lookupLinkedPkg->fetch(PDO::FETCH_ASSOC);
                        if (!$linkedPkg) {
                            return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                        }
                        $addonRate = (float) $linkedPkg['price'];
                        $addonPerHead = false;
                    }
                    // The chosen package/tier must be an ACTIVE option of the pricing source.
                    $checkAddonName->execute([$linked['id'], $aPkgName, $linked['id'], $aPkgName]);
                    if ((int) $checkAddonName->fetchColumn() < 1) {
                        return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                    }
                } elseif ($addon['fixed_price'] !== null) {
                    $addonRate = (float) $addon['fixed_price'];
                    $addonPerHead = false;
                    $checkAddonName->execute([$addon['service_id'], $aPkgName, $addon['service_id'], $aPkgName]);
                    if ((int) $checkAddonName->fetchColumn() < 1) {
                        return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                    }
                } else {
                    // Unpriceable add-on (no pricing source configured).
                    return ['ok' => false, 'field' => 'additionalServices', 'reason' => 'INVALID'];
                }

                // Line pricing: per-head add-ons multiply by guests, quantity
                // add-ons multiply by the chosen quantity, the rest are flat.
                $addonTotal = ($addonPerHead ? $addonRate * $guests : $addonRate)
                    * ((int) $addon['allow_qty'] === 1 ? $addonQty : 1);

                $pricedAddons[] = [
                    'name'        => $addon['name'],
                    'packageName' => $aPkgName,
                    'quantity'    => (int) $addon['allow_qty'] === 1 ? $addonQty : 1,
                    'unitPrice'   => round($addonRate, 2),
                    'totalPrice'  => round($addonTotal, 2),
                ];
                $total += $addonTotal;
            }
        }

        return [
            'ok'            => true,
            'total'         => round($total, 2),
            'packageMaxCap' => $packageMaxCap,
            'addons'        => $pricedAddons,
        ];
    }
}
