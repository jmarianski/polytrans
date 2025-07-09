<?php

/**
 * Handles security validation for translation receiver endpoints.
 */

if (!defined('ABSPATH')) {
    exit;
}

class PolyTrans_Translation_Security_Manager
{
    /**
     * Validates permission for translation receiver endpoints.
     * 
     * @param WP_REST_Request $request The REST API request
     * @return bool True if authorized, false otherwise
     */
    public function validate_permission($request)
    {
        $settings = get_option('polytrans_settings', []);
        $secret = isset($settings['translation_receiver_secret']) ? $settings['translation_receiver_secret'] : '';
        $method = isset($settings['translation_receiver_secret_method']) ? $settings['translation_receiver_secret_method'] : 'header_bearer';

        if (!$secret || $method === 'none') {
            return true; // No secret configured, allow all requests
        }

        $provided_secret = '';

        switch ($method) {
            case 'get_param':
                $provided_secret = $request->get_param('secret') ?: '';
                break;
            case 'header_bearer':
                $auth_header = $request->get_header('authorization');
                if ($auth_header && strpos($auth_header, 'Bearer ') === 0) {
                    $provided_secret = substr($auth_header, 7);
                }
                break;
            case 'header_custom':
                $provided_secret = $request->get_header('x-polytrans-secret') ?: '';
                break;
            case 'post_param':
                $body = $request->get_json_params();
                $provided_secret = isset($body['secret']) ? $body['secret'] : '';
                break;
        }

        $is_valid = hash_equals($secret, $provided_secret);

        if (!$is_valid) {
            error_log('[polytrans] Translation receiver authentication failed');
        }

        return $is_valid;
    }

    /**
     * Validates IP address restrictions if configured.
     * 
     * @param WP_REST_Request $request The REST API request
     * @return bool True if IP is allowed, false otherwise
     */
    public function validate_ip_address($request)
    {
        $settings = get_option('polytrans_settings', []);
        $allowed_ips = isset($settings['allowed_ips']) ? $settings['allowed_ips'] : [];

        // If no IP restrictions configured, allow all
        if (empty($allowed_ips)) {
            return true;
        }

        $client_ip = $this->get_client_ip($request);

        foreach ($allowed_ips as $allowed_ip) {
            if ($this->ip_matches($client_ip, trim($allowed_ip))) {
                return true;
            }
        }

        error_log("[polytrans] Translation receiver IP restriction failed for IP: $client_ip");
        return false;
    }

    /**
     * Gets the client IP address from the request.
     * 
     * @param WP_REST_Request $request The REST API request
     * @return string Client IP address
     */
    private function get_client_ip($request)
    {
        // Check for shared internet/proxy
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        }
        // Check for IP passed from proxy
        elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        }
        // Check for IP from remote address
        elseif (!empty($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }

        return '0.0.0.0';
    }

    /**
     * Checks if client IP matches allowed IP (supports CIDR notation).
     * 
     * @param string $client_ip Client IP address
     * @param string $allowed_ip Allowed IP address or CIDR range
     * @return bool True if IP matches
     */
    private function ip_matches($client_ip, $allowed_ip)
    {
        // Exact match
        if ($client_ip === $allowed_ip) {
            return true;
        }

        // CIDR range check
        if (strpos($allowed_ip, '/') !== false) {
            list($subnet, $mask) = explode('/', $allowed_ip);
            $subnet_long = ip2long($subnet);
            $client_long = ip2long($client_ip);
            $mask_long = ~((1 << (32 - $mask)) - 1);

            return ($client_long & $mask_long) === ($subnet_long & $mask_long);
        }

        return false;
    }
}
