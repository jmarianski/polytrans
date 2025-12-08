# Changelog

All notable changes to the PolyTrans plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.2.1] - 2025-12-08

### Fixed
- **USER AUTOCOMPLETE**: Fixed missing AJAX endpoint configuration in settings menu
  - Added `ajaxUrl` and `nonce` to `PolyTransUserAutocomplete` JavaScript object in class-settings-menu.php
  - User search now uses secure custom AJAX endpoint (`polytrans_search_users`) instead of WP REST API fallback
  - Fixes user autocomplete functionality on trans.eu and other installations where WP REST API is blocked by other plugins
  - Ensures consistent behavior across all PolyTrans admin pages (settings and postprocessing menus)

## [1.2.0] - 2025-07-17

### Added
- **POST-PROCESSING WORKFLOWS**: AI-driven automation for intelligent post management
  - Workflow system for running AI assistants on translated content
  - `update_post_status` action: AI can set post status based on content analysis (publish, draft, pending, etc.)
  - `update_post_date` action: AI can schedule posts for optimal publishing times
  - Workflow executor with test mode and production execution
  - Workflow output processor with robust helper methods (`create_change_object`, `apply_change_to_context`, `execute_change`, `refresh_context_from_database`)
  - Variable providers for context data (post data, meta data, articles data)
  - Workflow metabox for manual execution
  - Workflow debug tools and logging
- **MENU STRUCTURE**: Organized admin interface with 4 dedicated menu classes
  - Settings menu (`class-settings-menu.php`)
  - Post-processing menu (`class-postprocessing-menu.php`)
  - Logs menu (`class-logs-menu.php`)
  - Tag translation menu (`class-tag-translation.php`)
- **BACKGROUND PROCESSOR**: Asynchronous task handling for translation jobs
- **LOGS MANAGER**: Comprehensive debugging and monitoring system
- **INTERNATIONALIZATION**: Extensive i18n implementation
  - 233+ localized strings using `__()` function
  - 890+ instances of 'polytrans' text domain usage
  - 7 `wp_localize_script` implementations for JavaScript components
  - Translation strings for all user-facing interfaces

### Changed
- **ARCHITECTURE**: Restructured plugin with dedicated directories (menu/, postprocessing/, receiver/, scheduler/)
- **FILE ORGANIZATION**: Moved translation extension and settings to core directory
- **DOCUMENTATION**: Reorganized docs into structured directories (user-guide/, admin/, developer/, examples/)
- **README**: Enhanced with professional presentation and navigation

### Fixed
- **WORKFLOW TESTING**: Fixed JavaScript frontend processing of workflow test results
  - Enhanced change objects with display-friendly fields (`action_type`, `target_description`, `current_value`, `new_value`)
  - Resolved "No changes" display issue in test results
- **CONTEXT INITIALIZATION**: Fixed empty "BEFORE" values in workflow test mode
  - Added `ensure_context_has_post_data()` method to populate context with actual post data
  - Ensures accurate "BEFORE" values from database
- **SECURITY**: Resolved 43+ critical security issues
  - Proper nonce verification with `check_admin_referer()` for form processing
  - `$_SERVER` validation with isset() checks
  - `wp_unslash()` usage for all $_POST data handling (21+ instances)
  - Escaped output using `__()` and `esc_html()`
  - Array sanitization with `array_map()` for allowed sources/targets
- **JAVASCRIPT**: Fixed undefined variable `$failed` in translation scheduler

### Removed
- **CLEANUP**: Removed redundant internal development files and duplicate documentation
- **DIRECTORY STRUCTURE**: Removed standalone API directory (functionality integrated into core)
- **CODE CLEANUP**: Dashboard widget functionality

## [1.0.0] - 2025-07-09

### Added
- **Initial Release**: Core translation management functionality
- **PROVIDER SYSTEM**: Translation provider architecture
  - Google Translate integration (`class-google-provider.php`)
  - OpenAI integration with custom assistants (`class-openai-provider.php`, `class-openai-client.php`)
  - Provider registry and interface system
- **TRANSLATION SCHEDULER**: Translation scheduling and management
  - Meta box for post editing screen
  - Automatic and manual translation triggering
  - Translation status tracking (pending, processing, completed, failed)
  - Background task queue integration
- **RECEIVER SYSTEM**: REST API for receiving translations from external services
  - Translation coordinator for handling incoming translations
  - Language, media, metadata, taxonomy managers
  - Post creator with validation and security
  - Status and notification management
- **REVIEW WORKFLOW**: Translation review system
  - Reviewer assignment with user autocomplete
  - Email notifications for reviewers
  - Translation attribution tracking
- **TAG TRANSLATION**: Dedicated tag/term translation management
  - Bulk import/export functionality
  - CRUD operations for tag translations
- **CORE FEATURES**:
  - User autocomplete (`class-user-autocomplete.php`)
  - Translation meta box for post editor
  - Translation notifications system
  - Translation settings with multi-server support
  - Polylang integration
- **ADMIN INTERFACE**: Settings page with provider configuration
- **ASSETS**: Admin CSS and JavaScript files for all components

### Technical
- **PHP Version**: 7.4+
- **WordPress Version**: 5.0+
- **Text Domain**: polytrans
- **Plugin Architecture**: Object-oriented with PSR-4-like autoloading
