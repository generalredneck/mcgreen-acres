# Testing

Two suites: PHPUnit kernel tests for the back end, and Gherkin feature files driven by
Playwright for the browser behavior.

## Kernel tests

```bash
vendor/bin/phpunit -c web/core web/modules/contrib/drimage_improved
```

`tests/src/Kernel/` covers the settings form config, the formatter output, the hook
implementations, install and uninstall, the image style naming and its reverse parsing,
the style cleanup, and the Stage File Proxy integration. `modules/drimage_s3fs/tests/`
covers the submodule.

## Functional suite

Feature files live in `tests/features/drimage/`. They are run with Cucumber and Playwright:

```bash
npm install
npx playwright install --with-deps chromium
LAUNCH_URL=https://example.ddev.site npm run test:chromium
```

Run one file, or one lane by tag:

```bash
LAUNCH_URL=https://example.ddev.site npx cucumber-js --config cucumber.js \
  tests/features/drimage/10-01-drimage-rendering.feature

LAUNCH_URL=https://example.ddev.site npx cucumber-js --config cucumber.js --tags "@perf"
```

The suite covers rendering, focal point crops, the formatter settings, media and content
type setups, derivative paths, Image Widget Crop, style regeneration and a performance
budget.

## Test fixtures

The scenarios are seeded by a hidden recipe, `tests/recipes/drimage_improved_test`, with
optional sub-recipes for the Focal Point, Image Widget Crop and WebP-off variants:

```bash
drush recipe /path/to/drimage_improved/tests/recipes/drimage_improved_test
```

It creates the content type, the image field on the Drimage formatter, and a sample page.

## Continuous integration

`.gitlab-ci.yml` runs, in order: a Composer validation, the static analysis and linting
stage (PHPCS, PHPStan, CSpell, ESLint, Stylelint), the kernel tests, a Lighthouse budget,
the functional suite across several setups, and a job that merges the reports. Every job
gates the merge.

Run the whole pipeline locally before pushing:

```bash
gitlab-ci-local
```
