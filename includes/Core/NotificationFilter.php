<?php

namespace PolyTrans\Core;

/**
 * Filters email notifications based on user roles and email domains.
 * 
 * Prevents sending notifications to external users (e.g., guest authors)
 * who are not part of the internal team.
 * 
 * @package PolyTrans\Core
 * @since 1.6.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class NotificationFilter
{
    /**
     * Check if a user should receive email notifications.
     * 
     * @param int|\WP_User $user User ID or WP_User object
     * @return bool True if user should receive notifications, false otherwise
     */
    public static function should_notify_user($user)
    {
        if (is_numeric($user)) {
            $user = get_user_by('id', $user);
        }

        if (!$user || !($user instanceof \WP_User)) {
            return false;
        }

        $settings = get_option('polytrans_settings', []);
        
        // If no filters configured, allow all (backward compatibility)
        $has_role_filter = !empty($settings['notification_allowed_roles']);
        $has_domain_filter = !empty($settings['notification_allowed_domains']);
        
        if (!$has_role_filter && !$has_domain_filter) {
            return true;
        }

        // Check role filter
        if ($has_role_filter) {
            $allowed_roles = (array) $settings['notification_allowed_roles'];
            $user_roles = (array) $user->roles;
            
            // If user has any of the allowed roles, they pass the role filter
            $role_match = !empty(array_intersect($user_roles, $allowed_roles));
            
            if (!$role_match) {
                \PolyTrans_Logs_Manager::log(
                    "Notification blocked for user {$user->user_email} (ID: {$user->ID}): role not in allowed list",
                    "info",
                    [
                        'user_roles' => $user_roles,
                        'allowed_roles' => $allowed_roles
                    ]
                );
                return false;
            }
        }

        // Check domain filter
        if ($has_domain_filter) {
            $allowed_domains = (array) $settings['notification_allowed_domains'];
            $user_email = $user->user_email;
            $user_domain = self::extract_domain($user_email);
            
            $domain_match = in_array($user_domain, $allowed_domains, true);
            
            if (!$domain_match) {
                \PolyTrans_Logs_Manager::log(
                    "Notification blocked for user {$user->user_email} (ID: {$user->ID}): domain not in allowed list",
                    "info",
                    [
                        'user_domain' => $user_domain,
                        'allowed_domains' => $allowed_domains
                    ]
                );
                return false;
            }
        }

        // User passed all filters
        return true;
    }

    /**
     * Extract domain from email address.
     * 
     * @param string $email Email address
     * @return string Domain part (e.g., "example.com")
     */
    private static function extract_domain($email)
    {
        $parts = explode('@', $email);
        return isset($parts[1]) ? strtolower(trim($parts[1])) : '';
    }

    /**
     * Get all available WordPress roles for settings UI.
     * 
     * @return array Array of role slug => role name
     */
    public static function get_available_roles()
    {
        global $wp_roles;
        
        if (!isset($wp_roles)) {
            $wp_roles = new \WP_Roles();
        }
        
        return $wp_roles->get_names();
    }

    /**
     * Sanitize and validate domain list.
     * 
     * @param string|array $domains Comma-separated string or array of domains
     * @return array Array of sanitized domains
     */
    public static function sanitize_domains($domains)
    {
        if (is_string($domains)) {
            $domains = explode(',', $domains);
        }
        
        if (!is_array($domains)) {
            return [];
        }
        
        $sanitized = [];
        foreach ($domains as $domain) {
            $domain = strtolower(trim($domain));
            
            // Remove protocol if present
            $domain = preg_replace('#^https?://#i', '', $domain);
            
            // Remove www. if present
            $domain = preg_replace('#^www\.#i', '', $domain);
            
            // Remove trailing slash
            $domain = rtrim($domain, '/');
            
            // Basic validation: must contain at least one dot and no spaces
            if (!empty($domain) && strpos($domain, '.') !== false && strpos($domain, ' ') === false) {
                $sanitized[] = $domain;
            }
        }
        
        return array_unique($sanitized);
    }
}

