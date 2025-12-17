# Roadmap: Version 1.6.0 - Full Provider Extensibility

## Cel Główny

**Pełne wsparcie dla dowolnego providera** - zarówno wbudowanych (Claude, Gemini) jak i zewnętrznych pluginów (np. `polytrans_deepseek`). System ma być w pełni ekstensybilny, aby deweloperzy mogli łatwo dodawać własnych providerów.

## Główne Funkcjonalności

### 1. Ekstensybilność dla Zewnętrznych Pluginów (PRIORYTET)

#### Hooki i Filtry
- [x] Rozszerzyć `polytrans_register_providers` hook (już istnieje, działa)
- [x] Dodać `polytrans_assistant_client_factory_create` - filter dla adapterów asystentów
- [x] Dodać `polytrans_assistant_client_factory_get_provider_id` - filter dla provider ID detection
- [x] Dodać `polytrans_load_assistants_{provider_id}` - hook dla ładowania asystentów
- [ ] Dodać `polytrans_load_models_{provider_id}` - hook dla ładowania modeli (opcjonalnie)
- [x] Dodać `polytrans_validate_api_key_{provider_id}` - hook dla walidacji API key

#### Universal Endpoints
- [x] `polytrans_load_assistants` - iteruje przez wszystkie providery (wbudowane + zewnętrzne)
- [ ] `polytrans_load_models` - iteruje przez wszystkie providery (opcjonalnie, już działa przez `polytrans_get_provider_models`)
- [x] `polytrans_validate_provider_key` - uniwersalna walidacja dla wszystkich providerów

#### Dokumentacja dla Deweloperów
- [x] Przykładowy plugin `polytrans_deepseek` jako template
- [x] Kompletny guide "Jak dodać własnego providera" (`PROVIDER_EXTENSIBILITY_GUIDE.md`)
- [x] API reference dla wszystkich hooków i filtrów (w guide)
- [x] Przykłady użycia w różnych scenariuszach (w przykładowym pluginie)

### 2. Claude Provider (Wbudowany) ✅ ZREALIZOWANE

#### Backend
- [x] `ClaudeProvider` implementujący `TranslationProviderInterface` ✅
- [x] `ClaudeSettingsProvider` implementujący `SettingsProviderInterface` ✅
- [x] `ClaudeChatClientAdapter` implementujący `ChatClientInterface` ✅
- [x] Rejestracja w `ProviderRegistry` ✅
- [x] Implementacja `validate_api_key()` dla Claude ✅
- [x] Implementacja `load_assistants()` dla Claude (zwraca pustą tablicę - Claude nie ma Assistants API) ✅
- [x] Implementacja `load_models()` dla Claude models ✅

#### Frontend
- [x] HTML settings UI dla Claude (używa universal UI) ✅
- [x] Data attributes dla uniwersalnego JS systemu ✅
- [x] Lokalizacja strings dla Claude ✅
- [x] UI notice informujący że Claude nie ma Assistants API ✅

### 3. Gemini Provider (Wbudowany) ✅ ZREALIZOWANE

#### Backend
- [x] `GeminiProvider` implementujący `TranslationProviderInterface` ✅
- [x] `GeminiSettingsProvider` implementujący `SettingsProviderInterface` ✅
- [x] `GeminiChatClientAdapter` implementujący `ChatClientInterface` ✅
- [x] `GeminiAssistantClientAdapter` implementujący `AIAssistantClientInterface` ✅ (placeholder)
- [x] Rejestracja w `ProviderRegistry` ✅
- [x] Implementacja `validate_api_key()` dla Gemini ✅
- [x] Implementacja `load_assistants()` dla Gemini Agents/Tuned Models ✅
- [x] Implementacja `load_models()` dla Gemini models ✅
- [x] Filtrowanie modeli (wykluczenie image/video generation models) ✅

#### Frontend
- [x] HTML settings UI dla Gemini (używa universal UI) ✅
- [x] Data attributes dla uniwersalnego JS systemu ✅
- [x] Lokalizacja strings dla Gemini ✅

