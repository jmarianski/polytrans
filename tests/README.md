# PolyTrans Test Suite

This directory contains comprehensive test scripts for validating the functionality of the PolyTrans plugin.

## Test Files

### Core Feature Tests
- **`test-attribution-user.php`** - Tests the workflow attribution user feature
- **`test-post-attribution.php`** - Tests post creation author attribution
- **`test-context-updating.php`** - Tests workflow context updating between steps
- **`test-actions-count.php`** - Tests action counting in workflow execution
- **`test-workflow-output.php`** - Tests complete workflow execution and output processing

### Translation System Tests
- **`test-articles-provider.php`** - Tests the articles translation provider
- **`test-provider-system.php`** - Tests the provider system architecture
- **`test-provider-structure.php`** - Tests provider structure validation

### Workflow System Tests
- **`test-workflow-logging.php`** - Tests workflow logging functionality
- **`test-consolidated-logging.php`** - Tests consolidated logging system

### Content Processing Tests
- **`test-interpolation-wp.php`** - Tests WordPress content interpolation
- **`test-interpolation-debug.php`** - Tests interpolation debugging
- **`test-interpolation-fix.php`** - Tests interpolation fixes
- **`test-creation-revisions.php`** - Tests post creation and revision handling

### Utility Tests
- **`test-regex-pattern.php`** - Tests regex pattern matching
- **`test-polytrans.php`** - General plugin functionality tests

## Running Tests

### Individual Test Execution
Tests can be run individually by accessing them through the web interface:
```
http://yoursite.com/wp-content/plugins/polytrans/tests/test-attribution-user.php
```

### Test Requirements
- WordPress installation with PolyTrans plugin activated
- Debug mode enabled (recommended)
- Proper user permissions for administrative functions

## Test Coverage

### User Attribution System
- Workflow attribution user configuration
- User context switching during workflow execution
- Post creation author preservation
- Revision handling with correct attribution

### Workflow Engine
- Multi-step workflow execution
- Context updating between steps
- Action counting and reporting
- Output processing and database updates

### Translation System
- Provider system architecture
- Content interpolation and processing
- Post creation and metadata handling
- Taxonomy and language management

### Logging and Debugging
- Consolidated logging system
- Workflow execution tracking
- Error handling and validation
- Debug output and audit trails

## Test Structure

Most tests follow this pattern:
1. **Setup**: Initialize test environment and data
2. **Execution**: Run the functionality being tested
3. **Validation**: Check results and output
4. **Cleanup**: Reset state if necessary
5. **Report**: Display results and logs

## Development Guidelines

### Adding New Tests
1. Create test file in `/tests/` directory
2. Follow existing naming convention: `test-feature-name.php`
3. Include comprehensive validation and error handling
4. Add documentation comments for test purpose
5. Update this README with test description

### Test Best Practices
- Test both success and failure scenarios
- Include edge cases and boundary conditions
- Use proper WordPress hooks and filters
- Clean up after tests to avoid side effects
- Provide clear output and logging

## Debugging

### Enable Debug Mode
Add to wp-config.php:
```php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

### Check Logs
- WordPress Debug Log: `/wp-content/debug.log`
- PolyTrans Logs: Database table `polytrans_logs`
- Test Output: Direct browser output

### Common Issues
- Ensure proper user permissions
- Check plugin activation status
- Verify database connectivity
- Validate test data setup

## Support

For issues with specific tests or to report bugs found during testing, please contact the development team.

---

*Last updated: December 2024*
