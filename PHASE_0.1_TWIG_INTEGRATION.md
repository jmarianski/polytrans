# Phase 0.1: Twig Template Engine Integration

**Status**: ✅ Day 1 Complete (Setup + Integration)
**Date**: 2025-12-09
**Duration**: ~2 hours

---

## 📋 Overview

Phase 0.1 introduces Twig 3.x template engine to PolyTrans, replacing the limited regex-based variable interpolation system. This enables:

- Modern templating syntax with control structures (`if`, `for`, filters)
- Better error handling and debugging
- Backward compatibility with legacy `{variable}` syntax
- WordPress-specific filters and functions
- Automatic caching for performance

---

## ✅ What Was Implemented (Day 1)

### 1. Twig Dependency Installation
- **Package**: `twig/twig: ^3.0` (v3.22.1 installed)
- **Method**: Composer via Docker container
- **Location**: `composer.json` (require section)
- **Autoloader**: Added `vendor/autoload.php` to `polytrans.php`

### 2. Twig Template Engine Class
- **File**: `includes/templating/class-twig-template-engine.php` (450 lines)
- **Features**:
  - Twig environment initialization with caching
  - Legacy syntax conversion (`{variable}` → `{{ variable }}`)
  - Deprecated variable mappings (e.g., `post_title` → `title`)
  - WordPress filters: `wp_excerpt`, `wp_date`, `wp_kses`, `esc_html`, `esc_url`
  - WordPress functions: `get_permalink`, `get_post_meta`
  - Graceful fallback to regex on Twig errors
  - Cache management utilities

### 3. Variable Manager Integration
- **File**: `includes/postprocessing/class-variable-manager.php`
- **Changes**:
  - `interpolate_template()` now uses Twig Engine first
  - Added `interpolate_template_legacy()` private method (regex fallback)
  - Automatic fallback on Twig errors with logging
  - Full backward compatibility maintained

### 4. Plugin Bootstrap Updates
- **File**: `polytrans.php`
  - Added Composer autoloader loading (line 28-31)
  - Version remains 1.3.0 (no bump yet, waiting for full Phase 0.1-0.2)

- **File**: `includes/class-polytrans.php`
  - Added Twig Engine require_once (line 106)
  - Loaded before Variable Manager to satisfy dependencies

### 5. Configuration Updates
- **File**: `.gitignore`
  - Added `/cache/` directory (for Twig compiled templates)
  - `vendor/` already gitignored

- **File**: `docker-compose.test.yml`
  - Removed `user: "1000:1000"` (permission conflicts)
  - Removed named volume for `vendor/` (use host directory)
  - Added `COMPOSER_ALLOW_SUPERUSER=1` environment variable

---

## 🏗️ Architecture

### Template Rendering Flow

```
User Request (e.g., workflow step prompt)
  ↓
Variable Manager::interpolate_template($template, $context)
  ↓
Twig_Engine::render($template, $context)
  ↓
├─ Convert legacy syntax: {variable} → {{ variable }}
├─ Add deprecated mappings: post_title → title
├─ Initialize Twig Environment (if needed)
│  ├─ Setup ArrayLoader
│  ├─ Configure caching (cache/twig/)
│  ├─ Add WordPress filters (wp_excerpt, wp_date, wp_kses)
│  └─ Add WordPress functions (get_permalink, get_post_meta)
├─ Load template into Twig
└─ Render template with context
  ↓
  ┌─ Success: Return rendered string
  └─ Error: Log + Fallback to regex interpolation
```

### Deprecated Variable Mappings

Provides backward compatibility for old template syntax:

| Legacy Variable         | New Variable           |
|------------------------|------------------------|
| `{post_title}`         | `{title}`              |
| `{post_content}`       | `{content}`            |
| `{post_excerpt}`       | `{excerpt}`            |
| `{post_date}`          | `{date}`               |
| `{post_author}`        | `{author}`             |
| `{translated_title}`   | `{translated.title}`   |
| `{translated_content}` | `{translated.content}` |
| `{original_title}`     | `{original.title}`     |
| `{original_content}`   | `{original.content}`   |

