<?php
/**
 * Assistant Executor - AI Assistant Execution Engine
 *
 * Executes AI assistants with prompt interpolation and API calls.
 * Part of Phase 1: AI Assistants Management System
 *
 * @package PolyTrans
 * @subpackage Assistants
 * @since 1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class PolyTrans_Assistant_Executor
 *
 * Executes AI assistants by loading configuration, interpolating prompts,
 * calling provider APIs, and processing responses.
 */
class PolyTrans_Assistant_Executor {

	/**
	 * Execute assistant by ID
	 *
	 * @param int   $assistant_id Assistant ID from database.
	 * @param array $context      Execution context (post data, variables).
	 * @return array|WP_Error Execution result or error.
	 */
	public static function execute( $assistant_id, $context ) {
		// Load assistant from database
		$assistant = PolyTrans_Assistant_Manager::get_assistant( $assistant_id );

		if ( null === $assistant ) {
			return new WP_Error( 'assistant_not_found', __( 'Assistant not found', 'polytrans' ) );
		}

		// Check if assistant is active
		if ( 'active' !== $assistant['status'] ) {
			return new WP_Error( 'assistant_inactive', __( 'Assistant is inactive', 'polytrans' ) );
		}

		// Execute using the assistant configuration
		return self::execute_with_config( $assistant, $context );
	}

	/**
	 * Execute assistant with direct configuration (no database lookup)
	 *
	 * @param array $config  Assistant configuration.
	 * @param array $context Execution context.
	 * @return array|WP_Error Execution result or error.
	 */
	public static function execute_with_config( $config, $context ) {
		// Validate required fields
		if ( empty( $config['system_prompt'] ) ) {
			return new WP_Error( 'invalid_config', __( 'Missing required field: system_prompt', 'polytrans' ) );
		}

		if ( empty( $config['api_parameters'] ) ) {
			return new WP_Error( 'invalid_config', __( 'Missing required field: api_parameters', 'polytrans' ) );
		}

		// Interpolate prompts with context variables
		$prompts = self::interpolate_prompts( $config, $context );

		if ( is_wp_error( $prompts ) ) {
			return $prompts;
		}

		// Call provider API
		$api_response = self::call_provider_api( $config, $prompts );

		if ( is_wp_error( $api_response ) ) {
			return $api_response;
		}

		// Process response based on expected format
		$processed = self::process_response( $api_response, $config );

		if ( is_wp_error( $processed ) ) {
			return $processed;
		}

		// Return standardized result
		return array(
			'success'      => true,
			'output'       => $processed['output'],
			'provider'     => $config['provider'] ?? 'openai',
			'model'        => $config['api_parameters']['model'] ?? 'unknown',
			'usage'        => $api_response['usage'] ?? array(),
			'raw_response' => $api_response,
		);
	}

	/**
	 * Interpolate prompts using Variable Manager (Twig)
	 *
	 * @param array $config  Assistant configuration.
	 * @param array $context Execution context.
	 * @return array|WP_Error Interpolated prompts or error.
	 */
	public static function interpolate_prompts( $config, $context ) {
		// Load Variable Manager
		if ( ! class_exists( 'PolyTrans_Variable_Manager' ) ) {
			require_once POLYTRANS_PLUGIN_DIR . 'includes/postprocessing/class-variable-manager.php';
		}

		$variable_manager = new PolyTrans_Variable_Manager();

		try {
			// Interpolate system prompt (required)
			$system_prompt = $variable_manager->interpolate_template( $config['system_prompt'], $context );

			// Interpolate user message template (optional)
			$user_message = null;
			if ( ! empty( $config['user_message_template'] ) ) {
				$user_message = $variable_manager->interpolate_template( $config['user_message_template'], $context );
			}

			return array(
				'system_prompt' => $system_prompt,
				'user_message'  => $user_message,
			);
		} catch ( Exception $e ) {
			return new WP_Error( 'interpolation_error', $e->getMessage() );
		}
	}

	/**
	 * Call provider API (OpenAI, Claude, Gemini)
	 *
	 * @param array $config  Assistant configuration.
	 * @param array $prompts Interpolated prompts.
	 * @return array|WP_Error API response or error.
	 */
	public static function call_provider_api( $config, $prompts ) {
		$provider = $config['provider'] ?? 'openai';

		switch ( $provider ) {
			case 'openai':
				return self::call_openai_chat( $config, $prompts );

			case 'claude':
				// TODO: Implement Claude API in Phase 1 follow-up
				return new WP_Error( 'unsupported_provider', __( 'Claude provider not yet implemented', 'polytrans' ) );

			case 'gemini':
				// TODO: Implement Gemini API in Phase 1 follow-up
				return new WP_Error( 'unsupported_provider', __( 'Gemini provider not yet implemented', 'polytrans' ) );

			default:
				return new WP_Error( 'unsupported_provider', __( 'Provider not supported', 'polytrans' ) );
		}
	}

