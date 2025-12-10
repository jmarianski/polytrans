# Refactoring Plan: PSR-4 Autoloader & Code Modularization

**Goal**: Reduce cognitive load, improve maintainability, leverage Composer autoloader
**Status**: Planning
**Created**: 2025-12-10

---

## Current State Analysis

### Problems Identified

1. **48 manual `require_once` statements** in `class-polytrans.php`
2. **Large monolithic classes** (some 500-800+ lines)
3. **Inconsistent naming conventions**:
   - Some classes: `PolyTrans_Class_Name` (WordPress style)
   - PSR-4 configured but not used: `PolyTrans\` namespace
4. **No clear separation of concerns** in some large files
5. **Cognitive load**: Hard to understand what depends on what

### Current Structure

```
includes/
├── class-polytrans.php (450+ lines, 48 require_once)
├── assistants/
│   ├── class-assistant-manager.php (300+ lines)
│   ├── class-assistant-executor.php (250+ lines)
│   └── class-assistant-migration-manager.php (200+ lines)
├── core/
│   ├── class-translation-settings.php (530+ lines)
│   ├── class-background-processor.php (840+ lines) ⚠️
│   └── ... (many files)
├── menu/
│   ├── class-postprocessing-menu.php (700+ lines) ⚠️
│   ├── class-assistants-menu.php (400+ lines)
│   └── ...
├── postprocessing/
│   ├── class-workflow-executor.php (500+ lines)
│   ├── class-workflow-manager.php (600+ lines) ⚠️
│   └── ...
├── providers/
│   └── openai/
│       ├── class-openai-provider.php (518 lines)
│       └── class-openai-settings-provider.php (700+ lines) ⚠️
├── receiver/
│   └── managers/ (9 manager classes)
└── scheduler/
    └── class-translation-handler.php (791 lines) ⚠️
```

⚠️ = Files that need breaking down

---

## Refactoring Strategy

### Phase 1: Namespace Migration (Low Risk, High Impact)

**Goal**: Adopt PSR-4 autoloading without breaking existing code

**Approach**: Gradual migration with backward compatibility

1. **Keep old class names working** (WordPress style)
2. **Add new namespaced classes** alongside
3. **Use class aliases** for BC: `class_alias('PolyTrans\Core\Settings', 'PolyTrans_Translation_Settings')`
4. **Gradually migrate** module by module

**Benefits**:
- ✅ No breaking changes
- ✅ Eliminates 48 `require_once`
- ✅ Modern PHP standards
- ✅ Better IDE support

### Phase 2: Break Down Large Classes (Medium Risk, High Impact)

**Target files** (>500 lines):
1. `class-background-processor.php` (840 lines)
2. `class-translation-handler.php` (791 lines)
3. `class-openai-settings-provider.php` (700+ lines)
4. `class-postprocessing-menu.php` (700+ lines)
5. `class-workflow-manager.php` (600+ lines)
6. `class-translation-settings.php` (530+ lines)
7. `class-openai-provider.php` (518 lines)

**Strategy**: Extract to smaller, focused classes

### Phase 3: Service Container (Optional, Future)

Consider implementing a simple DI container for better dependency management.

---

## Proposed New Structure

```
includes/
├── Bootstrap.php (NEW - replaces load_dependencies logic)
│
├── Core/
│   ├── Settings/
│   │   ├── SettingsManager.php
│   │   ├── SettingsRenderer.php
│   │   └── Tabs/
│   │       ├── ProviderTab.php
│   │       ├── BasicTab.php
│   │       ├── LanguagePairsTab.php
│   │       └── ...
│   ├── Background/
│   │   ├── BackgroundProcessor.php
│   │   ├── TaskRunner.php
│   │   ├── TaskQueue.php
│   │   └── Tasks/
│   │       ├── TranslationTask.php
│   │       ├── WorkflowTask.php
│   │       └── ...
│   └── ...
│
├── Assistants/
│   ├── AssistantManager.php
│   ├── AssistantExecutor.php
│   ├── AssistantMigration.php
│   └── Parsers/
│       └── JsonResponseParser.php
│
├── Menu/
│   ├── SettingsMenu.php
│   ├── AssistantsMenu/
│   │   ├── AssistantsMenu.php
│   │   ├── AssistantsList.php
│   │   └── AssistantEditor.php
│   └── PostProcessing/
│       ├── PostProcessingMenu.php
│       ├── WorkflowsList.php
│       ├── WorkflowEditor.php
│       └── WorkflowExecutor.php
│
├── PostProcessing/
│   ├── Workflow/
│   │   ├── WorkflowManager.php
│   │   ├── WorkflowExecutor.php
│   │   ├── WorkflowStorage.php
│   │   └── WorkflowValidator.php
│   ├── Steps/
│   │   ├── StepInterface.php
│   │   ├── AiAssistantStep.php
│   │   ├── ManagedAssistantStep.php
│   │   └── ...
│   └── Variables/
│       ├── VariableManager.php
│       └── Providers/
│           ├── ProviderInterface.php
│           ├── PostDataProvider.php
│           └── ...
│
├── Providers/
│   ├── ProviderInterface.php
│   ├── ProviderRegistry.php
│   └── OpenAI/
│       ├── OpenAIProvider.php
│       ├── OpenAIClient.php
│       ├── OpenAISettings/
│       │   ├── OpenAISettingsProvider.php
│       │   ├── SettingsRenderer.php
│       │   └── AssistantLoader.php
│       └── ...
│
├── Scheduler/
│   ├── TranslationScheduler.php
│   ├── TranslationHandler/
│   │   ├── TranslationHandler.php
│   │   ├── StatusManager.php
│   │   ├── QueueManager.php
│   │   └── ExecutionManager.php
│   └── ...
│
└── Receiver/
    ├── TranslationCoordinator.php
    ├── TranslationReceiverExtension.php
    └── Managers/
        ├── RequestValidator.php
        ├── PostCreator.php
        └── ...
