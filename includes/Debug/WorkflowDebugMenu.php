<?php

/**
 * Workflow Debug Admin Menu
 * 
 * Provides a simple interface for debugging workflow issues
 */

namespace PolyTrans\Debug;

if (!defined('ABSPATH')) {
    exit;
}

class WorkflowDebugMenu
{
    /**
     * Singleton instance
     */
    private static $instance = null;

    /**
     * Get singleton instance
     */
    public static function get_instance()
    {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - register hooks
     */
    private function __construct()
    {
        add_action('admin_menu', [$this, 'add_admin_menu']);
    }

    /**
     * Add admin menu
     */
    public function add_admin_menu()
    {
        add_submenu_page(
            'polytrans-menu',
            'Workflow Debug',
            'Workflow Debug',
            'manage_options',
            'polytrans-workflow-debug',
            [$this, 'render_page']
        );
    }

    /**
     * Render debug page
     */
    public function render_page()
    {
?>
        <div class="wrap">
            <h1>PolyTrans Workflow Debug</h1>

            <?php if (isset($_POST['debug_submit'])): ?>
                <div class="notice notice-info">
                    <p>Running debug analysis...</p>
                </div>

                <?php
                $original_post_id = intval($_POST['original_post_id']);
                $translated_post_id = intval($_POST['translated_post_id']);
                $target_language = sanitize_text_field($_POST['target_language']);

                if ($original_post_id && $translated_post_id && $target_language) {
                    \PolyTrans_Workflow_Debug::debug_workflow_triggering($original_post_id, $translated_post_id, $target_language);
                } else {
                    echo "<div class='notice notice-error'><p>Please fill in all fields.</p></div>";
                }
                ?>

            <?php elseif (isset($_POST['simulate_submit'])): ?>
                <div class="notice notice-info">
                    <p>Simulating workflow trigger...</p>
                </div>

                <?php
                $original_post_id = intval($_POST['sim_original_post_id']);
                $translated_post_id = intval($_POST['sim_translated_post_id']);
                $target_language = sanitize_text_field($_POST['sim_target_language']);

                if ($original_post_id && $translated_post_id && $target_language) {
                    \PolyTrans_Workflow_Debug::simulate_workflow_trigger($original_post_id, $translated_post_id, $target_language);
                } else {
                    echo "<div class='notice notice-error'><p>Please fill in all fields.</p></div>";
                }
                ?>

            <?php endif; ?>

            <div class="debug-forms" style="display: flex; gap: 20px; margin-top: 20px;">
                <!-- Debug Form -->
                <div style="flex: 1; background: white; padding: 20px; border: 1px solid #ccd0d4;">
                    <h2>Debug Workflow Conditions</h2>
                    <p>This will analyze why workflows might not be triggering for a specific translation.</p>

                    <form method="post">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="original_post_id">Original Post ID</label></th>
                                <td><input type="number" id="original_post_id" name="original_post_id" value="<?php echo isset($_POST['original_post_id']) ? esc_attr($_POST['original_post_id']) : ''; ?>" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="translated_post_id">Translated Post ID</label></th>
                                <td><input type="number" id="translated_post_id" name="translated_post_id" value="<?php echo isset($_POST['translated_post_id']) ? esc_attr($_POST['translated_post_id']) : ''; ?>" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="target_language">Target Language</label></th>
                                <td>
                                    <input type="text" id="target_language" name="target_language" value="<?php echo isset($_POST['target_language']) ? esc_attr($_POST['target_language']) : 'en'; ?>" class="regular-text" required />
                                    <p class="description">Language code (e.g., 'en', 'fr', 'de')</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Debug Workflows', 'primary', 'debug_submit'); ?>
                    </form>
                </div>

                <!-- Simulate Form -->
                <div style="flex: 1; background: white; padding: 20px; border: 1px solid #ccd0d4;">
                    <h2>Simulate Workflow Trigger</h2>
                    <p>This will manually fire the translation completion action to test if workflows execute.</p>

                    <form method="post">
                        <table class="form-table">
                            <tr>
                                <th scope="row"><label for="sim_original_post_id">Original Post ID</label></th>
                                <td><input type="number" id="sim_original_post_id" name="sim_original_post_id" value="<?php echo isset($_POST['sim_original_post_id']) ? esc_attr($_POST['sim_original_post_id']) : ''; ?>" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sim_translated_post_id">Translated Post ID</label></th>
                                <td><input type="number" id="sim_translated_post_id" name="sim_translated_post_id" value="<?php echo isset($_POST['sim_translated_post_id']) ? esc_attr($_POST['sim_translated_post_id']) : ''; ?>" class="regular-text" required /></td>
                            </tr>
                            <tr>
                                <th scope="row"><label for="sim_target_language">Target Language</label></th>
                                <td>
                                    <input type="text" id="sim_target_language" name="sim_target_language" value="<?php echo isset($_POST['sim_target_language']) ? esc_attr($_POST['sim_target_language']) : 'en'; ?>" class="regular-text" required />
                                    <p class="description">Language code (e.g., 'en', 'fr', 'de')</p>
                                </td>
                            </tr>
                        </table>

                        <?php submit_button('Simulate Trigger', 'secondary', 'simulate_submit'); ?>
                    </form>
                </div>
            </div>

            <div style="margin-top: 30px; background: white; padding: 20px; border: 1px solid #ccd0d4;">
                <h2>Quick Workflow Status Check</h2>

                <?php
                $workflows = get_option('polytrans_workflows', []);
                echo "<p><strong>Total Workflows:</strong> " . count($workflows) . "</p>";

                if (empty($workflows)) {
                    echo "<div class='notice notice-warning inline'><p>No workflows found. <a href='" . admin_url('admin.php?page=polytrans-postprocessing') . "'>Create your first workflow</a>.</p></div>";
                } else {
                    echo "<h3>Workflow Summary</h3>";
                    echo "<table class='wp-list-table widefat fixed striped'>";
                    echo "<thead><tr><th>Name</th><th>Enabled</th><th>Target Languages</th><th>Auto Execute</th></tr></thead>";
                    echo "<tbody>";

                    foreach ($workflows as $workflow_id => $workflow) {
                        $name = $workflow['name'] ?? 'Unnamed';
                        $enabled = isset($workflow['enabled']) && $workflow['enabled'] ? '✅' : '❌';
                        $target_languages = implode(', ', $workflow['target_languages'] ?? []);
                        $triggers = $workflow['triggers'] ?? [];
                        $auto_execute = (isset($triggers['on_translation_complete']) && $triggers['on_translation_complete']) &&
                            (!isset($triggers['manual_only']) || !$triggers['manual_only']) ? '✅' : '❌';

                        echo "<tr>";
                        echo "<td><strong>{$name}</strong> <small>({$workflow_id})</small></td>";
                        echo "<td>{$enabled}</td>";
                        echo "<td>{$target_languages}</td>";
                        echo "<td>{$auto_execute}</td>";
                        echo "</tr>";
                    }

                    echo "</tbody></table>";
                }

                // Check if workflow manager is loaded
                global $polytrans_workflow_manager;
                if ($polytrans_workflow_manager) {
                    echo "<p><strong>Workflow Manager:</strong> ✅ Loaded</p>";
                } else {
                    echo "<p><strong>Workflow Manager:</strong> ❌ Not loaded</p>";
                }

                // Check if hook is registered
                global $wp_filter;
                if (isset($wp_filter['polytrans_translation_completed'])) {
                    $callback_count = count($wp_filter['polytrans_translation_completed']->callbacks);
                    echo "<p><strong>Translation Completed Hook:</strong> ✅ Registered ({$callback_count} callbacks)</p>";
                } else {
                    echo "<p><strong>Translation Completed Hook:</strong> ❌ Not registered</p>";
                }
                ?>
            </div>

            <div style="margin-top: 20px; background: #f0f6fc; padding: 15px; border: 1px solid #c3c4c7;">
                <h3>Common Issues & Solutions</h3>
                <ul>
                    <li><strong>Workflows not triggering:</strong> Check that the workflow is enabled, has correct target language, and 'on_translation_complete' trigger is enabled.</li>
                    <li><strong>Manual only workflows:</strong> If 'manual_only' is checked, workflows will not auto-execute after translation.</li>
                    <li><strong>Missing hook:</strong> If the translation completed hook is not registered, the workflow manager may not be loaded properly.</li>
                    <li><strong>Language mismatch:</strong> Ensure the target language code matches exactly what's configured in the workflow.</li>
                    <li><strong>Translation status:</strong> Check that the translation actually completed successfully and has status 'completed'.</li>
                </ul>
            </div>
        </div>

        <style>
            .debug-forms {
                display: flex;
                gap: 20px;
            }

            .debug-forms>div {
                flex: 1;
            }

            .notice.inline {
                display: inline-block;
                margin: 5px 0;
            }
        </style>
<?php
    }
}

