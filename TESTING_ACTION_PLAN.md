# Testing Implementation Action Plan

**Status**: Ready to Execute
**Updated**: 2025-12-08
**Timeline**: 2-3 days for Unit Tests, 1-2 days for E2E setup

---

## 🎯 Immediate Next Steps (Priority Order)

### ✅ **COMPLETED**
1. ✅ Research testing frameworks (Pest, PHPUnit, Behat, Codeception)
2. ✅ Analyze user feedback and issues
3. ✅ Document best practices (TESTING_BEST_PRACTICES.md)
4. ✅ Choose Pest + Playwright
5. ✅ Update composer.json with Pest dependencies

### 🚀 **PHASE 1: Pest Setup** (2-3 hours)

#### Step 1.1: Install Dependencies
```bash
cd /home/jm/projects/trans-info/plugins/polytrans

# Install Pest (pinned version)
composer require pestphp/pest:^2.35 --dev
composer require pestphp/pest-plugin-arch:^2.7 --dev

# Verify installation
./vendor/bin/pest --version
```

#### Step 1.2: Initialize Pest
```bash
# Create Pest.php config
./vendor/bin/pest --init

# This creates:
# - tests/Pest.php (global test configuration)
# - phpunit.xml (if not exists)
```

#### Step 1.3: Update phpunit.xml
```xml
<!-- phpunit.xml -->
<phpunit
    bootstrap="tests/bootstrap.php"
    colors="true"
    parallel="true"
    processes="4">

    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Architecture">
            <directory>tests/Architecture</directory>
        </testsuite>
    </testsuites>

    <coverage>
        <include>
            <directory suffix=".php">./includes/postprocessing/managers/</directory>
        </include>
    </coverage>
</phpunit>
```

#### Step 1.4: Create Directory Structure
```bash
mkdir -p tests/{Unit,Architecture,Fixtures,Helpers}

# Create test structure
tests/
├── Pest.php              # Global config
├── bootstrap.php         # WordPress mocks, SQLite setup
├── Unit/
│   ├── WorkflowStorageManagerTest.php
│   ├── WorkflowMigrationTest.php
│   ├── WorkflowValidationTest.php
│   └── BackwardCompatibilityTest.php
├── Architecture/
│   └── ArchitectureTest.php
├── Fixtures/
│   └── sample-workflows.json
└── Helpers/
    ├── DatabaseHelper.php      # SQLite setup
    └── WordPressMocks.php      # WP function mocks
```

---

### 🧪 **PHASE 2: SQLite Test Infrastructure** (3-4 hours)

#### Step 2.1: Create DatabaseHelper
```php
// tests/Helpers/DatabaseHelper.php
<?php

namespace PolyTrans\Tests\Helpers;

use PDO;

class DatabaseHelper
{
    private static ?PDO $pdo = null;

    /**
     * Get SQLite in-memory connection
     */
    public static function getConnection(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO('sqlite::memory:');
            self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        }

        return self::$pdo;
    }

    /**
     * Create workflows table (MySQL → SQLite compatible)
     */
    public static function createWorkflowsTable(PDO $pdo): void
    {
        $sql = "
            CREATE TABLE IF NOT EXISTS wp_polytrans_workflows (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                workflow_id VARCHAR(255) NOT NULL UNIQUE,
                name VARCHAR(255) NOT NULL,
                description TEXT,
                language VARCHAR(10) NOT NULL,
                enabled INTEGER DEFAULT 1,
                triggers TEXT NOT NULL,
                steps TEXT NOT NULL,
                output_actions TEXT NOT NULL,
                attribution_user_id INTEGER,
                created_at TEXT NOT NULL,
                updated_at TEXT NOT NULL,
                created_by INTEGER
            )
        ";

        $pdo->exec($sql);

        // Create indexes
        $pdo->exec("CREATE INDEX idx_language ON wp_polytrans_workflows(language)");
        $pdo->exec("CREATE INDEX idx_enabled ON wp_polytrans_workflows(enabled)");
        $pdo->exec("CREATE INDEX idx_name ON wp_polytrans_workflows(name)");
    }

    /**
     * Seed test workflows
     */
    public static function seedWorkflows(PDO $pdo, array $workflows): void
    {
        $stmt = $pdo->prepare("
            INSERT INTO wp_polytrans_workflows
            (workflow_id, name, description, language, enabled, triggers, steps, output_actions, created_at, updated_at)
            VALUES (:id, :name, :desc, :lang, :enabled, :triggers, :steps, :actions, :created, :updated)
        ");

        foreach ($workflows as $wf) {
            $stmt->execute([
                'id' => $wf['id'],
                'name' => $wf['name'],
                'desc' => $wf['description'] ?? '',
                'lang' => $wf['language'],
                'enabled' => $wf['enabled'] ? 1 : 0,
                'triggers' => json_encode($wf['triggers']),
                'steps' => json_encode($wf['steps']),
                'actions' => json_encode($wf['output_actions']),
                'created' => date('Y-m-d H:i:s'),
                'updated' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Clean up (for next test)
     */
    public static function reset(): void
    {
        self::$pdo = null;
    }
}
```

