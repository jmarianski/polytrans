# Plan Porządkowania Dokumentacji

**Data utworzenia**: 2025-12-16  
**Cel**: Uporządkowanie struktury dokumentacji, usunięcie duplikatów, archiwizacja przestarzałych dokumentów

---

## 📊 Analiza Obecnego Stanu

### Statystyki
- **Łączna liczba plików**: 42 pliki .md
- **Łączna liczba linii**: ~15,500 linii
- **Największe pliki**:
  - `planning/ASSISTANTS_SYSTEM_PLAN.md` - 1,413 linii
  - `development/phase-0/PHASE_0_VARIABLE_REFACTOR.md` - 1,010 linii
  - `development/REFACTORING_PLAN.md` - 960 linii
  - `development/REFACTORING_PLAN_DRY.md` - 809 linii
  - `planning/REFACTORING_AUTOLOADER_PLAN.md` - 749 linii

### Problemy Zidentyfikowane

#### 1. **Duplikaty i Przestarzałe Dokumenty**
- `QUALITY_ASSESSMENT.md` vs `QUALITY_IMPROVEMENTS.md` - podobna tematyka
- `REFACTORING_PLAN.md` vs `REFACTORING_PLAN_DRY.md` - dwa plany refaktoryzacji
- `REFACTORING_AUTOLOADER_PLAN.md` - plan z 2025-12-10, częściowo zrealizowany
- `EXTERNAL_PLUGIN_QUICK_START.md` vs `QUICK_START_ADD_PROVIDER.md` - podobna tematyka
- `ERROR_LOGGING_IMPROVEMENTS.md` - może być przestarzały
- `TWIG_MIGRATION_STATUS.md` - migracja zakończona, można zarchiwizować

#### 2. **Nieaktualne Informacje**
- `INDEX.md` - wskazuje na wersję 1.3.5, obecna to 1.6.14
- `development/phase-0/` - Phase 0 zakończona, można zarchiwizować
- `roadmap/VERSION_1.6.0_*` - wersja 1.6.0 już wydana
- `planning/ASSISTANTS_SYSTEM_PLAN.md` - może być częściowo zrealizowany

#### 3. **Brak Spójnej Struktury**
- Pliki w root `docs/` (ASSISTANTS_USER_GUIDE.md, ERROR_LOGGING_IMPROVEMENTS.md, etc.)
- Mieszanka języków (polski/angielski)
- Brak jasnego podziału na kategorie

#### 4. **Nieaktualne Linki**
- `INDEX.md` wskazuje na nieistniejące pliki
- Niektóre dokumenty mają przestarzałe linki

---

## 🎯 Proponowana Nowa Struktura

