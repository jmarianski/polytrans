# UI Redesign TODO - Variable Panel Layout

**Status**: In Progress (Phase 1 Complete, Phase 2 Pending)
**Date**: 2025-12-09
**Context**: Zmienne mają być przyklejone do każdego textarea (system prompt + user message) jak w TinyMCE

---

## ✅ Phase 1 Complete (Done Today)

1. ✅ Compact pills design (`title` zamiast `{{ title }}`)
2. ✅ Tooltips on hover (show description)
3. ✅ Click to insert with undo support (`Ctrl+Z` działa)
4. ✅ Scrollable (200px max height)
5. ✅ Removed legacy "Available Variables" section at bottom
6. ✅ Fixed `\\n` escaping in examples
7. ✅ Added `recent_articles` variable

---

## 🎯 Phase 2 TODO - Layout Redesign (NEXT)

### Problem:
Obecnie zmienne są w jednym miejscu dla całego step'a. Powinny być **przyklejone do każdego textarea osobno**.

### Target Layout:

#### Desktop (>= 768px):
```
┌─────────────────────────────────┬───────────────┐
│ System Prompt (textarea)        │ title         │
│                                  │ content       │
│                                  │ excerpt       │
│                                  │ original.title│
│                                  │ ...           │
│                                  │ (scrollable)  │
└─────────────────────────────────┴───────────────┘

┌─────────────────────────────────┬───────────────┐
│ User Message (textarea)         │ title         │
│                                  │ content       │
│                                  │ ...           │
└─────────────────────────────────┴───────────────┘
```

**Specs**:
- Sidebar z prawej: **120-150px szerokości**
- Zmienne **jeden pod drugim** (wąski layout)
- **Sticky** kiedy scroll (pozostaje widoczny)
- Pills mniejsze (padding: 3px 8px, font: 10px)

#### Mobile (<= 767px):
```
┌─────────────────────────────────────────────────┐
│ [title] [content] [excerpt] [original.title]   │
│ [translated.title] [source_lang] ...           │
│ [admin_email] [site_url] ...                    │
│ ◄────── scrollable horizontally ────────►      │
├─────────────────────────────────────────────────┤
│ System Prompt (textarea)                        │
│                                                  │
└─────────────────────────────────────────────────┘

┌─────────────────────────────────────────────────┐
│ [title] [content] [excerpt] ...                │
│ (same pills, 2-3 rows)                          │
├─────────────────────────────────────────────────┤
│ User Message (textarea)                         │
└─────────────────────────────────────────────────┘
```

**Specs**:
- Pills **nad textarea** (2-3 wiersze)
- **Horizontal scroll** jeśli za dużo
- Pills normalne (padding: 4px 10px)

---

## 📐 Implementation Plan

### 1. HTML Structure Change

