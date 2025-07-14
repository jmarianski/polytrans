# POLYTRANS QUICK START GUIDE

## For Immediate Development Continuation

### 1. Verify Environment (30 seconds)
```bash
cd /path/to/polytrans/plugin
make phpcs-syntax
# Should show: "Time: XXXms; Memory: XXmb" (0 errors)
```

### 2. Check Current Issues (30 seconds)  
```bash
make phpcs-relaxed
# Should show: ~93 security issues across 12 files
```

### 3. Start Next Task (choose one)

#### Option A: Fix Critical Security (RECOMMENDED)
```bash
# Edit: includes/core/class-translation-settings.php
# Fix: Lines 40, 54-61, 70, 77-85 (nonce verification)
# Test: make phpcs-relaxed (should reduce from 93 to ~56 issues)
```

#### Option B: Continue Feature Development  
```bash
# Use: make phpcs-syntax for quick validation
# Focus: Add new features without security fixes
```

#### Option C: WordPress Standards
```bash
# Use: make phpcs (shows many formatting issues)
# Auto-fix: make phpcbf
```

### 4. Document Progress
Update CODE_QUALITY_STATUS.md with:
- Issues fixed
- Current issue count
- Next steps

## Files You Need to Know

### Status Files (READ FIRST)
- `POLYTRANS_STATUS.md` - Complete current state
- `TASK_QUEUE.md` - Prioritized task list  
- `CODE_QUALITY_STATUS.md` - Technical details

### Development Files
- `Makefile` - All development commands
- `DEVELOPMENT.md` - Detailed workflow
- `composer.json` - Dependencies and scripts

### Problem Files (NEED FIXES)
1. `includes/core/class-translation-settings.php` (37 issues)
2. `includes/class-polytrans.php` (12 issues)
3. `includes/core/class-logs-manager.php` (7 issues)

## Essential Commands
```bash
make setup           # Environment setup
make phpcs-syntax    # Syntax check (should pass)
make phpcs-relaxed   # Security check (~93 issues)
make phpcs           # Full standards (many issues)
make phpcbf          # Auto-fix
make shell           # Development access
```

## Emergency Context Recovery
If you lose all context, this repository contains:
- Complete documentation of current state
- Working Docker development environment  
- Prioritized task queue
- All necessary development tools

Read POLYTRANS_STATUS.md for complete context recovery.