```
docs/
├── README.md                          # Główny punkt wejścia
├── INDEX.md                           # Zaktualizowany indeks
│
├── user-guide/                        # Dokumentacja dla użytkowników końcowych
│   ├── INSTALLATION.md
│   ├── INTERFACE.md
│   ├── FAQ.md
│   └── ASSISTANTS.md                  # Przeniesione z root
│
├── admin/                             # Dokumentacja dla administratorów
│   ├── CONFIGURATION.md
│   ├── WORKFLOW-TRIGGERING.md
│   └── WORKFLOW-LOGGING.md
│
├── developer/                         # Dokumentacja dla deweloperów
│   ├── DEVELOPMENT_SETUP.md
│   ├── ARCHITECTURE.md
│   ├── API-DOCUMENTATION.md
│   ├── CONTRIBUTING.md
│   ├── PROVIDER_CAPABILITIES.md
│   ├── PROVIDER_EXTENSIBILITY_GUIDE.md
│   └── QUICK_START_ADD_PROVIDER.md   # Przeniesione z root
│
├── development/                       # Dokumentacja techniczna (aktualna)
│   ├── QUALITY_ASSESSMENT.md          # Zaktualizowany (usunąć QUALITY_IMPROVEMENTS.md)
│   ├── REFACTORING_PLAN.md            # Zaktualizowany (połączyć z REFACTORING_PLAN_DRY.md)
│   └── TWIG_MIGRATION_STATUS.md      # Zaktualizowany (status: complete)
│
├── archive/                           # Zarchiwizowane dokumenty
│   ├── phase-0/                       # Phase 0 - zakończona
│   │   ├── README.md
│   │   ├── PHASE_0_EXTENDED.md
│   │   ├── PHASE_0_VARIABLE_REFACTOR.md
│   │   ├── PHASE_0_VERIFICATION.md
│   │   └── PHASE_0.1_TWIG_INTEGRATION.md
│   ├── planning/                      # Przestarzałe plany
│   │   ├── ASSISTANTS_SYSTEM_PLAN.md  # Jeśli zrealizowany
│   │   ├── IMPLEMENTATION_PLAN.md     # Jeśli przestarzały
│   │   └── REFACTORING_AUTOLOADER_PLAN.md  # Częściowo zrealizowany
│   └── roadmap/                       # Przestarzałe roadmapy
│       ├── VERSION_1.6.0_ROADMAP.md
│       └── VERSION_1.6.0_STATUS.md
│
├── analysis/                          # Analizy techniczne (zachować)
│   ├── PROVIDER_SYSTEM_ANALYSIS.md
│   └── UNIVERSAL_PROVIDER_JS_SYSTEM.md
│
├── testing/                           # Dokumentacja testów
│   ├── TESTING_SETUP.md
│   ├── TESTING_BEST_PRACTICES.md
│   ├── TESTING_FRAMEWORK_RESEARCH.md
│   └── TESTING_ACTION_PLAN.md
│
├── examples/                          # Przykłady
│   └── polytrans-deepseek/
│       └── README.md
│
└── RELEASE.md                         # Informacje o wydaniach
```

---

## 📋 Plan Działania

### Krok 1: Archiwizacja Przestarzałych Dokumentów

#### 1.1. Utwórz katalog `archive/`
```bash
mkdir -p docs/archive/{phase-0,planning,roadmap}
```

#### 1.2. Przenieś zakończone Phase 0
```bash
mv docs/development/phase-0/* docs/archive/phase-0/
```

#### 1.3. Przenieś przestarzałe plany
- `planning/REFACTORING_AUTOLOADER_PLAN.md` → `archive/planning/` (PSR-4 już zrealizowany)
- `roadmap/VERSION_1.6.0_*` → `archive/roadmap/` (wersja wydana)

#### 1.4. Sprawdź i zarchiwizuj jeśli zrealizowane:
- `planning/ASSISTANTS_SYSTEM_PLAN.md` - sprawdzić czy zrealizowany
- `planning/IMPLEMENTATION_PLAN.md` - sprawdzić czy przestarzały

---

### Krok 2: Konsolidacja Duplikatów

#### 2.1. Połącz plany refaktoryzacji
- **Zachować**: `REFACTORING_PLAN_DRY.md` (nowszy, lepszy)
- **Usunąć**: `REFACTORING_PLAN.md` (starszy)
- **Zaktualizować**: `REFACTORING_PLAN_DRY.md` → `REFACTORING_PLAN.md` (zmienić nazwę)

#### 2.2. Połącz dokumenty jakości
- **Zachować**: `QUALITY_ASSESSMENT.md` (szczegółowy)
- **Usunąć**: `QUALITY_IMPROVEMENTS.md` (duplikat)
- **Zaktualizować**: `QUALITY_ASSESSMENT.md` z informacjami z `QUALITY_IMPROVEMENTS.md` jeśli potrzebne

#### 2.3. Połącz quick starty
- **Zachować**: `QUICK_START_ADD_PROVIDER.md` (bardziej szczegółowy)
- **Sprawdzić**: `EXTERNAL_PLUGIN_QUICK_START.md` - czy to samo czy różne?
- **Decyzja**: Połączyć lub przenieść do `developer/`