### 4. Universal Provider JS System ✅ ZREALIZOWANE

#### Implementation
- [x] Stworzyć `assets/js/settings/provider-settings-universal.js` ✅
- [x] Dodać endpoint `polytrans_validate_provider_key` z parametrem `provider_id` ✅
- [x] Dodać metodę `validate_api_key()` do `SettingsProviderInterface` ✅
- [x] Zaimplementować w `OpenAISettingsProvider` ✅
- [x] Zaktualizować HTML wszystkich providerów (dodać data attributes) ✅ (universal UI automatycznie)
- [x] Usunąć `openai-integration.js` i `openai-integration.css` ✅ (OpenAI używa teraz universal system)
- [x] Force refresh models functionality ✅
- [x] "None selected" option dla modeli ✅

#### Migration
- [x] Zachować backward compatibility (stare ID selektorów) ✅
- [x] Dodać data attributes obok istniejących ID ✅ (universal UI używa data attributes)
- [ ] Przetestować z OpenAI, Claude, Gemini, DeepSeek (przykładowy zewnętrzny)

### 5. Universal Endpoints Refactoring ✅ ZREALIZOWANE

#### Endpoints to Refactor
- [x] `polytrans_load_assistants` - iteruje przez wszystkie providery ✅
- [x] Iterować przez wszystkie providery z manifestami (wbudowane + zewnętrzne) ✅
- [x] Wywoływać `load_assistants()` jeśli metoda istnieje ✅
- [x] `polytrans_get_provider_models` - uniwersalny endpoint dla modeli ✅
- [x] Force refresh support dla modeli ✅

#### Interface Extensions
- [x] Dodać `load_assistants(array $settings): array` do `SettingsProviderInterface` ✅
- [x] Dodać `load_models(array $settings): array` do `SettingsProviderInterface` ✅

## Przykładowy Plugin: polytrans_deepseek

### Struktura
```
polytrans-deepseek/
├── polytrans-deepseek.php          # Main plugin file
├── includes/
│   ├── DeepSeekProvider.php        # TranslationProviderInterface
│   ├── DeepSeekSettingsProvider.php # SettingsProviderInterface
│   └── DeepSeekAssistantClientAdapter.php # AIAssistantClientInterface
└── assets/
    └── js/
        └── deepseek-integration.js  # Opcjonalne, jeśli potrzebne
```

### Minimalna Implementacja

**polytrans-deepseek.php:**
```php
<?php
/**
 * Plugin Name: PolyTrans DeepSeek Provider
 * Description: Adds DeepSeek as a translation provider for PolyTrans
 * Version: 1.0.0
 * Requires at least: 5.0
 * Requires PHP: 8.1
 */

if (!defined('ABSPATH')) {
    exit;
}

// Register provider
add_action('polytrans_register_providers', function($registry) {
    require_once __DIR__ . '/includes/DeepSeekProvider.php';
    $registry->register_provider(new DeepSeekProvider());
});

// Register assistant client adapter
add_action('polytrans_register_assistant_clients', function($factory) {
    require_once __DIR__ . '/includes/DeepSeekAssistantClientAdapter.php';
    // Factory automatycznie wykryje adapter przez assistant ID format
});
```

**includes/DeepSeekProvider.php:**
```php
<?php
class DeepSeekProvider implements \PolyTrans\Providers\TranslationProviderInterface {
    public function get_id() { return 'deepseek'; }
    public function get_name() { return 'DeepSeek'; }
    public function get_description() { return 'DeepSeek AI translation provider'; }
    public function get_settings_provider_class() { return DeepSeekSettingsProvider::class; }
    public function has_settings_ui() { return true; }
    public function translate($content, $source_lang, $target_lang, $settings) { /* ... */ }
    public function is_configured($settings) { /* ... */ }
}
```

