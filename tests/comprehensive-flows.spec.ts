import { test, expect } from '@playwright/test';

/**
 * Comprehensive Test Suite for AEIMS Platform
 * Tests all authenticated and non-authenticated flows across all sites:
 * - aeims.app (admin dashboard)
 * - flirts.nyc (customer site)
 * - nycflirts.com (customer site)
 * - sexacomms.com (customer site)
 */

// Test credentials
const CREDENTIALS = {
  admin: {
    username: 'admin',
    password: 'admin123',
  },
  flirts: {
    username: 'flirtyuser',
    password: 'password123',
  },
  nycflirts: {
    username: 'nycuser',
    password: 'password123',
  },
  sexacomms: {
    username: 'sexauser',
    password: 'password123',
  },
  operator: {
    username: 'demo_operator',
    password: 'demo123',
  },
};

// ============================================================================
// AEIMS.APP - ADMIN DASHBOARD TESTS
// ============================================================================

test.describe('aeims.app - Non-Authenticated Flows', () => {
  test('should load homepage without authentication', async ({ page }) => {
    const response = await page.goto('https://aeims.app/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
    expect(content!.length).toBeGreaterThan(100);
  });

  test('should redirect to login if accessing admin dashboard without auth', async ({ page }) => {
    await page.goto('https://aeims.app/admin-dashboard.php');
    await page.waitForLoadState('networkidle');

    // Should redirect to login or show access denied
    const url = page.url();
    expect(url).toMatch(/login|access-denied|unauthorized/i);
  });

  test('should show login page', async ({ page }) => {
    await page.goto('https://aeims.app/login.php');

    // Check for login form elements
    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');
    const submitButton = await page.$('button[type="submit"]');

    expect(usernameInput).toBeTruthy();
    expect(passwordInput).toBeTruthy();
    expect(submitButton).toBeTruthy();
  });
});

test.describe('aeims.app - Admin Authentication', () => {
  test('should login successfully as admin', async ({ page }) => {
    await page.goto('https://aeims.app/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.admin.username);
    await page.fill('input[name="password"]', CREDENTIALS.admin.password);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Should be on admin dashboard
    const url = page.url();
    expect(url).toMatch(/admin-dashboard|dashboard/);
  });

  test('should reject invalid admin credentials', async ({ page }) => {
    await page.goto('https://aeims.app/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.admin.username);
    await page.fill('input[name="password"]', 'wrongpassword');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Should stay on login or show error
    expect(page.url()).toContain('login.php');
  });
});

test.describe('aeims.app - Operator Authentication', () => {
  test('should login successfully as operator', async ({ page }) => {
    await page.goto('https://aeims.app/agents/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.operator.username);
    await page.fill('input[name="password"]', CREDENTIALS.operator.password);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Should redirect to operator dashboard
    const url = page.url();
    expect(url).toMatch(/dashboard|operator/);
  });
});

// ============================================================================
// FLIRTS.NYC - CUSTOMER SITE TESTS
// ============================================================================

test.describe('flirts.nyc - Non-Authenticated Flows', () => {
  test('should load homepage', async ({ page }) => {
    const response = await page.goto('https://flirts.nyc/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
    expect(content!.toLowerCase()).toContain('flirt');
  });

  test('should show login page', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');

    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');

    expect(usernameInput).toBeTruthy();
    expect(passwordInput).toBeTruthy();
  });

  test('should not access protected pages without auth', async ({ page }) => {
    const protectedPages = [
      '/messages.php',
      '/chat.php',
      '/profile.php',
      '/favorites.php',
      '/activity-log.php',
    ];

    for (const pagePath of protectedPages) {
      await page.goto(`https://flirts.nyc${pagePath}`);
      await page.waitForLoadState('networkidle');

      const url = page.url();
      // Should redirect to login or homepage
      expect(url).toMatch(/login|^\/$|\/$/);
    }
  });
});

test.describe('flirts.nyc - Customer Authentication', () => {
  test('should login successfully', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.flirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.flirts.password);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Should redirect to homepage
    expect(page.url()).toBe('https://flirts.nyc/');

    // Should see logged-in content
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout/);
  });

  test('should not have redirect loops', async ({ page }) => {
    let redirectCount = 0;
    page.on('response', (response) => {
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        redirectCount++;
      }
    });

    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.flirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.flirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    expect(redirectCount).toBeLessThanOrEqual(2);
  });

  test('should reject invalid credentials', async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');

    await page.fill('input[name="username"]', 'invaliduser');
    await page.fill('input[name="password"]', 'invalidpass');
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('login.php');
  });
});

