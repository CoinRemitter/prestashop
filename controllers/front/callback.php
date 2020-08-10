<?php
include dirname(__DIR__, 2) . "/CR/CR_Invoice.php";

class CoinremitterCallbackModuleFrontController extends ModuleFrontController{
   	
   	public function init(){
        parent::init();
        switch ($_SERVER['REQUEST_METHOD']) {
            case 'GET':
                $this->processGetRequest();
                break;
            case 'POST':
                $this->processPostRequest();
                break;
            default:
                // throw some error or whatever
        }
    }

	 public function processGetRequest()
    {
        $this->ajaxDie(json_encode([
            'success' => true,
            'operation' => 'get',
            'message' => 'You should not be here',
        ]));
    }
    public function processPostRequest(){
      
      $param = Tools::getAllValues();  
  		if(!isset($param['coin'])){
  			die('Coin Not Found');	
  		}
      if(!isset($param['invoice_id'])){
        die('Invoice ID Not Found'); 
      }
	    $wallet = [];
     	$coin = $param['coin'];
     	$invoice_id = $param['invoice_id'];

      $wsql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
     	$results = Db::getInstance()->executes($wsql);
     	if(count($results) > 0){
     		$wallet = $results[0];	
     	}
     	if(!$wallet){
		     die('Wallet Not Found');	
     	}
     	$accepted_payment_status = Configuration::get('coinremitter_order_status');
	    $osql = "SELECT * FROM coinremitter_order WHERE invoice_id= '".$invoice_id."'";
      
      $ord_results = Db::getInstance()->executes($osql);

      if(count($ord_results) > 0){
      	$ord_results = $ord_results[0];	
      }else{
       die('Order Not Found'); 
      }
		  $orderId = $ord_results['order_id'];
   		$order = new Order($orderId);
   		$order_current_satus = $order->current_state;
   	  	$invoiceObj = new CR_Invoice();
        $postData = [
          'api_key'=>$wallet['api_key'],
          'password'=>$invoiceObj->decrypt($wallet['password']),
          'invoice_id'=>$invoice_id,
          'coin'=>$coin
        ];
        
        $invoice = $invoiceObj->CR_getInvoice($postData);
        if($invoice['flag'] ==1){
        	$invoice_data = $invoice['data'];
          $orderId = $order->id;
          $invoiceID = $invoice_data['invoice_id'];
          $invoice_name = $invoice_data['name'];
          $coin = $invoice_data['coin'];
          $marchant_name = isset($invoice_data['marchant_name'])?$invoice_data['marchant_name']:'';
          $total_amount = json_encode($invoice_data['total_amount']);
          $paid_amount = json_encode($invoice_data['paid_amount']);

          $base_currancy = $invoice_data['base_currency'];
          $description = $invoice_data['description'];
          
          $payment_history = json_encode($invoice_data['payment_history']);
          $conversion_rate =  json_encode($invoice_data['conversion_rate']);
          $invoice_url =  $invoice_data['url'];
          $status =  $invoice_data['status'];
          $payment_table_name = 'coinremitter_payment'; 
          if($invoice_data['status_code'] == 1 || $invoice_data['status_code'] == 3){
            if($order_current_satus != $accepted_payment_status){
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->id_employee = (int) 1;
                $history->id_order_state = $accepted_payment_status;
                $history->save();
                $history->changeIdOrderState($accepted_payment_status, $order , true); 
                $history->sendEmail($order);
            }
  				  


            $usql = "SELECT * FROM $payment_table_name WHERE invoice_id= '".$invoiceID."'";
            $check_pay = Db::getInstance()->executes($usql);
            if(count($check_pay) > 0){
              $or_sql = "UPDATE `coinremitter_payment` SET `status` = '$status',`total_amount` ='$total_amount', `paid_amount` = '$paid_amount', `payment_history` = '$payment_history',`conversion_rate` ='$conversion_rate' WHERE `coinremitter_payment`.`invoice_id` = '".$invoiceID."'";
            }else{
		          $or_sql = "INSERT INTO $payment_table_name (`order_id`, `invoice_id`, `invoice_name`, `marchant_name`, `total_amount`,`paid_amount`,`base_currancy`,`description`,`coin`,`payment_history`,`conversion_rate`,`invoice_url`,`status`) VALUES ('$orderId', '$invoiceID', '$invoice_name', '$marchant_name', '$total_amount','$paid_amount','$base_currancy','$description','$coin','$payment_history','$conversion_rate','$invoice_url','$status')";
            }
		  		  $db = Db::getInstance();
		        $db->Execute($or_sql);
		        die();
        	}elseif ($invoice_data['status_code'] == 4) {
              $ostate = 6; //canceled order status
              $or_sql = "UPDATE `coinremitter_payment` SET `status` = '$status',`total_amount` ='$total_amount', `paid_amount` = '$paid_amount', `payment_history` = '$payment_history',`conversion_rate` ='$conversion_rate' WHERE `coinremitter_payment`.`invoice_id` = '".$invoiceID."'";
              $db = Db::getInstance();
              $db->Execute($or_sql);
              if(empty($invoice_data['payment_history'])){
                $history = new OrderHistory();
                $history->id_order = $order->id;
                $history->id_employee = (int) 1;
                $history->id_order_state = $ostate;
                $history->save();
                $history->changeIdOrderState($ostate, $order , true); 
                $history->sendEmail($order);
                die('order canceled.');
              }

          }else{
        		die('invoice not paid');
        	}
        }else{
        	die('invoice not found');
        }
        
    }
  	public function getOrderStates(){

        $sql = 'SELECT * FROM ps_order_state_lang ORDER BY name ASC';
        $results = Db::getInstance()->ExecuteS($sql);
        $i = 0;
        foreach ($results as $row) {
            $arr[$i]['id_option'] = $row['id_order_state'];
            $arr[$i]['name'] = $row['name'];
            $i++;
        }
        return $arr;
    }
}
        		
