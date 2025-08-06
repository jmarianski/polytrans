# Changelog

All notable changes to the PolyTrans plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- **AI-DRIVEN AUTOMATION**: New workflow output actions for intelligent post management
  - `update_post_status` action: AI can set post status based on content analysis (publish, draft, pending, etc.)
  - `update_post_date` action: AI can schedule posts for optimal publishing times
  - Intelligent parsing of various AI response formats (natural language, timestamps, etc.)
  - Comprehensive validation and error handling with helpful format examples
- **WORKFLOW ENHANCEMENT**: Enhanced workflow output processor with robust helper methods
  - `create_change_object()`, `apply_change_to_context()`, `execute_change()`, `refresh_context_from_database()`
  - Better separation between test mode and production execution
  - Improved error handling and logging throughout workflow execution
- **DOCUMENTATION**: Comprehensive documentation structure
  - User-focused documentation in `docs/user-guide/` (Installation, Interface, FAQ)
  - Administrator guides in `docs/admin/` (Configuration, Workflow management)
  - Developer resources in `docs/developer/` (API docs, Contributing guide, Architecture)
  - Practical examples in `docs/examples/` (Blog posts, real-world usage)
  - Technical reference in `docs/reference/` (Task queue, system status)
  - Master documentation index with audience-based navigation
- **USER EXPERIENCE**: New user-focused Quick Start guide (5-minute setup)
- **ORGANIZATION**: Clean documentation structure with proper cross-references

### Fixed
- **WORKFLOW TESTING**: Fixed JavaScript frontend processing of workflow test results
  - Enhanced change objects with display-friendly fields (`action_type`, `target_description`, `current_value`, `new_value`)
  - Fixed field mapping between backend change objects and frontend JavaScript expectations
  - Resolved "No changes" display issue in workflow test results
- **CONTEXT INITIALIZATION**: Fixed empty "BEFORE" values in workflow test mode
  - Added `ensure_context_has_post_data()` method to populate context with actual post data
  - Ensures accurate "BEFORE" values from database instead of empty initial context
  - Improved progressive context updates between workflow steps

### Changed
- **DOCUMENTATION**: Reorganized scattered markdown files into logical structure
- **README**: Enhanced main README.md with professional presentation and clear navigation
- **FILE ORGANIZATION**: Moved technical documentation to appropriate subdirectories

### Removed
- **CLEANUP**: Removed redundant internal development files (AI_PROMPT.MD, various implementation plans)
- **CONSOLIDATION**: Eliminated duplicate documentation files

## [1.2.0] - 2025-07-14

### Added
- ✅ **Menu directory structure** for organized admin interface components (4 menu classes)
- ✅ **Background processor** for handling translation tasks asynchronously (602-line implementation)
- ✅ **Logs manager and logs menu** for comprehensive debugging and monitoring
- ✅ **Settings menu** for centralized plugin configuration
- ✅ **Enhanced core directory** with consolidated WordPress integration functionality
- ✅ **COMPREHENSIVE INTERNATIONALIZATION**: Extensive i18n implementation across the plugin
  - ✅ **233 localized strings** using __() function across the codebase
  - ✅ **890 instances** of 'polytrans' text domain usage
  - ✅ **7 wp_localize_script implementations** for JavaScript components
  - ✅ **Localized user-facing strings** in PHP and JavaScript
  - ✅ **Text domain loading** in main plugin class
  - ✅ **Translation strings for**:
    - Settings interface (saving, loading, validation messages)
    - Tag translation management (import/export, CRUD operations)
    - User autocomplete functionality
    - Translation scheduler (status messages, confirmations)
    - OpenAI provider integration (API validation, assistant management)
    - Error messages and notifications throughout the plugin

### Changed
- **ARCHITECTURE**: Restructured plugin architecture with dedicated menu directory
- **CODE QUALITY**: Moved tag translation functionality to menu directory (verified: commit 46c0f573)
- **FILE ORGANIZATION**: Consolidated translation settings in core directory
- **FILE REORGANIZATION**: Moved translation extension from translator to core directory
- **ADMIN INTERFACE**: Improved organization of admin interface components

### Deprecated
- N/A

### Removed
- **DIRECTORY STRUCTURE**: Standalone API directory (functionality integrated into core)
- **DIRECTORY REORGANIZATION**: Separate translator directory (verified: moved to core/class-translation-extension.php)
- **DIRECTORY REORGANIZATION**: Separate settings directory (verified: moved to core/class-translation-settings.php)
- **CODE CLEANUP**: Dashboard widget functionality (removed from main plugin class)

### Fixed
- ✅ **JavaScript undefined variable fix**: Fixed `$failed` variable definition in translation scheduler
- ✅ **SECURITY**: All 43 critical security issues in translation settings (100% resolved)
  - ✅ **Proper nonce verification** with check_admin_referer() for form processing  
  - ✅ **$_SERVER validation** with isset() check for request method
  - ✅ **wp_unslash() usage** for all $_POST data handling (21+ instances)
  - ✅ **Escaped output functions** using __() and esc_html()
  - ✅ **Array sanitization** with array_map() for allowed sources/targets

### Security
- N/A

## [1.1.0] - 2025-07-14

### Added
- Initial plugin structure and architecture
- Translation provider system with Google Translate and OpenAI integration
- Translation scheduling and management system
- Review workflow with email notifications
- REST API endpoints for external translation services
- Multi-server translation support
- Tag translation management
- Polylang integration

### Changed
- **DIRECTORY STRUCTURE**: Reorganized directory structure for better maintainability (code quality improvements)

### Fixed
- JavaScript undefined variable issues

## [1.0.0] - 2024-07-09

### Added
- Initial release
- Core translation management functionality
- Provider-based translation system
- Translation scheduler with meta box
- REST API for receiving translations
- Email notification system
- Settings management interface
