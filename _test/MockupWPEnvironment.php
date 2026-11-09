<?php

define('WP_PLUGIN_DIR', str_replace('\\', '/', sys_get_temp_dir()) . '/q-plugin-phpunit');

$GLOBALS['wp_test_hooks'] = [];
$GLOBALS['wp_test_options'] = [];
$GLOBALS['wp_test_remote_bodies'] = [];
$GLOBALS['wp_test_http_requests'] = [];

function get_file_data(string $file, array $defaultHeaders): array
{
    $contents = file_get_contents($file, false, null, 0, 8192);
    $data = [];

    foreach ($defaultHeaders as $key => $header) {
        $pattern = '/^[ \t\/*#@]*' . preg_quote($header, '/') . ':\s*(.*)$/mi';
        preg_match($pattern, $contents, $matches);
        $data[$key] = isset($matches[1]) ? trim($matches[1]) : '';
    }

    return $data;
}

function wp_parse_url(string $url, int $component = -1): mixed
{
    return parse_url($url, $component);
}

function add_filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    $GLOBALS['wp_test_hooks'][$hook][] = [$callback, $priority, $acceptedArgs];

    return true;
}

function add_action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): bool
{
    return add_filter($hook, $callback, $priority, $acceptedArgs);
}

function wp_remote_get(string $url, array $args = []): array
{
    $GLOBALS['wp_test_http_requests'][] = [$url, $args];

    return ['body' => $GLOBALS['wp_test_remote_bodies'][$url] ?? ''];
}

function wp_remote_retrieve_body(array $response): string
{
    return $response['body'] ?? '';
}

require_once __DIR__ . '/../_src/GitHubUpdater.php';
