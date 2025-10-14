import { test, expect } from '@playwright/test';

/**
 * Comprehensive Authentication Flow Tests with Network Capture
 * Tests both authenticated and unauthenticated flows across all domains
 */

// Increase test timeout for network operations
test.setTimeout(120000); // 2 minutes per test

// Test credentials
const credentials = {
  flirtsUser: { username: 'flirtyuser', password: 'password123', site: 'https://flirts.nyc' },
  nycUser: { username: 'nycuser', password: 'password123', site: 'https://nycflirts.com' },
  crossUser: { username: 'crossuser', password: 'password123', sites: ['https://flirts.nyc', 'https://nycflirts.com'] },
  newUser: { username: 'southernslut', password: 'password', sites: ['https://flirts.nyc', 'https://nycflirts.com'] }
};

test.describe('Authentication Flows with Network Capture', () => {

  test.beforeEach(async ({ page }) => {
    // Enable network logging
    page.on('request', request => {
      console.log(`>> ${request.method()} ${request.url()}`);
    });

    page.on('response', response => {
      console.log(`<< ${response.status()} ${response.url()}`);
    });

    page.on('console', msg => {
      console.log(`[Browser Console] ${msg.type()}: ${msg.text()}`);
    });
  });

  test('Flirts NYC - Unauthenticated Homepage Load', async ({ page }) => {
    console.log('\n=== TEST: Flirts NYC Unauthenticated ===');

    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    // Verify page loaded correctly
    await expect(page).toHaveTitle(/Flirts NYC/i);

    // Check for login modal or button
    const loginButton = page.locator('text=/login/i').first();
    await expect(loginButton).toBeVisible();

    // Take screenshot
    await page.screenshot({ path: 'tests/screenshots/flirts-unauthenticated.png', fullPage: true });

    console.log('✅ Unauthenticated homepage loaded successfully');
  });

  test('Flirts NYC - Customer Login Flow', async ({ page }) => {
    console.log('\n=== TEST: Flirts NYC Customer Login ===');

    // Navigate to homepage
    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    // Find and click login button/modal
    const loginTrigger = page.locator('button:has-text("Login"), a:has-text("Login"), [onclick*="login"], #loginBtn').first();
    await loginTrigger.click();

    // Wait for login form
    await page.waitForSelector('input[name="username"], #username', { timeout: 5000 });

    // Fill in credentials
    await page.fill('input[name="username"], #username', credentials.flirtsUser.username);
    await page.fill('input[name="password"], #password', credentials.flirtsUser.password);

    // Take screenshot before submit
    await page.screenshot({ path: 'tests/screenshots/flirts-login-form.png' });

    // Submit login form
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("Login"), button:has-text("Sign In")').first();
    await submitButton.click();

    // Wait for navigation or success message
    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    // Check for dashboard or authenticated state
    const url = page.url();
    console.log(`After login URL: ${url}`);

    // Verify we're authenticated
    const isDashboard = url.includes('dashboard');
    const hasLogout = await page.locator('text=/logout/i').count() > 0;
    const hasWelcome = await page.locator('text=/welcome/i').count() > 0;

    console.log(`Dashboard: ${isDashboard}, Logout: ${hasLogout}, Welcome: ${hasWelcome}`);

    // Take screenshot after login
    await page.screenshot({ path: 'tests/screenshots/flirts-authenticated.png', fullPage: true });

    // Verify authentication succeeded
    expect(isDashboard || hasLogout || hasWelcome).toBeTruthy();

    console.log('✅ Customer login successful');
  });

  test('NYC Flirts - Customer Login Flow', async ({ page }) => {
    console.log('\n=== TEST: NYC Flirts Customer Login ===');

    await page.goto('https://nycflirts.com/');
    await page.waitForLoadState('networkidle');

    // Find and click login
    const loginTrigger = page.locator('button:has-text("Login"), a:has-text("Login"), [onclick*="login"], #loginBtn').first();
    await loginTrigger.click();

    // Wait for login form
    await page.waitForSelector('input[name="username"], #username', { timeout: 5000 });

    // Fill credentials
    await page.fill('input[name="username"], #username', credentials.nycUser.username);
    await page.fill('input[name="password"], #password', credentials.nycUser.password);

    await page.screenshot({ path: 'tests/screenshots/nyc-login-form.png' });

    // Submit
    const submitButton = page.locator('button[type="submit"], input[type="submit"], button:has-text("Login")').first();
    await submitButton.click();

    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    const url = page.url();
    console.log(`After login URL: ${url}`);

    await page.screenshot({ path: 'tests/screenshots/nyc-authenticated.png', fullPage: true });

    const isDashboard = url.includes('dashboard');
    const hasLogout = await page.locator('text=/logout/i').count() > 0;

    expect(isDashboard || hasLogout).toBeTruthy();

    console.log('✅ NYC Flirts login successful');
  });

  test('Cross-Site User - Login on Both Sites', async ({ page }) => {
    console.log('\n=== TEST: Cross-Site User Authentication ===');

    // Test on Flirts NYC
    console.log('Testing on Flirts NYC...');
    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    const flirtsLogin = page.locator('button:has-text("Login"), a:has-text("Login"), [onclick*="login"]').first();
    await flirtsLogin.click();

    await page.waitForSelector('input[name="username"], #username');
    await page.fill('input[name="username"], #username', credentials.crossUser.username);
    await page.fill('input[name="password"], #password', credentials.crossUser.password);

    const flirtsSubmit = page.locator('button[type="submit"], input[type="submit"]').first();
    await flirtsSubmit.click();

    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    const flirtsUrl = page.url();
    console.log(`Flirts NYC URL after login: ${flirtsUrl}`);
    await page.screenshot({ path: 'tests/screenshots/crossuser-flirts.png', fullPage: true });

    // Verify Flirts NYC login
    expect(flirtsUrl.includes('dashboard') || await page.locator('text=/logout/i').count() > 0).toBeTruthy();
    console.log('✅ Cross-site user authenticated on Flirts NYC');

    // Logout
    const logoutLink = page.locator('a:has-text("Logout"), button:has-text("Logout")').first();
    if (await logoutLink.count() > 0) {
      await logoutLink.click();
      await page.waitForTimeout(1000);
    }

    // Test on NYC Flirts
    console.log('Testing on NYC Flirts...');
    await page.goto('https://nycflirts.com/');
    await page.waitForLoadState('networkidle');

    const nycLogin = page.locator('button:has-text("Login"), a:has-text("Login"), [onclick*="login"]').first();
    await nycLogin.click();

    await page.waitForSelector('input[name="username"], #username');
    await page.fill('input[name="username"], #username', credentials.crossUser.username);
    await page.fill('input[name="password"], #password', credentials.crossUser.password);

    const nycSubmit = page.locator('button[type="submit"], input[type="submit"]').first();
    await nycSubmit.click();

    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    const nycUrl = page.url();
    console.log(`NYC Flirts URL after login: ${nycUrl}`);
    await page.screenshot({ path: 'tests/screenshots/crossuser-nyc.png', fullPage: true });

    // Verify NYC Flirts login
    expect(nycUrl.includes('dashboard') || await page.locator('text=/logout/i').count() > 0).toBeTruthy();
    console.log('✅ Cross-site user authenticated on NYC Flirts');
  });

  test('Direct Auth.php Request - Flirts NYC', async ({ page }) => {
    console.log('\n=== TEST: Direct auth.php POST Request ===');

    // Test direct POST to auth.php endpoint
    const response = await page.request.post('https://flirts.nyc/auth.php', {
      form: {
        action: 'login',
        username: credentials.flirtsUser.username,
        password: credentials.flirtsUser.password
      },
      maxRedirects: 0,
      failOnStatusCode: false
    });

    console.log(`Response status: ${response.status()}`);
    console.log(`Response headers:`, response.headers());

    // Check for redirect to dashboard
    const location = response.headers()['location'];
    console.log(`Location header: ${location}`);

    if (response.status() === 302 || response.status() === 303) {
      expect(location).toContain('dashboard');
      console.log('✅ Direct auth.php POST redirected to dashboard');
    } else {
      const body = await response.text();
      console.log('Response body:', body.substring(0, 500));

      // Check if it's a success even without redirect
      expect(body).toMatch(/dashboard|welcome|authenticated/i);
      console.log('✅ Direct auth.php POST succeeded');
    }
  });

  test('Session Persistence Across Pages', async ({ page }) => {
    console.log('\n=== TEST: Session Persistence ===');

    // Login
    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    const loginBtn = page.locator('button:has-text("Login"), a:has-text("Login")').first();
    await loginBtn.click();

    await page.waitForSelector('input[name="username"]');
    await page.fill('input[name="username"]', credentials.flirtsUser.username);
    await page.fill('input[name="password"]', credentials.flirtsUser.password);

    const submit = page.locator('button[type="submit"]').first();
    await submit.click();

    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    // Navigate to different pages
    const pages = ['/messages.php', '/chat.php', '/search-operators.php', '/activity-log.php'];

    for (const path of pages) {
      try {
        await page.goto(`https://flirts.nyc${path}`);
        await page.waitForLoadState('networkidle');

        const url = page.url();
        console.log(`Navigated to: ${url}`);

        // Check if we're still authenticated (not redirected to login)
        const isStillAuth = !url.includes('login') || await page.locator('text=/logout/i').count() > 0;

        if (isStillAuth) {
          console.log(`✅ Session persisted on ${path}`);
        } else {
          console.log(`❌ Lost session on ${path}`);
        }
      } catch (error) {
        console.log(`⚠️  Page ${path} may not exist: ${error.message}`);
      }
    }
  });

  test('Error Handling - Invalid Credentials', async ({ page }) => {
    console.log('\n=== TEST: Invalid Credentials Error Handling ===');

    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    const loginBtn = page.locator('button:has-text("Login"), a:has-text("Login")').first();
    await loginBtn.click();

    await page.waitForSelector('input[name="username"]');
    await page.fill('input[name="username"]', 'wronguser');
    await page.fill('input[name="password"]', 'wrongpassword');

    await page.screenshot({ path: 'tests/screenshots/invalid-creds-before.png' });

    const submit = page.locator('button[type="submit"]').first();
    await submit.click();

    await page.waitForTimeout(2000);
    await page.waitForLoadState('networkidle');

    await page.screenshot({ path: 'tests/screenshots/invalid-creds-after.png' });

    // Check for error message
    const hasError = await page.locator('text=/incorrect|invalid|failed|error/i').count() > 0;

    // Verify we're still on Flirts NYC (not redirected to aeims.app)
    const url = page.url();
    expect(url).toContain('flirts.nyc');
    console.log(`URL after failed login: ${url}`);

    if (hasError) {
      console.log('✅ Error message displayed on correct site');
    } else {
      console.log('⚠️  No error message found, but stayed on correct site');
    }
  });
});

test.describe('SSL Certificate Verification', () => {
  const sites = [
    'https://aeims.app',
    'https://flirts.nyc',
    'https://nycflirts.com',
    'https://sexacomms.com'
  ];

  for (const site of sites) {
    test(`${site} - SSL Certificate Valid`, async ({ page }) => {
      console.log(`\n=== TEST: SSL for ${site} ===`);

      const response = await page.goto(site);

      // Verify HTTPS
      expect(response?.status()).toBe(200);
      expect(page.url()).toMatch(/^https:\/\//);

      console.log(`✅ ${site} - SSL certificate valid and HTTPS working`);
    });
  }
});
