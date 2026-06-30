<?php

declare(strict_types=1);

$root = dirname(__DIR__, 3);
$configPath = $root.'/infra/docker/nginx/default.conf';

if (!is_file($configPath)) {
    fwrite(STDERR, "Missing nginx config: {$configPath}\n");
    exit(1);
}

$config = file_get_contents($configPath);
if ($config === false) {
    fwrite(STDERR, "Unable to read nginx config: {$configPath}\n");
    exit(1);
}

function sr058_assert(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "SR-058 check failed: {$message}\n");
        exit(1);
    }
}

function sr058_location_body(string $config, string $locationPrefix): string
{
    $quoted = preg_quote($locationPrefix, '~');
    $pattern = '~location\s+(?:\^\~\s+|\~\s+)?'.$quoted.'[^{]*\{(?P<body>.*?)\n\s*\}~s';

    if (!preg_match($pattern, $config, $matches)) {
        return '';
    }

    return $matches['body'];
}

function sr058_assert_block_forbidden(string $config, string $location, string $label): void
{
    $body = sr058_location_body($config, $location);

    sr058_assert($body !== '', "{$label} location is missing");
    sr058_assert(
        preg_match('~\breturn\s+403\s*;|\bdeny\s+all\s*;~', $body) === 1,
        "{$label} must deny anonymous public access with 403"
    );
    sr058_assert(
        preg_match('~add_header\s+Cache-Control\s+"private,\s*no-store"\s+always\s*;~', $body) === 1,
        "{$label} must emit private no-store cache header"
    );
}

function sr058_assert_dynamic_no_store(string $config, string $needle, string $label): void
{
    sr058_assert(str_contains($config, $needle), "{$label} location is missing");

    $offset = strpos($config, $needle);
    $slice = substr($config, (int) $offset, 420);

    sr058_assert(
        preg_match('~try_files\s+\$uri\s+\$uri/\s+@wordpress_no_store\s*;~', $slice) === 1,
        "{$label} must route through no-store WordPress front controller"
    );
    sr058_assert(
        preg_match('~\b(?:fastcgi_cache|proxy_cache|expires)\b~', $slice) !== 1,
        "{$label} must not enable page/object cache directives"
    );
}

function sr058_assert_no_store_front_controller(string $config): void
{
    $body = sr058_location_body($config, '@wordpress_no_store');

    sr058_assert($body !== '', 'no-store WordPress front controller is missing');
    sr058_assert(
        preg_match('~add_header\s+Cache-Control\s+"private,\s*no-store"\s+always\s*;~', $body) === 1,
        'no-store WordPress front controller must emit private no-store cache header'
    );
    sr058_assert(
        preg_match('~fastcgi_param\s+SCRIPT_FILENAME\s+\$document_root/index\.php\s*;~', $body) === 1,
        'no-store WordPress front controller must execute index.php'
    );
    sr058_assert(
        preg_match('~fastcgi_pass\s+php:9000\s*;~', $body) === 1,
        'no-store WordPress front controller must use PHP-FPM'
    );
    sr058_assert(
        preg_match('~\b(?:fastcgi_cache|proxy_cache|expires)\b~', $body) !== 1,
        'no-store WordPress front controller must not enable page/object cache directives'
    );
}

sr058_assert(str_contains($config, 'root /var/www/html/web;'), 'WordPress web root is preserved');
sr058_assert(str_contains($config, 'fastcgi_pass php:9000;'), 'PHP-FPM upstream is preserved');

sr058_assert_block_forbidden($config, '/app/uploads/edd/', 'Bedrock EDD uploads');
sr058_assert_block_forbidden($config, '/wp-content/uploads/edd/', 'WordPress EDD uploads');
sr058_assert_block_forbidden($config, '/sr-private-objects/', 'private object storage');
sr058_assert_block_forbidden($config, '/private-downloads/', 'private download storage');

sr058_assert_dynamic_no_store($config, 'checkout|account', 'checkout/account dynamic pages');
sr058_assert_dynamic_no_store($config, '/wp-json/', 'REST API');
sr058_assert_dynamic_no_store($config, 'download|download-tokens', 'download delivery routes');
sr058_assert_no_store_front_controller($config);

echo "SR-058 nginx policy checks passed\n";
