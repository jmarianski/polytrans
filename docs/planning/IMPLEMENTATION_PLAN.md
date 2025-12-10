# PolyTrans: Implementation Plan - Variable System Refactor & Assistants

**Project**: PolyTrans Plugin Enhancement
**Estimated Timeline**: 3-4 weeks
**Priority**: High
**Status**: Phase 0.0 Complete, Testing Infrastructure Ready
**Last Updated**: 2025-12-08

---

## Executive Summary

This document outlines the implementation plan for two major enhancements to PolyTrans:

1. **Phase 0.0**: ✅ Workflows Database Migration (COMPLETE - 2025-12-08)
2. **Phase 0.1-0.4**: Variable System Refactor & Twig Integration (1-2 weeks)
3. **Phase 1**: AI Assistants Management System (2-3 weeks)

Phase 0 must be completed first as it fixes critical issues in the variable system that would affect the assistants feature.

---

## 🎉 Recent Completion: Phase 0.0

### Workflows Database Migration (v1.3.0)

**Status**: ✅ COMPLETE (2025-12-08)

**What was done**:
- Migrated workflows from `wp_options` to dedicated `wp_polytrans_workflows` table
- Automatic migration with backup (`polytrans_workflows_backup`)
- Backward compatibility (`workflow_id` → `id`, `language` → `target_language`)
- JSON storage for triggers, steps, output_actions
- Proper indexes (workflow_id, language, enabled, name)
- Activation hooks + admin_init safety check
- Tested on transeu production (1 workflow migrated successfully)

**Files modified**:
- `includes/postprocessing/managers/class-workflow-storage-manager.php` (full rewrite)
- `polytrans.php` (activation hooks, v1.3.0)
- `CHANGELOG.md` (documented)

**Benefits**:
- 🚀 Better performance (indexed queries)
- 🔍 Easy queries (SQL vs array filtering)
- 🔗 Ready for Phase 1 (assistants relationships)
- 📦 No wp_options bloat

**Testing**:
- ✅ Manual test script passed all scenarios
- ✅ Migration verified on transeu
- ⏳ Automated unit tests (next priority)

---

## 🧪 New Priority: Testing Infrastructure

### Testing Framework Decision

**Unit/Integration Tests**: Pest PHP v2.35+
**E2E Tests**: Playwright (not Cypress)
**Status**: Research complete, ready to implement

**Why Pest?**
- Modern, elegant syntax (Jest-like)
- Built on PHPUnit (full compatibility)
- Architecture testing built-in
- Growing adoption (17%, up 4pp in 2024)
- Laravel official choice

**Why Playwright?**
- Multi-tab support (critical for WP admin)
- Faster than Cypress for WordPress
- Better cross-browser support

**Documentation**:
- ✅ `TESTING_FRAMEWORK_RESEARCH.md` - Full analysis
- ✅ `TESTING_BEST_PRACTICES.md` - User feedback, pitfalls
- ✅ `TESTING_ACTION_PLAN.md` - Implementation steps

**Next Steps** (2-3 days):
1. Install Pest + pin version (`^2.35`)
2. Create SQLite test helpers
3. Write 25+ unit tests for WorkflowStorageManager
4. Architecture tests (5+ rules)
5. CI/CD integration

---

---

## Phase 0: Variable System Refactor (Critical)

### Problems Identified

1. **Inconsistent variable naming**: `{title}` vs `{post_title}` - same field, different sources
2. **Stale variables after changes**: AI changes title in Step 1, Step 2 sees old value
3. **Test mode inconsistency**: Context not refreshed properly in test mode
4. **Limited templating**: No conditionals, loops, or filters

### Goals

- ✅ Unified variable naming convention (`{title}`, `{content}`, `{original.title}`)
- ✅ Twig templating engine with backward compatibility for `{variable}` syntax
- ✅ Proper context refresh in both test and production modes
- ✅ All variables stay up-to-date across workflow steps

### Implementation Steps

