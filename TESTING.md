# AEIMS Testing Guide

## Quick Start

### Run All Tests
```bash
npm test
```

### Run Specific Test Suites
```bash
# Customer flow only
npm run test:customer

# Operator flow only
npm run test:operator

# Interactive UI mode (best for debugging)
npm run test:ui

# Watch mode with visible browser
npm run test:headed
```

### View Test Results
```bash
npm run test:report
```

---

## Test Accounts

### Customer
- **Site**: https://nycflirts.com
- **Username**: `nycfun25`
- **Password**: `password`

### Operators

#### NYC Flirts (nycflirts.com)
1. **NYCDiamond** (Premium)
   - Email: `nycdiamond@nycflirts.com`
   - Password: `diamond2024`

2. **NYCAngel** (Standard)
   - Email: `nycangel@nycflirts.com`
   - Password: `angel2024`

3. **NYCGoddess** (Elite)
   - Email: `nycgoddess@nycflirts.com`
   - Password: `goddess2024`

#### Flirts NYC (flirts.nyc)
4. **ManhattanQueen** (Elite)
   - Email: `manhattanqueen@flirts.nyc`
   - Password: `queen2024`

5. **BrooklynBabe** (Premium)
   - Email: `brooklynbabe@flirts.nyc`
   - Password: `brooklyn2024`

---

## Manual Testing Steps

### Customer Flow
1. Go to https://nycflirts.com
2. Click "Login"
3. Enter username: `nycfun25`, password: `password`
4. Verify dashboard loads with operators
5. Click "Search" - should load search page
6. Click "Messages" - should load messages
7. Click "Activity" - should load activity log
8. Verify credits balance is visible
9. Verify navigation links work

### Operator Flow
1. Go to https://aeims.app/agents/login.php
2. Login with any operator credentials above
3. Verify dashboard loads
4. Check for messaging interface
5. Check for earnings section
6. Verify operator is marked as "verified"

---

## Test Coverage

### Customer Tests (13 total)
- ✅ Authentication (login, credits display)
- ✅ Navigation (Search, Messages, Activity links)
- ✅ Operator Discovery (dashboard, search)
- ✅ Messaging System (navigation)
- ✅ Activity Tracking (navigation)
- ✅ Multi-Site Support (both sites work)

### Operator Tests (17 total)
- ✅ Authentication (all 5 operators)
- ✅ Dashboard (display, messaging, earnings)
- ✅ Verification Status (all verified)
- ✅ Category Structure (standard/premium/elite)
- ✅ Cross-Site Support (operators per site)

---

## Debugging Failed Tests

### View Last Test Run
```bash
npm run test:report
```

### Run Specific Test File
```bash
npx playwright test tests/customer-flow.spec.ts
```

### Run Single Test
```bash
npx playwright test -g "should login successfully"
```

### Debug Mode
```bash
npx playwright test --debug
```

### Generate Test Code
```bash
npx playwright codegen https://nycflirts.com
```

---

## CI/CD Integration

### GitHub Actions Example
```yaml
- name: Install dependencies
  run: npm ci

- name: Install Playwright Browsers
  run: npx playwright install --with-deps

- name: Run Playwright tests
  run: npm test

- name: Upload test results
  uses: actions/upload-artifact@v3
  if: always()
  with:
    name: playwright-report
    path: playwright-report/
```

---

## Performance Testing

### Check Site Response Times
```bash
for site in aeims.app nycflirts.com flirts.nyc; do
  curl -s -o /dev/null -w "$site: %{http_code} - %{time_total}s\n" https://$site/
done
```

### Expected Results
- aeims.app: < 0.5s
- nycflirts.com: < 0.2s
- flirts.nyc: < 0.2s

---

## Troubleshooting

### Tests Timing Out
- Increase timeout in `playwright.config.ts`
- Check if sites are accessible
- Verify deployment is stable

### Login Failures
- Verify credentials match test accounts
- Check if session handling works
- Clear browser cache/cookies

### Element Not Found
- Update selectors in test files
- Check if UI has changed
- Use Playwright Inspector: `npx playwright test --debug`

---

## Additional Resources

- [Playwright Documentation](https://playwright.dev)
- [Test Report](./TEST_REPORT.md) - Full deployment details
- [Credentials File](./data/test_operator_credentials.json) - All operator credentials
