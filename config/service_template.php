<?php
/**
 * T-26: Dynamic service page template helpers.
 * 
 * Provides pdrSetServiceIdFromDb() for resolving SERVICE_ID from the DB
 * catalog by lowercased service name slug. Each service page PHP includes
 * its own targeted DB query; this file exists as a shared utility.
 */

if (!function_exists('pdrSetServiceIdFromDb')) {
    function pdrSetServiceIdFromDb(string $serviceSlug): ?array
    {
        $known = ['catering-menu','coffee','dinein','equipment','intimate','noncoffee','pizza','venue'];
        if (!in_array($serviceSlug, $known, true)) {
            return null;
        }
        $labels = [
            'catering-menu' => 'Catering with Menu',
            'coffee' => 'Coffee Booth Package',
            'dinein' => 'Dine-In Menu',
            'equipment' => 'Equipment Rental',
            'intimate' => 'Intimate Celebration Package',
            'noncoffee' => 'Non-Coffee Booth Package',
            'pizza' => 'Pizza Booth',
            'venue' => 'Venue Rentals',
        ];
        return [
            'id' => $serviceSlug,
            'name' => $labels[$serviceSlug] ?? $serviceSlug,
            'service_id' => $serviceSlug,
        ];
    }
}