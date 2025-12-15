# Roadmap: Version 1.7.0 - Enhanced Extensibility & Behavior Modification

## Cel Główny

**Pełna ekstensybilność i modyfikacja zachowania** - umożliwienie zewnętrznym pluginom i deweloperom łatwego dodawania translacyjnych providerów oraz modyfikacji procesu translacji bez konieczności edycji kodu głównego pluginu.

## Główne Funkcjonalności

### 1. Hooki do Modyfikacji Procesu Translacji (PRIORYTET)

#### Pre-Translation Hooks
```php
// Przed przygotowaniem contentu do translacji
do_action('polytrans_before_prepare_content', $post_id, $source_lang, $target_lang, $content);

// Po przygotowaniu contentu, przed wysłaniem do providera
$content = apply_filters('polytrans_prepare_content', $content, $post_id, $source_lang, $target_lang);

// Przed wywołaniem providera
do_action('polytrans_before_translate', $content, $source_lang, $target_lang, $provider_id, $settings);
$content = apply_filters('polytrans_pre_translate', $content, $source_lang, $target_lang, $provider_id, $settings);
```

#### Post-Translation Hooks
```php
// Po otrzymaniu wyniku od providera, przed przetworzeniem
$translation_result = apply_filters('polytrans_post_translate', $translation_result, $content, $source_lang, $target_lang, $provider_id, $settings);

// Przed utworzeniem przetłumaczonego posta
do_action('polytrans_before_create_translated_post', $translation_result, $original_post_id, $source_lang, $target_lang);
$translation_result = apply_filters('polytrans_pre_create_translated_post', $translation_result, $original_post_id, $source_lang, $target_lang);

// Po utworzeniu przetłumaczonego posta
do_action('polytrans_translation_complete', $original_post_id, $translated_post_id, $source_lang, $target_lang, $translation_result);
```

#### Translation Path Hooks
```php
// Przed wykonaniem kroku w ścieżce translacji
do_action('polytrans_before_path_step', $content, $source_lang, $target_lang, $assistant_id, $step_index);
$content = apply_filters('polytrans_pre_path_step', $content, $source_lang, $target_lang, $assistant_id, $step_index);

// Po wykonaniu kroku w ścieżce translacji
$step_result = apply_filters('polytrans_post_path_step', $step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index);
do_action('polytrans_after_path_step', $step_result, $content, $source_lang, $target_lang, $assistant_id, $step_index);
```

**Lokalizacja implementacji:**
- `includes/Core/BackgroundProcessor.php` - główny proces translacji
- `includes/Core/TranslationPathExecutor.php` - wykonanie ścieżek translacji
- `includes/Receiver/TranslationCoordinator.php` - przetwarzanie wyników

### 2. Ulepszona Ekstensybilność dla Translacyjnych Providerów

#### Translation Provider Middleware Pattern
```php
interface TranslationProviderMiddlewareInterface {
    public function before_translate($content, $source_lang, $target_lang, $settings);
    public function after_translate($result, $content, $source_lang, $target_lang, $settings);
}

// Rejestracja middleware
add_filter('polytrans_provider_middleware', function($middleware, $provider_id) {
    $middleware[] = new CustomTranslationMiddleware();
    return $middleware;
}, 10, 2);
```

#### Provider Behavior Modification
```php
// Modyfikacja zachowania providera bez zmiany jego kodu
apply_filters('polytrans_provider_translate_{provider_id}', $result, $content, $source_lang, $target_lang, $settings);

// Przechwycenie i modyfikacja requestu przed wysłaniem do API
$api_request = apply_filters('polytrans_provider_api_request_{provider_id}', $api_request, $content, $source_lang, $target_lang);

// Przechwycenie i modyfikacja odpowiedzi z API
$api_response = apply_filters('polytrans_provider_api_response_{provider_id}', $api_response, $api_request);
```

### 3. Translation Strategy Pattern