test.describe('flirts.nyc - Authenticated Flows', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.flirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.flirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  });

  test('should access search operators page', async ({ page }) => {
    await page.goto('https://flirts.nyc/search-operators.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('search-operators.php');

    // Should show search filters
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/search|operator|filter/);
  });

  test('should see physical attribute filters in search', async ({ page }) => {
    await page.goto('https://flirts.nyc/search-operators.php');
    await page.waitForLoadState('networkidle');

    // Check for gender filter
    const genderSelect = await page.$('select[name="gender"]');
    expect(genderSelect).toBeTruthy();

    // Check for body type filter
    const bodyTypeSelect = await page.$('select[name="body_type"]');
    expect(bodyTypeSelect).toBeTruthy();
  });

  test('should filter operators by gender', async ({ page }) => {
    await page.goto('https://flirts.nyc/search-operators.php');
    await page.waitForLoadState('networkidle');

    // Select female gender
    await page.selectOption('select[name="gender"]', 'female');
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // URL should contain gender filter
    expect(page.url()).toContain('gender=female');
  });

  test('should access messages page', async ({ page }) => {
    await page.goto('https://flirts.nyc/messages.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('messages.php');

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
  });

  test('should access chat page', async ({ page }) => {
    await page.goto('https://flirts.nyc/chat.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('chat.php');
  });

  test('should access profile page', async ({ page }) => {
    await page.goto('https://flirts.nyc/profile.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('profile.php');

    // Should show user profile info
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/profile|account|username/);
  });

  test('should access favorites page', async ({ page }) => {
    await page.goto('https://flirts.nyc/favorites.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('favorites.php');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/favorite|bookmark/);
  });

  test('should access activity log', async ({ page }) => {
    await page.goto('https://flirts.nyc/activity-log.php');
    await page.waitForLoadState('networkidle');

    expect(page.url()).toContain('activity-log.php');
  });

  test('should maintain session across navigation', async ({ page }) => {
    // Navigate to search
    await page.goto('https://flirts.nyc/search-operators.php');
    await page.waitForLoadState('networkidle');

    // Navigate to messages
    await page.goto('https://flirts.nyc/messages.php');
    await page.waitForLoadState('networkidle');

    // Navigate back to homepage
    await page.goto('https://flirts.nyc/');
    await page.waitForLoadState('networkidle');

    // Should still be logged in
    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout/);
  });

  test('should logout successfully', async ({ page }) => {
    await page.goto('https://flirts.nyc/logout.php');
    await page.waitForLoadState('networkidle');

    // Should redirect to homepage or login
    const url = page.url();
    expect(url).toMatch(/login|^\/$|\/$/);

    // Try to access protected page
    await page.goto('https://flirts.nyc/messages.php');
    await page.waitForLoadState('networkidle');

    // Should be redirected back to login/homepage
    expect(page.url()).toMatch(/login|^\/$|\/$/);
  });
});

// ============================================================================
// NYCFLIRTS.COM - CUSTOMER SITE TESTS
// ============================================================================

test.describe('nycflirts.com - Non-Authenticated Flows', () => {
  test('should load homepage', async ({ page }) => {
    const response = await page.goto('https://nycflirts.com/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
  });

  test('should show login page', async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');

    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');

    expect(usernameInput).toBeTruthy();
    expect(passwordInput).toBeTruthy();
  });
});

test.describe('nycflirts.com - Customer Authentication', () => {
  test('should login successfully', async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.nycflirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.nycflirts.password);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    expect(page.url()).toBe('https://nycflirts.com/');

    const content = await page.textContent('body');
    expect(content?.toLowerCase()).toMatch(/welcome|logout/);
  });

  test('should not have redirect loops', async ({ page }) => {
    let redirectCount = 0;
    page.on('response', (response) => {
      if ([301, 302, 303, 307, 308].includes(response.status())) {
        redirectCount++;
      }
    });

    await page.goto('https://nycflirts.com/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.nycflirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.nycflirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle', { timeout: 10000 });

    expect(redirectCount).toBeLessThanOrEqual(2);
  });
});

