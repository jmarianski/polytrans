/**
 * PolyTrans Prompt Editor Module
 * 
 * Reusable textarea editor with variable sidebar/pills for AI prompts.
 * Supports desktop (sidebar) and mobile (pills above) layouts.
 * 
 * @package PolyTrans
 * @since 1.3.5
 */

(function($) {
    'use strict';

    /**
     * Available variables for prompts
     */
    const AVAILABLE_VARIABLES = [
        { name: 'title', desc: 'Translated post title' },
        { name: 'content', desc: 'Translated post content' },
        { name: 'excerpt', desc: 'Translated post excerpt' },
        { name: 'original.title', desc: 'Original post title' },
        { name: 'original.content', desc: 'Original post content' },
        { name: 'original.excerpt', desc: 'Original post excerpt' },
        { name: 'original.meta.KEY', desc: 'Original post meta field (replace KEY)' },
        { name: 'translated.title', desc: 'Current translated title' },
        { name: 'translated.content', desc: 'Current translated content' },
        { name: 'translated.excerpt', desc: 'Current translated excerpt' },
        { name: 'translated.meta.KEY', desc: 'Translated post meta field (replace KEY)' },
        { name: 'source_language', desc: 'Source language code' },
        { name: 'target_language', desc: 'Target language code' },
        { name: 'post_type', desc: 'Post type (post, page, etc.)' },
        { name: 'author_name', desc: 'Post author name' },
        { name: 'recent_articles', desc: 'Recent posts (for SEO context)' },
        { name: 'site_url', desc: 'Site URL' },
        { name: 'admin_email', desc: 'Site admin email' }
    ];

    /**
     * Render variable sidebar HTML
     */
    function renderVariableSidebar() {
        const variablePills = AVAILABLE_VARIABLES.map(variable =>
            `<span class="var-pill" data-variable="{{ ${variable.name} }}" title="${variable.desc}">${variable.name}</span>`
        ).join('');

        return `
            <div class="variable-sidebar">
                ${variablePills}
            </div>
        `;
    }

    /**
     * Create a prompt editor
     * 
     * @param {Object} options Configuration options
     * @param {string} options.id - Textarea ID
     * @param {string} options.name - Textarea name attribute
     * @param {string} options.label - Field label
     * @param {string} options.value - Initial value
     * @param {number} options.rows - Number of rows (default: 4)
     * @param {boolean} options.required - Is field required (default: false)
     * @param {string} options.placeholder - Placeholder text
     * @param {string} options.helpText - Help text below field
     * @returns {string} HTML string
     */
    function createPromptEditor(options) {
        const {
            id,
            name,
            label,
            value = '',
            rows = 4,
            required = false,
            placeholder = '',
            helpText = ''
        } = options;

        const escapedValue = $('<div>').text(value).html();

        return `
            <div class="workflow-step-field workflow-field-with-variables">
                <label for="${id}">${label}${required ? ' <span style="color: red;">*</span>' : ''}</label>
                <div class="field-wrapper">
                    <textarea 
                        id="${id}" 
                        name="${name}" 
                        rows="${rows}" 
                        ${required ? 'required' : ''}
                        ${placeholder ? `placeholder="${placeholder}"` : ''}
                        class="prompt-editor-textarea"
                    >${escapedValue}</textarea>
                    ${renderVariableSidebar()}
                </div>
                ${helpText ? `<small>${helpText}</small>` : ''}
            </div>
        `;
    }

    /**
     * Initialize prompt editor interactivity
     * Attaches event handlers for variable insertion
     * 
     * NOTE: This is currently disabled because postprocessing-admin.js
     * already has global handlers for .var-pill clicks.
     * If you use this module in other contexts, you may need to enable this.
     */
    function initPromptEditors() {
        // Event handlers are managed by postprocessing-admin.js
        // to avoid duplicate insertions
        
        // If you need to use this module standalone (outside workflows),
        // uncomment the code below:
        
        /*
        let lastFocusedTextarea = null;

        // Track last focused textarea
        $(document).on('focus', '.prompt-editor-textarea', function() {
            lastFocusedTextarea = this;
        });

        // Handle variable pill clicks
        $(document).on('click', '.var-pill', function() {
            const variable = $(this).data('variable');
            const textarea = lastFocusedTextarea || $(this).closest('.field-wrapper').find('textarea')[0];

            if (textarea) {
                const start = textarea.selectionStart;
                const end = textarea.selectionEnd;
                const text = textarea.value;
                const before = text.substring(0, start);
                const after = text.substring(end, text.length);

                textarea.value = before + variable + after;
                textarea.selectionStart = textarea.selectionEnd = start + variable.length;
                textarea.focus();

                // Trigger change event for frameworks that listen to it
                $(textarea).trigger('change');
            }
        });
        */
    }

    /**
     * Public API
     */
    window.PolyTransPromptEditor = {
        create: createPromptEditor,
        init: initPromptEditors,
        variables: AVAILABLE_VARIABLES
    };

    // Auto-init on document ready
    $(document).ready(function() {
        initPromptEditors();
    });

})(jQuery);

