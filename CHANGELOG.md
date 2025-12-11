# Changelog

All notable changes to the PolyTrans plugin will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.5.0] - 2025-12-10

### 🎉 Major Release: PSR-4 Architecture Migration

This release represents a **complete architectural refactoring** of the PolyTrans plugin to modern PHP standards (PSR-4). All 60+ classes have been migrated to namespaced structure with full backward compatibility.

### Added - PSR-4 Architecture

- **Complete PSR-4 Migration**: All classes now follow PSR-4 autoloading standard
  - Base namespace: `PolyTrans\`
  - Organized into logical modules: `Assistants\`, `Core\`, `Debug\`, `Menu\`, `PostProcessing\`, `Providers\`, `Receiver\`, `Scheduler\`, `Templating\`
  - Zero manual `require_once` statements (except WordPress core)
  - Composer-based autoloading for all classes
  
- **Three-Tier Autoloading System**:
  1. Composer Autoloader - Vendor dependencies (Twig, etc.)
  2. PSR-4 Autoloader - Namespaced classes (`PolyTrans\*`)
  3. LegacyAutoloader - Temporary backward compatibility (empty, will be removed)

- **Backward Compatibility Layer**: 
  - All old class names still work via aliases (e.g., `PolyTrans_Workflow_Manager` → `PolyTrans\PostProcessing\WorkflowManager`)
  - Zero breaking changes for existing code
  - Seamless upgrade path

- **New Directory Structure**:
  ```
  includes/
  ├── Assistants/          # PolyTrans\Assistants\
  ├── Core/                # PolyTrans\Core\
  ├── Debug/               # PolyTrans\Debug\
  ├── Menu/                # PolyTrans\Menu\
  ├── PostProcessing/      # PolyTrans\PostProcessing\
  ├── Providers/           # PolyTrans\Providers\
  ├── Receiver/            # PolyTrans\Receiver\
  ├── Scheduler/           # PolyTrans\Scheduler\
  └── Templating/          # PolyTrans\Templating\
  ```

- **Migrated Modules** (60+ classes):
  - ✅ Assistants (3 classes)
  - ✅ Core (8 classes)
  - ✅ Debug (2 classes)
  - ✅ Menu (5 classes)
  - ✅ PostProcessing (11 classes + 4 interfaces)
  - ✅ Providers (6 classes + 2 interfaces)
  - ✅ Receiver (11 classes)
  - ✅ Scheduler (2 classes)
  - ✅ Templating (1 class)

- **Interface Migration**: All 4 interfaces migrated to PSR-4
  - `TranslationProviderInterface`
  - `SettingsProviderInterface`
  - `WorkflowStepInterface`
  - `VariableProviderInterface`

### Removed - Cleanup

- **Deleted Unused Files**:
  - `includes/core/process-task.php` (not used, BackgroundProcessor creates dynamic scripts)
  - All old lowercase directories (`debug/`, `templating/`, `core/`)
  - All `class-*.php` files (replaced with PSR-4 versions)

### Fixed - PSR-4 Migration

- **Namespace Resolution**: Added leading backslash to all global class references in namespaced files (9 occurrences)
- **Test Compatibility**: Updated all test files to use PSR-4 autoloading
- **Bootstrap Loading**: Fixed interface loading order (interfaces before aliases)
- **Strict Types**: Fixed `declare(strict_types=1)` position in TwigEngine

### Improved - Code Quality

- **Reduced Cognitive Load**: Organized code into logical modules
- **Better Maintainability**: Clear namespace structure makes navigation easier
- **Modern PHP Standards**: Follows PSR-4, PSR-12 coding standards
- **Improved Testing**: All unit tests passing with new structure

### Documentation

- **Updated ARCHITECTURE.md**: Complete rewrite reflecting PSR-4 structure
- **Updated README.md**: All file paths updated to new structure
- **Added PSR-4 Guide**: Documentation of namespace structure and autoloading

### Upgrade Notes

**⚠️ PHP Version Requirement Change**: This release requires **PHP 8.1 or higher** (previously 7.4+).

**Backward Compatibility**:
- **No breaking changes**: All old class names work via aliases
- **No database changes**: Schema remains identical
- **No settings migration**: All options preserved
- **Zero downtime**: Plugin works immediately after update (if PHP 8.1+ is available)

**Requirements**:
- **PHP 8.1+** (breaking change from 7.4+)
- Composer dependencies must be installed (`vendor/autoload.php`)
- For production releases, dependencies are included
- For development installs, run `composer install` after update

**Why PHP 8.1+?**
- PHP 7.4 reached End of Life in November 2022
- Twig 3.14+ requires PHP 8.1+
- Better performance and security
- Modern PHP features (enums, readonly properties, etc.)

### Added
- **Managed Assistants in Translation System**: Integrated Managed Assistants with system translations
  - Grouped dropdown UI showing both Managed and OpenAI API Assistants
  - Assistant type auto-detection (managed_xxx vs asst_xxx)
  - Managed Assistants can now be used for language pair translations
  - Full schema-based parsing with auto-mapping for system translations
  - Scalable structure for future Claude/Gemini integration
  - Example: Select "Translation EN→PL (Managed)" for PL→EN translation pair

- **Complete Translation Schema Examples**: Added comprehensive examples for all SEO fields
  - `translation-schema-full.json`: Complete schema with 14 SEO fields (RankMath + Yoast)
  - `translation-user-message-full.twig`: Dynamic template with meta field loop
  - `TRANSLATION_SCHEMA_GUIDE.md`: Complete setup and customization guide
  - Minified schema for direct paste into UI
  - Zero Output Actions needed with auto-mapping

### Fixed
- **Workflow Test UI**: Added collapsible sections to display interpolated prompts (System Prompt & User Message) sent to AI after Twig variable interpolation
- **Managed Assistant Output**: Fixed output structure to wrap AI response in array format (`{ 'ai_response': '...' }`) so output processor can correctly extract values for saving to meta/content
- **Context Variable Updates**: Fixed `translated.meta` not being updated after `update_post_meta` action, enabling subsequent steps to access meta values via `{{ translated.meta.KEY }}`
- **Workflow Sanitization**: Added missing sanitization case for `managed_assistant` step type, fixing issue where `assistant_id` was stripped during workflow save
- **Assistant Migration**: Fixed critical bugs in workflow migration from `ai_assistant` to `managed_assistant`:
  - Correctly separate system prompt and user message based on delimiter
  - Properly handle WP_Error returns from Assistant_Manager
  - Update workflow array reference (not just local variable) when converting steps
  - Cast API parameters to correct types (int for max_tokens, float for temperature/top_p)

### Improved
- **OpenAI Error Logging**: Detailed error codes and messages for translation failures
  - Now shows specific error codes (rate_limit_exceeded, insufficient_quota, server_error, etc.)
  - Includes human-readable error messages from OpenAI API
  - Structured logging with error_code, error_details, thread_id, run_id
  - **Before**: "OpenAI: step 0 failed (de -> en): Run ended with status: failed"
  - **After**: "OpenAI: step 0 failed (de -> en) [code: rate_limit_exceeded]: Run ended with status: failed - Rate limit reached for requests"
  - Makes debugging translation failures much faster (instantly see if it's rate limiting, insufficient funds, timeout, etc.)
  - See: `docs/ERROR_LOGGING_IMPROVEMENTS.md` for full details and error code reference

### Added (Phase 1 - Complete)
- **AI Assistants Management System**: Complete implementation with Admin UI and Workflow integration
  
  **Backend Infrastructure:**
  - `wp_polytrans_assistants` table for centralized assistant configurations
  - `PolyTrans_Assistant_Manager`: Full CRUD operations (26 unit tests ✅)
  - `PolyTrans_Assistant_Executor`: Execute assistants with Twig variable interpolation (27 unit tests ✅)
  - Support for OpenAI Chat Completions API (Claude and Gemini placeholders)
  - Text and JSON response formats with validation
  - Comprehensive error handling (rate limiting, timeouts, API errors)
  
  **Admin UI (PolyTrans > AI Assistants):**
  - List view with assistant details (name, provider, model, response format, created date)
  - Create/Edit assistant form with:
    - Name, provider (OpenAI/Claude/Gemini), model dropdown with "Use Global Setting" option
    - **Separate editors** for System Instructions and User Message Template
    - Prompt template editor with Twig syntax and variable pills
    - Response format (text/json)
    - Configuration (temperature, max_tokens, top_p)
  - Delete assistant with confirmation
  - Test assistant functionality with sample variables
  - Beautiful, responsive interface matching WordPress admin design
  - Shared CSS/JS components with workflow editor for consistency
  
  **Workflow Integration:**
  - New step type: "Managed AI Assistant" (managed_assistant)
  - Uses assistants configured in Admin UI
  - Automatic Twig variable interpolation from workflow context
  - Dropdown selector showing all available assistants with model info
  - Backward compatible with existing workflow steps
  - AJAX endpoint for loading managed assistants in workflow editor
  - **Automatic Migration**: Existing `ai_assistant` steps are automatically converted to `managed_assistant` on plugin activation/update
  - Migration creates managed assistants from old step configs and updates workflows
  - Manual migration trigger available in AI Assistants admin page
  
  **Benefits:**
  - ✅ Centralized management - configure once, use everywhere
  - ✅ Multi-provider support - OpenAI, Claude, Gemini (extensible)
  - ✅ Twig templates - powerful variable interpolation
  - ✅ Reusable - same assistant in multiple workflows
  - ✅ Testable - test assistants before using in production
  - ✅ Maintainable - update assistant prompts without editing workflows

## [1.3.5] - 2025-12-10

### Added
- **Prompt Editor Module**: Extracted prompt editor into reusable module (`prompt-editor.js`)
  - Can be used in multiple places (workflows, assistants, etc.)
  - Centralized variable definitions
  - Consistent UI across plugin
  - Public API: `PolyTransPromptEditor.create()`, `.init()`, `.variables`

### Changed
- **Refactored workflow editor**: Now uses `PolyTransPromptEditor` module
  - Cleaner code, less duplication
  - Easier to maintain and extend
  - Backward compatible with existing workflows

## [1.3.4] - 2025-12-10

### Fixed
- **CRITICAL BUG**: Twig variable interpolation not working
  - `convert_legacy_syntax()` was converting `{{ variable }}` to `{{{ variable }}}`
  - Added check: if template already has Twig syntax, skip conversion
  - Variables now interpolate correctly in workflow prompts
- **File permissions**: Fixed templating files permissions (600 → 644) for Docker compatibility
- **Twig cache directory**: Improved cache directory handling
  - Cache enabled in production (WP_DEBUG = false), disabled in development
  - Automatic fallback to no-cache if directory creation fails
  - Set 777 permissions for Docker compatibility (www-data user)
  - Prevents "Unable to create cache directory" errors

### Added
- **Markdown rendering in AI responses**: AI responses with markdown are now rendered beautifully
  - Auto-detects markdown patterns (headers, bold, italic, code, lists, links)
  - Renders markdown as formatted HTML in test results
  - Fallback to plain text for non-markdown content
  - Improved readability of AI-generated content reviews

### Changed
- **Removed debug logging**: Cleaned up temporary debug logs from Variable Manager and Twig Engine
  - Debug logging was used to diagnose interpolation bug
  - Now removed for cleaner production logs
- **Increased AI response max height**: 200px → 400px for better visibility of longer responses

## [1.3.3] - 2025-12-10

### Changed
- **CONTEXT REFRESH LOGIC** (Phase 0.2): Context now stays fresh between workflow steps
  - `refresh_context_from_database()` now uses Post Data Provider for complete rebuild
  - Updates all variable structures: legacy (`post_title`), top-level (`title`), and nested (`original.*`, `translated.*`)
  - `apply_change_to_context()` (test mode) now updates all variable structures consistently
  - Ensures subsequent workflow steps see updated data after AI changes

### Fixed
- **Context staleness bug**: After AI changes title/content, next steps now see fresh data
  - Before: Step 2 would see old title from Step 0
  - After: Step 2 sees updated title from Step 1
- **Test mode consistency**: Test mode context updates now match production mode behavior

### Technical Details
- Workflow Output Processor: `refresh_context_from_database()` uses Post Data Provider
- Workflow Output Processor: `apply_change_to_context()` updates all variable structures
- Both production and test modes now maintain context consistency
- Meta field updates propagate to nested structures (`translated.meta.*`)

## [1.3.2] - 2025-12-10

### Added
- **SHORT VARIABLE ALIASES** (Phase 0.1 Day 2): Cleaner, more intuitive variable names
  - New short aliases: `{{ original.title }}`, `{{ translated.content }}`
  - Replaces verbose `{{ original_post.title }}`, `{{ translated_post.content }}`
  - Backward compatible: old names still work (`original_post.*`, `translated_post.*`)
  - Updated UI: Variable sidebar shows new recommended syntax
  - Meta field access: `{{ original.meta.seo_title }}`, `{{ translated.meta.KEY }}`

### Changed
- **POST DATA PROVIDER**: Added short aliases for better DX
  - `original` → alias for `original_post` (shorter, cleaner)
  - `translated` → alias for `translated_post` (shorter, cleaner)
  - Top-level aliases unchanged: `title`, `content`, `excerpt`
- **ADMIN UI**: Variable lists updated with new recommended syntax
  - Sidebar pills show `original.title` instead of `original_post.title`
  - Advanced examples updated with loops and meta field access
  - Legacy variables still shown for backward compatibility

### Technical Details
- Post Data Provider: Added `original` and `translated` aliases (lines 73-77)
- Updated `get_available_variables()` with new short aliases
- Updated `get_variable_documentation()` with Phase 0.1 examples
- JavaScript: Updated both `renderVariableSidebar()` and `renderVariableReferencePanel()`

## [1.3.1] - 2025-12-09

### Added
- **TWIG TEMPLATE ENGINE INTEGRATION**: Modern templating system with powerful features
  - Twig 3.22.1 for template rendering with caching and filters
  - New syntax: `{{ variable }}` for modern templating (legacy `{variable}` still works)
  - Nested variable access: `{{ original.title }}`, `{{ translated.content }}`
  - WordPress filters: `wp_excerpt`, `wp_date`, `wp_kses`, `esc_html`, `esc_url`
  - WordPress functions: `get_permalink`, `get_post_meta`
  - Conditional templates: `{% if translated.title %}...{% endif %}`
  - Template filters: `{{ content|wp_excerpt(50) }}`, `{{ date|wp_date('F j, Y') }}`
  - Automatic legacy syntax conversion (`{var}` → `{{ var }}`)
  - Deprecated variable mappings (`post_title` → `title` for backward compatibility)
  - Graceful fallback to regex interpolation on Twig errors
  - Cache directory: `/cache/twig/` (auto-created, gitignored)

### Changed
- **VARIABLE MANAGER**: Now uses Twig Engine for template interpolation
  - `interpolate_template()` delegates to Twig Engine with fallback
  - Added `interpolate_template_legacy()` private method for regex fallback
  - Automatic error logging when Twig rendering fails
- **ADMIN UI**: Completely redesigned variable panel for better UX
  - **Compact pills design**: Variables shown as `title` instead of `{{ title }}`
  - **Tooltips on hover**: Show variable description
  - **Click to insert**: Variables insert into last focused textarea
  - **Undo support**: `Ctrl+Z` works (uses `execCommand`)
  - **Scrollable**: Max 200px height with custom scrollbar
  - **Collapsible advanced section**: Filters, conditionals, meta examples
  - **New variables**: Added `recent_articles` for SEO context
  - **Removed legacy section**: Old "Available Variables" panel at bottom
  - **Fixed examples**: Show actual line breaks instead of `\\n`

### Fixed
- **LAZY LOADING**: Twig Engine now lazy loads to avoid fatal error
  - Removed `require_once` from `class-polytrans.php` (caused "Failed opening" error)
  - Added `load_twig_engine()` method in Variable Manager
  - Twig loads only when needed (during template interpolation)
  - Ensures Composer autoloader is available before loading Twig namespace

### Technical Details
- Composer dependencies: `twig/twig: ^3.0` (v3.22.1)
- Twig cache: `cache/twig/` (disabled in WP_DEBUG mode)
- Autoloader: `vendor/autoload.php` loaded in `polytrans.php`
- Class: `PolyTrans_Twig_Engine` (450 lines, includes/templating/)
- Integration: Variable Manager lazy loads Twig Engine with try-catch fallback
- Testing: Architecture tests passing (6/6), Pest PHP 2.36.0 installed

## [1.3.0] - 2025-12-08

### Added
- **WORKFLOW DATABASE MIGRATION**: Migrated workflows from wp_options to dedicated database table
  - Created `wp_polytrans_workflows` table with proper indexes (workflow_id, language, enabled, name)
  - Automatic migration from legacy wp_options storage with backup
  - JSON storage for triggers, steps, and output_actions
  - Backward compatibility mapping (`workflow_id` → `id`, `language` → `target_language`)
  - `get_workflows_using_assistant()` method for Phase 1 assistants system
  - Improved performance with indexed queries instead of array filtering
  - Migration flag to prevent duplicate migrations
  - Activation hook to create table on plugin activation
  - Admin_init hook to ensure table exists even if activation hook didn't run

### Changed
- **WORKFLOW STORAGE**: All workflow CRUD operations now use database instead of wp_options
  - `get_all_workflows()` uses SQL SELECT with hydration
  - `get_workflows_for_language()` uses indexed WHERE clause
  - `get_workflow()` uses prepared statement
  - `save_workflow()` uses INSERT/UPDATE with automatic timestamp tracking
  - `delete_workflow()` uses DELETE statement
  - `cleanup_orphaned_data()` removes invalid workflows from database

### Technical Details
- Database schema supports metadata fields (created_at, updated_at, created_by, attribution_user_id)
- Migration preserves all existing workflow data with automatic type conversion
- Fallback handling for legacy field names (target_language, attribution_user)
- Safe migration with backup to `polytrans_workflows_backup` option
- Error logging for migration failures

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
