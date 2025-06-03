<?php
/**
 * Admin email notifications for blocked email attempts.
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Send admin notification email when a disposable email is blocked.
 *
 * @param string $email  Blocked email address.
 * @param string $source Source of the block (e.g., Registration, WooCommerce, Contact Form 7).
 */
function bte_notify_admin( $email, $source ) {
    $admin_email = get_option( 'admin_email' );
    $subject     = sprintf( __( '[%s] Blocked Disposable Email Attempt', 'block-temp-email' ), get_bloginfo( 'name' ) );
    $message     = sprintf(
        __( "A disposable or temporary email address was blocked.\n\nEmail: %s\nSource: %s\nTime: %s\nIP Address: %s\n", 'block-temp-email' ),
        sanitize_email( $email ),
        sanitize_text_field( $source ),
        current_time( 'mysql' ),
        bte_get_user_ip()
    );

    wp_mail( $admin_email, $subject, $message );
}
