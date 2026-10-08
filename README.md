# Import WP BLM File Reader Addon

Requires Import WP: 2.6.2

**Version: 0.0.3**

## Description

Import WP BLM File Reader Addon allows you to import data from blm files.

## PHPUnit

Locally via the shared `iwp-dev` stack:

```bash
cd ../iwp-dev
./bin/use-profile.sh blm --test
npm run test:start
npm run test:blm:install
npm run test:blm
```

See [tests/README.md](tests/README.md) for GitHub Actions configuration and suite coverage.

## Release

Push a version tag matching the plugin header `0.0.2` to publish a GitHub Release zip via `.github/workflows/release.yml`.

## Changelog

### 0.0.3

- ADD - Update preview to work with new pagination.
- FIX - reduce memory consuption when working with large blm files.

### 0.0.2

- FIX - Escape "|" character when used as EOR.