#### Step 2.2: Create WordPressMocks
```php
// tests/Helpers/WordPressMocks.php
<?php

namespace PolyTrans\Tests\Helpers;

/**
 * Mock WordPress functions for testing
 */
class WordPressMocks
{
    private static array $options = [];

    public static function setupMocks(): void
    {
        // Mock get_option
        if (!function_exists('get_option')) {
            function get_option($key, $default = false) {
                return WordPressMocks::getOption($key, $default);
            }
        }

        // Mock update_option
        if (!function_exists('update_option')) {
            function update_option($key, $value) {
                return WordPressMocks::updateOption($key, $value);
            }
        }

        // Mock current_time
        if (!function_exists('current_time')) {
            function current_time($format) {
                return date($format);
            }
        }

        // Mock get_current_user_id
        if (!function_exists('get_current_user_id')) {
            function get_current_user_id() {
                return 1; // Test user
            }
        }
    }

    public static function getOption($key, $default = false)
    {
        return self::$options[$key] ?? $default;
    }

    public static function updateOption($key, $value): bool
    {
        self::$options[$key] = $value;
        return true;
    }

    public static function reset(): void
    {
        self::$options = [];
    }
}
```

#### Step 2.3: Update bootstrap.php
```php
// tests/bootstrap.php
<?php

// Autoload Composer
require_once __DIR__ . '/../vendor/autoload.php';

// Load test helpers
require_once __DIR__ . '/Helpers/DatabaseHelper.php';
require_once __DIR__ . '/Helpers/WordPressMocks.php';

// Setup WordPress mocks
PolyTrans\Tests\Helpers\WordPressMocks::setupMocks();

// Define WordPress constants
if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('WPINC')) {
    define('WPINC', 'wp-includes');
}
```

---

### ✍️ **PHASE 3: Write Unit Tests** (4-6 hours)

#### Step 3.1: WorkflowStorageManagerTest.php (CRUD operations)

**File**: `tests/Unit/WorkflowStorageManagerTest.php`

**Tests to write**:
1. ✅ `it('can create workflow')`
2. ✅ `it('can read workflow by id')`
3. ✅ `it('can update workflow')`
4. ✅ `it('can delete workflow')`
5. ✅ `it('can get all workflows')`
6. ✅ `it('can filter workflows by language')`
7. ✅ `it('validates required fields')`
8. ✅ `it('rejects invalid workflow structure')`

#### Step 3.2: WorkflowMigrationTest.php (wp_options → table)

**Tests to write**:
1. ✅ `it('creates table on first initialization')`
2. ✅ `it('migrates workflows from wp_options')`
3. ✅ `it('creates backup before migration')`
4. ✅ `it('sets migration flag after success')`
5. ✅ `it('does not migrate twice')`
6. ✅ `it('handles empty wp_options gracefully')`

#### Step 3.3: BackwardCompatibilityTest.php

**Tests to write**:
1. ✅ `it('maps workflow_id to id field')`
2. ✅ `it('aliases language as target_language')`
3. ✅ `it('decodes JSON fields correctly')`
4. ✅ `it('handles legacy field names')`

#### Step 3.4: WorkflowValidationTest.php

**Tests to write**:
1. ✅ `it('requires workflow id')`
2. ✅ `it('requires workflow name')`
3. ✅ `it('requires language')`
4. ✅ `it('requires at least one step')`
5. ✅ `it('validates step structure')`
6. ✅ `it('detects duplicate step ids')`

---

### 🏛️ **PHASE 4: Architecture Tests** (1-2 hours)

#### Step 4.1: Create ArchitectureTest.php

**File**: `tests/Architecture/ArchitectureTest.php`

```php
<?php

use PolyTrans\Tests\TestCase;

test('WorkflowStorageManager should not use eval')
    ->expect('PolyTrans\Workflow\WorkflowStorageManager')
    ->not->toUse('eval');

test('all managers should be in managers directory')
    ->expect('PolyTrans\Workflow')
    ->toOnlyUse([
        'PolyTrans\Workflow\Managers',
        'PolyTrans\Core',
    ]);

test('workflow classes should not access wpdb directly outside storage manager')
    ->expect('PolyTrans\Workflow')
    ->classes()
    ->excluding('PolyTrans\Workflow\Managers\WorkflowStorageManager')
    ->not->toUse('wpdb');
```

---

### 🎭 **PHASE 5: E2E Testing Setup** (2-3 hours)

#### Step 5.1: Install Playwright

```bash
mkdir -p tests/e2e
cd tests/e2e

# Initialize Node.js project
npm init -y

# Install Playwright
npm install @playwright/test --save-dev
npx playwright install chromium  # Just Chrome for now

# Create config
npx playwright test --init
```