#### Strategy Interface
```php
interface TranslationStrategyInterface {
    public function should_use_strategy($content, $source_lang, $target_lang, $settings);
    public function translate($content, $source_lang, $target_lang, $settings);
}

// Rejestracja strategii
add_filter('polytrans_translation_strategies', function($strategies) {
    $strategies[] = new CustomTranslationStrategy();
    return $strategies;
});
```

**Przykłady użycia:**
- Strategia dla długich postów (chunking)
- Strategia dla specjalnych typów contentu (tabele, listy)
- Strategia dla contentu z obrazami (OCR + translation)
- Strategia dla contentu technicznego (terminologia)

### 4. Content Preprocessing & Postprocessing Hooks

#### Content Preprocessing
```php
// Przed przygotowaniem contentu do translacji
$content = apply_filters('polytrans_preprocess_content', $content, $post_id, $source_lang, $target_lang);

// Dla każdego pola contentu osobno
$title = apply_filters('polytrans_preprocess_title', $title, $post_id, $source_lang, $target_lang);
$content_text = apply_filters('polytrans_preprocess_content_text', $content_text, $post_id, $source_lang, $target_lang);
$excerpt = apply_filters('polytrans_preprocess_excerpt', $excerpt, $post_id, $source_lang, $target_lang);
$meta = apply_filters('polytrans_preprocess_meta', $meta, $post_id, $source_lang, $target_lang);
```

#### Content Postprocessing
```php
// Po otrzymaniu przetłumaczonego contentu
$translated_content = apply_filters('polytrans_postprocess_content', $translated_content, $original_content, $post_id, $source_lang, $target_lang);

// Dla każdego pola osobno
$translated_title = apply_filters('polytrans_postprocess_title', $translated_title, $original_title, $post_id, $source_lang, $target_lang);
$translated_content_text = apply_filters('polytrans_postprocess_content_text', $translated_content_text, $original_content_text, $post_id, $source_lang, $target_lang);
```

### 5. Provider Response Interception

#### Response Modification
```php
// Przechwycenie odpowiedzi z providera przed przetworzeniem
$provider_response = apply_filters('polytrans_provider_response', $provider_response, $provider_id, $content, $source_lang, $target_lang);

// Dla konkretnego providera
$provider_response = apply_filters('polytrans_provider_response_{provider_id}', $provider_response, $content, $source_lang, $target_lang);
```

#### Error Handling & Retry Logic
```php
// Hook przed retry w przypadku błędu
$should_retry = apply_filters('polytrans_should_retry_translation', true, $error, $attempt, $content, $source_lang, $target_lang);
$retry_delay = apply_filters('polytrans_retry_delay', 5, $attempt, $error);

// Hook po retry
do_action('polytrans_translation_retry', $attempt, $error, $content, $source_lang, $target_lang);
```

### 6. Translation Context & Metadata

#### Context Object
```php
class TranslationContext {
    public $post_id;
    public $source_lang;
    public $target_lang;
    public $provider_id;
    public $settings;
    public $metadata; // Custom metadata
}

// Przekazywanie contextu przez hooki
$context = apply_filters('polytrans_translation_context', $context, $post_id, $source_lang, $target_lang);
```

### 7. Conditional Translation Rules

#### Rule Engine
```php
interface TranslationRuleInterface {
    public function matches($content, $source_lang, $target_lang, $settings);
    public function apply($content, $source_lang, $target_lang, $settings);
}

// Rejestracja reguł
add_filter('polytrans_translation_rules', function($rules) {
    $rules[] = new CustomTranslationRule();
    return $rules;
});
```

**Przykłady:**
- Skip translation dla określonych typów postów
- Użyj innego providera dla określonych języków
- Modyfikuj content przed translacją na podstawie warunków

## Implementacja

