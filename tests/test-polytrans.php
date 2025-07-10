<?php
/**
 * Test case for PolyTrans main class
 */

class Test_PolyTrans extends WP_UnitTestCase {

    /**
     * Test plugin initialization
     */
    public function test_plugin_initialization() {
        $this->assertTrue(class_exists('PolyTrans'));
        
        $instance = PolyTrans::get_instance();
        $this->assertInstanceOf('PolyTrans', $instance);
        
        // Test singleton pattern
        $second_instance = PolyTrans::get_instance();
        $this->assertSame($instance, $second_instance);
    }

    /**
     * Test plugin constants are defined
     */
    public function test_plugin_constants() {
        $this->assertTrue(defined('POLYTRANS_VERSION'));
        $this->assertTrue(defined('POLYTRANS_PLUGIN_DIR'));
        $this->assertTrue(defined('POLYTRANS_PLUGIN_URL'));
        $this->assertTrue(defined('POLYTRANS_PLUGIN_FILE'));
    }

    /**
     * Test plugin activation
     */
    public function test_plugin_activation() {
        // Test that default settings are created
        $settings = get_option('polytrans_settings');
        $this->assertIsArray($settings);
        
        // Test that required tables are created (if DB logging enabled)
        global $wpdb;
        $table_name = $wpdb->prefix . 'polytrans_logs';
        
        if (isset($settings['enable_db_logging']) && $settings['enable_db_logging']) {
            $this->assertEquals($table_name, $wpdb->get_var("SHOW TABLES LIKE '{$table_name}'"));
        }
    }
}
