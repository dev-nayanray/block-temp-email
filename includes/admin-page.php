<?php
/**
 * Admin settings page for Block Temporary Email plugin.
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add settings page to admin menu.
 */
function bte_add_admin_menu() {
    if ( is_multisite() && is_network_admin() ) {
        add_menu_page(
            __( 'Block Temp Emails', 'block-temp-email' ),
            __( 'Block Temp Emails', 'block-temp-email' ),
            'manage_network_options',
            'bte-settings',
            'bte_settings_page',
            'dashicons-shield-alt',
            80
        );
    } else {
        add_options_page(
            __( 'Block Temp Emails', 'block-temp-email' ),
            __( 'Block Temp Emails', 'block-temp-email' ),
            'manage_options',
            'bte-settings',
            'bte_settings_page'
        );
    }
}
add_action( 'admin_menu', 'bte_add_admin_menu' );

/**
 * Register plugin settings.
 */
function bte_register_settings() {
    $capability = ( is_multisite() && is_network_admin() ) ? 'manage_network_options' : 'manage_options';

    register_setting( 'bte_settings_group', 'bte_enable_registration_block', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ) );
    register_setting( 'bte_settings_group', 'bte_enable_woocommerce_block', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ) );
    register_setting( 'bte_settings_group', 'bte_enable_cf7_block', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ) );
    register_setting( 'bte_settings_group', 'bte_enable_wpforms_block', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ) );
    register_setting( 'bte_settings_group', 'bte_enable_fluentforms_block', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => true,
    ) );
    register_setting( 'bte_settings_group', 'bte_error_message', array(
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => __( 'Temporary email addresses are not allowed.', 'block-temp-email' ),
    ) );
    register_setting( 'bte_settings_group', 'bte_notify_admin', array(
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false,
    ) );
    register_setting( 'bte_settings_group', 'bte_role_bypass', array(
        'type' => 'array',
        'sanitize_callback' => 'bte_sanitize_roles',
        'default' => array(),
    ) );
}
add_action( 'admin_init', 'bte_register_settings' );

/**
 * Sanitize roles array.
 *
 * @param array $roles Roles input.
 * @return array Sanitized roles.
 */
function bte_sanitize_roles( $roles ) {
    if ( ! is_array( $roles ) ) {
        return array();
    }
    $editable_roles = get_editable_roles();
    return array_intersect( $roles, array_keys( $editable_roles ) );
}

/**
 * Check if current user role bypasses blocking.
 *
 * @return bool True if bypassed.
 */
