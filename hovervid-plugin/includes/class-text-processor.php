<?php
/**
 * Text Processor Class
 *
 * Handles the text processing and preparation for sign language translation
 *
 * @package SLVP
 */

// Include WordPress function stubs for IDE support
if (!function_exists('add_action')) {
    require_once dirname(__FILE__) . '/wp-stubs.php';
}

// Security check
defined('ABSPATH') or die('No direct access!');

/**
 * Text Processor Class for Sign Language Video Player
 *
 * Processes text content to make it compatible with sign language translation
 */
class SLVP_Text_Processor {
    
    /**
     * Minimum text length to process
     * @var int
     */
    private $min_length = 1;
    
    /**
     * Tags to exclude from processing
     * @var array
     */
    private $excluded_tags = ['script', 'style', 'textarea', 'code', 'pre', 'iframe', 'noscript', 'meta'];
    
    /**
     * Current page URL
     * @var string
     */
    private $current_url;
    
    /**
     * Context depth for element hierarchy
     * @var int
     */
    private $context_depth = 3;
    
    /**
     * Constructor - sets up WordPress hooks
     *
     * @uses add_filter() WordPress filter hook registration
     * @uses add_action() WordPress action hook registration
     */
    public function __construct() {
        // Check domain verification status using centralized verifier
        $verifier = SLVP_Domain_Verifier::get_instance();
        
        if (!$verifier->should_plugin_work()) {
            // Domain is not verified - do not set up any text processing hooks
            error_log('HoverVid Text Processor: Domain not verified - text processing disabled');
            error_log('HoverVid Text Processor: Domain: ' . $verifier->get_current_domain() . ' - Message: ' . $verifier->get_message());
            return;
        }
        
        error_log('HoverVid Text Processor: Domain verified - text processing enabled for: ' . $verifier->get_current_domain());
        
        $this->current_url = $this->get_current_url();
        add_filter('the_content', [$this, 'process_content'], 20);
        add_filter('widget_text', [$this, 'process_content'], 20);
        add_filter('the_title', [$this, 'process_text'], 20);
        add_filter('wp_nav_menu_items', [$this, 'process_content'], 20);
        add_filter('nav_menu_item_title', [$this, 'process_text'], 20);
        add_action('wp_enqueue_scripts', [$this, 'add_page_scanner']);
    }
    