---

## 📝 Usage Examples

### Legacy Syntax (Still Works)
```
Translate this article:
Title: {post_title}
Content: {post_content}
```

### New Twig Syntax (Recommended)
```twig
Translate this article:
Title: {{ title }}
Content: {{ content|wp_excerpt(50) }}

{% if translated.title %}
Previous translation: {{ translated.title }}
{% endif %}
```

### WordPress Filters
```twig
{# Truncate content to 100 words #}
{{ content|wp_excerpt(100) }}

{# Format date in WordPress format #}
Published on: {{ date|wp_date('F j, Y') }}

{# Sanitize HTML #}
{{ user_content|wp_kses }}

{# Escape for attributes #}
<div data-title="{{ title|esc_html }}">
```

### WordPress Functions
```twig
{# Get permalink #}
Read more: {{ get_permalink(post_id) }}

{# Get post meta #}
Custom field: {{ get_post_meta(post_id, '_custom_field', true) }}
```

---

## 🧪 Testing

### Manual Testing (Development)

1. **Test legacy syntax**:
   ```php
   $template = "Title: {post_title}, Content: {post_content}";
   $context = ['post_title' => 'Test', 'post_content' => 'Hello'];
   $result = $var_manager->interpolate_template($template, $context);
   // Expected: "Title: Test, Content: Hello"
   ```

2. **Test Twig syntax**:
   ```php
   $template = "Title: {{ title }}, Length: {{ content|length }}";
   $context = ['title' => 'Test', 'content' => 'Hello World'];
   $result = $var_manager->interpolate_template($template, $context);
   // Expected: "Title: Test, Length: 11"
   ```

3. **Test fallback**:
   ```php
   $template = "Title: {{ undefined_function() }}"; // Invalid Twig
   $context = ['title' => 'Test'];
   $result = $var_manager->interpolate_template($template, $context);
   // Expected: Falls back to regex, logs warning
   ```

### Automated Testing (TODO)
- Unit tests for Twig Engine (Phase 0.3+)
- Integration tests for Variable Manager (Phase 0.3+)
- E2E tests for workflow execution (Phase 0.3+)

---

## 📊 Performance

### Caching Strategy
- **Dev Mode** (`WP_DEBUG = true`): No caching, auto-reload templates
- **Production Mode** (`WP_DEBUG = false`): Compiled templates cached in `/cache/twig/`
- **Cache Clearing**: `PolyTrans_Twig_Engine::clear_cache()` (admin utility)

### Expected Impact
- **First render**: ~5-10ms slower (Twig compilation)
- **Subsequent renders**: ~2-3ms faster (compiled cache)
- **Memory**: +2-3 MB (Twig library)

---

## 🐛 Bugfix: Lazy Loading

**Problem**: Twig Engine uses `use Twig\...` statements which require Composer autoloader. If loaded via `require_once` in `class-polytrans.php` before WordPress init, it fails with:
```
Fatal error: Failed opening required 'class-twig-template-engine.php'
```

**Solution**: Lazy load Twig Engine in Variable Manager:
1. Removed `require_once` from `class-polytrans.php` (line 106)
2. Added `load_twig_engine()` private method in Variable Manager
3. Called `load_twig_engine()` before using Twig in `interpolate_template()`

**Result**: Twig Engine loads **only when needed** (during template interpolation), after Composer autoloader is available.

---

## 🔧 Configuration

### Twig Options
Set via `PolyTrans_Twig_Engine::init($options)`:

```php
$options = [
    'cache_dir' => '/custom/cache/path',  // Default: plugin_dir/cache/twig
    'debug' => true,                       // Default: WP_DEBUG
];
PolyTrans_Twig_Engine::init($options);
```

### WordPress Integration
- **Filters**: Automatically added (wp_excerpt, wp_date, wp_kses, esc_html, esc_url)
- **Functions**: Automatically added (get_permalink, get_post_meta)
- **Custom Extensions**: Add via `PolyTrans_Twig_Engine::get_twig()->addFilter(...)` (advanced)