```

---

## Implementation Plan

### Step 1: Setup Autoloader Bootstrap (1 day)

**Files to create**:
- `includes/Bootstrap.php` - Autoloader registration
- `includes/Compatibility.php` - Class aliases for BC

**Changes**:
- Update `polytrans.php` to use Bootstrap
- Register Composer autoloader
- Add class aliases

**Example**:
```php
// includes/Bootstrap.php
namespace PolyTrans;

class Bootstrap {
    public static function init() {
        // Register Composer autoloader
        require_once POLYTRANS_PLUGIN_DIR . 'vendor/autoload.php';
        
        // Register backward compatibility aliases
        require_once __DIR__ . '/Compatibility.php';
    }
}

// includes/Compatibility.php
// Map old class names to new namespaced ones
class_alias('PolyTrans\Core\Settings\SettingsManager', 'PolyTrans_Translation_Settings');
class_alias('PolyTrans\Assistants\AssistantManager', 'PolyTrans_Assistant_Manager');
// ... etc
```

### Step 2: Migrate Small Modules First (2-3 days)

**Priority order** (lowest risk first):
1. ✅ Assistants module (3 files, well-defined)
2. ✅ Menu classes (5 files, mostly independent)
3. ✅ Receiver managers (9 files, already modular)
4. ✅ PostProcessing steps (3 files, implement interface)
5. ✅ Variable providers (4 files, implement interface)

**For each module**:
1. Create new namespaced class
2. Add class alias for BC
3. Update internal references to use new class
4. Test thoroughly
5. Remove old file (keep alias)

### Step 3: Break Down Large Classes (3-4 days)

**Target 1: `class-background-processor.php` (840 lines)**

Extract to:
- `Core/Background/BackgroundProcessor.php` (orchestrator, ~200 lines)
- `Core/Background/TaskRunner.php` (execution logic, ~200 lines)
- `Core/Background/TaskQueue.php` (queue management, ~150 lines)
- `Core/Background/Tasks/TranslationTask.php` (~100 lines)
- `Core/Background/Tasks/WorkflowTask.php` (~100 lines)

**Target 2: `class-translation-handler.php` (791 lines)**

Extract to:
- `Scheduler/TranslationHandler/TranslationHandler.php` (orchestrator, ~200 lines)
- `Scheduler/TranslationHandler/StatusManager.php` (~150 lines)
- `Scheduler/TranslationHandler/QueueManager.php` (~150 lines)
- `Scheduler/TranslationHandler/ExecutionManager.php` (~200 lines)

**Target 3: `class-postprocessing-menu.php` (700+ lines)**

Extract to:
- `Menu/PostProcessing/PostProcessingMenu.php` (orchestrator, ~150 lines)
- `Menu/PostProcessing/WorkflowsList.php` (list rendering, ~150 lines)
- `Menu/PostProcessing/WorkflowEditor.php` (editor rendering, ~200 lines)
- `Menu/PostProcessing/AjaxHandlers.php` (AJAX endpoints, ~200 lines)

**Target 4: `class-workflow-manager.php` (600+ lines)**

Extract to:
- `PostProcessing/Workflow/WorkflowManager.php` (orchestrator, ~150 lines)
- `PostProcessing/Workflow/WorkflowStorage.php` (DB operations, ~150 lines)
- `PostProcessing/Workflow/WorkflowValidator.php` (~100 lines)
- `PostProcessing/Workflow/WorkflowExecutor.php` (~200 lines)

**Target 5: `class-openai-settings-provider.php` (700+ lines)**

Extract to:
- `Providers/OpenAI/Settings/OpenAISettingsProvider.php` (orchestrator, ~150 lines)
- `Providers/OpenAI/Settings/SettingsRenderer.php` (UI rendering, ~250 lines)
- `Providers/OpenAI/Settings/AssistantLoader.php` (AJAX, ~150 lines)
- `Providers/OpenAI/Settings/ModelProvider.php` (~100 lines)

### Step 4: Update Main Plugin Class (1 day)

**Simplify `class-polytrans.php`**:
- Remove all `require_once` (use autoloader)
- Keep only initialization logic
- Use namespaced classes internally
- Maintain BC with class aliases

**Before** (450 lines with 48 requires):
```php
class PolyTrans {
    private function load_dependencies() {
        require_once ...
        require_once ...
        // 48 times
    }
}
```

**After** (~150 lines, clean):
```php
namespace PolyTrans;