### Faza 1: Podstawowe Hooki (1-2 tygodnie)
- [ ] Dodanie hooków `polytrans_before_translate` i `polytrans_after_translate`
- [ ] Dodanie filtrów `polytrans_pre_translate` i `polytrans_post_translate`
- [ ] Dodanie hooków w `BackgroundProcessor::process_translation()`
- [ ] Dodanie hooków w `TranslationPathExecutor::execute_step()`
- [ ] Dokumentacja hooków

### Faza 2: Content Preprocessing/Postprocessing (1 tydzień)
- [ ] Dodanie filtrów dla preprocessing contentu
- [ ] Dodanie filtrów dla postprocessing contentu
- [ ] Dodanie hooków przed/po utworzeniem przetłumaczonego posta
- [ ] Dokumentacja

### Faza 3: Provider Behavior Modification (1-2 tygodnie)
- [ ] Implementacja middleware pattern dla providerów
- [ ] Dodanie filtrów `polytrans_provider_translate_{provider_id}`
- [ ] Dodanie filtrów dla API request/response
- [ ] Dokumentacja z przykładami

### Faza 4: Translation Strategy Pattern (1 tydzień)
- [ ] Implementacja `TranslationStrategyInterface`
- [ ] System wyboru strategii
- [ ] Przykładowe strategie (chunking, OCR, etc.)
- [ ] Dokumentacja

### Faza 5: Advanced Features (1-2 tygodnie)
- [ ] Translation Context object
- [ ] Conditional Translation Rules
- [ ] Error Handling & Retry Logic hooks
- [ ] Kompletna dokumentacja

## Przykłady Użycia

### Przykład 1: Modyfikacja Contentu Przed Translacją
```php
add_filter('polytrans_pre_translate', function($content, $source_lang, $target_lang, $provider_id, $settings) {
    // Usuń HTML komentarze przed translacją
    $content['content'] = preg_replace('/<!--.*?-->/s', '', $content['content']);
    return $content;
}, 10, 5);
```

### Przykład 2: Modyfikacja Wyniku Po Translacji
```php
add_filter('polytrans_post_translate', function($result, $content, $source_lang, $target_lang, $provider_id, $settings) {
    if ($result['success']) {
        // Dodaj watermark do przetłumaczonego contentu
        $result['translated_content']['content'] .= "\n\n[Translated by PolyTrans]";
    }
    return $result;
}, 10, 6);
```

### Przykład 3: Custom Translation Strategy
```php
class LongPostChunkingStrategy implements TranslationStrategyInterface {
    public function should_use_strategy($content, $source_lang, $target_lang, $settings) {
        return strlen($content['content']) > 10000;
    }
    
    public function translate($content, $source_lang, $target_lang, $settings) {
        // Implementacja chunkingu długich postów
        // ...
    }
}

add_filter('polytrans_translation_strategies', function($strategies) {
    $strategies[] = new LongPostChunkingStrategy();
    return $strategies;
});
```

### Przykład 4: Provider-Specific Behavior
```php
// Modyfikacja tylko dla OpenAI
add_filter('polytrans_provider_translate_openai', function($result, $content, $source_lang, $target_lang, $settings) {
    // Custom logic dla OpenAI
    return $result;
}, 10, 4);
```

## Korzyści

1. **Pełna ekstensybilność** - deweloperzy mogą modyfikować zachowanie bez edycji kodu głównego
2. **Modularność** - łatwe dodawanie nowych funkcjonalności przez hooki
3. **Testowalność** - hooki można łatwo testować osobno
4. **Backward compatibility** - istniejący kod działa bez zmian
5. **Flexibility** - możliwość modyfikacji na różnych poziomach (content, provider, strategy)

## Uwagi

- Wszystkie hooki powinny być dobrze udokumentowane z przykładami
- Należy zachować backward compatibility
- Hooki powinny być wydajne (minimalne overhead)
- Należy rozważyć cache dla niektórych hooków (jeśli potrzebne)

