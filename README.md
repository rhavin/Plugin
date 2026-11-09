# Q Plugin

## Tests

From the workspace root (the parent directory of `Plugin` and `Tools`), install the shared development dependencies and run both PHPUnit suites:

```sh
composer install
composer test
```

The shared Composer dependencies are installed in the workspace-level `vendor` directory. PHPUnit configuration, bootstrap, and test files belong in this repository's `_test` directory. The suite uses WordPress function shims and does not require a WordPress installation or make network requests.