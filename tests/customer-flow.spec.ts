import { test, expect } from '@playwright/test';

/**
 * AEIMS Customer Flow Tests
 * Tests the complete customer journey through the platform
 */

const SITES = {
  nycflirts: 'https://nycflirts.com',
  flirts: 'https://flirts.nyc'
};

const TEST_CUSTOMER = {
  username: 'nycfun25',
  password: 'password',
  email: 'nycfun25@nycflirts.com'
};

test.describe('Customer Authentication', () => {
  test('should load homepage', async ({ page }) => {
    await page.goto(SITES.nycflirts);
    await expect(page).toHaveTitle(/NYC Flirts|Flirts/);

    // Should see login/signup options
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Login');
  });

  test('should login successfully as nycfun25', async ({ page }) => {
    await page.goto(SITES.nycflirts);

    // Find and click login link
    await page.click('text=Login');

    // Fill login form
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);

    // Submit form
    await page.click('button[type="submit"], input[type="submit"]');

    // Should redirect to dashboard
    await page.waitForURL(/dashboard|explore/i, { timeout: 10000 });

    // Should see welcome message
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Welcome');
    expect(bodyText).toContain('nycfun25');
  });

  test('should display credits balance', async ({ page }) => {
    // Login first
    await page.goto(SITES.nycflirts);
    await page.click('text=Login');
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);

    // Should see credits display
    const bodyText = await page.textContent('body');
    expect(bodyText).toMatch(/Credits.*\$[\d.]+/);
  });
});

test.describe('Customer Navigation', () => {
  test.beforeEach(async ({ page }) => {
    // Login before each test
    await page.goto(SITES.nycflirts);
    await page.click('text=Login');
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);
  });

  test('should have Search navigation link', async ({ page }) => {
    const searchLink = page.locator('a:has-text("Search")');
    await expect(searchLink).toBeVisible();
  });

  test('should have Messages navigation link', async ({ page }) => {
    const messagesLink = page.locator('a:has-text("Messages")');
    await expect(messagesLink).toBeVisible();
  });

  test('should have Activity navigation link', async ({ page }) => {
    const activityLink = page.locator('a:has-text("Activity")');
    await expect(activityLink).toBeVisible();
  });

  test('should have Add Credits button', async ({ page }) => {
    const creditsButton = page.locator('a:has-text("Add Credits"), button:has-text("Add Credits")');
    await expect(creditsButton).toBeVisible();
  });
});

test.describe('Operator Discovery', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(SITES.nycflirts);
    await page.click('text=Login');
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);
  });

  test('should display operators on dashboard', async ({ page }) => {
    // Should see operator cards
    const bodyText = await page.textContent('body');

    // Check for test operators we created
    const hasOperators = bodyText.includes('Diamond') ||
                        bodyText.includes('Angel') ||
                        bodyText.includes('Goddess') ||
                        bodyText.includes('Queen') ||
                        bodyText.includes('Babe');

    expect(hasOperators).toBeTruthy();
  });

  test('should navigate to operator search', async ({ page }) => {
    await page.click('a:has-text("Search")');
    await page.waitForURL(/search/i);

    // Should have search interface
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Search');
  });
});

test.describe('Messaging System', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(SITES.nycflirts);
    await page.click('text=Login');
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);
  });

  test('should navigate to messages page', async ({ page }) => {
    await page.click('a:has-text("Messages")');
    await page.waitForURL(/messages/i);

    // Should load messages interface
    await expect(page).toHaveURL(/messages/i);
  });
});

test.describe('Activity Tracking', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto(SITES.nycflirts);
    await page.click('text=Login');
    await page.fill('input[name="username"], input[type="text"]', TEST_CUSTOMER.username);
    await page.fill('input[name="password"], input[type="password"]', TEST_CUSTOMER.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);
  });

  test('should navigate to activity log', async ({ page }) => {
    await page.click('a:has-text("Activity")');
    await page.waitForURL(/activity/i);

    // Should load activity log interface
    await expect(page).toHaveURL(/activity/i);
  });
});

test.describe('Multi-Site Support', () => {
  test('should work on flirts.nyc', async ({ page }) => {
    await page.goto(SITES.flirts);
    await expect(page).toHaveTitle(/Flirts/);

    // Should have login option
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Login');
  });

  test('should work on nycflirts.com', async ({ page }) => {
    await page.goto(SITES.nycflirts);
    await expect(page).toHaveTitle(/NYC Flirts|Flirts/);

    // Should have login option
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Login');
  });
});
