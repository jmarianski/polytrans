# Contributing to PolyTrans

## Prerequisites

- PHP 7.4+, WordPress, Polylang
- Composer, Git
- Docker (recommended for development)

## Setup

```bash
cd /wp-content/plugins/
git clone [repository-url] polytrans
cd polytrans
make setup  # Docker environment
composer install
```

See [DEVELOPMENT_SETUP.md](DEVELOPMENT_SETUP.md) for details.

## Code Standards

**Follow WordPress Coding Standards:**

```php
// Classes: PolyTrans_Class_Name
// Functions: polytrans_function_name()
// Variables: $variable_name
```

**PHPDoc required for all public methods:**

```php
/**
 * Brief description.
 *
 * @param int $post_id Post ID.
 * @return bool Success status.
 */
public function method_name($post_id) {}
```

**Run checks:**
```bash
composer run phpcs      # Check standards
composer run phpcbf     # Auto-fix
composer run test       # Run tests
```

## Workflow

1. **Create issue first** - Describe the bug/feature
2. **Fork and branch** - `git checkout -b fix/issue-123`
3. **Commit format** - `type(scope): description` (e.g., `fix(workflow): resolve context bug`)
4. **Add tests** - Required for new features
5. **Update docs** - If changing user-facing features
6. **Create PR** - Reference the issue number

## Commit Types

- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation
- `refactor`: Code refactoring
- `test`: Tests

## Testing

```bash
composer run test           # All tests
composer run test-coverage  # With coverage
```

Test structure:
```php
class Test_Feature extends WP_UnitTestCase {
    public function test_feature() {
        // Arrange
        $data = $this->create_test_data();
        // Act
        $result = $this->execute($data);
        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

## Pull Requests

**Before submitting:**
- [ ] Tests pass
- [ ] Follows code standards
- [ ] Documentation updated
- [ ] No merge conflicts

**Template:**
```markdown
## Description
Brief description

## Fixes
Fixes #123

## Changes
- Change 1
- Change 2

## Testing
How you tested this
```

## Questions?

- GitHub Issues for bugs/features
- See [ARCHITECTURE.md](ARCHITECTURE.md) for codebase overview