test.describe('nycflirts.com - Authenticated Flows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('https://nycflirts.com/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.nycflirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.nycflirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  });

  test('should access all protected pages', async ({ page }) => {
    const pages = [
      '/search-operators.php',
      '/messages.php',
      '/chat.php',
      '/profile.php',
      '/favorites.php',
      '/activity-log.php',
    ];

    for (const pagePath of pages) {
      await page.goto(`https://nycflirts.com${pagePath}`);
      await page.waitForLoadState('networkidle');
      expect(page.url()).toContain(pagePath);
    }
  });

  test('should see physical attribute filters', async ({ page }) => {
    await page.goto('https://nycflirts.com/search-operators.php');
    await page.waitForLoadState('networkidle');

    const genderSelect = await page.$('select[name="gender"]');
    expect(genderSelect).toBeTruthy();
  });
});

// ============================================================================
// SEXACOMMS.COM - CUSTOMER SITE TESTS
// ============================================================================

test.describe('sexacomms.com - Non-Authenticated Flows', () => {
  test('should load homepage', async ({ page }) => {
    const response = await page.goto('https://sexacomms.com/');
    expect(response?.status()).toBe(200);

    const content = await page.textContent('body');
    expect(content).toBeTruthy();
  });

  test('should show login page', async ({ page }) => {
    await page.goto('https://sexacomms.com/login.php');
    await page.waitForLoadState('networkidle');

    // Check for login form
    const usernameInput = await page.$('input[name="username"]');
    const passwordInput = await page.$('input[name="password"]');

    expect(usernameInput || passwordInput).toBeTruthy();
  });
});

test.describe('sexacomms.com - Customer Authentication', () => {
  test('should login successfully', async ({ page }) => {
    await page.goto('https://sexacomms.com/login.php');

    await page.fill('input[name="username"]', CREDENTIALS.sexacomms.username);
    await page.fill('input[name="password"]', CREDENTIALS.sexacomms.password);
    await page.click('button[type="submit"]');

    await page.waitForLoadState('networkidle');

    // Should be logged in
    const url = page.url();
    expect(url).not.toContain('login.php');
  });
});

// ============================================================================
// CROSS-SITE TESTS
// ============================================================================

test.describe('Cross-Site Tests', () => {
  test('should not share sessions between sites', async ({ page }) => {
    // Login to flirts.nyc
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.flirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.flirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');

    // Try to access protected page on nycflirts.com
    await page.goto('https://nycflirts.com/messages.php');
    await page.waitForLoadState('networkidle');

    // Should not be authenticated on nycflirts.com
    const url = page.url();
    expect(url).toMatch(/login|^\/$|\/$/);
  });

  test('should have proper cache headers on all sites', async ({ page }) => {
    const sites = [
      'https://aeims.app/login.php',
      'https://flirts.nyc/login.php',
      'https://nycflirts.com/login.php',
      'https://sexacomms.com/login.php',
    ];

    for (const site of sites) {
      const response = await page.goto(site);
      const cacheControl = response?.headers()['cache-control'];

      // Login pages should have cache control
      expect(cacheControl).toBeTruthy();
    }
  });

  test('all sites should return non-empty pages', async ({ page }) => {
    const sites = [
      'https://aeims.app/',
      'https://flirts.nyc/',
      'https://nycflirts.com/',
      'https://sexacomms.com/',
    ];

    for (const site of sites) {
      await page.goto(site);
      await page.waitForLoadState('networkidle');

      const content = await page.textContent('body');
      expect(content).toBeTruthy();
      expect(content!.length).toBeGreaterThan(100);
    }
  });
});

// ============================================================================
// VISUAL/TEXT COLOR TESTS
// ============================================================================

test.describe('Visual Tests', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('https://flirts.nyc/login.php');
    await page.fill('input[name="username"]', CREDENTIALS.flirts.username);
    await page.fill('input[name="password"]', CREDENTIALS.flirts.password);
    await page.click('button[type="submit"]');
    await page.waitForLoadState('networkidle');
  });

  test('text should be white (#ffffff) for visibility', async ({ page }) => {
    await page.goto('https://flirts.nyc/search-operators.php');
    await page.waitForLoadState('networkidle');

    // Check computed style of nav links
    const navLink = await page.$('nav a');
    if (navLink) {
      const color = await navLink.evaluate(el =>
        window.getComputedStyle(el).color
      );

      // rgb(255, 255, 255) is white
      expect(color).toMatch(/rgb\(255,\s*255,\s*255\)/);
    }
  });
});
