# PolyTrans Documentation Index

## 📚 Quick Links

### Getting Started
- [README](../README.md) - Main plugin documentation
- [Quick Start Guide](../QUICK_START.md) - Get up and running quickly
- [Changelog](../CHANGELOG.md) - Version history and changes

### For Users
- [User Guide](user-guide/) - How to use PolyTrans features
- [Admin Guide](admin/) - WordPress admin configuration

### For Developers
- [Developer Guide](developer/) - Contributing and extending PolyTrans
- [Development Docs](development/) - Implementation details and architecture

## 🏗️ Development Documentation

### Phase 0: Foundation (✅ Complete - v1.3.5)
**Location**: `docs/archive/phase-0/` (archived)

Core infrastructure improvements:
- **Twig Integration** - Modern templating engine
- **Variable Structure Refactor** - Clean, consistent variable access
- **Context Refresh Logic** - Multi-step workflow data consistency
- **UI Redesign** - Variable sidebar/pills responsive layout

[View Phase 0 Details →](../archive/phase-0/)

### Current Development Plans
**Location**: `docs/development/`

- [Refactoring Plan](development/REFACTORING_PLAN.md) - DRY approach with design patterns
- [Quality Assessment](development/QUALITY_ASSESSMENT.md) - Code quality analysis and improvements roadmap
- [Docs Cleanup Plan](development/DOCS_CLEANUP_PLAN.md) - Documentation organization plan

### Roadmap
**Location**: `docs/roadmap/`

- [Version 1.7.0 Plan](roadmap/VERSION_1.7.0_PLAN.md) - Comprehensive refactoring & extensibility
- [Version 1.7.0 Proposal](roadmap/VERSION_1.7.0_PROPOSAL.md) - Initial extensibility proposal

### Planning Documents (Archived)
**Location**: `docs/archive/completed/`

- [Assistants System Plan](../archive/completed/ASSISTANTS_SYSTEM_PLAN.md) - Phase 1 roadmap (✅ Complete)
- [Implementation Plan](../archive/completed/IMPLEMENTATION_PLAN.md) - Overall architecture plan (✅ Phase 0 & Phase 1 Complete)

## 🧪 Testing Documentation

**Location**: `docs/testing/`

- [Testing Setup](testing/TESTING_SETUP.md) - How to run tests
- [Testing Best Practices](testing/TESTING_BEST_PRACTICES.md) - Writing good tests
- [Framework Research](testing/TESTING_FRAMEWORK_RESEARCH.md) - Why Pest PHP
- [Action Plan](testing/TESTING_ACTION_PLAN.md) - Testing roadmap

Quick start:
```bash
./run-tests.sh
```

## 📂 Documentation Structure

```
docs/
├── INDEX.md                    # This file
├── README.md                   # Docs overview
├── admin/                      # Admin documentation
├── user-guide/                 # End-user guides (includes ASSISTANTS.md)
├── developer/                  # Developer guides
├── development/                # Implementation details & plans
│   ├── REFACTORING_PLAN.md    # Refactoring plan (DRY approach)
│   ├── QUALITY_ASSESSMENT.md  # Quality assessment & improvements
│   └── DOCS_CLEANUP_PLAN.md   # Documentation cleanup plan
├── roadmap/                     # Version roadmaps
│   ├── VERSION_1.7.0_PLAN.md   # Comprehensive 1.7.0 plan
│   └── VERSION_1.7.0_PROPOSAL.md
├── archive/                    # Archived documentation
    ├── phase-0/                # Phase 0 (complete)
    ├── roadmap/               # Old roadmaps (VERSION_1.6.0_*)
    ├── planning/               # Old planning docs
    ├── analysis/               # Analysis documents (proposals implemented)
    └── completed/               # Completed features documentation
├── testing/                    # Testing documentation
└── archive/                    # Archived documentation
    ├── phase-0/                # Phase 0 (complete)
    ├── roadmap/               # Old roadmaps (VERSION_1.6.0_*)
    └── planning/               # Old planning docs
```

## 🎯 Current Status

**Version**: 1.6.14 → **1.7.0** (in development)  
**Phase**: Twig Migration Complete ✅  
**Next**: Version 1.7.0 - Comprehensive Refactoring & Extensibility

### What's Working
- ✅ Twig template engine with WordPress integration
- ✅ Clean variable structure (`original.*`, `translated.*`)
- ✅ Context refresh between workflow steps
- ✅ Responsive variable editor UI
- ✅ Markdown rendering in AI responses
- ✅ Reusable Prompt Editor module
- ✅ Testing infrastructure (Pest PHP)
- ✅ All admin pages migrated to Twig templates

### What's Next (Version 1.7.0)
- 🔄 Complete PSR-4 migration (377 references to fix)
- 🔄 Utility classes refactoring (HttpClient, BaseAjaxHandler, etc.)
- 🔄 Extensibility hooks system (27 new hooks)
- 🔄 Path resolution interceptors (modify pathing behavior)
- 🔄 Manifest extensions (custom endpoint handlers)
- 🔄 Documentation cleanup and reorganization

## 🔗 External Resources

- [WordPress Plugin Handbook](https://developer.wordpress.org/plugins/)
- [Twig Documentation](https://twig.symfony.com/doc/3.x/)
- [Pest PHP Documentation](https://pestphp.com/)

---

**Last Updated**: 2025-12-16  
**Version**: 1.7.0 (planning)  
**Maintained By**: PolyTrans Team

