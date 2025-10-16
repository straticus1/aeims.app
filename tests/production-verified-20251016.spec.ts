import { test, expect } from '@playwright/test';

/**
 * PRODUCTION VERIFIED TEST SUITE - October 16, 2025
 *
 * Tests all authentication flows with VERIFIED working credentials
 * All operator logins tested and confirmed working via curl
 *
 * Image: sha256:44a91991f9686086c0953355ea3b47d77308d5d8bbff6bc6695ebbe6e240d8ad
 * Task: arn:aws:ecs:us-east-1:515966511618:task/aeims-cluster/5b61e18c3eab4eea8ad8f3520fea54a5
 */

// ============================================================================
// VERIFIED CREDENTIALS
// ============================================================================

const VERIFIED_OPERATORS = {
  sarah: {
    email: 'sarah@example.com',
    password: 'demo123',
    name: 'Sarah Johnson',
    phone: '+1-555-0101'
  },
  jessica: {
    email: 'jessica@example.com',
    password: 'demo456',
    name: 'Jessica Williams',
    phone: '+1-555-0102'
  },
  amanda: {
    email: 'amanda@example.com',
    password: 'demo789',
    name: 'Amanda Rodriguez',
    phone: '+1-555-0103'
  }
};

// ============================================================================
// OPERATOR LOGIN TESTS (aeims.app/agents/)
// ============================================================================

test.describe('Operator Authentication - PRODUCTION VERIFIED', () => {

  test('✅ Sarah Johnson (sarah@example.com) login', async ({ page }) => {
    console.log('🔍 Testing Sarah Johnson operator login...');

    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    // Check login page loaded
    expect(page.url()).toContain('login.php');

    // Fill in credentials
    await page.fill('input[name="username"]', VERIFIED_OPERATORS.sarah.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.sarah.password);

    // Submit
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Verify success
    const url = page.url();
    console.log(`📍 Redirected to: ${url}`);

    expect(url).toMatch(/dashboard|operator|agents/);
    expect(url).not.toContain('login.php');

    console.log('✅ Sarah login SUCCESSFUL');
  });

  test('✅ Jessica Williams (jessica@example.com) login', async ({ page }) => {
    console.log('🔍 Testing Jessica Williams operator login...');

    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="username"]', VERIFIED_OPERATORS.jessica.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.jessica.password);

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    const url = page.url();
    console.log(`📍 Redirected to: ${url}`);

    expect(url).toMatch(/dashboard|operator|agents/);
    expect(url).not.toContain('login.php');

    console.log('✅ Jessica login SUCCESSFUL');
  });

  test('✅ Amanda Rodriguez (amanda@example.com) login', async ({ page }) => {
    console.log('🔍 Testing Amanda Rodriguez operator login...');

    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="username"]', VERIFIED_OPERATORS.amanda.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.amanda.password);

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    const url = page.url();
    console.log(`📍 Redirected to: ${url}`);

    expect(url).toMatch(/dashboard|operator|agents/);
    expect(url).not.toContain('login.php');

    console.log('✅ Amanda login SUCCESSFUL');
  });

  test('❌ Invalid operator credentials should fail', async ({ page }) => {
    console.log('🔍 Testing invalid operator credentials...');

    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="username"]', 'invalid@example.com');
    await page.fill('input[name="password"]', 'wrongpassword');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Should stay on login page or show error
    expect(page.url()).toMatch(/login\.php|error/);

    console.log('✅ Invalid credentials correctly rejected');
  });

  test('🔄 Session persistence across navigation', async ({ page }) => {
    console.log('🔍 Testing session persistence...');

    // Login as Sarah
    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    await page.fill('input[name="username"]', VERIFIED_OPERATORS.sarah.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.sarah.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    await page.waitForTimeout(1000);

    // Navigate to another page
    await page.goto('https://aeims.app/agents/');
    await page.waitForLoadState('networkidle');

    // Should still be logged in
    expect(page.url()).not.toContain('login.php');

    console.log('✅ Session persisted across navigation');
  });
});

// ============================================================================
// CUSTOMER SITE TESTS
// ============================================================================

