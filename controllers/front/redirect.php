<?php 

class CoinremitterRedirectModuleFrontController extends ModuleFrontController{

   public function postProcess(){
        
      $cart = $this->context->cart;
      $car_array = json_decode(json_encode($cart),true); 
      
      if ($cart->id_customer == 0
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
         $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$msg));
         Tools::redirect($error_link);
      }
      $mailVars = array();
      $customer = new Customer($cart->id_customer);

      if (!Validate::isLoadedObject($customer)) {
         Tools::redirect('index.php?controller=order&step=1');
      }

      $wallet_coin_name = $_REQUEST['coinremitter_select_coin'];
      $currency = $this->context->currency;
      $total = (string)$cart->getOrderTotal(true, Cart::BOTH);

      $invoice = new CR_Invoice();
      $wallet = $invoice->CR_getWallet($wallet_coin_name);
      
      if(!$wallet){
         $msg = 'Payment gateway error.';
         $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$msg));
         Tools::redirect($error_link);
      }

      $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, null, $mailVars, (int) $currency->id, false, $customer->secure_key);
      $orderId = $this->module->currentOrder;
      date_default_timezone_set("UTC");
      $invoice_expiry =Configuration::get('coinremitter_invoice_expiry');
      $invoice_exchange_rate = $wallet['exchange_rate_multiplier'];

      if($invoice_expiry == 0 || $invoice_expiry == null || $invoice_expiry == ''){
         $expire_on ='';
      } else {
         $newtimestamp = strtotime(date('Y-m-d H:i:s').' + '.$invoice_expiry.' minute');
         $expire_on = date('Y-m-d H:i:s', $newtimestamp);
      }
      if($invoice_exchange_rate == 0 && $invoice_exchange_rate == ''){
         $invoice_exchange_rate = 1;
      }else{
         $invoice_exchange_rate = $invoice_exchange_rate;
      }
      $total = $total*$invoice_exchange_rate;

      $add_param['api_key'] = $wallet['api_key'];
      $add_param['password'] = $invoice->decrypt($wallet['password']);
      $add_param['coin'] = $wallet['coin'];
      //$add_param['label'] = ""

      try {
         //code...
         $address_data = $invoice->CR_createAddress($add_param);
      } catch (\Throwable $th) {
         //throw $th;
      }
      if(!isset($address_data['flag']) || $address_data['flag'] != 1){
         $this->delete_order($orderId);
         $msg = 'Opps! Somethig went wrong!';
         if(isset($address_data['msg'])){
            $msg = $address_data['msg'];
         }
         $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$msg));
         Tools::redirect($error_link);
      }
      $inv_data = $address_data['data'];
      $add_param['fiat_symbol'] = $currency->iso_code;
      $add_param['fiat_amount'] = $total;

      $currency_data = $invoice->CR_getFiatToCrypToRate($add_param);

      if(!isset($currency_data['flag']) || $currency_data['flag'] != 1){
         $this->delete_order($orderId);
         $msg = 'Opps! Somethig went wrong!';
         if(isset($currency_data['msg'])){
               $msg = $currency_data['msg'];
         }
         $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$msg));
         Tools::redirect($error_link);
      }

      //if total amount is less than minimum value than invoice doesn't create
      if($currency_data['data']['crypto_amount'] < $wallet['minimum_value']){
         $msg = 'Opps! Somethig went wrong!';
         $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$msg));
         Tools::redirect($error_link);
      }
      $curr_data = $currency_data['data'];
      $address = $inv_data['address'];
      $qr_code = $inv_data['qr_code'];
      $invoiceID = '';
      $camount = $curr_data['crypto_amount'];
      $order_status = 'Pending';
      $inv_amount = $total;
      $invoice_name = $wallet['name'];
      $coin = $wallet['coin'];
      $marchant_name = '';
      $total_amount = $total;
      $paid_amount = '[]'; 
      $base_currancy = $curr_data['fiat_symbol'];
      $description = 'Order Id #'.$orderId; 
      $payment_history = '';
      $conversion_rate =  '';
      $invoice_url =  '';

      $payment_table_name = 'coinremitter_payment'; 
      $order_table_name = 'coinremitter_order';
      $date_added = date("Y-m-d H:i:s");

      $db = Db::getInstance();
      $or_sql = "INSERT INTO `$order_table_name` (`order_id`, `invoice_id`, `address`, `amountusd`, `crp_amount`, `address_qrcode`, `payment_status`) VALUES ('$orderId', '$invoiceID', '$address', '$inv_amount', '$camount', '$qr_code', '$order_status')";
      $db->Execute($or_sql);

      $pay_sql = "INSERT INTO $payment_table_name (`order_id`, `address`, `invoice_id`, `invoice_name`, `marchant_name`, `total_amount`,`paid_amount`,`base_currancy`,`description`,`coin`,`payment_history`,`conversion_rate`,`invoice_url`,`status`,`expire_on`, `date_added`) VALUES ('$orderId', '$address', '$invoiceID', '$invoice_name', '$marchant_name', '$total_amount','$paid_amount','$base_currancy','$description','$coin','$payment_history','$conversion_rate','$invoice_url','$order_status','$expire_on','$date_added')";
      $db->Execute($pay_sql);
      // die(_PS_BASE_URL_);
      Tools::redirect(_PS_BASE_URL_ . __PS_BASE_URI__ .'index.php?controller=invoice&fc=module&module=coinremitter&order_id='.$orderId);
    }

   protected function delete_order($orderId){
      $order = New Order($orderId);
      $order->delete();
   }
}