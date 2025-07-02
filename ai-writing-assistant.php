<?php
/*
Plugin Name: AI Writing Assistant
Description: Generate content using Cohere with multi-language support, save to posts/products, and Gutenberg integration.
Version: 2.0
Author: Subrata Debnath
Author URI: https://profiles.wordpress.org/subrata-deb-nath/
*/

if (!defined('ABSPATH')) exit;

class AI_Writing_Assistant {
    private $premium_enabled = true; // Simulated premium flag

    private $option_name = 'cohere_api_key';

    public function __construct() {
        add_action('admin_menu', [$this, 'admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_aiwa_generate', [$this, 'generate_content']);
        add_action('wp_ajax_aiwa_save_post', [$this, 'save_post']);
        add_action('wp_ajax_aiwa_save_product', [$this, 'save_product']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function admin_menu() {
        add_menu_page(
            'AI Writing Assistant',
            'AI Assistant',
            'manage_options',
            'ai-writing-assistant',
            [$this, 'admin_page'],
            'dashicons-analytics',
            100
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_ai-writing-assistant') return;
        wp_enqueue_script('aiwa-script', plugin_dir_url(__FILE__) . 'script.js', ['jquery'], '2.0', true);
        wp_localize_script('aiwa-script', 'aiwa_obj', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce('aiwa_nonce'),
        ]);
    }

    public function admin_page() {
        $is_premium = $this->premium_enabled;

        ?>
        <div class="wrap">
            <h1>🧠 AI Writing Assistant</h1>
            <form method="post" action="options.php">
                <?php settings_fields('aiwa_settings_group'); ?>
                <?php do_settings_sections('aiwa_settings_group'); ?>
                <label>Cohere API Key:</label><br>
                <input type="text" name="<?php echo esc_attr($this->option_name); ?>" value="<?php echo esc_attr(get_option($this->option_name)); ?>" size="60">
                <input type="submit" class="button button-primary" value="Save API Key">
            </form>

            <hr>

    <?php if (\$is_premium): ?>
        <label>Language:</label><br>
            <select id="aiwa-language">
                <option value="en">English</option>
                <option value="es">Spanish</option>
                <option value="fr">French</option>
                <option value="de">German</option>
            </select><br><br>
<?php else: ?>
        <p><strong>Multi-language support is a premium feature.</strong> <a href='#'>Upgrade</a> to unlock.</p>
<?php endif; ?>

            <label>What do you want to generate?</label><br>
            <select id="aiwa-type">
                <option value="blog_title">📝 Blog Title</option>
                <option value="blog_intro">📖 Blog Intro</option>
                <option value="product_title">📅 Product Title</option>
                <option value="product_description">🏦 Product Description</option>
                <option value="seo_keywords">🔑 SEO Keywords</option>
            </select><br><br>

            <textarea id="aiwa-prompt" rows="5" cols="80" placeholder="Describe your topic or product..."></textarea><br>
            <button id="aiwa-generate-btn" class="button button-primary">Generate Content</button>

    <?php if (\$is_premium): ?>
        <div id="aiwa-output" style="margin-top:20px;"></div>
        <div id="aiwa-actions" style="display:none; margin-top:10px;">
            <button id="aiwa-save-post" class="button">Save as Draft Post</button>
            <button id="aiwa-save-product" class="button">Save as WooCommerce Product</button>
        </div>
<?php else: ?>
        <p><strong>Saving drafts is a premium feature.</strong> <a href='#'>Upgrade</a> to unlock.</p>
<?php endif; ?>
            <div id="aiwa-actions" style="display:none; margin-top:10px;">
                <button id="aiwa-save-post" class="button">Save as Draft Post</button>
                <button id="aiwa-save-product" class="button">Save as WooCommerce Product</button>
            </div>
        </div>
        <?php
    }

    public function register_settings() {
        register_setting('aiwa_settings_group', $this->option_name);
    }

    public function generate_content() {
        check_ajax_referer('aiwa_nonce', 'nonce');

        $prompt = sanitize_text_field($_POST['prompt'] ?? '');
        $type   = sanitize_text_field($_POST['type'] ?? '');
        $lang   = sanitize_text_field($_POST['lang'] ?? 'en');
        $api_key = get_option($this->option_name);

        if (empty($api_key) || empty($prompt)) {
            wp_send_json_error('Missing API key or prompt');
        }

        $instruction = $this->build_prompt($type, $prompt, $lang);

        $response = wp_remote_post('https://api.cohere.ai/v1/generate', [
            'headers' => [
                'Content-Type'  => 'application/json',
                'Authorization' => 'Bearer ' . $api_key,
            ],
            'body' => json_encode([
                'model' => 'command',
                'prompt' => $instruction,
                'max_tokens' => 300,
                'temperature' => 0.7,
            ])
        ]);

        if (is_wp_error($response)) {
            wp_send_json_error('API request failed.');
        }

        $body = json_decode(wp_remote_retrieve_body($response), true);
        if (isset($body['generations'][0]['text'])) {
            wp_send_json_success(trim($body['generations'][0]['text']));
        } else {
            wp_send_json_error('No response from Cohere API.');
        }
    }

    private function build_prompt($type, $input, $lang) {
        $prefix = "in $lang language, ";
        switch ($type) {
            case 'blog_title':
                return "$prefix generate a catchy blog title for the topic: $input";
            case 'blog_intro':
                return "$prefix write a compelling blog introduction about: $input";
            case 'product_title':
                return "$prefix create a short, catchy WooCommerce product title for: $input";
            case 'product_description':
                return "$prefix write a product description suitable for an online store. Product: $input";
            case 'seo_keywords':
                return "$prefix suggest SEO keywords and a meta description for: $input";
            default:
                return "$prefix write something useful about: $input";
        }
    }

    public function save_post() {
        check_ajax_referer('aiwa_nonce', 'nonce');
        $title = sanitize_text_field($_POST['title'] ?? 'AI Generated Post');
        $content = sanitize_textarea_field($_POST['content'] ?? '');
        $post_id = wp_insert_post([
            'post_title' => $title,
            'post_content' => $content,
            'post_status' => 'draft',
            'post_type' => 'post',
        ]);
        wp_send_json_success('Draft post created with ID: ' . $post_id);
    }

    public function save_product() {
        check_ajax_referer('aiwa_nonce', 'nonce');
        if (!class_exists('WC_Product_Simple')) {
            wp_send_json_error('WooCommerce not active');
        }
        $title = sanitize_text_field($_POST['title'] ?? 'AI Product');
        $desc = sanitize_textarea_field($_POST['content'] ?? '');
        $product = new WC_Product_Simple();
        $product->set_name($title);
        $product->set_description($desc);
        $product->set_status('draft');
        $product->save();
        wp_send_json_success('Draft product created: ' . $product->get_id());
    }
}

new AI_Writing_Assistant();
