# PHPUnit

Integration tests for the BLM Reader addon. Locally they run against the shared `iwp-dev` stack (ImportWP + optional Pro + this addon). On GitHub Actions they use `.wp-env.ci.json` with sibling checkouts.

## Local (`iwp-dev`)

```bash
cd ../iwp-dev
./bin/use-profile.sh blm --test
npm run test:start
npm run test:blm:install   # first time / after composer.json changes
npm run test:blm
```

## GitHub Actions

Workflow: `.github/workflows/phpunit.yml`

- Checks out `importwp/importwp` and (optionally) `jcollings/importwp-pro`
- Starts wp-env from `.wp-env.ci.json`
- Runs this repo’s PHPUnit suite

Optional repo configuration:

| Name | Type | Purpose |
| --- | --- | --- |
| `IMPORTWP_PRO_TOKEN` | secret | PAT with read access to `jcollings/importwp-pro` |
| `IMPORTWP_REF` | variable | ImportWP branch/tag (default `master`) |
| `IMPORTWP_PRO_REF` | variable | ImportWP Pro branch/tag (default `master`) |

Without `IMPORTWP_PRO_TOKEN`, CI still runs against free ImportWP.

## Suites

| Path | Coverage |
| --- | --- |
| `Bootstrap/DependenciesTest.php` | Stack smoke (ImportWP + addon classes) |
| `Importer/File/BLMFileTest.php` | Header/definition parse, record index, processing sample, pipe EOR |
| `Importer/Parser/BLMParserTest.php` | Field-name queries, multiline values, queryGroup |
| `Setup/FileTypesTest.php` | Allowed types + extension detection |
| `Setup/PreviewTest.php` | File preview paging, file-process sample count, sample-index rebuild, record preview |
| `Setup/AttachmentTest.php` | Companion zip extract via `[iwp:iwp_blmr_attachment()]`, price qualifier, import context |

Shared helpers live in `Utils/BLMFileTestTrait.php`.

## Sample data

| File | Purpose |
| --- | --- |
| `samples/properties.blm` | 3 properties, multiline description, `^` / `~` |
| `samples/pipe-eor.blm` | Pipe `|` as EOR |
| `samples/with-media.blm` | Media + price qualifier fields for attachment tests |
