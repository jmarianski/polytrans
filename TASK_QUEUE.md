# PolyTrans Development Task Queue

## Current Priority Queue (Execute in Order)

### IMMEDIATE (Priority 1) - Security Critical
```
Task: Fix nonce verification in settings
File: includes/settings/class-translation-settings.php
Issues: 37 security violations
Command: make phpcs-relaxed
Goal: Reduce security issues from 93 to ~56
Time: 1-2 hours
```

### HIGH (Priority 2) - Security Continued  
```
Task: Fix input sanitization across core files
Files: includes/class-polytrans.php, includes/core/class-logs-manager.php
Issues: 19 combined security violations  
Command: make phpcs-relaxed
Goal: Reduce security issues from ~56 to ~30
Time: 1-2 hours
```

### MEDIUM (Priority 3) - Security Completion
```
Task: Fix remaining output escaping issues
Files: Multiple files (see phpcs-relaxed output)
Issues: ~30 remaining security violations
Command: make phpcs-relaxed  
Goal: Achieve 0 security issues
Time: 2-3 hours
```

### MEDIUM (Priority 4) - Testing Foundation
```
Task: Implement basic unit tests
Files: tests/ directory
Goal: Create tests for core classes, achieve 50% coverage
Command: make test
Time: 3-4 hours
```

### LOW (Priority 5) - Standards Compliance
```
Task: WordPress coding standards compliance
Files: All PHP files
Command: make phpcs (and make phpcbf for auto-fixes)
Goal: Prepare for WordPress.org submission
Time: 4-6 hours
```

## Quick Reference Commands

### Daily Development
```bash
make phpcs-syntax    # Quick check (should always pass)
make phpcs-relaxed   # Security check (track progress)  
make phpcbf          # Auto-fix issues when possible
```

### Environment
```bash
make setup           # First-time setup
make shell           # Development environment access
make clean           # Reset environment
```

## Progress Tracking

### Security Issues Baseline (July 10, 2025)
- **Total Issues**: 93
- **Critical (Settings)**: 37
- **Core Files**: 19  
- **Other Files**: 37

### Target Milestones
- [ ] Milestone 1: Fix settings file (93 → 56 issues)
- [ ] Milestone 2: Fix core files (56 → 37 issues)
- [ ] Milestone 3: Complete security fixes (37 → 0 issues)
- [ ] Milestone 4: Implement testing (0% → 50% coverage)
- [ ] Milestone 5: WordPress standards compliance

## Context-Free Continuation

Any developer can continue by:
1. Reading POLYTRANS_STATUS.md for complete context
2. Running `make phpcs-relaxed` to see current issues
3. Picking the next task from this queue
4. Updating progress in CODE_QUALITY_STATUS.md

## Emergency Information

### If Environment Doesn't Work
```bash
# Rebuild from scratch
make clean
make setup
```

### If Commands Fail
- Check Docker is running
- Ensure you're in the plugin directory
- Review DEVELOPMENT.md for detailed setup

### Critical Files to Never Delete
- POLYTRANS_STATUS.md (this file)
- IMPROVEMENT_PLAN.md (strategic overview)
- CODE_QUALITY_STATUS.md (current technical state)
- DEVELOPMENT.md (workflow documentation)
