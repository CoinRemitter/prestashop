<?php

class CoinremitterWebhookModuleFrontController extends ModuleFrontController
{

   public function init()
   {
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

   public function processGetRequest()
   {

      $this->ajaxRender(json_encode([
         'success' => true,
         'operation' => 'get',
         'message' => 'You should not be here',
      ]));
   }

   public function processPostRequest()
   {

      $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
      $logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/debug.log");
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

      if (!isset($post['address']) || !isset($post['coin_symbol']) || !isset($post['type'])) {
         return "Invalid webhook data";
      }

      $address = $post['address'];
      $coin = $post['coin_symbol'];
      $id = $post['id'];

      $logger->logDebug("Address " . $address);
      $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address`= '" . $address . "' LIMIT 1";
      $result = $db->executes($sql);
      $logger->logDebug($sql);
      if (empty($result)) {
         $logger->logDebug("Address Not Found");
         return;
      }
      $logger->logDebug('Step ->1');
      $coinremitterOrder = $result[0];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $logger->logDebug($coinremitterOrder);
      if ($coinremitterOrder['coin_symbol'] != $coin) {
         $logger->logDebug('Stwp ->2');
         $logger->logDebug("Coin Not Matched");
         return;
      }

      $logger->logDebug('Stwp ->3');
      $expire_on = $coinremitterOrder['expiry_date'];
      $order_id = $coinremitterOrder['order_id'];
      $order = new Order((int)$order_id);
      $logger->logDebug('Stwp ->4');

      $order_status = $order->current_state;
      $date_diff = 0;
      if (empty($coinremitterOrder['transaction_meta']) && $coinremitterOrder['order_status'] == ORDER_STATUS_CODE['pending'] && $expire_on != "") {
         $logger->logDebug('Stwp ->5');
         $current = strtotime(date("Y-m-d H:i:s"));
         $expire_on = strtotime($expire_on);
         $date_diff = $expire_on - $current;
         if ($date_diff < 1 && ($order_status == 10 || $order_status == 6)) {

            $logger->logDebug('Stwp ->6');
            $sql = "UPDATE `coinremitter_orders` SET `order_status`=" . ORDER_STATUS_CODE['expired'] . " WHERE `payment_address`= '" . $address . "'";
            $db->Execute($sql);
            $this->orderCancel($order_id);
            return 'Order Expired';
         }
      }
      $logger->logDebug('Stwp ->7');
      $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $coin . "'";
      $resultWallet = $db->executes($sql);
      if (empty($resultWallet)) {
         $logger->logDebug("Wallet Not Found");
         return;
      }
      $logger->logDebug('Stwp ->8');

      $wallet = $resultWallet[0];
      $requestParam = [
         "api_key" => $wallet['api_key'],
         "password" => $invoice->decrypt($wallet['password']),
         "coin" => $coin,
         "id" => $id,
      ];
      $transaction = $invoice->CR_getTransaction($requestParam);

      if (!isset($transaction) || !$transaction['success']) {
         $logger->logDebug("Transaction Not Found");
         return;
      }
      $transactionData = $transaction['data'];
      if ($transactionData['type'] != 'receive') {
         $logger->logDebug("Transaction Type Not Matched");
         return "Transaction Type Not Matched";
      }

      if (strtolower($transactionData['address']) != strtolower($coinremitterOrder['payment_address'])) {
         return 'Invalid Address';
      }

      $fiat_amount = ($transactionData['amount'] * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'];
      $minFiatAmount = $wallet['minimum_invoice_amount'];
      if ($coinremitterOrder['fiat_symbol'] != 'USD') {
         $minFiatAmount = $wallet['minimum_invoice_amount'];
      }
      $minFiatAmount = number_format($minFiatAmount, 2, '.', '');
      $fiat_amount = number_format($fiat_amount, 2, '.', '');
      $currency = Currency::getIdByIsoCode($coinremitterOrder['fiat_symbol']);
      $currency = new Currency($currency);
      $fiat_amount = Tools::convertPrice($fiat_amount, $currency, false);
      if ($fiat_amount < $minFiatAmount) {
         $logger->logDebug('Order amount is less than minimum amount');
         return 'Less than minimum amount';
      }

      $sql = "SELECT * FROM `coinremitter_orders` WHERE `payment_address`= '" . $address . "' LIMIT 1";
      $result = $db->executes($sql);
      if (empty($result)) {
         $logger->logDebug("Address Not Found");
         return;
      }
      $coinremitterOrder = $result[0];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $transactionInfo = $invoice->checkTransactionExists($coinremitterOrder['transaction_meta'], $transactionData['txid']);
      $trxMeta = $coinremitterOrder['transaction_meta'];
      $total_paid = $coinremitterOrder['paid_crypto_amount'];
      $updateOrderRequired = false;
      if (empty($transactionInfo)) {
         $trxMeta[$transactionData['txid']] = $transactionData;
         if ($transactionData['status_code']) {
            $total_paid += $transactionData['amount'];
         }
         $updateOrderRequired = true;
      }

      if ($trxMeta[$transactionData['txid']]['status_code'] == 0 && $transactionData['confirmations'] >= $transactionData['required_confirmations']) {
         $trxMeta[$transactionData['txid']] = $transactionData;
         $trxMeta[$transactionData['txid']]['status_code'] = 1;
         $total_paid += $transactionData['amount'];
         $updateOrderRequired = true;
      }

      if (!$updateOrderRequired) {
         return 'Order Not Updated';
      }

      $truncationValue = TRUNCATION_VALUE;
      if ($coinremitterOrder['fiat_symbol'] != 'USD') {
         $truncationValue = TRUNCATION_VALUE;
      }
      $truncationValue = number_format($truncationValue, 4, '.', '');
      $total_fiat_paid = number_format(($total_paid * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'], 2, '.', '');
      $totalFiatPaidWithTruncation = $total_fiat_paid + $truncationValue;

      $status = $coinremitterOrder['order_status'];
      if ($total_paid == $coinremitterOrder['crypto_amount']) {
         $status = ORDER_STATUS_CODE['paid'];
      } else if ($total_paid > $coinremitterOrder['crypto_amount']) {
         $status = ORDER_STATUS_CODE['over_paid'];
      } else if ($total_paid != 0 && $total_paid < $coinremitterOrder['crypto_amount']) {
         $status = ORDER_STATUS_CODE['under_paid'];
         if ($totalFiatPaidWithTruncation > $coinremitterOrder['fiat_amount']) {
            $status = ORDER_STATUS_CODE['paid'];
         }
      }

      if ($coinremitterOrder['order_status'] == ORDER_STATUS_CODE['expired']) {
         $logger->logDebug('Order already expired just update history.');
         $status = ORDER_STATUS_CODE['expired'];
      }

      $logger->logDebug("Total Crypto paid amount: " . $total_paid);
      $logger->logDebug("Order Status: " . $status);

      $trxMeta = json_encode($trxMeta);
      $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE `payment_address`='" . $coinremitterOrder['payment_address'] . "'";
      $db->query($sql);
      if ($status == ORDER_STATUS_CODE['paid'] || $status == ORDER_STATUS_CODE['over_paid']) {
         $this->orderConfirm($order_id);
         return 'Order Paid';
      }
      return 'Order Updated';
   }

   public function orderCancel($order_id)
   {

      $ostate = 6; //canceled order status
      $order = new Order($order_id);
      $history = new OrderHistory();
      $history->id_order = $order->id;
      $history->id_employee = (int) 1;
      $history->id_order_state = (int) $ostate;
      $history->save();
      $history->changeIdOrderState($ostate, $order, true);
      $history->sendEmail($order);
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

   public function checkRaceCondition($transaction_id, $last_id)
   {
      $db = Db::getInstance();
      $sql = "SELECT transaction_id FROM coinremitter_webhook WHERE transaction_id= '" . $transaction_id . "'";
      $result = $db->executes($sql);
      if (count($result) > 1) {
         $sql = "DELETE FROM coinremitter_webhook WHERE id= '" . $last_id . "'";
         $db->Execute($sql);
      }
   }
}
