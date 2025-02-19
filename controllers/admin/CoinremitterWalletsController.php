<?php

use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;

class CoinremitterWalletsController extends ModuleAdminController
{
   public $api_url = '';
   public $success_msg = '';
   public $invoice = '';

   public function __construct()
   {
      parent::__construct();
      $this->success_msg = $this->_getSession()->get('displaySuccess');
      $this->invoice = new CR_Invoice();
   }
   public function init()
   {
      parent::init();
      $this->name = 'coinremitter';
      $this->bootstrap = true;
   }
   public function initContent()
   {

      parent::initContent();

      if (Tools::isSubmit('create_wallet')) {
         $this->AddWallet();
      }

      if (Tools::isSubmit('update_wallet')) {
         $this->UpdateWallet();
      }
      $wallets  = $this->getWallets();
      $action = Tools::getValue('action');
      $post_param = Tools::getAllValues();

      if ($action) {
         if ($action == 'create') {
            $p['wallets_api'] = '';
            $p['wallet_password'] = '';
            $p['coinremitter_ex_rate'] = '1';
            $p['minimum_invoice_amount'] = '0';
            $this->context->smarty->assign(array(
               'params' => $p,
            ));
            $this->setTemplate('create.tpl');
         } else if ($action == 'edite') {
            $id = Tools::getValue('id');
            $wallets = $this->getWalletById($id);
            $wallets['password'] = $this->invoice->decrypt($wallets['password']);
            $this->context->smarty->assign(array(
               'wallet' => $wallets,
            ));
            $this->setTemplate('edite.tpl');
         } else if ($action == 'delete') {
            $id = Tools::getValue('id');
            $this->getWalletDelete($id);
         } else if ($action == 'save') {
            $this->context->smarty->assign(array(
               'params' => $post_param,
            ));
            $this->setTemplate('create.tpl');
         } else if ($action == 'up') {
            // print_r($post_param);
            // die;
            $id = Tools::getValue('id');
            $up['api_key'] = $post_param['wallets_api'];
            $up['password'] = $post_param['wallet_password'];
            $up['exchange_rate_multiplier'] = $post_param['coinremitter_ex_rate'];
            $up['minimum_invoice_amount'] = $post_param['minimum_invoice_amount'];
            $up['id'] = $id;
            $this->context->smarty->assign(array(
               'wallet' => $up,
            ));
            $this->setTemplate('edite.tpl');
         }
      } else {
         
         $msg = '';
         if ($this->success_msg) {
            $msg = $this->displaySuccess($this->success_msg);
         }
         $baseFiatCurrency = $this->context->currency->iso_code;
         foreach ($wallets as &$wallet) {

            $postData = [
               'api_key' => $wallet['api_key'],
               'password' => $this->invoice->decrypt($wallet['password']),
            ];
            $result = $this->invoice->CR_getBalance($postData);
            $balance = '<span title="Invalid API key or password. Please check credential again."><i class="material-icons">error</i></span>';
            if ($result['success']) {
               $wData = $result['data'];
               if($wallet['base_fiat_symbol'] != $baseFiatCurrency){
                  $fiatToCryptoConversionParam = array(
                     'crypto'=>$wallet['coin_symbol'],
                     'fiat'=>$wallet['base_fiat_symbol'],
                     'fiat_amount'=>$wallet['minimum_invoice_amount']
                  );
                  $fiatToCryptoConversion = $this->invoice->CR_getFiatToCryptoRate($fiatToCryptoConversionParam);
                  
                  $cryptoToFiatConversionParam = array(
                     'crypto'=>$wallet['coin_symbol'],
                     'crypto_amount'=>$fiatToCryptoConversion['data'][0]['price'],
                     'fiat'=>$baseFiatCurrency
                  );
                  $cryptoToFiatConversionRes = $this->invoice->CR_getCryptoToFiatRate($cryptoToFiatConversionParam);
                  if($cryptoToFiatConversionRes['success']){
                     $minimumInvAmountInFiat = $cryptoToFiatConversionRes['data'][0]['amount'];
                     $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
                     $wallet['minimum_invoice_amount'] = $minimumInvAmountInFiat;
                     $walletId = $wallet['id'];
                     $updateQuery = "UPDATE `coinremitter_wallets` SET minimum_invoice_amount=$minimumInvAmountInFiat,base_fiat_symbol='$baseFiatCurrency' WHERE id = $walletId";
                     $db = Db::getInstance();
                     $db->Execute($updateQuery);
                  }
               }
               $balance = $wData['balance'];
            }
            $wallet['balance'] = $balance;

         }
         $this->_getSession()->remove('displaySuccess');
         $this->context->smarty->assign(array(
            'success_msg' => $msg,
            'wallets' => $wallets,
            'fiat_currency' => $this->context->currency->iso_code,
            'img_path' => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/img/'),
            'webhook_url' => $this->context->link->getModuleLink('coinremitter', 'webhook'),
         ));
         $this->setTemplate('index.tpl');
      }
   }
   public function getWallets()
   {
      $bp_sql = "SELECT * FROM coinremitter_wallets";
      $results = Db::getInstance()->executes($bp_sql);
      return $results;
   }
   public function getWalletById($id)
   {
      $bp_sql = "SELECT * FROM coinremitter_wallets WHERE id = '$id' LIMIT 1";
      $results = Db::getInstance()->executes($bp_sql);
      if (count($results) == 1) {
         return $results[0];
      }
   }

