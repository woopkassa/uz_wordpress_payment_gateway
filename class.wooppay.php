<?php

/**
 * The MIT License (MIT)
 *
 * Copyright (c) 2012-2021 Wooppay
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * @copyright   Copyright (c) 2012-2021 Wooppay
 * @author      Artyom Narmagambetov <anarmagambetov@wooppay.com>
 * @version     2.0
 */
class WC_Gateway_Wooppay extends WC_Payment_Gateway
{
	public $debug = 'yes';

	public function __construct()
	{
		$this->id = 'wooppay';
		$this->icon = apply_filters('woocommerce_wooppay_icon',
			plugins_url() . '/wooppay-2.0/assets/images/wooppay.png');
		$this->has_fields = false;
		$this->method_title = __('WOOPPAY', 'Wooppay');
		$this->init_form_fields();
		$this->init_settings();
		$this->title = $this->settings['title'];
		$this->description = $this->settings['description'];
		$this->instructions = $this->get_option('instructions');
		$this->enable_for_methods = $this->get_option('enable_for_methods', array());

		add_action('woocommerce_update_options_payment_gateways_' . $this->id, array(&$this, 'process_admin_options'));
		add_action('woocommerce_api_wc_gateway_wooppay', array($this, 'check_response'));
	}

	/**
	 * Web hook handler which triggers after success payment
	 */
	public function check_response()
	{
		if (isset($_GET['id_order']) && isset($_GET['key'])) {
			$order = wc_get_order((int)$_GET['id_order']);
			if ($order && $order->key_is_valid($_GET['key'])) {
				include_once('WooppayRestClient.php');
				$client = new WooppayRestClient($this->get_option('api_url'));
				if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
					$operationData = $client->getOperationData($_POST['operationId']);
					if ($operationData[0]->status == 14 || $operationData[0]->status == 19) {
						$order->update_status('completed', __('Payment completed.', 'woocommerce'));
						die('{"data":1}');
					}
				}
			} else {
				$this->add_log('Error order key: ' . print_r($_REQUEST, true));
			}
		} else {
			$this->add_log('Error call back: ' . print_r($_REQUEST, true));
		}

		die('{"data":1}');
	}

	/**
	 * Admin Panel Options.
	 */
	public function admin_options()
	{
		?>
        <h3><?php _e('Wooppay', 'wooppay'); ?></h3>
        <table class="form-table">
			<?php $this->generate_settings_html(); ?>
        </table> <?php
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields()
	{
		global $woocommerce;

		$shipping_methods = array();

		if (is_admin()) {
			foreach ($woocommerce->shipping->load_shipping_methods() as $method) {
				$shipping_methods[$method->id] = $method->get_title();
			}
		}
		$this->form_fields = array(
			'enabled' => array(
				'title' => __('Enable/Disable', 'wooppay'),
				'type' => 'checkbox',
				'label' => __('Enable Wooppay Gateway', 'wooppay'),
				'default' => 'no'
			),
			'title' => array(
				'title' => __('Title', 'wooppay'),
				'type' => 'text',
				'description' => __('This controls the title which the user sees during checkout.', 'wooppay'),
				'desc_tip' => true,
				'default' => __('Wooppay Gateway', 'wooppay')
			),
			'description' => array(
				'title' => __('Description', 'wooppay'),
				'type' => 'textarea',
				'description' => __('This controls the description which the user sees during checkout.', 'wooppay'),
				'default' => __('Оплата с помощью кредитной карты или кошелька Wooppay', 'wooppay')
			),
			'instructions' => array(
				'title' => __('Instructions', 'wooppay'),
				'type' => 'textarea',
				'description' => __('Instructions that will be added to the thank you page.', 'wooppay'),
				'default' => __('Введите все необходимые данные и вас перенаправит на портал Wooppay для оплаты',
					'wooppay')
			),
			'api_details' => array(
				'title' => __('API Credentials', 'wooppay'),
				'type' => 'title',
			),
			'api_url' => array(
				'title' => __('API URL', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'api_username' => array(
				'title' => __('API Username', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'api_password' => array(
				'title' => __('API Password', 'wooppay'),
				'type' => 'text',
				'description' => __('Get your API credentials from Wooppay.', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'order_prefix' => array(
				'title' => __('Order prefix', 'wooppay'),
				'type' => 'text',
				'description' => __('Order prefix', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
			'service_name' => array(
				'title' => __('Service name', 'wooppay'),
				'type' => 'text',
				'description' => __('Service name', 'wooppay'),
				'default' => '',
				'desc_tip' => true,
				'placeholder' => __('Optional', 'wooppay')
			),
		);

	}

	/**
	 * Creates invoice after checkout
	 * @param $order_id
	 * @return array
	 */
	function process_payment($order_id)
	{
		include_once('WooppayRestClient.php');
		global $woocommerce;
		$order = new WC_Order($order_id);
		try {
			$client = new WooppayRestClient($this->get_option('api_url'));
			if ($client->login($this->get_option('api_username'), $this->get_option('api_password'))) {
				$requestUrl = WC()->api_request_url('WC_Gateway_Wooppay') . '?id_order=' . $order_id . '&key=' . $order->get_order_key();
				$backUrl = $this->get_return_url($order);
				$orderPrefix = $this->get_option('order_prefix');
				$serviceName = $this->get_option('service_name');
				$invoice = $client->createInvoice($orderPrefix . '_' . $order->id, $backUrl, $requestUrl,
					$order->get_total(), $serviceName, 'Оплата заказа №' . $order->id, '', '',
					$order->get_billing_email(), $order->get_billing_phone());
				$woocommerce->cart->empty_cart();
				$order->update_status('pending', __('Payment Pending.', 'woocommerce'));
				return array(
					'result' => 'success',
					'redirect' => $invoice->operation_url
				);
			}
		} catch (Exception $e) {
			$this->add_log($e->getMessage());
			wc_add_notice(__($e->getMessage(), 'woocommerce'), 'error');
		}
	}

	function thankyou()
	{
		echo $this->instructions != '' ? wpautop($this->instructions) : '';
	}

	function add_log($message)
	{
		if ($this->debug == 'yes') {
			if (empty($this->log))
				$this->log = new WC_Logger();
			$this->log->add('Wooppay', $message);
		}
	}
}
