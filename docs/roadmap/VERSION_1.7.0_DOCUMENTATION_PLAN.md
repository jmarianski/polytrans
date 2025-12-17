# Version 1.7.0 - Documentation Improvement Plan

**Status**: In Progress  
**Created**: 2025-12-16  
**Part of**: Version 1.7.0 Comprehensive Refactoring

---

## 🎯 Cel

Aktualizacja i poprawa dokumentacji, aby była:
- ✅ **Aktualna** - zgodna z rzeczywistym stanem kodu
- ✅ **Kompletna** - zawiera wszystkie potrzebne informacje
- ✅ **Użyteczna** - łatwa do znalezienia i zrozumienia
- ✅ **Spójna** - jednolite wersje, wymagania, konwencje

---

## 📊 Weryfikacja Rzeczywistego Stanu (z kodu)

### Wersje
- **polytrans.php header**: `Version: 1.6.14`
- **POLYTRANS_VERSION constant**: `1.6.12` ⚠️ **NIESPÓJNOŚĆ!**
- **README.md**: `1.5.0` ❌ **PRZESTARZAŁE**
- **docs/INDEX.md**: `1.6.14 → 1.7.0` ✅

### Wymagania PHP
- **polytrans.php**: `Requires PHP: 8.1` ✅
- **README.md**: `PHP 8.1+` ✅
- **INSTALLATION.md**: `PHP 7.4+` ❌ **PRZESTARZAŁE**

### Providery (wbudowane)
- ✅ Google Translate (`PolyTrans_Google_Provider`)
- ✅ OpenAI (`PolyTrans_OpenAI_Provider`)
- ✅ Claude (`PolyTrans\Providers\Claude\ClaudeProvider`)
- ✅ Gemini (`PolyTrans\Providers\Gemini\GeminiProvider`)

### REST API Endpoints
- ✅ `POST /wp-json/polytrans/v1/translation/translate`
- ✅ `POST /wp-json/polytrans/v1/translation/receive-post`
- ⚠️ Inne endpointy mogą być w workflow/assistants (sprawdzić)

### Menu Structure (z kodu)
```
PolyTrans (main menu)
├── Overview (polytrans)
├── Settings (polytrans-settings)
├── AI Assistants (polytrans-assistants)
├── Tag Translations (polytrans-tag-translation)
├── Post-Processing (polytrans-workflows)
├── Execute Workflow (polytrans-execute-workflow)
└── Logs (polytrans-logs)
```

### Konwencje Nazewnictwa
- **Rzeczywistość**: PSR-4 (`PolyTrans\Namespace\Class`)
- **CONTRIBUTING.md**: Mówi o `PolyTrans_Class_Name` ❌ **PRZESTARZAŁE**

---

## 🔴 Krytyczne Problemy do Naprawienia

### 1. QUICK_START.md - Pusty Plik
**Status**: Plik istnieje, ale jest pusty (0 linii)  
**Linkowany w**: `docs/INDEX.md`, `README.md`

**Plan**:
- Utworzyć zawartość `QUICK_START.md` z:
  - Instalacja w 3 krokach
  - Konfiguracja Google Translate (najprostsze)
  - Pierwsza translacja
  - Linki do szczegółowej dokumentacji

---

### 2. Broken Links
**Status**: 2 broken links

**Naprawić**:
- `README.md` linia 12: Usunięto link do phase-0 (archiwum usunięte)
- `QUICK_START.md` - utworzyć zawartość

---

### 3. Niespójność Wersji
**Status**: 3 różne wersje w dokumentacji

**Naprawić**:
- `README.md`: 1.5.0 → 1.6.14
- `polytrans.php`: POLYTRANS_VERSION 1.6.12 → 1.6.14 (naprawić też w kodzie!)
- Wszystkie referencje do wersji zsynchronizować

---

### 4. Niespójność Wymagań PHP
**Status**: INSTALLATION.md mówi 7.4+, kod wymaga 8.1

**Naprawić**:
- `docs/user-guide/INSTALLATION.md`: PHP 7.4+ → PHP 8.1+

---

### 5. Przestarzałe Konwencje w CONTRIBUTING.md
**Status**: Mówi o `PolyTrans_Class_Name`, rzeczywistość to PSR-4

**Naprawić**:
- Zaktualizować przykłady na PSR-4
- Usunąć referencje do `PolyTrans_Class_Name`

---

### 6. Brakujące Providery w INSTALLATION.md
**Status**: Opisane tylko Google i OpenAI

**Naprawić**:
- Dodać sekcję o Claude
- Dodać sekcję o Gemini

---

### 7. API Endpoints - Niespójność
**Status**: README.md i API-DOCUMENTATION.md mają różne endpointy

**Naprawić**:
- Zweryfikować wszystkie endpointy w kodzie
- Zsynchronizować dokumentację

---

## 📋 Plan Implementacji

### Faza 1: Weryfikacja i Planowanie (1 dzień)
- [x] Zweryfikować rzeczywisty stan w kodzie
- [x] Stworzyć plan poprawy dokumentacji
- [ ] Zweryfikować wszystkie endpointy API
- [ ] Zweryfikować strukturę menu

### Faza 2: Naprawa Krytycznych Problemów (1 dzień)
- [ ] Utworzyć zawartość QUICK_START.md
- [ ] Naprawić broken links
- [ ] Zaktualizować wersje (README.md, polytrans.php)
- [ ] Ujednolicić PHP requirements
- [ ] Zaktualizować CONTRIBUTING.md (PSR-4)

### Faza 3: Uzupełnienie Dokumentacji (1 dzień)
- [ ] Dodać Claude do INSTALLATION.md
- [ ] Dodać Gemini do INSTALLATION.md
- [ ] Zweryfikować i zsynchronizować API endpoints
- [ ] Dodać Quick Start do PROVIDER_EXTENSIBILITY_GUIDE.md
- [ ] Dodać więcej przykładów workflow

### Faza 4: Weryfikacja i Finalizacja (0.5 dnia)
- [ ] Sprawdzić wszystkie linki
- [ ] Zweryfikować przykłady kodu
- [ ] Finalna weryfikacja spójności

---

## ✅ Checklist Poprawek

### Krytyczne (Zrobić teraz)
- [ ] Utworzyć zawartość QUICK_START.md
- [x] Usunięto link phase-0/ w README.md (archiwum usunięte)
- [ ] Zaktualizować wersję w README.md (1.5.0 → 1.6.14)
- [ ] Naprawić POLYTRANS_VERSION w polytrans.php (1.6.12 → 1.6.14)
- [ ] Ujednolicić PHP requirements (8.1+ wszędzie)
- [ ] Zaktualizować CONTRIBUTING.md (PSR-4)

### Wysokie (Zrobić wkrótce)
- [ ] Dodać Claude do INSTALLATION.md
- [ ] Dodać Gemini do INSTALLATION.md
- [ ] Zweryfikować API endpoints
- [ ] Zsynchronizować API endpoints w dokumentacji

### Średnie (Zrobić później)
- [ ] Dodać Quick Start do PROVIDER_EXTENSIBILITY_GUIDE.md
- [ ] Dodać więcej przykładów workflow
- [ ] Zweryfikować menu structure w INTERFACE.md

---

**Ostatnia aktualizacja**: 2025-12-16

