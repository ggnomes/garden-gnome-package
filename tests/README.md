# Tests

This project includes a minimal WordPress PHPUnit setup.

## Prerequisites

- WordPress test library available at `WP_TESTS_DIR` (or `/tmp/wordpress-tests-lib`)
- PHPUnit

## Run

```bash
WP_TESTS_DIR=/path/to/wordpress-tests-lib phpunit
```

## Current Coverage

- GGPKG unpack warning status for invalid file extensions
- Admin notice queue rendering on media screens
- Media Library row action registration for re-extract
