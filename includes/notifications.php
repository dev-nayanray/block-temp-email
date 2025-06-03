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
    /* translators: %s: Blog name */
    $subject     = sprintf( __( '[%1$s] Blocked Disposable Email Attempt', 'block-temp-email' ), get_bloginfo( 'name' ) );
    /* translators: 1: Email address, 2: Source, 3: Time, 4: IP Address */
    $message     = sprintf(
        __( "A disposable or temporary email address was blocked.\n\nEmail: %1\$s\nSource: %2\$s\nTime: %3\$s\nIP Address: %4\$s\n", 'block-temp-email' ),
        sanitize_email( $email ),
        sanitize_text_field( $source ),
        current_time( 'mysql' ),
        bte_get_user_ip()
    );

    wp_mail( $admin_email, $subject, $message );
}
