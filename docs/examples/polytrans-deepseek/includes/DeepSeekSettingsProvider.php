<?php
/**
 * DeepSeek Settings Provider
 * Implements SettingsProviderInterface for DeepSeek configuration
 */

if (!defined('ABSPATH')) {
    exit;
}

class DeepSeekSettingsProvider implements \PolyTrans\Providers\SettingsProviderInterface
{
    public function get_provider_id()
    {
        return 'deepseek';
    }
    
    public function get_tab_label()
    {
        return 'DeepSeek Configuration';
    }
    
    public function get_tab_description()
    {
        return 'Configure your DeepSeek API key and settings for AI-powered translations.';
    }
    
    public function get_required_js_files()
    {
        return []; // Uses universal JS system via data attributes
    }
    
    public function get_required_css_files()
    {
        return [];
    }
    
    public function get_settings_keys()
    {
        return [
            'deepseek_api_key',
            'deepseek_model',
        ];
    }
    
    public function render_settings_ui(array $settings, array $languages, array $language_names)
    {
        // DeepSeek uses universal UI - no custom implementation needed!
        // Universal UI will automatically render:
        // - API Key field (from manifest: api_key_setting)
        // - Model selection (from manifest: capabilities include 'chat')
        // 
        // To use custom UI, uncomment and customize below:
        /*
        $api_key = $settings['deepseek_api_key'] ?? '';
        $model = $settings['deepseek_model'] ?? 'deepseek-chat';
        ?>
        <div class="deepseek-config-section">
            <h2><?php echo esc_html($this->get_tab_label()); ?></h2>
            <p><?php echo esc_html($this->get_tab_description()); ?></p>
            
            <!-- Custom UI here -->
        </div>
        <?php
        */
        
        // Return empty to use universal UI
        return;
    }
    
    public function validate_settings(array $posted_data)
    {
        $validated = [];
        
        if (isset($posted_data['deepseek_api_key'])) {
            $validated['deepseek_api_key'] = sanitize_text_field($posted_data['deepseek_api_key']);
        }
        
        if (isset($posted_data['deepseek_model'])) {
            $validated['deepseek_model'] = sanitize_text_field($posted_data['deepseek_model']);
        }
        
        return $validated;
    }
    
    public function get_default_settings()
    {
        return [
            'deepseek_api_key' => '',
            'deepseek_model' => 'deepseek-chat',
        ];
    }
    
    public function is_configured(array $settings)
    {
        return !empty($settings['deepseek_api_key'] ?? '');
    }
    
    public function get_configuration_status(array $settings)
    {
        if (!$this->is_configured($settings)) {
            return 'DeepSeek API key not configured';
        }
        return '';
    }
    
    public function enqueue_assets()
    {
        // Uses universal JS system - no custom assets needed
    }
    
    public function get_ajax_handlers()
    {
        return []; // Uses universal endpoints
    }
    
    public function register_ajax_handlers()
    {
        // Uses universal endpoints - no custom handlers needed
    }
    
    public function get_provider_manifest(array $settings)
    {
        $api_key = $settings['deepseek_api_key'] ?? '';
        
        return [
            'provider_id' => 'deepseek',
            'capabilities' => ['chat'], // DeepSeek has chat API, but NO dedicated Assistants API
            // Can be used for managed assistants (with system prompt), but not for predefined assistants
            'chat_endpoint' => 'https://api.deepseek.com/v1/chat/completions',
            'models_endpoint' => 'https://api.deepseek.com/v1/models',
            'auth_type' => 'bearer',
            'auth_header' => 'Authorization',
            'api_key_setting' => 'deepseek_api_key',
            'api_key_configured' => !empty($api_key) && current_user_can('manage_options'),
            'base_url' => 'https://api.deepseek.com/v1',
            'supports_system_prompt' => false, // DeepSeek API doesn't support system role, only user messages
        ];
    }
    
    public function validate_api_key(string $api_key): bool
    {
        if (empty($api_key)) {
            return false;
        }
        
        // Validate by calling a simple endpoint (e.g., /models)
        try {
            $response = wp_remote_get('https://api.deepseek.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return false;
            }
            
            $status_code = wp_remote_retrieve_response_code($response);
            return $status_code === 200;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    public function load_assistants(array $settings): array
    {
        // DeepSeek doesn't have a dedicated Assistants API
        // Managed assistants can still use DeepSeek via chat API with system prompt
        // But there are no predefined assistants to load
        return [];
    }
    
    public function load_models(array $settings): array
    {
        $api_key = $settings['deepseek_api_key'] ?? '';
        
        if (empty($api_key)) {
            return $this->get_fallback_models();
        }
        
        try {
            $response = wp_remote_get('https://api.deepseek.com/v1/models', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'timeout' => 10,
            ]);
            
            if (is_wp_error($response)) {
                return $this->get_fallback_models();
            }
            
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);
            
            $grouped = [
                'DeepSeek Models' => [],
            ];
            
            foreach ($data['data'] ?? [] as $model) {
                $model_id = $model['id'] ?? '';
                $model_name = $model['name'] ?? $model_id;
                $grouped['DeepSeek Models'][$model_id] = $model_name;
            }
            
            return $grouped;
        } catch (\Exception $e) {
            return $this->get_fallback_models();
        }
    }
    
    private function get_fallback_models()
    {
        return [
            'DeepSeek Models' => [
                'deepseek-chat' => 'DeepSeek Chat',
                'deepseek-coder' => 'DeepSeek Coder',
            ],
        ];
    }
}

