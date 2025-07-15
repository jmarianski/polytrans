# Changelog

All notable changes to the PolyTrans plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added
- Menu directory structure for organized admin interface components
- Background processor for handling translation tasks asynchronously
- Logs manager and logs menu for comprehensive debugging and monitoring
- Settings menu for centralized plugin configuration
- Enhanced core directory with consolidated WordPress integration functionality
- **INTERNATIONALIZATION**: Complete i18n implementation across the entire plugin
  - Standardized text domain to 'polytrans' across all files
  - Added comprehensive wp_localize_script for all JavaScript components
  - Localized all user-facing strings in PHP and JavaScript
  - Added text domain loading in main plugin class
  - Created comprehensive translation strings for:
    - Settings interface (saving, loading, validation messages)
    - Tag translation management (import/export, CRUD operations)
    - User autocomplete functionality
    - Translation scheduler (status messages, confirmations)
    - OpenAI provider integration (API validation, assistant management)
    - Error messages and notifications throughout the plugin

### Changed
- Restructured plugin architecture with dedicated menu directory
- Moved tag translation functionality to menu directory
- Consolidated translation settings in core directory
- Moved translation extension from translator to core directory
- Improved organization of admin interface components

### Deprecated
- N/A

### Removed
- Standalone API directory (functionality integrated into core)
- Separate translator directory (functionality moved to core)
- Separate settings directory (functionality moved to core)

### Fixed
- JavaScript undefined variable `$failed` in translation scheduler
- **SECURITY**: All 43 critical security issues in translation settings (100% resolved)
  - Added proper nonce verification for form processing
  - Fixed $_SERVER request method validation with isset() check
  - Added wp_unslash() for all $_POST data handling
  - Properly escaped output functions (__() and variables)
  - Fixed array sanitization for allowed sources/targets

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
- Reorganized directory structure for better maintainability

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
