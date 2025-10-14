import { test, expect } from '@playwright/test';

test('Debug admin login step by step', async ({ page }) => {
  // Enable console logging
  page.on('console', msg => console.log('BROWSER CONSOLE:', msg.text()));
  page.on('pageerror', err => console.log('PAGE ERROR:', err));

  console.log('=== STEP 1: Navigate to login page ===');
  await page.goto('https://aeims.app/login.php');
  await page.waitForLoadState('domcontentloaded');

  const title = await page.title();
  console.log('Page title:', title);
  console.log('Current URL:', page.url());

  console.log('\n=== STEP 2: Check form elements ===');
  const form = await page.locator('form.login-form');
  const formCount = await form.count();
  console.log('Login form count:', formCount);

  const formAction = await form.getAttribute('action');
  const formMethod = await form.getAttribute('method');
  console.log('Form action:', formAction);
  console.log('Form method:', formMethod);

  console.log('\n=== STEP 3: Fill in credentials ===');
  await page.fill('#username', 'admin');
  console.log('✓ Filled username');

  await page.fill('#password', 'admin123');
  console.log('✓ Filled password');

  await page.screenshot({ path: 'test-results/debug-before-submit.png', fullPage: true });
  console.log('✓ Screenshot taken');

  console.log('\n=== STEP 4: Submit form ===');

  // Listen for navigation
  const navigationPromise = page.waitForNavigation({ timeout: 10000 }).catch(() => null);

  await page.click('button[type="submit"]');
  console.log('✓ Clicked submit button');

  // Wait a bit
  await page.waitForTimeout(3000);

  const nav = await navigationPromise;
  console.log('Navigation occurred:', nav !== null);
  console.log('Current URL after submit:', page.url());

  await page.screenshot({ path: 'test-results/debug-after-submit.png', fullPage: true });

  console.log('\n=== STEP 5: Check for errors ===');
  const errorMsg = await page.locator('.error-message').textContent().catch(() => 'none');
  console.log('Error message:', errorMsg);

  console.log('\n=== STEP 6: Check response ===');
  const bodyText = await page.locator('body').textContent();

  if (bodyText.includes('Invalid username or password')) {
    console.log('❌ Found: Invalid username or password');
  }

  if (bodyText.includes('Admin Dashboard')) {
    console.log('✅ Found: Admin Dashboard');
  }

  if (bodyText.includes('Customer Login')) {
    console.log('⚠️  Still on login page');
  }

  if (bodyText.includes('Welcome')) {
    console.log('✅ Found: Welcome message');
  }

  // Check session/cookies
  const cookies = await page.context().cookies();
  console.log('\n=== COOKIES ===');
  cookies.forEach(cookie => {
    console.log(`${cookie.name}: ${cookie.value.substring(0, 20)}...`);
  });

  console.log('\n=== FINAL URL ===');
  console.log(page.url());
});

test('Check what accounts.json has in prod', async ({ page }) => {
  console.log('Attempting to fetch accounts.json (should be blocked)...');

  const response = await page.goto('https://aeims.app/data/accounts.json').catch(() => null);

  if (response) {
    console.log('Response status:', response.status());
    if (response.status() === 200) {
      const content = await response.text();
      console.log('accounts.json content:', content.substring(0, 500));
    }
  }
});