---

## 🚫 Known Limitations

1. **No WordPress Plugin Available**: Using custom integration instead
2. **Cache Management**: No automatic cache invalidation (manual clear needed)
3. **Error Handling**: Twig errors fall back to regex (no user-facing error messages)
4. **Autoescape Disabled**: WordPress handles escaping, not Twig
5. **Strict Variables Disabled**: Missing variables return empty string (no errors)

---

## 📁 Files Modified

```
plugins/polytrans/
├── composer.json                                      # ADD twig/twig ^3.0
├── .gitignore                                          # ADD /cache/
├── docker-compose.test.yml                             # MODIFY (volume, permissions)
├── polytrans.php                                       # ADD vendor autoloader
├── includes/
│   ├── class-polytrans.php                            # ADD require twig engine
│   ├── templating/                                    # NEW DIRECTORY
│   │   └── class-twig-template-engine.php             # CREATE (450 lines)
│   └── postprocessing/
│       └── class-variable-manager.php                 # MODIFY (use Twig, add fallback)
└── PHASE_0.1_TWIG_INTEGRATION.md                      # THIS FILE
```

---

## 🎯 Next Steps (Day 2-3)

### Day 2: Post Data Provider Refactor
- [ ] Read current `class-post-data-provider.php` implementation
- [ ] Design new context structure:
  ```php
  [
      // Top-level aliases (backward compat)
      'title' => 'Original Title',
      'content' => 'Original content...',

      // Nested objects (new standard)
      'original' => [
          'title' => 'Original Title',
          'content' => 'Original content...',
          'excerpt' => '...',
          'meta' => [...],
      ],
      'translated' => [
          'title' => 'Translated Title',
          'content' => 'Translated content...',
          // ... same structure
      ],
  ]
  ```
- [ ] Update `get_context()` method
- [ ] Maintain backward compatibility (deprecated mappings)
- [ ] Document variable reference

### Day 3: Testing & Documentation
- [ ] Test legacy templates work (`{post_title}`)
- [ ] Test new templates work (`{{ title }}`, `{{ original.title }}`)
- [ ] Test Twig features (`{% if %}`, `{{ var|filter }}`)
- [ ] Create `TEMPLATING_GUIDE.md`
- [ ] Create `VARIABLE_REFERENCE.md`

---

## 📚 Resources

- [Twig Documentation](https://twig.symfony.com/doc/3.x/)
- [Twig for Template Designers](https://twig.symfony.com/doc/3.x/templates.html)
- [Twig Filters Reference](https://twig.symfony.com/doc/3.x/filters/index.html)
- [WordPress Template Tags](https://developer.wordpress.org/themes/basics/template-tags/)

---

## ✅ Checklist (Day 1 + Bugfix)

- [x] Add `twig/twig` dependency to composer.json
- [x] Install Twig via Docker Composer
- [x] Create Twig Template Engine class
- [x] Initialize Twig with caching
- [x] Add WordPress filters (wp_excerpt, wp_date, wp_kses)
- [x] Add WordPress functions (get_permalink, get_post_meta)
- [x] Implement legacy syntax conversion
- [x] Implement deprecated variable mappings
- [x] Implement fallback to regex
- [x] Integrate with Variable Manager
- [x] Load Composer autoloader in polytrans.php
- [x] Load Twig Engine in class-polytrans.php
- [x] Update .gitignore for cache/
- [x] Fix Docker permissions for vendor/
- [x] Document Phase 0.1 progress
- [x] **BUGFIX**: Lazy load Twig Engine (avoid fatal error)
  - Removed require_once from class-polytrans.php
  - Added load_twig_engine() in Variable Manager
  - Tested on transeu localhost (working)

---

**Status**: ✅ Day 1 Complete + Lazy Load Bugfix ✅, Ready for Day 2
**Next Session**: Refactor Post Data Provider (context structure redesign)
