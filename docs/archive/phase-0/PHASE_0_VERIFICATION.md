# Phase 0 Verification Guide

## 🎯 Cel
Ten dokument opisuje jak zweryfikować, że **Phase 0** działa poprawnie.

## ✅ Quick Check - Pliki

Wszystkie kluczowe pliki Phase 0 są na miejscu:

```bash
cd /home/jm/projects/trans-info/plugins/polytrans

# Phase 0.0: Database
ls includes/postprocessing/managers/class-workflow-storage-manager.php

# Phase 0.1 Day 1: Twig
ls includes/templating/class-twig-template-engine.php
grep "twig/twig" composer.json

# Phase 0.1 Day 2: Variables
grep "short alias" includes/postprocessing/providers/class-post-data-provider.php

# Phase 0.2: Context Refresh
grep "PolyTrans_Post_Data_Provider" includes/postprocessing/class-workflow-output-processor.php
```

## 📋 Manual Testing Checklist

### 1. UI Test (Phase 0.1 Day 2 + UI Redesign)

**Desktop (szerokość > 767px):**
- [ ] Otwórz: WordPress Admin > PolyTrans > Workflows > Edit workflow
- [ ] Rozwiń "AI Assistant" step
- [ ] **System Prompt field:**
  - [ ] Textarea po lewej (zajmuje większość szerokości)
  - [ ] Variable sidebar po prawej (150px, sticky)
  - [ ] Sidebar zawiera pills z nazwami zmiennych
  - [ ] Kliknięcie pill wstawia `{{ variable_name }}` do textarea
- [ ] **User Message field:**
  - [ ] Identyczny layout jak System Prompt
  - [ ] Osobny sidebar dla tego pola

**Mobile (szerokość < 767px):**
- [ ] Otwórz na telefonie lub zmień szerokość okna
- [ ] Pills są **nad** textarea (nie z boku)
- [ ] Pills w 2-3 wierszach z horizontal scroll
- [ ] Kliknięcie pill wstawia zmienną

### 2. Variable Structure Test (Phase 0.1 Day 2)

**Stare zmienne (backward compatibility):**
```twig
{{ post_title }}
{{ post_content }}
{{ original_post.title }}
{{ translated_post.content }}
```

**Nowe short aliases:**
```twig
{{ title }}              {# alias for post_title #}
{{ content }}            {# alias for post_content #}
{{ excerpt }}            {# alias for post_excerpt #}

{{ original.title }}     {# alias for original_post.title #}
{{ original.content }}   {# alias for original_post.content #}
{{ original.meta.seo_title }}  {# meta field access #}

{{ translated.title }}   {# alias for translated_post.title #}
{{ translated.content }} {# alias for translated_post.content #}
{{ translated.meta.custom_field }}  {# meta field access #}
```

**Test workflow:**
1. Utwórz nowy workflow
2. Dodaj AI Assistant step
3. **System Prompt:**
   ```
   You are a content reviewer. Analyze the following post.
   ```
4. **User Message:**
   ```
   Original Title: {{ original.title }}
   Translated Title: {{ translated.title }}
   
   Content: {{ content }}
   
   SEO Title: {{ translated.meta.seo_title }}
   
   Please review and suggest improvements.
   ```
5. Zapisz workflow
6. Przetestuj na jakimś poście
7. Sprawdź logi - zmienne powinny być zinterpolowane

### 3. Context Refresh Test (Phase 0.2)

**Scenariusz: Multi-step workflow z modyfikacją tytułu**

1. Utwórz workflow z 2 krokami:

**Step 1: Update Title**
- Type: AI Assistant
- System Prompt: `You are a title optimizer.`
- User Message: `Improve this title: {{ title }}`
- Output Actions:
  - Action: `update_post_title`
  - Value: `{{ ai_response }}`

**Step 2: Use Updated Title**
- Type: AI Assistant  
- System Prompt: `You are a content writer.`
- User Message: `Write intro for article titled: {{ title }}`
- Output Actions:
  - Action: `prepend_to_post_content`
  - Value: `{{ ai_response }}`

2. Uruchom workflow na teście (test mode)
3. **Sprawdź logi:**
   - Po Step 1: `title` powinien być zaktualizowany
   - W Step 2: `{{ title }}` powinien pokazywać NOWY tytuł (nie stary)

**Oczekiwane zachowanie:**
- ✅ Step 2 widzi zmiany ze Step 1
- ✅ Context jest odświeżany między krokami
- ✅ Test mode = production mode (ta sama logika)

### 4. Twig Integration Test (Phase 0.1 Day 1)

**Podstawowe features:**

