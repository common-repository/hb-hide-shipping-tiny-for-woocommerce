<?php
/*
 * Plugin Name: WC 隱藏運送方式
 * Plugin URI: https://piglet.me/hb-hide-shipping-tiny-for-woocommerce
 * Description: A HB Hide Shipping Tiny For Woocommerce
 * Version: 0.2.0
 * Author: heiblack
 * Author URI: https://piglet.me
 * License:  GPL 3.0
 * Domain Path: /languages
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
*/

class hb_hide_shipping_tiny_admin
{
    public function __construct()
    {
        if (!defined('ABSPATH')) {
            http_response_code(404);
            die();
        }
        if (!function_exists('plugin_dir_url')) {
            return;
        }
        if (!function_exists('is_plugin_active')) {
            require_once(ABSPATH . 'wp-admin/includes/plugin.php');
        }
        if (!is_plugin_active('woocommerce/woocommerce.php')) {
            return;
        }
        $this->init();
    }

    public function init()
    {
        $this->HB_package_rates();
        $this->HB_checkout_fields();
        $this->HB_product_options_variable();
        $this->HB_product_options_shipping();
        $this->HB_add_hide_shipping_setting();
    }
    private function  HB_product_options_variable(){
        add_action('woocommerce_product_after_variable_attributes', function ($loop, $variation_data, $variation) {
            echo '<div class="Woo_HideShipping_Volume" style="border-top: 1px solid #eee;border-bottom: 1px solid #eee;padding-bottom: 10px">';
            woocommerce_wp_text_input(
                array(
                    'id'                => "_hb_hide_shipping_tiny_Volume{$loop}",
                    'name'              => "_hb_hide_shipping_tiny_Volume[{$loop}]",
                    'value'             => get_post_meta( $variation->ID, '_hb_hide_shipping_tiny_Volume', true ),
                    'label'             => __( 'Volume', 'hb-hide-shipping-tiny-for-woocommerce' ),
                    'type'              => 'number',
                    'desc_tip'          => true,
                    'description'       => __( 'Product volume', 'hb-hide-shipping-tiny-for-woocommerce' ),
                    'custom_attributes' => array('min' => '0')
                )
            );
            echo '</div>';
        },10,3);

        add_action( 'woocommerce_save_product_variation', function ( $id, $loop ){
            $text_field = sanitize_text_field($_POST['_hb_hide_shipping_tiny_Volume'][ $loop ]);
            update_post_meta( $id, '_hb_hide_shipping_tiny_Volume', esc_attr( $text_field ));
        }, 10, 2 );
    }
    private function  HB_product_options_shipping(){
        add_action('woocommerce_product_options_shipping', function () {
            echo '<div class="Woo_HideShipping_Volume" style="border-top: 1px solid #eee;border-bottom: 1px solid #eee;padding-bottom: 10px">';
            woocommerce_wp_text_input(
                array(
                    'id'                => '_hb_hide_shipping_tiny_Volume_all',
                    'label'             => __( 'Volume', 'hb-hide-shipping-tiny-for-woocommerce' ),
                    'type'              => 'number',
                    'desc_tip'          => 'true',
                    'description'       => __( 'Product volume', 'hb-hide-shipping-tiny-for-woocommerce' ),
                    'custom_attributes' => array('min' => '0')
                )
            );
            echo '&nbsp;&nbsp;&nbsp;&nbsp;';
            echo esc_html_e( '' );
            echo '</div>';
        });
        add_action( 'woocommerce_process_product_meta', function ( $id){
            $woocommerce_checkbox = isset( $_POST['_hb_hide_shipping_tiny_Volume_all'] ) ? sanitize_text_field($_POST['_hb_hide_shipping_tiny_Volume_all']) : '';
            update_post_meta( $id, '_hb_hide_shipping_tiny_Volume_all', esc_attr($woocommerce_checkbox) );
        }, 10, 1 );
    }
    private function  HB_package_rates()
    {
        add_filter('woocommerce_package_rates', function ($rates, $package) {
            $all_weight         = 0;
            $all_volume         = 0;
            $cart_total         = WC()->cart->cart_contents_total;
            foreach ($package['contents'] as $value) {
                $product_id     = $value['product_id'];
                $variation_id   = $value['variation_id'];
                if (empty($variation_id)) {
                    $volume     = get_post_meta($product_id, '_hb_hide_shipping_tiny_Volume_all');
                } else {
                    $volume     = get_post_meta($variation_id, '_hb_hide_shipping_tiny_Volume');
                    if (!$volume[0]) {
                        $volume     = get_post_meta($product_id, '_hb_hide_shipping_tiny_Volume_all');
                    }
                }
                $quantity = $value['quantity'];
                $weight     = ((float)$value['data']->weight) * ((int)$quantity);
                if (isset($volume[0])) {
                    $volume     = ((float)$volume[0]) * ((int)$quantity);
                }
                $all_volume += $volume;
                $all_weight += $weight;
            }

            foreach ($rates as $rate_key => $rate) {
                $custom_value = get_option('woocommerce_' . $rate->method_id . '_' . $rate->instance_id . '_settings');

                if (isset($custom_value) && $custom_value['hb_hide_shipping_tiny_checkbox'] == 'yes') {
                    if ($custom_value['hb_hide_shipping_tiny_weightLimit'] > 0 && $custom_value['hb_hide_shipping_tiny_weightLimit'] < $all_weight) {
                        unset($rates[$rate->id]);
                    }
                    if ($custom_value['hb_hide_shipping_tiny_volumeLimit'] > 0 && $custom_value['hb_hide_shipping_tiny_volumeLimit'] < $all_volume) {
                        unset($rates[$rate->id]);
                    }
                    if ($custom_value['hb_hide_shipping_tiny_subtotal'] > 0 && $custom_value['hb_hide_shipping_tiny_subtotal'] > $cart_total) {
                        unset($rates[$rate->id]);
                    }
                }
            }
            return $rates;
        }, 10, 2);
    }
    private function  HB_checkout_fields()
    {
        add_filter('woocommerce_checkout_fields', function ($fields) {
            $chosen_shipping_methods = WC()->session->get('chosen_shipping_methods');
            $chosen_shipping_methods = str_replace(':', '_', $chosen_shipping_methods[0]);
            $option = get_option("woocommerce_" . $chosen_shipping_methods . "_settings");
            if ($option && $option['hb_No_need_to_enter_billing_address'] == 'yes') {
                foreach ($option['hb_No_need_to_enter_billing_address_select'] as $value) {
                    $fields['billing'][$value]['required'] = false;
                }
                return $fields;
            }
            return $fields;
        });
    }
    private function HB_add_hide_shipping_setting()
    {
        add_action('woocommerce_init', function () {
            $shipping_methods = WC()->shipping->get_shipping_methods();
            if (isset($shipping_methods)) {
                foreach ($shipping_methods as $shipping_method) {
                    add_filter('woocommerce_shipping_instance_form_fields_' . $shipping_method->id, function ($settings) {


                        $settings['hb_hide_shipping_tiny_checkbox'] = [
                            'title' => '啟用',
                            'type' => 'checkbox',
                            'description' => '',
                            'default' => 'no'

                        ];
                        $settings['hb_hide_shipping_tiny_weightLimit'] = [
                            'title' => '重量大於',
                            'type' => 'number',
                            'description' => '',
                            'default' => '0',
                            'custom_attributes' => array('min' => '0')
                        ];
                        $settings['hb_hide_shipping_tiny_volumeLimit'] = [
                            'title' => '體積大於',
                            'type' => 'number',
                            'description' => '',
                            'default' => '0',
                            'custom_attributes' => array('min' => '0')
                        ];
                        $settings['hb_hide_shipping_tiny_subtotal'] = [
                            'title' => '價錢低於',
                            'type' => 'number',
                            'default' => '0',
                            'custom_attributes' => array('min' => '0')
                        ];
                        $settings['hb_No_need_to_enter_billing_address'] = [
                            'title' => '跳過輸入帳單地址(測試)',
                            'type' => 'checkbox',
                            'default' => 'no',
                        ];
                        $settings['hb_No_need_to_enter_billing_address_select'] = [
                            'title' => '',
                            'id' => 'hb_No_need_to_enter_billing_address_select',
                            'class'   => 'wc-enhanced-select',
                            'type' => 'multiselect',
                            'options' => [
                                'billing_first_name' => 'billing_first_name',
                                'billing_last_name' => 'billing_last_name',
                                'billing_address_1' => 'billing_address_1',
                                'billing_address_2' => 'billing_address_2',
                                'billing_city' => 'billing_city',
                                'billing_postcode' => 'billing_postcode',
                                'billing_country' => 'billing_country',
                                'billing_state' => 'billing_state',
                                'billing_email' => 'billing_email',
                                'billing_phone' => 'billing_phone',
                                'billing_company' => 'billing_company',
                            ],
                        ];
                        return $settings;
                    });
                }
            }
        });
        add_action('admin_enqueue_scripts', function ($hook) {
            if ($hook === 'woocommerce_page_wc-settings' && $_GET['tab'] == "shipping" && $_GET['zone_id']) {
                wp_enqueue_script('hb-hide-shipping-tiny', plugin_dir_url(__FILE__) . 'assets/hb-hide-shipping-tiny.js', array('jquery'), '1.0', true);
            }
        });
    }
}

new hb_hide_shipping_tiny_admin();
