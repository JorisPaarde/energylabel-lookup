<?php
/**
 * Plugin Name: Energy Label Lookup
 * Plugin URI: https://github.com/JorisPaarde/energylabel-lookup
 * Description: A WordPress plugin to look up energy labels using the EP Online API. It supports postcode, house number, and addition inputs, and is compatible with Elementor Pro.
 * Version: 1.3.4
 * Author: JPWebCreation - Joris Paardekooper
 * Author URI: https://jpwebcreation.nl
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: energylabel-lookup
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants (prevent redeclaration)
if (!defined('ELL_PLUGIN_URL')) {
    define('ELL_PLUGIN_URL', plugin_dir_url(__FILE__));
}
if (!defined('ELL_PLUGIN_PATH')) {
    define('ELL_PLUGIN_PATH', plugin_dir_path(__FILE__));
}
if (!defined('ELL_PLUGIN_VERSION')) {
    define('ELL_PLUGIN_VERSION', '1.3.4');
}

// Initialize plugin
function ell_init() {
    // Load text domain for translations
    load_plugin_textdomain('energylabel-lookup', false, dirname(plugin_basename(__FILE__)) . '/languages');
    
    // Enqueue scripts and styles
    add_action('wp_enqueue_scripts', 'ell_enqueue_scripts');
    
    // Add shortcode
    add_shortcode('energylabel_lookup', 'ell_shortcode');
    
    // Add AJAX handlers
    add_action('wp_ajax_ell_lookup_label', 'ell_ajax_lookup_label');
    add_action('wp_ajax_nopriv_ell_lookup_label', 'ell_ajax_lookup_label');
    
    // Add AJAX handler for getting fresh nonce (cache-proof)
    add_action('wp_ajax_ell_get_nonce', 'ell_ajax_get_nonce');
    add_action('wp_ajax_nopriv_ell_get_nonce', 'ell_ajax_get_nonce');
}
add_action('init', 'ell_init');

// Enqueue scripts and styles
function ell_enqueue_scripts() {
    wp_enqueue_script('ell-script', ELL_PLUGIN_URL . 'assets/js/ell-script.js', array('jquery'), ELL_PLUGIN_VERSION, true);
    wp_enqueue_style('ell-style', ELL_PLUGIN_URL . 'assets/css/ell-style.css', array(), ELL_PLUGIN_VERSION);
    
    // Localize script for AJAX
    wp_localize_script('ell-script', 'ell_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ell_nonce'),
        'loading_text' => __('Opzoeken...', 'energylabel-lookup'),
        'error_text' => __('Er is een fout opgetreden. Probeer het opnieuw.', 'energylabel-lookup'),
        'nonce_error_text' => __('Sessie verlopen / beveiligingscontrole mislukt. Ververs de pagina.', 'energylabel-lookup')
    ));
}

// Main shortcode function
function ell_shortcode($atts) {
    $atts = shortcode_atts(array(
        'title' => __('Energielabel Opzoeken', 'energylabel-lookup'),
        'description' => __('Voer uw postcode, huisnummer en eventuele toevoeging in om uw energielabel op te zoeken.', 'energylabel-lookup')
    ), $atts);
    
    ob_start();
    ?>
    <div class="ell-container">
        <form id="ell-form" class="ell-form">
            <?php wp_nonce_field('ell_nonce', 'ell_form_nonce_field'); ?>
            
            <div class="ell-form-group">
                <label for="ell-postcode"><?php _e('Postcode', 'energylabel-lookup'); ?> *</label>
                <input type="text" id="ell-postcode" name="postcode" required 
                       pattern="[0-9]{4}\s?[A-Za-z]{2}" 
                       placeholder="1234 AB"
                       maxlength="7"
                       title="Voer een geldige postcode in (bijv. 1234 AB)">
                <small><?php _e('Formaat: 1234 AB', 'energylabel-lookup'); ?></small>
            </div>
            
            <div class="ell-form-group">
                <label for="ell-huisnummer"><?php _e('Huisnummer', 'energylabel-lookup'); ?> *</label>
                <input type="number" id="ell-huisnummer" name="huisnummer" required 
                       min="1" max="99999"
                       placeholder="123">
            </div>
            
            <div class="ell-form-group">
                <label for="ell-toevoeging"><?php _e('Toevoeging', 'energylabel-lookup'); ?></label>
                <input type="text" id="ell-toevoeging" name="toevoeging" 
                       placeholder="A, II, 2, hoog, etc."
                       maxlength="10">
                <small><?php _e('Optioneel: A, II, 2, hoog, etc.', 'energylabel-lookup'); ?></small>
            </div>
            
            <div class="ell-form-group">
                <button type="submit" id="ell-submit" class="ell-submit">
                    <?php _e('Energielabel Opzoeken', 'energylabel-lookup'); ?>
                </button>
            </div>

            <div class="ell-form-group ell-form-footer">
                <p class="ell-powered-by">
                    <?php
                    $powered_by_link = sprintf(
                        '<a href="%s" target="_blank" rel="noopener noreferrer">%s</a>',
                        esc_url('https://www.mijnenergielabelberekenen.nl'),
                        esc_html__('MijnEnergielabelBerekenen.nl', 'energylabel-lookup')
                    );
                    echo wp_kses_post(sprintf(__('Energielabel check door %s', 'energylabel-lookup'), $powered_by_link));
                    ?>
                </p>
            </div>
        </form>
        
        <div id="ell-loading" class="ell-loading" style="display: none;">
            <div class="ell-spinner"></div>
            <p><?php _e('Label wordt opgezocht...', 'energylabel-lookup'); ?></p>
        </div>
        
        <div id="ell-results" class="ell-results" style="display: none;"></div>
        
        <div id="ell-error" class="ell-error" style="display: none;"></div>
    </div>
    <?php
    return ob_get_clean();
}

// AJAX handler for getting fresh nonce (cache-proof)
if (!function_exists('ell_ajax_get_nonce')) {
    function ell_ajax_get_nonce() {
        wp_send_json_success(array(
            'nonce' => wp_create_nonce('ell_nonce')
        ));
    }
}

// AJAX handler for looking up energy label
function ell_ajax_lookup_label() {
    // Verify nonce - accept from both fields for compatibility
    $nonce = '';
    $nonce_source = '';
    
    if (isset($_POST['nonce'])) {
        $nonce = $_POST['nonce'];
        $nonce_source = 'nonce';
    } elseif (isset($_POST['ell_form_nonce_field'])) {
        $nonce = $_POST['ell_form_nonce_field'];
        $nonce_source = 'ell_form_nonce_field';
    }
    
    // Verify nonce
    if (empty($nonce) || !wp_verify_nonce($nonce, 'ell_nonce')) {
        // Debug logging (without sensitive data)
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $user_agent = isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 100) : 'unknown';
            $ip = isset($_SERVER['REMOTE_ADDR']) ? preg_replace('/(\d+)\.(\d+)\.(\d+)\.(\d+)/', '$1.$2.$3.xxx', $_SERVER['REMOTE_ADDR']) : 'unknown';
            error_log(sprintf(
                'ELL Nonce verification failed - Source: %s, User-Agent: %s, IP: %s',
                $nonce_source ?: 'none',
                $user_agent,
                $ip
            ));
        }
        
        wp_send_json_error(__('Ververs de pagina en probeer opnieuw.', 'energylabel-lookup'), 403);
    }
    
    // Sanitize inputs
    $postcode = isset($_POST['postcode']) ? sanitize_text_field($_POST['postcode']) : '';
    $huisnummer = isset($_POST['huisnummer']) ? intval($_POST['huisnummer']) : 0;
    $toevoeging = isset($_POST['toevoeging']) ? sanitize_text_field($_POST['toevoeging']) : '';
    
    // Validate inputs
    if (empty($postcode) || empty($huisnummer)) {
        wp_send_json_error(__('Postcode en huisnummer zijn verplicht.', 'energylabel-lookup'));
    }
    
    // Clean postcode for validation (remove spaces and convert to uppercase)
    $clean_postcode = strtoupper(str_replace(' ', '', $postcode));
    
    // Specific postcode validation with detailed feedback
    if (strlen($clean_postcode) < 6) {
        wp_send_json_error(__('Postcode is te kort. Voer 4 cijfers en 2 letters in.', 'energylabel-lookup'));
    } elseif (strlen($clean_postcode) > 6) {
        wp_send_json_error(__('Postcode is te lang. Voer 4 cijfers en 2 letters in.', 'energylabel-lookup'));
    } elseif (!preg_match('/^\d{4}[A-Z]{2}$/', $clean_postcode)) {
        // Check specific parts
        if (!preg_match('/^\d{4}/', $clean_postcode)) {
            wp_send_json_error(__('Postcode moet beginnen met 4 cijfers.', 'energylabel-lookup'));
        } elseif (!preg_match('/[A-Z]{2}$/', $clean_postcode)) {
            wp_send_json_error(__('Postcode moet eindigen met 2 letters.', 'energylabel-lookup'));
        } else {
            wp_send_json_error(__('Voer een geldige postcode in (bijv. 1234 AB)', 'energylabel-lookup'));
        }
    }
    
    // Validate huisnummer
    if ($huisnummer < 1) {
        wp_send_json_error(__('Huisnummer moet groter zijn dan 0.', 'energylabel-lookup'));
    } elseif ($huisnummer > 99999) {
        wp_send_json_error(__('Huisnummer is te groot (maximaal 99999).', 'energylabel-lookup'));
    } elseif (!is_numeric($huisnummer) || $huisnummer != intval($huisnummer)) {
        wp_send_json_error(__('Huisnummer moet een geheel getal zijn.', 'energylabel-lookup'));
    }
    
    // Make API call
    $result = ell_call_api($clean_postcode, $huisnummer, $toevoeging);
    
    if (is_wp_error($result)) {
        wp_send_json_error($result->get_error_message());
    }
    
    // Track usage on successful API call
    ell_track_usage();
    
    $sanitized_result = ell_sanitize_api_response_recursive($result);
    wp_send_json_success($sanitized_result);
}

// Enqueue admin scripts
add_action('admin_enqueue_scripts', 'ell_admin_enqueue_scripts');
function ell_admin_enqueue_scripts($hook) {
    if (strpos($hook, 'energylabel-lookup') !== false) {
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.9.1', true);
        wp_enqueue_style('ell-admin-style', ELL_PLUGIN_URL . 'assets/css/ell-admin-style.css', array(), ELL_PLUGIN_VERSION);
    }
}

// API call function
function ell_call_api($postcode, $huisnummer, $toevoeging = '') {
    // Check for API key in order of priority: constant > admin setting
    $api_key = defined('EP_ONLINE_API_KEY') ? EP_ONLINE_API_KEY : get_option('ell_api_key');
    $base_url = 'https://public.ep-online.nl/api/v5';
    
    if (empty($api_key)) {
        return new WP_Error('no_api_key', __('API sleutel is niet geconfigureerd. Voeg deze toe op de <a href="/wp-admin/options-general.php?page=energylabel-lookup">instellingen pagina</a>.', 'energylabel-lookup'));
    }
    
    // Prepare query parameters
    $query_params = array(
        'postcode' => $postcode,
        'huisnummer' => $huisnummer
    );
    
    if (!empty($toevoeging)) {
        $query_params['huisnummertoevoeging'] = $toevoeging;
    }
    
    // Build URL with query parameters
    $url = $base_url . '/PandEnergielabel/Adres';
    if ($query_params) {
        $url .= '?' . http_build_query($query_params);
    }
    
    // Make API request using GET
    $response = wp_remote_get($url, array(
        'headers' => array(
            'Accept' => 'application/json',
            'Authorization' => $api_key
        ),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        return $response;
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code !== 200) {
        // Parse error response for better user feedback
        $error_data = json_decode($body, true);
        $error_message = '';
        
        if ($status_code === 404) {
            $error_message = __('Geen energielabel gevonden voor dit adres. Controleer of het postcode en huisnummer correct zijn ingevoerd.', 'energylabel-lookup');
        } elseif ($status_code === 401) {
            $error_message = __('API sleutel is ongeldig of ontbreekt. Controleer de instellingen.', 'energylabel-lookup');
        } elseif ($status_code === 429) {
            $error_message = __('Te veel verzoeken. Probeer het over enkele minuten opnieuw.', 'energylabel-lookup');
        } elseif ($status_code >= 500) {
            $error_message = __('De API service is momenteel niet beschikbaar. Probeer het later opnieuw.', 'energylabel-lookup');
        } else {
            $error_message = __('Er is een fout opgetreden bij het ophalen van het energielabel. Probeer het opnieuw.', 'energylabel-lookup');
        }
        
        return new WP_Error('api_error', $error_message);
    }
    
    $data = json_decode($body, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return new WP_Error('json_error', __('Er is een fout opgetreden bij het verwerken van de API response. Probeer het opnieuw.', 'energylabel-lookup'));
    }

    if (empty($data) || !isset($data[0]['Energieklasse'])) {
        return new WP_Error('api_no_data', __('Geen energielabel gevonden voor dit adres. Controleer of het postcode en huisnummer correct zijn ingevoerd.', 'energylabel-lookup'));
    }
    
    // Get the first result
    $result = $data[0];
    
    // Data quality checks
    $co2_value = isset($result['BerekendeCO2Emissie']) ? floatval($result['BerekendeCO2Emissie']) : 0;
    $co2_is_valid = ($co2_value > 0 && $co2_value < 999999);
    
    $energieverbruik_value = isset($result['BerekendeEnergieverbruik']) ? floatval($result['BerekendeEnergieverbruik']) : 0;
    $energieverbruik_is_valid = ($energieverbruik_value > 0 && $energieverbruik_value < 999999);
    $energieverbruik_is_extreme = ($energieverbruik_value >= 50000); // Flag for extreme values
    
    // Calculate days until expiration
    $geldig_tot_timestamp = strtotime($result['Geldig_tot']);
    $days_until_expiry = floor(($geldig_tot_timestamp - time()) / (60 * 60 * 24));
    $is_near_expiry = ($days_until_expiry > 0 && $days_until_expiry <= 180); // Less than 6 months
    
    // Format the response for display with comprehensive information
    $formatted_result = array(
        'energielabel' => $result['Energieklasse'],
        'adres' => $result['Postcode'] . ' ' . $result['Huisnummer'],
        'bouwjaar' => $result['Bouwjaar'],
        'gebouwtype' => $result['Gebouwtype'],
        'gebouwsubtype' => isset($result['Gebouwsubtype']) ? $result['Gebouwsubtype'] : '',
        'gebouwklasse' => isset($result['Gebouwklasse']) ? $result['Gebouwklasse'] : '',
        'energieverbruik' => $energieverbruik_is_valid ? number_format($energieverbruik_value, 0, ',', '.') . ' kWh/jaar' : '',
        'energieverbruik_is_valid' => $energieverbruik_is_valid,
        'energieverbruik_is_extreme' => $energieverbruik_is_extreme,
        'co2_uitstoot' => $co2_is_valid ? number_format($co2_value, 2, ',', '.') . ' kg CO2/jaar' : '',
        'co2_is_valid' => $co2_is_valid,
        'certificaathouder' => $result['Certificaathouder'],
        'geldig_tot' => date('d-m-Y', $geldig_tot_timestamp),
        'registratiedatum' => isset($result['Registratiedatum']) ? date('d-m-Y', strtotime($result['Registratiedatum'])) : '',
        'opnamedatum' => isset($result['Opnamedatum']) ? date('d-m-Y', strtotime($result['Opnamedatum'])) : '',
        'status' => isset($result['Status']) ? $result['Status'] : 'Actief',
        'status_display' => isset($result['Status']) ? $result['Status'] : 'Actief',
        // NTA 8800 Prestatie-scores
        'energieindex' => isset($result['EnergieIndex']) ? number_format($result['EnergieIndex'], 1, ',', '.') : '',
        'primaire_fossiele_energie' => isset($result['PrimaireFossieleEnergie']) ? number_format($result['PrimaireFossieleEnergie'], 0, ',', '.') . ' kWh/m²/jaar' : '',
        'energiebehoefte' => isset($result['Energiebehoefte']) ? number_format($result['Energiebehoefte'], 0, ',', '.') . ' kWh/m²/jaar' : '',
        'aandeel_hernieuwbare_energie' => isset($result['Aandeel_hernieuwbare_energie']) ? number_format($result['Aandeel_hernieuwbare_energie'], 1, ',', '.') . '%' : '',
        // Oppervlakte & compactheid
        'gebruiksoppervlakte' => isset($result['Gebruiksoppervlakte_thermische_zone']) ? number_format($result['Gebruiksoppervlakte_thermische_zone'], 0, ',', '.') . ' m²' : '',
        'compactheid' => isset($result['Compactheid']) ? number_format($result['Compactheid'], 1, ',', '.') . ' m³/m²' : '',
        // Klimaat-indicatoren
        'temperatuuroverschrijding' => isset($result['Temperatuuroverschrijding']) ? number_format($result['Temperatuuroverschrijding'], 1, ',', '.') . '%' : '',
        'warmtebehoefte' => isset($result['Warmtebehoefte']) ? number_format($result['Warmtebehoefte'], 0, ',', '.') . ' kWh/m²/jaar' : '',
        // Herkomst label
        'soort_opname' => isset($result['Soort_opname']) ? $result['Soort_opname'] : '',
        'is_vereenvoudigd' => isset($result['IsVereenvoudigdLabel']) ? ($result['IsVereenvoudigdLabel'] === true || $result['IsVereenvoudigdLabel'] === 'true' || $result['IsVereenvoudigdLabel'] === 1) : false,
        // Check if label is expired
        'is_verlopen' => $geldig_tot_timestamp < time(),
        'is_near_expiry' => $is_near_expiry,
        'days_until_expiry' => $days_until_expiry
    );
    
    return $formatted_result;
}

/**
 * Recursively sanitizes data for safe output.
 *
 * @param mixed $data The data to sanitize.
 * @return mixed Sanitized data.
 */
