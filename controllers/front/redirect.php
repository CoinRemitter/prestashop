<?php 
include dirname(__DIR__, 2) . "/CR/CR_Invoice.php";

class CoinremitterRedirectModuleFrontController extends ModuleFrontController
{



    public function postProcess()
    {
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
            die($this->module->l('This payment method is not available.', 'redirect'));
        }
        $mailVars = array(

        );

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
        	die($this->module->l('Payment gateway error.', 'redirect'));
        }	

        $this->module->validateOrder($cart->id, Configuration::get('PS_OS_BANKWIRE'), $total, $this->module->displayName, null, $mailVars, (int) $currency->id, false, $customer->secure_key);
        $redirectURL = _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=order-detail&id_order=' . $this->module->currentOrder . '&key=' . $customer->secure_key;
        $orderId = $this->module->currentOrder;
        $invoice_expiry =Configuration::get('coinremitter_invoice_expiry');
        $invoice_exchange_rate = Configuration::get('coinremitter_ex_rate');
        $notificationURL = _PS_BASE_URL_ . __PS_BASE_URI__ . 'module/coinremitter/callback';
        if($invoice_expiry == 0 || $invoice_expiry == null){
            $invoice_expiry ='';
        }
         if($invoice_exchange_rate == 0 && $invoice_exchange_rate == ''){
            $invoice_exchange_rate = 1;
        }else{
            $invoice_exchange_rate = $invoice_exchange_rate;
        }
        $total = $total*$invoice_exchange_rate;
    	$inv_param['api_key'] = $wallet['api_key'];
    	$inv_param['password'] = $invoice->decrypt($wallet['password']);
    	$inv_param['amount'] = $total;
    	$inv_param['coin'] = $wallet['coin'];
    	$inv_param['notify_url'] = $notificationURL;
    	$inv_param['currency'] = $currency->iso_code;
    	$inv_param['expire_time'] = $invoice_expiry;
        $inv_param['suceess_url'] = $redirectURL;
    	$inv_param['fail_url'] = $redirectURL;
    	$inv_param['description'] = 'Order Id #'.$orderId;
        $invoice_data = $invoice->CR_createInvoice($inv_param);
        if($invoice_data['flag'] != 1){
            die($this->module->l($invoice_data['msg'], 'redirect'));			
		}
        $inv_data = $invoice_data['data'];


        if (empty($inv_data) || empty($inv_data['url'])) {
            die($this->module->l('error', 'redirect'));			
        }

        if(empty($inv_data['paid_amount'])){
            $paid_amount = '';
        }else{
            $paid_amount = json_encode($inv_data['paid_amount']);
        }
       
        $invoiceID = $inv_data['invoice_id'];
        $coin = $inv_data['coin'];
        $camount = $inv_data['total_amount'][$coin] ;
        $order_status = $inv_data['status'];
        $inv_amount =$inv_data['usd_amount'];
        $invoice_name = $inv_data['name'];
        $marchant_name = isset($inv_data['marchant_name'])?$inv_data['marchant_name']:'';
        $total_amount = json_encode($inv_data['total_amount']);
        $paid_amount = $paid_amount; 
        $base_currancy = $inv_data['base_currency'];
        $description = $inv_data['description']; 
        $payment_history = isset($inv_data['payment_history'])?json_encode($inv_data['payment_history']):'';
        $conversion_rate =  isset($inv_data['conversion_rate'])?json_encode($inv_data['conversion_rate']):'';
        $invoice_url =  $inv_data['url'];
        $payment_table_name = 'coinremitter_payment'; 
        $order_table_name = 'coinremitter_order';
        $db = Db::getInstance();
        $or_sql = "INSERT INTO `$order_table_name` (`order_id`, `invoice_id`, `amountusd`, `crp_amount`, `payment_status`) VALUES ('$orderId', '$invoiceID', '$inv_amount', '$camount', '$order_status')";
        $db->Execute($or_sql);

        $pay_sql = "INSERT INTO $payment_table_name (`order_id`, `invoice_id`, `invoice_name`, `marchant_name`, `total_amount`,`paid_amount`,`base_currancy`,`description`,`coin`,`payment_history`,`conversion_rate`,`invoice_url`,`status`) VALUES ('$orderId', '$invoiceID', '$invoice_name', '$marchant_name', '$total_amount','$paid_amount','$base_currancy','$description','$coin','$payment_history','$conversion_rate','$invoice_url','$order_status')";
        print_r($pay_sql);
        $db->Execute($pay_sql);

        $order = new Order($orderId);
        $history = new OrderHistory();
        $history->id_order = $order->id;
        $history->id_employee = (int) 1;
        $history->id_order_state = 3;
        $history->save();
        $history->sendEmail($order);
		Tools::redirect($inv_data['url']);
    }
}