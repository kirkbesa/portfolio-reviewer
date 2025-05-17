<?php
/**
 * Plugin Name: Claude Portfolio Reviewer
 * Description: Integrates Claude API for portfolio image analysis and feedback
 * Version: 1.0
 * Author: Besa & Jacinto
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Add menu page
add_action('admin_menu', 'claude_analysis_menu');
function claude_analysis_menu() {
    add_menu_page(
        'Claude Document Analysis Settings',
        'Claude Analysis',
        'manage_options',
        'claude-analysis',
        'claude_analysis_settings_page',
        'dashicons-media-document',
        30
    );
}

// Settings page with direct form handling
function claude_analysis_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Handle form submission
    if (isset($_POST['submit_claude_settings'])) {
        if (check_admin_referer('claude_settings_nonce')) {
            $api_key = sanitize_text_field($_POST['claude_api_key']);
            update_option('claude_api_key', $api_key);
            echo '<div class="notice notice-success"><p>Settings saved.</p></div>';
        }
    }

    // Handle test API key
    if (isset($_POST['test_api_key'])) {
        if (check_admin_referer('claude_settings_nonce')) {
            $test_result = test_claude_api_key();
            if (is_wp_error($test_result)) {
                echo '<div class="notice notice-error"><p>API Test Failed: ' . esc_html($test_result->get_error_message()) . '</p></div>';
            } else {
                echo '<div class="notice notice-success"><p>API Test Successful! Connection working properly.</p></div>';
            }
        }
    }

    $api_key = get_option('claude_api_key', '');
    ?>
    <div class="wrap">
        <h1>Claude Document Analysis Settings</h1>
        <form method="post" action="">
            <?php wp_nonce_field('claude_settings_nonce'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row">
                        <label for="claude_api_key">Claude API Key</label>
                    </th>
                    <td>
                        <input type="password"
                               name="claude_api_key"
                               id="claude_api_key"
                               value="<?php echo esc_attr($api_key); ?>"
                               class="regular-text"
                        />
                        <p class="description">
                            Enter your Claude API key. Keep this secure and never share it.
                        </p>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <?php submit_button('Save Settings', 'primary', 'submit_claude_settings', false); ?>
                <?php submit_button('Test API Key', 'secondary', 'test_api_key', false); ?>
            </p>
        </form>
        <div class="shortcode-info" style="margin-top: 20px; padding: 15px; background: #fff; border-left: 4px solid #646970;">
            <h3>How to Use</h3>
            <p>Use these shortcodes on your pages:</p>
            <ul>
                <li><code>[claude_document_upload]</code> - Adds the file upload form</li>
            </ul>
        </div>
    </div>
    <?php
}

// Function to test API key
function test_claude_api_key() {
    $api_key = get_option('claude_api_key');
    
    if (empty($api_key)) {
        return new WP_Error('no_api_key', 'API key is not configured.');
    }

    $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ],
        'body' => json_encode([
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => 'Test message'
                ]
            ],
            'max_tokens' => 10
        ])
    ]);

    if (is_wp_error($response)) {
        return new WP_Error('api_error', $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        return new WP_Error('api_error', $body['error']['message']);
    }

    return true;
}

// Shortcode for the upload form with spinner UI
add_shortcode('claude_document_upload', 'claude_document_upload_shortcode');
function claude_document_upload_shortcode() {
    // Check if API key is configured
    $api_key = get_option('claude_api_key');
    if (empty($api_key)) {
        return '<div class="error">Please configure the Claude API key in the WordPress admin settings.</div>';
    }

    ob_start();
    ?>
    <div id="claude-document-upload">
		<div id="step1">
			<div id="description-container">
				<p>Get feedback trained on industry-leading portfolios</p>
			</div>
			<div id="tips-container">
				<p>📷 Tips for best results:</p>
				<ul>
					<li>Use clear screenshots of your main portfolio sections</li>
					<li>Try tools like screenshot.guru or your OS screenshot tool</li>
				</ul>
			</div>
			<label>
				<div id="drop-area">
					<p>Drag & drop your document here, or <span id="spanBlue">click to browse</span>.</p>
					<p class="supported-formats">Supported formats: PNG, JPG, PDF (max 10MB per file)</p>
					<input type="file" id="file-input" name="document" accept="image/*,.pdf" hidden />
				</div>
			</label>
		</div>
		
		<div id="loading-steps">
			<div id="loading-description">
				<p class="loading-description-title">Analyzing using industry-leading portfolio patterns</p>
				<p class="loading-description-text">Our AI evaluates visual hierarchy, information architecture, storytelling, and design best practices.</p>
			</div>
			<div id="spinner-and-analyzing"></div>
			<div id="loading-checklist">
				<p id="step-1">- Analyzing visual design</p>
				<p id="step-2">- Evaluating content structure</p>
				<p id="step-3">- Generating recommendations</p>
			</div>
		</div>
		
		<div id="claude-analysis-result">
			<div id="result-header">
				<p>Portfolio Analysis Results</p>
			</div>
			<div class="claude-feedback">
				<div class="feedback-content">
				</div>
				<div class="action-buttons">
					<button class="copy-btn" id="copyResult">Copy Text</button>
					<button class="download-btn" id="downloadResult">Download</button>
					<button class="new-analysis-btn" id="newAnalysisBtn">Start New Analysis</button>
				</div>
			</div>
		</div>
		
    </div>

    <style>		
    </style>
    <?php
    return ob_get_clean();
}



// Handle document analysis
add_action('wp_ajax_analyze_document', 'claude_analyze_document');
add_action('wp_ajax_nopriv_analyze_document', 'claude_analyze_document');
function claude_analyze_document() {
    // Verify nonce
    if (!check_ajax_referer('claude_upload_nonce', 'claude_nonce', false)) {
        wp_send_json_error('Security check failed');
    }

    if (!isset($_FILES['document'])) {
        wp_send_json_error('No document uploaded');
    }

    $file = $_FILES['document'];
    
    // Check file size
    if ($file['size'] > 10 * 1024 * 1024) {
        wp_send_json_error('File size must be under 10MB');
    }

    $file_type = wp_check_filetype($file['name']);
    $allowed_types = array(
        'image/jpeg' => 'image/jpeg',
        'image/png' => 'image/png',
        'application/pdf' => 'application/pdf'
    );

    if (!array_key_exists($file_type['type'], $allowed_types)) {
        wp_send_json_error('Invalid file type. Please upload an image (JPEG/PNG) or PDF.');
    }

    // Get API key
    $api_key = get_option('claude_api_key');
    if (empty($api_key)) {
        wp_send_json_error('API key not configured');
    }

    // Read file
    $file_data = base64_encode(file_get_contents($file['tmp_name']));
	
// Prepare message content based on file type
    if ($file_type['type'] === 'application/pdf') {
        $message_content = [
            [
                'type' => 'document',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $file_type['type'],
                    'data' => $file_data
                ]
            ],
            [
                'type' => 'text',
                'text' => 'Please analyze this portfolio image and provide/enumerate detailed, industry-ready recommendations and feedback. Consider visual hierarchy, information architecture, storytelling, and design best practices. Format your response by encapsulating each sentence with <p> and </p> tags. Separate different ideas with <br> tags in between. Separate headings with <br>. Add line breaks before bullet points if you are going to use it. Utilize <strong> and <i> to emphasize keywords. No need to introduce your response.'
            ]
        ];
    } elseif (in_array($file_type['type'], ['image/jpeg', 'image/png'])) {
        $message_content = [
            [
                'type' => 'image',
                'source' => [
                    'type' => 'base64',
                    'media_type' => $file_type['type'],
                    'data' => $file_data
                ]
            ],
            [
                'type' => 'text',
                'text' => 'Please analyze this portfolio image and provide/enumerate detailed, industry-ready recommendations and feedback. Consider visual hierarchy, information architecture, storytelling, and design best practices. Format your response by encapsulating each sentence with <p> and </p> tags. Separate different ideas with <br> tags in between. Separate headings with <br>. Add line breaks before bullet points if you are going to use it. Utilize <strong> and <i> to emphasize keywords. No need to introduce your response.'
            ]
        ];
    }
 
    $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
        'headers' => [
            'Content-Type' => 'application/json',
            'x-api-key' => $api_key,
            'anthropic-version' => '2023-06-01'
        ],
        'body' => json_encode([
            'model' => 'claude-3-5-sonnet-20241022',
            'messages' => [
                [
                    'role' => 'user',
                    'content' => $message_content
                ]
            ],
            'max_tokens' => 1024
        ]),
        'timeout' => 60
    ]);

    if (is_wp_error($response)) {
        wp_send_json_error('Error connecting to Claude API: ' . $response->get_error_message());
    }

    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($body['error'])) {
        wp_send_json_error('API Error: ' . $body['error']['message']);
    }

    wp_send_json_success($body['content'][0]['text']);
}

// Add shortcode for displaying feedback
add_shortcode('claude_feedback_display', 'claude_feedback_display_shortcode');
function claude_feedback_display_shortcode($atts) {
    $latest_analysis = get_option('claude_latest_analysis');
    
    if (!$latest_analysis) {
        return '<div class="claude-feedback">No analysis available yet. Please upload a document first.</div>';
    }
    
    ob_start();
    ?>

	<div class="claude-feedback">
        <h3>Portfolio Analysis Feedback</h3>
        <div class="feedback-content">
            <?php echo wp_kses_post($latest_analysis); ?>
        </div>
    </div>
    <style>
        .claude-feedback {
            padding: 20px;
            background: #f9f9f9;
            border-radius: 5px;
            margin: 20px 0;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .feedback-content {
            line-height: 1.6;
        }
    </style>
    <?php
    return ob_get_clean();
}

// Store analysis results
add_action('wp_ajax_store_analysis', 'store_claude_analysis');
add_action('wp_ajax_nopriv_store_analysis', 'store_claude_analysis');
function store_claude_analysis() {
    if (!check_ajax_referer('claude_upload_nonce', 'claude_nonce', false)) {
		wp_send_json_error('Security check failed');
	}

    if (!isset($_POST['analysis'])) {
        wp_send_json_error('No analysis data received');
    }
    
    update_option('claude_latest_analysis', wp_kses_post($_POST['analysis']));
    wp_send_json_success();
}

// Add necessary scripts and styles
add_action('wp_enqueue_scripts', 'claude_analysis_scripts');
function claude_analysis_scripts() {
	
	$js_version = filemtime(plugin_dir_path(__FILE__) . 'assets/js/scripts.js');
    $css_version = filemtime(plugin_dir_path(__FILE__) . 'assets/css/styles.css');
	
    // Enqueue jQuery (WordPress includes it by default)
    wp_enqueue_script('jquery');
    
    // Enqueue your custom JavaScript file
    wp_enqueue_script(
        'claude-scripts', // Handle name
        plugins_url('assets/js/scripts.js', __FILE__), // Path to your custom JS file
        ['jquery'], // Dependencies (jQuery in this case)
        $js_version, // Version
        true // Load in the footer
    );

    wp_localize_script('claude-scripts', 'ajax_object', [
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('claude_upload_nonce')
    ]);

    // Enqueue your custom CSS file
    wp_enqueue_style(
        'claude-styles', // Handle name
        plugins_url('assets/css/styles.css', __FILE__), // Path to your custom CSS file
        [], // Dependencies (empty if none)
         $css_version // Version
    );
}