---

### Krok 3: Reorganizacja Struktury

#### 3.1. Przenieś pliki z root do odpowiednich katalogów
- `ASSISTANTS_USER_GUIDE.md` → `user-guide/ASSISTANTS.md`
- `EXTERNAL_PLUGIN_QUICK_START.md` → `developer/EXTERNAL_PLUGIN_QUICK_START.md` (lub połączyć z QUICK_START_ADD_PROVIDER.md)
- `ERROR_LOGGING_IMPROVEMENTS.md` → Sprawdzić czy aktualny, jeśli nie → `archive/`

#### 3.2. Zaktualizuj `TWIG_MIGRATION_STATUS.md`
- Dodać sekcję "Status: COMPLETE ✅"
- Przenieść do `development/` jeśli nie jest tam
- Zaktualizować datę

---

### Krok 4: Aktualizacja Indeksów i Linków

#### 4.1. Zaktualizuj `INDEX.md`
- Aktualizuj wersję: 1.3.5 → 1.6.14
- Usuń sekcję Phase 0 (zarchiwizowana)
- Dodaj sekcję Archive
- Zaktualizuj linki

#### 4.2. Zaktualizuj `README.md`
- Sprawdź wszystkie linki
- Zaktualizuj strukturę katalogów
- Dodaj informacje o archiwum

#### 4.3. Zaktualizuj linki w dokumentach
- Znajdź wszystkie `[text](path)` i zaktualizuj ścieżki
- Sprawdź czy wszystkie linki działają

---

### Krok 5: Standaryzacja Formatowania

#### 5.1. Nagłówki
- Upewnij się że wszystkie pliki mają nagłówek z datą i wersją
- Standardowy format:
  ```markdown
  # Tytuł Dokumentu
  
  **Data utworzenia**: YYYY-MM-DD  
  **Ostatnia aktualizacja**: YYYY-MM-DD  
  **Wersja**: X.Y.Z
  ```

#### 5.2. Spis treści
- Dodaj spis treści do długich dokumentów (>300 linii)
- Użyj `## Spis Treści` na początku

#### 5.3. Status badges
- Użyj spójnych oznaczeń statusu:
  - ✅ Complete / Zakończone
  - 🔄 In Progress / W trakcie
  - 📋 Planned / Zaplanowane
  - ⏸️ On Hold / Wstrzymane
  - 🗄️ Archived / Zarchiwizowane

---

## 📝 Szczegółowa Lista Zmian

### Pliki do Usunięcia
- [ ] `docs/development/QUALITY_IMPROVEMENTS.md` (duplikat QUALITY_ASSESSMENT.md)
- [ ] `docs/development/REFACTORING_PLAN.md` (zastąpiony przez REFACTORING_PLAN_DRY.md)

### Pliki do Przeniesienia
- [ ] `docs/ASSISTANTS_USER_GUIDE.md` → `docs/user-guide/ASSISTANTS.md`
- [ ] `docs/EXTERNAL_PLUGIN_QUICK_START.md` → `docs/developer/EXTERNAL_PLUGIN_QUICK_START.md`
- [ ] `docs/development/phase-0/*` → `docs/archive/phase-0/`
- [ ] `docs/planning/REFACTORING_AUTOLOADER_PLAN.md` → `docs/archive/planning/`
- [ ] `docs/roadmap/VERSION_1.6.0_*` → `docs/archive/roadmap/`

### Pliki do Zmiany Nazwy
- [ ] `docs/development/REFACTORING_PLAN_DRY.md` → `docs/development/REFACTORING_PLAN.md`

### Pliki do Zaktualizowania
- [ ] `docs/INDEX.md` - wersja, linki, struktura
- [ ] `docs/README.md` - linki, struktura
- [ ] `docs/TWIG_MIGRATION_STATUS.md` - status: complete
- [ ] `docs/development/QUALITY_ASSESSMENT.md` - zaktualizować datę
- [ ] Wszystkie dokumenty z przestarzałymi linkami

