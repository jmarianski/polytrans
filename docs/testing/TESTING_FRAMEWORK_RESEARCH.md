# PHP Testing Framework Research & Decision

**Date**: 2025-12-08
**Version**: 1.0
**Status**: Research Complete

---

## Executive Summary

**Recommendation**: **Pest PHP** with SQLite for database isolation

**Rationale**: Modern syntax, excellent WordPress integration, growing adoption (17% in 2025), full PHPUnit compatibility for migration path, built-in architecture testing.

---

## Framework Comparison

### 1. **PHPUnit** (Industry Standard)

**Market Share**: ~50% (2025)

**Pros**:
- ✅ Industry standard since 2004
- ✅ Maximum compatibility (all CI/CD systems)
- ✅ Massive ecosystem and documentation
- ✅ Already in composer.json (`"phpunit/phpunit": "^9.0"`)
- ✅ Familiar to all PHP developers
- ✅ Enterprise-grade stability

**Cons**:
- ❌ Verbose class-based syntax
- ❌ Less readable assertions (`$this->assertEquals()` vs `expect()->toBe()`)
- ❌ No built-in architecture testing
- ❌ No snapshot testing out of box
- ❌ Boilerplate heavy

**Example**:
```php
class WorkflowTest extends TestCase {
    public function test_it_can_create_workflow() {
        $storage = new WorkflowStorageManager();
        $result = $storage->save_workflow([...]);
        $this->assertTrue($result['success']);
        $this->assertEmpty($result['errors']);
    }
}
```

### 2. **Pest PHP** (Modern Alternative)

**Market Share**: ~17% (2025, up 4pp)

**Pros**:
- ✅ Built on top of PHPUnit (full compatibility)
- ✅ Elegant, functional syntax
- ✅ Excellent WordPress integration (via plugins)
- ✅ **Built-in architecture testing** (perfect for Phase 0!)
- ✅ Snapshot testing included
- ✅ Parallel test execution
- ✅ Beautiful console output
- ✅ Growing community momentum
- ✅ Laravel's official choice (shows industry trend)

**Cons**:
- ❌ Newer (2020), less battle-tested than PHPUnit
- ❌ Smaller ecosystem vs PHPUnit
- ❌ Team learning curve if unfamiliar

**Example**:
```php
it('can create workflow', function () {
    $storage = new WorkflowStorageManager();
    $result = $storage->save_workflow([...]);

    expect($result['success'])->toBeTrue()
        ->and($result['errors'])->toBeEmpty();
});
```

### 3. **Codeception** (Full-Stack Framework)

**Market Share**: ~8-10%

**Pros**:
- ✅ Unit, functional, acceptance tests in one
- ✅ Browser automation built-in
- ✅ BDD-style scenarios
- ✅ Good for full-stack testing

**Cons**:
- ❌ Heavier setup
- ❌ Overkill for unit/integration tests
- ❌ Slower execution
- ❌ More complex configuration
- ❌ Less relevant for backend-focused plugin

---

## Decision Matrix

| Criteria | PHPUnit | Pest | Codeception |
|----------|---------|------|-------------|
| **Syntax Elegance** | 3/5 | 5/5 | 4/5 |
| **WordPress Integration** | 4/5 | 5/5 | 3/5 |
| **Learning Curve** | 5/5 | 4/5 | 3/5 |
| **Architecture Testing** | 2/5 | 5/5 | 2/5 |
| **Database Isolation (SQLite)** | 5/5 | 5/5 | 4/5 |
| **CI/CD Integration** | 5/5 | 5/5 | 4/5 |
| **Community Support** | 5/5 | 4/5 | 3/5 |
| **Future-Proof** | 5/5 | 5/5 | 3/5 |
| **Speed** | 4/5 | 5/5 | 3/5 |
| **Total** | 38/45 | **43/45** | 29/45 |

---

## Recommendation: **Pest PHP**

### Why Pest?

