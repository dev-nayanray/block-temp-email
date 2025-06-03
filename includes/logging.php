<?php
/**
 * Logging system for blocked email attempts.
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Log a blocked email attempt.
 *
 * @param string $email Email address blocked.
 * @param string $source Source of the block (e.g., Registration, WooCommerce, Contact Form 7).
 */
function bte_log_blocked_attempt( $email, $source ) {
    $log = get_option( 'bte_blocked_log', array() );

    $log[] = array(
        'timestamp' => current_time( 'mysql' ),
        'ip'        => bte_get_user_ip(),
        'email'     => sanitize_email( $email ),
        'source'    => sanitize_text_field( $source ),
    );

    // Keep log size reasonable (e.g., last 1000 entries)
    if ( count( $log ) > 1000 ) {
        $log = array_slice( $log, -1000 );
    }

    update_option( 'bte_blocked_log', $log );
}

/**
 * Get the user's IP address.
 *
 * @return string IP address.
 */
function bte_get_user_ip() {
    if ( isset( $_SERVER['HTTP_CLIENT_IP'] ) && ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CLIENT_IP'] ) );
    } elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) && ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) );
    } elseif ( isset( $_SERVER['REMOTE_ADDR'] ) && ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) );
    } else {
        $ip = '';
    }
    return $ip;
}

/**
 * Retrieve blocked attempts log.
 *
 * @return array Log entries.
 */
function bte_get_blocked_log() {
    return get_option( 'bte_blocked_log', array() );
}
