<?php

use PrestaShop\PrestaShop\Adapter\Presenter\Order\OrderPresenter;

class CoinremitterInvoiceModuleFrontController extends ModuleFrontController {


	public function setMedia()
	{
		parent::setMedia();
	}

	public function initContent() {
		parent::initContent(); 

		$order_id = Tools::getValue('order_id');
		if(!isset($order_id) || $order_id == ''){
			Tools::redirect('index.php?controller=404');
		}
		$order = New Order((int)$order_id);

		if (Validate::isLoadedObject($order) && $order->id_customer == $this->context->customer->id && $order->module == "coinremitter") {

			if(Tools::getValue('action') !== null && Tools::getValue('action')=="success"){
				if($this->orderConfirm($order_id)){
					$cart_id = $order->id_cart;
					$key = $order->secure_key;
					$module = $order->module;
					$sql = "SELECT id_module FROM "._DB_PREFIX_."module WHERE name='$module'";
					$module_id = Db::getInstance()->executes($sql)[0]['id_module'];
					Tools::redirect('order-confirmation?id_cart='.$cart_id.'&id_module='.$module_id.'&id_order='.$order_id.'&key='.$key);
				} else {
					$this->redirect_after = '404';
          		$this->redirect();
				}

			} else if(Tools::getValue('action') !== null && Tools::getValue('action')=="cancel"){
				if($this->orderCancel($order_id)){
					Tools::redirect(_PS_BASE_URL_ . __PS_BASE_URI__ ."index.php?controller=cancel&fc=module&module=coinremitter&order_id=".$order_id);
				} else {
					$this->redirect_after = '404';
          		$this->redirect();
				}

			} else {

				$sql = "SELECT co.address, co.address_qrcode, co.crp_amount, cp.expire_on, cp.coin, co.payment_status FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id= '".$order_id."'";
            $orderData = Db::getInstance()->executes($sql)[0];
            
            if($orderData['payment_status'] != "Pending" && $orderData['payment_status'] != "Under Paid"){
               Tools::redirect('index.php?controller=404');
            }

				$currency = New Currency((int)$order->id_currency);
	         $products = [];
	         foreach ($order->getProductsDetail() as $product) {
					$data = array();
					$data['name'] = $product['product_name'];
					$data['image'] = $this->getImage($product['id_product']);
					$data['quantity'] = $product['product_quantity'];
					if(isset($order->carrier_tax_rate) && $order->carrier_tax_rate!=0)
						$data['price'] = number_format($product['unit_price_tax_excl'],2,'.','');
					else
						$data['price'] = number_format($product['unit_price_tax_incl'],2,'.','');
					array_push($products, $data);
				}

				$subTotal = $order->total_products;
				$grandTotal = $order->total_paid;
            $shipping = $order->total_shipping;

	         $this->order_to_display = (new OrderPresenter())->present($order);
	         $this->context->smarty->assign([
	            'order' => $this->order_to_display,
	            'order_id' => $order_id,
				'reference' => $order->reference,
	         	'subTotal' => number_format($subTotal,2,'.',''),
	         	'grandTotal' => number_format($grandTotal,2,'.',''),
	         	'shippingAmount' => number_format($shipping,2,'.',''),
	         	'taxAmount' => number_format($grandTotal - $subTotal - $shipping,2,'.',''),
	         	'orderCurrencySymbol' => $currency->symbol,
	         	'expire_on' => $orderData['expire_on'],
	         	'order_address' => $orderData['address'],
	         	'qr_code' => $orderData['address_qrcode'],
	         	'totalAmount' => $orderData['crp_amount'],
	         	'coin' => $orderData['coin'],
	         	'carrier_tax_rate' => (int)$order->carrier_tax_rate,
	         	'img_path' => __PS_BASE_URI__."modules/".$this->module->name."/img/",
	         	'products' => $products,
	         ]);
	         
	         $this->registerStyleSheet(
					"front-controller-coinremitter-css",
					"modules/".$this->module->name."/views/templates/front/css/cr_plugin.css",
					[
						'priority' => 999
					]
				);
				$this->registerJavascript(
					"front-controller-coinremitter-js",
					"modules/".$this->module->name."/views/templates/front/js/paymenthistory.js",
					[
						'priority' => 999
					]
				);
	      	$this->setTemplate('module:coinremitter/views/templates/front/invoice.tpl');
	      }
      } else {
       	$this->redirect_after = '404';
       	$this->redirect();
      }
	}

	public function getImage($id_product){
		$sql="SELECT id_image FROM "._DB_PREFIX_."image WHERE cover = 1 AND id_product = $id_product";
		$imageData = Db::getInstance()->executes($sql)[0];
		$idImage = $imageData['id_image'];
		$imgPath = _PS_BASE_URL_ . __PS_BASE_URI__.'img/p/';
		for ($i = 0; $i < strlen($idImage); $i++) {
  			$imgPath .= $idImage[$i] . '/';
		}
		$imgPath .= $idImage . '.jpg';
		return $imgPath;
	}

	public function orderCancel($order_id){

		date_default_timezone_set("UTC");
		$db = Db::getInstance();
		$order = new Order($order_id);
		$sql = "SELECT co.*, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id= '".$order_id."'";
		$coinremitter_order_detail = $db->executes($sql);
		if(!empty($coinremitter_order_detail)){
			$coinremitter_order = $coinremitter_order_detail[0];
			if(isset($coinremitter_order['payment_status']) && ($coinremitter_order['payment_status'] == "Pending" || $coinremitter_order['payment_status'] == "Expired")){
				$date_diff = 0;
            if($coinremitter_order['expire_on'] != ""){
            	$current = strtotime(date("Y-m-d H:i:s"));
               $expire_on = strtotime($coinremitter_order['expire_on']);
               $date_diff = $expire_on - $current;
               if($date_diff < 1){
               	$order_current_status = $order->current_state;
               	if($order_current_status == 10){
                     $sql = "UPDATE coinremitter_order SET payment_status='Expired' WHERE order_id= '".$order_id."'";
                     $db->Execute($sql);
                     $sql = "UPDATE coinremitter_payment SET status='Expired' WHERE order_id= '".$order_id."'";
                     $db->Execute($sql);
      					$ostate = 6; //canceled order status
					      $history = new OrderHistory();
					      $history->id_order = $order->id;
					      $history->id_employee = (int) 1;
					      $history->id_order_state = (int) $ostate;
					      $history->save();
					      $history->changeIdOrderState($ostate, $order , true); 
					      $history->sendEmail($order);
					      return true;
					   }
					}
				}
			}
		}
		return false;
	}

	public function orderConfirm($order_id){
		$db = Db::getInstance();
		$order = new Order($order_id);
		$sql = "SELECT * FROM coinremitter_order WHERE order_id='".$order_id."'";
		$result_invoice = $db->executes($sql);
		if(!empty($result_invoice) && ($result_invoice[0]['payment_status'] == "Paid" || $result_invoice[0]['payment_status'] == "Over Paid")){
	      $accepted_payment_status = Configuration::get('coinremitter_order_status');
			$order_current_status = $order->current_state;
			if($accepted_payment_status != $order_current_status){
				$history = new OrderHistory();
				$history->id_order = $order->id;
				$history->id_employee = (int) 1;
				$history->id_order_state = $accepted_payment_status;
				$history->save();
				$history->changeIdOrderState($accepted_payment_status, $order , true); 
				$history->sendEmail($order);
			}
			return true;
	   }
	   return false;
	}
}