   public function getWalletDelete($id)
   {
      $bp_d = "DELETE FROM coinremitter_wallets WHERE id = '$id'";
      $db = Db::getInstance();
      $db->Execute($bp_d);
      $this->_getSession()->set('displaySuccess', 'Wallet Delete Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }
   public function AddWallet()
   {
      $post_param = Tools::getAllValues();
      $postData = [
         'api_key' => $post_param['wallets_api'],
         'password' => $post_param['wallet_password'],
      ];
      $result = $this->invoice->CR_getBalance($postData);

      if (!$result['success']) {
         return $this->displayWarning($result['msg']);
      }

      //validation
      $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];

      if ($coinremitter_ex_rate_value == '') {

         return $this->displayWarning('Exchange rate multiplier field is required');
      } else if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)) {

         return $this->displayWarning('Exchange rate multiplier field is invalid');
      } else if ($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101) {

         return $this->displayWarning('Exchange rate multiplier field should be between 0 to 101');
      }

      $minimum_value = $post_param['minimum_invoice_amount'];

      if ($minimum_value == '') {
         return $this->displayWarning('Minimum value field is required');
      }

      $wdata = $result['data'];
      $baseFiatCurrency = $this->context->currency->iso_code;
      $coinData = $this->invoice->CR_getCoin($wdata['coin_symbol']);
      if(empty($coinData)){
         return $this->displayWarning('Coin not found');
      }
      
      $unit_fiat_amount = $coinData['price_in_usd'];
      if(strtoupper($baseFiatCurrency) != 'USD'){
         $conversionParam = array(
            'crypto'=>$wdata['coin_symbol'],
            'crypto_amount'=>1,
            'fiat'=>$baseFiatCurrency
         );
         $convertionRes = $this->invoice->CR_getCryptoToFiatRate($conversionParam);
         if(!$convertionRes['success']){
            return $this->displayWarning($convertionRes['msg']);
         }
         $unit_fiat_amount = $convertionRes['data'][0]['amount'];
      }
      $minimumInvAmountInFiat = $wdata['minimum_deposit_amount'] * $unit_fiat_amount;
      $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
      if($minimum_value < $minimumInvAmountInFiat){
         return $this->displayWarning('Minimum value should be greater than or equal to '.$minimumInvAmountInFiat.' '.$baseFiatCurrency);
      }
      
      $coin_name = $wdata['coin'];
      $coin_symbol = $wdata['coin_symbol'];
      $wallets_name = $wdata['wallet_name'];
      $api_key = $post_param['wallets_api'];
      $password = $this->invoice->encrypt($post_param['wallet_password']);

      $coin_image_path = dirname(__DIR__, 2) . '/img/' . strtolower($wdata['coin_symbol']) . '.png';
      if (!file_exists($coin_image_path)) {
         $url = $wdata['coin_logo'];
         if (getimagesize($url)) {
            copy($url, $coin_image_path);
         }
      }
      
