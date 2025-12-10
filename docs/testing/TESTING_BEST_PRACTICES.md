# Testing Best Practices & Pitfalls - Pest PHP

**Based on**: Real user feedback, GitHub issues, production experiences (2024-2025)

---

## 🚨 Critical: How NOT to Fail with Pest

### 1. **Performance Issues** (Issue #712)

**Problem**: Pest 2.0 was 4x slower than 1.22 (0.5s vs 0.12s)

**Prevention**:
```bash
# Monitor test performance in CI/CD
composer test -- --profile

# Set timeout alerts
# tests/.github/workflows/tests.yml:
- name: Run tests
  run: composer test
  timeout-minutes: 5  # Fail if tests take >5min
```

**Action Items**:
- ✅ Benchmark tests before/after Pest upgrades
- ✅ Use `--parallel` flag for speed
- ✅ Monitor CI/CD times in GitLab
- ✅ If upgrade causes slowdown, **rollback immediately**

---

### 2. **Version Upgrade Strategy**

**Problem**: Breaking changes between v1→v2, v2→v3

**Prevention**:
```json
// composer.json - PIN minor version
{
  "require-dev": {
    "pestphp/pest": "^2.35",  // ✅ Not ^2.0 (too broad)
    "pestphp/pest-plugin-arch": "^2.7"
  }
}
```

