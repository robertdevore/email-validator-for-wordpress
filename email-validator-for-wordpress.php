<?php

/**
 * The plugin bootstrap file.
 *
 * @link              https://robertdevore.com
 * @since             1.0.0
 * @package           Email_Validator_WP
 *
 * @wordpress-plugin
 *
 * Plugin Name: Email Validator for WordPress
 * Description: A plugin that stops user registration if the email address has more numbers than a specified limit (configurable). It also works for WooCommerce registration if WooCommerce is active.
 * Plugin URI:  https://robertdevore.com/
 * Version:     1.0.0
 * Author:      Robert DeVore
 * Author URI:  https://robertdevore.com/
 * License:     GPL-3.0+
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain: email-validator-wp
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

/**
 * Load plugin text domain for localization.
 *
 * @return void
 */
function evwp_email_validator_load_textdomain() {
    load_plugin_textdomain( 'email-validator-wp', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'plugins_loaded', 'evwp_email_validator_load_textdomain' );


/**
 * Validate email to block registration if it contains too many numbers (as defined by the user).
 * This function is used for the default WordPress registration process.
 *
 * @param string   $username The username entered during registration.
 * @param string   $email    The email address entered during registration.
 * @param WP_Error $errors   An object to store validation errors.
 * 
 * @return void
 */
function evwp_validate_email_numbers( $username, $email, $errors ) {
    // Get the number threshold from the settings, default is 4.
    $number_limit = get_option( 'evwp_email_number_limit', 4 );

    // Count the number of digits in the email.
    $number_count = preg_match_all( '/\d/', $email );

    // Check if the email contains more numbers than the limit.
    if ( $number_count >= $number_limit ) {
        // Add a validation error to prevent registration.
        $errors->add(
            'email_too_many_numbers',
            sprintf(
                esc_html__( 'Registration failed: The email address cannot contain %d or more numbers.', 'email-validator-wp' ),
                $number_limit
            )
        );

        // Log the failed registration.
        evwp_log_failed_attempt( $username, $email );
    }
}
add_action( 'register_post', 'evwp_validate_email_numbers', 10, 3 );

/**
 * Check if WooCommerce is active and add WooCommerce-specific registration hook.
 *
 * @return void
 */
function evwp_check_for_woocommerce() {
    if ( class_exists( 'WooCommerce' ) ) {
        // Hook into WooCommerce's registration process if WooCommerce is active.
        add_action( 'woocommerce_register_post', 'evwp_validate_woocommerce_email_numbers', 10, 3 );
    }
}
add_action( 'plugins_loaded', 'evwp_check_for_woocommerce' );

/**
 * Validate WooCommerce email to block registration if it contains too many numbers.
 *
 * @param string   $username The username entered during registration.
 * @param string   $email    The email address entered during registration.
 * @param WP_Error $errors   An object to store validation errors.
 * 
 * @return void
 */
function evwp_validate_woocommerce_email_numbers( $username, $email, $errors ) {
    // Get the number threshold from the settings, default is 4.
    $number_limit = get_option( 'evwp_email_number_limit', 4 );

    // Count the number of digits in the email.
    $number_count = preg_match_all( '/\d/', $email );

    // Check if the email contains more numbers than the limit.
    if ( $number_count >= $number_limit ) {
        // Add a validation error to prevent registration.
        $errors->add(
            'email_too_many_numbers',
            sprintf(
                esc_html__( 'WooCommerce registration failed: The email address cannot contain %d or more numbers.', 'email-validator-wp' ),
                $number_limit
            )
        );

        // Log the failed registration.
        evwp_log_failed_attempt( $username, $email );
    }
}

/**
 * Log the failed registration attempts.
 *
 * @param string $username The username that was used.
 * @param string $email    The email that failed validation.
 * 
 * @return void
 */
function evwp_log_failed_attempt( $username, $email ) {
    // Get the existing log from the database.
    $failed_attempts = get_option( 'evwp_failed_attempts_log', [] );

    // Add the new attempt to the log with the current timestamp.
    $failed_attempts[] = [
        'username' => $username,
        'email'    => $email,
        'time'     => current_time( 'mysql' ),
    ];

    // Update the log in the database.
    update_option( 'evwp_failed_attempts_log', $failed_attempts );
}

/**
 * Add the settings page to the WordPress menu.
 *
 * @return void
 */
function evwp_email_validator_settings_page() {
    add_options_page(
        esc_html__( 'Email Validator Settings', 'email-validator-wp' ),
        esc_html__( 'Email Validator', 'email-validator-wp' ),
        'manage_options',
        'evwp-email-validator-settings',
        'evwp_email_validator_settings_render'
    );
}
add_action( 'admin_menu', 'evwp_email_validator_settings_page' );

/**
 * Initialize the plugin settings.
 *
 * @return void
 */
function evwp_email_validator_settings_init() {
    // Register a new setting for the "evwp_email_validator" page.
    register_setting( 'evwp_email_validator', 'evwp_email_number_limit' );

    // Add a new section in the settings page.
    add_settings_section(
        'evwp_email_validator_section',
        esc_html__( 'Email Validator Settings', 'email-validator-wp' ),
        null,
        'evwp_email_validator'
    );

    // Add a new field to set the number limit.
    add_settings_field(
        'evwp_email_number_limit',
        esc_html__( 'Number Limit', 'email-validator-wp' ),
        'evwp_email_number_limit_render',
        'evwp_email_validator',
        'evwp_email_validator_section'
    );
}
add_action( 'admin_init', 'evwp_email_validator_settings_init' );

/**
 * Render the number limit input field.
 *
 * @return void
 */
function evwp_email_number_limit_render() {
    // Get the stored value from the database, default to 4 if not set.
    $number_limit = get_option( 'evwp_email_number_limit', 4 );

    // Nonce field for security.
    wp_nonce_field( 'evwp_email_validator_save', 'evwp_email_validator_nonce' );
    ?>
    <input
        type="number"
        name="evwp_email_number_limit"
        value="<?php esc_attr_e( $number_limit ); ?>"
        min="1"
        max="10"
    />
    <p class="description"><?php esc_html_e( 'Set the maximum number of digits allowed in the email address for registration.', 'email-validator-wp' ); ?></p>
    <?php
}

/**
 * Render the settings page and provide options to download logs, clear logs, and filter by date.
 *
 * @return void
 */
function evwp_email_validator_settings_render() {
    // Verify current user permissions.
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'email-validator-wp' ) );
    }
    ?>
    <div class="wrap">
        <h1><?php esc_html_e( 'Email Validator Settings', 'email-validator-wp' ); ?></h1>

        <hr />

        <form action="options.php" method="post">
            <?php
            settings_fields( 'evwp_email_validator' );
            do_settings_sections( 'evwp_email_validator' );
            submit_button();
            ?>
        </form>

        <hr />

        <h2><?php esc_html_e( 'Failed Registration Attempts', 'email-validator-wp' ); ?></h2>
        <p><?php esc_html_e( 'Download or clear the log of failed registration attempts:', 'email-validator-wp' ); ?></p>

        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field( 'evwp_download_csv', 'evwp_download_nonce' ); ?>
            <input type="submit" name="evwp_download_csv" class="button button-primary" value="<?php esc_html_e( 'Download CSV', 'email-validator-wp' ); ?>">
        </form>

        <form method="post" style="display: inline-block;">
            <?php wp_nonce_field( 'evwp_clear_log', 'evwp_clear_log_nonce' ); ?>
            <input type="submit" name="evwp_clear_log" class="button button-secondary" value="<?php esc_html_e( 'Clear Log', 'email-validator-wp' ); ?>">
        </form>

        <hr />

        <h2><?php esc_html_e( 'Download by Date', 'email-validator-wp' ); ?></h2>
        <form method="post">
            <?php wp_nonce_field( 'evwp_download_csv_by_date', 'evwp_download_date_nonce' ); ?>
            <label for="start_date"><?php esc_html_e( 'Start Date:', 'email-validator-wp' ); ?></label>
            <input type="date" name="start_date" required>
            <label for="end_date"><?php esc_html_e( 'End Date:', 'email-validator-wp' ); ?></label>
            <input type="date" name="end_date" required>
            <input type="submit" name="evwp_download_csv_by_date" class="button button-primary" value="<?php esc_attr_e( 'Download CSV by Date', 'email-validator-wp' ); ?>">
        </form>
    </div>
    <?php

    // Handle CSV download for all attempts
    if ( isset( $_POST['evwp_download_csv'] ) && check_admin_referer( 'evwp_download_csv', 'evwp_download_nonce' ) ) {
        evwp_download_failed_attempts_csv();
    }

    // Handle CSV download by date
    if ( isset( $_POST['evwp_download_csv_by_date'] ) && check_admin_referer( 'evwp_download_csv_by_date', 'evwp_download_date_nonce' ) ) {
        $start_date = sanitize_text_field( $_POST['start_date'] );
        $end_date   = sanitize_text_field( $_POST['end_date'] );
        evwp_download_failed_attempts_csv_by_date( $start_date, $end_date );
    }

    // Handle log clearing
    if ( isset( $_POST['evwp_clear_log'] ) && check_admin_referer( 'evwp_clear_log', 'evwp_clear_log_nonce' ) ) {
        evwp_clear_failed_attempts_log();
    }
}