#### Step 5.2: Configure Playwright

**File**: `tests/e2e/playwright.config.js`

```javascript
import { defineConfig } from '@playwright/test';

export default defineConfig({
  testDir: './',
  fullyParallel: false,
  forbidOnly: !!process.env.CI,
  retries: process.env.CI ? 2 : 0,
  workers: 1,

  use: {
    baseURL: 'http://localhost:9009',
    trace: 'on-first-retry',
    screenshot: 'only-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],

  webServer: {
    command: 'echo "WordPress already running on :9009"',
    url: 'http://localhost:9009',
    reuseExistingServer: true,
  },
});
```

#### Step 5.3: Write First E2E Test

**File**: `tests/e2e/workflow-basic.spec.js`

```javascript
import { test, expect } from '@playwright/test';

test('can login to WordPress admin', async ({ page }) => {
  await page.goto('/wp-admin');

  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD);
  await page.click('#wp-submit');

  await expect(page).toHaveURL(/wp-admin/);
  await expect(page.locator('#wpadminbar')).toBeVisible();
});

test('workflow menu is accessible', async ({ page }) => {
  // Login first
  await page.goto('/wp-admin');
  await page.fill('#user_login', 'admin');
  await page.fill('#user_pass', process.env.WP_ADMIN_PASSWORD);
  await page.click('#wp-submit');

  // Navigate to workflows
  await page.click('text=PolyTrans');
  await page.click('text=Post-processing');

  await expect(page.locator('h1')).toContainText('Post-processing');
});
```

---

### 🔄 **PHASE 6: CI/CD Integration** (1-2 hours)

#### Step 6.1: Update .gitlab-ci.yml

**File**: `.gitlab-ci.yml` (add test stage)

```yaml
stages:
  - test
  - build
  - deploy

variables:
  COMPOSER_CACHE_DIR: "$CI_PROJECT_DIR/.composer-cache"

cache:
  paths:
    - vendor/
    - .composer-cache/

# Unit & Architecture Tests
test:unit:
  stage: test
  image: php:8.1
  before_script:
    - apt-get update && apt-get install -y sqlite3 libsqlite3-dev
    - php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
    - php composer-setup.php --install-dir=/usr/local/bin --filename=composer
    - composer install --prefer-dist --no-progress
  script:
    - composer test:unit
    - composer test:arch
  artifacts:
    reports:
      coverage_report:
        coverage_format: cobertura
        path: coverage.xml

# E2E Tests (manual trigger)
test:e2e:
  stage: test
  when: manual
  image: mcr.microsoft.com/playwright:v1.40.0
  script:
    - cd tests/e2e
    - npm ci
    - npx playwright test
  artifacts:
    when: on_failure
    paths:
      - tests/e2e/test-results/
      - tests/e2e/playwright-report/
```

---

## 📅 Timeline Summary

| Phase | Task | Time | Priority |
|-------|------|------|----------|
| **1** | Pest Setup | 2-3h | 🔥 Critical |
| **2** | SQLite Infrastructure | 3-4h | 🔥 Critical |
| **3** | Write Unit Tests | 4-6h | 🔥 Critical |
| **4** | Architecture Tests | 1-2h | ⚡ High |
| **5** | E2E Setup (Playwright) | 2-3h | 📋 Medium |
| **6** | CI/CD Integration | 1-2h | ⚡ High |
| **TOTAL** | **13-20 hours** | **~2-3 days** | |

---

## ✅ Success Criteria

### Phase 1-3 Complete (Unit Tests)
- [ ] All 25+ unit tests passing
- [ ] Coverage >80% on WorkflowStorageManager
- [ ] Tests run in <10s locally
- [ ] SQLite isolation working (parallel tests)

### Phase 4 Complete (Architecture)
- [ ] 5+ architecture rules defined
- [ ] All rules passing
- [ ] Documented in tests/Architecture/README.md

### Phase 5-6 Complete (E2E + CI/CD)
- [ ] Basic E2E tests passing
- [ ] CI/CD pipeline green
- [ ] Coverage reports generated
- [ ] Tests run on every MR

---

## 🚨 Blockers & Mitigation

| Potential Blocker | Mitigation |
|-------------------|------------|
| Pest installation issues | Use PHPUnit fallback, debug locally first |
| SQLite incompatibility | Document MySQL-specific features, adjust schema |
| WordPress mock complexity | Start simple, add mocks as needed |
| E2E flakiness | Use Playwright auto-wait, increase timeouts |
| CI/CD timeout | Split tests into parallel jobs |

---

## 📚 Documentation Updates Needed

After implementation:

1. **README.md** - Add "Testing" section
2. **CONTRIBUTING.md** - Testing guidelines
3. **tests/README.md** - How to run tests
4. **CHANGELOG.md** - Document test infrastructure

---

**Next Action**: Execute Phase 1 (Pest Setup) → 2-3 hours

**Ready to start?** 🚀