1. **Perfect for Our Use Case**:
   - We're building a **modern WordPress plugin** (2025)
   - Need **architecture testing** for Phase 0 (workflows, assistants, Twig integration)
   - SQLite isolation works perfectly with Pest
   - Readable tests = better documentation

2. **Industry Momentum**:
   - Laravel (most popular PHP framework) chose Pest as default
   - 17% adoption in 2025 (up from 13% in 2024)
   - Growing enterprise adoption

3. **Full PHPUnit Compatibility**:
   - Can run PHPUnit tests alongside Pest
   - Easy migration path if needed
   - All PHPUnit plugins work with Pest

4. **Built-in Features We Need**:
   - Architecture testing (verify Twig templates don't call DB directly)
   - Snapshot testing (for workflow output validation)
   - Parallel execution (faster CI/CD)

5. **Developer Experience**:
   - **80% less boilerplate** vs PHPUnit
   - Beautiful, readable tests
   - Faster to write, easier to maintain

### Migration from PHPUnit

Since we already have `phpunit/phpunit: ^9.0` in composer.json, migration is trivial:

```bash
composer require pestphp/pest --dev
composer require pestphp/pest-plugin-wordpress --dev
./vendor/bin/pest --init
```

Existing PHPUnit tests can coexist with Pest tests.

---

## Testing Strategy

### 1. **Unit Tests** (Pest + SQLite)

**Location**: `tests/Unit/`

**Scope**:
- `WorkflowStorageManager` CRUD operations
- Workflow validation logic
- Migration logic
- Hydration/serialization
- Backward compatibility mappings

**Database**: SQLite in-memory (`:memory:`)

**Example Structure**:
```
tests/
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
    └── DatabaseHelper.php (SQLite setup)
```

### 2. **Integration Tests** (Future - Phase 1)

**Location**: `tests/Integration/`

**Scope**:
- Workflow executor with real AI calls (mocked)
- WordPress hook interactions
- Multi-workflow orchestration

**Database**: SQLite file-based (persistent across test run)

### 3. **Architecture Tests** (Pest Built-in)

**Location**: `tests/Architecture/ArchitectureTest.php`

**Scope**:
- Verify no direct DB calls in templates
- Ensure proper namespace structure
- Check dependency rules (e.g., controllers can't access DB directly)

**Example**:
```php
test('workflows should not access database directly')
    ->expect('PolyTrans\Workflows')
    ->not->toUse('wpdb');

test('Twig templates should not contain PHP logic')
    ->expect('templates/*.twig')
    ->not->toContain('<?php');
```

---

## Implementation Plan

### Phase 0: Setup (2 hours)

1. **Install Pest**:
   ```bash
   composer require pestphp/pest --dev
   composer require pestphp/pest-plugin-wordpress --dev
   ```

2. **Create Pest configuration**:
   - `Pest.php` (global test setup)
   - `tests/Helpers/DatabaseHelper.php` (SQLite setup)

3. **Create SQLite bootstrap**:
   - Mock WordPress functions (`get_option`, `update_option`, etc.)
   - Setup in-memory SQLite
   - Table creation helper

### Phase 1: Unit Tests (4 hours)

4. **Write core tests**:
   - `WorkflowStorageManagerTest.php` (CRUD)
   - `WorkflowMigrationTest.php` (wp_options → table)
   - `WorkflowValidationTest.php`
   - `BackwardCompatibilityTest.php`

5. **Target**: 80%+ code coverage on `class-workflow-storage-manager.php`

### Phase 2: Architecture Tests (1 hour)

6. **Define architecture rules**:
   - Namespace structure
   - Dependency constraints
   - Twig template purity

### Phase 3: CI/CD Integration (1 hour)

7. **GitLab CI/CD**:
   - Add `composer test` to pipeline
   - Generate coverage reports
   - Fail on <80% coverage

---

## SQLite vs MySQL in Tests

### Why SQLite for Tests?

| Criteria | SQLite | MySQL |
|----------|--------|-------|
| **Speed** | ⚡ In-memory (milliseconds) | 🐢 Network + disk (seconds) |
| **Isolation** | ✅ Perfect (`:memory:`) | ⚠️ Requires cleanup |
| **Setup** | ✅ Zero config | ❌ Container/service needed |
| **CI/CD** | ✅ Native support | ⚠️ Service dependency |
| **Parallelization** | ✅ Each test = new DB | ❌ Shared DB issues |

### Compatibility Concerns

**WordPress uses MySQL-specific features**:
- ❌ `ON DUPLICATE KEY UPDATE` (MySQL only)
- ⚠️ Different transaction semantics
- ⚠️ Collation differences

**Solution**:
- Use **SQLite pragmas** for MySQL emulation
- Keep integration tests with real MySQL (future)
- Focus unit tests on **business logic**, not SQL specifics

---

## Sources

Research based on:

- [Pest Official Website](https://pestphp.com/)
- [Pest vs PHPUnit: How Pest modernizes PHP testing](https://nabilhassen.com/pest-vs-phpunit)
- [Pest Framework Deep Dive by Algolia](https://www.algolia.com/blog/engineering/pest-a-testing-framework-that-goes-above-and-beyond-phpunit)
- [Difference between PHPUnit and Pest](https://www.shiftdev.nl/posts/difference-between-phpunit-and-pest/)
- [Pest vs PHPUnit: Choosing Your Testing Style (Medium, Oct 2025)](https://medium.com/@annxsa/pest-vs-phpunit-choosing-your-testing-style-de9614fb43c8)
- [Top 5 PHP Testing Frameworks in 2025](https://testautomationtools.dev/top-5-php-testing-frameworks/)
- [Laravel Testing Documentation (v12.x)](https://laravel.com/docs/12.x/testing)
- [The State of PHP 2025 – JetBrains](https://blog.jetbrains.com/phpstorm/2025/10/state-of-php-2025/)

---

## Final Decision & Learnings

### ✅ **DECISION: Pest PHP + Playwright**

**Unit/Integration Tests**: Pest PHP v2.35+
**E2E Tests**: Playwright (not Cypress)

### Why Playwright over Cypress?
- Multi-tab support (critical for WordPress admin)
- Faster execution for WordPress workflows
- Better cross-browser support
- PHP-friendly (can run alongside Pest)

### Critical Lessons from User Feedback

1. **Pin Pest version** (`^2.35` not `^2.0`) - breaking changes risk
2. **Monitor performance** - Pest 2.0 had 4x slowdown issues
3. **Use PhpStorm** - official plugin, best DX
4. **Keep PHPUnit fallback** - don't delete, migration insurance
5. **Mock WordPress manually** - no pest-plugin-wordpress needed
6. **SQLite `:memory:`** - perfect isolation, parallel tests

### Risk Mitigation

**If Pest fails us**:
- PHPUnit stays in composer.json
- Can migrate tests back (1-2 days work)
- Architecture tests portable to PHPUnit

**Success Metrics**:
- Test execution: <30s
- Coverage: >80%
- Zero flaky tests
- Team satisfaction after 2 months

---

## Next Steps

1. ✅ **Decision made**: Pest PHP + Playwright
2. ✅ **Research complete**: User feedback analyzed
3. ✅ **Best practices documented**: TESTING_BEST_PRACTICES.md
4. ⏳ **Install Pest** + pin version
5. ⏳ **Create SQLite test helpers**
6. ⏳ **Write WorkflowStorageManager tests**
7. ⏳ **Setup Playwright for E2E**
8. ⏳ **Add to CI/CD pipeline**

---

**Decision Status**: ✅ **APPROVED WITH SAFEGUARDS**
**Adoption Timeline**: Immediate (Phase 0)
**Review Date**: 2025-03-08 (3 months check-in)
**Rollback Plan**: Documented in TESTING_BEST_PRACTICES.md
