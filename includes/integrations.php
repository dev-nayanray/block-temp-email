<?php
/**
 * Integrations with popular form plugins to block disposable emails.
 *
 * @package BlockTemporaryEmail
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Validate Contact Form 7 email fields.
 *
 * @param WPCF7_Validation $result Validation result.
 * @param WPCF7_FormTag    $tag    Form tag object.
 * @return WPCF7_Validation Modified validation result.
 */
function bte_cf7_email_validation( $result, $tag ) {
    $name = $tag->name;

    if ( 'email' === $tag->basetype || 'your-email' === $name ) {
        if ( ! isset( $_POST['bte_cf7_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bte_cf7_nonce'] ) ), 'bte_cf7_action' ) ) {
            return $result;
        }

        $email = isset( $_POST[ $name ] ) ? sanitize_email( wp_unslash( $_POST[ $name ] ) ) : '';

        if ( $email && is_wp_error( bte_validate_temp_email( $email ) ) ) {
            $result->invalidate( $tag, __( 'Temporary email addresses are not allowed.', 'block-temp-email' ) );
            bte_log_blocked_attempt( $email, 'Contact Form 7' );
            bte_notify_admin( $email, 'Contact Form 7' );
        }
    }

    return $result;
}
add_filter( 'wpcf7_validate_email*', 'bte_cf7_email_validation', 20, 2 );
add_filter( 'wpcf7_validate_email', 'bte_cf7_email_validation', 20, 2 );

/**
 * Validate WPForms email fields.
 *
 * @param array $fields Form fields.
 * @param array $entry  Form entry data.
 * @param array $form_data Form data.
 */
function bte_wpforms_email_validation( $fields, $entry, $form_data ) {
    if ( ! isset( $_POST['bte_wpforms_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bte_wpforms_nonce'] ) ), 'bte_wpforms_action' ) ) {
        return;
    }

    foreach ( $fields as $field ) {
        if ( in_array( $field['type'], array( 'email', 'email-confirmation' ), true ) ) {
            $email = sanitize_email( $field['value'] );
            if ( $email && is_wp_error( bte_validate_temp_email( $email ) ) ) {
                wpforms()->process->errors[ $form_data['id'] ][ $field['id'] ] = __( 'Temporary email addresses are not allowed.', 'block-temp-email' );
                bte_log_blocked_attempt( $email, 'WPForms' );
                bte_notify_admin( $email, 'WPForms' );
            }
        }
    }
}
add_action( 'wpforms_process_validate', 'bte_wpforms_email_validation', 10, 3 );

/**
 * Validate Fluent Forms email fields.
 *
 * @param array $insert_data Form submission data.
 * @param array $form       Form data.
 * @return array Modified form data.
 */
function bte_fluentform_email_validation( $insert_data, $form ) {
    if ( ! isset( $_POST['bte_fluentform_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['bte_fluentform_nonce'] ) ), 'bte_fluentform_action' ) ) {
        return $insert_data;
    }

    foreach ( $insert_data as $key => $value ) {
        if ( strpos( $key, 'email' ) !== false ) {
            $email = sanitize_email( $value );
            if ( $email && is_wp_error( bte_validate_temp_email( $email ) ) ) {
                wp_die( esc_html__( 'Temporary email addresses are not allowed.', 'block-temp-email' ) );
                bte_log_blocked_attempt( $email, 'Fluent Forms' );
                bte_notify_admin( $email, 'Fluent Forms' );
            }
        }
    }
    return $insert_data;
}
add_filter( 'fluentform_before_insert_submission', 'bte_fluentform_email_validation', 10, 2 );