```twig
{# Variables #}
{{ title }}

{# Filters #}
{{ content|length }}
{{ content|upper }}
{{ content|slice(0, 100) }}

{# Conditionals #}
{% if title %}
Title exists: {{ title }}
{% else %}
No title
{% endif %}

{# Loops (for arrays) #}
{% for article in recent_articles %}
- {{ article.title }}
{% endfor %}
```

**Test workflow:**
1. Utwórz workflow z User Message:
   ```twig
   Title: {{ title }}
   Length: {{ content|length }}
   
   {% if excerpt %}
   Excerpt: {{ excerpt }}
   {% else %}
   No excerpt available
   {% endif %}
   ```
2. Uruchom na poście z excertem i bez
3. Sprawdź logi - Twig powinien działać

### 5. Database Migration Test (Phase 0.0)

**Sprawdź tabelę:**
```sql
-- W phpMyAdmin lub mysql CLI
SHOW TABLES LIKE 'wp_polytrans_workflows';
DESCRIBE wp_polytrans_workflows;
SELECT * FROM wp_polytrans_workflows;
```

**Oczekiwane kolumny:**
- `id` (bigint, auto_increment, primary key)
- `workflow_id` (varchar(50), unique)
- `name` (varchar(255))
- `language` (varchar(10))
- `enabled` (tinyint(1))
- `steps` (longtext) - JSON
- `triggers` (longtext) - JSON
- `created_at` (datetime)
- `updated_at` (datetime)

**Test migracji:**
1. Jeśli masz stare workflows w `wp_options` (klucz: `polytrans_workflows_*`)
2. Uruchom: `php test-migration.php` (jeśli istnieje)
3. Sprawdź czy workflows są w tabeli

**Uwaga:** Migracja jest opcjonalna w Phase 0. Główny cel to **przygotowanie infrastruktury**.

## 🔍 Debug Tips

### Logi workflow
```php
// W WordPress admin
PolyTrans > Logs

// Lub w bazie
SELECT * FROM wp_polytrans_logs 
WHERE workflow_id = 'your_workflow_id' 
ORDER BY created_at DESC 
LIMIT 10;
```

### JavaScript Console
```javascript
// Otwórz DevTools (F12)
// Sprawdź czy są błędy przy klikaniu pills
// Sprawdź czy lastFocusedTextarea jest ustawiony
```

### PHP Errors
```bash
# WordPress debug log
tail -f /path/to/wp-content/debug.log

# Lub włącz WP_DEBUG w wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);
```

## ✅ Success Criteria

Phase 0 działa poprawnie jeśli:

1. **UI:**
   - ✅ Variable sidebar/pills są widoczne i działają
   - ✅ Kliknięcie pill wstawia zmienną do textarea
   - ✅ Responsive layout (desktop sidebar, mobile pills)

2. **Variables:**
   - ✅ Stare zmienne działają (backward compatibility)
   - ✅ Nowe short aliases działają (`{{ original.title }}`)
   - ✅ Meta access działa (`{{ original.meta.KEY }}`)

3. **Context Refresh:**
   - ✅ Multi-step workflows widzą zmiany z poprzednich kroków
   - ✅ Test mode = production mode
   - ✅ Wszystkie struktury są aktualizowane (top-level, nested, aliases)

4. **Twig:**
   - ✅ Interpolacja zmiennych działa
   - ✅ Filtry działają (`|length`, `|upper`, etc.)
   - ✅ Conditionals działają (`{% if %}`)
   - ✅ Loops działają (`{% for %}`)

5. **Database:**
   - ✅ Tabela `wp_polytrans_workflows` istnieje
   - ✅ Ma poprawną strukturę
   - ✅ Workflows są zapisywane i odczytywane

## 🚀 Quick Test Command

```bash
cd /home/jm/projects/trans-info/plugins/polytrans

# Sprawdź wszystkie pliki
echo "=== Phase 0 Files Check ==="
ls -1 includes/templating/class-twig-template-engine.php \
     includes/postprocessing/providers/class-post-data-provider.php \
     includes/postprocessing/class-workflow-output-processor.php \
     includes/postprocessing/managers/class-workflow-storage-manager.php 2>&1 | \
     sed 's/^/✅ /'

# Sprawdź wersję
echo ""
echo "=== Version ==="
grep "Version:" polytrans.php

# Sprawdź changelog
echo ""
echo "=== Recent Changes ==="
grep "^\[1\.3\." CHANGELOG.md | head -5
```

## 📝 Next Steps

Po zweryfikowaniu Phase 0, możesz przejść do:

- **Phase 1:** Assistants System (centralizacja konfiguracji AI)
- **Deep Unit Tests:** Testy dla Phase 0 komponentów
- **Phase 2:** Advanced features (custom filters, providers, etc.)

---

**Wersja dokumentu:** 1.0  
**Data:** 2025-12-10  
**Phase:** 0 (Complete)

