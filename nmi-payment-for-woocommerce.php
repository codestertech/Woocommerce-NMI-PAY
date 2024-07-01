<?php
/*
Plugin Name: NMI Payment for WooCommerce
Description: NMI payment gateway for WooCommerce.
Version: 1.0.1
Author: codestertech
Author URI: http://codestertech.com
License: GPLv2 or later
Text Domain: nmipayment
*/

defined('ABSPATH') or exit;

// Make sure WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    return;
}

/**
 * Add the gateway to WC Available Gateways
 */
function wc_nim_add_to_gateways($gateways)
{
    $gateways[] = 'WC_NIM_Gateway';
    return $gateways;
}
add_filter('woocommerce_payment_gateways', 'wc_nim_add_to_gateways');

/**
 * NIM Payment Gateway
 */
add_action('plugins_loaded', 'wc_nim_gateway_init', 11);
function wc_nim_gateway_init()
{
    class WC_NIM_Gateway extends WC_Payment_Gateway
    {
        public function __construct()
        {
            $this->id = 'nim_payment_wc';
            $this->icon = apply_filters('woocommerce_nim_icon', 'https://mms.businesswire.com/media/20181016005244/en/684544/2/NMI-Logo-Purple_Text_thumbnail.jpg');
            $this->has_fields = true;
            $this->method_title = __('NMI Payment', 'nmipayment');
            $this->method_description = __('NMI payment gateway', 'nmipayment');

            // Load the settings.
            $this->init_form_fields();
            $this->init_settings();

            // Define user set variables
            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->instructions = $this->get_option('instructions', $this->description);
            $this->gateway_js_key = $this->get_option('gateway_js_key');
            $this->collect_js_key = $this->get_option('collect_js_key');
            $this->security_key = $this->get_option('security_key');
            $this->license_key = $this->get_option('license_key');

            // Actions
            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_thankyou_' . $this->id, array($this, 'thankyou_page'));
            add_action('woocommerce_email_before_order_table', array($this, 'email_instructions'), 10, 3);
            add_action('wp_head', array($this, 'manage_page_content'));
            add_action('wp_footer', array($this, 'minicart_checkout_refresh_script'));

            // Register Deactivation Hook
            register_deactivation_hook(__FILE__, array($this, 'api_keys_counter_remove'));
        }

        /**
         * Deactivate Hook
         */
        function api_keys_counter_remove()
        {
            delete_option("gateway_js_key");
            delete_option("collect_js_key");
            delete_option("security_key");
            delete_option("license_key");
        }

        /**
         * Output styles and scripts for gateway
         */
        function manage_page_content()
        {
            $order_total = WC()->cart->get_total('raw');
            ?>
            <style>.woocommerce-checkout.processing .blockUI.blockOverlay { display: block !important; }</style>
            <link rel="stylesheet" href="<?php echo site_url(); ?>/wp-content/plugins/nmi-payment-for-woocommerce/asset/jquery_ui/jquery-ui.css">
            <script src="<?php echo site_url(); ?>/wp-content/plugins/nmi-payment-for-woocommerce/asset/jquery_ui/jquery-ui.js"></script>
            <script src="https://secure.nmi.com/js/v1/Gateway.js"></script>
            <script src="https://secure.networkmerchants.com/token/Collect.js"
                    data-tokenization-key="<?php echo $this->get_option('collect_js_key'); ?>"
                    data-variant="inline"
                    data-country="GB"
                    data-price="<?php echo $order_total; ?>"
                    data-currency="GBP"
                    data-field-apple-pay-selector=".apple-pay-button"
                    data-field-apple-pay-total-label="Total"
                    data-field-apple-pay-type="buy"
                    data-field-apple-pay-style-button-style="white-outline"
                    data-field-apple-pay-style-height="30px"
                    data-field-apple-pay-style-border-radius="4px"></script>
            <?php
        }

        /**
         * Manage refresh script for checkout
         */
        function minicart_checkout_refresh_script()
        {
            if (is_checkout() && !is_wc_endpoint_url()) {
                ?>
                <script type="text/javascript">
                    (function ($) {
                        $(document.body).on('change', 'input[name="payment_method"],input[name^="shipping_method"]', function () {
                            $(document.body).trigger('update_checkout').trigger('wc_fragment_refresh');
                        });

                        $('body').on('updated_checkout', function () {
                            if ($("input[name='payment_method']:checked").val() == 'nim_payment_wc') {
                                $("#payButton").show();
                                $('#payment #place_order').hide();
                            }
                        });
                    })(jQuery);
                </script>
                <?php
            }
        }

        /**
         * Initialize Gateway Settings Form Fields
         */
        public function init_form_fields()
        {
            $this->form_fields = apply_filters('nim_pay_wc_fields', array(
                'enabled' => array(
                    'title' => __('Enable/Disable', 'nmipayment'),
                    'type' => 'checkbox',
                    'label' => __('Enable NMI Payment', 'nmipayment'),
                    'default' => 'no'
                ),
                'title' => array(
                    'title' => __('Title', 'nmipayment'),
                    'type' => 'text',
                    'description' => __('This controls the title for the payment method the customer sees during checkout.', 'nmipayment'),
                    'default' => __('NMI Payment', 'nmipayment'),
                    'desc_tip' => true,
                ),
                'description' => array(
                    'title' => __('Description', 'nmipayment'),
                    'type' => 'textarea',
                    'description' => __('Payment method description that the customer will see on your checkout.', 'nmipayment'),
                    'default' => __('Pay with NMI Payment Gateway', 'nmipayment'),
                    'desc_tip' => true,
                ),
                'instructions' => array(
                    'title' => __('Instructions', 'nmipayment'),
                    'type' => 'textarea',
                    'description' => __('Instructions that will be added to the thank you page and emails.', 'nmipayment'),
                    'default' => '',
                    'desc_tip' => true,
                ),
                'gateway_js_key' => array(
                    'title' => 'Gateway.js KEY',
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ),
                'collect_js_key' => array(
                    'title' => 'Collect.js Key',
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ),
                'security_key' => array(
                    'title' => 'Security Key',
                    'type' => 'text',
                    'desc_tip' => false,
                    'default' => ''
                ),
                'license_key' => array(
                    'title' => __('License Key', 'nmipayment'),
                    'type' => 'text',
                    'description' => __('Enter your License Key.', 'nmipayment'),
                    'default' => ''
                ),
            ));
        }

        /**
         * Validate Credit Card Fields
         */
        public function validate_fields()
        {
        }

        /**
         * Payment fields for checkout page
         */
        public function payment_fields()
        {
            $l_license_key = $this->get_option('license_key');
            // validate key
            $result = $this->validate_license_key($l_license_key);

            if (isset($l_license_key) && !empty($l_license_key) && isset($result) && $result->status == 'true') {
                ?>
                <style>.error { border: 1px solid; margin: 10px 0px; padding: 15px 10px 15px 50px; background-repeat: no-repeat; background-position: 10px center; color: #D8000C; background-color: #FFBABA; background-image: url('https://i.imgur.com/GnyDvKN.png'); }</style>
                <div class="error" style="display: none;"></div>
                <div id="applepaybutton" class="apple-pay-button"></div>
                <br>
                <label>Credit Card Number</label><div id="ccnumber"></div>
                <label>CC EXP</label><div id="ccexp"></div>
                <label>CVV</label><div id="cvv"></div>
                <br>
                <img id="loader" style="display: none;" src="<?php echo site_url(); ?>/wp-content/plugins/nmi-payment-for-woocommerce/asset/loader.gif"/>
                <button style="margin-top: 2%; width: 100%; display: none;" class="button alt" id="payButton">Place Order <img src="<?php echo site_url(); ?>/wp-content/plugins/nmi-payment-for-woocommerce/secure-payment.png" style="margin-left: 6px; margin-top: 3px;"/></button>
                <script>
                    jQuery(function ($) {
                        var opts = {
                            form: '#checkout',
                            debug: false
                        };
                        var GatewayJS = new gateway(js(opts));

                        // Get details from forms
                        $('#applepaybutton').on('click', function () {
                            console.log('script');
                            if (!$(this).hasClass('pending')) {
                                if (!($(this).hasClass('loading'))) {
                                    $(this).addClass('loading');
                                    $(this).addClass('applepaybutton');
                                    // Clear the text boxes
                                    $('#ccnumber').html("");
                                    $('#ccexp').html("");
                                    $('#cvv').html("");
                                    $('#loader').html("");
                                }
                                $('#ccnumber').html($(this).val());
                                $('#ccexp').html("");
                                $('#cvv').html("");
                                $('#loader').html("");
                                var opts = {
                                    form: '#checkout',
                                    debug: false
                                };
                                var CollectJS = new collect (opts);
                            }

                            // Now collect the data
                            $('#applepaybutton').html($('#payButton').html());
                        });
                    });
                </script>
                <?php
            }
            $a='javascript:cs.applepaybutton.onload';
        }

        /**
         * Process the payment and return the result
         */
        public function process_payment($order_id)
        {
            $order = wc_get_order($order_id);
            $order->update_status('completed', __('Payment completed successfully.', 'nmipayment'));

            return array(
                'result' => 'success',
                'redirect' => $this->get_return_url($order)
            );
        }

        /**
         * Output for the order received page.
         */
        public function thankyou_page($order_id)
        {
            if ($this->instructions) {
                echo wpautop(wptexturize($this->instructions));
            }
        }

        /**
         * Add content to the WC emails.
         */
        public function email_instructions($order, $sent_to_admin, $plain_text = false)
        {
            if ($this->instructions && !$sent_to_admin && 'nim_payment_wc' === $order->get_payment_method()) {
                echo wpautop(wptexturize($this->instructions)) . PHP_EOL;
            }
        }
    } // End of class WC_NIM_Gateway
} // End of function wc_nim_gateway_init()
