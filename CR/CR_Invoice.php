<?php
class CR_Invoice
{
   private $skey = "coinremitter";        // Store the encryption key
   private $ciphering = "AES-256-CBC"; // Store the cipher method
   private $options = 0; //a bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
   private $encryption_iv = 'Coinremitter__iv'; // Non-NULL (precisely 16 bytes) Initialization Vector for encryption

   private $plugin_version = '1.0.2';
   private $api_version = 'v1';
   private $api_base_url = 'https://api.coinremitter.com/';


   public function __construct()
   {
      if (!defined('ORDER_STATUS_CODE')) {
         define('ORDER_STATUS_CODE', array(
            'pending' => 0,
            'paid' => 1,
            'under_paid' => 2,
            'over_paid' => 3,
            'expired' => 4,
            'cancelled' => 5,
         ));
      }
      if (!defined('ORDER_STATUS')) {
         define('ORDER_STATUS', array('Pending', 'Paid', 'Under Paid', 'Over Paid', 'Expired', 'Cancelled'));
      }
      if (!defined('TRUNCATION_VALUE')) {
         define('TRUNCATION_VALUE', 0.05); // in USD
      }
   }

   public function CR_getBalance($param)
   {
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/wallet/balance";
      $res = $this->apiCall($url, $param);

      return $res;
   }

   public function CR_createAddress($param)
   {
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/wallet/address/create";
      $res = $this->apiCall($url, $param);
      return $res;
   }


   public function CR_getTransaction($param)
   {
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/wallet/transaction";
      $res = $this->apiCall($url, $param);
      return $res;
   }

   public function CR_getTransactionsByAddress($param)
   {
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/wallet/address/transactions";
      $res = $this->apiCall($url, $param);
      return $res;
   }

   public function checkTransactionExists($transactions, $trx_id)
	{
		foreach ($transactions as $transaction) {
			if ($transaction['txid'] == $trx_id) {
				return $transaction;
			}
		}
		return [];
	}
   
   public function prepareReturnTrxData($transactions)
	{
      foreach ($transactions as $trxId => $trx) {
         unset($transactions[$trxId]['wallet_id']);
         unset($transactions[$trxId]['wallet_name']);
         unset($transactions[$trxId]['type']);
         unset($transactions[$trxId]['label']);
         unset($transactions[$trxId]['required_confirmations']);
         unset($transactions[$trxId]['id']);
      }
      return $transactions;
	}

   public function CR_getFiatToCryptoRate($param)
   {
      // print_r($param);
      // die();
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/rate/fiat-to-crypto";
      $res = $this->apiCall($url, $param);
      return $res;
   }

   public function CR_getCryptoToFiatRate($param)
   {
      // print_r($param);
      // die();
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/rate/crypto-to-fiat";
      $res = $this->apiCall($url, $param);
      return $res;
   }

   public function CR_getCoin($coin_symbol)
   {
      if (!$coin_symbol) {
         return [];
      }
      $url = $this->api_base_url . $this->api_version;
      $url =  $url . "/rate/supported-currency";
      $coins = $this->apiCall($url);
      if (!$coins['success']) {
         return [];
      }
      foreach ($coins['data'] as $coin) {
         if ($coin['coin_symbol'] == $coin_symbol) {
            return $coin;
         }
      }
      return [];
   }

   public function CR_getWallet($coin_short_name)
   {
      $res = [];
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT * FROM $wtable WHERE coin_symbol= '$coin_short_name' LIMIT 1";
      $results = Db::getInstance()->executes($bp_sql);
      if (count($results) == 1):
         $res =  $results[0];
      endif;
      return $res;
   }

   public function encrypt($value)
   {
      if (!$value) {
         return false;
      }
      $text = $value;
      $crypttext = openssl_encrypt($text, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
      return trim($crypttext);
   }

   public function decrypt($value)
   {
      if (!$value) {
         return false;
      }
      $encryption = $value;
      $decrypttext = openssl_decrypt($encryption, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
      return trim($decrypttext);
   }

   public function apiCall($url, $postVars = array())
   {

      $userAgent = 'Prestashop@' . _PS_VERSION_ . ', crplugin@' . $this->plugin_version;

      $header = array(
         "Content-Type: application/json",
         "User-Agent: " . $userAgent
      );

      if (isset($postVars['password'])) {
         array_push($header, "X-Api-Password: " . $postVars['password']);
      }
      if (isset($postVars['api_key'])) {
         array_push($header, "X-Api-Key: " . $postVars['api_key']);
      }

      unset($postVars['password']);
      unset($postVars['api_key']);

      $options = [
         'http' => [
            'header' => $header,
            'ignore_errors' =>  true,
            'method'  => 'POST', //We are using the POST HTTP method.
            'content' => json_encode($postVars)
         ],
      ];

      $streamContext  = stream_context_create($options);

      $result = file_get_contents($url, false, $streamContext);

      $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
      $logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/debug.log");

      $logger->logDebug('********** Request Param **********');
      $logger->logDebug(json_encode($postVars));
      $logger->logDebug('********** Response Param **********');
      $logger->logDebug($result);

      //If $result is FALSE, then the request has failed.
      if ($result === false) {
         return array('success' => false, 'error' => "server_error", "error_code" => 1002, 'msg' => 'Something went wrong. Please try again later.');
         $error = error_get_last();
         throw new Exception('POST request failed: ' . $error['message']);
      }
      return json_decode($result, true);
   }
}