      $wallet_add = "INSERT INTO coinremitter_wallets (wallet_name,coin_symbol,coin_name,api_key,password,minimum_invoice_amount,exchange_rate_multiplier,unit_fiat_amount,base_fiat_symbol) VALUES ('$wallets_name','$coin_symbol','$coin_name','$api_key','$password','$minimum_value','$coinremitter_ex_rate_value',$unit_fiat_amount,'$baseFiatCurrency')";
      $db = Db::getInstance();
      $db->Execute($wallet_add);

      $this->_getSession()->set('displaySuccess', 'Wallet Add Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }

   public function UpdateWallet()
   {
      $post_param = Tools::getAllValues();

      $wallet_id = $post_param['id'];
      $wallet = $this->getWalletById($wallet_id);
      if (!$wallet) {
         return Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
      }

      $postData = [
         'api_key' => $post_param['wallets_api'],
         'password' => $post_param['wallet_password'],
      ];

      $result = $this->invoice->CR_getBalance($postData);

      if(!$result['success']){
         return $this->displayWarning($result['msg']);
      }
      $wdata = $result['data'];
      //validation
      $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];

      if ($coinremitter_ex_rate_value == '') {
         return $this->displayWarning('Exchange rate multiplier field is required');
      } else if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)) {
         return $this->displayWarning('Exchange rate multiplier field is invalid');
      } else if ($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101) {
         return $this->displayWarning('Exchange rate multiplier field should be between 0 to 101');
      }

      $minimum_value = $post_param['minimum_invoice_amount'];
      if ($minimum_value == '') {
         return $this->displayWarning('Minimum value field is required');
      } else if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $minimum_value)) {
         return $this->displayWarning('Minimum value field is invalid');
      }

      $baseFiatCurrency = $this->context->currency->iso_code;
      $coinData = $this->invoice->CR_getCoin($wdata['coin_symbol']);
      if(empty($coinData)){
         return $this->displayWarning('Coin not found');
      }

      $unit_fiat_amount = $coinData['price_in_usd'];
      if(strtoupper($baseFiatCurrency) != 'USD'){
         $conversionParam = array(
            'crypto'=>$wdata['coin_symbol'],
            'crypto_amount'=>1,
            'fiat'=>$baseFiatCurrency
         );
         $convertionRes = $this->invoice->CR_getCryptoToFiatRate($conversionParam);
         if(!$convertionRes['success']){
            return $this->displayWarning($convertionRes['msg']);
         }
         $unit_fiat_amount = $convertionRes['data'][0]['amount'];
      }
      $minimumInvAmountInFiat = $wdata['minimum_deposit_amount'] * $unit_fiat_amount;
      $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
      if($minimum_value < $minimumInvAmountInFiat){
         return $this->displayWarning('Minimum value should be greater than or equal to '.$minimumInvAmountInFiat.' '.$baseFiatCurrency);
      }

      $api_key = $post_param['wallets_api'];
      $password = $this->invoice->encrypt($post_param['wallet_password']);
      $wallet_name = $wdata['wallet_name'];
      $coin_symbol = $wdata['coin_symbol'];
      $coin_name = $wdata['coin'];

      $updateQuery = "UPDATE `coinremitter_wallets` SET wallet_name='$wallet_name',coin_symbol='$coin_symbol',coin_name='$coin_name',api_key = '$api_key',password = '$password',exchange_rate_multiplier=$coinremitter_ex_rate_value,minimum_invoice_amount=$minimum_value,unit_fiat_amount=$unit_fiat_amount,base_fiat_symbol='$baseFiatCurrency' WHERE id = '$wallet_id'";
      $db = Db::getInstance();
      $db->Execute($updateQuery);
      $this->_getSession()->set('displaySuccess', 'Wallet Updated Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }

   public function displaySuccess($information)
   {
      $output = '<div class="alert alert-success" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true"><i class="material-icons">close</i></span></button><div class="alert-text"><p>' . $information . '</p></div></div>';
      return $output;
   }
   private function _getSession()
   {
      return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('session');
   }
}
