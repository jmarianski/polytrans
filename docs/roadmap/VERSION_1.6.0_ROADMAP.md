# Roadmap: Version 1.6.0 - Multi-Provider Support

## Cel

Dodanie pełnego wsparcia dla Claude i Gemini jako providerów z możliwością używania ich asystentów w workflow i translation paths.

## Główne Funkcjonalności

### 1. Claude Provider

#### Backend
- [ ] `ClaudeProvider` implementujący `TranslationProviderInterface`
- [ ] `ClaudeSettingsProvider` implementujący `SettingsProviderInterface`
- [ ] `ClaudeAssistantClientAdapter` implementujący `AIAssistantClientInterface`
- [ ] Rejestracja w `ProviderRegistry`
- [ ] Implementacja `validate_api_key()` dla Claude
- [ ] Implementacja `load_assistants()` dla Claude Projects
- [ ] Implementacja `load_models()` dla Claude models

#### Frontend
- [ ] HTML settings UI dla Claude (API key, model selection)
- [ ] Data attributes dla uniwersalnego JS systemu
- [ ] Lokalizacja strings dla Claude

#### Integration
- [ ] Dodanie do `AIAssistantClientFactory` (detekcja `project_xxx` ID)
- [ ] Dodanie do `polytrans_load_assistants` endpoint
- [ ] Dodanie do workflow predefined assistant dropdown
- [ ] Dodanie do managed assistants provider list

### 2. Gemini Provider

#### Backend
- [ ] `GeminiProvider` implementujący `TranslationProviderInterface`
- [ ] `GeminiSettingsProvider` implementujący `SettingsProviderInterface`
- [ ] `GeminiAssistantClientAdapter` implementujący `AIAssistantClientInterface`
- [ ] Rejestracja w `ProviderRegistry`
- [ ] Implementacja `validate_api_key()` dla Gemini
- [ ] Implementacja `load_assistants()` dla Gemini Tuned Models
- [ ] Implementacja `load_models()` dla Gemini models

#### Frontend
- [ ] HTML settings UI dla Gemini (API key, model selection)
- [ ] Data attributes dla uniwersalnego JS systemu
- [ ] Lokalizacja strings dla Gemini

#### Integration
- [ ] Dodanie do `AIAssistantClientFactory` (detekcja `tuned_model_xxx` ID)
- [ ] Dodanie do `polytrans_load_assistants` endpoint
- [ ] Dodanie do workflow predefined assistant dropdown
- [ ] Dodanie do managed assistants provider list

### 3. Universal Provider JS System

#### Implementation
- [ ] Stworzyć `assets/js/settings/provider-settings-universal.js`
- [ ] Dodać endpoint `polytrans_validate_provider_key` z parametrem `provider_id`
- [ ] Dodać metodę `validate_api_key()` do `SettingsProviderInterface`
- [ ] Zaimplementować w `OpenAISettingsProvider`, `ClaudeSettingsProvider`, `GeminiSettingsProvider`
- [ ] Zaktualizować HTML wszystkich providerów (dodać data attributes)
- [ ] Zaktualizować `openai-integration.js` aby używał uniwersalnego systemu (opcjonalnie)

#### Migration
- [ ] Zachować backward compatibility (stare ID selektorów)
- [ ] Dodać data attributes obok istniejących ID
- [ ] Przetestować z OpenAI, Claude, Gemini

### 4. Universal Endpoints Refactoring

#### Endpoints to Refactor
- [ ] `polytrans_load_assistants` - przenieść z `OpenAISettingsProvider` do `TranslationSettings` lub osobnej klasy
- [ ] Iterować przez wszystkie providery z manifestami
- [ ] Wywoływać `load_assistants()` jeśli metoda istnieje

#### Interface Extensions
- [ ] Dodać `load_assistants(array $settings): array` do `SettingsProviderInterface`
- [ ] Dodać `load_models(array $settings): array` do `SettingsProviderInterface` (opcjonalnie)

### 5. Documentation

- [ ] Zaktualizować dokumentację dla deweloperów
- [ ] Przykład dodania nowego providera
- [ ] Przykład użycia uniwersalnego JS systemu
- [ ] Migration guide z OpenAI-specific do universal

## Plan Implementacji

### Faza 1: Universal JS System (Podstawa)
1. Stworzyć uniwersalny JS manager
2. Dodać uniwersalny endpoint walidacji
3. Zaktualizować OpenAI aby używał uniwersalnego systemu
4. Przetestować z OpenAI

### Faza 2: Claude Provider
1. Backend implementation
2. Frontend UI
3. Integration z workflow i translation paths
4. Testing

### Faza 3: Gemini Provider
1. Backend implementation
2. Frontend UI
3. Integration z workflow i translation paths
4. Testing

### Faza 4: Universal Endpoints
1. Refactor `polytrans_load_assistants`
2. Dodać metody `load_assistants()` do interfejsu
3. Zaimplementować w wszystkich providerach
4. Testing

### Faza 5: Documentation & Polish
1. Dokumentacja
2. Przykłady
3. Migration guide
4. Final testing

## Breaking Changes

**Brak** - wszystkie zmiany są backward compatible:
- Stare endpointy nadal działają
- Stare ID selektorów nadal działają
- Nowe providery są opcjonalne

## Testing Checklist

- [ ] OpenAI provider działa jak wcześniej
- [ ] Claude provider można skonfigurować
- [ ] Gemini provider można skonfigurować
- [ ] Workflow predefined assistant pokazuje wszystkich providerów
- [ ] Translation paths działają z wszystkimi providerami
- [ ] Managed assistants można tworzyć dla wszystkich providerów
- [ ] Uniwersalny JS działa dla wszystkich providerów
- [ ] Walidacja API key działa dla wszystkich providerów
- [ ] Refresh models działa dla wszystkich providerów

## Estimated Timeline

- **Faza 1**: 2-3 dni
- **Faza 2**: 3-4 dni
- **Faza 3**: 3-4 dni
- **Faza 4**: 2-3 dni
- **Faza 5**: 1-2 dni

**Total**: ~2-3 tygodnie

