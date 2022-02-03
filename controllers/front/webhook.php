<?php

class CoinremitterWebhookModuleFrontController extends ModuleFrontController {
	
	public function init(){
      $this->ajax = true;
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

   public function processGetRequest(){

      $this->ajaxRender(json_encode([
         'success' => true,
         'operation' => 'get',
         'message' => 'You should not be here',
      ]));
   }

	public function processPostRequest() {

      $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
      $logger->setFilename(_PS_ROOT_DIR_."/var/logs/debug.log");
      $logger->logDebug("");
      $logger->logDebug("");
      $logger->logDebug("");
      $logger->logDebug("");
      $logger->logDebug("");
      date_default_timezone_set("UTC");
      $logger->logDebug("Webhook Called");
      $post = Tools::getAllValues();
      $logger->logDebug($post);
      
      

		$db = Db::getInstance();
      $invoice = new CR_Invoice();
		if($post['address'] && $post['address'] && $post['coin_short_name'] && $post['coin_short_name']!="" && $post['type'] && $post['type'] == "receive"){
         $address = $post['address'];
         $logger->logDebug("Address ". $address);
			$sql = "SELECT order_id, expire_on, status FROM coinremitter_payment WHERE address= '".$address."'";
			$result = $db->executes($sql);
			if($result){
				$coin = $post['coin_short_name'];
            $transactionID = $post['id'];
            $expire_on = $result[0]['expire_on'];
            $order_id = $result[0]['order_id'];
            $order = New Order((int)$order_id);
            $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
            $resultWebhook = $db->executes($sql);
            $total_paid1 = 0;
            foreach ($resultWebhook as $rowWebhook) {
               $total_paid1 += number_format($rowWebhook['paid_amount'],8,".","");
            }
            $order_status = $order->current_state;
            $sql = "SELECT crp_amount FROM coinremitter_order WHERE address= '".$address."'";
            $resultAmount = $db->executes($sql)[0];
            $date_diff = 0;
            if($expire_on != ""){
               $current = strtotime(date("Y-m-d H:i:s"));
               $expire_on = strtotime($expire_on);
               $date_diff = $expire_on - $current;
            }
            if((string)$total_paid1 == 0 && $date_diff < 1 && $expire_on != "" && ($order_status == 10 || $order_status == 6)) {

               $sql = "UPDATE coinremitter_order SET payment_status='Expired' WHERE address= '".$address."'";
               $db->Execute($sql);
               $sql = "UPDATE coinremitter_payment SET status='Expired' WHERE address= '".$address."'";
               $db->Execute($sql);
               $this->orderCancel($order_id);
               return;
            }
            $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
            $resultWallet = $db->executes($sql);
            $trans_data = [
               "api_key" => $resultWallet[0]['api_key'],
               "password" => $invoice->decrypt($resultWallet[0]['password']),
               "coin" => $coin,
               "id" => $transactionID,
            ];
            $transaction = $invoice->CR_getTransaction($trans_data);
            if($transaction['flag'] == 1){
               $confirmations = $transaction['data']['confirmations'];
               $sql = "SELECT * FROM coinremitter_webhook WHERE transaction_id= '".$transactionID."'";
               $resultTransaction = $db->executes($sql);
               if(!$resultTransaction){
                  $sql = "INSERT INTO coinremitter_webhook(address, transaction_id, txId, paid_amount, coin, confirmations, paid_date, explorer_url) VALUES('".$transaction['data']['address']."','".$transaction['data']['id']."','".$transaction['data']['txid']."','".$transaction['data']['amount']."','".$transaction['data']['coin_short_name']."','".$transaction['data']['confirmations']."','".$transaction['data']['date']."','".$transaction['data']['explorer_url']."')";
                  $logger->logDebug($sql);
                  $db->Execute($sql);
                  $this->checkRaceCondition($transaction['data']['id'], $db->Insert_ID());
                  $logger->logDebug("New Transaction");
               } else {
                  $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirmations." WHERE transaction_id='".$transaction['data']['id']."'";
                  $db->Execute($sql);
                  $logger->logDebug("Transaction already exists");
               }

               $sql="SELECT * FROM coinremitter_webhook WHERE address='".$address."' AND confirmations < 3";
               $webhookData = $db->executes($sql);
               if($webhookData){
                  foreach ($webhookData as $webhook) {
                     $wallet_data = [
                        "api_key" => $resultWallet[0]['api_key'],
                        "password" => $invoice->decrypt($resultWallet[0]['password']),
                        "coin" => $coin,
                        "id" => $webhook['transaction_id'],
                     ];
                     $transaction = $invoice->CR_getTransaction($wallet_data);
                     if($transaction['flag'] == 1){
                        $confirm = $transaction["data"]['confirmations'] < 3 ? $transaction["data"]['confirmations'] : 3;
                        $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirm." WHERE transaction_id='".$webhook['transaction_id']."'";
                        $db->Execute($sql);
                     }
                  }
               }

               $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
               $resultWebhook = $db->executes($sql);
               $total_paid = 0;
               foreach ($resultWebhook as $rowWebhook) {
                  if($rowWebhook['confirmations'] >= 3){
                     $total_paid += number_format($rowWebhook['paid_amount'],8,".","");
                  }
               }
               $total_paid = (string)$total_paid;
               $status = 'Pending';
               if($resultAmount['crp_amount'] == $total_paid)
                  $status = "Paid";
               else if($resultAmount['crp_amount'] < $total_paid)
                  $status = "Over Paid";
               else if($total_paid != 0 && $resultAmount['crp_amount'] > $total_paid)
                  $status = "Under Paid";
               
               $logger->logDebug("crp_amount " . $resultAmount['crp_amount']);
               $logger->logDebug("total_paid " . $total_paid);
               $logger->logDebug("Status " . $status);
               if($status == "Paid" || $status == "Over Paid" || $status == "Under Paid"){
                  $sql = "UPDATE coinremitter_order SET payment_status='$status' WHERE address= '".$address."'";
                  $db->Execute($sql);
                  $sql = "UPDATE coinremitter_payment SET status='$status' WHERE address= '".$address."'";
                  $db->Execute($sql);
                  if($status == "Paid" || $status == "Over Paid")
                     $this->orderConfirm($order_id);
               }
            } else {
               $logger->logDebug("Transaction Not Found");
            }
			} else {
            $logger->logDebug("Address Not Found");
			}
		}
	}

	public function orderCancel($order_id){

      $ostate = 6; //canceled order status
		$order = new Order($order_id);
      $history = new OrderHistory();
      $history->id_order = $order->id;
      $history->id_employee = (int) 1;
      $history->id_order_state = (int) $ostate;
      $history->save();
      $history->changeIdOrderState($ostate, $order , true); 
      $history->sendEmail($order);
	}

	public function orderConfirm($order_id){

      $order = new Order($order_id);
      $order_current_satus = $order->current_state;
      $accepted_payment_status = Configuration::get('coinremitter_order_status');
      if($order_current_satus != $accepted_payment_status){
   		$history = new OrderHistory();
         $history->id_order = $order->id;
         $history->id_employee = (int) 1;
         $history->id_order_state = $accepted_payment_status;
         $history->save();
         $history->changeIdOrderState($accepted_payment_status, $order , true); 
         $history->sendEmail($order);
      }
	}

   public function checkRaceCondition($transaction_id, $last_id)
	{
		$db = Db::getInstance();
		$sql = "SELECT transaction_id FROM coinremitter_webhook WHERE transaction_id= '".$transaction_id."'";
	   $result = $db->executes($sql);
		if(count($result) > 1) {
			$sql = "DELETE FROM coinremitter_webhook WHERE id= '".$last_id."'";
	      $db->Execute($sql);
		}
	}
}