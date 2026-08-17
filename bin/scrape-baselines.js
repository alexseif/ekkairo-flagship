const { chromium } = require('@playwright/test');
const fs = require('fs');
const path = require('path');

const BASELINES_DIR = path.join(__dirname, '..', 'ai-work', 'baselines');

if (!fs.existsSync(BASELINES_DIR)) {
  fs.mkdirSync(BASELINES_DIR, { recursive: true });
}

const PAGES = [
  // Live Production Baseline Snapshots
  { name: 'live_el_homepage', url: 'https://ekalexandria.org/' },
  { name: 'live_en_homepage', url: 'https://ekalexandria.org/en/' },
  { name: 'live_ar_homepage', url: 'https://ekalexandria.org/ar/' },
  // Staging Comparison Snapshots
  { name: 'backstage_el_homepage', url: 'https://backstage.ekalexandria.org/' },
  { name: 'backstage_en_homepage', url: 'https://backstage.ekalexandria.org/en/' },
  { name: 'backstage_ar_homepage', url: 'https://backstage.ekalexandria.org/ar/' },
  { name: 'backstage_tachydromos', url: 'https://backstage.ekalexandria.org/alx_tachydromos/' },
  { name: 'backstage_board_members', url: 'https://backstage.ekalexandria.org/board-members/' }
];

(async () => {
  console.log('Starting baseline screenshot extraction...');
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({ ignoreHTTPSErrors: true });
  const page = await context.newPage();
  await page.setViewportSize({ width: 1280, height: 800 });

  for (const item of PAGES) {
    try {
      console.log(`Navigating to ${item.url}...`);
      await page.goto(item.url, { waitUntil: 'networkidle', timeout: 60000 }).catch(async () => {
        console.log(`Networkidle timeout, continuing with domcontentloaded for ${item.url}...`);
        await page.goto(item.url, { waitUntil: 'domcontentloaded', timeout: 30000 });
      });

      // Additional delay for dynamic sliders & lazy loaded media
      await page.waitForTimeout(5000);

      // Scroll down to trigger any scroll-based dynamic assets, then scroll back to top
      await page.evaluate(async () => {
        await new Promise((resolve) => {
          let totalHeight = 0;
          const distance = 400;
          const timer = setInterval(() => {
            const scrollHeight = document.body.scrollHeight;
            window.scrollBy(0, distance);
            totalHeight += distance;

            if (totalHeight >= scrollHeight) {
              clearInterval(timer);
              window.scrollTo(0, 0);
              resolve();
            }
          }, 100);
        });
      });

      // Extra settling time after scrolling back up
      await page.waitForTimeout(2000);

      const filePath = path.join(BASELINES_DIR, `${item.name}.png`);
      await page.screenshot({ path: filePath, fullPage: true });
      console.log(`Saved screenshot: ${filePath}`);
    } catch (err) {
      console.error(`Failed to capture ${item.name} (${item.url}):`, err.message);
    }
  }

  await browser.close();
  console.log('Baseline screenshot extraction completed.');
})();
