<?php

use Symfony\Component\Translation\TranslatorInterface;

class CoinremitterWalletsController extends ModuleAdminController
{
   public $api_url = '';
   public $success_msg = '';
   public $invoice = '';

   public function __construct()
   {
      parent::__construct();

      // Flash message
      $this->success_msg = $this->flashGet();

      // Invoice helper
      $this->invoice = new CR_Invoice();
   }

   public function init()
   {
      parent::init();
      $this->name      = 'coinremitter';
      $this->bootstrap = true;
   }

   public function initContent()
   {
      parent::initContent();

      // Handle actions
      if (Tools::isSubmit('create_wallet')) {
         return $this->AddWallet();
      }

      if (Tools::isSubmit('update_wallet')) {
         return $this->UpdateWallet();
      }
      $wallets     = $this->getWallets();
      $action      = Tools::getValue('action');
      $post_param  = Tools::getAllValues();

      if ($action) {
         if ($action === 'create') {
            $p = [
               'wallets_api'            => '',
               'wallet_password'        => '',
               'coinremitter_ex_rate'   => '1',
               'minimum_invoice_amount' => '0'
            ];

            $this->context->smarty->assign(['params' => $p]);
            return $this->setTemplate('create.tpl');
         }

         if ($action === 'edite') {
            $id      = Tools::getValue('id');
            $wallets = $this->getWalletById($id);
            $wallets['password'] = $this->invoice->decrypt($wallets['password']);

            $this->context->smarty->assign(['wallet' => $wallets]);
            return $this->setTemplate('edite.tpl');
         }

         if ($action === 'delete') {
            $id = Tools::getValue('id');
            return $this->getWalletDelete($id);
         }

         if ($action === 'save') {
            $this->context->smarty->assign(['params' => $post_param]);
            return $this->setTemplate('create.tpl');
         }

         if ($action === 'up') {
            $id = Tools::getValue('id');

            $up = [
               'api_key'                 => $post_param['wallets_api'],
               'password'                => $post_param['wallet_password'],
               'exchange_rate_multiplier' => $post_param['coinremitter_ex_rate'],
               'minimum_invoice_amount'  => $post_param['minimum_invoice_amount'],
               'id'                      => $id,
            ];

            $this->context->smarty->assign(['wallet' => $up]);
            return $this->setTemplate('edite.tpl');
         }
      }

      // Wallet listing page
      $msg = '';
      if ($this->success_msg) {
         $msg = $this->displaySuccess($this->success_msg);
      }

      $baseFiatCurrency = $this->context->currency->iso_code;

      foreach ($wallets as &$wallet) {
         $postData = [
            'api_key'  => $wallet['api_key'],
            'password' => $this->invoice->decrypt($wallet['password']),
         ];

         $result  = $this->invoice->CR_getBalance($postData);
         $balance = '<span title="Invalid API key or password. Please check credential again."><i class="material-icons">error</i></span>';

         if ($result['success']) {
            $wData = $result['data'];

            if ($wallet['base_fiat_symbol'] != $baseFiatCurrency) {
               $fiatToCryptoConversionParam = [
                  'crypto'       => $wallet['coin_symbol'],
                  'fiat'         => $wallet['base_fiat_symbol'],
                  'fiat_amount'  => $wallet['minimum_invoice_amount']
               ];

               $fiatToCryptoConversion = $this->invoice->CR_getFiatToCryptoRate($fiatToCryptoConversionParam);

               $cryptoToFiatConversionParam = [
                  'crypto'        => $wallet['coin_symbol'],
                  'crypto_amount' => $fiatToCryptoConversion['data'][0]['price'],
                  'fiat'          => $baseFiatCurrency
               ];

               $cryptoToFiatConversionRes = $this->invoice->CR_getCryptoToFiatRate($cryptoToFiatConversionParam);

               if ($cryptoToFiatConversionRes['success']) {
                  $minimumInvAmountInFiat = $cryptoToFiatConversionRes['data'][0]['amount'];
                  $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');
                  $wallet['minimum_invoice_amount'] = $minimumInvAmountInFiat;

                  $walletId = (int) $wallet['id'];
                  $updateQuery = "
                            UPDATE `coinremitter_wallets`
                            SET minimum_invoice_amount = {$minimumInvAmountInFiat},
                                base_fiat_symbol = '{$baseFiatCurrency}'
                            WHERE id = {$walletId}
                        ";
                  Db::getInstance()->execute($updateQuery);
               }
            }

            $balance = $wData['balance'];
         }

         $wallet['balance'] = $balance;
      }

      // Clear flash
      $this->flashClear();

      $this->context->smarty->assign([
         'success_msg'  => $msg,
         'wallets'      => $wallets,
         'fiat_currency' => $this->context->currency->iso_code,
         'img_path'     => Media::getMediaPath(_PS_MODULE_DIR_ . $this->name . '/img/'),
         'webhook_url'  => $this->context->link->getModuleLink('coinremitter', 'webhook'),
      ]);

      return $this->setTemplate('index.tpl');
   }