/**
 * Download the failed registration attempts as a CSV file.
 *
 * @return void
 */
function evwp_download_failed_attempts_csv() {
    // Get the log of failed registration attempts.
    $failed_attempts = get_option( 'evwp_failed_attempts_log', [] );

    // Start output buffering to prevent any prior output from corrupting the CSV.
    ob_clean(); 
    ob_start();

    // Set the headers to output a CSV file.
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="failed_attempts.csv"' );

    // Open output stream for CSV.
    $output = fopen( 'php://output', 'w' );

    // Output column headers.
    fputcsv( $output, [ 'Username', 'Email', 'Time' ] );

    // Output each log entry.
    foreach ( $failed_attempts as $attempt ) {
        fputcsv( $output, $attempt );
    }

    // Close output stream and end output buffer.
    fclose( $output );
    
    // Flush output buffer to ensure all content is sent correctly.
    ob_flush();
    
    exit;
}

/**
 * Download the failed registration attempts as a CSV file within a date range.
 *
 * @param string $start_date The start date (Y-m-d format).
 * @param string $end_date   The end date   (Y-m-d format).
 * 
 * @return void
 */
function evwp_download_failed_attempts_csv_by_date( $start_date, $end_date ) {
    // Get the log of failed registration attempts.
    $failed_attempts = get_option( 'evwp_failed_attempts_log', [] );

    // Start output buffering to prevent any prior output from corrupting the CSV.
    ob_clean(); 
    ob_start();

    // Set the headers to output a CSV file.
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="failed_attempts_by_date.csv"' );

    // Open output stream for CSV.
    $output = fopen( 'php://output', 'w' );

    // Output column headers.
    fputcsv( $output, [ 'Username', 'Email', 'Time' ] );

    // Filter and output log entries within the date range.
    foreach ( $failed_attempts as $attempt ) {
        $attempt_time = date( 'Y-m-d', strtotime( $attempt['time'] ) );
        if ( $attempt_time >= $start_date && $attempt_time <= $end_date ) {
            fputcsv( $output, $attempt );
        }
    }

    // Close output stream and end output buffer.
    fclose( $output );
    
    // Flush output buffer to ensure all content is sent correctly.
    ob_flush();
    
    exit;
}

/**
 * Clear the failed attempts log
 * 
 * @return void
 */
function evwp_clear_failed_attempts_log() {
    update_option( 'evwp_failed_attempts_log', [] );
    wp_safe_redirect( admin_url( 'options-general.php?page=evwp-email-validator-settings' ) );
    exit;
}