    /**
     * Get current page URL
     *
     * @return string Current URL
     */
    private function get_current_url() {
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
            $url = "https://";
        } else {
            $url = "http://";
        }
        $url .= $_SERVER['HTTP_HOST'];
        $url .= $_SERVER['REQUEST_URI'];
        return $url;
    }

    /**
     * Generate hash for content identification
     *
     * @param string $text The text to hash
     * @return string MD5 hash of the text
     */
    private function generate_content_hash($text) {
        return md5(trim($text));
    }

    /**
     * Get parent context for an element
     *
     * @param DOMElement $element The element to get context for
     * @return string Context path
     */
    private function get_element_context($element) {
        $context = [];
        $current = $element;
        $depth = 0;

        while ($current && $depth < $this->context_depth) {
            if ($current instanceof DOMElement) {
                $context_info = $current->tagName;
                
                if ($current->hasAttribute('id')) {
                    $context_info .= '#' . $current->getAttribute('id');
                }
                
                if ($current->hasAttribute('class')) {
                    $context_info .= '.' . str_replace(' ', '.', $current->getAttribute('class'));
                }
                
                $context[] = $context_info;
            }
            $current = $current->parentNode;
            $depth++;
        }

        return implode(' > ', array_reverse($context));
    }
    
    /**
     * Add page scanner scripts
     *
     * @uses wp_enqueue_script() WordPress function to register and enqueue scripts
     * @uses wp_add_inline_script() WordPress function to add inline script
     */
    public function add_page_scanner() {
        // Double-check domain verification before adding scanner scripts
        $verifier = SLVP_Domain_Verifier::get_instance();
        
        if (!$verifier->should_plugin_work()) {
            error_log('HoverVid Text Processor: Domain not verified - page scanner disabled');
            return;
        }
        
        // Add Tesseract.js library with the correct version and configuration
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'tesseract-js',
                'https://cdn.jsdelivr.net/npm/tesseract.js@4.1.1/dist/tesseract.min.js',
                array(),
                '4.1.1',
                true
            );
        }

        // Add a small script to ensure Tesseract is properly initialized
        if (function_exists('wp_add_inline_script')) {
            wp_add_inline_script(
                'tesseract-js',
                'window.Tesseract = Tesseract; console.log("Tesseract.js loaded:", typeof Tesseract !== "undefined");',
                'before'
            );

            // Add a fallback script in case Tesseract fails to load
            wp_add_inline_script(
                'tesseract-js',
                'window.addEventListener("error", function(e) { if (e.filename && e.filename.includes("tesseract")) { console.error("Tesseract.js error:", e.message); } }, true);',
                'after'
            );
        }

        // Add our text scanner script after Tesseract is loaded
        if (function_exists('wp_enqueue_script')) {
            wp_enqueue_script(
                'slvp-text-scanner',
                SLVP_PLUGIN_URL . 'public/js/text-scanner.js',
                array('tesseract-js'),  // Make text-scanner depend on tesseract-js
                '1.0.0',
                true
            );
        }

        // Add a check to ensure Tesseract is loaded before initializing
        if (function_exists('wp_add_inline_script')) {
            wp_add_inline_script(
                'slvp-text-scanner',
                'document.addEventListener("DOMContentLoaded", function() { 
                    if (typeof Tesseract === "undefined") {
                        console.error("Tesseract.js failed to load. Please check your internet connection.");
                        return;
                    }
                    console.log("Tesseract.js is ready to use");
                });',
                'after'
            );
            
            // Add a script to handle Tesseract loading errors gracefully
            wp_add_inline_script(
                'slvp-text-scanner',
                'window.addEventListener("load", function() {
                    // Check if Tesseract is loaded after a short delay
                    setTimeout(function() {
                        if (typeof Tesseract === "undefined") {
                            console.error("Tesseract.js failed to load after timeout. Disabling image processing.");
                            // Set a flag to disable image processing
                            window.slvpScanner = window.slvpScanner || {};
                            window.slvpScanner.imageProcessingEnabled = false;
                        }
                    }, 3000);
                });',
                'after'
            );
        }
    }
    
    /**
     * Process content through DOM
     *
     * @param string $content The content to process
     * @return string Processed content
     * @uses is_admin() WordPress function to check if in admin area
     */
    public function process_content($content) {
        if (empty($content) || (function_exists('is_admin') && is_admin())) {
            return $content;
        }
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), 
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $this->process_dom_element($dom->documentElement);
        
        return $dom->saveHTML();
    }
    
    /**
     * Process a DOM element recursively
     *
     * @param DOMElement $element The element to process
     */
    private function process_dom_element($element) {
        if (!$element || !($element instanceof DOMElement)) return;
        
        if (in_array(strtolower($element->nodeName), $this->excluded_tags)) {
            return;
        }
        
        if ($element->hasAttribute('id') && $element->getAttribute('id') === 'slvp-player-container') {
            return;
        }
        
        $children = iterator_to_array($element->childNodes);
        foreach ($children as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                $text = trim($node->nodeValue);
                if (strlen($text) >= $this->min_length && $text !== '.') {
                    $isInVideoPlayer = false;
                    $parent = $node->parentNode;
                    while ($parent && $parent instanceof DOMElement) {
                        if ($parent->hasAttribute('id') && $parent->getAttribute('id') === 'slvp-player-container') {
                            $isInVideoPlayer = true;
                            break;
                        }
                        $parent = $parent->parentNode;
                    }
                    
                    if (!$isInVideoPlayer) {
                        $this->wrap_text_node($node);
                    }
                }
            } else if ($node instanceof DOMElement) {
                $this->process_dom_element($node);
            }
        }
    }
    
    /**
     * Determine the type of element
     *
     * @param DOMElement $element The element to check
     * @return string Element type (heading, button, link, paragraph, or text)
     */
    private function get_element_type($element) {
        if (!($element instanceof DOMElement)) {
            return 'text';
        }
        
        if (preg_match('/^h[1-6]$/i', $element->nodeName)) {
            return 'heading';
        }
        
        if ($element->nodeName === 'button' || 
            ($element->hasAttribute('role') && $element->getAttribute('role') === 'button') ||
            ($element->nodeName === 'input' && 
             $element->hasAttribute('type') && 
             in_array($element->getAttribute('type'), ['submit', 'button']))) {
            return 'button';
        }
        
        if ($element->nodeName === 'a') {
            return 'link';
        }
        
        if ($element->nodeName === 'p') {
            return 'paragraph';
        }
        
        return 'text';
    }
    
    /**
     * Wrap a text node with sign language elements
     *
     * @param DOMText $node The text node to wrap
     * @uses plugins_url() WordPress function to get plugin directory URL
     */
    private function wrap_text_node($node) {
        $doc = $node->ownerDocument;
        $parent = $node->parentNode;
        
        if ($parent instanceof DOMElement && $parent->getAttribute('data-slvp-processed') === 'true') {
            return;
        }
        
        $wrapper = $doc->createElement('span');
        $wrapper->setAttribute('class', 'slvp-text-wrapper');
        $wrapper->setAttribute('data-slvp-processed', 'true');
        
        $text_content = trim($node->nodeValue);
        $content_hash = $this->generate_content_hash($text_content);
        $context = $this->get_element_context($parent);
        $fingerprint = [
            'url' => $this->current_url,
            'context' => $context,
            'content_hash' => $content_hash
        ];
        
        $wrapper->setAttribute('data-slvp-fingerprint', json_encode($fingerprint));
        $wrapper->setAttribute('data-slvp-hash', $content_hash);
        
        $type = $this->get_element_type($parent);
        if ($type === 'link') {
            $wrapper->setAttribute('data-is-link', 'true');
        } elseif ($type === 'button') {
            $wrapper->setAttribute('data-is-button', 'true');
        }
        
        $wrapper->appendChild($node->cloneNode(true));
        
        // Create icon with logo image
        $icon = $doc->createElement('span');
        $icon->setAttribute('class', 'slvp-translate-icon');
        
        $img = $doc->createElement('img');
        
        // Use plugins_url function with function_exists check
        if (function_exists('plugins_url')) {
            $img->setAttribute('src', plugins_url('assets/hovervid-icon.svg', dirname(__FILE__)));
        } else {
            // Fallback to a relative path
            $img->setAttribute('src', '../assets/hovervid-icon.svg');
        }
        
        $img->setAttribute('alt', 'Translate');
        $icon->appendChild($img);
        
        $wrapper->appendChild($icon);
        
        $parent->replaceChild($wrapper, $node);
    }
    
    //process functions
    public function process_text($text) {
        if (empty(trim($text))) {
            return $text;
        }
        
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(
            mb_convert_encoding($text, 'HTML-ENTITIES', 'UTF-8'),
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        
        $this->process_dom_element($dom->documentElement);
        
        return $dom->saveHTML();
    }
    
    public function process_menu_link_attributes($atts) {
        $atts['data-slvp-processable'] = 'true';
        return $atts;
    }
    
    public function process_menu_classes($classes, $item) {
        $classes[] = 'slvp-processable';
        return $classes;
    }
}