function ell_sanitize_api_response_recursive($data) {
    if (is_array($data)) {
        foreach ($data as $key => $value) {
            $data[$key] = ell_sanitize_api_response_recursive($value);
        }
    } elseif (is_string($data)) {
        return esc_html($data);
    }
    return $data;
}

// --- Admin Settings Page ---

// Add admin menu
add_action('admin_menu', 'ell_add_admin_menu');
function ell_add_admin_menu() {
    add_menu_page(
        __('Energielabel Opzoeken', 'energylabel-lookup'),
        __('Label Lookup', 'energylabel-lookup'),
        'manage_options',
        'energylabel-lookup',
        'ell_admin_page_html',
        'dashicons-search',
        30
    );
    
    add_submenu_page(
        'energylabel-lookup',
        __('Dashboard', 'energylabel-lookup'),
        __('Dashboard', 'energylabel-lookup'),
        'manage_options',
        'energylabel-lookup',
        'ell_admin_page_html'
    );
    
    add_submenu_page(
        'energylabel-lookup',
        __('Instellingen', 'energylabel-lookup'),
        __('Instellingen', 'energylabel-lookup'),
        'manage_options',
        'energylabel-lookup-settings',
        'ell_settings_page_html'
    );
}

// Track usage statistics
function ell_track_usage() {
    $today = current_time('Y-m-d');
    $usage_data = get_option('ell_usage_stats', array());
    
    if (!isset($usage_data[$today])) {
        $usage_data[$today] = 0;
    }
    $usage_data[$today]++;
    
    update_option('ell_usage_stats', $usage_data);
}

