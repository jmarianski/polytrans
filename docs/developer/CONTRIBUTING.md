# Contributing to PolyTrans

Thank you for your interest in contributing to PolyTrans! This guide will help you get started with contributing to the project.

## Table of Contents

- [Getting Started](#getting-started)
- [Development Environment](#development-environment)
- [Code Standards](#code-standards)
- [Contribution Workflow](#contribution-workflow)
- [Testing](#testing)
- [Documentation](#documentation)
- [Code Review Process](#code-review-process)
- [Community Guidelines](#community-guidelines)

## Getting Started

### Prerequisites

Before contributing, ensure you have:
- **WordPress Development Environment**: Local WordPress installation
- **PHP 7.4+**: Compatible PHP version
- **Git**: Version control system
- **Composer**: PHP dependency management
- **Node.js & npm**: For frontend assets (if applicable)
- **Code Editor**: VS Code, PHPStorm, or similar

### Understanding the Codebase

PolyTrans follows WordPress plugin best practices:

```
polytrans/
├── assets/                    # CSS, JS, and other static assets
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   └── images/               # Images and icons
├── includes/                 # Core PHP functionality
│   ├── admin/                # Admin interface
│   ├── api/                  # REST API endpoints
│   ├── core/                 # Core classes
│   ├── menu/                 # Admin menu management
│   ├── postprocessing/       # Workflow system
│   ├── providers/            # Translation providers
│   ├── receiver/             # Translation processing
│   └── utils/                # Utility functions
├── languages/                # Translation files
├── tests/                    # Test files
├── docs/                     # Documentation
├── examples/                 # Usage examples
└── polytrans.php            # Main plugin file
```

### Key Concepts

#### Translation Providers
- **Pluggable Architecture**: Easy to add new providers
- **Interface-Based**: All providers implement common interface
- **Settings Integration**: Automatic settings UI generation

#### Workflow System
- **Step-Based Processing**: Modular post-processing steps
- **Context Management**: Data flow between steps
- **Output Actions**: Automated post updates

#### Event System
- **WordPress Hooks**: Standard WordPress action/filter system
- **Custom Events**: PolyTrans-specific events
- **Extensibility**: Third-party integration points

## Development Environment

### Local Setup

1. **Clone the Repository**
   ```bash
   cd /wp-content/plugins/
   git clone [repository-url] polytrans
   cd polytrans
   ```

2. **Quick Setup with Docker** (Recommended)
   ```bash
   # See detailed setup in Development Setup guide
   make setup
   ```

3. **Install Dependencies**
   ```bash
   composer install
   npm install  # if frontend assets exist
   ```

📖 **Detailed setup instructions**: See [Development Setup Guide](DEVELOPMENT_SETUP.md)

4. **Configure Development Environment**
   Add to `wp-config.php`:
   ```php
   define('WP_DEBUG', true);
   define('WP_DEBUG_LOG', true);
   define('POLYTRANS_DEBUG', true);
   define('SCRIPT_DEBUG', true);
   ```

4. **Activate Plugin**
   - Go to WordPress Admin → Plugins
   - Activate PolyTrans
   - Configure basic settings

### Development Tools

#### Code Quality Tools
```bash
# PHP CodeSniffer (WordPress Coding Standards)
composer run phpcs

# PHP Mess Detector
composer run phpmd

# PHPUnit Tests
composer run test

# Fix code style issues
composer run phpcbf
```

#### Build Tools
```bash
# Build frontend assets (if applicable)
npm run build

# Development mode with watching
npm run dev

# Production build
npm run production
```

### Database Setup

PolyTrans creates custom tables:
```sql
-- Translation logs
polytrans_logs

-- Workflow execution history
polytrans_workflow_executions

-- Custom meta storage
polytrans_meta
```

## Code Standards

### PHP Standards

Follow **WordPress Coding Standards** strictly:

#### Naming Conventions
```php
// Classes: PascalCase with prefix
class PolyTrans_Translation_Manager {
    // Methods: snake_case
    public function create_translation() {
        // Variables: snake_case
        $translation_data = [];
    }
}

// Functions: snake_case with prefix
function polytrans_get_translation_status() {
    // Implementation
}

// Constants: UPPER_CASE with prefix
define('POLYTRANS_VERSION', '1.0.0');
```

#### File Structure
```php
<?php
/**
 * Brief file description.
 *
 * @package PolyTrans
 * @subpackage Core
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Class description.
 *
 * @since 1.0.0
 */
class PolyTrans_Example_Class {
    // Implementation
}
```

#### Documentation Standards
Use **PHPDoc** for all functions and classes:
```php
/**
 * Translate a post to target language.
 *
 * @since 1.0.0
 *
 * @param int    $post_id        Post ID to translate.
 * @param string $target_language Target language code.
 * @param array  $options        Optional. Translation options.
 * @return array|WP_Error Translation result or error.
 */
public function translate_post($post_id, $target_language, $options = []) {
    // Implementation
}
```

### JavaScript Standards

Follow **WordPress JavaScript Standards**:

#### ES6+ Features
```javascript
// Use modern JavaScript features
const translatePost = async (postId, targetLanguage) => {
    try {
        const response = await fetch('/wp-json/polytrans/v1/translate', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                post_id: postId,
                target_language: targetLanguage
            })
        });
        
        return await response.json();
    } catch (error) {
        console.error('Translation failed:', error);
        throw error;
    }
};
```

#### jQuery (for WordPress compatibility)
```javascript
// When jQuery is required
(function($) {
    'use strict';
    
    const PolyTrans = {
        init: function() {
            this.bindEvents();
        },
        
        bindEvents: function() {
            $('.translate-button').on('click', this.handleTranslate);
        },
        
        handleTranslate: function(e) {
            e.preventDefault();
            // Implementation
        }
    };
    
    $(document).ready(function() {
        PolyTrans.init();
    });
})(jQuery);
```

### CSS Standards

Follow **WordPress CSS Standards**:

```css
/* Use WordPress-style CSS organization */
.polytrans-container {
    /* Use consistent naming */
    display: flex;
    flex-direction: column;
    gap: 1rem;
}

.polytrans-translation-box {
    /* BEM-style naming for components */
    background: #fff;
    border: 1px solid #ddd;
    border-radius: 4px;
    padding: 1rem;
}

.polytrans-translation-box__header {
    /* Component modifiers */
    font-weight: 600;
    margin-bottom: 0.5rem;
}

.polytrans-translation-box--loading {
    /* State modifiers */
    opacity: 0.6;
    pointer-events: none;
}
```

## Contribution Workflow

### 1. Issue Creation

Before starting work:
1. **Check Existing Issues**: Avoid duplicates
2. **Create Detailed Issue**: Include reproduction steps
3. **Label Appropriately**: Bug, enhancement, question, etc.
4. **Get Confirmation**: Wait for maintainer confirmation

#### Issue Template
```markdown
## Description
Brief description of the issue or feature request.

## Steps to Reproduce (for bugs)
1. Go to...
2. Click on...
3. See error...

## Expected Behavior
What should happen.

## Actual Behavior
What actually happens.

## Environment
- WordPress Version:
- PHP Version:
- PolyTrans Version:
- Active Plugins:

## Additional Context
Any other relevant information.
```

### 2. Development Process

#### Fork and Branch
```bash
# Fork the repository on GitHub
# Clone your fork
git clone https://github.com/YOUR_USERNAME/polytrans.git
cd polytrans

# Create feature branch
git checkout -b feature/issue-123-description
```

#### Development Guidelines
- **Single Purpose**: One feature/fix per branch
- **Small Commits**: Logical, atomic commits
- **Clear Messages**: Descriptive commit messages
- **Test Coverage**: Add tests for new functionality

#### Commit Message Format
```
type(scope): brief description

Longer description if needed

Fixes #123
```

**Types:**
- `feat`: New feature
- `fix`: Bug fix
- `docs`: Documentation changes
- `style`: Code style changes
- `refactor`: Code refactoring
- `test`: Test additions/modifications
- `chore`: Maintenance tasks

**Examples:**
```bash
git commit -m "feat(provider): add DeepL translation provider"
git commit -m "fix(workflow): resolve context updating issue"
git commit -m "docs(readme): update installation instructions"
```

### 3. Testing Requirements

#### Unit Tests
Create tests for new functionality:
```php
<?php
/**
 * Test translation functionality.
 */
class Test_Translation extends WP_UnitTestCase {
    
    public function test_post_translation_creation() {
        // Create test post
        $post_id = $this->factory->post->create([
            'post_title' => 'Test Post',
            'post_content' => 'Test content'
        ]);
        
        // Test translation
        $translator = new PolyTrans_Translation_Manager();
        $result = $translator->translate_post($post_id, 'es');
        
        // Assertions
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertTrue($result['success']);
    }
}
```

#### Integration Tests
Test component interactions:
```php
<?php
/**
 * Test workflow integration.
 */
class Test_Workflow_Integration extends WP_UnitTestCase {
    
    public function test_translation_with_workflow() {
        // Setup test data
        // Execute translation with workflow
        // Verify workflow execution
        // Check final results
    }
}
```

#### Manual Testing
- Test all affected functionality
- Verify in different environments
- Check for regression issues
- Test error conditions

### 4. Pull Request Process

#### Pre-Submission Checklist
- [ ] All tests pass
- [ ] Code follows standards
- [ ] Documentation updated
- [ ] No merge conflicts
- [ ] Feature complete

#### Pull Request Template
```markdown
## Description
Brief description of changes made.

## Related Issue
Fixes #123

## Type of Change
- [ ] Bug fix
- [ ] New feature
- [ ] Documentation update
- [ ] Performance improvement
- [ ] Code refactoring

## Testing
- [ ] Unit tests added/updated
- [ ] Manual testing completed
- [ ] All existing tests pass

## Screenshots (if applicable)
Add screenshots for UI changes.

## Checklist
- [ ] Code follows project standards
- [ ] Self-review completed
- [ ] Documentation updated
- [ ] No breaking changes (or clearly documented)
```

#### Review Process
1. **Automated Checks**: CI/CD pipeline runs
2. **Code Review**: Maintainer reviews code
3. **Testing**: Manual testing if needed
4. **Feedback**: Address review comments
5. **Approval**: Final approval from maintainer
6. **Merge**: Maintainer merges the PR

## Testing

### Running Tests

#### Full Test Suite
```bash
# Run all tests
composer run test

# Run specific test class
./vendor/bin/phpunit tests/test-translation.php

# Run with coverage
composer run test-coverage
```

#### Test Categories
- **Unit Tests**: Individual function testing
- **Integration Tests**: Component interaction testing
- **API Tests**: REST endpoint testing
- **UI Tests**: Interface functionality testing

### Writing Tests

#### Test Structure
```php
<?php
class Test_Feature extends WP_UnitTestCase {
    
    public function setUp(): void {
        parent::setUp();
        // Test setup
    }
    
    public function tearDown(): void {
        // Test cleanup
        parent::tearDown();
    }
    
    public function test_feature_functionality() {
        // Arrange
        $test_data = $this->create_test_data();
        
        // Act
        $result = $this->execute_feature($test_data);
        
        // Assert
        $this->assertEquals($expected, $result);
    }
}
```

#### Testing Best Practices
- **Descriptive Names**: Clear test method names
- **Single Responsibility**: One concept per test
- **Arrange-Act-Assert**: Clear test structure
- **Test Data**: Use factories for consistent test data
- **Edge Cases**: Test boundary conditions and errors

## Documentation

### Code Documentation

#### Inline Comments
```php
// Explain complex logic
if ($this->should_process_translation($post)) {
    // Process translation only for published posts
    // that haven't been translated yet
    $result = $this->process_translation($post);
}
```

#### Function Documentation
```php
/**
 * Process translation workflow for a post.
 *
 * Executes the configured workflow steps in sequence,
 * updating the post content based on step outputs.
 *
 * @since 1.2.0
 *
 * @param int   $post_id     Post ID to process.
 * @param array $workflow    Workflow configuration.
 * @param bool  $test_mode   Whether to run in test mode.
 * @return array {
 *     Workflow execution result.
 *
 *     @type bool   $success     Whether workflow succeeded.
 *     @type array  $steps       Executed step results.
 *     @type string $message     Result message.
 *     @type array  $errors      Any errors encountered.
 * }
 */
public function execute_workflow($post_id, $workflow, $test_mode = false) {
    // Implementation
}
```

### User Documentation

#### Update Relevant Guides
When making changes, update:
- **README.md**: If core functionality changes
- **User Guides**: If interface changes
- **API Documentation**: If API changes
- **Configuration Guide**: If settings change

#### Documentation Standards
- **Clear Language**: Write for your audience
- **Examples**: Provide practical examples
- **Screenshots**: Include for UI changes
- **Links**: Cross-reference related sections

## Code Review Process

### For Contributors

#### Self-Review Checklist
Before requesting review:
- [ ] Code is clean and well-commented
- [ ] All tests pass
- [ ] Documentation is updated
- [ ] No debug code remains
- [ ] Performance implications considered

#### Responding to Feedback
- **Be Receptive**: Welcome constructive feedback
- **Ask Questions**: Clarify unclear feedback
- **Make Changes**: Address valid concerns
- **Explain Decisions**: Justify design choices when needed

### For Reviewers

#### Review Focus Areas
- **Functionality**: Does it work as intended?
- **Code Quality**: Is it maintainable?
- **Performance**: Any performance implications?
- **Security**: Any security concerns?
- **Standards**: Follows project standards?

#### Feedback Guidelines
- **Be Constructive**: Suggest improvements
- **Be Specific**: Point to exact issues
- **Be Kind**: Maintain positive tone
- **Explain Why**: Help contributor learn

## Community Guidelines

### Communication

#### Be Respectful
- Treat all contributors with respect
- Value diverse perspectives
- Assume good intentions

#### Be Helpful
- Help new contributors get started
- Share knowledge and experience
- Provide constructive feedback

#### Be Patient
- Remember everyone is learning
- Take time to explain concepts
- Allow time for responses

### Collaboration

#### Work Together
- Collaborate on complex features
- Share knowledge and best practices
- Help each other solve problems

#### Communicate Clearly
- Be clear in issues and comments
- Ask questions when uncertain
- Document decisions and rationale

### Recognition

Contributors are recognized through:
- **Changelog Credits**: Listed in release notes
- **Contributors File**: Listed in CONTRIBUTORS.md
- **GitHub Credits**: Automatic GitHub contribution tracking

## Getting Help

### Resources
- **[Architecture Documentation](ARCHITECTURE.md)**: Understanding the codebase
- **[API Documentation](API-DOCUMENTATION.md)**: API reference
- **[Development Setup](DEVELOPMENT_SETUP.md)**: Complete dev environment setup
- **[Code Quality Status](CODE_QUALITY_STATUS.md)**: Current development status

### Support Channels
- **GitHub Issues**: Technical questions and bug reports
- **GitHub Discussions**: General questions and ideas
- **Development Chat**: Real-time development discussion (if available)

### Mentorship
New contributors can get help from experienced contributors:
- **Pair Programming**: Work together on features
- **Code Reviews**: Learn from feedback
- **Office Hours**: Regular Q&A sessions (if available)

---

*Thank you for contributing to PolyTrans! Your contributions help make multilingual WordPress content management better for everyone.*
