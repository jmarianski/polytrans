<?php

/**
 * Variable Provider Interface
 * 
 * Defines the structure for data providers that supply variables
 * to workflow execution context.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PolyTrans_Variable_Provider_Interface
{
    /**
     * Get the provider identifier
     * 
     * @return string Provider identifier (e.g., 'post_data', 'meta_data', 'context_data')
     */
    public function get_provider_id();

    /**
     * Get the provider name
     * 
     * @return string Human-readable provider name
     */
    public function get_provider_name();

    /**
     * Get variables provided by this provider
     * 
     * @param array $context Execution context (post IDs, language, etc.)
     * @return array Associative array of variables
     */
    public function get_variables($context);

    /**
     * Get list of variable names this provider can supply
     * 
     * @return array Array of variable names (for documentation/UI)
     */
    public function get_available_variables();

    /**
     * Check if provider can supply variables for given context
     * 
     * @param array $context Execution context
     * @return bool True if provider can supply variables
     */
    public function can_provide($context);

    /**
     * Get variable documentation for UI display
     * 
     * @return array Array with variable descriptions and examples
     */
    public function get_variable_documentation();
}
