import assert from 'node:assert/strict';
import { chromium } from 'playwright';

const baseUrl = process.env.ACS_TEST_URL;
const adminUser = process.env.ACS_ADMIN_USER;
const adminPassword = process.env.ACS_ADMIN_PASSWORD;

if (!baseUrl || !adminUser || !adminPassword) {
  throw new Error('ACS_TEST_URL, ACS_ADMIN_USER, and ACS_ADMIN_PASSWORD are required.');
}

const pageErrors = [];
const consoleErrors = [];

async function removeLastGroup(page, label) {
  const titlePattern = new RegExp(`^${label} \\d+$`, 'i');
  const title = page.getByText(titlePattern).last();
  await title.waitFor({ state: 'visible' });

  const removeButtons = page.getByRole('button', { name: /remove item/i });
  const count = await removeButtons.count();
  for (let index = 0; index < count; index += 1) {
    const button = removeButtons.nth(index);
    const belongsToGroup = await button.evaluate((element, groupPatternSource) => {
      const group = element.closest('.at-rpt-grp');
      if (!group) {
        return false;
      }
      const groupTitle = group.querySelector('.at-rpt-grp-hdr-btn');
      if (!groupTitle) {
        return false;
      }
      return new RegExp(groupPatternSource, 'i').test((groupTitle.textContent || '').trim());
    }, titlePattern.source);
    if (belongsToGroup) {
      await button.click();
      return;
    }
  }

  throw new Error(`Could not find the remove button for the last ${label} row.`);
}

async function saveSettings(page) {
  const save = page.getByRole('button', { name: /save settings/i });
  await save.waitFor({ state: 'visible' });
  const [response] = await Promise.all([
    page.waitForResponse(
      (candidate) =>
        candidate.url().includes('/wp-json/acme-coming-soon/v1/settings') &&
        ['PUT', 'POST', 'PATCH'].includes(candidate.request().method()),
    ),
    save.click(),
  ]);
  assert.equal(response.status(), 200, `Settings save returned HTTP ${response.status()}.`);
  const saveUrl = new URL(response.request().url());
  assert.ok(
    saveUrl.pathname.startsWith('/wp-json/'),
    `Settings save used ${saveUrl.href} instead of the WordPress REST API.`,
  );
  const settings = await response.json();
  await page.getByRole('button', { name: /^saved$/i }).waitFor({ state: 'visible' });
  return settings;
}

async function main() {
  const browser = await chromium.launch({ headless: true });
  try {
    const page = await browser.newPage();
    page.setDefaultTimeout(20_000);
    page.on('pageerror', (error) => pageErrors.push(error.message));
    page.on('console', (message) => {
      if (message.type() === 'error') {
        consoleErrors.push(message.text());
      }
    });

    await page.goto(`${baseUrl}/wp-login.php`, { waitUntil: 'networkidle' });
    await page.locator('#user_login').fill(adminUser);
    await page.locator('#user_pass').fill(adminPassword);
    await Promise.all([
      page.waitForURL(/\/wp-admin\//),
      page.locator('#wp-submit').click(),
    ]);

    await page.goto(`${baseUrl}/wp-admin/admin.php?page=acme-coming-soon`, {
      waitUntil: 'networkidle',
    });
    await page.locator('#acme-coming-soon > *').first().waitFor({ state: 'visible' });
    const app = page.locator('#acme-coming-soon');
    await app.getByRole('link', { name: 'Settings', exact: true }).click();
    await page.waitForURL(/#\/maintenance\/general/);
    await page.getByText('Select maintenance page', { exact: true }).waitFor({ state: 'visible' });

    const exclusions = page.getByText(/Exclude from Coming soon \(Maintenance\) mode/i).first();
    await exclusions.click();

    let addIp = page.getByRole('button', { name: /^Add IP$/i });
    if (!(await addIp.isVisible())) {
      await page.getByText('Allow IPs during maintenance mode.', { exact: true }).click();
      addIp = page.getByRole('button', { name: /^Add IP$/i });
    }
    await addIp.click();
    await page.getByText(/^IP \d+$/i).last().click();
    await page.getByLabel('IP', { exact: true }).last().fill('192.0.2.123');

    let addSlug = page.getByRole('button', { name: /^Add slug$/i });
    if (!(await addSlug.isVisible())) {
      await page.getByText('Allow Slugs during maintenance mode.', { exact: true }).click();
      addSlug = page.getByRole('button', { name: /^Add slug$/i });
    }
    await addSlug.click();
    await page.getByText(/^Slug \d+$/i).last().click();
    await page.getByLabel('Slug', { exact: true }).last().fill('playwright-open');

    const added = await saveSettings(page);
    assert.deepEqual(added.exclude.ips, ['192.0.2.123']);
    assert.deepEqual(added.exclude.slugs, ['playwright-open']);

    await removeLastGroup(page, 'IP');
    await removeLastGroup(page, 'Slug');
    const removed = await saveSettings(page);
    assert.deepEqual(removed.exclude.ips, []);
    assert.deepEqual(removed.exclude.slugs, []);

    // The maintenance toggle and page picker must persist through the REST API.
    const maintenanceToggle = page.getByLabel('Activate coming soon (Maintenance) mode');
    const pageSelect = page.getByLabel('Select maintenance page');
    const template = Number(await pageSelect.inputValue());
    assert.ok(template > 0, 'A maintenance page should be selected before toggling.');

    await maintenanceToggle.uncheck();
    const disabled = await saveSettings(page);
    assert.equal(disabled.on, false, 'Turning maintenance mode off should persist.');

    await maintenanceToggle.check();
    const pageSelectAgain = page.getByLabel('Select maintenance page');
    await pageSelectAgain.waitFor({ state: 'visible' });
    if (Number(await pageSelectAgain.inputValue()) !== template) {
      await pageSelectAgain.selectOption(String(template));
    }
    const enabled = await saveSettings(page);
    assert.equal(enabled.on, true, 'Turning maintenance mode on should persist.');
    assert.equal(enabled.template, template, 'The selected maintenance page should persist.');

    assert.deepEqual(pageErrors, [], `Admin page errors: ${pageErrors.join(' | ')}`);
    assert.deepEqual(consoleErrors, [], `Unexpected console errors: ${consoleErrors.join(' | ')}`);
    console.log('Playwright admin smoke passed: login, IP/slug rows, save, and maintenance toggle.');
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  if (pageErrors.length) {
    console.error(`Page errors: ${pageErrors.join(' | ')}`);
  }
  if (consoleErrors.length) {
    console.error(`Console errors: ${consoleErrors.join(' | ')}`);
  }
  console.error(error);
  process.exitCode = 1;
});
