<?php
/*
Plugin Name: Post to Mastodon
Description: Posts weather updates from clientraw.txt to Mastodon
Version: 1.0
Author: Marcus Hazel-McGown - MM0ZIF
*/

if (!defined('ABSPATH')) {
    exit;
}

// Single initialization function
function wtm_init() {
    if (!get_option('wtm_settings')) {
        add_option('wtm_settings', array(
            'initialized' => true,
            'version' => '1.0'
        ));
    }
}
add_action('init', 'wtm_init');

function wtm_custom_intervals($schedules) {
    $schedules['twohourly'] = array(
        'interval' => 7200,
        'display' => 'Every Two Hours'
    );
    $schedules['sixhourly'] = array(
        'interval' => 21600,
        'display' => 'Every Six Hours'
    );
    return $schedules;
}
add_filter('cron_schedules', 'wtm_custom_intervals');

// Handle test post with enhanced error reporting
function wtm_handle_test_post() {
    if (!isset($_POST['wtm_test_post']) || !check_admin_referer('wtm_test_post')) {
        wp_redirect(admin_url('options-general.php?page=wtm'));
        exit;
    }
    
    $data = wtm_read_clientraw();
    $debug_message = "Raw Weather Data:\n";
    $debug_message .= print_r($data, true);
    
    if (!$data) {
        set_transient('wtm_admin_notice', array(
            'message' => 'Failed to read weather data. Debug info: ' . $debug_message,
            'type' => 'error'
        ), 45);
        wp_redirect(admin_url('options-general.php?page=wtm'));
        exit;
    }
    
    $result = wtm_post_to_mastodon($data);
    
    $message = $result['success'] ? 'Test post successful!' : 'Test post failed: ' . $result['error'];
    $debug_message .= "\n\nMastodon API Response:\n";
    $debug_message .= print_r($result, true);
    
    set_transient('wtm_admin_notice', array(
        'message' => $message . '<br><pre>' . $debug_message . '</pre>',
        'type' => $result['success'] ? 'success' : 'error'
    ), 45);
    
    wp_redirect(admin_url('options-general.php?page=wtm'));
    exit;
}
add_action('admin_post_wtm_test_post', 'wtm_handle_test_post');

// Display admin notices
function wtm_admin_notices() {
    $notice = get_transient('wtm_admin_notice');
    if ($notice) {
        echo '<div class="notice notice-' . $notice['type'] . ' is-dismissible"><p>' . $notice['message'] . '</p></div>';
        delete_transient('wtm_admin_notice');
    }
}
add_action('admin_notices', 'wtm_admin_notices');

// Register settings
function wtm_register_settings() {
    register_setting('wtm_options_group', 'wtm_clientraw_path');
    register_setting('wtm_options_group', 'wtm_mastodon_instance');
    register_setting('wtm_options_group', 'wtm_client_key');
    register_setting('wtm_options_group', 'wtm_client_secret');
    register_setting('wtm_options_group', 'wtm_mastodon_token');
    register_setting('wtm_options_group', 'wtm_post_title');
    register_setting('wtm_options_group', 'wtm_location');
    register_setting('wtm_options_group', 'wtm_url');
    register_setting('wtm_options_group', 'wtm_post_interval');
}
add_action('admin_init', 'wtm_register_settings');

// Create options page
function wtm_options_page() {
    ?>
    <div class="wrap">
        <h1>Weather to Mastodon Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('wtm_options_group'); ?>
            <?php do_settings_sections('wtm_options_group'); ?>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Clientraw.txt URL</th>
                    <td><input type="text" name="wtm_clientraw_path" size="60" value="<?php echo esc_attr(get_option('wtm_clientraw_path')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Mastodon Instance URL</th>
                    <td><input type="text" name="wtm_mastodon_instance" value="<?php echo esc_attr(get_option('wtm_mastodon_instance')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Client Key</th>
                    <td><input type="text" name="wtm_client_key" value="<?php echo esc_attr(get_option('wtm_client_key')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Client Secret</th>
                    <td><input type="text" name="wtm_client_secret" value="<?php echo esc_attr(get_option('wtm_client_secret')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Access Token</th>
                    <td><input type="text" name="wtm_mastodon_token" value="<?php echo esc_attr(get_option('wtm_mastodon_token')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Post Title</th>
                    <td><input type="text" name="wtm_post_title" value="<?php echo esc_attr(get_option('wtm_post_title')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Location</th>
                    <td><input type="text" name="wtm_location" value="<?php echo esc_attr(get_option('wtm_location')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">URL</th>
                    <td><input type="text" name="wtm_url" value="<?php echo esc_attr(get_option('wtm_url')); ?>" /></td>
                </tr>
                <tr valign="top">
                    <th scope="row">Posting Interval</th>
                    <td>
                        <select name="wtm_post_interval">
                            <option value="hourly" <?php selected(get_option('wtm_post_interval'), 'hourly'); ?>>Hourly</option>
                            <option value="twohourly" <?php selected(get_option('wtm_post_interval'), 'twohourly'); ?>>Every 2 Hours</option>
                            <option value="sixhourly" <?php selected(get_option('wtm_post_interval'), 'sixhourly'); ?>>Every 6 Hours</option>
                        </select>
                    </td>
                </tr>
            </table>
            <?php submit_button(); ?>
        </form>
        
        <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
            <input type="hidden" name="action" value="wtm_test_post" />
            <?php wp_nonce_field('wtm_test_post'); ?>
            <?php submit_button('Test Post', 'secondary', 'wtm_test_post', false); ?>
        </form>
    </div>
    <?php
}

