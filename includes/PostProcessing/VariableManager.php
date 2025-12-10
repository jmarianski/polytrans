<?php

namespace PolyTrans\PostProcessing;

/**
 * Variable Manager
 * 
 * Handles variable interpolation, context building, and data passing
 * between workflow steps.
 */

if (!defined('ABSPATH')) {
    exit;
}

class VariableManager
{
    /**
     * Lazy load Twig Engine (requires Composer autoloader)
     *
     * @return void
     */
    private function load_twig_engine() {
        if (!class_exists('PolyTrans_Twig_Engine')) {
            $twig_engine_path = dirname(__DIR__) . '/templating/class-twig-template-engine.php';
            if (file_exists($twig_engine_path)) {
                require_once $twig_engine_path;
            }
        }
    }

    /**
     * Build execution context from providers
     * 
     * @param array $context Base context (post IDs, language, etc.)
     * @param array $data_providers Array of variable providers
     * @return array Complete variable context
     */
    public function build_context($context, $data_providers)
    {
        $variable_context = $context;

        // Get variables from each provider
        foreach ($data_providers as $provider) {
            if ($provider instanceof PolyTrans_Variable_Provider_Interface) {
                try {
                    if ($provider->can_provide($context)) {
                        $provider_variables = $provider->get_variables($context);
                        if (is_array($provider_variables)) {
                            $variable_context = array_merge($variable_context, $provider_variables);
                        }
                    }
                } catch (Exception $e) {
                    // Log error but continue with other providers
                    PolyTrans_Logs_Manager::log("Variable Provider Error: " . $e->getMessage(), 'error', [
                        'source' => 'variable_manager',
                        'provider_class' => get_class($provider),
                        'exception' => $e->getMessage()
                    ]);
                }
            }
        }

        return $variable_context;
    }

    /**
     * Interpolate variables in a string template
     *
     * Uses Twig template engine for modern templating with fallback to regex.
     * Supports both legacy {variable} syntax and Twig {{ variable }} syntax.
     *
     * @param string $template Template string with {variable} placeholders
     * @param array $context Variable context
     * @return string Interpolated string
     */
    public function interpolate_template($template, $context)
    {
        if (!is_string($template)) {
            return $template;
        }

        // Lazy load Twig Engine (requires Composer autoloader)
        $this->load_twig_engine();

        // Use Twig Engine if available
        if (class_exists('PolyTrans_Twig_Engine')) {
            try {
                return PolyTrans_Twig_Engine::render($template, $context);
            } catch (Exception $e) {
                // Twig failed, fall back to legacy regex
                PolyTrans_Logs_Manager::log(
                    "Twig rendering failed, using legacy regex: " . $e->getMessage(),
                    'warning',
                    [
                        'source' => 'variable_manager',
                        'template_preview' => substr($template, 0, 100),
                        'exception' => $e->getMessage()
                    ]
                );
            }
        }

        // Fallback to legacy regex interpolation
        return $this->interpolate_template_legacy($template, $context);
    }

    /**
     * Legacy regex-based variable interpolation (fallback)
     *
     * @param string $template Template string with {variable} placeholders
     * @param array $context Variable context
     * @return string Interpolated string
     */
    private function interpolate_template_legacy($template, $context)
    {
        // Find variable placeholders (but not JSON structures)
        // This pattern matches {variable_name} or {object.property} but not JSON like {"key": "value"}
        $pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';

        return preg_replace_callback($pattern, function ($matches) use ($context) {
            $variable_path = $matches[1];
            $value = $this->get_variable_value($variable_path, $context);

            // Convert arrays/objects to JSON for display
            if (is_array($value) || is_object($value)) {
                return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
            }

            return (string)$value;
        }, $template);
    }

