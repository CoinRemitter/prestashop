<?php

class CoinremitterRedirectModuleFrontController extends ModuleFrontController
{

   public function postProcess()
   {
      $this->title = $this->module->l('My module title');
      $cart = $this->context->cart;
      if (
         $cart->id_customer == 0
         || $cart->id_address_delivery == 0
         || $cart->id_address_invoice == 0
         || !$this->module->active
         || empty($_REQUEST['coinremitter_select_coin'])
      ) {
         Tools::redirect('index.php?controller=order&step=1');
      }

      $authorized = false;

      foreach (Module::getPaymentModules() as $module) {
         if ($module['name'] == 'coinremitter') {
            $authorized = true;
            break;
         }
      }

      if (!$authorized) {
         $msg = 'This payment method is not available';
         $error_link = $this->context->link->getModuleLink('coinremitter', 'error', array('msg' => $msg));
         Tools::redirect($error_link);
      }
      $mailVars = array();
      $customerId = $cart->id_customer;
      $customer = new Customer($customerId);
      
      if (!Validate::isLoadedObject($customer)) {
         Tools::redirect('index.php?controller=order&step=1');
      }

      $wallet_coin_name = $_REQUEST['coinremitter_select_coin'];
      $currency = $this->context->currency;

      $cartRules = $cart->getCartRules();
      
      $couponAmount = 0;
      foreach ($cartRules as $cartRule) {
         if (isset($cartRule['value_real']) && $cartRule['value_real'] > 0) {
            $couponAmount += $cartRule['value_real'];
         }
      }
      
      $productTotal = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
      $otherTotal = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);
      $total = (string)$cart->getOrderTotal(true, Cart::BOTH);

      $invoice = new CR_Invoice();
      $wallet = $invoice->CR_getWallet($wallet_coin_name);

      if (!$wallet) {
         $msg = 'Payment gateway error.';
         $error_link = $this->context->link->getModuleLink('coinremitter', 'error', array('msg' => $msg));
         Tools::redirect($error_link);
      }

      $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, null, $mailVars, (int) $currency->id, false, $customer->secure_key);
      $orderId = $this->module->currentOrder;
      date_default_timezone_set("UTC");
      $invoice_expiry = Configuration::get('coinremitter_invoice_expiry');
      $invoice_exchange_rate = $wallet['exchange_rate_multiplier'];
      if ($invoice_expiry == 0 || $invoice_expiry == null || $invoice_expiry == '') {
         $expire_on = '';
      } else {
         $newtimestamp = strtotime(date('Y-m-d H:i:s') . ' + ' . $invoice_expiry . ' minute');
         $expire_on = date('Y-m-d H:i:s', $newtimestamp);
      }
      if ($invoice_exchange_rate == 0 && $invoice_exchange_rate == '') {
         $invoice_exchange_rate = 1;
      } else {
         $invoice_exchange_rate = $invoice_exchange_rate;
      }
      $productTotal = $productTotal - $couponAmount;
      $orderTotal = ($productTotal * $invoice_exchange_rate) + $otherTotal;
      $orderTotal = number_format($orderTotal, 2, ".", "");
      $defaultCurrencyOrderTotal = Tools::convertPrice($orderTotal,Context::getContext()->currency,false);
      $defaultCurrencyOrderTotal = number_format($defaultCurrencyOrderTotal, 2, ".", "");
      
      //if total amount is less than minimum value than invoice doesn't create
      if ($defaultCurrencyOrderTotal < $wallet['minimum_invoice_amount']) {
         $msg = 'Opps! Somethig went wrong!';
         $error_link = $this->context->link->getModuleLink('coinremitter', 'error', array('msg' => $msg));
         Tools::redirect($error_link);
      }
      
      $addressParam['api_key'] = $wallet['api_key'];
      $addressParam['password'] = $invoice->decrypt($wallet['password']);
      $address_data = $invoice->CR_createAddress($addressParam);
      if (!$address_data['success']) {
         $this->delete_order($orderId);
         $msg = 'Opps! Somethig went wrong!';
         if (isset($address_data['msg'])) {
            $msg = $address_data['msg'];
         }
         $error_link = $this->context->link->getModuleLink('coinremitter', 'error', array('msg' => 'Opps! Somethig went wrong!'));
         Tools::redirect($error_link);
      }
      $inv_data = $address_data['data'];
      $fiatSymbol = $currency->iso_code;
      $conversionParam = array(
         'fiat' => $fiatSymbol,
         'fiat_amount' => $orderTotal,
         'crypto' => $wallet['coin_symbol'],
      );

      $currency_data = $invoice->CR_getFiatToCryptoRate($conversionParam);

      if (!$currency_data['success']) {
         $this->delete_order($orderId);
         $msg = 'Opps! Somethig went wrong!';
         if (isset($currency_data['msg'])) {
            $msg = $currency_data['msg'];
         }
         $error_link = $this->context->link->getModuleLink('coinremitter', 'error', array('msg' => 'Opps! Somethig went wrong!'));
         Tools::redirect($error_link);
      }

      $curr_data = $currency_data['data'][0];
      $address = $inv_data['address'];
      $qr_code = $inv_data['qr_code'];
      $crypto_amount = $curr_data['price'];
      $coin_symbol = $wallet['coin_symbol'];
      $coin_name = $wallet['coin_name'];
      $now = date('Y-m-d H:i:s');
      $db = Db::getInstance();
      $or_sql = "INSERT INTO `coinremitter_orders` (`order_id`, `user_id`, `coin_symbol`, `coin_name`, `crypto_amount`, `fiat_symbol`, `fiat_amount`,`payment_address`,`qr_code`,`expiry_date`,`created_at`,`updated_at`) VALUES ('$orderId', '$customerId','$coin_symbol','$coin_name','$crypto_amount','$fiatSymbol','$orderTotal', '$address', '$qr_code','$expire_on','$now','$now')";
      $db->Execute($or_sql);

      Tools::redirect(_PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=invoice&fc=module&module=coinremitter&order_id=' . $orderId);
   }

   protected function delete_order($orderId)
   {
      $order = new Order($orderId);
      $order->delete();
   }
}