function bte_current_user_role_bypass() {
    $bypass_roles = get_option( 'bte_role_bypass', array() );
    if ( empty( $bypass_roles ) ) {
        return false;
    }
    $user = wp_get_current_user();
    if ( empty( $user->roles ) ) {
        return false;
    }
    foreach ( $user->roles as $role ) {
        if ( in_array( $role, $bypass_roles, true ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Render the admin settings page.
 */
function bte_settings_page() {
    if ( ! current_user_can( is_multisite() && is_network_admin() ? 'manage_network_options' : 'manage_options' ) ) {
        wp_die( __( 'You do not have sufficient permissions to access this page.', 'block-temp-email' ) );
    }

    // Handle form submissions
    if ( isset( $_POST['bte_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bte_settings_nonce'] ) ), 'bte_save_settings' ) ) {
        check_admin_referer( 'bte_save_settings', 'bte_settings_nonce' );

        // Save settings
        update_option( 'bte_enable_registration_block', isset( $_POST['bte_enable_registration_block'] ) );
        update_option( 'bte_enable_woocommerce_block', isset( $_POST['bte_enable_woocommerce_block'] ) );
        update_option( 'bte_enable_cf7_block', isset( $_POST['bte_enable_cf7_block'] ) );
        update_option( 'bte_enable_wpforms_block', isset( $_POST['bte_enable_wpforms_block'] ) );
        update_option( 'bte_enable_fluentforms_block', isset( $_POST['bte_enable_fluentforms_block'] ) );
        update_option( 'bte_error_message', sanitize_text_field( wp_unslash( $_POST['bte_error_message'] ) ) );
        update_option( 'bte_notify_admin', isset( $_POST['bte_notify_admin'] ) );
        $roles = isset( $_POST['bte_role_bypass'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['bte_role_bypass'] ) ) : array();
        update_option( 'bte_role_bypass', $roles );

        // Handle manual blocklist update
        if ( isset( $_POST['bte_manual_update'] ) ) {
            bte_fetch_blocklist();
            echo '<div class="updated"><p>' . esc_html__( 'Blocklist updated successfully.', 'block-temp-email' ) . '</p></div>';
        }
    }

    // Load current settings
    $enable_registration = get_option( 'bte_enable_registration_block', true );
    $enable_woocommerce = get_option( 'bte_enable_woocommerce_block', true );
    $enable_cf7 = get_option( 'bte_enable_cf7_block', true );
    $enable_wpforms = get_option( 'bte_enable_wpforms_block', true );
    $enable_fluentforms = get_option( 'bte_enable_fluentforms_block', true );
    $error_message = get_option( 'bte_error_message', __( 'Temporary email addresses are not allowed.', 'block-temp-email' ) );
    $notify_admin = get_option( 'bte_notify_admin', false );
    $role_bypass = get_option( 'bte_role_bypass', array() );

    // Get blocked log count
    $blocked_log = bte_get_blocked_log();
    $blocked_count = count( $blocked_log );

    // Get blocked domains count
    $blocked_domains_count = count( bte_get_combined_blocklist() );

    // Get whitelist count
    $whitelist_count = count( bte_get_whitelist() );

    // Editable roles
    $editable_roles = get_editable_roles();

    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Block Temporary Email Settings', 'block-temp-email' ); ?></h1>

        <form method="post" action="">
            <?php wp_nonce_field( 'bte_save_settings', 'bte_settings_nonce' ); ?>

            <h2><?php esc_html_e( 'Enable Blocking', 'block-temp-email' ); ?></h2>
            <table class="form-table">
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block on Registration', 'block-temp-email' ); ?></th>
                    <td><input type="checkbox" name="bte_enable_registration_block" <?php checked( $enable_registration ); ?> /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block on WooCommerce Checkout', 'block-temp-email' ); ?></th>
                    <td><input type="checkbox" name="bte_enable_woocommerce_block" <?php checked( $enable_woocommerce ); ?> /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block on Contact Form 7', 'block-temp-email' ); ?></th>
                    <td><input type="checkbox" name="bte_enable_cf7_block" <?php checked( $enable_cf7 ); ?> /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block on WPForms', 'block-temp-email' ); ?></th>
                    <td><input type="checkbox" name="bte_enable_wpforms_block" <?php checked( $enable_wpforms ); ?> /></td>
                </tr>
                <tr>
                    <th scope="row"><?php esc_html_e( 'Block on Fluent Forms', 'block-temp-email' ); ?></th>
                    <td><input type="checkbox" name="bte_enable_fluentforms_block" <?php checked( $enable_fluentforms ); ?> /></td>
                </tr>
            </table>

            <h2><?php esc_html_e( 'Blocklist and Whitelist Management', 'block-temp-email' ); ?></h2>
            <p><?php esc_html_e( 'Blocked Domains Count:', 'block-temp-email' ); ?> <strong><?php echo esc_html( $blocked_domains_count ); ?></strong></p>
            <p><?php esc_html_e( 'Whitelisted Domains Count:', 'block-temp-email' ); ?> <strong><?php echo esc_html( $whitelist_count ); ?></strong></p>

            <h3><?php esc_html_e( 'Add Domain to Blocklist', 'block-temp-email' ); ?></h3>
            <input type="text" name="bte_add_blocklist_domain" value="" placeholder="<?php esc_attr_e( 'example.com', 'block-temp-email' ); ?>" />
            <input type="submit" name="bte_add_blocklist_submit" class="button button-secondary" value="<?php esc_attr_e( 'Add', 'block-temp-email' ); ?>" />

            <h3><?php esc_html_e( 'Add Domain to Whitelist', 'block-temp-email' ); ?></h3>
            <input type="text" name="bte_add_whitelist_domain" value="" placeholder="<?php esc_attr_e( 'example.com', 'block-temp-email' ); ?>" />
            <input type="submit" name="bte_add_whitelist_submit" class="button button-secondary" value="<?php esc_attr_e( 'Add', 'block-temp-email' ); ?>" />

            <h2><?php esc_html_e( 'Error Message', 'block-temp-email' ); ?></h2>
            <textarea name="bte_error_message" rows="3" cols="50"><?php echo esc_textarea( $error_message ); ?></textarea>

            <h2><?php esc_html_e( 'Notifications and Bypass', 'block-temp-email' ); ?></h2>
            <p><label><input type="checkbox" name="bte_notify_admin" <?php checked( $notify_admin ); ?> /> <?php esc_html_e( 'Send admin email notifications on blocked attempts', 'block-temp-email' ); ?></label></p>

            <h3><?php esc_html_e( 'Role-based Bypass', 'block-temp-email' ); ?></h3>
            <p><?php esc_html_e( 'Select roles that bypass blocking:', 'block-temp-email' ); ?></p>
            <?php foreach ( $editable_roles as $role_key => $role ) : ?>
                <label><input type="checkbox" name="bte_role_bypass[]" value="<?php echo esc_attr( $role_key ); ?>" <?php checked( in_array( $role_key, $role_bypass, true ) ); ?> /> <?php echo esc_html( $role['name'] ); ?></label><br />
            <?php endforeach; ?>

            <h2><?php esc_html_e( 'Blocked Attempts Analytics', 'block-temp-email' ); ?></h2>
            <p><?php esc_html_e( 'Total Blocked Attempts:', 'block-temp-email' ); ?> <strong><?php echo esc_html( $blocked_count ); ?></strong></p>

            <h2><?php esc_html_e( 'Manual Blocklist Update', 'block-temp-email' ); ?></h2>
            <p><input type="submit" name="bte_manual_update" class="button button-primary" value="<?php esc_attr_e( 'Update Blocklist Now', 'block-temp-email' ); ?>" /></p>

            <?php if ( $blocked_count > 0 ) : ?>
                <h3><?php esc_html_e( 'Recent Blocked Attempts', 'block-temp-email' ); ?></h3>
                <table class="widefat fixed" cellspacing="0">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Timestamp', 'block-temp-email' ); ?></th>
                            <th><?php esc_html_e( 'IP Address', 'block-temp-email' ); ?></th>
                            <th><?php esc_html_e( 'Email', 'block-temp-email' ); ?></th>
                            <th><?php esc_html_e( 'Source', 'block-temp-email' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $recent_logs = array_slice( array_reverse( $blocked_log ), 0, 20 );
                        foreach ( $recent_logs as $log ) :
                        ?>
                            <tr>
                                <td><?php echo esc_html( $log['timestamp'] ); ?></td>
                                <td><?php echo esc_html( $log['ip'] ); ?></td>
                                <td><?php echo esc_html( $log['email'] ); ?></td>
                                <td><?php echo esc_html( $log['source'] ); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

        </form>
    </div>
    <?php
}
