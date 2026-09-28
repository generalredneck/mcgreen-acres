// Playwright launch/context config consumed by @vardot/varbase-e2e for the
// Drimage acceptance suite. Headless Chromium sized so the admin UI and the
// Drimage settings form render at a desktop viewport.
/** @type {{ browser: string, launchOptions: import('playwright').LaunchOptions, contextOptions: import('playwright').BrowserContextOptions }} */
const browser = process.env.BROWSER || 'chromium';

const chromiumArgs = [
  '--no-sandbox',
  '--disable-dev-shm-usage',
  '--disable-setuid-sandbox',
  '--ignore-certificate-errors',
  '--window-size=1920,1080',
  '--force-device-scale-factor=1',
  '--disable-gpu',
  '--allow-insecure-localhost',
  '--no-first-run',
];

const config = {
  browser,
  launchOptions: {
    headless: process.env.HEADLESS !== 'false',
    slowMo: 0,
    args: browser === 'chromium' ? chromiumArgs : [],
  },
  contextOptions: {
    viewport: { width: 1920, height: 1080 },
    ignoreHTTPSErrors: true,
  },
};

module.exports = config;
