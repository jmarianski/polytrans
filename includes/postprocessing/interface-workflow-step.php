<?php

/**
 * Workflow Step Interface
 * 
 * Defines the structure for individual workflow steps that can be executed
 * as part of a post-processing workflow.
 */

if (!defined('ABSPATH')) {
    exit;
}

interface PolyTrans_Workflow_Step_Interface
{
    /**
     * Get the step type identifier
     * 
     * @return string Step type (e.g., 'ai_assistant', 'data_transform', 'validation')
     */
    public function get_type();

    /**
     * Get the step name/title
     * 
     * @return string Human-readable step name
     */
    public function get_name();

    /**
     * Get the step description
     * 
     * @return string Step description for UI display
     */
    public function get_description();

    /**
     * Execute the workflow step
     * 
     * @param array $context Variable context from previous steps and data providers
     * @param array $step_config Step-specific configuration
     * @return array Result containing 'success', 'data', and optionally 'error'
     */
    public function execute($context, $step_config);

    /**
     * Validate step configuration
     * 
     * @param array $step_config Step configuration to validate
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public function validate_config($step_config);

    /**
     * Get available output variables this step can produce
     * 
     * @return array Array of variable names this step can output
     */
    public function get_output_variables();

    /**
     * Get required input variables for this step
     * 
     * @return array Array of variable names required for execution
     */
    public function get_required_variables();

    /**
     * Get step configuration schema for UI generation
     * 
     * @return array Configuration schema defining UI fields
     */
    public function get_config_schema();
}