add_action('admin_menu', function() {
    add_options_page('Weather to Mastodon', 'Weather to Mastodon', 'manage_options', 'wtm', 'wtm_options_page');
});

function wtm_read_clientraw() {
    $file_path = get_option('wtm_clientraw_path');
    
    if (empty($file_path)) {
        return false;
    }
    
    $args = array(
        'timeout' => 30,
        'sslverify' => false,
        'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
    );
    
    $response = wp_remote_get($file_path, $args);
    
    if (is_wp_error($response)) {
        return false;
    }
    
    $content = wp_remote_retrieve_body($response);
    if (empty($content)) {
        return false;
    }
    
    $data = explode(' ', trim($content));
    
    return array(
        'temperature' => isset($data[4]) ? round($data[4], 1) : 0,
        'humidity' => isset($data[5]) ? round($data[5]) : 0,
        'wind_speed' => isset($data[1]) ? round($data[1]) : 0,
        'wind_direction' => isset($data[3]) ? wtm_get_wind_direction($data[3]) : 'N/A'
    );
}

function wtm_get_wind_direction($degrees) {
    $directions = array('N', 'NNE', 'NE', 'ENE', 'E', 'ESE', 'SE', 'SSE', 'S', 'SSW', 'SW', 'WSW', 'W', 'WNW', 'NW', 'NNW');
    $index = round($degrees / 22.5) % 16;
    return $directions[$index];
}

function wtm_format_weather_output($data) {
    $title = get_option('wtm_post_title');
    $location = get_option('wtm_location');
    $url = get_option('wtm_url');
    
    $status = "$title\n";
    $status .= "Temperature: {$data['temperature']}°C\n";
    $status .= "Humidity: {$data['humidity']}%\n";
    $status .= "Wind: {$data['wind_speed']} km/h {$data['wind_direction']}\n";
    $status .= "Location: $location\n";
    
    if (!empty($url)) {
        $status .= "$url\n";
    }
    
    $status .= "#weather";
    
    return $status;
}

function wtm_post_to_mastodon($data) {
    if (!$data) {
        return ['success' => false, 'error' => 'No weather data available'];
    }

    $instance = get_option('wtm_mastodon_instance');
    $token = get_option('wtm_mastodon_token');

    if (empty($instance)) {
        return ['success' => false, 'error' => 'Mastodon instance URL not configured'];
    }

    if (empty($token)) {
        return ['success' => false, 'error' => 'Mastodon access token not configured'];
    }

    // Ensure instance URL has https:// and no trailing slash
    $instance = rtrim(preg_replace('#^(https?:)?//#', 'https://', $instance), '/');
    $status = wtm_format_weather_output($data);

    $response = wp_remote_post("$instance/api/v1/statuses", [
        'headers' => [
            'Authorization' => "Bearer $token",
            'Content-Type' => 'application/json'
        ],
        'body' => json_encode([
            'status' => $status
        ]),
        'timeout' => 30,
        'sslverify' => true
    ]);

    if (is_wp_error($response)) {
        return ['success' => false, 'error' => 'API Error: ' . $response->get_error_message()];
    }

    $response_code = wp_remote_retrieve_response_code($response);
    $response_body = json_decode(wp_remote_retrieve_body($response), true);

    if ($response_code !== 200) {
        $error_message = isset($response_body['error']) ? $response_body['error'] : 'Unknown error';
        return ['success' => false, 'error' => "HTTP $response_code: $error_message"];
    }

    wtm_save_post_history($status, true);
    return ['success' => true, 'response' => $response_body];
}

function wtm_save_post_history($status, $response) {
    $history = get_option('wtm_post_history', []);
    $history[] = array(
        'time' => current_time('mysql'),
        'status' => $status,
        'response' => $response
    );
    
    if (count($history) > 50) {
        $history = array_slice($history, -50);
    }
    
    update_option('wtm_post_history', $history);
}

register_activation_hook(__FILE__, 'wtm_activation');
register_deactivation_hook(__FILE__, 'wtm_deactivation');
add_action('wtm_cron_hook', 'wtm_cron_exec');

function wtm_activation() {
    wp_clear_scheduled_hook('wtm_cron_hook');
    $interval = get_option('wtm_post_interval', 'hourly');
    wp_schedule_event(time(), $interval, 'wtm_cron_hook');
}

function wtm_deactivation() {
    wp_clear_scheduled_hook('wtm_cron_hook');
}

function wtm_cron_exec() {
    $data = wtm_read_clientraw();
    if ($data) {
        $result = wtm_post_to_mastodon($data);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Weather to Mastodon cron execution: ' . ($result['success'] ? 'Success' : 'Failed - ' . $result['error']));
        }
    }
}
