<?php
require 'Request.php';
class CR_Invoice
{	
   public $api_url = '' ;
   public $request = '' ;
   public $version = '';
   public $skey = "coinremitter";        // Store the encryption key
   public $ciphering = "AES-256-CBC"; // Store the cipher method
   public $options = 0; //a bitwise disjunction of the flags OPENSSL_RAW_DATA and OPENSSL_ZERO_PADDING.
   public $encryption_iv = 'Coinremitter__iv'; // Non-NULL (precisely 16 bytes) Initialization Vector for encryption

	public function __construct(){
      $this->api_base_url = 'https://coinremitter.com/api'; 
      $this->version = 'v3';
      $this->request = new Request();
	}
      
   public function CR_createAddress($param){
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-new-address";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_getFiatToCrypToRate($param) {
      // print_r($param);
      // die();
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-fiat-to-crypto-rate";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_get_transactions_by_address($param){
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-transaction-by-address";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_getInvoice($param){
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-invoice";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_getTransaction($param){
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-transaction";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_getBalance($param){
      $api_base_url = $this->api_base_url.'/'.$this->version;
      $url =  $api_base_url."/".$param['coin']."/get-balance";
      $res = $this->request->post($url,$param);
      return $res;
   }

   public function CR_getCoin(){
      $api_base_url = $this->api_base_url;
      $url =  $api_base_url."/get-coin-rate";
      $res = $this->request->get($url);
      if (isset($res['flag']) && $res['flag'] == 1) {
         $res = $res['data'];
      }
      return $res;
   }

  	public function CR_getWallet($coin_short_name){
      $res = [];
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT * FROM $wtable WHERE coin = '$coin_short_name' LIMIT 1";
      $results = Db::getInstance()->executes($bp_sql);
      if (count($results) == 1):
         $res =  $results[0];
      endif;
      return $res;
  	}

   public function encrypt($value){
      if(!$value){
         return false;
      }
      $text = $value;
      $crypttext = openssl_encrypt($text, $this->ciphering, $this->skey, $this->options, $this->encryption_iv); 
      return trim($crypttext);
   }

   public function decrypt($value) {
      if (!$value) {
         return false;
      }
      $encryption = $value;
      $decrypttext=openssl_decrypt($encryption, $this->ciphering, $this->skey, $this->options, $this->encryption_iv);
      return trim($decrypttext);
   }
}