    /**
     * Get variable value from context using dot notation
     * 
     * @param string $variable_path Variable path (e.g., 'original_post.title', 'meta.custom_field')
     * @param array $context Variable context
     * @return mixed Variable value or empty string if not found
     */
    public function get_variable_value($variable_path, $context)
    {
        $parts = explode('.', $variable_path);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                return '';
            }
        }

        return $current;
    }

    /**
     * Set variable value in context using dot notation
     * 
     * @param string $variable_path Variable path
     * @param mixed $value Value to set
     * @param array &$context Variable context (passed by reference)
     */
    public function set_variable_value($variable_path, $value, &$context)
    {
        $parts = explode('.', $variable_path);
        $current = &$context;

        // Navigate to the parent of the target variable
        for ($i = 0; $i < count($parts) - 1; $i++) {
            $part = $parts[$i];

            if (!isset($current[$part]) || !is_array($current[$part])) {
                $current[$part] = [];
            }

            $current = &$current[$part];
        }

        // Set the final value
        $final_key = end($parts);
        $current[$final_key] = $value;
    }

    /**
     * Extract variables from text using patterns
     * 
     * @param string $text Text to search for variables
     * @return array Array of found variable names
     */
    public function extract_variables($text)
    {
        // Use the same pattern as interpolate_template to ensure consistency
        $pattern = '/\{([a-zA-Z_][a-zA-Z0-9_]*(?:\.[a-zA-Z_][a-zA-Z0-9_]*)*)\}/';
        preg_match_all($pattern, $text, $matches);

        return array_unique($matches[1]);
    }

    /**
     * Validate that all required variables exist in context
     * 
     * @param array $required_variables Array of required variable names
     * @param array $context Variable context
     * @return array Validation result with 'valid' boolean and 'missing' array
     */
    public function validate_required_variables($required_variables, $context)
    {
        $missing = [];

        foreach ($required_variables as $variable_name) {
            if (!$this->variable_exists($variable_name, $context)) {
                $missing[] = $variable_name;
            }
        }

        return [
            'valid' => empty($missing),
            'missing' => $missing
        ];
    }

    /**
     * Check if a variable exists in context
     * 
     * @param string $variable_path Variable path
     * @param array $context Variable context
     * @return bool
     */
    public function variable_exists($variable_path, $context)
    {
        $parts = explode('.', $variable_path);
        $current = $context;

        foreach ($parts as $part) {
            if (is_array($current) && isset($current[$part])) {
                $current = $current[$part];
            } elseif (is_object($current) && isset($current->$part)) {
                $current = $current->$part;
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Get all available variables from context (flattened with dot notation)
     * 
     * @param array $context Variable context
     * @param string $prefix Prefix for nested variables
     * @return array Array of variable paths
     */
    public function get_available_variables($context, $prefix = '')
    {
        $variables = [];

        foreach ($context as $key => $value) {
            $full_key = $prefix ? "{$prefix}.{$key}" : $key;

            if (is_array($value) && !empty($value)) {
                // Add the array itself
                $variables[] = $full_key;

                // Add nested variables (limit depth to avoid infinite recursion)
                if (substr_count($full_key, '.') < 3) {
                    $nested = $this->get_available_variables($value, $full_key);
                    $variables = array_merge($variables, $nested);
                }
            } else {
                $variables[] = $full_key;
            }
        }

        return $variables;
    }

    /**
     * Sanitize variable value for safe output
     * 
     * @param mixed $value Variable value
     * @param string $context Sanitization context ('html', 'attribute', 'url', etc.)
     * @return mixed Sanitized value
     */
    public function sanitize_variable($value, $context = 'html')
    {
        if (is_array($value) || is_object($value)) {
            return $value; // Don't sanitize complex types
        }

        $value = (string)$value;

        switch ($context) {
            case 'html':
                return wp_kses_post($value);

            case 'attribute':
                return esc_attr($value);

            case 'url':
                return esc_url($value);

            case 'text':
                return sanitize_text_field($value);

            case 'textarea':
                return sanitize_textarea_field($value);

            default:
                return wp_kses_post($value);
        }
    }

    /**
     * Create a variable context summary for debugging
     * 
     * @param array $context Variable context
     * @return array Simplified context summary
     */
    public function create_context_summary($context)
    {
        $summary = [];

        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $summary[$key] = '[Array with ' . count($value) . ' items]';
            } elseif (is_object($value)) {
                $summary[$key] = '[Object: ' . get_class($value) . ']';
            } elseif (is_string($value) && strlen($value) > 100) {
                $summary[$key] = '[String: ' . strlen($value) . ' chars] ' . substr($value, 0, 100) . '...';
            } else {
                $summary[$key] = $value;
            }
        }

        return $summary;
    }

    /**
     * Convert array data to JSON format for AI consumption
     * 
     * @param array $data Data to convert
     * @return string JSON string
     */
    public function array_to_json($data)
    {
        return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /**
     * Parse JSON response and extract variables
     * 
     * @param string $json_response JSON response from AI
     * @return array Parsed variables or empty array if parsing fails
     */
    public function parse_json_response($json_response)
    {
        $decoded = json_decode($json_response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            // Try to extract JSON from mixed content
            $pattern = '/\{.*\}/s';
            if (preg_match($pattern, $json_response, $matches)) {
                $decoded = json_decode($matches[0], true);
            }
        }

        return is_array($decoded) ? $decoded : [];
    }
}
