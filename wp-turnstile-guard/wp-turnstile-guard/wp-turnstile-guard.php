<?php
/**
 * Plugin Name: WP Turnstile Guard
 * Description: Add Cloudflare Turnstile to WordPress login, registration, and comments. Lightweight settings, minimal footprint.
 * Version: 1.0.0
 * Author: Muhammad Ahmed
 * License: GPL-2.0-or-later
 * Text Domain: wp-turnstile-guard
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class WPTurnstileGuard {
    const OPTION_KEY = 'wtg_options';
    const VERIFY_ENDPOINT = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    public function __construct() {
        add_action('admin_init',        [$this, 'register_settings']);
        add_action('admin_menu',        [$this, 'add_settings_page']);

        // Enqueue JS only when needed
        add_action('login_enqueue_scripts',      [$this, 'enqueue_script']);
        add_action('wp_enqueue_scripts',         [$this, 'maybe_enqueue_on_frontend']);

        // Render widgets
        add_action('login_form',                 [$this, 'render_login_widget']);
        add_action('register_form',              [$this, 'render_register_widget']);
        add_action('comment_form_after_fields',  [$this, 'render_comment_widget']);
        add_action('comment_form_logged_in_after',  [$this, 'render_comment_widget']);

        // Validate tokens
        add_filter('authenticate',               [$this, 'validate_login'], 30, 3);
        add_filter('registration_errors',        [$this, 'validate_register'], 30, 3);
        add_filter('preprocess_comment',         [$this, 'validate_comment']);
    }

    public function options() {
        $defaults = [
            'site_key'        => '',
            'secret_key'      => '',
            'enable_login'    => 1,
            'enable_register' => 1,
            'enable_comment'  => 1,
        ];
        $opts = get_option(self::OPTION_KEY, []);
        return wp_parse_args($opts, $defaults);
    }

    public function register_settings() {
        register_setting(self::OPTION_KEY, self::OPTION_KEY, function($input){
            $out = [];
            $out['site_key']        = sanitize_text_field($input['site_key'] ?? '');
            $out['secret_key']      = sanitize_text_field($input['secret_key'] ?? '');
            $out['enable_login']    = !empty($input['enable_login']) ? 1 : 0;
            $out['enable_register'] = !empty($input['enable_register']) ? 1 : 0;
            $out['enable_comment']  = !empty($input['enable_comment']) ? 1 : 0;
            return $out;
        });

        add_settings_section('wtg_main', __('Cloudflare Turnstile Settings','wp-turnstile-guard'), function(){
            echo '<p>'.esc_html__('Enter your Turnstile site/secret keys and choose where to enable.', 'wp-turnstile-guard').'</p>';
        }, self::OPTION_KEY);

        add_settings_field('site_key', __('Site Key','wp-turnstile-guard'), function(){
            $o = $this->options();
            printf('<input type="text" name="%1$s[site_key]" value="%2$s" class="regular-text" placeholder="0x4AAAA...">', esc_attr(self::OPTION_KEY), esc_attr($o['site_key']));
        }, self::OPTION_KEY, 'wtg_main');

        add_settings_field('secret_key', __('Secret Key','wp-turnstile-guard'), function(){
            $o = $this->options();
            printf('<input type="text" name="%1$s[secret_key]" value="%2$s" class="regular-text" placeholder="0x4AAAA...">', esc_attr(self::OPTION_KEY), esc_attr($o['secret_key']));
        }, self::OPTION_KEY, 'wtg_main');

        add_settings_field('locations', __('Enable on','wp-turnstile-guard'), function(){
            $o = $this->options();
            ?>
            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_login]" <?php checked($o['enable_login']); ?>> <?php _e('Login','wp-turnstile-guard'); ?></label><br>
            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_register]" <?php checked($o['enable_register']); ?>> <?php _e('Registration','wp-turnstile-guard'); ?></label><br>
            <label><input type="checkbox" name="<?php echo esc_attr(self::OPTION_KEY); ?>[enable_comment]" <?php checked($o['enable_comment']); ?>> <?php _e('Comments','wp-turnstile-guard'); ?></label>
            <?php
        }, self::OPTION_KEY, 'wtg_main');
    }

    public function add_settings_page() {
        add_options_page(
            __('Turnstile Guard','wp-turnstile-guard'),
            __('Turnstile Guard','wp-turnstile-guard'),
            'manage_options',
            self::OPTION_KEY,
            [$this, 'render_settings_page']
        );
    }

    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php _e('WP Turnstile Guard', 'wp-turnstile-guard'); ?></h1>
            <form action="options.php" method="post">
                <?php
                    settings_fields(self::OPTION_KEY);
                    do_settings_sections(self::OPTION_KEY);
                    submit_button();
                ?>
            </form>
            <p><?php _e('Get keys from Cloudflare → Turnstile.', 'wp-turnstile-guard'); ?></p>
        </div>
        <?php
    }

    public function enqueue_script() {
        $o = $this->options();
        if ( empty($o['site_key']) ) return;
        wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
    }

    public function maybe_enqueue_on_frontend() {
        $o = $this->options();
        if ( empty($o['site_key']) ) return;

        if ( (is_user_logged_in() && !$o['enable_comment']) ) return;

        if ( $o['enable_comment'] && (is_singular() || is_home() || is_archive()) ) {
            wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        }

        if ( $o['enable_register'] && (isset($_GET['action']) && $_GET['action'] === 'register') ) {
            wp_enqueue_script('cf-turnstile', 'https://challenges.cloudflare.com/turnstile/v0/api.js', [], null, true);
        }
    }

    private function widget_html() {
        $o = $this->options();
        if ( empty($o['site_key']) ) return '';
        return '<div class="cf-turnstile" data-sitekey="'.esc_attr($o['site_key']).'"></div>';
    }

    public function render_login_widget() {
        $o = $this->options();
        if ( $o['enable_login'] ) {
            echo $this->widget_html();
        }
    }

    public function render_register_widget() {
        $o = $this->options();
        if ( $o['enable_register'] ) {
            echo $this->widget_html();
        }
    }

    public function render_comment_widget() {
        $o = $this->options();
        if ( $o['enable_comment'] ) {
            echo $this->widget_html();
        }
    }

    private function verify_token( $token ) {
        $o = $this->options();
        if ( empty($o['secret_key']) ) {
            return new WP_Error('wtg_missing_secret', __('Turnstile secret key not configured.', 'wp-turnstile-guard'));
        }
        $response = wp_remote_post(self::VERIFY_ENDPOINT, [
            'timeout' => 10,
            'body'    => [
                'secret' => $o['secret_key'],
                'response' => $token,
                'remoteip' => $_SERVER['REMOTE_ADDR'] ?? ''
            ]
        ]);
        if ( is_wp_error($response) ) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);
        if ( $code !== 200 || empty($body['success']) ) {
            return new WP_Error('wtg_failed', __('Turnstile verification failed.', 'wp-turnstile-guard'));
        }
        return true;
    }

    public function validate_login( $user, $username, $password ) {
        $o = $this->options();
        if ( ! $o['enable_login'] ) return $user;
        if ( is_wp_error($user) ) return $user;

        $token = $_POST['cf-turnstile-response'] ?? '';
        if ( empty($token) ) return new WP_Error('wtg_missing', __('Please complete the Turnstile challenge.', 'wp-turnstile-guard'));

        $ok = $this->verify_token($token);
        if ( is_wp_error($ok) ) return $ok;
        return $user;
    }

    public function validate_register( $errors, $sanitized_user_login, $user_email ) {
        $o = $this->options();
        if ( ! $o['enable_register'] ) return $errors;

        $token = $_POST['cf-turnstile-response'] ?? '';
        if ( empty($token) ) {
            $errors->add('wtg_missing', __('Please complete the Turnstile challenge.', 'wp-turnstile-guard'));
            return $errors;
        }
        $ok = $this->verify_token($token);
        if ( is_wp_error($ok) ) {
            $errors->add('wtg_failed', $ok->get_error_message());
        }
        return $errors;
    }

    public function validate_comment( $commentdata ) {
        $o = $this->options();
        if ( ! $o['enable_comment'] ) return $commentdata;

        $token = $_POST['cf-turnstile-response'] ?? '';
        if ( empty($token) ) {
            wp_die( __('Please complete the Turnstile challenge.', 'wp-turnstile-guard') );
        }
        $ok = $this->verify_token($token);
        if ( is_wp_error($ok) ) {
            wp_die( esc_html( $ok->get_error_message() ) );
        }
        return $commentdata;
    }
}

new WPTurnstileGuard();