test.describe('Customer Sites - Page Load Tests', () => {

  test('Flirts NYC - homepage loads', async ({ page }) => {
    const response = await page.goto('https://flirts.nyc/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
    expect(content!.length).toBeGreaterThan(100);

    console.log('✅ Flirts NYC homepage loads');
  });

  test('Flirts NYC - login page loads', async ({ page }) => {
    const response = await page.goto('https://flirts.nyc/login.php');
    expect(response?.status()).toBe(200);

    // Check for login form
    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');

    expect(usernameInput || passwordInput).toBeTruthy();

    console.log('✅ Flirts NYC login page loads');
  });

  test('NYC Flirts - homepage loads', async ({ page }) => {
    const response = await page.goto('https://nycflirts.com/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
    expect(content!.length).toBeGreaterThan(100);

    console.log('✅ NYC Flirts homepage loads');
  });

  test('NYC Flirts - login page loads', async ({ page }) => {
    const response = await page.goto('https://nycflirts.com/login.php');
    expect(response?.status()).toBe(200);

    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');

    expect(usernameInput || passwordInput).toBeTruthy();

    console.log('✅ NYC Flirts login page loads');
  });
});

// ============================================================================
// NO REDIRECT LOOPS TEST
// ============================================================================

test.describe('Redirect Loop Prevention', () => {

  test('No redirect loops on operator login', async ({ page }) => {
    let redirectCount = 0;

    page.on('response', (response) => {
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        redirectCount++;
        console.log(`↪️  Redirect ${redirectCount}: ${response.status()} to ${response.headers()['location']}`);
      }
    });

    await page.goto('https://aeims.app/agents/login.php');
    await page.fill('input[name="username"]', VERIFIED_OPERATORS.sarah.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.sarah.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    console.log(`Total redirects: ${redirectCount}`);
    expect(redirectCount).toBeLessThanOrEqual(3);

    console.log('✅ No redirect loops detected');
  });
});

// ============================================================================
// SECURITY TESTS
// ============================================================================

test.describe('Security Tests', () => {

  test('CSRF token present in login form', async ({ page }) => {
    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    const csrfInput = await page.$('input[name="csrf_token"]');
    expect(csrfInput).toBeTruthy();

    const csrfValue = await page.getAttribute('input[name="csrf_token"]', 'value');
    expect(csrfValue).toBeTruthy();
    expect(csrfValue!.length).toBeGreaterThan(16);

    console.log(`✅ CSRF token present (length: ${csrfValue!.length})`);
  });

  test('Session cookie set after login', async ({ page }) => {
    await page.goto('https://aeims.app/agents/login.php');
    await page.fill('input[name="username"]', VERIFIED_OPERATORS.sarah.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.sarah.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    const cookies = await page.context().cookies();
    const sessionCookie = cookies.find(c => c.name === 'AEIMS_SESSION' || c.name.includes('PHPSESSID'));

    expect(sessionCookie).toBeTruthy();
    console.log(`✅ Session cookie set: ${sessionCookie?.name}`);
  });
});

// ============================================================================
// PERFORMANCE TESTS
// ============================================================================

test.describe('Performance Tests', () => {

  test('Login page loads within 5 seconds', async ({ page }) => {
    const startTime = Date.now();
    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');
    const endTime = Date.now();

    const loadTime = endTime - startTime;
    console.log(`⏱️  Load time: ${loadTime}ms`);

    expect(loadTime).toBeLessThan(5000);
    console.log('✅ Page loads within 5 seconds');
  });

  test('Login completes within 10 seconds', async ({ page }) => {
    await page.goto('https://aeims.app/agents/login.php');
    await page.waitForLoadState('networkidle');

    const startTime = Date.now();
    await page.fill('input[name="username"]', VERIFIED_OPERATORS.sarah.email);
    await page.fill('input[name="password"]', VERIFIED_OPERATORS.sarah.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
    const endTime = Date.now();

    const loginTime = endTime - startTime;
    console.log(`⏱️  Login time: ${loginTime}ms`);

    expect(loginTime).toBeLessThan(10000);
    console.log('✅ Login completes within 10 seconds');
  });
});
