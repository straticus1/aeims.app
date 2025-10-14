import { test, expect } from '@playwright/test';

test.describe('Production Authentication Tests', () => {

  test('Admin login on aeims.app', async ({ page }) => {
    console.log('🔍 Testing admin login on aeims.app...');

    // Navigate to login page
    await page.goto('https://aeims.app/login.php');
    await page.waitForLoadState('networkidle');

    // Take screenshot of login page
    await page.screenshot({ path: 'test-results/01-aeims-login-page.png', fullPage: true });
    console.log('📸 Screenshot: login page');

    // Fill in credentials
    await page.fill('#username', 'admin');
    await page.fill('#password', 'admin123');

    console.log('✏️  Filled in admin credentials');
    await page.screenshot({ path: 'test-results/02-aeims-credentials-filled.png', fullPage: true });

    // Submit form
    await page.click('button[type="submit"]');
    console.log('🔘 Clicked submit button');

    // Wait for navigation
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    // Take screenshot of result
    await page.screenshot({ path: 'test-results/03-aeims-after-submit.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);

    const pageContent = await page.content();
    console.log(`📄 Page title: ${await page.title()}`);

    // Check for errors
    const errorMessage = await page.locator('.error-message').textContent().catch(() => null);
    if (errorMessage) {
      console.log(`❌ Error message found: ${errorMessage}`);
    }

    // Check if redirected to dashboard
    if (currentUrl.includes('admin-dashboard.php')) {
      console.log('✅ Redirected to admin dashboard');
    } else if (currentUrl.includes('dashboard.php')) {
      console.log('✅ Redirected to customer dashboard');
    } else if (currentUrl.includes('login.php')) {
      console.log('❌ Still on login page - login failed');

      // Check for specific error indicators
      const bodyText = await page.locator('body').textContent();
      console.log('🔍 Checking page content for clues...');

      if (bodyText.includes('Invalid')) {
        console.log('❌ Found "Invalid" in page content');
      }
      if (bodyText.includes('error')) {
        console.log('❌ Found "error" in page content');
      }
    } else {
      console.log(`⚠️  Unexpected URL: ${currentUrl}`);
    }

    // Verify we're logged in (check for welcome message or dashboard)
    const isLoggedIn = currentUrl.includes('dashboard.php') && !currentUrl.includes('login.php');
    console.log(`🔐 Login successful: ${isLoggedIn}`);

    expect(isLoggedIn).toBeTruthy();
  });

  test('Customer login on aeims.app', async ({ page }) => {
    console.log('🔍 Testing customer login on aeims.app...');

    await page.goto('https://aeims.app/login.php');
    await page.waitForLoadState('networkidle');

    await page.screenshot({ path: 'test-results/04-customer-login-page.png', fullPage: true });

    await page.fill('#username', 'demo@example.com');
    await page.fill('#password', 'password123');

    console.log('✏️  Filled in customer credentials');
    await page.screenshot({ path: 'test-results/05-customer-credentials-filled.png', fullPage: true });

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.screenshot({ path: 'test-results/06-customer-after-submit.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);
    console.log(`📄 Page title: ${await page.title()}`);

    const isLoggedIn = currentUrl.includes('dashboard.php') && !currentUrl.includes('login.php');
    console.log(`🔐 Customer login successful: ${isLoggedIn}`);

    expect(isLoggedIn).toBeTruthy();
  });

  test('Flirts.NYC customer login', async ({ page }) => {
    console.log('🔍 Testing flirts.nyc customer login...');

    await page.goto('https://flirts.nyc/login.php');
    await page.waitForLoadState('networkidle');

    await page.screenshot({ path: 'test-results/07-flirts-login-page.png', fullPage: true });

    // Try to find the username/password fields (they might have different IDs)
    const usernameField = await page.locator('input[name="username"], input[type="text"], input[type="email"]').first();
    const passwordField = await page.locator('input[name="password"], input[type="password"]').first();

    await usernameField.fill('flirtyuser');
    await passwordField.fill('password123');

    console.log('✏️  Filled in flirts.nyc credentials');
    await page.screenshot({ path: 'test-results/08-flirts-credentials-filled.png', fullPage: true });

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(2000);

    await page.screenshot({ path: 'test-results/09-flirts-after-submit.png', fullPage: true });

    const currentUrl = page.url();
    console.log(`📍 Current URL: ${currentUrl}`);
    console.log(`📄 Page title: ${await page.title()}`);

    const isLoggedIn = currentUrl.includes('dashboard.php') && !currentUrl.includes('login.php');
    console.log(`🔐 Flirts.NYC login successful: ${isLoggedIn}`);
  });

  test('Debug: Check login.php form action', async ({ page }) => {
    console.log('🔍 Debugging login form...');

    await page.goto('https://aeims.app/login.php');
    await page.waitForLoadState('networkidle');

    // Get form details
    const formAction = await page.locator('form').getAttribute('action');
    const formMethod = await page.locator('form').getAttribute('method');

    console.log(`📋 Form action: ${formAction}`);
    console.log(`📋 Form method: ${formMethod}`);

    // Check if username/password fields exist
    const usernameExists = await page.locator('#username').count() > 0;
    const passwordExists = await page.locator('#password').count() > 0;

    console.log(`✅ Username field exists: ${usernameExists}`);
    console.log(`✅ Password field exists: ${passwordExists}`);

    // Check button
    const submitButton = await page.locator('button[type="submit"]').count();
    console.log(`✅ Submit button count: ${submitButton}`);
  });

  test('Check production data files', async ({ page }) => {
    console.log('🔍 Checking if data files are accessible...');

    // Try to access data files (should be blocked in production)
    await page.goto('https://aeims.app/data/accounts.json');
    const status1 = page.url();
    console.log(`📍 accounts.json URL: ${status1}`);

    await page.goto('https://aeims.app/router.php');
    const status2 = page.url();
    console.log(`📍 router.php URL: ${status2}`);

    await page.screenshot({ path: 'test-results/10-router-check.png', fullPage: true });
  });
});
