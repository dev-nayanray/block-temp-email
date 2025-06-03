<?php
/**
 * Core plugin functions for Block Temporary Email.
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Get the local blocklist of disposable email domains.
 *
 * @return array List of blocked domains.
 */
function bte_get_local_blocklist() {
    $file = BTE_PLUGIN_DIR . 'includes/blocklist.txt';
    if ( ! file_exists( $file ) ) {
        return array();
    }
    $contents = file_get_contents( $file );
    $domains = array_filter( array_map( 'trim', explode( "\n", $contents ) ) );
    return $domains;
}

/**
 * Get the combined blocklist (local + remote + admin added).
 *
 * @return array List of blocked domains.
 */
function bte_get_combined_blocklist() {
    $local = bte_get_local_blocklist();
    $remote = bte_get_remote_blocklist();

    if ( is_multisite() ) {
        $admin_added = get_site_option( 'bte_admin_blocklist', array() );
    } else {
        $admin_added = get_option( 'bte_admin_blocklist', array() );
    }

    $combined = array_unique( array_merge( $local, $remote, $admin_added ) );

    return array_map( 'strtolower', $combined );
}

/**
 * Get the whitelist domains added by admin.
 *
 * @return array List of whitelisted domains.
 */
function bte_get_whitelist() {
    if ( is_multisite() ) {
        $whitelist = get_site_option( 'bte_whitelist', array() );
    } else {
        $whitelist = get_option( 'bte_whitelist', array() );
    }
    return array_map( 'strtolower', $whitelist );
}

/**
 * Check if an email domain is blocked.
 *
 * @param string $email Email address to check.
 * @return bool True if blocked, false otherwise.
 */
function bte_is_email_blocked( $email ) {
    $domain = strtolower( substr( strrchr( $email, '@' ), 1 ) );

    // Whitelist check
    if ( in_array( $domain, bte_get_whitelist(), true ) ) {
        return false;
    }

    // Blocklist check
    return in_array( $domain, bte_get_combined_blocklist(), true );
}

/**
 * Validate email and return WP_Error if blocked.
 *
 * @param string $email Email address to validate.
 * @return string|WP_Error Email if valid, WP_Error if blocked.
 */
function bte_validate_temp_email( $email ) {
    if ( bte_is_email_blocked( $email ) ) {
        $message = get_option( 'bte_error_message', __( 'Temporary email addresses are not allowed.', 'block-temp-email' ) );
        return new WP_Error( 'invalid_email', $message );
    }
    return $email;
}

/**
 * Validate email on user registration.
 *
 * @param WP_Error $errors Registration errors.
 * @param string   $sanitized_user_login User login.
 * @param string   $user_email User email.
 * @return WP_Error Modified errors object.
 */
function bte_registration_email_validation( $errors, $sanitized_user_login, $user_email ) {
    if ( ! get_option( 'bte_enable_registration_block', true ) ) {
        return $errors;
    }

    $error = bte_validate_temp_email( $user_email );
    if ( is_wp_error( $error ) ) {
        $errors->add( 'invalid_email', $error->get_error_message() );
        bte_log_blocked_attempt( $user_email, 'Registration' );
        if ( get_option( 'bte_notify_admin', false ) ) {
            bte_notify_admin( $user_email, 'Registration' );
        }
    }
    return $errors;
}
add_filter( 'registration_errors', 'bte_registration_email_validation', 10, 3 );

/**
 * Validate email on WooCommerce checkout.
 */
function bte_woocommerce_check_email() {
    if ( ! get_option( 'bte_enable_woocommerce_block', true ) ) {
        return;
    }

    if ( ! isset( $_POST['billing_email'] ) ) {
        return;
    }

    if ( ! isset( $_POST['bte_woocommerce_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bte_woocommerce_nonce'] ) ), 'bte_woocommerce_action' ) ) {
        return;
    }

    $email = sanitize_email( wp_unslash( $_POST['billing_email'] ) );
    $error = bte_validate_temp_email( $email );
    if ( is_wp_error( $error ) ) {
        wc_add_notice( $error->get_error_message(), 'error' );
        bte_log_blocked_attempt( $email, 'WooCommerce' );
        if ( get_option( 'bte_notify_admin', false ) ) {
            bte_notify_admin( $email, 'WooCommerce' );
        }
    }
}
add_action( 'woocommerce_checkout_process', 'bte_woocommerce_check_email' );