#### Step 1: Add Twig Dependency (1 day)

**Files to modify/create**:
- `composer.json` - Add `twig/twig: ^3.0`
- `.gitignore` - Add `vendor/`, `cache/twig/`

**Actions**:
```bash
cd plugins/polytrans
composer require twig/twig:^3.0
```

#### Step 2: Create Twig Engine Wrapper (2 days)

**File**: `includes/templating/class-twig-template-engine.php`

**Key features**:
- Initialize Twig with caching
- Convert legacy `{variable}` to `{{ variable }}`
- Map deprecated names (`post_title` → `title`)
- Custom WordPress filters (`wp_excerpt`, `wp_date`, `wp_kses`)
- Fallback to regex method if Twig fails

**Core methods**:
```php
PolyTrans_Twig_Engine::init()
PolyTrans_Twig_Engine::render($template, $context)
PolyTrans_Twig_Engine::convert_legacy_syntax($template)
PolyTrans_Twig_Engine::map_deprecated_variable($variable_path)
```

#### Step 3: Update Variable Manager (1 day)

**File**: `includes/postprocessing/class-variable-manager.php`

**Changes**:
- `interpolate_template()` - Use Twig Engine instead of regex
- Keep `interpolate_template_legacy()` as fallback
- No other changes needed (build_context, get_variable_value, etc. stay same)

#### Step 4: Refactor Post Data Provider (1 day)

**File**: `includes/postprocessing/providers/class-post-data-provider.php`

**New context structure**:
```php
return [
    // Top-level aliases (primary)
    'title' => 'Post Title',
    'content' => 'Content...',
    'excerpt' => 'Excerpt...',

    // Structured objects
    'original' => [
        'id' => 123,
        'title' => 'Original Title',
        'content' => '...',
        'meta' => [...]
    ],
    'translated' => [
        'id' => 456,
        'title' => 'Post Title',
        'content' => '...',
        'meta' => [...]
    ],

    // Merged meta
    'meta' => [...],

    // DEPRECATED (backward compat)
    'post_title' => 'Post Title',
    'post_content' => 'Content...',
    'original_post' => [...],
    'translated_post' => [...]
];
```

#### Step 5: Implement Context Rebuild Logic (2 days)

**File**: `includes/postprocessing/class-workflow-output-processor.php`

**New methods**:
- `rebuild_context_from_database($context)` - Production mode
- `rebuild_context_from_changes($context, $changes)` - Test mode
- `create_virtual_post_from_context($context)` - Helper for test mode

**Update**: `process_step_outputs()` method to use rebuild instead of simple refresh

**Key logic**:
```php
if (!empty($changes)) {
    if ($test_mode) {
        $updated_context = $this->rebuild_context_from_changes($updated_context, $changes);
    } else {
        $updated_context = $this->rebuild_context_from_database($updated_context);
    }
}
```

#### Step 6: Testing (2 days)

**Test cases**:
1. Legacy templates work (`{post_title}` mapped to `{title}`)
2. New templates work (`{title}`, `{original.title}`)
3. Twig features work (conditionals, loops, filters)
4. Variables update correctly after changes
5. Test mode behaves like production (minus DB writes)
6. Context refresh works in both modes

**Test workflows**:
- Multi-step workflow that changes title, then reads it
- Test mode vs production mode comparison
- Backward compatibility with existing workflows

#### Step 7: Documentation (1 day)

**Files to create**:
- `TEMPLATING_GUIDE.md` - Complete templating reference
- `VARIABLE_REFERENCE.md` - All available variables
- `PHASE_0_MIGRATION.md` - How to update existing templates

---

## Phase 1: AI Assistants Management System

### Business Requirements

**Central assistants management**:
- Create/edit/delete assistants via admin UI
- Configure: name, provider, model, prompts, API parameters
- Reuse assistants in workflows and translations

**Workflow integration**:
- Select assistant from dropdown instead of defining prompts per-step
- Backward compatible: existing workflows with inline prompts still work
- Editing assistant affects all workflows using it (global reuse)