   public function getWallets()
   {
      return Db::getInstance()->executeS("SELECT * FROM coinremitter_wallets");
   }

   public function getWalletById($id)
   {
      $result = Db::getInstance()->executeS("SELECT * FROM coinremitter_wallets WHERE id = '" . pSQL($id) . "' LIMIT 1");
      return $result[0] ?? null;
   }

   public function getWalletDelete($id)
   {
      Db::getInstance()->execute("DELETE FROM coinremitter_wallets WHERE id = '" . pSQL($id) . "'");
      $this->flashSet('Wallet Delete Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }

   private function renderCreateForm($warningMessage, $params = [])
   {
      // print_r($warningMessage);
      // die;
      $this->context->smarty->assign([
         'params' => $params,
         'warning_msg' => $this->displayWarning($warningMessage),
      ]);

      $this->setTemplate('create.tpl');
   }

   private function renderEditForm($warningMessage, $params = [])
   {
      $this->context->smarty->assign([
         'wallet' => $params,
         'warning_msg' => $this->displayWarning($warningMessage),
      ]);

      $this->setTemplate('edite.tpl');
   }

   public function AddWallet()
   {
      $post_param = Tools::getAllValues();

      $postData = [
         'api_key'  => $post_param['wallets_api'],
         'password' => $post_param['wallet_password'],
      ];

      $result = $this->invoice->CR_getBalance($postData);

      if (!$result['success']) {
         return $this->displayWarning($result['msg']);
      }
      // validation
      $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];

      if ($coinremitter_ex_rate_value === '') {
         return $this->renderCreateForm('Exchange rate multiplier field is required', $post_param);
      }
      if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)) {
         return $this->renderCreateForm('Exchange rate multiplier field is invalid', $post_param);
      }
      if ($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101) {
         return $this->renderCreateForm('Exchange rate multiplier field should be between 0 to 101', $post_param);
      }

      $minimum_value = $post_param['minimum_invoice_amount'];
      if ($minimum_value === '') {
         return $this->renderCreateForm('Minimum value field is required', $post_param);
      }

      $wdata = $result['data'];
      $baseFiatCurrency = $this->context->currency->iso_code;

      $coinData = $this->invoice->CR_getCoin($wdata['coin_symbol']);
      if (empty($coinData)) {
         return $this->renderCreateForm('Coin not found', $post_param);
      }

      $unit_fiat_amount = $coinData['price_in_usd'];

      if (strtoupper($baseFiatCurrency) != 'USD') {
         $conversionParam = [
            'crypto'        => $wdata['coin_symbol'],
            'crypto_amount' => 1,
            'fiat'          => $baseFiatCurrency
         ];
         $conversionRes = $this->invoice->CR_getCryptoToFiatRate($conversionParam);

         if (!$conversionRes['success']) {
            return $this->renderCreateForm($conversionRes['msg'], $post_param);
         }

         $unit_fiat_amount = $conversionRes['data'][0]['amount'];
      }

      $minimumInvAmountInFiat = $wdata['minimum_deposit_amount'] * $unit_fiat_amount;
      $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');

      if ($minimum_value < $minimumInvAmountInFiat) {
         return $this->renderCreateForm('Minimum value should be greater than or equal to ' . $minimumInvAmountInFiat . ' ' . $baseFiatCurrency, $post_param);
      }

      $coin_image_path = dirname(__DIR__, 2) . '/img/' . strtolower($wdata['coin_symbol']) . '.png';
      if (!file_exists($coin_image_path)) {
         $url = $wdata['coin_logo'];
         if (getimagesize($url)) {
            copy($url, $coin_image_path);
         }
      }