**Action Items**:
- ✅ **Never upgrade Pest without reading CHANGELOG**
- ✅ Test upgrades in separate branch first
- ✅ Run full test suite before merging
- ✅ Keep PHPUnit as fallback option (don't remove)

---

### 3. **IDE Support Requirements**

**Problem**: VSCode plugin inferior to PhpStorm

**Prevention**:
- ✅ **Use PhpStorm** for Pest development (official plugin)
- ✅ If VSCode required: Install `Better Pest` extension
- ✅ Configure `.vscode/settings.json`:
  ```json
  {
    "pest.enable": true,
    "pest.files": ["tests/**/*Test.php"],
    "php.suggest.basic": false
  }
  ```

**Action Items**:
- Document IDE setup in `CONTRIBUTING.md`
- Add `.idea/` and `.vscode/` configs to repo
- Test that debugging works (breakpoints in Pest tests)

---

### 4. **Learning Curve Management**

**Problem**: Team fragmentation (PHPUnit vs Pest)

**Prevention**:
- ✅ **Commit to ONE framework** - no mixing PHPUnit/Pest tests
- ✅ Create internal "Pest Cheat Sheet" for PHPUnit devs:
  ```php
  // PHPUnit → Pest translation
  $this->assertEquals(X, Y)     → expect(Y)->toBe(X)
  $this->assertTrue(X)          → expect(X)->toBeTrue()
  $this->assertCount(N, X)      → expect(X)->toHaveCount(N)
  $this->assertInstanceOf(C, X) → expect(X)->toBeInstanceOf(C)
  ```

**Action Items**:
- ✅ Create `docs/PEST_MIGRATION_GUIDE.md`
- ✅ Pair programming sessions for first 3 Pest tests
- ✅ Code review focuses on Pest idioms

---

### 5. **PHP Version Compatibility**

**Problem**: Pest lags behind PHP releases (Issue #1199 - PHP 8.4)

**Prevention**:
```bash
# .gitlab-ci.yml - Test on multiple PHP versions
test:php8.1:
  image: php:8.1
  script: composer test

test:php8.2:
  image: php:8.2
  script: composer test

test:php8.3:
  image: php:8.3
  script: composer test
  allow_failure: true  # New PHP = optional
```

**Action Items**:
- ✅ Don't upgrade PHP if Pest doesn't support it yet
- ✅ Check [Pest Support Policy](https://pestphp.com/docs/support-policy) before PHP upgrade
- ✅ Run tests on lowest AND highest supported PHP version

---

### 6. **Documentation & Google-ability**

**Problem**: Fewer search results than PHPUnit

**Prevention**:
- ✅ **Document EVERYTHING** internally
- ✅ Create `tests/README.md` with examples:
  ```php
  // How to test database operations
  it('migrates workflows from wp_options', function () {
      // Setup
      setupSQLiteDatabase();
      seedLegacyWorkflows();

      // Execute
      $storage->initialize();

      // Assert
      expect(getWorkflowsCount())->toBe(5);
  });
  ```

**Action Items**:
- ✅ Every test file has docblock explaining what it tests
- ✅ Link to Pest docs in comments for non-obvious syntax
- ✅ Create runnable examples in `tests/examples/`

---

### 7. **Ecosystem & Plugin Dependency**

**Problem**: Pest depends on plugins for WordPress/Laravel features

**Prevention**:
```json
// composer.json - Explicit versions
{
  "require-dev": {
    "pestphp/pest": "^2.35",
    "pestphp/pest-plugin-arch": "^2.7"
    // Note: No WordPress plugin (we'll mock WP functions manually)
  }
}
```

**Why no `pest-plugin-wordpress`?**
- Not maintained actively
- Better to mock WordPress functions ourselves (more control)
- SQLite tests don't need real WordPress

**Action Items**:
- ✅ Create `tests/Helpers/WordPressMocks.php`
- ✅ Mock only what we need (`get_option`, `update_option`, `wpdb`)
- ✅ Keep mocks simple and tested

---

### 8. **Parallel Execution** (Critical for CI/CD)

**Problem**: Sequential tests slow down CI/CD

**Prevention**:
```bash
# Run tests in parallel (Pest 2.0+)
composer test -- --parallel

# Configure in phpunit.xml
<phpunit
  parallel="true"
  processes="4">
</phpunit>
```

**Action Items**:
- ✅ Use SQLite `:memory:` for perfect isolation
- ✅ No shared state between tests
- ✅ Each test creates fresh database

---

### 9. **Coverage Requirements**

**Problem**: Low coverage = bugs in production

**Prevention**:
```bash
# Fail CI/CD if coverage <80%
composer test:coverage -- --min=80

# Generate HTML report locally
composer test:coverage
open coverage/index.html
```

**Action Items**:
- ✅ Target 80% coverage for `class-workflow-storage-manager.php`
- ✅ 90% for migration logic (critical)
- ✅ Add coverage badge to README.md

---

### 10. **Rollback Plan**

**Problem**: What if Pest doesn't work for us?

**Prevention**:
```bash
# Keep PHPUnit available
composer require phpunit/phpunit:^9.0 --dev

# Run old PHPUnit tests
composer run test-phpunit
```

**Action Items**:
- ✅ Don't delete `phpunit.xml.dist`
- ✅ Keep PHPUnit in composer.json
- ✅ Document migration back to PHPUnit if needed

---

## 🎯 E2E Testing Strategy

### Cypress Alternative Analysis

| Tool | Type | Pros | Cons |
|------|------|------|------|
| **Cypress** | Browser E2E | Fast, great DX, time-travel | JS-only, no multi-tab |
| **Playwright** | Browser E2E | Multi-browser, multi-tab, fast | Newer, smaller community |
| **Behat** | BDD/E2E | PHP native, Gherkin | Slow, heavy setup |
| **Codeception** | Full-stack | PHP native, DB testing | Overkill for E2E only |

### ✅ **Recommendation: Playwright**

**Why not Cypress?**
- Cypress doesn't support multi-tab workflows
- WordPress admin = multi-tab (editor + preview)
- Playwright faster for WordPress E2E

**Setup**:
```bash
# In separate e2e/ directory
cd /home/jm/projects/trans-info/plugins/polytrans
mkdir -p tests/e2e
cd tests/e2e
npm init -y
npm install @playwright/test --save-dev
npx playwright install
```

**Example E2E Test**:
```javascript
// tests/e2e/workflow-execution.spec.js
import { test, expect } from '@playwright/test';

test('workflow executes on post translation', async ({ page }) => {
  // Login to WordPress admin
  await page.goto('http://localhost:9009/wp-admin');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', 'password');
  await page.click('#wp-submit');

  // Create post with workflow
  await page.goto('http://localhost:9009/wp-admin/post-new.php');
  await page.fill('#title', 'Test Post');

  // Trigger translation
  await page.click('[data-action="translate-post"]');

  // Wait for workflow to complete
  await expect(page.locator('.workflow-status')).toHaveText('Completed');
});
```

---

## 📋 Testing Checklist

### Before Every Commit
- [ ] Run `composer test` locally
- [ ] Check coverage: `composer test:coverage`
- [ ] Run architecture tests: `composer test:arch`
- [ ] Lint code: `composer phpcs`

### Before Every Pest Upgrade
- [ ] Read CHANGELOG thoroughly
- [ ] Test in separate branch
- [ ] Benchmark performance (before/after)
- [ ] Check PHP version compatibility
- [ ] Run full test suite 3x (catch flaky tests)

### Weekly
- [ ] Review test execution times in CI/CD
- [ ] Check for flaky tests (non-deterministic failures)
- [ ] Update test documentation if needed

### Monthly
- [ ] Audit test coverage (aim for 80%+)
- [ ] Review and refactor slow tests
- [ ] Update mocks if WordPress API changed

---

## 🔗 Resources

### Official Docs
- [Pest Documentation](https://pestphp.com/docs)
- [Pest Architecture Testing](https://pestphp.com/docs/arch-testing)
- [Pest Support Policy](https://pestphp.com/docs/support-policy)

### Community
- [Pest GitHub Issues](https://github.com/pestphp/pest/issues)
- [Pest Discussions](https://github.com/pestphp/pest/discussions)
- [Laravel Daily Pest Articles](https://laraveldaily.com/tag/pest)

### Troubleshooting
- [Performance Issues Thread](https://github.com/pestphp/pest/issues/712)
- [Pain Points Compilation](https://github.com/pestphp/pest/issues/33)
- [Arguments Against Pest](https://github.com/pestphp/pest/issues/149)

---

## 📊 Success Metrics

Track these to ensure Pest is working for us:

1. **Test Execution Time**: <30s for full suite
2. **Coverage**: >80% on critical files
3. **Flaky Tests**: 0 (all tests deterministic)
4. **Team Satisfaction**: Survey after 2 months
5. **Bug Detection**: Track bugs caught by tests vs production

If any metric fails for 2+ weeks → Consider rollback to PHPUnit

---

**Last Updated**: 2025-12-08
**Review Date**: 2025-03-08 (3 months)