**Translation integration**:
- Grouped dropdown: "OpenAI Assistants" | "System Assistants"
- System assistants use Chat API (not Assistants API)
- Future-ready for Claude/Gemini providers

### Implementation Steps

#### Step 1: Database Schema (1 day)

**Table**: `wp_polytrans_assistants`

```sql
CREATE TABLE wp_polytrans_assistants (
  id bigint(20) unsigned AUTO_INCREMENT PRIMARY KEY,
  name varchar(255) NOT NULL,
  description text,
  provider varchar(50) DEFAULT 'openai',
  status varchar(20) DEFAULT 'active',

  system_prompt text NOT NULL,
  user_message_template text,

  api_parameters text NOT NULL,  -- JSON: {model, temperature, top_p, max_tokens, ...}
  expected_format varchar(20) DEFAULT 'text',
  output_variables text,  -- JSON array

  created_at datetime NOT NULL,
  updated_at datetime NOT NULL,
  created_by bigint(20) unsigned,

  KEY provider (provider),
  KEY status (status),
  KEY name (name)
);
```

**File**: `includes/assistants/class-assistant-manager.php` (create table method)

#### Step 2: Assistant Manager Class (2 days)

**File**: `includes/assistants/class-assistant-manager.php`

**CRUD methods**:
- `create_assistant($data)` - Returns ID or WP_Error
- `update_assistant($id, $data)` - Returns bool or WP_Error
- `delete_assistant($id)` - Returns bool or WP_Error
- `get_assistant($id)` - Returns array or null
- `get_all_assistants($filters)` - Returns array of assistants

**Utility methods**:
- `validate_assistant_data($data)` - Returns `['valid' => bool, 'errors' => array]`
- `check_assistant_usage($id)` - Returns `['workflows' => array, 'translations' => array]`
- `get_assistants_for_dropdown($group_by_provider)` - Returns formatted options

#### Step 3: Assistant Executor Class (2 days)

**File**: `includes/assistants/class-assistant-executor.php`

**Core method**:
```php
public static function execute($assistant_id, $context) {
    // 1. Load assistant from DB
    // 2. Interpolate prompts using Variable Manager (Twig)
    // 3. Call provider API (OpenAI Chat Completions)
    // 4. Process response (text or JSON)
    // 5. Return standardized result
}
```

**Helper methods**:
- `execute_with_config($config, $context)` - Direct execution without DB lookup
- `interpolate_prompts($config, $context)` - Use Variable Manager
- `call_provider_api($config, $prompts)` - API call
- `process_response($response, $config)` - Parse result

#### Step 4: Admin UI - Assistants Menu (3 days)

**File**: `includes/menu/class-assistants-menu.php`

**Pages**:
1. **List page**: Table with Name, Provider, Model, Status, Used In, Actions
2. **Edit page**: Form with all assistant configuration fields

**Features**:
- Add New / Edit / Delete
- Filters (Provider, Status)
- Bulk actions (Delete, Activate, Deactivate)
- Usage warning when deleting (show which workflows use it)

**UI sections** (edit page):
- Basic Information (name, description, provider, status)
- Prompts Configuration (system prompt, user message template)
- API Parameters (model, temperature, top_p, max_tokens, frequency_penalty)
- Response Configuration (expected_format, output_variables)
- Test Section (test assistant with sample context)

**JavaScript**: `assets/js/assistants-admin.js`
- Range sliders with output display
- Show/hide output_variables based on expected_format
- Test assistant functionality (AJAX)
- Delete confirmation
- Form validation

#### Step 5: Workflow Integration (2 days)

**File**: `includes/postprocessing/steps/class-ai-assistant-step.php`

