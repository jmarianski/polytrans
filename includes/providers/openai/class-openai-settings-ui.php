<?php

/**
 * OpenAI Provider Settings UI
 * Handles the settings interface for the OpenAI translation provider
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_OpenAI_Settings_UI
{
    /**
     * Render the OpenAI configuration section
     */
    public static function render_openai_section($settings, $languages, $lang_names)
    {
        $openai_api_key = $settings['openai_api_key'] ?? '';
        $openai_source_language = $settings['openai_source_language'] ?? ($languages[0] ?? 'en');
        $openai_assistants = $settings['openai_assistants'] ?? [];
?>
        <div class="openai-config-section">
            <h2><?php esc_html_e('OpenAI Configuration', 'polytrans'); ?></h2>
            <p><?php esc_html_e('Configure your OpenAI API key and translation assistants for AI-powered translations.', 'polytrans'); ?></p>

            <!-- API Key Section -->
            <div class="openai-api-key-section">
                <h3><?php esc_html_e('API Key', 'polytrans'); ?></h3>
                <div style="display:flex;gap:0.5em;align-items:center;max-width:600px;">
                    <input type="password"
                        id="openai-api-key"
                        name="openai_api_key"
                        value="<?php echo esc_attr($openai_api_key); ?>"
                        style="width:100%"
                        placeholder="sk-..."
                        autocomplete="off" />
                    <button type="button" id="validate-openai-key" class="button"><?php esc_html_e('Validate', 'polytrans'); ?></button>
                    <button type="button" id="toggle-openai-key-visibility" class="button">👁</button>
                </div>
                <div id="openai-validation-message" style="margin-top:0.5em;"></div>
                <small><?php esc_html_e('Enter your OpenAI API key. It will be validated before saving.', 'polytrans'); ?></small>
            </div>

            <!-- Source Language Section -->
            <div class="openai-source-language-section" style="margin-top:2em;">
                <h3><?php esc_html_e('OpenAI Source Language', 'polytrans'); ?></h3>
                <select name="openai_source_language" id="openai-source-language" style="max-width:200px;">
                    <?php foreach ($languages as $i => $lang): ?>
                        <option value="<?php echo esc_attr($lang); ?>" <?php selected($openai_source_language, $lang); ?>>
                            <?php echo esc_html($lang_names[$i] ?? strtoupper($lang)); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <br><small><?php esc_html_e('OpenAI will translate all content to this language first, then to the target language if needed. This enables multi-step translation through your preferred intermediate language.', 'polytrans'); ?></small>
            </div>

            <!-- Assistants Section -->
            <div class="openai-assistants-section" style="margin-top:2em;">
                <h3><?php esc_html_e('Translation Assistants', 'polytrans'); ?></h3>
                <p><?php esc_html_e('Configure OpenAI assistants for different language pairs. Each assistant should be trained for a specific translation direction (e.g., "en_to_pl").', 'polytrans'); ?></p>
                
                <div class="assistants-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1em;margin-top:1em;">
                    <?php
                    // Generate all possible language pairs
                    $language_pairs = [];
                    foreach ($languages as $source_lang) {
                        foreach ($languages as $target_lang) {
                            if ($source_lang !== $target_lang) {
                                $language_pairs[] = [$source_lang, $target_lang];
                            }
                        }
                    }

                    // Group by source language for better organization
                    $grouped_pairs = [];
                    foreach ($language_pairs as [$source, $target]) {
                        $grouped_pairs[$source][] = $target;
                    }

                    foreach ($grouped_pairs as $source_lang => $target_langs):
                        $source_name = $lang_names[array_search($source_lang, $languages)] ?? strtoupper($source_lang);
                    ?>
                        <div class="language-pair-group" style="border:1px solid #ddd;padding:1em;border-radius:4px;">
                            <h4><?php echo esc_html(sprintf(__('From %s', 'polytrans'), $source_name)); ?></h4>
                            <?php foreach ($target_langs as $target_lang):
                                $target_name = $lang_names[array_search($target_lang, $languages)] ?? strtoupper($target_lang);
                                $pair_key = $source_lang . '_to_' . $target_lang;
                                $assistant_id = $openai_assistants[$pair_key] ?? '';
                            ?>
                                <div style="margin-bottom:0.5em;">
                                    <label style="display:block;font-weight:500;">
                                        <?php echo esc_html(sprintf(__('To %s', 'polytrans'), $target_name)); ?>
                                    </label>
                                    <input type="text"
                                        name="openai_assistants[<?php echo esc_attr($pair_key); ?>]"
                                        value="<?php echo esc_attr($assistant_id); ?>"
                                        placeholder="asst_..."
                                        style="width:100%;max-width:250px;"
                                        class="openai-assistant-input" />
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div style="margin-top:1em;">
                    <small>
                        <?php esc_html_e('Assistant IDs start with "asst_". Leave blank for language pairs you don\'t want to support. You can create assistants at', 'polytrans'); ?>
                        <a href="https://platform.openai.com/assistants" target="_blank">https://platform.openai.com/assistants</a>
                    </small>
                </div>
            </div>

            <!-- Test Translation Section -->
            <div class="openai-test-section" style="margin-top:2em;padding:1em;background:#f9f9f9;border-radius:4px;">
                <h3><?php esc_html_e('Test Translation', 'polytrans'); ?></h3>
                <p><?php esc_html_e('Test your OpenAI configuration with a sample translation.', 'polytrans'); ?></p>
                
                <div style="display:flex;gap:1em;margin-bottom:1em;">
                    <select id="test-source-lang" style="max-width:150px;">
                        <?php foreach ($languages as $i => $lang): ?>
                            <option value="<?php echo esc_attr($lang); ?>">
                                <?php echo esc_html($lang_names[$i] ?? strtoupper($lang)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span style="align-self:center;">→</span>
                    <select id="test-target-lang" style="max-width:150px;">
                        <?php foreach ($languages as $i => $lang): ?>
                            <option value="<?php echo esc_attr($lang); ?>" <?php if ($i === 1) echo 'selected'; ?>>
                                <?php echo esc_html($lang_names[$i] ?? strtoupper($lang)); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <textarea id="test-text" placeholder="<?php esc_attr_e('Enter text to translate...', 'polytrans'); ?>" style="width:100%;height:80px;margin-bottom:0.5em;"></textarea>
                <button type="button" id="test-openai-translation" class="button"><?php esc_html_e('Test Translation', 'polytrans'); ?></button>
                <div id="test-translation-result" style="margin-top:1em;"></div>
            </div>
        </div>
<?php
    }

    /**
     * Validate OpenAI settings
     */
    public static function validate_settings($posted_data)
    {
        $validated = [];

        // Validate API key
        if (isset($posted_data['openai_api_key'])) {
            $validated['openai_api_key'] = sanitize_text_field($posted_data['openai_api_key']);
        }

        // Validate source language
        if (isset($posted_data['openai_source_language'])) {
            $validated['openai_source_language'] = sanitize_text_field($posted_data['openai_source_language']);
        }

        // Validate assistants
        if (isset($posted_data['openai_assistants']) && is_array($posted_data['openai_assistants'])) {
            $validated['openai_assistants'] = [];
            foreach ($posted_data['openai_assistants'] as $pair => $assistant_id) {
                $pair = sanitize_text_field($pair);
                $assistant_id = sanitize_text_field($assistant_id);
                
                // Only save non-empty assistant IDs
                if (!empty($assistant_id)) {
                    $validated['openai_assistants'][$pair] = $assistant_id;
                }
            }
        }

        return $validated;
    }
}
