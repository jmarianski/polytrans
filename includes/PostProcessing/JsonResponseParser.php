<?php

/**
 * JSON Response Parser
 * 
 * Robust JSON extraction and schema-based validation/coercion for AI responses.
 * Handles common AI quirks like wrapping JSON in code blocks, adding commentary, etc.
 */

namespace PolyTrans\PostProcessing;

if (!defined('ABSPATH')) {
    exit;
}

class JsonResponseParser
{
    /**
     * Extract JSON from AI response with multiple fallback strategies
     * 
     * @param string $response Raw AI response
     * @return array|null Parsed JSON as array, or null if extraction failed
     */
    public function extract_json($response)
    {
        if (empty($response)) {
            return null;
        }

        // Strategy 1: Try direct parse (clean JSON)
        $decoded = json_decode($response, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }

        // Strategy 2: Extract from ```json...``` code blocks
        if (preg_match('/```json\s*(.*?)\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Strategy 3: Extract from ``` blocks (without json tag)
        if (preg_match('/```\s*(.*?)\s*```/s', $response, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Strategy 4: Find JSON object anywhere in text (with proper nesting support)
        // Match balanced braces using recursive pattern
        if (preg_match('/\{(?:[^{}]|(?R))*\}/x', $response, $matches)) {
            $decoded = json_decode($matches[0], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // Strategy 5: Try to clean and parse
        $cleaned = trim($response);
        // Remove everything before first {
        $cleaned = preg_replace('/^[^{]*/', '', $cleaned);
        // Remove everything after last }
        $cleaned = preg_replace('/[^}]*$/', '', $cleaned);

        if (!empty($cleaned)) {
            $decoded = json_decode($cleaned, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }

        // All strategies failed
        return null;
    }

    /**
     * Normalize schema format (supports both simple and object format)
     * 
     * @param array $schema Schema definition
     * @return array ['normalized_schema' => [...], 'mappings' => [...]]
     */
    private function normalize_schema($schema)
    {
        $normalized = [];
        $mappings = [];

        foreach ($schema as $field_name => $definition) {
            if (is_array($definition)) {
                // Check if this is an object schema (has 'type' key) or nested schema
                if (isset($definition['type'])) {
                    // Object format: {"type": "string", "target": "post.title", "required": true}
                    $normalized[$field_name] = $definition['type'];

                    // Store target mapping if present
                    if (isset($definition['target'])) {
                        $mappings[$field_name] = [
                            'target' => $definition['target'],
                            'required' => $definition['required'] ?? false
                        ];
                    }
                } else {
                    // Nested schema - recursively normalize
                    $nested_result = $this->normalize_schema($definition);
                    $normalized[$field_name] = $nested_result['normalized_schema'];

                    // Prefix nested mappings with parent field name
                    foreach ($nested_result['mappings'] as $nested_field => $nested_mapping) {
                        $mappings[$field_name . '.' . $nested_field] = $nested_mapping;
                    }
                }
            } else {
                // Simple format: "string" or nested array
                $normalized[$field_name] = $definition;
            }
        }

        return [
            'normalized_schema' => $normalized,
            'mappings' => $mappings
        ];
    }

    /**
     * Parse response with schema validation and type coercion
     * 
     * @param string $response Raw AI response
     * @param array $schema Expected schema (supports simple and object format)
     * @return array Result with 'success', 'data', 'warnings', 'error', 'mappings'
     */
    public function parse_with_schema($response, $schema = [])
    {
        try {
            // Extract JSON
            $json_data = $this->extract_json($response);

            if ($json_data === null) {
                return [
                    'success' => false,
                    'error' => 'Failed to extract JSON from response',
                    'raw_response' => $response,
                    'mappings' => []
                ];
            }

            // If no schema provided, return all data as-is
            if (empty($schema)) {
                return [
                    'success' => true,
                    'data' => $json_data,
                    'warnings' => [],
                    'mappings' => []
                ];
            }

            // Normalize schema (supports both simple and object format)
            $schema_info = $this->normalize_schema($schema);
            $normalized_schema = $schema_info['normalized_schema'];
            $mappings = $schema_info['mappings'];

            // Validate and coerce according to schema
            $result_data = [];
            $warnings = [];

            // Process schema fields
            foreach ($normalized_schema as $field_name => $expected_type) {
                if (isset($json_data[$field_name])) {
                    $raw_value = $json_data[$field_name];

                    // Check if this is a nested schema (array with field definitions)
                    if (is_array($expected_type) && !empty($expected_type)) {
                        // Nested schema validation
                        if (is_array($raw_value)) {
                            $nested_result = [];
                            $nested_count = count($expected_type);
                            $processed_count = 0;

                            foreach ($expected_type as $nested_field => $nested_type) {
                                $processed_count++;
                                if (isset($raw_value[$nested_field])) {
                                    $nested_coerced = $this->coerce_type($raw_value[$nested_field], $nested_type);

                                    // Check nested type coercion
                                    if ($this->get_type($raw_value[$nested_field]) !== $nested_type && $nested_coerced !== null) {
                                        $warnings[] = sprintf(
                                            'Type coercion: %s.%s (%s → %s)',
                                            $field_name,
                                            $nested_field,
                                            $this->get_type($raw_value[$nested_field]),
                                            $nested_type
                                        );
                                    }

                                    $nested_result[$nested_field] = $nested_coerced;
                                } else {
                                    // Nested field missing
                                    $nested_result[$nested_field] = null;
                                    $warnings[] = sprintf('Missing nested field: %s.%s', $field_name, $nested_field);
                                }
                            }

                            // Preserve extra nested fields not in schema
                            foreach ($raw_value as $nested_field => $nested_value) {
                                if (!isset($expected_type[$nested_field])) {
                                    $nested_result[$nested_field] = $nested_value;
                                }
                            }

                            $result_data[$field_name] = $nested_result;
                        } else {
                            // Expected nested object but got something else
                            $warnings[] = sprintf('Type mismatch: %s (expected object with nested schema, got %s)', $field_name, $this->get_type($raw_value));
                            $result_data[$field_name] = null;
                        }
                    } else {
                        // Simple type validation
                        $coerced_value = $this->coerce_type($raw_value, $expected_type);

                        // Check if type coercion happened or failed
                        if ($this->get_type($raw_value) !== $expected_type) {
                            if ($coerced_value !== null) {
                                $warnings[] = sprintf(
                                    'Type coercion: %s (%s → %s)',
                                    $field_name,
                                    $this->get_type($raw_value),
                                    $expected_type
                                );
                            } else {
                                $warnings[] = sprintf(
                                    'Type coercion failed: %s (cannot convert %s to %s)',
                                    $field_name,
                                    $this->get_type($raw_value),
                                    $expected_type
                                );
                            }
                        }

                        $result_data[$field_name] = $coerced_value;
                    }
                } else {
                    // Field missing from response
                    $result_data[$field_name] = null;
                    $warnings[] = sprintf('Missing field: %s', $field_name);
                }
            }

            // Preserve extra fields not in schema (bonus data from AI)
            foreach ($json_data as $field_name => $value) {
                if (!isset($schema[$field_name])) {
                    $result_data[$field_name] = $value;
                }
            }

            return [
                'success' => true,
                'data' => $result_data,
                'warnings' => $warnings,
                'mappings' => $mappings
            ];
        } catch (\Throwable $e) {
            // Log exception with full details
            if (class_exists('\PolyTrans_Logs_Manager')) {
                \PolyTrans_Logs_Manager::log(
                    'JSON Response Parser exception: ' . $e->getMessage(),
                    'error',
                    [
                        'exception_class' => get_class($e),
                        'exception_file' => $e->getFile(),
                        'exception_line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'response_length' => strlen($response),
                    ]
                );
            }

            return [
                'success' => false,
                'error' => 'Parser exception: ' . $e->getMessage(),
                'exception' => get_class($e),
                'mappings' => []
            ];
        }
    }

    /**
     * Coerce value to expected type with intelligent fallbacks
     * 
     * @param mixed $value Raw value from JSON
     * @param string $expected_type Expected type (string, number, array, object, boolean)
     * @return mixed Coerced value or null if coercion impossible
     */
    private function coerce_type($value, $expected_type)
    {
        switch ($expected_type) {
            case 'string':
                if (is_array($value) || is_object($value)) {
                    return json_encode($value); // Convert complex types to JSON string
                }
                return (string) $value;

            case 'number':
                if (is_numeric($value)) {
                    // Preserve float vs int distinction
                    return strpos((string)$value, '.') !== false ? (float)$value : (int)$value;
                }
                // Can't convert non-numeric string to number
                return null;

            case 'array':
                if (is_array($value)) {
                    return $value;
                }
                // Wrap single value in array
                return [$value];

            case 'object':
                if (is_array($value)) {
                    return $value; // PHP arrays are objects in JSON context
                }
                if (is_object($value)) {
                    return (array) $value;
                }
                // Wrap in object
                return ['value' => $value];

            case 'boolean':
                if (is_bool($value)) {
                    return $value;
                }
                if (is_string($value)) {
                    $lower = strtolower(trim($value));
                    if (in_array($lower, ['true', 'yes', '1', 'tak', 'y'])) {
                        return true;
                    }
                    if (in_array($lower, ['false', 'no', '0', 'nie', 'n'])) {
                        return false;
                    }
                }
                // Use PHP's boolean conversion
                return (bool) $value;

            default:
                // Unknown type, return as-is
                return $value;
        }
    }

    /**
     * Get the type name of a value
     * 
     * @param mixed $value Value to check
     * @return string Type name (string, number, array, object, boolean, null)
     */
    private function get_type($value)
    {
        if (is_null($value)) {
            return 'null';
        }
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value) || is_float($value)) {
            return 'number';
        }
        if (is_array($value)) {
            return 'array';
        }
        if (is_object($value)) {
            return 'object';
        }
        if (is_string($value)) {
            return 'string';
        }
        return 'unknown';
    }
}