/**
 * Add domain to admin blocklist.
 *
 * @param string $domain Domain to add.
 */
function bte_add_admin_blocklist_domain( $domain ) {
    $domain = strtolower( trim( $domain ) );
    if ( empty( $domain ) ) {
        return;
    }

    if ( is_multisite() ) {
        $blocklist = get_site_option( 'bte_admin_blocklist', array() );
    } else {
        $blocklist = get_option( 'bte_admin_blocklist', array() );
    }

    if ( ! in_array( $domain, $blocklist, true ) ) {
        $blocklist[] = $domain;
        if ( is_multisite() ) {
            update_site_option( 'bte_admin_blocklist', $blocklist );
        } else {
            update_option( 'bte_admin_blocklist', $blocklist );
        }
    }
}

/**
 * Remove domain from admin blocklist.
 *
 * @param string $domain Domain to remove.
 */
function bte_remove_admin_blocklist_domain( $domain ) {
    $domain = strtolower( trim( $domain ) );
    if ( empty( $domain ) ) {
        return;
    }

    if ( is_multisite() ) {
        $blocklist = get_site_option( 'bte_admin_blocklist', array() );
    } else {
        $blocklist = get_option( 'bte_admin_blocklist', array() );
    }

    $key = array_search( $domain, $blocklist, true );
    if ( false !== $key ) {
        unset( $blocklist[ $key ] );
        if ( is_multisite() ) {
            update_site_option( 'bte_admin_blocklist', $blocklist );
        } else {
            update_option( 'bte_admin_blocklist', $blocklist );
        }
    }
}

/**
 * Add domain to whitelist.
 *
 * @param string $domain Domain to add.
 */
function bte_add_whitelist_domain( $domain ) {
    $domain = strtolower( trim( $domain ) );
    if ( empty( $domain ) ) {
        return;
    }

    if ( is_multisite() ) {
        $whitelist = get_site_option( 'bte_whitelist', array() );
    } else {
        $whitelist = get_option( 'bte_whitelist', array() );
    }

    if ( ! in_array( $domain, $whitelist, true ) ) {
        $whitelist[] = $domain;
        if ( is_multisite() ) {
            update_site_option( 'bte_whitelist', $whitelist );
        } else {
            update_option( 'bte_whitelist', $whitelist );
        }
    }
}

/**
 * Remove domain from whitelist.
 *
 * @param string $domain Domain to remove.
 */
function bte_remove_whitelist_domain( $domain ) {
    $domain = strtolower( trim( $domain ) );
    if ( empty( $domain ) ) {
        return;
    }

    if ( is_multisite() ) {
        $whitelist = get_site_option( 'bte_whitelist', array() );
    } else {
        $whitelist = get_option( 'bte_whitelist', array() );
    }

    $key = array_search( $domain, $whitelist, true );
    if ( false !== $key ) {
        unset( $whitelist[ $key ] );
        if ( is_multisite() ) {
            update_site_option( 'bte_whitelist', $whitelist );
        } else {
            update_option( 'bte_whitelist', $whitelist );
        }
    }
}

/**
 * Get the error message to display when blocking an email.
 *
 * @return string Error message.
 */
function bte_get_error_message() {
    $default = __( 'Temporary email addresses are not allowed.', 'block-temp-email' );
    return get_option( 'bte_error_message', $default );
}

/**
 * Update the blocklist from remote source (GitHub JSON or text file).
 * This function can be hooked to WP-Cron for weekly updates.
 */
