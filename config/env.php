<?php
/**
 * Minimal .env loader (no Composer required).
 *
 * Values are read from the project-root .env file (which is git-ignored) and
 * can be overridden by real environment variables. Never commit .env.
 */

function pdrEnv(string $key, ?string $default = null): ?string
{
    static $loaded = false;
    static $vars = [];

    if (!$loaded) {
        $loaded = true;
        $file = dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env';
        if (is_file($file)) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines !== false) {
                foreach ($lines as $line) {
                    $line = trim($line);
                    if ($line === '' || $line[0] === '#') {
                        continue;
                    }
                    $eq = strpos($line, '=');
                    if ($eq === false) {
                        continue;
                    }
                    $name = trim(substr($line, 0, $eq));
                    $value = trim(substr($line, $eq + 1));
                    if ($name === '') {
                        continue;
                    }
                    // Strip matching single or double quotes.
                    $len = strlen($value);
                    if ($len >= 2) {
                        $first = $value[0];
                        $last = $value[$len - 1];
                        if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                            $value = substr($value, 1, -1);
                        }
                    }
                    $vars[$name] = $value;
                }
            }
        }
    }

    $fromEnv = getenv($key);
    if ($fromEnv !== false) {
        return (string) $fromEnv;
    }

    return array_key_exists($key, $vars) ? (string) $vars[$key] : $default;
}
