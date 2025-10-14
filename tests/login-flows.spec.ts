import { test, expect } from '@playwright/test';

/**
 * Login Flow Tests for AEIMS Platform
 * Tests all authentication flows across different sites
 */

// Test credentials
const ADMIN_CREDS = {
  username: 'admin',
  password: 'admin123',
};

const CUSTOMER_FLIRTS_CREDS = {
  username: 'flirtyuser',
  password: 'password123',
};

const CUSTOMER_NYC_CREDS = {
  username: 'nycuser',
  password: 'password123',
};

test.describe('AEIMS Admin Login', () => {
  test('should login successfully as admin on aeims.app', async ({ page }) => {
    // Go to login page
    await page.goto('https://aeims.app/login.php');

    // Fill in credentials
    await page.fill('input[name="username"]', ADMIN_CREDS.username);
    await page.fill('input[name="password"]', ADMIN_CREDS.password);

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for navigation
    await page.waitForLoadState('networkidle');

    // Should redirect to admin dashboard
    expect(page.url()).toContain('admin-dashboard.php');

    // Check for admin dashboard content
    const content = await page.textContent('body');
    expect(content).toContain('Admin');
  });

  test('should show error for invalid admin credentials', async ({ page }) => {
    await page.goto('https://aeims.app/login.php');

    await page.fill('input[name="username"]', 'admin');
    await page.fill('input[name="password"]', 'wrongpassword');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should stay on login page and show error
    expect(page.url()).toContain('login.php');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/invalid|error|failed/);
  });
});

test.describe('Flirts.nyc Customer Login', () => {
  test('should login successfully as customer on flirts.nyc', async ({ page }) => {
    // Go to login page
    await page.goto('https://flirts.nyc/login.php');

    // Fill in credentials
    await page.fill('input[name="username"]', CUSTOMER_FLIRTS_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_FLIRTS_CREDS.password);

    // Submit form
    await page.click('button[type="submit"]');

    // Wait for navigation
    await page.waitForLoadState('networkidle');

    // Should redirect to homepage (/)
    expect(page.url()).toBe('https://flirts.nyc/');

    // Check for logged-in state
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout|dashboard/);
  });

  test('should not have redirect loop on flirts.nyc', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');

    await page.fill('input[name="username"]', CUSTOMER_FLIRTS_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_FLIRTS_CREDS.password);

    // Monitor redirects
    let redirectCount = 0;
    page.on('response', (response) => {
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        redirectCount++;
      }
    });

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    // Should have at most 2 redirects (login -> success -> final page)
    expect(redirectCount).toBeLessThanOrEqual(2);
  });

  test('should show error for invalid customer credentials', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');

    await page.fill('input[name="username"]', 'wronguser');
    await page.fill('input[name="password"]', 'wrongpass');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should stay on login page and show error
    expect(page.url()).toContain('login.php');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/invalid|error|failed/);
  });

  test('should redirect to homepage if already logged in', async ({ page }) => {
    // First login
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CUSTOMER_FLIRTS_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_FLIRTS_CREDS.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Try to access login page again
    await page.goto('https://flirts.nyc/login.php');
    await page.waitForLoadState('networkidle');

    // Should redirect to homepage
    expect(page.url()).toBe('https://flirts.nyc/');
  });
});

test.describe('NYCFlirts.com Customer Login', () => {
  test('should login successfully as customer on nycflirts.com', async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');

    await page.fill('input[name="username"]', CUSTOMER_NYC_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_NYC_CREDS.password);

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Should redirect to homepage
    expect(page.url()).toBe('https://nycflirts.com/');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout|dashboard/);
  });

  test('should not have redirect loop on nycflirts.com', async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');

    await page.fill('input[name="username"]', CUSTOMER_NYC_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_NYC_CREDS.password);

    let redirectCount = 0;
    page.on('response', (response) => {
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        redirectCount++;
      }
    });

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    expect(redirectCount).toBeLessThanOrEqual(2);
  });

  test('should show error for invalid credentials', async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');

    await page.fill('input[name="username"]', 'wronguser');
    await page.fill('input[name="password"]', 'wrongpass');

    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('login.php');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/invalid|error|failed/);
  });
});

test.describe('Session Persistence', () => {
  test('should maintain session across page navigation on flirts.nyc', async ({ page }) => {
    // Login
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CUSTOMER_FLIRTS_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_FLIRTS_CREDS.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Navigate to another page
    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    // Should still be logged in
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout/);
  });
});

test.describe('Chrome Cache Issues', () => {
  test('should not return blank pages after login', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CUSTOMER_FLIRTS_CREDS.username);
    await page.fill('input[name="password"]', CUSTOMER_FLIRTS_CREDS.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Check that the page has content
    const content = await page.textContent('body');
    expect(content).toBeTruthy();
    expect(content!.length).toBeGreaterThan(100);
  });

  test('should have proper cache headers', async ({ page }) => {
    const response = await page.goto('https://flirts.nyc/login.php');
    const cacheControl = response?.headers()['cache-control'];

    // Login pages should not be cached
    expect(cacheControl).toMatch(/no-cache|no-store|must-revalidate/);
  });
});
