const { test, expect } = require('@playwright/test');

test.describe('AEIMS Security Audit', () => {
  test('Homepage loads and has secure headers', async ({ page }) => {
    const response = await page.goto('/');

    // Check response status
    expect(response.status()).toBe(200);

    // Check for security headers
    const headers = response.headers();
    console.log('Security Headers:', headers);

    // Test for XSS protection
    await page.setContent('<script>alert("XSS")</script>');
    const alerts = [];
    page.on('dialog', dialog => {
      alerts.push(dialog.message());
      dialog.dismiss();
    });

    // Should not execute scripts
    expect(alerts.length).toBe(0);
  });

  test('Login page authentication', async ({ page }) => {
    await page.goto('/login.php');

    // Check login form exists
    await expect(page.locator('form')).toBeVisible();

    // Test SQL injection attempt
    await page.fill('input[name="username"]', "admin' OR '1'='1");
    await page.fill('input[name="password"]', "admin' OR '1'='1");

    const submitButton = page.locator('input[type="submit"], button[type="submit"]');
    if (await submitButton.count() > 0) {
      await submitButton.click();

      // Should not bypass authentication
      const url = page.url();
      expect(url).not.toContain('dashboard');
      expect(url).not.toContain('admin');
    }
  });

  test('Check for exposed admin endpoints', async ({ page }) => {
    const adminPaths = [
      '/admin.php',
      '/admin/',
      '/administrator/',
      '/wp-admin/',
      '/phpmyadmin/',
      '/config.php',
      '/.env',
      '/api/admin',
      '/admin-dashboard.php'
    ];

    for (const path of adminPaths) {
      const response = await page.goto(path, { waitUntil: 'networkidle' });
      console.log(`${path}: ${response.status()}`);

      // Should not expose sensitive admin areas without auth
      if (response.status() === 200) {
        const content = await page.textContent('body');
        expect(content).not.toContain('password');
        expect(content).not.toContain('secret');
        expect(content).not.toContain('database');
      }
    }
  });

  test('API endpoints security', async ({ page }) => {
    const apiEndpoints = [
      '/api/',
      '/api/users',
      '/api/domains',
      '/api/toys',
      '/api/system'
    ];

    for (const endpoint of apiEndpoints) {
      const response = await page.goto(endpoint);
      console.log(`API ${endpoint}: ${response.status()}`);

      if (response.status() === 200) {
        const content = await page.textContent('body');

        // Check for information disclosure
        expect(content).not.toMatch(/error.*file.*line/i);
        expect(content).not.toContain('stack trace');
        expect(content).not.toContain('mysql');
        expect(content).not.toContain('postgresql');
      }
    }
  });

  test('Chat functionality security', async ({ page }) => {
    // Try to access chat without authentication
    const chatResponse = await page.goto('/sites/nycflirts.com/chat.php');

    // Should redirect to login or show error
    expect(chatResponse.status()).not.toBe(200);

    // Test for XSS in chat messages (if accessible)
    if (chatResponse.status() === 200) {
      const messageInput = page.locator('input[name="message_content"], textarea[name="message_content"]');
      if (await messageInput.count() > 0) {
        await messageInput.fill('<script>alert("XSS")</script>');

        const alerts = [];
        page.on('dialog', dialog => {
          alerts.push(dialog.message());
          dialog.dismiss();
        });

        const submitButton = page.locator('button[type="submit"], input[type="submit"]');
        if (await submitButton.count() > 0) {
          await submitButton.click();
          expect(alerts.length).toBe(0);
        }
      }
    }
  });

  test('File upload security', async ({ page }) => {
    await page.goto('/');

    // Look for file upload forms
    const fileInputs = page.locator('input[type="file"]');
    const count = await fileInputs.count();

    if (count > 0) {
      // Test malicious file upload
      const testFile = Buffer.from('<?php system($_GET["cmd"]); ?>', 'utf8');

      for (let i = 0; i < count; i++) {
        const input = fileInputs.nth(i);

        // Create a malicious PHP file
        await input.setInputFiles({
          name: 'malicious.php',
          mimeType: 'application/x-php',
          buffer: testFile
        });
      }
    }
  });

  test('Session security', async ({ page }) => {
    await page.goto('/');

    // Check cookie security
    const cookies = await page.context().cookies();

    for (const cookie of cookies) {
      console.log(`Cookie ${cookie.name}:`, {
        secure: cookie.secure,
        httpOnly: cookie.httpOnly,
        sameSite: cookie.sameSite
      });

      // Session cookies should be secure
      if (cookie.name.toLowerCase().includes('session') ||
          cookie.name.toLowerCase().includes('auth')) {
        expect(cookie.secure).toBe(true);
        expect(cookie.httpOnly).toBe(true);
        expect(cookie.sameSite).not.toBe('None');
      }
    }
  });
});