# PolyTrans Plugin Development Guide

## Overview
This document outlines the development process, standards, and best practices for the PolyTrans WordPress plugin.

## Development Environment Setup

### Prerequisites
- Docker and Docker Compose
- Git
- (Optional) PHP 7.4+ and Composer for local development

### Quick Start with Docker (Recommended)
```bash
# Clone the repository
git clone https://github.com/your-org/polytrans-wp-plugin.git
cd polytrans-wp-plugin

# Setup development environment (builds containers and installs dependencies)
make setup
# OR
./dev.sh setup
# OR
composer run docker:setup

# Run quality checks
make phpcs          # Check coding standards
make test           # Run tests
make all           # Run all quality checks
```

### Local Development (Alternative)
```bash
# Install PHP dependencies (requires local PHP with required extensions)
composer install

# Install development dependencies
composer install --dev
```

### Available Development Commands

#### Using Make (Easiest)
```bash
make setup          # Setup development environment
make phpcs          # Check coding standards
make phpcbf         # Fix coding standards
make phpmd          # Run mess detector
make test           # Run tests
make coverage       # Run tests with coverage
make shell          # Open development shell
make clean          # Clean up Docker containers
make all           # Run all quality checks
make help          # Show help
```

#### Using Development Script
```bash
./dev.sh setup     # Setup development environment
./dev.sh phpcs     # Check coding standards
./dev.sh phpcbf    # Fix coding standards
./dev.sh phpmd     # Run mess detector
./dev.sh test      # Run tests
./dev.sh coverage  # Run tests with coverage
./dev.sh shell     # Open development shell
./dev.sh clean     # Clean up Docker containers
./dev.sh all       # Run all quality checks
./dev.sh help      # Show help
```

#### Using Composer (Docker)
```bash
composer run docker:setup     # Setup environment
composer run docker:phpcs     # Check standards
composer run docker:phpcbf    # Fix standards
composer run docker:phpmd     # Mess detector
composer run docker:test      # Run tests
composer run docker:coverage  # Coverage report
composer run docker:all       # All checks
```

#### Using Composer (Local - requires proper PHP setup)
```bash
composer run phpcs          # Check standards
composer run phpcbf         # Fix standards
composer run phpmd          # Mess detector
composer run test           # Run tests
composer run test-coverage  # Coverage report
```

## Development Workflow

### 1. Code Standards
We follow WordPress Coding Standards with some modifications:

```bash
# Using Make (recommended)
make phpcs          # Check code standards
make phpcbf         # Auto-fix code standards
make phpmd          # Run PHP Mess Detector

# Using development script
./dev.sh phpcs      # Check code standards
./dev.sh phpcbf     # Auto-fix code standards
./dev.sh phpmd      # Run PHP Mess Detector

# Using Composer (Docker)
composer run docker:phpcs
composer run docker:phpcbf
composer run docker:phpmd

# Using Composer (local - requires proper PHP setup)
composer run phpcs
composer run phpcbf
composer run phpmd
```

### 2. Testing
```bash
# Using Make (recommended)
make test           # Run all tests
make coverage       # Run tests with coverage

# Using development script
./dev.sh test       # Run all tests
./dev.sh coverage   # Run tests with coverage

# Using Composer (Docker)
composer run docker:test
composer run docker:coverage

# Using Composer (local)
composer run test
composer run test-coverage

# Run specific test file (requires shell access)
make shell
./vendor/bin/phpunit tests/TestClassName.php
```

### 3. Git Workflow
- Use feature branches for new features
- Use semantic commit messages
- All PRs must pass CI checks
- Minimum 80% test coverage required

## Architecture Guidelines

### File Organization
```
polytrans/
├── assets/               # Frontend assets (CSS, JS, images)
├── includes/            # PHP classes and core functionality
│   ├── api/            # REST API endpoints
│   ├── core/           # Core WordPress integration
│   ├── providers/      # Translation providers
│   ├── scheduler/      # Translation scheduling
│   ├── settings/       # Admin settings
│   └── receiver/       # Translation receiver system
├── languages/          # Internationalization files
├── tests/              # PHPUnit tests
└── docs/               # Additional documentation
```

### Class Naming Conventions
- Main classes: `PolyTrans_Class_Name`
- Interfaces: `PolyTrans_Interface_Name`
- Traits: `PolyTrans_Trait_Name`
- Abstract classes: `PolyTrans_Abstract_Class_Name`

### Code Organization Principles
1. **Single Responsibility**: Each class should have one reason to change
2. **Dependency Injection**: Use constructor injection for dependencies
3. **Interface Segregation**: Create focused interfaces
4. **Open/Closed Principle**: Open for extension, closed for modification

## Error Handling and Logging

### Logging Levels
Use the built-in logging system instead of direct `error_log()` calls:

```php
// Good
PolyTrans_Logs_Manager::log('Translation started', 'info', ['post_id' => 123]);

// Bad
error_log('[polytrans] Translation started for post 123');
```

### Error Handling
Always handle exceptions gracefully:

```php
try {
    $result = $provider->translate($content, $source_lang, $target_lang);
} catch (PolyTrans_Translation_Exception $e) {
    PolyTrans_Logs_Manager::log('Translation failed: ' . $e->getMessage(), 'error');
    return new WP_Error('translation_failed', $e->getMessage());
}
```

## Security Guidelines

### Input Validation
- Sanitize all input data
- Validate data types and ranges
- Use WordPress sanitization functions

### Nonce Verification
All AJAX requests must verify nonces:

```php
if (!wp_verify_nonce($_POST['_ajax_nonce'], 'polytrans_action')) {
    wp_die('Security check failed');
}
```

### SQL Queries
Use `$wpdb->prepare()` for all custom queries:

```php
$query = $wpdb->prepare(
    "SELECT * FROM {$wpdb->posts} WHERE post_status = %s AND post_type = %s",
    $status,
    $post_type
);
```

## Performance Guidelines

### Database Queries
- Use WordPress caching functions
- Minimize database queries in loops
- Use transients for expensive operations

### Frontend Performance
- Minify CSS and JavaScript in production
- Use CDN for external resources
- Implement lazy loading where appropriate

## Release Process

### Version Numbering
We use Semantic Versioning (SemVer):
- MAJOR.MINOR.PATCH (e.g., 1.2.3)
- Major: Breaking changes
- Minor: New features (backward compatible)
- Patch: Bug fixes (backward compatible)

### Release Checklist
1. Update version numbers in:
   - `polytrans.php` header
   - `composer.json`
   - `CHANGELOG.md`
2. Run full test suite
3. Update documentation
4. Create release tag
5. Deploy to WordPress.org (if applicable)

## Troubleshooting

### Common Issues
1. **Translation stuck**: Check background process logs
2. **JavaScript errors**: Verify nonce and AJAX endpoints
3. **Provider errors**: Check API keys and configuration

### Debug Mode
Enable debug mode in `wp-config.php`:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
define('POLYTRANS_DEBUG', true);
```

## Contributing

### Code Review Process
1. Create feature branch
2. Implement changes with tests
3. Run code quality checks
4. Submit pull request
5. Address review feedback
6. Merge after approval

### Documentation Requirements
- All public methods must have PHPDoc comments
- Complex algorithms need inline comments
- User-facing features need documentation updates
