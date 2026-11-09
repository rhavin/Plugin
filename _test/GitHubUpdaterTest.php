<?php

namespace Q\Tools\Tests;

use PHPUnit\Framework\TestCase;
use Q\Tools\GitHubUpdater;

final class GitHubUpdaterTest extends TestCase
{
    private string $pluginDirectory;
    private string $pluginFile;

    protected function setUp(): void
    {
        $this->pluginDirectory = WP_PLUGIN_DIR . '/sample-plugin';
        $this->pluginFile = $this->pluginDirectory . '/sample-plugin.php';

        mkdir($this->pluginDirectory, 0777, true);
        file_put_contents(
            $this->pluginFile,
            "<?php\n/*\n"
            . " * Plugin Name: Sample Plugin\n"
            . " * Plugin URI: https://example.com/sample-plugin\n"
            . " * Version: 1.0.0\n"
            . " * Update URI: https://github.com/acme/sample-plugin\n"
            . " * Tested up to: 6.7\n"
            . " */\n"
        );

        $GLOBALS['wp_test_hooks'] = [];
        $GLOBALS['wp_test_options'] = [];
        $GLOBALS['wp_test_remote_bodies'] = [];
        $GLOBALS['wp_test_http_requests'] = [];
    }

    protected function tearDown(): void
    {
        if (file_exists($this->pluginFile)) {
            unlink($this->pluginFile);
        }

        if (is_dir($this->pluginDirectory)) {
            rmdir($this->pluginDirectory);
        }
    }

    public function testAddRegistersWordPressUpdateHooks(): void
    {
        $updater = new GitHubUpdater($this->pluginFile);

        $updater->add();

        self::assertArrayHasKey('plugins_api', $GLOBALS['wp_test_hooks']);
        self::assertArrayHasKey('update_plugins_github.com', $GLOBALS['wp_test_hooks']);
        self::assertArrayHasKey('http_request_args', $GLOBALS['wp_test_hooks']);
        self::assertArrayHasKey('upgrader_install_package_result', $GLOBALS['wp_test_hooks']);
    }

    public function testPluginDetailsCallbackBuildsDetailsAndConvertsChangelog(): void
    {
        $updater = new GitHubUpdater($this->pluginFile);
        $remoteFile = " * Plugin Name: Remote Sample Plugin\n"
            . " * Plugin URI: https://example.com/remote-plugin\n"
            . " * Version: 2.4.1\n"
            . " * Author: Example Author\n"
            . " * Author URI: https://example.com/author\n"
            . " * Tested up to: 6.8\n"
            . " * Requires at least: 6.4\n"
            . " * Requires PHP: 8.2\n";
        $GLOBALS['wp_test_remote_bodies'] = [
            'https://raw.githubusercontent.com/acme/sample-plugin/main/sample-plugin.php' => $remoteFile,
            'https://raw.githubusercontent.com/acme/sample-plugin/main/CHANGELOG.md' => "# Release 2.4.1\n\n- Fixes **updates**",
        ];
        $updater->setChangelog('CHANGELOG.md');

        $unrelated = (object) ['unchanged' => true];
        self::assertSame(
            $unrelated,
            $updater->_buildPluginDetailsResult(
                $unrelated,
                'plugin_information',
                (object) ['slug' => 'another-plugin']
            )
        );
        self::assertCount(0, $GLOBALS['wp_test_http_requests']);

        $result = $updater->_buildPluginDetailsResult(
            false,
            'plugin_information',
            (object) ['slug' => 'acme-sample-plugin']
        );

        self::assertIsObject($result);
        self::assertSame('Remote Sample Plugin', $result->name);
        self::assertSame('acme-sample-plugin', $result->slug);
        self::assertSame('2.4.1', $result->version);
        self::assertSame('6.4', $result->requires);
        self::assertSame('6.8', $result->tested);
        self::assertSame('8.2', $result->requires_php);
        self::assertSame(
            '<a href="https://example.com/author">Example Author</a>',
            $result->author
        );
        self::assertSame(
            '<h1>Release 2.4.1</h1>' . PHP_EOL
                . PHP_EOL
                . '<ul>' . PHP_EOL
                . '<li>Fixes <strong>updates</strong></li>' . PHP_EOL
                . '</ul>',
            $result->sections['changelog']
        );
    }

    public function testUpdateCallbackReturnsRemoteVersionAndPackage(): void
    {
        $updater = new GitHubUpdater($this->pluginFile);
        $GLOBALS['wp_test_remote_bodies'][
            'https://raw.githubusercontent.com/acme/sample-plugin/main/sample-plugin.php'
        ] = " * Version: 2.4.1\n";

        $unrelated = ['existing' => 'value'];
        self::assertSame(
            $unrelated,
            $updater->_checkPluginUpdates($unrelated, [], 'other-plugin/plugin.php')
        );
        self::assertCount(0, $GLOBALS['wp_test_http_requests']);

        $result = $updater->_checkPluginUpdates(
            false,
            [],
            'sample-plugin/sample-plugin.php'
        );

        self::assertSame('2.4.1', $result['version']);
        self::assertSame('acme-sample-plugin', $result['slug']);
        self::assertSame(
            'https://github.com/acme/sample-plugin/archive/refs/heads/main.zip',
            $result['package']
        );
        self::assertSame('6.7', $result['tested']);
    }

    public function testPrivatePackageRequestsReceiveAuthorizationHeadersOnlyForPackageUrl(): void
    {
        $updater = new GitHubUpdater($this->pluginFile);
        $updater->setAccessToken('github_pat_test-token')->setBranch('release');
        $args = ['headers' => ['Existing' => 'value']];

        self::assertSame(
            $args,
            $updater->_prepareHttpRequestArgs(
                $args,
                'https://api.github.com/repos/acme/sample-plugin/contents/readme.md'
            )
        );

        self::assertSame(
            [
                'headers' => [
                    'Existing' => 'value',
                    'Authorization' => 'Bearer github_pat_test-token',
                    'Accept' => 'application/vnd.github+json',
                ],
            ],
            $updater->_prepareHttpRequestArgs(
                $args,
                'https://api.github.com/repos/acme/sample-plugin/zipball/release'
            )
        );
    }
}
