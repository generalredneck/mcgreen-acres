// cucumber-js configuration for the Drimage front-end acceptance suite.
//
// Drives a running Drupal site (any Drupal site with drimage_improved
// + focal_point + content that renders a Drimage image) through the browser
// with @vardot/varbase-e2e (Playwright + Cucumber-js).
//
//   LAUNCH_URL=<served-url> npx cucumber-js --config cucumber.js
//   FEATURES="tests/features/drimage/**/*.feature" LAUNCH_URL=<url> npx cucumber-js --config cucumber.js
//
// This is a browser suite: it needs a served site AND a Chromium browser
// (`npx playwright install --with-deps chromium`). It cannot run against a
// static checkout — see the functional-acceptance job in .gitlab-ci.yml.

module.exports = {
  default: {
    timeout: 60000,
    retry: 1,
    // The step library loads .js and .ts step files through tsx's require hook.
    requireModule: ['tsx/cjs'],
    require: [
      // Shared step definitions from the step library (I am an anonymous user, I go to
      // homepage, "…" should be attached within N seconds, I should see, …).
      'node_modules/@vardot/varbase-e2e/tests/step-definitions/**/*.js',
      // The Drimage custom step ("… should be loaded"), shipped in this module.
      'tests/step-definitions/**/*.js',
    ],
    // FEATURES scopes the run; unset runs every Drimage feature.
    paths: [process.env.FEATURES || 'tests/features/**/*.feature'],
    format: [
      '@cucumber/pretty-formatter',
      'json:tests/reports/' + (process.env.CUCUMBER_JSON || 'drimage_report') + '.json',
    ],
    worldParameters: {
      launchUrl: process.env.LAUNCH_URL || process.env.DDEV_PRIMARY_URL || 'https://localhost',
      users: {
        "webmaster": {
          "username": "webmaster",
          "email": "webmaster@example.com",
          "password": process.env.VARBASE_E2E_WEBMASTER_PASSWORD || "dD.123123ddd"
        }
      },
      minWaitTime: {
        page: 8000,
        before_scenario: 0,
        after_scenario: 0,
        before_step: 0,
        after_step: 0,
      },
      screenshot: {
        dir: './tests/screenshots',
        onFailed: true,
        onEveryStep: false,
        failedPrefix: 'failed_',
      },
      video: {
        mode: process.env.VARBASE_E2E_VIDEO || 'on-failure',
        dir: './tests/videos',
      },
      javascript: {
        // Report but do not fail on benign console noise; genuine JS errors
        // are still surfaced. The Drimage placeholder swap and lazy loader
        // emit expected chatter on some admin pages.
        mode: process.env.VARBASE_E2E_JS_ERROR_MODE || 'warn',
        levels: ['error'],
        afterScenario: true,
      },
    },
  },
};