class PolyTrans {
    public function __construct() {
        Bootstrap::init();
        $this->init_hooks();
    }
    
    // Clean initialization, no manual requires
}
```

### Step 5: Testing & Validation (2 days)

- ✅ Run all existing tests
- ✅ Test backward compatibility
- ✅ Manual testing of all features
- ✅ Performance benchmarks
- ✅ Update documentation

---

## Success Criteria

### Code Quality Metrics

**Before**:
- 48 manual `require_once`
- 6 files >500 lines
- Mixed naming conventions
- No autoloading

**After**:
- ✅ 0 manual `require_once` (use autoloader)
- ✅ 0 files >400 lines (target: <300 lines per class)
- ✅ Consistent PSR-4 namespacing
- ✅ Full Composer autoloading
- ✅ Backward compatible (class aliases)
- ✅ Better IDE support (autocomplete, navigation)
- ✅ Easier testing (smaller, focused classes)

### Backward Compatibility

- ✅ All existing code continues to work
- ✅ Class aliases for old names
- ✅ No breaking changes for users
- ✅ Gradual migration path

---

## Risk Assessment

### Low Risk
- ✅ Adding namespaced classes alongside old ones
- ✅ Using class aliases for BC
- ✅ Migrating small, independent modules

### Medium Risk
- ⚠️ Breaking down large classes (complex dependencies)
- ⚠️ Updating internal references

### High Risk
- ❌ Removing old classes without aliases (DON'T DO)
- ❌ Breaking backward compatibility (AVOID)

### Mitigation
- Comprehensive testing at each step
- Keep class aliases indefinitely
- Feature flags for new code paths
- Gradual rollout (module by module)

---

## Timeline

**Total Estimate**: 7-10 days

- **Day 1**: Setup Bootstrap & Autoloader
- **Day 2-4**: Migrate small modules (Assistants, Menu, Receiver)
- **Day 5-8**: Break down large classes (Background, Handler, Menu, Workflow)
- **Day 9**: Update main plugin class
- **Day 10**: Testing & validation

---

## Future Enhancements (Post-Refactoring)

1. **Dependency Injection Container**
   - Better dependency management
   - Easier testing with mocks
   - Cleaner initialization

2. **Service Locator Pattern**
   - Central registry for services
   - Lazy loading of components

3. **Event System**
   - Decouple components
   - Better extensibility

4. **Repository Pattern**
   - Abstract database operations
   - Easier testing
   - Better separation of concerns

---

## Next Steps

1. **Review this plan** - Get approval
2. **Create feature branch**: `refactor/psr4-autoloader`
3. **Start with Step 1**: Bootstrap setup
4. **Iterate module by module**
5. **Test continuously**

---

**Document Version**: 1.0
**Last Updated**: 2025-12-10
**Status**: 📋 Planning - Ready for Review
**Estimated Effort**: 7-10 working days