function bte_fetch_blocklist() {
    // Check if auto-update is enabled
    $auto_update = get_option( 'bte_enable_auto_update', true );
    if ( ! $auto_update ) {
        return;
    }

    $url = 'https://raw.githubusercontent.com/disposable-email-domains/disposable-email-domains/master/disposable_email_blocklist.conf';

    $response = wp_remote_get( $url );
    if ( is_wp_error( $response ) ) {
        return;
    }

    $body = wp_remote_retrieve_body( $response );
    if ( empty( $body ) ) {
        return;
    }

    // Parse domains from body
    $domains = array_filter( array_map( 'trim', explode( "\n", $body ) ) );

    // Save to site option or option depending on multisite
    if ( is_multisite() ) {
        update_site_option( 'bte_remote_blocklist', $domains );
    } else {
        update_option( 'bte_remote_blocklist', $domains );
    }
}

/**
 * Clean blocked logs older than specified days.
 */
function bte_clean_old_logs() {
    $days = intval( get_option( 'bte_log_retention_days', 30 ) );
    if ( $days <= 0 ) {
        return;
    }

    $logs = get_option( 'bte_blocked_log', array() );
    $threshold = strtotime( "-{$days} days" );

    $logs = array_filter( $logs, function( $log ) use ( $threshold ) {
        return strtotime( $log['timestamp'] ) >= $threshold;
    } );

    update_option( 'bte_blocked_log', $logs );
}

/**
 * Import plugin settings from JSON string.
 *
 * @param string $json JSON string of settings.
 * @return bool True on success, false on failure.
 */
function bte_import_settings( $json ) {
    $data = json_decode( $json, true );
    if ( ! is_array( $data ) ) {
        return false;
    }

    $allowed_keys = array(
        'bte_enable_registration_block',
        'bte_enable_woocommerce_block',
        'bte_enable_cf7_block',
        'bte_enable_wpforms_block',
        'bte_enable_fluentforms_block',
        'bte_error_message',
        'bte_notify_admin',
        'bte_role_bypass',
        'bte_admin_blocklist',
        'bte_whitelist',
        'bte_enable_auto_update',
        'bte_log_retention_days',
    );

    foreach ( $allowed_keys as $key ) {
        if ( isset( $data[ $key ] ) ) {
            update_option( $key, $data[ $key ] );
        }
    }

    return true;
}

/**
 * Export plugin settings as JSON string.
 *
 * @return string JSON string of settings.
 */
function bte_export_settings() {
    $keys = array(
        'bte_enable_registration_block',
        'bte_enable_woocommerce_block',
        'bte_enable_cf7_block',
        'bte_enable_wpforms_block',
        'bte_enable_fluentforms_block',
        'bte_error_message',
        'bte_notify_admin',
        'bte_role_bypass',
        'bte_admin_blocklist',
        'bte_whitelist',
        'bte_enable_auto_update',
        'bte_log_retention_days',
    );

    $settings = array();
    foreach ( $keys as $key ) {
        $settings[ $key ] = get_option( $key );
    }

    return wp_json_encode( $settings );
}

/**
 * Reset plugin settings to defaults.
 */
function bte_reset_settings() {
    update_option( 'bte_enable_registration_block', true );
    update_option( 'bte_enable_woocommerce_block', true );
    update_option( 'bte_enable_cf7_block', true );
    update_option( 'bte_enable_wpforms_block', true );
    update_option( 'bte_enable_fluentforms_block', true );
    update_option( 'bte_error_message', __( 'Temporary email addresses are not allowed.', 'block-temp-email' ) );
    update_option( 'bte_notify_admin', false );
    update_option( 'bte_role_bypass', array() );
    update_option( 'bte_admin_blocklist', array() );
    update_option( 'bte_whitelist', array() );
    update_option( 'bte_enable_auto_update', true );
    update_option( 'bte_log_retention_days', 30 );
}

/**
 * Get the remote blocklist domains.
 *
 * @return array List of remote blocklist domains.
 */
function bte_get_remote_blocklist() {
    if ( is_multisite() ) {
        return get_site_option( 'bte_remote_blocklist', array() );
    }
    return get_option( 'bte_remote_blocklist', array() );
}
