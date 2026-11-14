<?php
/**
 * Asset versioning helper.
 *
 * .htaccess marks CSS/JS as immutable for a year, so every reference must carry
 * a version string or browsers keep using an old copy after a deploy. The
 * version is the file's mtime, which changes whenever the file is edited, so
 * there is no manifest to keep in sync.
 *
 * The asset is expected to sit in the same directory as the executing page
 * (admin/*.php -> admin/*.css, customer/*.php -> customer/*.js).
 */
if (!function_exists('pdrAsset')) {
    function pdrAsset(string $file): string
    {
        $relative = ltrim(str_replace('\\', '/', $file), '/');
        $directory = dirname((string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
        $absolute = $directory . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        $version = is_file($absolute) ? (string) filemtime($absolute) : '';

        return $relative . ($version !== '' ? '?v=' . $version : '');
    }
}
