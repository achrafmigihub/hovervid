<?php

// Define plugin version constant if not already defined
if (!defined('HOVERVID_PLUGIN_VERSION')) {
    define('HOVERVID_PLUGIN_VERSION', '1.0.0');
}

// Include WordPress function stubs for IDE support
if (!function_exists('add_action')) {
    require_once dirname(__FILE__) . '/wp-stubs.php';
}

// Security check
defined('ABSPATH') or die('No direct access!');

class Hovervid_Plugin {

    public function __construct() {
        // Add Elementor compatibility
        add_action('elementor/init', array($this, 'register_elementor_support'));
        add_filter('elementor/frontend/print_styles', array($this, 'add_elementor_styles'));
        add_action('elementor/frontend/after_register_scripts', array($this, 'register_elementor_scripts'));
    }

    /**
     * Register Elementor support
     */
    public function register_elementor_support() {
        // Add support for Elementor widgets
        add_action('elementor/widgets/widgets_registered', array($this, 'register_elementor_widgets'));
        
        // Add custom controls
        add_action('elementor/element/after_section_end', array($this, 'add_elementor_controls'), 10, 2);
    }

    /**
     * Add Elementor styles
     */
    public function add_elementor_styles($styles) {
        $styles[] = 'hovervid-public-style';
        return $styles;
    }

    /**
     * Register Elementor scripts
     */
    public function register_elementor_scripts() {
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'hovervid-elementor',
                plugin_dir_url(__FILE__) . '../public/js/public-script.js',
                array('jquery'),
                HOVERVID_PLUGIN_VERSION,
                true
            );
        }
    }

    /**
     * Add Elementor controls
     */
    public function add_elementor_controls($element, $args) {
        // Check if Elementor is active and loaded
        if (!function_exists('did_action') || !did_action('elementor/loaded')) {
            return;
        }

        // Add controls for text widgets
        if (in_array($element->get_name(), array('text-editor', 'heading', 'button'))) {
            $element->start_controls_section(
                'section_hovervid',
                array(
                    'label' => function_exists('__') ? __('Sign Language Video', 'hovervid') : 'Sign Language Video',
                    'tab' => 'content',
                )
            );

            $element->add_control(
                'enable_sign_language',
                array(
                    'label' => function_exists('__') ? __('Enable Sign Language', 'hovervid') : 'Enable Sign Language',
                    'type' => 'switcher',
                    'default' => 'yes',
                )
            );

            $element->end_controls_section();
        }
    }
} 