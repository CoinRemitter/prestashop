<?php

class CoinremitterPaymenthistoryModuleFrontController extends ModuleFrontController
{

	public function init()
	{

		$this->ajax = true;
		$post = Tools::getAllValues();
		$db = Db::getInstance();
		$invoice = new CR_Invoice();
		date_default_timezone_set("UTC");

		
		if (!isset($post['address'])) {
			echo json_encode(array('flag' => 0, 'msg' => 'Invalid address'));
			parent::init();
			return;
		}
		$address = $post['address'];
		$sql = "SELECT * FROM coinremitter_orders WHERE `payment_address`= '" . $address . "'";
		$order = $db->executes($sql);
		if (empty($order)) {
			echo json_encode(array('flag' => 0, 'msg' => 'Invalid address'));
			parent::init();
			return;
		}
		$order = $order[0];

		$order_id = $order['order_id'];
		if (!isset($order_id) || $order_id == '') {
			echo json_encode(array('flag' => 0, 'msg' => 'Unauthorized access'));
			parent::init();
			return;
		}
		$systemOrder = new Order((int)$order_id);

		if (!Validate::isLoadedObject($systemOrder) || $systemOrder->id_customer != $this->context->customer->id || $systemOrder->module != "coinremitter") {
			echo json_encode(array('flag' => 0, 'msg' => 'Unauthorized access'));
			parent::init();
			return;
		}

		$coin = $order['coin_symbol'];

		if($order['order_status'] == ORDER_STATUS_CODE['expired']){
			echo json_encode(array('flag' => 0, 'msg' => 'Order expired'));
			parent::init();
			return;
		}
		$sql = "SELECT * FROM coinremitter_wallets WHERE `coin_symbol`= '" . $coin . "' LIMIT 1";
		$wallet = $db->executes($sql);
		if (empty($wallet)) {
			echo json_encode(array('flag' => 0, 'msg' => 'Invalid address'));
			parent::init();
			return;
		}
		$wallet = $wallet[0];

		$pendingCryptoAmount = number_format($order['crypto_amount'] - $order['paid_crypto_amount'], 8, '.', '');
		$order['transaction_meta'] = $order['transaction_meta'] ? json_decode($order['transaction_meta'], true) : [];
		$order_status = $order['order_status'];
		$address = $order['payment_address'];
		$expire_on = $order['expiry_date'];
		$date_diff = 0;
		if ($expire_on != "") {
			$current = strtotime(date("Y-m-d H:i:s"));
			$expire_on = strtotime($expire_on);
			$date_diff = $expire_on - $current;
			$expire_on = date("M d, Y H:i:s", $expire_on);
		}
		$responseData = array(
			'flag' => 1,
			'msg' => 'Success',
			'data' => [
				"order_id" => $order['order_id'],
				"now_time" => date('Y-m-d, H:i:s'),
				"coin_symbol" => $order['coin_symbol'],
				"status" => ORDER_STATUS[$order_status],
				"status_code" => $order_status,
				"transactions" => $invoice->prepareReturnTrxData($order['transaction_meta']),
				"paid_amount" => $order['paid_crypto_amount'],
				"pending_amount" => $pendingCryptoAmount,
				"expire_on" => $expire_on,
			]
		);


		if (empty($order['transaction_meta']) && $date_diff < 1 && $expire_on != "") {
			$expireStatus = ORDER_STATUS_CODE['expired'];
			$sql = "UPDATE `coinremitter_orders` SET `order_status`= $expireStatus WHERE `id`= '" . $order['id'] . "'";
			$db->Execute($sql);
			$responseData['data']['status'] = ORDER_STATUS[$expireStatus];
			$responseData['data']['status_code'] = $expireStatus;
			echo json_encode($responseData);
			$this->orderCancel($order['order_id']);
			parent::init();
			return;
		}

		$requestParam = [
			"api_key" => $wallet['api_key'],
			"password" => $invoice->decrypt($wallet['password']),
			"address" => $address,
		];
		$transactionsRes = $invoice->CR_getTransactionsByAddress($requestParam);

		if (!isset($transactionsRes) || !$transactionsRes['success']) {
			echo json_encode($responseData);
			parent::init();
			return;
		}

		$allTrx = $transactionsRes['data']['transactions'];
		$trxMeta = $order['transaction_meta'];
		$updateOrderRequired = false;
		$total_paid = 0;
		foreach ($allTrx as $trx) {
			if (isset($trx['type']) && $trx['type'] == 'receive') {
				// print_r($trx);
				// die;
				$fiat_amount = ($trx['amount'] * $order['fiat_amount']) / $order['crypto_amount'];
				$minFiatAmount = $wallet['minimum_invoice_amount'];
				if ($order['fiat_symbol'] != 'USD') {
					$minFiatAmount = $wallet['minimum_invoice_amount'];
				}
				$minFiatAmount = number_format($minFiatAmount, 2, '.', '');
				$fiat_amount = number_format($fiat_amount, 2, '.', '');
				$currency = Currency::getIdByIsoCode($order['fiat_symbol']);
				$currency = new Currency($currency);

				$fiat_amount = Tools::convertPrice($fiat_amount,$currency,false);
				if ($fiat_amount < $minFiatAmount) {
					continue;
				}

				$transactionInfo = $invoice->checkTransactionExists($order['transaction_meta'], $trx['txid']);
				if (empty($transactionInfo)) {
					$updateOrderRequired = true;
					$trxMeta[$trx['txid']] = $trx;
				} else {
					if ($transactionInfo['status_code'] != $trx['status_code']) {
						$trxMeta[$trx['txid']] = $trx;
						$updateOrderRequired = true;
					}
				}
				if ($trx['status_code'] == 1) {
					$total_paid += $trx['amount'];
				}
			}
		}
		
		if(!$updateOrderRequired){
			echo json_encode($responseData);
			parent::init();
			return;
		}

		$truncationValue = TRUNCATION_VALUE;
		if ($order['fiat_symbol'] != 'USD') {
			$truncationValue = TRUNCATION_VALUE;
		}
		$truncationValue = number_format($truncationValue, 4, '.', '');
		$total_fiat_paid = number_format(($total_paid * $order['fiat_amount']) / $order['crypto_amount'], 2, '.', '');
		$totalFiatPaidWithTruncation = $total_fiat_paid + $truncationValue;

		$status = $order_status;
		if ($total_paid == $order['crypto_amount']) {
			$status = ORDER_STATUS_CODE['paid'];
		} else if ($total_paid > $order['crypto_amount']) {
			$status = ORDER_STATUS_CODE['over_paid'];
		} else if ($total_paid != 0 && $total_paid < $order['crypto_amount']) {
			$status = ORDER_STATUS_CODE['under_paid'];
			if ($totalFiatPaidWithTruncation > $order['fiat_amount']) {
				$status = ORDER_STATUS_CODE['paid'];
			}
		}
		$trxMeta = json_encode($trxMeta);
		$sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE payment_address='" . $order['payment_address'] . "'";
		$db->query($sql);

		$responseData['data']['status'] = ORDER_STATUS[$status];
		$responseData['data']['status_code'] = $status;
		$responseData['data']['transactions'] = $invoice->prepareReturnTrxData(json_decode($trxMeta,true));
		$responseData['data']['paid_amount'] = number_format($total_paid, 8, '.', '');
		$responseData['data']['pending_amount'] = number_format($order['crypto_amount'] - $total_paid, 8, '.', '');
		echo json_encode($responseData);
		parent::init();
		return;
	}

	public function orderCancel($order_id)
	{

		try {
			$ostate = 6; //canceled order status
			$order = new Order($order_id);
			$history = new OrderHistory();
			$history->id_order = $order->id;
			$history->id_employee = (int) 1;
			$history->id_order_state = (int) $ostate;
			$history->save();
			$history->changeIdOrderState($ostate, $order, true);
			$history->sendEmail($order);
			header("Refresh:0");
		} catch (\Exception $ex) {
			return ;
		}
	}

	
}