**Changes in `execute()` method**:
```php
// Check for legacy mode (backward compatibility)
if (isset($step_config['system_prompt']) && !empty($step_config['system_prompt'])) {
    return $this->execute_legacy_mode($context, $step_config);
}

// New mode: Use system assistant
if (isset($step_config['assistant_id'])) {
    return PolyTrans_Assistant_Executor::execute($step_config['assistant_id'], $context);
}

return ['success' => false, 'error' => 'No assistant configured'];
```

**UI updates** (`assets/js/postprocessing-admin.js`):
- Add "Assistant Source" selector (Custom Prompts | System Assistant)
- System Assistant dropdown (loads from AJAX)
- Show/hide fields based on source
- Warning message: "Editing affects all workflows"

#### Step 6: Translation Integration (2 days)

**File**: `includes/providers/openai/class-openai-settings-ui.php`

**Changes in assistants section**:
```html
<select name="openai_assistants[en_to_pl]">
    <optgroup label="OpenAI Assistants">
        <option value="asst_xxx">Assistant Name (gpt-4)</option>
    </optgroup>
    <optgroup label="System Assistants">
        <option value="system:123">Custom Assistant (gpt-4o-mini)</option>
    </optgroup>
</select>
```

**File**: `includes/providers/openai/class-openai-translation-provider.php`

**Changes in `translate()` method**:
```php
$config = $this->get_assistant_config_for_translation($source_lang, $target_lang);

if (strpos($assistant_ref, 'system:') === 0) {
    // Load system assistant from DB
    // Execute using Chat API (not Assistants API)
    return $this->translate_with_system_assistant(...);
}

if (strpos($assistant_ref, 'asst_') === 0) {
    // Use OpenAI Assistants API (legacy)
    return $this->translate_with_openai_assistant(...);
}
```

#### Step 7: Testing (2 days)

**Test scenarios**:
1. Create/edit/delete assistants via UI
2. Use system assistant in workflow
3. Use system assistant in translation
4. Backward compatibility: old workflows still work
5. Variables interpolate correctly in assistant prompts
6. Test mode works with assistants
7. Assistant usage tracking (which workflows use it)

#### Step 8: Documentation (1 day)

**Files to create**:
- `ASSISTANTS_USER_GUIDE.md` - How to use assistants system
- `ASSISTANTS_API_REFERENCE.md` - Developer documentation
- Update `README.md` with assistants section

---

## Implementation Schedule

### Week 1: Phase 0 - Twig & Variable Refactor
- **Day 1**: Composer setup, Twig Engine wrapper
- **Day 2**: Twig Engine wrapper completion, Variable Manager update
- **Day 3**: Post Data Provider refactor
- **Day 4**: Context rebuild logic
- **Day 5**: Context rebuild logic completion, testing start

### Week 2: Phase 0 - Testing & Documentation
- **Day 1**: Testing (variable updates, test mode)
- **Day 2**: Testing (Twig features, backward compat)
- **Day 3**: Bug fixes from testing
- **Day 4**: Documentation (Templating Guide, Variable Reference)
- **Day 5**: Final review, Phase 0 completion

### Week 3: Phase 1 - Assistants Core
- **Day 1**: Database schema, Assistant Manager class
- **Day 2**: Assistant Manager completion, Assistant Executor start
- **Day 3**: Assistant Executor completion
- **Day 4**: Assistants Menu UI (list page)
- **Day 5**: Assistants Menu UI (edit page)

### Week 4: Phase 1 - Integration & Testing
- **Day 1**: Workflow integration
- **Day 2**: Translation integration
- **Day 3**: Testing (all scenarios)
- **Day 4**: Bug fixes, polish
- **Day 5**: Documentation, final review

---

## File Structure