// Get usage statistics
function ell_get_usage_stats($period = 'week') {
    $usage_data = get_option('ell_usage_stats', array());
    $stats = array();
    
    switch ($period) {
        case 'week':
            $days = 7;
            break;
        case 'month':
            $days = 30;
            break;
        case 'year':
            $days = 365;
            break;
        default:
            $days = 7;
    }
    
    for ($i = $days - 1; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $stats[$date] = isset($usage_data[$date]) ? $usage_data[$date] : 0;
    }
    
    return $stats;
}

// Render the main admin page
function ell_admin_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    $week_stats = ell_get_usage_stats('week');
    $month_stats = ell_get_usage_stats('month');
    $year_stats = ell_get_usage_stats('year');
    
    $total_week = array_sum($week_stats);
    $total_month = array_sum($month_stats);
    $total_year = array_sum($year_stats);
    
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <div class="ell-admin-dashboard">
            <div class="ell-stats-overview">
                <div class="ell-stat-card">
                    <h3><?php _e('Afgelopen Week', 'energylabel-lookup'); ?></h3>
                    <div class="ell-stat-number"><?php echo $total_week; ?></div>
                    <div class="ell-stat-label"><?php _e('zoekopdrachten', 'energylabel-lookup'); ?></div>
                </div>
                
                <div class="ell-stat-card">
                    <h3><?php _e('Afgelopen Maand', 'energylabel-lookup'); ?></h3>
                    <div class="ell-stat-number"><?php echo $total_month; ?></div>
                    <div class="ell-stat-label"><?php _e('zoekopdrachten', 'energylabel-lookup'); ?></div>
                </div>
                
                <div class="ell-stat-card">
                    <h3><?php _e('Afgelopen Jaar', 'energylabel-lookup'); ?></h3>
                    <div class="ell-stat-number"><?php echo $total_year; ?></div>
                    <div class="ell-stat-label"><?php _e('zoekopdrachten', 'energylabel-lookup'); ?></div>
                </div>
            </div>
            
            <div class="ell-charts-container">
                <div class="ell-chart">
                    <h3><?php _e('Gebruik Afgelopen Week', 'energylabel-lookup'); ?></h3>
                    <canvas id="ell-week-chart"></canvas>
                </div>
                
                <div class="ell-chart">
                    <h3><?php _e('Gebruik Afgelopen Maand', 'energylabel-lookup'); ?></h3>
                    <canvas id="ell-month-chart"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Week chart
        var weekCtx = document.getElementById('ell-week-chart').getContext('2d');
        new Chart(weekCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($week_stats)); ?>,
                datasets: [{
                    label: '<?php _e('Zoekopdrachten', 'energylabel-lookup'); ?>',
                    data: <?php echo json_encode(array_values($week_stats)); ?>,
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
        
        // Month chart
        var monthCtx = document.getElementById('ell-month-chart').getContext('2d');
        new Chart(monthCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($month_stats)); ?>,
                datasets: [{
                    label: '<?php _e('Zoekopdrachten', 'energylabel-lookup'); ?>',
                    data: <?php echo json_encode(array_values($month_stats)); ?>,
                    borderColor: '#27ae60',
                    backgroundColor: 'rgba(39, 174, 96, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    });
    </script>
    <?php
}

// Render the settings page
function ell_settings_page_html() {
    if (!current_user_can('manage_options')) {
        return;
    }
    ?>
    <div class="wrap ell-settings-page">
        <h1><?php _e('Energielabel Opzoeken Instellingen', 'energylabel-lookup'); ?></h1>
        <form action="options.php" method="post">
            <?php
            settings_fields('ell_options_group');
            do_settings_sections('energylabel-lookup');
            submit_button(__('Instellingen Opslaan', 'energylabel-lookup'));
            ?>
        </form>
    </div>
    <?php
}

// Register settings
add_action('admin_init', 'ell_register_settings');
function ell_register_settings() {
    register_setting('ell_options_group', 'ell_api_key', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
        'default' => ''
    ]);

    add_settings_section(
        'ell_api_settings_section',
        __('API Instellingen', 'energylabel-lookup'),
        'ell_api_settings_section_callback',
        'energylabel-lookup'
    );

    add_settings_field(
        'ell_api_key_field',
        __('EP-Online API Sleutel', 'energylabel-lookup'),
        'ell_api_key_field_callback',
        'energylabel-lookup',
        'ell_api_settings_section'
    );
}

function ell_api_settings_section_callback() {
    echo '<p>' . __('Voer hieronder uw EP-Online API sleutel in. Deze sleutel is vereist om energielabels op te zoeken.', 'energylabel-lookup') . '</p>';
    echo '<p>' . sprintf(__('U kunt uw API sleutel ook definiëren in uw %s bestand door %s toe te voegen. Dit overschrijft de instelling hieronder.', 'energylabel-lookup'), '<code>wp-config.php</code>', '<code>define(\'EP_ONLINE_API_KEY\', \'uw-api-sleutel\');</code>') . '</p>';
    echo '<p>' . __('API URL: https://public.ep-online.nl/api/v5/PandEnergielabel/Adres', 'energylabel-lookup') . '</p>';
    echo '<p>' . __('Methode: GET met query parameters (postcode, huisnummer, huisnummertoevoeging)', 'energylabel-lookup') . '</p>';
}

function ell_api_key_field_callback() {
    $api_key = get_option('ell_api_key');
    ?>
    <input type="text" name="ell_api_key" id="ell_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" placeholder="<?php _e('Voer uw API sleutel in', 'energylabel-lookup'); ?>">
    <?php
}

// Activation hook
register_activation_hook(__FILE__, 'ell_activate');
function ell_activate() {
    // Create necessary directories
    $upload_dir = wp_upload_dir();
    $ell_dir = $upload_dir['basedir'] . '/energylabel-lookup';
    
    if (!file_exists($ell_dir)) {
        wp_mkdir_p($ell_dir);
    }
    
    // Add default options
    add_option('ell_version', ELL_PLUGIN_VERSION);
    
    // Set default settings if they don't exist
    if (!get_option('ell_api_key')) {
        add_option('ell_api_key', '');
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'ell_deactivate');
function ell_deactivate() {
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Uninstall hook
register_uninstall_hook(__FILE__, 'ell_uninstall');
function ell_uninstall() {
    // Remove options
    delete_option('ell_version');
    delete_option('ell_api_key');
    delete_option('ell_usage_stats');
}

// Check for updates
add_action('plugins_loaded', 'ell_check_for_updates');
function ell_check_for_updates() {
    $current_version = get_option('ell_version', '1.0.0');
    
    if (version_compare($current_version, ELL_PLUGIN_VERSION, '<')) {
        // Update version
        update_option('ell_version', ELL_PLUGIN_VERSION);
        
        // Run any necessary update tasks
        ell_run_updates($current_version, ELL_PLUGIN_VERSION);
    }
}

// Run updates
function ell_run_updates($from_version, $to_version) {
    // Update from 1.1.0 to 1.2.0
    if (version_compare($from_version, '1.2.0', '<')) {
        // Add any new options or database changes here
        if (!get_option('ell_usage_stats')) {
            add_option('ell_usage_stats', array());
        }
    }
    
    // Add more version-specific updates here as needed
} 