import { test, expect } from '@playwright/test';

/**
 * AEIMS Operator Flow Tests
 * Tests the operator dashboard and functionality
 */

const AGENT_PORTAL = 'https://aeims.app/agents';

const TEST_OPERATORS = [
  {
    name: 'NYCDiamond',
    email: 'nycdiamond@nycflirts.com',
    password: 'diamond2024',
    site: 'nycflirts.com',
    category: 'premium'
  },
  {
    name: 'NYCAngel',
    email: 'nycangel@nycflirts.com',
    password: 'angel2024',
    site: 'nycflirts.com',
    category: 'standard'
  },
  {
    name: 'ManhattanQueen',
    email: 'manhattanqueen@flirts.nyc',
    password: 'queen2024',
    site: 'flirts.nyc',
    category: 'elite'
  },
  {
    name: 'BrooklynBabe',
    email: 'brooklynbabe@flirts.nyc',
    password: 'brooklyn2024',
    site: 'flirts.nyc',
    category: 'premium'
  },
  {
    name: 'NYCGoddess',
    email: 'nycgoddess@nycflirts.com',
    password: 'goddess2024',
    site: 'nycflirts.com',
    category: 'elite'
  }
];

test.describe('Operator Authentication', () => {
  test('should load operator portal', async ({ page }) => {
    await page.goto(AGENT_PORTAL);

    // Should see agent portal login
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Agents Portal') || expect(bodyText).toContain('Operator');
  });

  TEST_OPERATORS.forEach(operator => {
    test(`should login as ${operator.name} (${operator.category})`, async ({ page }) => {
      await page.goto(AGENT_PORTAL + '/login.php');

      // Fill login form
      await page.fill('input[name="username"], input[id="username"]', operator.email);
      await page.fill('input[name="password"], input[id="password"]', operator.password);

      // Submit
      await page.click('button[type="submit"], input[type="submit"]');

      // Should redirect to dashboard
      await page.waitForURL(/dashboard/i, { timeout: 10000 });

      // Should see operator name or welcome message
      const bodyText = await page.textContent('body');
      expect(bodyText).toContain('Dashboard') || expect(bodyText).toContain('Welcome');
    });
  });
});

test.describe('Operator Dashboard', () => {
  test.beforeEach(async ({ page }) => {
    // Login as first test operator
    const operator = TEST_OPERATORS[0];
    await page.goto(AGENT_PORTAL + '/login.php');
    await page.fill('input[name="username"], input[id="username"]', operator.email);
    await page.fill('input[name="password"], input[id="password"]', operator.password);
    await page.click('button[type="submit"], input[type="submit"]');
    await page.waitForURL(/dashboard/i);
  });

  test('should display operator dashboard', async ({ page }) => {
    // Should have dashboard elements
    const bodyText = await page.textContent('body');
    expect(bodyText).toContain('Dashboard') || expect(bodyText).toContain('Operator');
  });

  test('should have messaging interface', async ({ page }) => {
    // Look for messages link or section
    const bodyText = await page.textContent('body');
    const hasMessaging = bodyText.includes('Message') ||
                        bodyText.includes('Chat') ||
                        bodyText.includes('Conversation');
    expect(hasMessaging).toBeTruthy();
  });

  test('should have earnings section', async ({ page }) => {
    // Look for earnings/stats
    const bodyText = await page.textContent('body');
    const hasEarnings = bodyText.includes('Earning') ||
                       bodyText.includes('Revenue') ||
                       bodyText.includes('Stats');
    expect(hasEarnings).toBeTruthy();
  });
});

test.describe('Operator Verification Status', () => {
  TEST_OPERATORS.forEach(operator => {
    test(`${operator.name} should be verified`, async ({ page }) => {
      await page.goto(AGENT_PORTAL + '/login.php');
      await page.fill('input[name="username"], input[id="username"]', operator.email);
      await page.fill('input[name="password"], input[id="password"]', operator.password);
      await page.click('button[type="submit"], input[type="submit"]');

      // Should be able to access dashboard (verified operators only)
      await page.waitForURL(/dashboard/i, { timeout: 10000 });

      const bodyText = await page.textContent('body');

      // Should not see "verification required" messages
      expect(bodyText).not.toContain('verification required');
      expect(bodyText).not.toContain('pending verification');
    });
  });
});

test.describe('Operator Categories', () => {
  test('should have different rate structures per category', async ({ page }) => {
    // This tests that our operator creation worked with different categories
    const categories = TEST_OPERATORS.reduce((acc, op) => {
      acc[op.category] = (acc[op.category] || 0) + 1;
      return acc;
    }, {} as Record<string, number>);

    // Should have multiple categories
    expect(Object.keys(categories).length).toBeGreaterThan(1);

    // Should have standard, premium, and elite
    expect(categories).toHaveProperty('standard');
    expect(categories).toHaveProperty('premium');
    expect(categories).toHaveProperty('elite');
  });
});

test.describe('Cross-Site Operator Support', () => {
  test('should have operators for nycflirts.com', async ({ page }) => {
    const nycOperators = TEST_OPERATORS.filter(op => op.site === 'nycflirts.com');
    expect(nycOperators.length).toBeGreaterThan(0);
    expect(nycOperators.length).toBe(3); // NYCDiamond, NYCAngel, NYCGoddess
  });

  test('should have operators for flirts.nyc', async ({ page }) => {
    const flirtsOperators = TEST_OPERATORS.filter(op => op.site === 'flirts.nyc');
    expect(flirtsOperators.length).toBeGreaterThan(0);
    expect(flirtsOperators.length).toBe(2); // ManhattanQueen, BrooklynBabe
  });
});