	/**
	 * Call OpenAI Chat Completions API
	 *
	 * @param array $config  Assistant configuration.
	 * @param array $prompts Interpolated prompts.
	 * @return array|WP_Error API response or error.
	 */
	private static function call_openai_chat( $config, $prompts ) {
		// Get OpenAI API key from settings
		$settings = get_option( 'polytrans_settings', array() );
		$api_key  = $settings['openai_api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return new WP_Error( 'missing_api_key', __( 'OpenAI API key not configured', 'polytrans' ) );
		}

		// Build messages array
		$messages = array(
			array(
				'role'    => 'system',
				'content' => $prompts['system_prompt'],
			),
		);

		if ( ! empty( $prompts['user_message'] ) ) {
			$messages[] = array(
				'role'    => 'user',
				'content' => $prompts['user_message'],
			);
		}

		// Build API request body
		$body = array_merge(
			array(
				'model'    => $config['api_parameters']['model'] ?? 'gpt-4o-mini',
				'messages' => $messages,
			),
			$config['api_parameters']
		);

		// Remove 'model' from api_parameters merge (already set above)
		unset( $body['model'] );
		$body['model'] = $config['api_parameters']['model'] ?? 'gpt-4o-mini';

		// Make API request
		$response = wp_remote_post(
			'https://api.openai.com/v1/chat/completions',
			array(
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 120,
			)
		);

		// Handle errors
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'api_error', $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body_data   = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle API errors
		if ( $status_code !== 200 ) {
			$error_message = $body_data['error']['message'] ?? 'Unknown API error';
			$error_code    = $status_code === 429 ? 'rate_limit' : 'api_error';

			return new WP_Error(
				$error_code,
				$error_message,
				array(
					'status'        => $status_code,
					'retry_after'   => wp_remote_retrieve_header( $response, 'retry-after' ),
					'error_details' => $body_data,
				)
			);
		}

		return $body_data;
	}

	/**
	 * Process API response based on expected format
	 *
	 * @param array $response API response.
	 * @param array $config   Assistant configuration.
	 * @return array|WP_Error Processed output or error.
	 */
	public static function process_response( $response, $config ) {
		$expected_format = $config['expected_format'] ?? 'text';

		// Extract content from response (handle different API formats)
		$content = self::extract_content_from_response( $response, $config['provider'] ?? 'openai' );

		if ( null === $content ) {
			return new WP_Error( 'invalid_response', __( 'Failed to extract content from API response', 'polytrans' ) );
		}

		// Process based on expected format
		switch ( $expected_format ) {
			case 'text':
				return array( 'output' => $content );

			case 'json':
				return self::process_json_response( $content, $config );

			default:
				return array( 'output' => $content );
		}
	}

	/**
	 * Extract content from API response (handle different providers)
	 *
	 * @param array  $response API response.
	 * @param string $provider Provider name.
	 * @return string|null Content or null if not found.
	 */
	private static function extract_content_from_response( $response, $provider ) {
		switch ( $provider ) {
			case 'openai':
				return $response['choices'][0]['message']['content'] ?? null;

			case 'claude':
				// Claude format: content[0].text
				return $response['content'][0]['text'] ?? null;

			case 'gemini':
				// TODO: Add Gemini response format
				return null;

			default:
				return null;
		}
	}

	/**
	 * Process JSON response
	 *
	 * @param string $content JSON content.
	 * @param array  $config  Assistant configuration.
	 * @return array|WP_Error Parsed JSON or error.
	 */
	private static function process_json_response( $content, $config ) {
		// Parse JSON
		$decoded = json_decode( $content, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error(
				'invalid_json',
				__( 'Failed to parse JSON response', 'polytrans' ),
				array(
					'json_error' => json_last_error_msg(),
					'content'    => substr( $content, 0, 200 ),
				)
			);
		}

		// Validate output_variables if specified
		if ( ! empty( $config['output_variables'] ) ) {
			$missing = array();
			foreach ( $config['output_variables'] as $var ) {
				if ( ! isset( $decoded[ $var ] ) ) {
					$missing[] = $var;
				}
			}

			if ( ! empty( $missing ) ) {
				// Return partial result with warnings
				return array(
					'output'   => $decoded,
					'warnings' => array(
						sprintf(
							// translators: %s is a comma-separated list of missing variable names
							__( 'Missing expected fields: %s', 'polytrans' ),
							implode( ', ', $missing )
						),
					),
				);
			}
		}

		return array( 'output' => $decoded );
	}
}