**includes/DeepSeekSettingsProvider.php:**
```php
<?php
class DeepSeekSettingsProvider implements \PolyTrans\Providers\SettingsProviderInterface {
    public function get_provider_id() { return 'deepseek'; }
    public function get_tab_label() { return 'DeepSeek Configuration'; }
    public function get_provider_manifest(array $settings) {
        return [
            'provider_id' => 'deepseek',
            'capabilities' => ['assistants', 'translation'],
            'assistants_endpoint' => 'https://api.deepseek.com/v1/assistants',
            'api_key_setting' => 'deepseek_api_key',
            'api_key_configured' => !empty($settings['deepseek_api_key']),
        ];
    }
    public function validate_api_key(string $api_key): bool { /* ... */ }
    public function load_assistants(array $settings): array { /* ... */ }
    public function render_settings_ui(...) { /* ... */ }
    // ... pozostałe metody interfejsu
}
```

## Plan Implementacji

### Faza 1: Ekstensybilność i Hooki (PRIORYTET)
1. ✅ Rozszerzyć hooki dla zewnętrznych pluginów
2. ✅ Stworzyć przykładowy plugin `polytrans_deepseek`
3. ✅ Zaktualizować `AIAssistantClientFactory` aby wspierał hooki
4. ✅ Zaktualizować `polytrans_load_assistants` aby używał hooków
5. ✅ Przetestować z przykładowym pluginem

### Faza 2: Universal JS System
1. Stworzyć uniwersalny JS manager
2. Dodać uniwersalny endpoint walidacji
3. Zaktualizować OpenAI aby używał uniwersalnego systemu
4. Przetestować z OpenAI i przykładowym pluginem

### Faza 3: Claude Provider (Wbudowany)
1. Backend implementation
2. Frontend UI
3. Integration z workflow i translation paths
4. Testing

### Faza 4: Gemini Provider (Wbudowany)
1. Backend implementation
2. Frontend UI
3. Integration z workflow i translation paths
4. Testing

### Faza 5: Universal Endpoints Refactoring
1. Refactor `polytrans_load_assistants`
2. Dodać metody `load_assistants()` do interfejsu
3. Zaimplementować w wszystkich providerach
4. Testing z wbudowanymi i zewnętrznymi

### Faza 6: Documentation & Examples
1. Kompletny guide "Jak dodać własnego providera"
2. Przykładowy plugin `polytrans_deepseek` jako template
3. API reference dla wszystkich hooków
4. Przykłady użycia
5. Migration guide

## Breaking Changes

**Brak** - wszystkie zmiany są backward compatible:
- Stare endpointy nadal działają
- Stare ID selektorów nadal działają
- Nowe providery są opcjonalne
- Hooki są dodatkowe, nie zastępują istniejących mechanizmów

## Testing Checklist

### Wbudowane Providery
- [ ] OpenAI provider działa jak wcześniej
- [ ] Claude provider można skonfigurować
- [ ] Gemini provider można skonfigurować

### Zewnętrzne Providery (Przykład: DeepSeek)
- [ ] Plugin `polytrans_deepseek` można zainstalować
- [ ] DeepSeek pojawia się w Enabled Translation Providers
- [ ] DeepSeek ma własny tab w settings
- [ ] DeepSeek assistants są widoczne w workflow dropdown
- [ ] DeepSeek można używać w translation paths
- [ ] DeepSeek managed assistants działają
- [ ] Walidacja DeepSeek API key działa
- [ ] Refresh DeepSeek models działa

### Universal System
- [ ] Uniwersalny JS działa dla wszystkich providerów (wbudowanych i zewnętrznych)
- [ ] Universal endpoints działają dla wszystkich providerów
- [ ] Hooki działają poprawnie dla zewnętrznych pluginów

## Estimated Timeline

- **Faza 1**: 3-4 dni (ekstensybilność i hooki)
- **Faza 2**: 2-3 dni (universal JS)
- **Faza 3**: 3-4 dni (Claude)
- **Faza 4**: 3-4 dni (Gemini)
- **Faza 5**: 2-3 dni (universal endpoints)
- **Faza 6**: 2-3 dni (dokumentacja)

**Total**: ~3-4 tygodnie
