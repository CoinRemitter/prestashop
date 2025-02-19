<?php

use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter;

class CoinremitterInvoiceModuleFrontController extends ModuleFrontController
{

	public function setMedia()
	{
		parent::setMedia();
	}

	public function initContent()
	{

		parent::initContent();

		$order_id = Tools::getValue('order_id');
		if (!isset($order_id) || $order_id == '') {
			Tools::redirect('index.php?controller=404');
		}
		$order = new Order((int)$order_id);

		if (!Validate::isLoadedObject($order) || $order->id_customer != $this->context->customer->id || $order->module != "coinremitter") {
			$this->redirect_after = '404';
			$this->redirect();
		}
		if (Tools::getValue('action') !== null && Tools::getValue('action') == "success") {
			if ($this->orderConfirm($order_id)) {
				$cart_id = $order->id_cart;
				$key = $order->secure_key;
				$module = $order->module;
				$sql = "SELECT id_module FROM " . _DB_PREFIX_ . "module WHERE name='$module'";
				$module_id = Db::getInstance()->executes($sql)[0]['id_module'];
				Tools::redirect('order-confirmation?id_cart=' . $cart_id . '&id_module=' . $module_id . '&id_order=' . $order_id . '&key=' . $key);
			} else {
				$this->redirect_after = '404';
				$this->redirect();
			}
		} else if (Tools::getValue('action') !== null && Tools::getValue('action') == "cancel") {
			if ($this->orderCancel($order_id)) {
				Tools::redirect(_PS_BASE_URL_ . __PS_BASE_URI__ . "index.php?controller=cancel&fc=module&module=coinremitter&order_id=" . $order_id);
			} else {
				$this->redirect_after = '404';
				$this->redirect();
			}
		} else {

			$sql = "SELECT * FROM coinremitter_orders WHERE order_id= '" . $order_id . "'";
			$orderData = Db::getInstance()->executes($sql)[0];

			if ($orderData['order_status'] != ORDER_STATUS_CODE["pending"] && $orderData['order_status'] != ORDER_STATUS_CODE['under_paid']) {
				Tools::redirect('index.php?controller=404');
			}

			$currency = new Currency((int)$order->id_currency);
			$products = [];
			$link = Context::getContext()->link;
			foreach ($order->getProductsDetail() as $product) {
				$data = array();
				$data['name'] = $product['product_name'];
				$getProduct = new Product($product['id_product'], false, Context::getContext()->language->id);
				$id_image = '';
				$images = Image::getImages(Context::getContext()->language->id, $product['id_product']);
				foreach ($images as $img) {
					$id_image = $img['id_image'];
					break;
				}
				$data['product_link'] = $link->getProductLink($product['id_product']);
				$image_url = $link->getImageLink($getProduct->link_rewrite, $id_image, 'home_default');

				$data['image'] = $image_url;
				$data['quantity'] = $product['product_quantity'];
				if (isset($order->carrier_tax_rate) && $order->carrier_tax_rate != 0)
					$data['price'] = number_format($product['unit_price_tax_excl'], 2, '.', '');
				else
					$data['price'] = number_format($product['unit_price_tax_incl'], 2, '.', '');
				array_push($products, $data);
			}

			$subTotal = $order->total_products;
			$grandTotal = $order->total_paid;
			$shipping = $order->total_shipping;

			$pending_crypto_amount = number_format($orderData['crypto_amount'] - $orderData['paid_crypto_amount'], 8, '.', '');
			$this->order_to_display = (new OrderPresenter())->present($order);
			$this->context->smarty->assign([
				'order' => $this->order_to_display,
				'order_id' => $order_id,
				'reference' => $order->reference,
				'subTotal' => number_format($subTotal, 2, '.', ''),
				'grandTotal' => number_format($grandTotal, 2, '.', ''),
				'shippingAmount' => number_format($shipping, 2, '.', ''),
				'taxAmount' => number_format($grandTotal - $subTotal - $shipping, 2, '.', ''),
				'orderCurrencySymbol' => $currency->symbol,
				'expire_on' => $orderData['expiry_date'],
				'order_address' => $orderData['payment_address'],
				'qr_code' => $orderData['qr_code'],
				'total_crypto_amount' => $orderData['crypto_amount'],
				'total_paid_crypto_amount' => $orderData['paid_crypto_amount'],
				'total_pending_crypto_amount' => $pending_crypto_amount,
				'coin' => $orderData['coin_symbol'],
				'carrier_tax_rate' => (int)$order->carrier_tax_rate,
				'img_path' => __PS_BASE_URI__ . "modules/" . $this->module->name . "/img/",
				'products' => $products,
			]);
			// echo "<pre>";
			// print_r($order->getProductsDetail());
			// die;
			$this->registerStyleSheet(
				"front-controller-coinremitter-css",
				"modules/" . $this->module->name . "/views/templates/front/css/cr_plugin.css",
				[
					'priority' => 999
				]
			);
			$this->registerJavascript(
				"front-controller-coinremitter-js",
				"modules/" . $this->module->name . "/views/templates/front/js/paymenthistory.js",
				[
					'priority' => 999
				]
			);
			$this->setTemplate('module:coinremitter/views/templates/front/invoice.tpl');
		}
	}

