<?php
class CR_Invoice
{
   private $skey = "coinremitter";        // Store the encryption key
   private $ciphering = "AES-256-CBC"; // Store the cipher method
   private $options = 0; //a bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
   private $encryption_iv = 'Coinremitter__iv'; // Non-NULL (precisely 16 bytes) Initialization Vector for encryption

   private $plugin_version = '1.0.5';
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
      $this->migrateWalletsEncryption();
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

   private static $migrationChecked = false;

   public function migrateWalletsEncryption()
   {
      $logger = new FileLogger(0); //0 == debug level, logDebug() won’t work without this.
      $logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/debug.log");

      // Skip if already checked in this request lifecycle
      if (self::$migrationChecked) {
         $logger->logDebug("migrateWalletsEncryption: Wallets encryption migration already checked in this request. Skipping.");
         return;
      }

      // Skip if previously completed and stored in database configuration
      if (class_exists('Configuration') && Configuration::get('COINREMITTER_WALLETS_MIGRATED')) {
         $logger->logDebug("migrateWalletsEncryption: Wallets encryption migration already completed. Skipping.");
         self::$migrationChecked = true;
         return;
      }

      if (!class_exists('Db')) {
         $logger->logDebug("migrateWalletsEncryption: Db class not found. Skipping migration.");
         return;
      }

      try {
         $db = Db::getInstance();
         $tableCheck = $db->executeS("SHOW TABLES LIKE 'coinremitter_wallets'");
         if (empty($tableCheck)) {
            return;
         }
         $wallets = $db->executeS("SELECT id, password FROM `coinremitter_wallets`");
         if (!empty($wallets)) {
            $siteKey = $this->getSecretKey();
            $siteIv = $this->getEncryptionIv();
            $migratedCount = 0;
            foreach ($wallets as $wallet) {
               $rawCipher = $wallet['password'];
               $testSite = openssl_decrypt($rawCipher, $this->ciphering, $siteKey, $this->options, $siteIv);
               if ($testSite !== false && $testSite !== '') {
                  $logger->logDebug("migrateWalletsEncryption: Wallet ID {$wallet['id']} already encrypted with site key. Skipping.");
                  continue; // Already encrypted with site key
               }
               $legacyPlain = openssl_decrypt($rawCipher, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
               if ($legacyPlain !== false && $legacyPlain !== '') {
                  $newCipher = openssl_encrypt($legacyPlain, $this->ciphering, $siteKey, $this->options, $siteIv);
                  if ($newCipher) {
                     $db->execute("UPDATE `coinremitter_wallets` SET `password` = '" . pSQL($newCipher) . "' WHERE `id` = " . (int)$wallet['id']);
                     $migratedCount++;
                  }
               }
            }
            if ($migratedCount > 0) {
               $logger->logDebug("migrateWalletsEncryption: Migrated {$migratedCount} wallet password(s) from legacy key to site-specific encryption.");
            }
         }

         // Mark migration as completed permanently so it never queries again
         if (class_exists('Configuration')) {
            $logger->logDebug("migrateWalletsEncryption: Marking wallets migration as completed in Configuration.");
            Configuration::updateValue('COINREMITTER_WALLETS_MIGRATED', 1);
         }
         self::$migrationChecked = true;
      } catch (\Exception $e) {
         $logger->logDebug("migrateWalletsEncryption: Exception occurred during migration: " . $e->getMessage());
      }
   }

   public function CR_getWallet($coin_short_name)
   {
      $res = [];
      if (empty($coin_short_name)) {
         return $res;
      }
      $escaped_coin = pSQL($coin_short_name);
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT * FROM `$wtable` WHERE `coin_symbol`= '$escaped_coin' LIMIT 1";
      $results = Db::getInstance()->executeS($bp_sql);
      if (is_array($results) && count($results) == 1):
         $res =  $results[0];
      endif;
      return $res;
   }

   private function getSecretKey()
   {
      if (defined('_COOKIE_KEY_') && !empty(_COOKIE_KEY_)) {
         return hash('sha256', _COOKIE_KEY_ . 'coinremitter_secret');
      }
      if (class_exists('Configuration')) {
         $key = Configuration::get('COINREMITTER_ENC_KEY');
         if (empty($key)) {
            $key = function_exists('random_bytes') ? bin2hex(random_bytes(16)) : md5(uniqid('cr_', true));
            Configuration::updateValue('COINREMITTER_ENC_KEY', $key);
         }
         return hash('sha256', $key);
      }
      return hash('sha256', $this->skey);
   }

   private function getEncryptionIv()
   {
      if (defined('_COOKIE_IV_') && !empty(_COOKIE_IV_)) {
         return substr(hash('md5', _COOKIE_IV_ . 'coinremitter_iv'), 0, 16);
      }
      return substr(hash('md5', $this->getSecretKey() . 'coinremitter_iv'), 0, 16);
   }

   public function encrypt($value)
   {
      if (!$value) {
         return false;
      }
      $text = $value;
      $siteKey = $this->getSecretKey();
      $siteIv = $this->getEncryptionIv();
      $crypttext = openssl_encrypt($text, $this->ciphering, $siteKey, $this->options, $siteIv);
      return trim($crypttext);
   }

   public function decrypt($value)
   {
      if (!$value) {
         return false;
      }
      $encryption = $value;
      $siteKey = $this->getSecretKey();
      $siteIv = $this->getEncryptionIv();
      $decrypttext = openssl_decrypt($encryption, $this->ciphering, $siteKey, $this->options, $siteIv);
      if ($decrypttext !== false && $decrypttext !== '') {
         return trim($decrypttext);
      }
      // Fallback for legacy encrypted passwords
      $legacyText = openssl_decrypt($encryption, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
      if ($legacyText !== false && $legacyText !== '') {
         return trim($legacyText);
      }
      return false;
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
