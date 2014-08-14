<?php
/*
Plugin Name: WooCommerce Pay What You Want
Plugin URI: https://github.com/draekko/woocommerce-pay-what-you-want
Description: Adds the ability to let users set their own prices for products in WooCommerce
Author: Draekko
Author URI: http://draekko.com
Requires at least: 3.9.1
Tested up to: 3.9.1
Version: 1.0.0
License: GPLv3 or later
*/

/*
= Disclaimer =

This application and documentation and the information in it does not 
constitute legal advice. It is also is not a substitute for legal or 
other professional advice. Users should consult their own legal counsel 
for advice regarding the application of the law and this application as 
it applies to you and/or your business. This program is distributed in 
the hope that it will be useful, but WITHOUT ANY WARRANTY; without even 
the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. 
Continued use consitutes agreement of these terms. See the GNU General 
Public License for more details. 
*/

if ( ! defined( 'ABSPATH' ) ) exit;

global $pwyw_related_product;

/* Verify that Woocommerce is active */
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option('active_plugins' ) ) ) ) {
    if ( !class_exists( 'WC_Pay_What_You_Want' ) ) {

    /* CLASS START */

    class WC_Pay_What_You_Want {

        private $plugin_dir = '';
        private $plugin_url = '';
        private $plugin_css_url = '';
        private $plugin_js_url = '';
        private $plugin_mm_js_url = '';

        const ID = 'wc_pay_what_you_want';
        const TITLE = 'Pay What You Want';
        const DESCRIPTION = 'WooCommerce Pay What You Want lets clients pay desired amounts for products.';
        const WC_PWYW_VERSION = '1.0.0';

        /*********************************************************************/

        public function __construct() {
            global $post;

            $enabled = get_option( self::ID . '_plugin_enabled' );
            if ( $enabled == 'yes' || $enabled == '1' ) {
                // Load stuff here if enabled
                $debug_js_suffix='.min';
                if (WP_DEBUG) {
                    $debug_js_suffix='';
                }
                    $debug_js_suffix='';
                $this->plugin_dir = dirname( __FILE__ );
                $this->plugin_url = plugins_url( '', __FILE__ );
                $this->plugin_css_url = plugins_url( 'css/paywhatyouwant.css', __FILE__ );
                $this->plugin_js_url = plugins_url( 'scripts/paywhatyouwant'.$debug_js_suffix.'.js', __FILE__ );
                $this->plugin_mm_js_url = plugins_url( 'scripts/jquery.maskMoney'.$debug_js_suffix.'.js', __FILE__ );

                /* DEQUEUE JS */
                add_action( 'wp_enqueue_scripts', array( &$this, 'pwyw_dequeue_scripts' ), 9999 );

                /* CSS TEMPLATE OVERRIDE */
                $template_integration_url_path = get_template_directory() . '/css/paywhatyouwant.css';
                if ( file_exists( $template_integration_url_path ) ) {
                    $this->plugin_css_url = get_template_directory_uri() . '/css/paywhatyouwant.css';
                }

                /* JS TEMPLATE OVERRIDE */
                $template_integration_url_path = get_template_directory() . '/css/paywhatyouwant'.$debug_js_suffix.'.js';
                if ( file_exists( $template_integration_url_path ) ) {
                    $this->plugin_js_url = get_template_directory_uri() . '/css/paywhatyouwant'.$debug_js_suffix.'.js';
                }
            }

            /* BOOTSTRAP */
            add_action( 'wp_head', array( &$this, self::ID . '_h_init' ), 0);
            add_action( 'plugins_loaded', array( &$this, self::ID . '_p_init' ), 0);
            add_action( 'init', array( &$this, self::ID . '_c_init' ), 0 );
            add_filter( 'loop_shop_columns', array( &$this, 'pwyw_product_columns_frontend' ) );
        }

        function get_price_add_cart_buttons() {
            add_action( 'woocommerce_after_shop_loop_item', array( &$this, 'add_to_cart_button' ) );
        }
        
        /*********************************************************************/

        public function wc_pay_what_you_want_c_init() {
            $enabled = get_option( self::ID . '_plugin_enabled' );
            if ( $enabled == 'yes' || $enabled == '1' ) {
                remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );
            }
        }

        /*********************************************************************/

        public function wc_pay_what_you_want_p_init() {
            if ( is_dir( $this->plugin_dir . 'languages' ) ) {
				add_action( 'init', array( &$this, 'load_textdomain' ) );
            }

            /* ADMIN */
			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array(&$this, 'add_action_link') );
            add_filter( 'woocommerce_settings_tabs_array', __CLASS__ . '::add_settings_tab', 30 );
            add_action( 'woocommerce_settings_tabs_wc_pay_what_you_want', __CLASS__ . '::admin_settings_tab');
            add_action( 'woocommerce_update_options_wc_pay_what_you_want', __CLASS__ . '::update_admin_settings' );
            add_action( 'init', __CLASS__ . '::default_settings' );
            add_action( 'admin_enqueue_scripts', __CLASS__ . '::do_admin_styles_scripts' );

            $enabled = get_option( self::ID . '_plugin_enabled' );
            if ( $enabled != 'yes' && $enabled != '1' ) {
                return;
            }

            /* BOOTSTRAP */
            add_action( 'wp_enqueue_scripts', array( &$this, 'pwyw_dequeue_scripts' ), 9999 );

            $priority = has_action('woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart');
            remove_action( 'woocommerce_simple_add_to_cart', 'woocommerce_simple_add_to_cart', $priority );
            add_action( 'woocommerce_simple_add_to_cart', array( &$this, 'set_simple_add_to_cart' ), 30 );

            $priority = has_action('woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart');
            remove_action( 'woocommerce_variable_add_to_cart', 'woocommerce_variable_add_to_cart', $priority );
            add_action( 'woocommerce_variable_add_to_cart', array( &$this, 'set_variable_add_to_cart' ), 30 );

            $priority = has_action('woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart');
            remove_action( 'woocommerce_grouped_add_to_cart', 'woocommerce_grouped_add_to_cart', $priority );
            add_action( 'woocommerce_grouped_add_to_cart', array( &$this, 'set_grouped_add_to_cart' ), 30 );

            $priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta' );
            remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta', $priority );
            add_action( 'woocommerce_after_single_product_summary', __CLASS__ . '::do_action_output_related_products', 20 );

            $priority = has_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products' );
            remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', $priority );

            add_action( 'woocommerce_after_shop_loop_item', array( &$this, 'get_price_add_cart_buttons' ), 1 );
            //add_action( 'woocommerce_single_product_summary', __CLASS__ . '::set_product_meta', 1 );
            add_action( 'wp_footer', __CLASS__ . '::set_info_boxes');

            /* PRODUCTS */
            //add_action( 'woocommerce_product_options_general_product_data', __CLASS__ . '::display_product_price_options', 10 );
            //add_action( 'woocommerce_product_options_pricing', __CLASS__ . '::display_product_price_options', 10 );
            //add_action( 'woocommerce_product_write_panel_tabs', __CLASS__ . '::add_product_options_tab' );
            add_action('woocommerce_product_write_panels', __CLASS__ . '::display_product_price_options' );
            add_action( 'woocommerce_process_product_meta', __CLASS__ . '::save_product_price_options' );
            add_filter( 'woocommerce_product_data_tabs', __CLASS__ . '::wc_pay_what_you_want_options_tab' );

            /* STORE & CART */
            add_filter( 'woocommerce_get_price_html', __CLASS__ . '::set_product_price_alt', 10, 2 );
            add_filter( 'woocommerce_get_cart_', __CLASS__ . '::set_product_price', 10, 2 );
            //add_filter( 'woocommerce_add_to_cart_handler', __CLASS__ . '::add_to_cart_handler', 10 );
            add_action( 'wp_ajax_wc_pay_what_you_want_add_to_cart', array( &$this, 'add_to_cart_handler' ) );
            add_action( 'wp_ajax_nopriv_wc_pay_what_you_want_add_to_cart', array( &$this, 'add_to_cart_handler_nopriv' ) );
            add_action( 'woocommerce_before_calculate_totals', __CLASS__ . '::set_custom_price' );
            add_action( 'woocommerce_add_to_cart', array( $this, 'add_to_cart_hook' ) );
            add_filter( 'woocommerce_loop_add_to_cart_link', array( $this, 'remove_link' ), 10 );
            add_filter( 'woocommerce_add_cart_item', __CLASS__ . '::pwyw_add_cart_item', 10, 1);
            add_filter( 'woocommerce_get_cart_item_from_session', __CLASS__ . '::get_cart_item_from_session', 5, 2 );
            add_filter( 'woocommerce_add_cart_item_data', __CLASS__ . '::add_cart_item_custom_data_price', 10, 2 );

            /* QUEUE JS */
            add_action( 'wp_enqueue_scripts', array( &$this, 'set_queue_js_css' ), 999 );

            /* QUEUE JS */
            add_action( 'wp_enqueue_scripts', __CLASS__ . '::set_product_css', 999 );
            //add_action( 'wp_print_styles', __CLASS__ . '::set_product_js_css', 999 );
        }

        /***************************************************************/

        public static function do_admin_styles_scripts() {
            wp_enqueue_style( self::ID . '_CSS',  plugins_url( 'css/paywhatyouwant.css', __FILE__ ), false, self::WC_PWYW_VERSION );
        }

        public function add_action_link( $links ) {
            global $woocommerce;
            $settings_url = admin_url( 'admin.php?page=wc-settings&tab=wc_pay_what_you_want' );
            $plugin_links = array( '<a href="' . $settings_url . '">' . __( 'Settings', 'wc_mailchimp_casl' ) . '</a>', );
            return array_merge( $plugin_links, $links );
        }

        /*********************************************************************/

        function pwyw_dequeue_scripts() {
            wp_deregister_script( 'woocommerce' );
            wp_dequeue_script( 'woocommerce' );
        }
        
        /*********************************************************************/

        public function wc_pay_what_you_want_h_init() {
            //self::set_product_js();
            echo '<script type="text/javascript">';
            echo "var ajaxurl='" . admin_url( 'admin-ajax.php' ) . "'";
            echo '</script>';
        }

        /***************************************************************/

        public function load_textdomain() {
            load_plugin_textdomain( self::ID, false, dirname( plugin_basename( __FILE__ ) ) . 'languages' );
        }

        /*********************************************************************/

        public static function add_settings_tab($settings_tabs) {
            $settings_tabs[ self::ID ] = __( self::TITLE, self::ID );
            return $settings_tabs;
        }

        /*********************************************************************/

        public static function add_product_options_tab() {
            echo '<li class="' . self::ID . '_options_tab"><a href="#'.self::ID . '_options_tab">' . __( self::TITLE, self::ID ) . '</a></li>';
        }

        /*********************************************************************/

        public static function wc_pay_what_you_want_options_tab( $data ) {
            $data['wc_pay_what_you_want_options_tab'] = array(
                'label' => __( self::TITLE, self::ID ),
                'target' => 'wc_pay_what_you_want_options_tab',
                'class' => array( 'hide_if_grouped', 'hide_if_external' ),
            );
            return $data;
        }

        /*********************************************************************/

        public static function get_admin_form() {
            $admin_menu = array(
                'section_title_t' => array(
                    'id'        => self::ID . '_section_title_t',
                    'name'      => 'Plugin',
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'css'       => 'font-size:56px;',
                    'type'      => 'title',
                ),
				'plugin_enabled' => array(
                    'id'        => self::ID . '_plugin_enabled',
					'title' 	=> __( 'Enable plugin', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Enable/Disable option to turn plugin on or off.',
					'default' 	=> 'no'
				),
                'section_end_t' => array(
                    'id'        => self::ID . '_section_end_t',
                    'type'      => 'sectionend',
                ),
                'section_title' => array(
                    'id'        => self::ID . '_section_title',
                    'name'      => 'Product Page Labels',
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'css'       => 'font-size:56px;',
                    'type'      => 'title',
                ),
                'shop_variable_pay_price' => array(
                    'id'        => self::ID . '_shop_variable_pay_price',
                    'name'      => __('Variable Product Message', self::ID),
                    'desc'      => __('Variable product message to show on shop page'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'Click to select options and the amount you wish to pay.',
                    'type'      => 'text',
                ),
                'pay_price' => array(
                    'id'        => self::ID . '_pay_price',
                    'name'      => __('Pay What You Want', self::ID),
                    'desc'      => __('Please Enter A Label'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'Enter the amount you wish to pay',
                    'type'      => 'text',
                ),
                'minimum_price' => array(
                    'id'        => self::ID . '_minimum_price',
                    'name'      => __( 'Minimum Price', self::ID ),
                    'desc'      => __( 'Please Enter A Minimum Price Label', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'Minimum Price',
                    'type'      => 'text',
                ),
                'recommended_price' => array(
                    'id'        => self::ID . '_recommended_price',
                    'name'      => __( 'Suggested Price',  self::ID ),
                    'desc'      => __( 'Please Enter A Suggested Price Label', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'Suggested Price',
                    'type'      => 'text',
                ),
                'maximum_price' => array(
                    'id'        => self::ID . '_maximum_price',
                    'name'      => __('Maximum Price', self::ID),
                    'desc'      => __('Please Enter A Maximum Price Label'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'Maximum Price',
                    'type'      => 'text',
                ),
                'section_end_0' => array(
                    'id'        => self::ID . '_section_end_0',
                    'type'      => 'sectionend',
                ),
                'section_show_misc' => array(
                    'id'        => self::ID . '_misc_title',
                    'name'      => 'Miscellaneous',
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'css'       => 'font-size:56px;',
                    'type'      => 'title',
                ),
				'show_related_items' => array(
                    'id'        => self::ID . '_show_related_products',
					'title' 	=> __( 'Show Related Products', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Enable to show related products in single product view.',
					'default' 	=> 'yes'
				),
				'related_button_text' => array(
                    'id'        => self::ID . '_related_button_text',
					'title' 	=> __( 'Related button text', self::ID ),
                    'desc'      => __( 'Set related button text', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'   => 'SEE PRODUCT',
					'type' 		=> 'text',
				),
				'price_money_prefix' => array(
                    'id'        => self::ID . '_money_prefix',
					'title' 	=> __( 'Set money prefix symbol', self::ID ),
                    'desc'      => __( 'Displays money prefix symbol for input box', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'   => 'CAD$ ',
					'type' 		=> 'text',
				),
				'price_money_suffix' => array(
                    'id'        => self::ID . '_money_suffix',
					'title' 	=> __( 'Set suffix money symbol', self::ID ),
                    'desc'      => __( 'Displays money suffix symbol for input box', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'   => '',
					'type' 		=> 'text',
				),
				'stock_show_items' => array(
                    'id'        => self::ID . '_show_stocks',
					'title' 	=> __( 'Show Stock With Price', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Show in or out of stock for all products.',
					'default' 	=> 'no'
				),
				'show_product_text' => array(
                    'id'        => self::ID . '_show_product_text',
					'title' 	=> __( 'Show text', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Enable to show text portion of description for products.',
					'default' 	=> 'no'
				),
				'max_product_text_size' => array(
                    'id'        => self::ID . '_max_product_text_size',
					'title' 	=> __( 'Set maximum characters', self::ID ),
                    'desc'      => __( 'Set value of maximum characters to show (default: 220).', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'   => '220',
					'type' 		=> 'text',
				),
				'show_product_rating' => array(
                    'id'        => self::ID . '_show_product_rating',
					'title' 	=> __( 'Show rating', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Enable to show rating portion of description for products.',
					'default' 	=> 'no'
				),
				'show_linkify_items' => array(
                    'id'        => self::ID . '_linkify_text',
					'title' 	=> __( 'Set text as link to product', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Enable to make text portion of description a link to product in shop.',
					'default' 	=> 'no'
				),
                'section_end_1' => array(
                    'id'        => self::ID . '_section_end_1',
                    'type'      => 'sectionend',
                ),
                'filters_title' => array(
                    'id'        => self::ID . '_filters_title',
                    'name'      => __('Product Group Filters', self::ID),
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'type'      => 'title',
                ),
				/*'group_show_external' => array(
                    'id'        => self::ID . '_filters_group_external',
					'title' 	=> __( 'Show For External', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Show external product groups (simple products always enabled).',
					'default' 	=> 'no'
				),*/
				'group_show_variable' => array(
                    'id'        => self::ID . '_filters_group_variable',
					'title' 	=> __( 'Show For Variable', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Show variable product groups.',
					'default' 	=> 'yes'
				),
				/*
				'group_show_simple' => array(
                    'id'        => self::ID . '_filters_group_simple',
					'title' 	=> __( 'Show For Simple', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Show simple product groups.',
					'default' 	=> 'no'
				),
				'group_show_grouped' => array(
                    'id'        => self::ID . '_filters_group_grouped',
					'title' 	=> __( 'Show For Grouped', self::ID ),
					'label' 	=> __( 'Enable/Disable', self::ID ),
					'type' 		=> 'checkbox',
					'desc' 	    => 'Show grouped product groups.',
					'default' 	=> 'no'
				),
                */
                'section_end_2' => array(
                    'id'        => self::ID . '_section_end_2',
                    'type'      => 'sectionend',
                ),
                'error_title' => array(
                    'id'        => self::ID . '_error_title',
                    'name'      => __('Error Message Labels', self::ID),
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'type'      => 'title',
                ),
                'minimum_price_error' => array(
                    'id'        => self::ID . '_minimum_price_error',
                    'name'      => __('Minimum Price Error', self::ID),
                    'desc'      => __('Please Enter Minimum Price Error Message'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'ERROR: Enter Minimum Price',
                    'type'      => 'text',
                ),
                'maximum_price_error' => array(
                    'id'        => self::ID . '_maximum_price_error',
                    'name'      => __('Maximum Price Error', self::ID),
                    'desc'      => __('Please Enter Maximum Price Error Message'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'ERROR: Price should not be more than Maximum Price',
                    'type'      => 'text'
                ),
                'input_price_error' => array(
                    'id'        => self::ID . '_input_price_error',
                    'name'      => __('Price Input Error', self::ID),
                    'desc'      => __('Please Enter Your Input Error Message'),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_input',
                    'css'       => '',
                    'inittxt'       => 'ERROR: Enter Only Numbers',
                    'type'      => 'text'
                ),
                'section_end_3' => array(
                    'id'        => self::ID . '_section_end_3',
                    'type'      => 'sectionend',
                ),
                'css_title' => array(
                    'id'        => self::ID . '_modify_css',
                    'name'      => __( 'Modify CSS', self::ID ),
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'type'      => 'title',
                ),
                'css_code' => array(
                    'id'        => self::ID . '_custom_css',
                    'name'      => __( 'CSS Code', self::ID ),
                    'desc'      => __( 'Add Custom CSS', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_textarea',
                    'css'       => '',
                    'inittxt'   => '',
                    'type'      => 'textarea',
                ),
                'section_end_4' => array(
                    'id'        => self::ID . '_section_end_4',
                    'type'      => 'sectionend',
                ),
                'js_title' => array(
                    'id'        => self::ID . '_modify_js',
                    'name'      => __( 'Modify Javascript', self::ID ),
                    'desc'      => '',
                    'class'     => 'pwyw_admin_menu_h3',
                    'type'      => 'title',
                ),
                'js_code' => array(
                    'id'        => self::ID . '_custom_js',
                    'name'      => __( 'Javascript Code', self::ID ),
                    'desc'      => __( 'Add Custom Javascript', self::ID ),
                    'desc_tip'  => true,
                    'tip'       => '',
                    'class'     => 'pwyw_admin_menu_textarea',
                    'css'       => '',
                    'inittxt'   => '',
                    'type'      => 'textarea',
                ),
                'section_end_5' => array(
                    'id'        => self::ID . '_section_end_5',
                    'type'      => 'sectionend',
                ),
            );

            return apply_filters( 'wc_pay_what_you_want_settings', $admin_menu );
        }

        /*********************************************************************/

        public static function update_default_settings() {
            foreach ( self::get_admin_form() as $setting ) {
                if ( isset( $setting['id'] ) && isset( $setting['inittxt'] ) ) {
                    update_option( $setting['id'], $setting['inittxt'] );
                }
            }
        }

        /*********************************************************************/

        public static function default_settings() {
            foreach ( self::get_admin_form() as $setting ) {
                if ( isset( $setting['id'] ) && isset( $setting['inittxt'] ) ) {
                    add_option( $setting['id'], $setting['inittxt'] );
                }
            }
        }

        /*********************************************************************/

        public static function update_admin_settings() {
            woocommerce_update_options( self::get_admin_form() );
        }

        /*********************************************************************/

        public static function admin_settings_tab() {
            woocommerce_admin_fields( self::get_admin_form() );
        }

        /*********************************************************************/

        public static function get_product_group_filters() {
            $groupsfilters = '';
            $showall = false;

            if ( isset( $showall ) && $showall ) {
                $groupsfilters = ' show_if_simple show_if_external show_if_grouped show_if_variable ';
            } else {
                $groupsfilters = ' show_if_simple ';
                //$groupsfilters .= ' hide_if_variable ';
                $groupsfilters .= ' hide_if_external ';
                $groupsfilters .= ' hide_if_grouped ';
                /* $showexternal = get_option(self::ID . '_filters_group_external');
                if ( isset( $showexternal ) && $showexternal == 'yes' ) {
                    $groupsfilters .= ' show_if_external ';
                } else {
                    $groupsfilters .= ' hide_if_external ';
                }*/

                $showvariable = get_option(self::ID . '_filters_group_variable');
                if ( isset( $showvariable ) && $showvariable ) {
                    $groupsfilters .= ' show_if_variable ';
                } else {
                    $groupsfilters .= ' hide_if_variable ';
                }

                /*
                $showsimple = get_option(self::ID . '_filters_group_simple');
                if ( isset( $showsimple ) && $showsimple ) {
                    $groupsfilters .= ' show_if_simple ';
                } else {
                    $groupsfilters .= ' hide_if_simple ';
                }

                $showgrouped = get_option(self::ID . '_filters_group_grouped');
                if ( isset( $showgrouped ) && $showgrouped ) {
                    $groupsfilters .= ' show_if_grouped ';
                } else {
                    $groupsfilters .= ' hide_if_grouped ';
                }
                */
            }

            return $groupsfilters;
        }

        /*********************************************************************/

        public static function get_product_price_options_field() {
            $display_options_fields = array(
                '0' => array (
                    'type'          => 'select',
                    'id'            => self::ID . '_enable_for_product',
                    'label'         => __(self::TITLE, self::ID),
                    'description'   => __('Enable or disable for this product', self::ID),
                    'desc_tip'      => true,
                    'options'       => array(
                        '0'          => __('Disabled', self::ID),
                        '1'          => __('Enabled', self::ID),
                    ),
                    'value'         => '0',
                ),
                '1' => array (
                    'type'          => 'select',
                    'id'            => self::ID . '_input_for_product',
                    'label'         => __('Input Type', self::ID),
                    'description'   => __('Choose which type of input to show client', self::ID),
                    'desc_tip'      => true,
                    'options'       => array(
                        '0'          => __('Generic', self::ID),
                        '1'          => __('Fancy', self::ID),
                    ),
                    'value'         => '0',
                ),
                '2' => array (
                    'type'          => 'select',
                    'id'            => self::ID . '_show_stock_product',
                    'label'         => __('Stocks', self::ID),
                    'description'   => __('Choose to show if product in stock', self::ID),
                    'desc_tip'      => true,
                    'options'       => array(
                        'show'       => __('Show', self::ID),
                        'hide'       => __('Hide', self::ID),
                    ),
                    'value'         => 'hide',
                ),
                '3' => array (
                    'type'          => 'checkbox',
                    'id'            => self::ID . '_display_min_price',
                    'name'          => self::ID . '_display_min_price',
                    'label'         => __('Display Minimum', self::ID),
                    'description'   => '',
                    'value'         => '',
                    'cbvalue'       => 'yes',
                ),
                '4' => array (
                    'type'          => 'number',
                    'id'            => self::ID . '_amount_min_price',
                    'label'         => __('Minimum Price', self::ID),
                    'description'   => 'Minimum price selection for clients',
                    'desc_tip'      => true,
                    'value'         => '',
                    'custom_attributes' => array(
				        'step' 	     => 'any',
				        'min'        => '0'
			         ),
                ),
                '5' => array (
                    'type'          => 'checkbox',
                    'id'            => self::ID . '_display_rec_price',
                    'name'          => self::ID . '_display_rec_price',
                    'label'         => __('Display Recommended', self::ID),
                    'description'   => '',
                    'value'         => '',
                    'cbvalue'       => 'yes',
                ),
                '6' => array (
                    'type'          => 'number',
                    'id'            => self::ID . '_amount_rec_price',
                    'label'         => __('Recommended Price', self::ID),
                    'description'   => 'Suggested price recommendation for clients',
                    'desc_tip'      => true,
                    'value'         => '',
                    'custom_attributes' => array(
				        'step' 	     => 'any',
				        'min'        => '0'
			         ),
                ),
                '7' => array (
                    'type'          => 'checkbox',
                    'id'            => self::ID . '_display_max_price',
                    'name'          => self::ID . '_display_max_price',
                    'label'         => __('Display Maximum', self::ID),
                    'description'   => '',
                    'value'         => '',
                    'cbvalue'       => 'yes',
                ),
                '8' => array (
                    'type'          => 'number',
                    'id'            => self::ID . '_amount_max_price',
                    'label'         => __('Maximum Price', self::ID),
                    'description'   => 'Maximum price selection for clients',
                    'desc_tip'      => true,
                    'value'         => '',
                    'custom_attributes' => array(
				        'step' 	     => 'any',
				        'min'        => '0'
			         ),
                ),
            );
            return $display_options_fields;
        }


        public function pwyw_product_columns_frontend() {
            global $woocommerce;

            // Default Value
            $columns = 4;

            // Product List
            if ( is_product_category() ) {
                $columns = 4;
            }

            //Related Products
            if ( is_product() ) {
                $columns = 2;
            }

            //Cross Sells
            if ( is_checkout() ) {
                $columns = 4;
            }

            return $columns;
        }

        /*********************************************************************/

        public static function save_product_price_options( $post_id ) {
            $display_options_fields = self::get_product_price_options_field();
            self::save_field_options( $display_options_fields, $post_id );
        }

        /*********************************************************************/

        public static function display_product_price_options() {
            $groupfilters = self::get_product_group_filters();

            echo '<div id="'. self::ID.'_options_tab" class="panel woocommerce_options_panel">';
            echo "<div class=\"options_group pricing " . $groupfilters . " paywhatyouwant\">";
            $display_options_fields = self::get_product_price_options_field();
            self::display_field_options( $display_options_fields );
            echo "</div>";
            echo "</div>";
        }

        /*********************************************************************/

        public static function display_field_options( $fieldoptions ) {
            global $woocommerce, $post;
            foreach ( $fieldoptions as $key => $fields ) {
                if ( isset( $fields['type'] ) ) {
                    switch( $fields['type'] ) {
                        case 'select':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_select( $fields );
                            break;
                            
                        case 'checkbox':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_checkbox( $fields );
                            break;
                            
                        case 'radio':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_radio( $fields );
                            break;
                            
                        case 'hidden':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_hidden_input( $fields );
                            break;
                            
                        case 'number':
                        case 'text':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_text_input( $fields );
                            break;
                            
                        case 'textarea':
                            if ( $result = get_post_meta( $post->ID, $fields['id'], true ) ) {
                                $fields['value'] = $result;
                            }
                            woocommerce_wp_textarea_input( $fields );
                            break;
                    }
                }
            }
        }

        /*********************************************************************/

        public static function save_field_options( $fieldoptions, $post_id  ) {
            foreach ( $fieldoptions as $key => $fields ) {
                if ( isset( $fields['type'] ) ) {
                    switch( $fields['type'] ) {
                        case 'select':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, esc_attr( $wc_data_field ) );
                            break;
                            
                        case 'checkbox':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, $wc_data_field );
                            break;
                            
                        case 'radio':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, $wc_data_field );
                            break;
                            
                        case 'hidden':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, esc_attr( $wc_data_field ) );
                            break;
                            
                        case 'number':
                        case 'text':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, esc_attr( $wc_data_field ) );
                            break;
                            
                        case 'textarea':
                            $fid = $fields[ 'id' ];
                            $wc_data_field = $_POST[ $fid ];
                            update_post_meta( $post_id, $fid, esc_html( $wc_data_field ) );
                            break;
                    }
                }
            }
        }

        /***************************************************************/

        public function remove_link($link) {
            global $post;
            //$product_enabled = get_post_meta( $post->ID, self::ID . '_enable_for_product', true );
            //if ( $product_enabled == 'yes' || $product_enabled == '1' ) return '';
            return $link;
        }

        public static function add_cart_item_custom_data_price( $cart_item, $product_id ) {
            global $woocommerce;
            $pid = 0;
            $amt = 0;
            $qty = 0;
            $vri = 0;

            if ( isset( $_POST ) ) {
                if ( isset( $_POST[self::ID . '_pid'] ) || !empty( $_POST[self::ID . '_pid'] ) ) {
                    $pid = intval( filter_var( $_POST[self::ID . '_pid'], FILTER_SANITIZE_STRING )  );
                }
                if ( isset( $_POST[self::ID . '_amt'] ) || !empty( $_POST[self::ID . '_amt'] ) ) {
                    $amt = floatval( filter_var( $_POST[self::ID . '_amt'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_qty'] ) || !empty( $_POST[self::ID . '_qty'] ) ) {
                    $qty = floatval( filter_var( $_POST[self::ID . '_qty'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_vri'] ) || !empty( $_POST[self::ID . '_vri'] ) ) {
                    $vri = intval( filter_var( $_POST[self::ID . '_vri'], FILTER_SANITIZE_STRING ) );
                }
            }

            if ( $pid == 0) $pid = $product_id;

            $min_amt = get_post_meta( $pid, self::ID . '_amount_min_price', true );
            $max_amt = get_post_meta( $pid, self::ID . '_amount_max_price', true );

            if ( isset( $min_amt ) && !empty( $min_amt ) ) {
                $min_amt = floatVal( $min_amt );
                if ( $amt < 0 ) $amt = 0;
                if ( $amt < $min_amt ) $amt = $min_amt;
            }
            if ( isset( $max_amt ) && !empty( $max_amt ) ) {
                $max_amt = floatVal( $max_amt );
                if ( $amt > $max_amt && $max_amt > 0 ) $amt = $max_amt;
            }
            $cart_item['pwyw_price'] = $amt;
            $cart_item['pwyw_quantity'] = $qty;
            $cart_item['pwyw_product_id'] = $pid;
            $cart_item['pwyw_variation_id'] = $vri;

            return $cart_item;
        }

        /***************************************************************/

        public static function get_cart_item_from_session( $cart_item, $values ) {
            $cart_item['pwyw_quantity'] = isset($values['pwyw_quantity']) ? $values['pwyw_quantity'] : 0;
            $cart_item['pwyw_product_id'] = isset($values['pwyw_product_id']) ? $values['pwyw_product_id'] : 0;
            $cart_item['pwyw_variation_id'] = isset($values['pwyw_variation_id']) ? $values['pwyw_variation_id'] : 0;
            $cart_item['pwyw_price'] = isset($values['pwyw_price']) ? $values['pwyw_price'] : 0;
            $cart_item = self::pwyw_add_cart_item( $cart_item );
            return $cart_item;
        }

        /***************************************************************/

        public static function pwyw_add_cart_item($cart_item) {
            global $woocommerce;
            $cart_item['data']->set_price($cart_item['pwyw_price']);
            return $cart_item;
        }

        /***************************************************************/

        public function add_to_cart_hook($thekey) {
            global $woocommerce;

            $pid = 0;
            $amt = 0;
            $qty = 0;
            $vri = 0;

            if ( isset( $_POST ) ) {
                if ( isset( $_POST[self::ID . '_pid'] ) || !empty( $_POST[self::ID . '_pid'] ) ) {
                    $pid = intval( filter_var( $_POST[self::ID . '_pid'], FILTER_SANITIZE_STRING )  );
                }
                if ( isset( $_POST[self::ID . '_qty'] ) || !empty( $_POST[self::ID . '_qty'] ) ) {
                    $qty = floatval( filter_var( $_POST[self::ID . '_qty'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_amt'] ) || !empty( $_POST[self::ID . '_amt'] ) ) {
                    $amt = floatval( filter_var( $_POST[self::ID . '_amt'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_vri'] ) || !empty( $_POST[self::ID . '_vri'] ) ) {
                    $vri = intval( filter_var( $_POST[self::ID . '_vri'], FILTER_SANITIZE_STRING ) );
                }
                if ( !isset( $pid ) || !isset( $amt ) || !isset( $vri ) || $amt < 0 || $vri < 0 || $pid < 1 ) {
                    // DOOP ... SHIT HAPPENS!
                } else {
                    $min_amt = get_post_meta( $pid, self::ID . '_amount_min_price', true );
                    $max_amt = get_post_meta( $pid, self::ID . '_amount_max_price', true );

                    if ( isset( $min_amt ) && !empty( $min_amt ) ) {
                        $min_amt = floatVal( $min_amt );
                        if ( $amt < 0 ) $amt = 0;
                        if ( $amt < $min_amt ) $amt = $min_amt;
                    }
                    if ( isset( $max_amt ) && !empty( $max_amt ) ) {
                        $max_amt = floatVal( $max_amt );
                        if ( $amt > $max_amt && $max_amt > 0 ) $amt = $max_amt;
                    }
                    $cart_item['pwyw_price'] = $amt;
                    $cart_item['pwyw_quantity'] = $qty;
                    $cart_item['pwyw_product_id'] = $pid;
                    $cart_item['pwyw_variation_id'] = $vri;

                    $price = $amt;
                    $product_enabled = get_post_meta( $pid, self::ID . '_enable_for_product', true );
                    $product_min = get_post_meta( $pid, self::ID . '_amount_min_price', true );
                    $product_max = get_post_meta( $pid, self::ID . '_amount_max_price', true );
                    foreach ($woocommerce->cart->get_cart() as $cart_item_key => $values) {
                        $product_enabled = get_post_meta( $values['product_id'], self::ID . '_enable_for_product', true );
                        if ( $product_enabled != '1' && $product_enabled != 'yes' ) {
                            $values['data']->set_price($price);
                            $values['pwyw_price'] = $price;
                            continue;
                        }
                        if($cart_item_key == $thekey) {
                            $values['data']->set_price($price);
                            $values['pwyw_price'] = $price;
                            $woocommerce->session->__set($thekey .'_named_price', $price);
                        }
                    }
                }
            }


            return $thekey;
        }

        /*********************************************************************/

        public static function set_custom_price( $cartandbuggy ) {
            global $woocommerce;

            $pid = 0;
            $amt = 0;
            $qty = 0;
            $vri = 0;

            if ( isset( $_POST ) ) {
                if ( isset( $_POST[self::ID . '_pid'] ) || !empty( $_POST[self::ID . '_pid'] ) ) {
                    $pid = intval( filter_var( $_POST[self::ID . '_pid'], FILTER_SANITIZE_STRING )  );
                }
                if ( isset( $_POST[self::ID . '_amt'] ) || !empty( $_POST[self::ID . '_amt'] ) ) {
                    $amt = floatval( filter_var( $_POST[self::ID . '_amt'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_qty'] ) || !empty( $_POST[self::ID . '_qty'] ) ) {
                    $qty = floatval( filter_var( $_POST[self::ID . '_qty'], FILTER_SANITIZE_STRING ) );
                }
                if ( isset( $_POST[self::ID . '_vri'] ) || !empty( $_POST[self::ID . '_vri'] ) ) {
                    $vri = intval( filter_var( $_POST[self::ID . '_vri'], FILTER_SANITIZE_STRING ) );
                }
                if ( !isset( $pid ) || !isset( $amt ) || !isset( $vri ) || $amt < 0 || $vri < 0 || $pid < 1 ) {
                    // DOOP ... SHIT HAPPENS!
                } else {
                    $min_amt = get_post_meta( $pid, self::ID . '_amount_min_price', true );
                    $max_amt = get_post_meta( $pid, self::ID . '_amount_max_price', true );

                    if ( isset( $min_amt ) && !empty( $min_amt ) ) {
                        $min_amt = floatVal( $min_amt );
                        if ( $amt < 0 ) $amt = 0;
                        if ( $amt < $min_amt ) $amt = $min_amt;
                    }
                    if ( isset( $max_amt ) && !empty( $max_amt ) ) {
                        $max_amt = floatVal( $max_amt );
                        if ( $amt > $max_amt && $max_amt > 0 ) $amt = $max_amt;
                    }
                    $cart_item['pwyw_price'] = $amt;
                    $cart_item['pwyw_quantity'] = $qty;
                    $cart_item['pwyw_product_id'] = $pid;
                    $cart_item['pwyw_variation_id'] = $vri;

                    $price = $amt;
                    $product_enabled = get_post_meta( $pid, self::ID . '_enable_for_product', true );
                    foreach ( $cartandbuggy->cart_contents as $key => $value ) {
                        if ( $product_enabled != '1' && $product_enabled != 'yes' ) {
                            continue;
                        }
                        $named_price = $woocommerce->session->__get($key .'_named_price');
                        if($named_price) {
                            $value['pwyw_price'] = $named_price;
                            $value['data']->price = $named_price;
                        }
                    }
                }
            }
        }

        /*********************************************************************/

        public function set_queue_js_css() {
            /* QUEUE JS */
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'jquery-blockui' );
            wp_enqueue_script( self::ID . '_mask_money_JS',  $this->plugin_mm_js_url, array( 'jquery' ), '3.0.2', false );
            wp_enqueue_script( self::ID . '_pwyw_JS',  $this->plugin_js_url, array( 'jquery', 'jquery-blockui' ), self::WC_PWYW_VERSION, false );
            self::set_product_js();

            /* QUEUE CSS */
            wp_enqueue_style( self::ID . '_CSS',  $this->plugin_css_url, false, self::WC_PWYW_VERSION );
            //self::set_product_css();
        }

        /*********************************************************************/

        public static function set_product_css() {
            echo "<!-- wp_head -->";
            /* CSS CODE OVERRIDE */
            $pwyw_css  = get_option(self::ID . '_custom_css');

            /* CSS */
            if ( !empty ( $pwyw_css ) ) {
                wp_add_inline_style( self::ID . '_CSS', esc_html( $pwyw_css ) );
            }
        }

        /*********************************************************************/

        public static function set_product_js() {
            global $post;

            /* JS CODE OVERRIDE */
            $pwyw_js = get_option(self::ID . '_custom_js');

            /* SCRIPT */
            echo "<script>\n";
            if ( !empty ( $pwyw_js ) ) {
                echo esc_html( $pwyw_js ) . "\n";
            }
            echo "jQuery(document).ready(function($){\n";
            echo "});\n";
            echo "</script>\n";
        }

        /*********************************************************************/

        public static function set_info_boxes() {
            /* ERROR BOX DIALOG */
            echo "<div class=\"pwyw_error_container\">";
            echo "    <div class=\"pwyw_error_box\">";
            echo "        <div class=\"pwyw_error_message\">";
            echo "        </div>";
            echo "        <button name=\"cancel\" id=\"pwyw_cancel_dialog\"> OK </button>";
            echo "    </div>";
            echo "</div>";

            /* SPINNER BOX DIALOG */
            echo "<div class=\"pwyw_spinner_container\">";
            echo "    <div class=\"pwyw_spinner_msg\">";
            echo "          PROCESSING";
            echo "    </div>";
            echo "    <div class=\"pwyw_spinner_box\">";
            echo "        <div class=\"pwyw_spinner\">";
            echo "        </div>";
            echo "    </div>";
            echo "</div>";
        }

        /*********************************************************************/

        public static function set_original_external_product_price( $price, $product ) {
            self::set_original_product_price( $price, $product );
        }

        /*********************************************************************/

        public static function set_original_variable_product_price( $price, $product ) {
            self::set_original_product_price( $price, $product );
        }

        /*********************************************************************/

        public static function set_original_product_price( $price, $product ) {
            global $post;

            $price = get_post_meta( $post->ID, '_price', true);
            if ( empty( $price ) ) {
                $price = get_post_meta( $post->ID, '_regular_price', true);
            }

            $max_val_text = get_option( self::ID . '_max_product_text_size' );
            if ( !isset( $max_val_text ) || empty( $max_val_text ) ) {
                $max_val_text = '220';
            }
            $max_product_text_size = intval( $max_val_text );
            $show_product_text = get_option(self::ID . '_show_product_text');
            $show_product_rating = get_option(self::ID . '_show_product_rating');
            $linkify = get_option(self::ID . '_linkify_text');
            $stock_override = get_option(self::ID . '_show_stocks');
            $is_virtual = get_post_meta( $post->ID, '_virtual', true );
            $show_if_stocked = get_post_meta( $post->ID,  self::ID . '_show_stock_product', true );
            $show_stock = true;
            if ( $stock_override != '1' && $stock_override != 'yes' ) {
                $show_stock = false;
            }
            if ( $show_if_stocked == 'show' ) {
                $show_stock = true;
            } else {
                $show_stock = false;
                if ( $stock_override == '1' || $stock_override == 'yes' ) {
                    $show_stock = true;
                }
            }
            if ( $is_virtual == '1' || $is_virtual == 'yes' ) {
                $show_stock = false;
            }

            $linkify_text = false;
            if ( is_shop() && ( $linkify == 'yes' || $linkify == '1' ) ) {
                $linkify_text = true;
            }

            if ($linkify_text) {
                echo '<a href="' . get_permalink( $product->ID ) . '" >';
            }

            if ( $show_product_text && is_shop() ) {
                echo "<p id='pwyw_stock_text'>";
                $post_item_content = get_the_content();
                echo substr( $post_item_content, 0, $max_product_text_size );
                if ( strlen( $post_item_content ) > $max_product_text_size ) {
                    echo "...";
                }
                echo "</p>";
            }

            if ( $show_product_rating && is_shop() ) {
                woocommerce_get_template( 'loop/rating.php' );
            }

            $itemprice = '';
            if ( $product->is_type( 'variable' ) ) {
                $itemprice = self::get_variable_price_html( '', $product );
            } else {
                $itemprice = self::get_price_html( '', $product );
            }

            $pwyw_class = '';
            if (is_shop() || $product->is_type('simple') ) {
                $pwyw_class = '_pwyw';
            }


            //$pwyw_offers_class = 'pwyw_offers';
            //if ( !is_shop() ) {
            //    $pwyw_offers_class = 'pwyw_offers_simple';
            //}
            //echo "<div class='". $pwyw_offers_class ."' itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">";
            //echo "<div itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">";
             if ( $product->is_type( 'variable' ) ||  $product->is_type( 'grouped' ) ) {
                echo "	<p class=\"price".$pwyw_class."\">" . $itemprice . "</p>";
            } else {
                echo "	<span class=\"price".$pwyw_class."\">" . $itemprice . "</span>";
            }
            echo "	<meta itemprop=\"price\" content=\"" . $product->get_price() . "\" />";
            echo "	<meta itemprop=\"priceCurrency\" content=\"" . get_woocommerce_currency() . "\" />";
            if ($show_stock) {
                //echo "<p class='price' id='pwyw_stock_amount_orig'>";
                //echo "    <link itemprop=\"availability\" href=\"http://schema.org/". $product->is_in_stock() ? 'InStock' : 'OutOfStock' . "\" />";
                //echo "</p>";
            }
            //echo "</div>";
            if ($linkify_text) {
                echo '</a>';
            }
        }

        /*********************************************************************/

        public static function set_product_price_alt( $price, $product ) {
            if ( !is_shop() ) {
                self::set_product_price( $price, $product );
            }
        }

        /*********************************************************************/

        public static function set_related_product_text( $product ) {

            $linkify = get_option(self::ID . '_linkify_text');

            $linkify_text = false;
            if ( $linkify == 'yes' || $linkify == '1' ) {
                $linkify_text = true;
            }

            if ($linkify_text) {
                echo '<a href="' . get_permalink( $product->ID ) . '" >';
            }

            $max_val_text = get_option( self::ID . '_max_product_text_size' );
            if ( !isset( $max_val_text ) || empty( $max_val_text ) ) {
                $max_val_text = '220';
            }
            $max_product_text_size = intval( $max_val_text );
            $show_product_text = get_option(self::ID . '_show_product_text');
            $show_product_rating = get_option(self::ID . '_show_product_rating');

            if ( $show_product_text ) {
                echo "<p id='pwyw_stock_text'>";
                $post_item_content = get_the_content();
                echo substr( $post_item_content, 0, $max_product_text_size );
                if ( strlen( $post_item_content ) > $max_product_text_size ) {
                    echo "...";
                }
                echo "</p>";
            }

            if ( $show_product_rating ) {
                woocommerce_get_template( 'loop/rating.php' );
            }

            if ($linkify_text) {
                echo '</a>';
            }

        }

        /*********************************************************************/

        public static function set_product_price( $price, $product ) {
            global $post, $woocommerce, $pwyw_related_product;

            /* LINKIFY TEXT WITH PRODUCT ID LINK */
            $linkify = get_option(self::ID . '_linkify_text');

            $linkify_text = false;
            if ( is_shop() && ( $linkify == 'yes' || $linkify == '1' ) ) {
                $linkify_text = true;
            }

            if ( $pwyw_related_product ) {
                self::set_related_product_text( $product );
                return;
            }

            $enable_pwyw = get_post_meta( $post->ID, self::ID . '_enable_for_product', true );
            if ( $enable_pwyw != '1' ) {
                self::set_original_product_price( $price, $product );
                return;
            }

            if ( $product->is_type('grouped') ) {
                self::set_original_variable_product_price( $price, $product );
                return;
            }

            //if ( $product->is_type('variable') ) {
            //    self::set_original_variable_product_price( $price, $product );
            //    return;
            //}

            $external_filter = get_option(self::ID . '_filters_group_external');
            if ( ( $product->is_type('external') ) && ( $external_filter != 'yes' ) ) {
                self::set_original_external_product_price( $price, $product );
                return;
            }

            /* STOCK OVERRIDE */
            $stock_override = get_option(self::ID . '_show_stocks');

            /* INPUT TYPE */
            $price_input_type = get_post_meta( $post->ID, self::ID . '_input_for_product', true );

            $money_prefix  = trim(get_option(self::ID . '_money_prefix'));
            $money_suffix  = trim(get_option(self::ID . '_money_suffix'));
            if (!empty(trim($money_prefix))) $money_prefix = $money_prefix . " ";
            if (!empty(trim($money_suffix))) $money_suffix = " " . $money_suffix;

            /* PRICE VALUES */
            $min_price = get_post_meta( $post->ID, self::ID . '_amount_min_price', true );
            $rec_price = get_post_meta( $post->ID, self::ID . '_amount_rec_price', true );
            $max_price = get_post_meta( $post->ID, self::ID . '_amount_max_price', true );

            /* SHOW VALUES */
            $show_min_price = get_post_meta( $post->ID, self::ID . '_display_min_price', true );
            $show_rec_price = get_post_meta( $post->ID, self::ID . '_display_rec_price', true );
            $show_max_price = get_post_meta( $post->ID, self::ID . '_display_max_price', true );
            $show_if_stocked = get_post_meta( $post->ID,  self::ID . '_show_stock_product', true );

            /* TEXT STRINGS TO DISPLAY */
            $pay_text = get_option( self::ID . '_pay_price' );
            $min_text = get_option( self::ID . '_minimum_price' );
            $rec_text = get_option( self::ID . '_recommended_price' );
            $max_text = get_option( self::ID . '_maximum_price' );

            /* ERROR TEXT STRINGS TO DISPLAY */
            $min_error_text = get_option( self::ID . '_minimum_price_error' );
            $max_error_text = get_option( self::ID . '_maximum_price_error' );
            $input_error_text = get_option( self::ID . '_input_price_error' );

            /* CHECK VIRTUAL */
            $is_virtual = get_post_meta( $post->ID, '_virtual', true );

            $show_stock = true;
            if ( $stock_override != '1' && $stock_override != 'yes' ) {
                $show_stock = false;
            }
            if ( $show_if_stocked == 'show' ) {
                $show_stock = true;
            } else {
                $show_stock = false;
                if ( $stock_override == '1' || $stock_override == 'yes' ) {
                    $show_stock = true;
                }
            }
            if ( $is_virtual == '1' || $is_virtual == 'yes' ) {
                $show_stock = false;
            }

            $shop_tag = '';
            if ( is_shop() ) {
                $shop_tag = '_store';
            }

            $id_tag = 'pwyw_input_pay_amount' . $shop_tag . '_' . $post->ID;
            $plus_tag = self::ID . '_plus_' . $post->ID;
            $minus_tag = self::ID . '_minus_' . $post->ID;

            /* CSS */
            echo "<style>\n";
            $text_color = '#DDD';
            $background_color = '#0D0D0D';
            $border_color = '#888 !important';
            if ( $price_input_type == '1' ) {
                echo "#" . $plus_tag . " {";
                echo "border: 1px solid;";
                //echo "border-color: ".$border_color.";";
                //echo "background-color: ".$background_color.";";
                //echo "color: ".$text_color.";";
                echo "-webkit-border-radius: 3px;";
                echo "-moz-border-radius: 3px;";
                echo "border-radius: 3px;";
                if ( is_shop() ) {
                    echo "margin: 0 0 0 1%;";
                } else {
                    echo "margin: 0;";
                }
                if ( $price_input_type == '1' ) {
                    echo "font-weight: 300;";
                    echo "font-size: 18px;";
                    if ( is_shop() ) {
                        echo "height: 36px;";
                    } else {
                        echo "height: 50px;";
                    }
                } else {
                    echo "font-weight: 900;";
                    echo "font-size: 25px;";
                    echo "height: 50px;";
                }
                echo "float: right;";
                echo "width: 7%;";
                echo "padding: 3px;";
                echo "}";

                echo "#" . $minus_tag . " {";
                echo "border: 1px solid;";
                //echo "border-color: ".$border_color.";";
                //echo "background-color: ".$background_color.";";
                //echo "color: ".$text_color.";";
                echo "-webkit-border-radius: 3px;";
                echo "-moz-border-radius: 3px;";
                echo "border-radius: 3px;";
                if ( is_shop() ) {
                    echo "margin: 0 1% 0 0;";
                } else {
                    echo "margin: 0 2% 0 0;";
                }
                if ( $price_input_type == '1' ) {
                    echo "font-weight: 300;";
                    echo "font-size: 18px;";
                    if ( is_shop() ) {
                        echo "height: 36px;";
                    } else {
                        echo "height: 50px;";
                    }
                } else {
                    echo "font-weight: 900;";
                    echo "font-size: 25px;";
                    echo "height: 50px;";
                }
                echo "float: left;";
                echo "width: 7%;";
                echo "padding: 3px;";
                echo "}";
            }

            echo "#" . $id_tag . " {";
            echo "border: 1px solid;";
            //echo "border-color: ".$border_color.";";
            //echo "background-color: ".$background_color.";";
            //echo "color: ".$text_color.";";
            echo "text-align: center;";
            echo "-webkit-border-radius: 3px;";
            echo "-moz-border-radius: 3px;";
            echo "border-radius: 3px;";
            echo "padding: 0;";
            echo "margin: 0;";
            echo "border: 1px solid;";
            echo "float: none;";
            echo "font-weight: 300;";
            if ( $price_input_type == '1' ) {
                echo "width: 82%;";
            } else {
                echo "width: 100%;";
            }
            if ( is_shop() ) {
                echo "font-size: 20px;";
                echo "height: 36px;";
                echo "margin-bottom: 36px;";
            } else {
                echo "font-size: 26px;";
                echo "height: 50px;";
            }
            echo "}";
            echo "</style>\n";

            if ($linkify_text) {
                echo '<a href="' . get_permalink( $product->ID ) . '" >';
            }

            if ( $show_min_price == 'yes' ) {
                echo "<p class='price' id='pwyw_price_style_min'>";
                echo "    <span>";
                echo "        " . $min_text . " " . get_woocommerce_currency_symbol() . $min_price;
                echo "    </span>";
                echo "</p>";
            }
            if ( $show_max_price == 'yes' ) {
                echo "<p class='price' id='pwyw_price_style_max'>";
                echo "    <span>";
                echo "        " . $max_text . " " . get_woocommerce_currency_symbol() . $max_price;
                echo "    </span>";
                echo "</p>";
            }
            if ( $show_rec_price == 'yes' ) {
                echo "<p class='price' id='pwyw_price_style_rec'>";
                echo "    <span>";
                echo "        " . $rec_text . " " . get_woocommerce_currency_symbol() . $rec_price;
                echo "    </span>";
                echo "</p>";
            }

            if ($linkify_text) {
                echo '</a>';
            }

            $price = get_post_meta( get_the_ID(), '_price', true);
            if ( empty( $price ) ) {
                $price = get_post_meta( get_the_ID(), '_regular_price', true);
            }

            $entered_price_amount = $rec_price;
            if ( empty( $entered_price_amount ) ) {
                $entered_price_amount = $price;
            }

            if ( $show_rec_price != 'yes' ) {
                $entered_price_amount = '';
            }

            if ( is_shop() && $product->is_type( 'variable' ) ) {
                echo "    <p class='price' id='pwyw_pay_amount_text'>";
                echo get_option( self::ID . '_shop_variable_pay_price' );
                echo "    </p>";
            } else {
                if ( $price_input_type == '1' ) {
                    if ($linkify_text) {
                        echo '<a href="' . get_permalink( $product->ID ) . '" >';
                    }

                    if ($show_stock) {
                        //echo "<p class='price' id='pwyw_stock_amount'>";
                        //echo "    <link itemprop=\"availability\" href=\"http://schema.org/". $product->is_in_stock() ? 'InStock' : 'OutOfStock' . "\" />";
                        //echo "</p>";
                        }

                    if ($linkify_text) {
                        echo '</a>';
                    }

                    $pwyw_offers_class = 'pwyw_offers';
                    if ( !is_shop() ) {
                        $pwyw_offers_class = 'pwyw_offers_simple';
                    }
                    echo "<div class='". $pwyw_offers_class ."' itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">";
                    //echo "<div class='pwyw_offers' itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">";
                    echo "    <p class='price' id='pwyw_pay_amount_text'>";
                    echo '        ' . $pay_text;
                    echo "    </p>";
                    echo "    <p class='price' id='pwyw_pay_amount'>";
                    echo "    <span>";
                    echo "    <input id='" . $minus_tag . "' type='button' class='pwyw_quantity_minus' value='-'>";
                    echo "    <input id='" . $id_tag . "' name='price' class='pwyw_price_amount pwyw_quantity_input' type='text' autocomplete='off' value='" . $entered_price_amount . "'/>";
                    echo "    <input id='" . $plus_tag . "' type='button' class='pwyw_quantity_plus' value='+'>";
                    echo "    </span>";
                    echo "    </p>";
                    echo "    <meta itemprop=\"price\" content=\"" . $entered_price_amount . "\" />";
                    echo "    <meta itemprop=\"priceCurrency\" content=\"" . get_woocommerce_currency() . "\" />";
                    echo "</div>";
                } else if ( $price_input_type == '0' ) {
                    if ($linkify_text) {
                        echo '<a href="' . get_permalink( $product->ID ) . '" >';
                    }

                    if ($show_stock) {
                        //echo "<p class='price' id='pwyw_stock_amount'>";
                            //echo "    <link itemprop=\"availability\" href=\"http://schema.org/". $product->is_in_stock() ? 'InStock' : 'OutOfStock' . "\" />";
                        //echo "</p>";
                    }

                    if ($linkify_text) {
                        echo '</a>';
                    }

                    $pwyw_offers_class = 'pwyw_offers';
                    if ( !is_shop() ) {
                        $pwyw_offers_class = 'pwyw_offers_simple';
                    }
                    echo "<div class='". $pwyw_offers_class ."' itemprop=\"offers\" itemscope itemtype=\"http://schema.org/Offer\">";
                    echo "    <p class='price' id='pwyw_pay_amount_text'>";
                    echo '        ' . $pay_text;
                    echo "    </p>";
                    echo "    <p class='price' id='pwyw_pay_amount'>";
                    echo "        <input id='" . $id_tag . "' name='price' data-pid='" . $post->ID . "' class='pwyw_price_amount pwyw_quantity_input' type='text' autocomplete='off' value='" . $entered_price_amount . "'/>";
                    echo "    </p>";
                    echo "    <meta itemprop=\"price\" content=\"" . $entered_price_amount . "\" />";
                    echo "    <meta itemprop=\"priceCurrency\" content=\"" . get_woocommerce_currency() . "\" />";
                    echo "</div>";
                }
            }

            /* SCRIPT */
            echo "<script>\n";
            echo "var pwyw_min_price_".$post->ID."="   . $min_price . ";\n";
            echo "var pwyw_rec_price_".$post->ID."="   . $rec_price . ";\n";
            echo "var pwyw_max_price_".$post->ID."="   . $max_price . ";\n";
            echo "var pwyw_min_error_".$post->ID."=\""   . $min_error_text . "\";\n";
            echo "var pwyw_max_error_".$post->ID."=\""   . $max_error_text . "\";\n";
            echo "var pwyw_input_error_".$post->ID."=\"" . $input_error_text . "\";\n";
            echo "jQuery(window).ready(function($){\n";
            echo "    var firstmoney='" . $rec_price . "';\n";
            echo "    $('[id^=".$id_tag."]').maskMoney({prefix:'". $money_prefix ."', suffix:'". $money_suffix ."', thousands:',', decimal:'.', symbolStay: true});\n";
            echo "    $('[id^=".$id_tag."]').maskMoney('mask', parseFloat(firstmoney));\n";
            echo "    $('#".$plus_tag."').click( function(e) {\n";
            echo "        var mm = $('#".$id_tag."').maskMoney('unmasked')[0];\n";
            echo "        var num = parseFloat(mm);\n";
            echo "        num += 1.0;";
            echo "        if (num < pwyw_min_price_".$post->ID . ") num=" . $min_price . ";";
            echo "        if (num > pwyw_max_price_".$post->ID . ") num=" . $max_price . ";";
            echo "        $('[id^=".$id_tag."]').maskMoney('mask', num);";
            echo "    });\n";
            echo "    $('#".$minus_tag."').click( function(e) {\n";
            echo "        var mm = $('#".$id_tag."').maskMoney('unmasked')[0];\n";
            echo "        var num = parseFloat(mm);\n";
            echo "        num -= 1.0;";
            echo "        if (num < pwyw_min_price_".$post->ID . ") num=" . $min_price . ";";
            echo "        if (num > pwyw_max_price_".$post->ID . ") num=" . $max_price . ";";
            echo "        $('[id^=".$id_tag."]').maskMoney('mask', num);";
            echo "    });\n";
            echo "});\n";
            echo "</script>\n";
        }

        /*********************************************************************/

        public static function get_variable_price_html( $price = '', $product ) {
            global $pwyw_related_product;

            if ( $pwyw_related_product ) return '';

            if ( $product->get_variation_regular_price( 'min' ) === false || $product->get_variation_price( 'min' ) === false || $product->get_variation_price( 'min' ) === '' || $product->get_price() === '' ) {
                $product->variable_product_sync( $product->id );
            }

            $get_price = $product->get_price();

            if ( $get_price === '' ) {
                $price = apply_filters( 'woocommerce_variable_empty_price_html', '', $product );
            } else {
                $prices = array( $product->get_variation_price( 'min', true ), $product->get_variation_price( 'max', true ) );
                $price = $prices[0] !== $prices[1] ? sprintf( _x( '%1$s<span class="amount">&ndash;</span>%2$s', 'Price range: from-to', 'woocommerce' ), wc_price( $prices[0] ), wc_price( $prices[1] ) ) : wc_price( $prices[0] );

                $prices = array( $product->get_variation_regular_price( 'min', true ), $product->get_variation_regular_price( 'max', true ) );
                sort( $prices );
                $saleprice = $prices[0] !== $prices[1] ? sprintf( _x( '%1$s<span class="amount">&ndash;</span>%2$s', 'Price range: from-to', 'woocommerce' ), wc_price( $prices[0] ), wc_price( $prices[1] ) ) : wc_price( $prices[0] );
                if ( $price !== $saleprice ) {
                    $price = apply_filters( 'woocommerce_variable_sale_price_html', $product->get_price_html_from_to( $saleprice, $price ) . $product->get_price_suffix(), $product );
                } else {
                    $price = apply_filters( 'woocommerce_variable_price_html', $price . $product->get_price_suffix(), $product );
                }
            }

            return $price;
        }

        /*********************************************************************/

        public static function get_variation_regular_price( $variation_id ) {
            global $product;
            $variable_product = new WC_Product_Variation( $variation_id );
            $tax_display_mode      = get_option( 'woocommerce_tax_display_shop' );
            $display_regular_price = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $variable_product->regular_price ) : $product->get_price_excluding_tax( 1, $variable_product->regular_price );
            return $display_regular_price;
        }

        /*********************************************************************/

        public static function get_variation_sales_price( $variation_id  ) {
            global $product;
            $variable_product = new WC_Product_Variation( $variation_id );
            $tax_display_mode      = get_option( 'woocommerce_tax_display_shop' );
            $display_sale_price    = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $variable_product->sale_price ) : $product->get_price_excluding_tax( 1, $variable_product->sale_price );
            return $display_sale_price;
        }

        /*********************************************************************/

        public static function get_variation_price_html( $price = '', $product ) {
            global $pwyw_related_product;

            if ( $pwyw_related_product ) {
                return '';
            }

            $get_price = $product->get_price();
            $tax_display_mode      = get_option( 'woocommerce_tax_display_shop' );
            $display_price         = $tax_display_mode == 'incl' ? $product->get_price_including_tax() : $product->get_price_excluding_tax();
            $display_regular_price = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_regular_price() ) : $product->get_price_excluding_tax( 1, $product->get_regular_price() );
            $display_sale_price    = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_sale_price() ) : $product->get_price_excluding_tax( 1, $product->get_sale_price() );

            if ( $get_price  > 0  ) {
                if ( $product->is_on_sale() ) {
				    $price = '<del>' . wc_price( $display_regular_price ) . '</del> <ins>' . wc_price( $display_sale_price ) . '</ins>' . $product->get_price_suffix();
				    $price = apply_filters( 'woocommerce_variation_sale_price_html', $price, $product );
                } elseif ( $get_price > 0 ) {
                    $price = wc_price( $display_price ) . $product->get_price_suffix();
                    $price = apply_filters( 'woocommerce_variation_price_html', $price, $product );
                } else {
                    $price = __( 'Free!', 'woocommerce' );
                    $price = apply_filters( 'woocommerce_variation_free_price_html', $price, $product );
                }
            } else {
                $price = apply_filters( 'woocommerce_variation_empty_price_html', '', $product );
            }

            return $price;
        }

        /*********************************************************************/

        public static function get_price_html( $price = '', $product ) {
            global $pwyw_related_product;

            if ( $pwyw_related_product ) {
                return '';
            }

            $tax_display_mode      = get_option( 'woocommerce_tax_display_shop' );
            $display_price         = $tax_display_mode == 'incl' ? $product->get_price_including_tax() : $product->get_price_excluding_tax();
            $display_regular_price = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_regular_price() ) : $product->get_price_excluding_tax( 1, $product->get_regular_price() );
            $display_sale_price    = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_sale_price() ) : $product->get_price_excluding_tax( 1, $product->get_sale_price() );

            $get_price = $product->get_price();

            if ( $get_price  > 0  ) {
                if ( $product->is_on_sale() && $product->get_regular_price() ) {
                    $price .= $product->get_price_html_from_to( $display_regular_price, $display_price ) . $product->get_price_suffix();
                    $price = apply_filters( 'woocommerce_sale_price_html', $price, $product );
                } else {
                    $price .= wc_price( $display_price ) . $product->get_price_suffix();
                    $price = apply_filters( 'woocommerce_price_html', $price, $product );
                }
            } elseif ( $get_price === '' ) {
                $price = apply_filters( 'woocommerce_empty_price_html', '', $product );
            } elseif ( $get_price == 0 ) {
                if ( $product->is_on_sale() && $product->get_regular_price() ) {
                    $price .= $product->get_price_html_from_to( $display_regular_price, __( 'Free!', 'woocommerce' ) );
                    $price = apply_filters( 'woocommerce_free_sale_price_html', $price, $product );
                } else {
                    $price = __( 'Free!', 'woocommerce' );
                    $price = apply_filters( 'woocommerce_free_price_html', $price, $product );
                }
            }

            return $price;
        }
        
        /*********************************************************************/

        public function get_related_button_ext() {
            return get_option( self::ID . '_related_button_text' );
        }
        
        /*********************************************************************/

        function add_to_cart_button() {
            global $product, $post, $pwyw_related_product;

            if ( $pwyw_related_product ) {
                echo '<a href="' . get_permalink($post->ID) . '" class="button add_to_cart_button product_type_external">' . $this->get_related_button_ext() . '</a>';
                return;
            }

            $pwyw_amount = $product->get_price();
            if ( is_shop() ) self::set_product_price ( $product->get_price(), $product );
            $external_filter = get_option(self::ID . '_filters_group_external');
            if ( ( !$product->is_type('external') ) && ( $external_filter != 'yes' ) ) {
                echo "<input type='button' data-amount='". $pwyw_amount . "' data-pid='" . $post->ID . "' onclick='pwyw_add_to_cart(" . $post->ID . ");' class='button add_to_cart_button product_type_external' value='" . $product->add_to_cart_text() . "' >";
            } else {
                if ( $product->is_type('external') ) {
                    echo '<a href="' . $product->get_product_url() . '" class="button add_to_cart_button product_type_external">' . $product->add_to_cart_text() . '</a>';
                } elseif ( $product->is_type('grouped') ) {
                    echo '<a href="' . get_permalink($post->ID) . '" class="button add_to_cart_button product_type_external">' . $product->add_to_cart_text() . '</a>';
                } elseif ( $product->is_type('variable') ) {
                    echo '<a href="' . get_permalink($post->ID) . '" class="button add_to_cart_button product_type_external">' . $product->add_to_cart_text() . '</a>';
                } else {
                    echo "<input type='button' data-amount='". $pwyw_amount . "' data-pid='" . $post->ID . "' onclick='pwyw_add_to_cart(" . $post->ID . ");' class='button add_to_cart_button product_type_external' value='" . $product->add_to_cart_text() . "' >";
                }
            }
        }

        /*********************************************************************/

        public function set_grouped_add_to_cart() {
            global $product, $post;

            $grouped_product  = $product;
            $grouped_products = $product->get_children();
            $quantites_required = false;

            $parent_product_post = $post;
            do_action( 'woocommerce_before_add_to_cart_form' );

            echo "<form class='cart paywhatyouwant' method='post' enctype='multipart/form-data'>";
	        echo '    <table cellspacing="0" class="group_table pwyw_table">';
            // START DOOP
            echo '      <tbody>';
            foreach ( $grouped_products as $product_id ) {
			    $product = get_product( $product_id );
				$post    = $product->post;
				setup_postdata( $post );
                echo '          <tr>';
                echo '              <td>';
                                    if ( $product->is_sold_individually() || ! $product->is_purchasable() ) :
                                        woocommerce_template_loop_add_to_cart();
                                    else :
                                        $quantites_required = true;
                                        woocommerce_quantity_input( array( 'input_name' => 'quantity[' . $product_id . ']', 'input_value' => '0' ) );
                                    endif;
                echo '              </td>';
                /*
                echo '              <td class="label paywhatyouwant">';
                echo '                  <label class="paywhatyouwant" for="product-' . $product_id . '">';
								        echo $product->is_visible() ? '<a href="' . get_permalink() . '">' . get_the_title() . '</a>' : get_the_title();
                echo '                  </label>';
                echo '              </td>';
                do_action ( 'woocommerce_grouped_product_list_before_price', $product );
                echo '              <td class="price">';
                                    echo $product->get_price_html();
                                    if ( ( $availability = $product->get_availability() ) && $availability['availability'] ) {
                                        echo apply_filters( 'woocommerce_stock_html', '<p class="stock ' . esc_attr( $availability['class'] ) . '">' . esc_html( $availability['availability'] ) . '</p>', $availability['availability'] );
                                    }
                echo '              </td>';
                */
                echo '              <td>';
                echo '              <table class="pwyw_inner_table">';
                echo '                  <tr>';
                echo '                      <td>';
                echo '                      <label class="paywhatyouwant" for="product-' . $product_id . '">';
                echo $product->is_visible() ? '<a href="' . get_permalink() . '">' . get_the_title() . '</a>' : get_the_title();
                echo '                      </label>';
                echo '                      </td>';
                echo '                  </tr>';
                do_action ( 'woocommerce_grouped_product_list_before_price', $product );
                echo '                  <tr>';
                echo '                      <td>';
                                    echo $product->get_price_html();
                                    if ( ( $availability = $product->get_availability() ) && $availability['availability'] ) {
                                        echo apply_filters( 'woocommerce_stock_html', '<p class="stock ' . esc_attr( $availability['class'] ) . '">' . esc_html( $availability['availability'] ) . '</p>', $availability['availability'] );
                                    }
                echo '                      </td>';
                echo '                  </tr>';
                echo '              </table>';
                echo '              </td>';
                echo '          </tr>';
            }

            // Reset to parent grouped product
            $post    = $parent_product_post;
            $product = get_product( $parent_product_post->ID );
            setup_postdata( $parent_product_post );
            echo '      </tbody>';

            // END DOOP
            echo '  </table>';

            echo '  <input id="pwyw-add-to-cart-grouped" type="hidden" name="add-to-cart-grouped" value="true" />';
            echo '  <input type="hidden" name="add-to-cart" value="' . esc_attr( $product->id ) . '" />';
            if ( $quantites_required ) {
                do_action( 'woocommerce_before_add_to_cart_button' );
                echo '  <button type="submit" class="single_add_to_cart_button button alt pwyw_price_input pwyw_grouped">' . $product->single_add_to_cart_text() . '</button>';
                do_action( 'woocommerce_after_add_to_cart_button' );
            }

            echo "</form>";

            do_action( 'woocommerce_after_add_to_cart_form' );

            self::set_product_meta();
        }

        /*********************************************************************/

        public function set_variable_add_to_cart() {
            global $woocommerce, $product, $post;

            $show_if_stocked = get_post_meta( $post->ID,  self::ID . '_show_stock_product', true );
            $stock_override = get_option(self::ID . '_show_stocks');
            $is_virtual = get_post_meta( $post->ID, '_virtual', true );

            $show_stock = true;
            if ( $stock_override != '1' && $stock_override != 'yes' ) {
                $show_stock = false;
            }
            if ( $show_if_stocked == 'show' ) {
                $show_stock = true;
                if ( $stock_override != '1' && $stock_override != 'yes' ) {
                    $show_stock = false;
                }
            } else {
                $show_stock = false;
                if ( $stock_override == '1' || $stock_override == 'yes' ) {
                    $show_stock = true;
                }
            }
            if ( $is_virtual == '1' || $is_virtual == 'yes' ) {
                $show_stock = false;
            }

            //$available_variations = array();
            $available_variations = $product->get_available_variations();

            do_action( 'woocommerce_before_add_to_cart_form' );

            echo '<form class="variations_form cart paywhatyouwant_form_variations" method="post" enctype="multipart/form-data" data-product_id="' . $post->ID . '" data-product_variations="' . esc_attr( json_encode( $available_variations ) ) . '">';
            if ( ! empty( $available_variations ) ) {
                echo "<table class=\"variations\" cellspacing=\"0\">";
    			echo "   <tbody>";
                $loop = 0;
                $attributes = $product->get_variation_attributes();
                $default_attributes = $product->get_variation_default_attributes();

                foreach ( $attributes as $name => $options ) {
                    $loop++;
                    echo '      <tr>';
                    echo '          <td class="label"><label for="' . sanitize_title($name) . '">' . wc_attribute_label( $name ) . '</label></td>';
                    echo '          <td class="value"><select class="pwyw_sku_value" id="' . esc_attr( sanitize_title( $name ) ) . '" name="attribute_' . sanitize_title( $name ) . '">';
                    //echo '          <option value="">' . __( 'Choose an option', 'woocommerce' ) . '&hellip;</option>';

                    if ( is_array( $options ) ) {
                        if ( isset( $_REQUEST[ 'attribute_' . sanitize_title( $name ) ] ) ) {
                            $selected_value = $_REQUEST[ 'attribute_' . sanitize_title( $name ) ];
                        } elseif ( isset( $selected_attributes[ sanitize_title( $name ) ] ) ) {
                            $selected_value = $selected_attributes[ sanitize_title( $name ) ];
                        } else {
                            $selected_value = '';
                        }

                        // Get terms if this is a taxonomy - ordered
                        if ( taxonomy_exists( $name ) ) {
                            $orderby = wc_attribute_orderby( $name );

                            switch ( $orderby ) {
                                case 'name' :
                                    $args = array( 'orderby' => 'name', 'hide_empty' => false, 'menu_order' => false );
                                break;

                                case 'id' :
                                    $args = array( 'orderby' => 'id', 'order' => 'ASC', 'menu_order' => false, 'hide_empty' => false );
                                    break;

                                case 'menu_order' :
                                    $args = array( 'menu_order' => 'ASC', 'hide_empty' => false );
                                    break;
                            }

                            $terms = get_terms( $name, $args );
                            $loop1 = 0;
                            foreach ( $terms as $term ) {
                                $data_pid = $available_variations[$loop1]['variation_id'];
                                $data_sku = $available_variations[$loop1]['sku'];
                                $enable_pwyw = get_post_meta( $post->ID, self::ID . '_enable_for_product', true );
                                $data_sales = '';
                                $data_price = '';
                                $data_d_sales = '';
                                $data_d_price = '';
                                if ( $enable_pwyw != '1' && $enable_pwyw != 'yes' ) {
                                    $data_sales = round( self::get_variation_sales_price( $data_pid ), 2 );
                                    $data_price = round( self::get_variation_regular_price( $data_pid ), 2 );
                                    $data_d_sales = wc_price( $data_sales );
                                    $data_d_price = wc_price( $data_price );
                                }
                                $data_attribute = $available_variations[$loop1]['attributes']['attribute_'.$name];
                                $data_img = $available_variations[$loop1]['image_src'];
                                $data_min = $available_variations[$loop1]['min_qty'];
                                $data_max = $available_variations[$loop1]['max_qty'];
                                //$data_img_alt = $available_variations[$loop1]['image_src'];
                                //$data_img_title = $available_variations[$loop1]['image_src'];
                                $selected_option = '';
                                if ( $default_attributes[$name] == $data_attribute ) {
                                    $selected_option = ' selected="selected"';
                                }
                                if ( !isset($data_max) || empty($data_max)) {
                                    $data_max = '5777';
                                }

                                if ( ! in_array( $term->slug, $options ) ) {
                                    continue;
                                }

                                $attributes_list  = '';
                                $attributes_list .= ' data-min="' . esc_attr( $data_min ) . '" ';
                                $attributes_list .= ' data-max="' . esc_attr( $data_max ) . '" ';
                                $attributes_list .= ' data-attrib="' . esc_attr( $name ) . '" ';
                                $attributes_list .= ' data-item="' . esc_attr( $loop1 ) . '" ';
                                $attributes_list .= ' data-product_id="' . esc_attr( $data_pid ) . '" ';
                                $attributes_list .= ' data-sku="' . esc_attr( $data_sku ) . '" ';
                                $attributes_list .= ' data-sales="' . esc_attr( $data_sales ) . '" ';
                                $attributes_list .= ' data-price="' . esc_attr( $data_price ) . '" ';
                                $attributes_list .= ' data-d_sales="' . esc_attr( $data_d_sales ) . '" ';
                                $attributes_list .= ' data-d_price="' . esc_attr( $data_d_price ) . '" ';
                                $attributes_list .= ' data-image="' . esc_attr( $data_img ) . '" ';
                                $attributes_list .= ' ' . esc_attr( $selected_option ) . ' ';

                                echo '                <option ' . $attributes_list . ' value="' . esc_attr( $term->slug ) . '" ' . selected( sanitize_title( $selected_value ), sanitize_title( $term->slug ), false ) . '>' . apply_filters( 'woocommerce_variation_option_name', $term->name ) . '</option>';
                                $loop1++;
                            }
                        } else {
                            foreach ( $options as $option ) {
                                echo '                <option value="' . esc_attr( sanitize_title( $option ) ) . '" ' . selected( sanitize_title( $selected_value ), sanitize_title( $option ), false ) . '>' . esc_html( apply_filters( 'woocommerce_variation_option_name', $option ) ) . '</option>';
                            }
                        }
                    }
                    echo "              </select>";

                    if ( sizeof( $attributes ) == $loop ) {
                        echo '          <a class="reset_variations" href="#reset">' . __( 'Clear selection', 'woocommerce' ) . '</a>';
                    }

                    echo '          </td>';
                    echo '      </tr>';
                }

                echo '  </tbody>';
                echo '</table>';

		        do_action( 'woocommerce_before_add_to_cart_button' );

                echo '<div class="single_variation_wrap">';
                do_action( 'woocommerce_before_single_variation' );

                if ($show_stock) {
                    //echo '  <span class="instock_variation">';
                    echo "    <link class='instock_class' itemprop=\"availability\" href=\"http://schema.org/". $product->is_in_stock() ? 'InStock' : 'OutOfStock' . "\" />";
                    //echo '  </span>';
                }

                $enable_pwyw = get_post_meta( $post->ID, self::ID . '_enable_for_product', true );
                $price_style = '';
                if ( $enable_pwyw == '1' || $enable_pwyw == 'yes' ) {
                    $price_style = ' style="display:none;" ';

                    /* PRICE VALUES */
                    $min_price = get_post_meta( $post->ID, self::ID . '_amount_min_price', true );
                    $max_price = get_post_meta( $post->ID, self::ID . '_amount_max_price', true );
                    echo '<input type="hidden" class="input_pwyw_price" value="true" />';
                    echo '<div class="single_variation">';
                    echo '  <span class="price">';
                    echo '  <del class="span_price">REGULAR</del>';
                    echo '  <ins class="span_price">SALES</ins>';
                    echo '  <span class="span_price">';
                    echo "Min: " . wc_price($min_price) . " -  Max: " . wc_price($max_price);
                    echo '  </span>';
                    echo '  </span>';
                    echo '</div>';

                } else {
                    echo '  <div class="single_variation"' . $price_style .'>';
                    echo '  <span class="price">';
                    echo '  <del class="span_price">REGULAR</del>';
                    echo '  <ins class="span_price">SALES</ins>';
                    echo '  <span class="span_price">PRICE</span>';
                    echo '  </span>';
                    echo '  </div>';
                }

                echo '  <div class="variations_button">';
                $defaults = array(
                    'input_name'    => 'quantity',
                    'input_value'   => '1',
                    'max_value'     => '5555',
                    'min_value'     => '1',
                    'step'          => '1'
                );
				woocommerce_quantity_input( $defaults, $product, true );
                echo '      <button onclick="pwyw_add_variation_to_cart('. esc_attr( $post->ID ) .')" type="button" class="single_add_to_cart_button button alt pwyw_price_input">' . $product->single_add_to_cart_text() . '</button>';
                echo '  </div>';

                echo '  <input type="hidden" name="add-to-cart" value="' . $product->id . '" />';
                echo '  <input type="hidden" name="product_id" value="' . esc_attr( $post->ID ) . '" />';
                echo '  <input type="hidden" name="variation_id" value="" />';

                do_action( 'woocommerce_after_single_variation' );
                echo '</div>';

		        do_action( 'woocommerce_after_add_to_cart_button' );
            } else {
		        echo '<p class="stock out-of-stock">' . _e( 'This product is currently out of stock and unavailable.', 'woocommerce' ) . '</p>';
            }
            echo '</form>';

            do_action( 'woocommerce_after_add_to_cart_form' );

            self::set_product_meta();
        }

        /*********************************************************************/

        public function set_simple_add_to_cart() {
            global $woocommerce, $product, $post;

            if ( ! $product->is_purchasable() ) {
                return;
            }

            $availability = $product->get_availability();

            /* CHECK IF DISPLAY IN_STOCK */
            $show_if_stocked = get_post_meta( $post->ID,  self::ID . '_show_stock_product', true );
            $stock_override = get_option(self::ID . '_show_stocks');
            $is_virtual = get_post_meta( $post->ID, '_virtual', true );
            $show_stock = true;
            if ( $stock_override != '1' && $stock_override != 'yes' ) {
                $show_stock = false;
            }
            if ( $show_if_stocked == 'show' ) {
                $show_stock = true;
            } else {
                $show_stock = false;
                if ( $stock_override == '1' || $stock_override == 'yes' ) {
                    $show_stock = true;
                }
            }
            if ( $is_virtual == '1' || $is_virtual == 'yes' ) {
                $show_stock = false;
            }

            if ( $show_stock ) {
                if ( $availability['availability'] ) {
                    echo apply_filters( 'woocommerce_stock_html', '<p class="stock paywhatyouwant ' . esc_attr( $availability['class'] ) . '">' . esc_html( $availability['availability'] ) . '</p>', $availability['availability'] );
                }
            }

            if ( $product->is_in_stock() ) :
                do_action( 'woocommerce_before_add_to_cart_form' ); ?>

            <form class="cart paywhatyouwant" method="post" enctype='multipart/form-data'>
            <?php do_action( 'woocommerce_before_add_to_cart_button' ); ?>
            <?php
            if ( ! $product->is_sold_individually() ) {
                $itemproduct = array(
                    'min_value' => apply_filters( 'woocommerce_quantity_input_min', 1, $product ),
	 				'max_value' => apply_filters( 'woocommerce_quantity_input_max', $product->backorders_allowed() ? '' : $product->get_stock_quantity(), $product )
	 			);
                woocommerce_quantity_input( $itemproduct );
            }
            ?>
            <input type="hidden" name="add-to-cart" value="<?php echo esc_attr( $product->id ); ?>" />
            <?php
                $pwyw_amount = self::get_single_item_price();
                /*<button type="submit" class="single_add_to_cart_button button alt"><?php echo $product->single_add_to_cart_text(); ?></button> */
                echo '<input type="button" data-amount="' . $pwyw_amount . '" data-pid="' . $post->ID . '" onclick="pwyw_add_to_cart(' . $post->ID . ');" class="single_add_to_cart_button button alt pwyw_price_input" value="' . $product->add_to_cart_text() . '" >';
                //echo '<input type="button" id="single_add_to_cart_button" data-pid="' . $post->ID . '" class="single_add_to_cart_button button alt" value="' . $product->add_to_cart_text() . '" >';
                do_action( 'woocommerce_after_add_to_cart_button' ); ?>
            </form>

            <?php do_action( 'woocommerce_after_add_to_cart_form' );
            endif;

            self::set_product_meta();
        }

        /*********************************************************************/

        public static function get_item_price_from_product( $product ) {
            $tax_display_mode      = get_option( 'woocommerce_tax_display_shop' );
            $display_regular_price = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_regular_price() ) : $product->get_price_excluding_tax( 1, $product->get_regular_price() );
            $display_sale_price    = $tax_display_mode == 'incl' ? $product->get_price_including_tax( 1, $product->get_sale_price() ) : $product->get_price_excluding_tax( 1, $product->get_sale_price() );
            if ($product->is_on_sale()) {
                return round( floatval( $display_sale_price ), 2);
            }
            return round( floatval( $display_regular_price ), 2);
        }

        /*********************************************************************/

        public static function get_single_item_price() {
            global $product;
            return self::get_item_price_from_product( $product );
        }

        /*********************************************************************/

        public function add_to_cart_handler() {
            global $woocommerce;

            $amt = 0;
            $qty = 0;
            $pid = 0;
            $vri = 0;
            $submitted = false;

            if ( isset( $_POST ) ) {
                if ( isset( $_POST['submit'] ) || isset( $_POST['SUBMIT'] ) ) {
                    $submitted = true;
                }
                if ( isset( $_POST[self::ID . '_pid'] ) || !empty( $_POST[self::ID . '_pid'] ) ) {
                    $pid = intval( filter_var( $_POST[self::ID . '_pid'], FILTER_SANITIZE_STRING )  );
                } else {
                    $pid = 0;
                }
                if ( isset( $_POST[self::ID . '_qty'] ) || !empty( $_POST[self::ID . '_qty'] ) ) {
                    $qty = intval( filter_var( $_POST[self::ID . '_qty'], FILTER_SANITIZE_STRING ) );
                } else {
                    $qty = 0;
                }
                if ( isset( $_POST[self::ID . '_amt'] ) || !empty( $_POST[self::ID . '_amt'] ) ) {
                    $amt = floatval( filter_var( $_POST[self::ID . '_amt'], FILTER_SANITIZE_STRING ) );
                } else {
                    $amt = 0;
                }
                if ( isset( $_POST[self::ID . '_vri'] ) || !empty( $_POST[self::ID . '_vri'] ) ) {
                    $vri = intval( filter_var( $_POST[self::ID . '_vri'], FILTER_SANITIZE_STRING ) );
                } else {
                    $vri = 0;
                }
            } else {
                echo "ERROR: invalid or no data.";
                die();
                return;
            }

            if ( !isset( $pid ) || !isset( $qty ) || !isset( $amt ) || !isset( $vri ) ) {
                echo "ERROR: invalid data.";
                die();
                return;
            }

            if ($qty < 1) {
                echo "ERROR: invalid quantity value.";
                die();
                return;
            }

            if ($amt < 0) {
                echo "ERROR: invalid amount value.";
                die();
                return;
            }

            if ($pid < 1) {
                echo "ERROR: invalid item value.";
                die();
                return;
            }

            if ($vri < 0) {
                echo "ERROR: invalid variation item value.";
                die();
                return;
            }

            $p_post = get_post( $pid );
            $p_product = get_product( $pid );
            $add_to_cart_status = false;
            $error_message = 'ERROR: Unknown cart error.';

            if ( $vri > 0 ) {
			    $variation = get_product( $vri );
                $add_to_cart_status = $woocommerce->cart->add_to_cart ( $pid, $qty, $vri, $variation );
                if ( !$add_to_cart_status ) {
                    $error_message = 'Cannot add item to cart.';
                }
            } else {
                $add_to_cart_status = $woocommerce->cart->add_to_cart( $pid, $qty );
                if ( !$add_to_cart_status ) {
                    $error_message = 'Cannot add item to cart.';
                }
            }

            $redirect_cart = get_option( 'woocommerce_cart_redirect_after_add' );
            $return_to_cart = false;

            if ($add_to_cart_status) {
                if ($redirect_cart == '1' || $redirect_cart == 'yes') {
                    $return_to_cart = true;
                }
                $return_id = $pid;
                if ( $return_to_cart ) {
                    $return_id = get_option( 'woocommerce_cart_page_id' );
                } else if ( is_shop() ) {
                    $return_id = get_option('woocommerce_shop_page_id');
                }
                echo get_permalink( $return_id );
            } else {
                echo "ERROR: " . $error_message;
			}

            die();
            return;
        }

        /*********************************************************************/

        public function add_to_cart_handler_nopriv() {
            $do_cart = true;
            if ( $do_cart ) {
                $this->add_to_cart_handler();
            }
        }

        /*********************************************************************/

        public static function set_product_meta() {
            global $post, $product;
            $cat_count = sizeof( get_the_terms( $post->ID, 'product_cat' ) );
            $tag_count = sizeof( get_the_terms( $post->ID, 'product_tag' ) );
            echo '<div class="product_meta_pwyw">';
            do_action( 'woocommerce_product_meta_start' );
            if ( wc_product_sku_enabled() && ( $product->get_sku() || $product->is_type( 'variable' ) ) ) :
              $sku = $product->get_sku();
              if ( $sku ) __( 'N/A', 'woocommerce' );
		      echo '<span class="sku_wrapper">' . _e( 'SKU: ', 'woocommerce' ) . '<span class="sku" itemprop="sku">' . $sku . '</span>.</span><br>';
            endif;
            echo $product->get_categories( ', ', '<span class="posted_in">' . _n( 'Category:', 'Categories:', $cat_count, 'woocommerce' ) . ' ', '.</span>' ) . '<br>';
            echo $product->get_tags( ', ', '<span class="tagged_as">' . _n( 'Tag:', 'Tags:', $tag_count, 'woocommerce' ) . ' ', '.</span>' );
            do_action( 'woocommerce_product_meta_end' );
            echo "</div>";
        }

        /*********************************************************************/

        public static function do_action_output_related_products( $initargs ) {
            global $product, $woocommerce_loop, $pwyw_related_product;

            $show_related = get_option( self::ID . '_show_related_products' );
            if ( $show_related == 'yes' || $show_related == '1' ) {
                $pwyw_related_product = true;
                $posts_per_page = 2;
                $columns = $woocommerce_loop['columns'];
                if (!isset($columns)) $columns = 2;
                $orderby = 'rand';
                $related = $product->get_related( $posts_per_page );
                if ( sizeof( $related ) == 0 ) return;
                $args = apply_filters( 'woocommerce_related_products_args', array(
                    'post_type'            => 'product',
                    'pwyw_type'            => 'related',
                    'ignore_sticky_posts'  => 1,
                    'no_found_rows'        => 1,
                    'posts_per_page'       => $posts_per_page,
                    'orderby'              => $orderby,
                    'post__in'             => $related,
                    'post__not_in'         => array( $product->id )
                ) );

                $products = new WP_Query( $args );
                $woocommerce_loop['columns'] = $columns;
                if ( $products->have_posts() ) :
                    echo '<div class="related products">';
                    echo '    <h2>'. __( 'Related Products', 'woocomerce' ) .'</h2>';
                    woocommerce_product_loop_start();
                    echo "    <ul>";
                    while ( $products->have_posts() ) : $products->the_post();
                        wc_get_template_part( 'content', 'product' );
                    endwhile;
                    echo "    </ul>";
                    woocommerce_product_loop_end();
                    echo '</div>';
                endif;
                wp_reset_postdata();
                $pwyw_related_product = false;
            }
        }

        /*********************************************************************/

    }

    /* LAUNCH PLUGIN */
    
    $paywhatyouwant = new  WC_Pay_What_You_Want();

    /* CLASS END */

    }
}

?>