```
plugins/polytrans/
├── composer.json (NEW)
├── vendor/ (NEW, gitignored)
├── cache/twig/ (NEW, gitignored)
│
├── includes/
│   ├── templating/ (NEW)
│   │   └── class-twig-template-engine.php
│   │
│   ├── assistants/ (NEW)
│   │   ├── class-assistant-manager.php
│   │   └── class-assistant-executor.php
│   │
│   ├── menu/
│   │   ├── class-assistants-menu.php (NEW)
│   │   └── class-postprocessing-menu.php (MODIFY)
│   │
│   ├── postprocessing/
│   │   ├── class-variable-manager.php (MODIFY)
│   │   ├── class-workflow-output-processor.php (MODIFY)
│   │   ├── steps/
│   │   │   └── class-ai-assistant-step.php (MODIFY)
│   │   └── providers/
│   │       ├── class-post-data-provider.php (MODIFY)
│   │       └── class-context-data-provider.php (MODIFY)
│   │
│   └── providers/
│       └── openai/
│           ├── class-openai-settings-ui.php (MODIFY)
│           └── class-openai-translation-provider.php (MODIFY)
│
├── assets/
│   ├── js/
│   │   ├── assistants-admin.js (NEW)
│   │   └── postprocessing-admin.js (MODIFY)
│   └── css/
│       └── assistants-admin.css (NEW)
│
└── docs/
    ├── PHASE_0_VARIABLE_REFACTOR.md (EXISTS)
    ├── ASSISTANTS_SYSTEM_PLAN.md (EXISTS)
    ├── IMPLEMENTATION_PLAN.md (THIS FILE)
    ├── TEMPLATING_GUIDE.md (TO CREATE)
    ├── VARIABLE_REFERENCE.md (TO CREATE)
    ├── ASSISTANTS_USER_GUIDE.md (TO CREATE)
    └── ASSISTANTS_API_REFERENCE.md (TO CREATE)
```

---

## Risk Assessment

### High Risk
- **Context rebuild performance**: Rebuilding entire context on every change
  - *Mitigation*: Only rebuild when changes exist, use Twig caching, profile performance

### Medium Risk
- **Backward compatibility breaking**: Despite best efforts, some edge cases might break
  - *Mitigation*: Extensive testing, beta release to internal users, deprecation warnings

- **Twig learning curve**: Team needs to learn Twig syntax
  - *Mitigation*: Comprehensive documentation, examples, legacy syntax still works

### Low Risk
- **Database migration**: New table creation
  - *Mitigation*: Standard WordPress dbDelta, tested pattern (logs table already uses it)

---

## Success Criteria

### Phase 0 Success Criteria
- ✅ All existing workflows work without modification
- ✅ Variables update correctly across workflow steps
- ✅ Test mode behaves identically to production (minus DB writes)
- ✅ Twig templates work (conditionals, loops, filters)
- ✅ Performance acceptable (< 10% overhead vs regex method)
- ✅ Documentation complete

### Phase 1 Success Criteria
- ✅ Assistants can be created/edited/deleted via UI
- ✅ Assistants work in workflows
- ✅ Assistants work in translations
- ✅ Backward compatibility maintained
- ✅ All tests passing
- ✅ Documentation complete

---

## Rollback Plan

### If Phase 0 Fails
- Revert commits
- Keep existing regex-based interpolation
- Assistants system will work but with existing variable issues

### If Phase 1 Fails
- Remove assistants table
- Remove assistant UI
- Keep workflow/translation system as is
- Phase 0 improvements remain (they're valuable independently)

---

## Dependencies

### External
- **Twig**: `twig/twig ^3.0` via Composer
- **PHP**: >= 7.4 (Twig requirement)
- **WordPress**: >= 5.0

### Internal
- Phase 1 depends on Phase 0 completion
- No other PolyTrans features are blocked

---

## Next Steps

1. **Review this plan** - Get stakeholder approval
2. **Setup development environment** - Branch: `feature/phase-0-variables`
3. **Begin Phase 0, Week 1, Day 1** - Composer setup
4. **Daily standups** - Track progress, address blockers
5. **Weekly review** - Ensure timeline on track

---

**Document Version**: 1.0
**Last Updated**: 2024-12-08
**Status**: ✅ Ready for Implementation
**Estimated Effort**: 20-25 working days (4 weeks)