### Pliki do Sprawdzenia
- [ ] `docs/ERROR_LOGGING_IMPROVEMENTS.md` - czy aktualny?
- [ ] `docs/planning/ASSISTANTS_SYSTEM_PLAN.md` - czy zrealizowany?
- [ ] `docs/planning/IMPLEMENTATION_PLAN.md` - czy przestarzały?
- [ ] `docs/roadmap/VERSION_1.7.0_PROPOSAL.md` - czy aktualny?

---

## 🔍 Sprawdzenie Linków

### Linki do Sprawdzenia
1. `docs/INDEX.md`:
   - `../QUICK_START.md` - czy istnieje?
   - `../CHANGELOG.md` - czy istnieje?
   - Wszystkie linki do `development/phase-0/` - zaktualizować do `archive/phase-0/`

2. `docs/ASSISTANTS_USER_GUIDE.md`:
   - `WORKFLOW_USER_GUIDE.md` - czy istnieje?
   - `VARIABLE_REFERENCE.md` - czy istnieje?

3. Wszystkie dokumenty w `developer/`:
   - Linki do `PROVIDER_CAPABILITIES.md`
   - Linki do innych dokumentów

---

## ✅ Checklist Porządkowania

### Faza 1: Przygotowanie
- [ ] Utwórz katalog `docs/archive/`
- [ ] Utwórz podkatalogi: `phase-0/`, `planning/`, `roadmap/`
- [ ] Backup obecnej struktury (git commit przed zmianami)

### Faza 2: Archiwizacja
- [ ] Przenieś `development/phase-0/` → `archive/phase-0/`
- [ ] Przenieś przestarzałe plany → `archive/planning/`
- [ ] Przenieś przestarzałe roadmapy → `archive/roadmap/`

### Faza 3: Konsolidacja
- [ ] Połącz plany refaktoryzacji
- [ ] Połącz dokumenty jakości
- [ ] Sprawdź i połącz quick starty

### Faza 4: Reorganizacja
- [ ] Przenieś pliki z root do odpowiednich katalogów
- [ ] Zaktualizuj `TWIG_MIGRATION_STATUS.md`
- [ ] Zmień nazwy plików jeśli potrzebne

### Faza 5: Aktualizacja
- [ ] Zaktualizuj `INDEX.md`
- [ ] Zaktualizuj `README.md`
- [ ] Zaktualizuj wszystkie linki w dokumentach
- [ ] Dodaj nagłówki do dokumentów bez nich
- [ ] Standaryzuj formatowanie

### Faza 6: Weryfikacja
- [ ] Sprawdź wszystkie linki (działają?)
- [ ] Sprawdź strukturę katalogów
- [ ] Sprawdź czy nic nie zostało usunięte przypadkowo
- [ ] Zaktualizuj `.gitignore` jeśli potrzebne

---

## 📊 Metryki Po Porządkowaniu

### Przed
- 42 pliki .md
- ~15,500 linii
- 3 duplikaty
- Nieaktualne informacje w INDEX.md
- Przestarzałe dokumenty w głównych katalogach

### Po
- ~35 plików .md (aktywnych)
- ~7 plików w archive/
- 0 duplikatów
- Aktualne informacje
- Czysta struktura

---

## 🎯 Priorytety

### Wysoki Priorytet
1. ✅ Archiwizacja Phase 0 (zakończona)
2. ✅ Konsolidacja planów refaktoryzacji
3. ✅ Aktualizacja INDEX.md i README.md

### Średni Priorytet
4. ⚠️ Przeniesienie plików z root
5. ⚠️ Sprawdzenie i aktualizacja linków
6. ⚠️ Standaryzacja formatowania

### Niski Priorytet
7. 📋 Dodanie spisów treści
8. 📋 Dodanie status badges
9. 📋 Optymalizacja długich dokumentów

---

**Ostatnia aktualizacja**: 2025-12-16