**Current** (jeden panel dla step'a):
```html
<div class="workflow-step-field">
    <div class="variable-reference-panel">...</div>
    <textarea id="step-0-system-prompt">...</textarea>
</div>
```

**Target** (osobne panele dla każdego textarea):
```html
<div class="workflow-step-field workflow-field-with-variables">
    <div class="field-wrapper">
        <textarea id="step-0-system-prompt">...</textarea>
        <div class="variable-sidebar">
            <span class="var-pill" ...>title</span>
            <span class="var-pill" ...>content</span>
            ...
        </div>
    </div>
</div>
```

### 2. CSS Changes

#### Desktop (sidebar layout):
```css
.workflow-field-with-variables .field-wrapper {
    display: flex;
    gap: 10px;
}

.workflow-field-with-variables textarea {
    flex: 1;
}

.workflow-field-with-variables .variable-sidebar {
    width: 150px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    max-height: 300px;
    overflow-y: auto;
    padding: 8px;
    background: #fafafa;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    position: sticky;
    top: 20px;
}

.workflow-field-with-variables .var-pill {
    padding: 3px 8px;
    font-size: 10px;
    white-space: normal; /* Allow wrapping for long names */
    text-align: center;
}
```

#### Mobile (top layout):
```css
@media (max-width: 767px) {
    .workflow-field-with-variables .field-wrapper {
        flex-direction: column;
    }

    .workflow-field-with-variables .variable-sidebar {
        width: 100%;
        flex-direction: row;
        flex-wrap: wrap;
        max-height: none;
        overflow-x: auto;
        overflow-y: hidden;
        white-space: nowrap;
        position: relative;
        top: 0;
    }

    .workflow-field-with-variables .var-pill {
        padding: 4px 10px;
        font-size: 11px;
        white-space: nowrap;
    }
}
```

### 3. JS Changes

**Update `renderAIStepFields()` and `renderPredefinedAssistantStepFields()`**:

```javascript
// OLD
html += `<div class="workflow-step-field">
    ${renderVariableReferencePanel()}
    <textarea ...></textarea>
</div>`;

// NEW
html += `<div class="workflow-step-field workflow-field-with-variables">
    <div class="field-wrapper">
        <textarea ...></textarea>
        ${renderVariableSidebar()}
    </div>
</div>`;
```

**New function**:
```javascript
function renderVariableSidebar() {
    const variables = [...]; // same as before
    const pills = variables.map(v =>
        `<span class="var-pill" data-variable="{{ ${v.name} }}" title="${v.desc}">${v.name}</span>`
    ).join('');

    return `<div class="variable-sidebar">${pills}</div>`;
}
```

---

## 🎨 Visual Reference (ASCII)

### Desktop:
```
┌───────────────────────────────────────────────┬────────┐
│ Label: System Prompt                          │        │
│ ┌───────────────────────────────────────────┐ │ title  │
│ │ You are a helpful assistant...            │ │        │
│ │                                            │ │ content│
│ │                                            │ │        │
│ │                                            │ │ excerpt│
│ │                                            │ │        │
│ │                                            │ │ orig.. │
│ │                                            │ │        │
│ └───────────────────────────────────────────┘ │ (↕)    │
└───────────────────────────────────────────────┴────────┘
```

### Mobile:
```
┌───────────────────────────────────────────────────┐
│ Label: System Prompt                              │
│ ┌───────────────────────────────────────────────┐ │
│ │[title][content][excerpt][original.title] ───► │ │
│ │[translated.title][source_lang][target_lang]   │ │
│ └───────────────────────────────────────────────┘ │
│ ┌───────────────────────────────────────────────┐ │
│ │ You are a helpful assistant...                │ │
│ │                                                │ │
│ └───────────────────────────────────────────────┘ │
└───────────────────────────────────────────────────┘
```

---

## 📝 Files to Modify

1. **JS**: `assets/js/postprocessing-admin.js`
   - Update `renderAIStepFields()` (line ~420)
   - Update `renderPredefinedAssistantStepFields()` (line ~480)
   - Create `renderVariableSidebar()` function
   - Remove `renderVariableReferencePanel()` (or keep for legacy)

2. **CSS**: `assets/css/postprocessing-admin.css`
   - Add `.workflow-field-with-variables` styles
   - Add `.variable-sidebar` styles
   - Update `.var-pill` sizes (desktop: 10px, mobile: 11px)
   - Add `@media` query for mobile layout

---

## 🚧 Edge Cases to Handle

1. **Long variable names**: `original.meta.article_category`
   - Desktop: Allow text wrapping in narrow sidebar
   - Mobile: Keep nowrap with horizontal scroll

2. **Sticky sidebar on desktop**:
   - Use `position: sticky; top: 20px;`
   - Ensure parent container allows sticky positioning

3. **Focus tracking**:
   - Already implemented (tracks `lastFocusedTextarea`)
   - No changes needed

4. **Advanced examples**:
   - Option 1: Remove completely (pills are self-explanatory)
   - Option 2: Move to bottom of step (collapsible)
   - **Recommendation**: Remove (less clutter)

---

## ⚡ Performance Considerations

- Each step will have **2 variable sidebars** (system prompt + user message)
- ~30 pills total per sidebar (15 variables)
- **Not an issue**: Pills are static HTML, no performance impact

---

## 🧪 Testing Checklist

After implementation:

- [ ] Desktop: Sidebar shows on right
- [ ] Desktop: Pills are narrow (one per line)
- [ ] Desktop: Sidebar is sticky on scroll
- [ ] Desktop: Scrollbar appears if >300px
- [ ] Mobile: Pills show above textarea (2-3 rows)
- [ ] Mobile: Horizontal scroll works
- [ ] Click to insert works (both layouts)
- [ ] Hover tooltip works (both layouts)
- [ ] Undo (Ctrl+Z) works (both layouts)

---

## 📊 Current Status

**Phase 1**: ✅ Complete
- Compact pills design
- Click to insert
- Undo support
- Tooltips

**Phase 2**: ✅ Complete (2025-12-10)
- Sidebar layout (desktop) ✅
- Top layout (mobile) ✅
- Sticky positioning ✅
- Per-textarea panels ✅

---

## ✅ Phase 2 Implementation Summary

**Changes Made**:

1. **JavaScript (`assets/js/postprocessing-admin.js`)**:
   - ✅ Created new `renderVariableSidebar()` function
   - ✅ Updated `renderAIAssistantFields()` to use new layout with sidebar
   - ✅ Updated `renderPredefinedAssistantFields()` to use new layout with sidebar
   - ✅ Removed old `renderVariableReferencePanel()` calls from step rendering
   - ✅ Kept `renderVariableReferencePanel()` for backward compatibility (marked as LEGACY)

2. **CSS (`assets/css/postprocessing-admin.css`)**:
   - ✅ Added `.workflow-field-with-variables` styles
   - ✅ Added `.variable-sidebar` styles (150px width, sticky, scrollable)
   - ✅ Desktop: Sidebar on right with vertical pills
   - ✅ Mobile: Pills above textarea with horizontal scroll
   - ✅ Smaller pill sizes (desktop: 10px, mobile: 11px)

**Result**: Each textarea (system prompt + user message) now has its own variable sidebar that:
- On **desktop** (>= 768px): Shows on the right, sticky, scrollable vertically
- On **mobile** (<= 767px): Shows above textarea, horizontal scroll, wraps to 2-3 rows

---

**Files Modified**:
- ✅ `assets/js/postprocessing-admin.js` - Sidebar rendering implemented
- ✅ `assets/css/postprocessing-admin.css` - Desktop & mobile styles added

**Testing Status**: Ready for user testing
**Next Action**: User verification and feedback
