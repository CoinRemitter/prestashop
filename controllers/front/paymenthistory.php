<?php

class CoinremitterPaymenthistoryModuleFrontController extends ModuleFrontController {

	public function init(){

		$this->ajax = true;
      $post = Tools::getAllValues();
      $db = Db::getInstance();
      $invoice = new CR_Invoice();
      date_default_timezone_set("UTC");
		$responseData = array();
		
      if($post['address'] && $post['address'] && $post['coin'] && $post['coin']!=""){

      	$address = $post['address'];
      	$coin = $post['coin'];
      	$sql="SELECT * FROM coinremitter_webhook WHERE address='".$address."'";
         $webhookData = $db->executes($sql);
         $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
         $result = $db->executes($sql);
         $Wallets = $result[0];

			$sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
			$resultWebhook = $db->executes($sql);
			$total_paid1 = 0;
			foreach ($resultWebhook as $rowWebhook) {
				$total_paid1 += number_format($rowWebhook['paid_amount'],8,".","");
			}
			$sql = "SELECT order_id,expire_on FROM coinremitter_payment WHERE address= '".$address."'";
			$resultPayment = $db->executes($sql)[0];
			$order = New Order((int)$resultPayment['order_id']);
			$order_status = $order->current_state;
			$expire_on = $resultPayment['expire_on'];

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
				$responseData['status'] = "expire";
	         $responseData['order_id'] = $resultPayment['order_id'];
				echo json_encode($responseData);
				parent::init();
				return;
			}
         
         $transactionsArray = array();
         foreach ($webhookData as $a) {
            $transactionsArray[] = $a['transaction_id'];
         }
         $wallet_data = [
            "api_key" => $Wallets['api_key'],
            "password" => $invoice->decrypt($Wallets['password']),
            "coin" => $Wallets['coin'],
            "address" => $address,
         ];
         $transactions = $invoice->CR_get_transactions_by_address($wallet_data);
         if($transactions['flag'] == 1){
            foreach ($transactions['data'] as $transaction) {
               $confirm = $transaction['confirmations'] < 3 ? $transaction['confirmations'] : 3;
               if(in_array($transaction['id'], $transactionsArray)){
                  $sql = "UPDATE coinremitter_webhook SET confirmations=".$confirm." WHERE transaction_id='".$transaction['id']."'";
                  $db->query($sql);
               } else {
                  $sql = "INSERT INTO coinremitter_webhook(address, transaction_id, txId, paid_amount, coin, confirmations, paid_date, explorer_url) VALUES('".$transaction['address']."','".$transaction['id']."','".$transaction['txid']."','".$transaction['amount']."','".$transaction['coin_short_name']."','".$transaction['confirmations']."','".$transaction['date']."','".$transaction['explorer_url']."')";
                  $db->Execute($sql);
						$this->checkRaceCondition($transaction['id'], $db->Insert_ID());
               }
            }
         }
	      $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
	      $resultWebhook = $db->executes($sql);
	      $total_paid = 0;
	      $total_paid1 = 0;
	      foreach ($resultWebhook as $rowWebhook) {
	         $total_paid1 += number_format($rowWebhook['paid_amount'],8,".","");
	         if($rowWebhook['confirmations'] >= 3){
	            $total_paid += number_format($rowWebhook['paid_amount'],8,".","");
	         }
	      }
	      $sql = "SELECT crp_amount FROM coinremitter_order WHERE address= '".$address."'";
	      $resultAmount = $db->executes($sql)[0];
	      $total_paid = (string)$total_paid;
	      $status = "Pending";
	      if($resultAmount['crp_amount'] == $total_paid)
	         $status = "Paid";
	      else if($resultAmount['crp_amount'] < $total_paid)
	         $status = "Over Paid";
	      else if($total_paid != 0 && $resultAmount['crp_amount'] > $total_paid)
	         $status = "Under Paid";
	      if($status == "Paid" || $status == "Over Paid" || $status == "Under Paid"){
	         $sql = "UPDATE coinremitter_order SET payment_status='$status' WHERE address= '".$address."'";
				$db->Execute($sql);
	         $sql = "UPDATE coinremitter_payment SET status='$status' WHERE address= '".$address."'";
	         $db->Execute($sql);
	      }

	      $sql = "SELECT co.payment_status,co.crp_amount,cp.coin,co.order_id, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address=cp.address AND co.address= '".$address."'";
	      $result = $db->executes($sql);
	      
	      if($result){
	         $orderData = $result[0];
	         $responseData['expire_on'] = $orderData['expire_on'];
	         if($orderData['payment_status'] == "Paid" || $orderData['payment_status'] == 'Over Paid'){
	            $responseData['status'] = "success";
	            $responseData['order_id'] = $orderData['order_id'];
	         } else {
	            $totalPaid = 0;
	            $total = $orderData['crp_amount'];
	            $responseData['nopayment'] = 0;
	            if($orderData['expire_on'] != ""){
	               $responseData['expire_on'] = date("M d, Y H:i:s", strtotime($orderData['expire_on']));
	               $responseData['curr'] = date("Y-m-d H:i:s");
	            } else {               
	               $responseData['nopayment'] = 1;
	            }
	            $responseData['total'] = $total;
	            $sql = "SELECT * FROM coinremitter_webhook WHERE address= '".$address."'";
	            $resultWebhook = $db->executes($sql);
	            if($resultWebhook){
	               $responseData['flag'] = 1;
	               $responseData['data'] = array();
	               $responseData['nopayment'] = 1;
	               foreach ($resultWebhook as $row) {
	                  $data = array();
	                  $data['transaction'] = $row['transaction_id'];
	                  $data['txid'] = substr($row['txId'],0,20)."...";
	                  $data['amount'] = $row['paid_amount'];
	                  $data['coin'] = $row['coin'];
	                  $data['explorer_url'] = $row['explorer_url'];
	                  $data['confirmations'] = $row['confirmations'];
	                  $data['paid_date'] = date("M d, Y H:i:s", strtotime($row['paid_date']));
	                  if($row['confirmations']>= 3)
	                     $totalPaid += $row['paid_amount'];
	                  array_push($responseData['data'], $data);
	               }
	            } else {
	               $responseData['flag'] = 0;
	               $responseData['msg'] = "No payment history found";
	            }
	            $responseData['totalPaid'] = number_format($totalPaid, 8, '.', '');
	            $responseData['totalPending'] = number_format($total - $totalPaid, 8, '.', '');
	            $responseData['coin'] = $orderData['coin'];
	         }
	      } else {
	         $responseData['flag'] = 0;
	         $responseData['msg'] = "Address not found";
	      }
      }
      echo json_encode($responseData);
      parent::init();
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