	public function getImage($id_product)
	{
		$sql = "SELECT id_image FROM " . _DB_PREFIX_ . "image WHERE cover = 1 AND id_product = $id_product";
		$imageData = Db::getInstance()->executes($sql)[0];
		$idImage = $imageData['id_image'];
		$imgPath = _PS_BASE_URL_ . __PS_BASE_URI__ . 'img/p/';
		for ($i = 0; $i < strlen($idImage); $i++) {
			$imgPath .= $idImage[$i] . '/';
		}
		$imgPath .= $idImage . '.jpg';
		return $imgPath;
	}

	public function orderCancel($order_id)
	{

		date_default_timezone_set("UTC");
		$db = Db::getInstance();
		$order = new Order($order_id);
		$sql = "SELECT * FROM coinremitter_orders WHERE order_id= '" . $order_id . "'";
		$coinremitterOrder = $db->executes($sql);
		
		if (empty($coinremitterOrder)) {
			return false;
		}
		$coinremitterOrder = $coinremitterOrder[0];
		$coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
		if(!empty($coinremitterOrder['transaction_meta']) && $coinremitterOrder['order_status'] != ORDER_STATUS_CODE["pending"] && $coinremitterOrder['order_status'] != ORDER_STATUS_CODE['expired']){
			return false;
		}
		if(!isset($coinremitterOrder['expiry_date']) || $coinremitterOrder['expiry_date'] == ''){
			return false;
		}
		$date_diff = 0;
		$current = strtotime(date("Y-m-d H:i:s"));
		$expire_on = strtotime($coinremitterOrder['expiry_date']);
		$date_diff = $expire_on - $current;
		if($date_diff > 0){
			return false;
		}
		
		if($coinremitterOrder['order_status'] == ORDER_STATUS_CODE['pending']){
			$sql = "UPDATE `coinremitter_orders` SET `order_status`=".ORDER_STATUS_CODE['expired']." WHERE `order_id`= '" . $order_id . "'";
			
			$db->Execute($sql);
			$ostate = 6; //canceled order status
			$history = new OrderHistory();
			$history->id_order = $order->id;
			$history->id_employee = (int) 1;
			$history->id_order_state = (int) $ostate;
			$history->save();
			$history->changeIdOrderState($ostate, $order, true);
			$history->sendEmail($order);
		}
		return true;
	}

	public function orderConfirm($order_id)
	{

		$db = Db::getInstance();
		$sql = "SELECT * FROM coinremitter_orders WHERE order_id='" . $order_id . "'";
		$order = $db->executes($sql);
		if (empty($order)) {
			return false;
		}
		$order = $order[0];
		if ($order['order_status'] != ORDER_STATUS_CODE["paid"] && $order['order_status'] != ORDER_STATUS_CODE['over_paid']) {
			return false;
		}
		$order = new Order($order_id);
		$accepted_payment_status = Configuration::get('coinremitter_order_status');
		$order_current_status = $order->current_state;
		if ($accepted_payment_status != $order_current_status) {
			$history = new OrderHistory();
			$history->id_order = $order->id;
			$history->id_employee = (int) 1;
			$history->id_order_state = $accepted_payment_status;
			$history->save();
			$history->changeIdOrderState($accepted_payment_status, $order, true);
			$history->sendEmail($order);
		}
		return true;
	}
}