      $wallet_add = sprintf(
         "INSERT INTO coinremitter_wallets
            (wallet_name, coin_symbol, coin_name, api_key, password, minimum_invoice_amount, exchange_rate_multiplier, unit_fiat_amount, base_fiat_symbol)
            VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %f, '%s')",
         pSQL($wdata['wallet_name']),
         pSQL($wdata['coin_symbol']),
         pSQL($wdata['coin']),
         pSQL($post_param['wallets_api']),
         pSQL($this->invoice->encrypt($post_param['wallet_password'])),
         pSQL($minimum_value),
         pSQL($coinremitter_ex_rate_value),
         $unit_fiat_amount,
         pSQL($baseFiatCurrency)
      );

      Db::getInstance()->execute($wallet_add);

      $this->flashSet('Wallet Add Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }

   public function UpdateWallet()
   {
      $post_param = Tools::getAllValues();

      $wallet_id = $post_param['id'];
      $wallet    = $this->getWalletById($wallet_id);

      if (!$wallet) {
         Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
      }

      $postData = [
         'api_key'  => $post_param['wallets_api'],
         'password' => $post_param['wallet_password'],
      ];

      $renderParam = [
         'api_key'                 => $post_param['wallets_api'],
         'password'                => $post_param['wallet_password'],
         'exchange_rate_multiplier' => $post_param['coinremitter_ex_rate'],
         'minimum_invoice_amount'  => $post_param['minimum_invoice_amount'],
         'id'                      => $wallet_id,
      ];

      $result = $this->invoice->CR_getBalance($postData);

      if (!$result['success']) {
         return $this->renderEditForm($result['msg'], $renderParam);
      }

      $wdata = $result['data'];

      // validations
      $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];

      if ($coinremitter_ex_rate_value === '') {
         return $this->renderEditForm('Exchange rate multiplier field is required', $renderParam);
      }
      if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)) {
         return $this->renderEditForm('Exchange rate multiplier field is invalid', $renderParam);
      }
      if ($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101) {
         return $this->renderEditForm('Exchange rate multiplier field should be between 0 to 101', $renderParam);
      }

      $minimum_value = $post_param['minimum_invoice_amount'];
      if ($minimum_value === '') {
         return $this->renderEditForm('Minimum value field is required', $renderParam);
      }
      if (!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $minimum_value)) {
         return $this->renderEditForm('Minimum value field is invalid', $renderParam);
      }

      $baseFiatCurrency = $this->context->currency->iso_code;

      $coinData = $this->invoice->CR_getCoin($wdata['coin_symbol']);
      if (empty($coinData)) {
         return $this->renderEditForm('Coin not found', $renderParam);
      }

      $unit_fiat_amount = $coinData['price_in_usd'];

      if (strtoupper($baseFiatCurrency) != 'USD') {
         $conversionParam = [
            'crypto'        => $wdata['coin_symbol'],
            'crypto_amount' => 1,
            'fiat'          => $baseFiatCurrency
         ];

         $conversionRes = $this->invoice->CR_getCryptoToFiatRate($conversionParam);

         if (!$conversionRes['success']) {
            return $this->renderEditForm($conversionRes['msg'], $renderParam);
         }

         $unit_fiat_amount = $conversionRes['data'][0]['amount'];
      }

      $minimumInvAmountInFiat = $wdata['minimum_deposit_amount'] * $unit_fiat_amount;
      $minimumInvAmountInFiat = number_format($minimumInvAmountInFiat, 2, '.', '');

      if ($minimum_value < $minimumInvAmountInFiat) {
         return $this->renderEditForm('Minimum value should be greater than or equal to ' . $minimumInvAmountInFiat . ' ' . $baseFiatCurrency, $renderParam);
      }

      $updateQuery = "
            UPDATE `coinremitter_wallets`
            SET wallet_name = '" . pSQL($wdata['wallet_name']) . "',
                coin_symbol = '" . pSQL($wdata['coin_symbol']) . "',
                coin_name = '" . pSQL($wdata['coin']) . "',
                api_key = '" . pSQL($post_param['wallets_api']) . "',
                password = '" . pSQL($this->invoice->encrypt($post_param['wallet_password'])) . "',
                exchange_rate_multiplier = '" . pSQL($coinremitter_ex_rate_value) . "',
                minimum_invoice_amount = '" . pSQL($minimum_value) . "',
                unit_fiat_amount = '{$unit_fiat_amount}',
                base_fiat_symbol = '" . pSQL($baseFiatCurrency) . "'
            WHERE id = '" . pSQL($wallet_id) . "'
        ";

      Db::getInstance()->execute($updateQuery);

      $this->flashSet('Wallet Updated Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token=' . Tools::getValue('token'));
   }

   public function displaySuccess($information)
   {
      return '
        <div class="alert alert-success" role="alert">
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true"><i class="material-icons">close</i></span>
            </button>
            <div class="alert-text"><p>' . $information . '</p></div>
        </div>';
   }

   /** COOKIE FLASH MESSAGE SYSTEM */
   private function flashSet($msg)
   {
      $this->context->cookie->__set('coinremitter_success', $msg);
   }

   private function flashGet()
   {
      $msg = $this->context->cookie->__get('coinremitter_success') ?? null;
      unset($this->context->cookie->coinremitter_success);
      return $msg;
   }

   private function flashClear()
   {
      unset($this->context->cookie->coinremitter_success);
   }
}
