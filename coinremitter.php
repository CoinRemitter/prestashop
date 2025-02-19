<?php
/*
   * 2007-2015 PrestaShop
   *
   * NOTICE OF LICENSE
   *
   * This source file is subject to the Academic Free License (AFL 3.0)
   * that is bundled with this package in the file LICENSE.txt.
   * It is also available through the world-wide-web at this URL:
   * http://opensource.org/licenses/afl-3.0.php
   * If you did not receive a copy of the license and are unable to
   * obtain it through the world-wide-web, please send an email
   * to license@prestashop.com so we can send you a copy immediately.
   *
   * DISCLAIMER
   *
   * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
   * versions in the future. If you wish to customize PrestaShop for your
   * needs please refer to http://www.prestashop.com for more information.
   *
   *  @author PrestaShop SA <contact@prestashop.com>
   *  @copyright  2007-2015 PrestaShop SA
   *  @license    http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
   *  International Registered Trademark & Property of PrestaShop SA
   */

include dirname(__FILE__) . "/CR/CR_Invoice.php";

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

if (!defined('_PS_VERSION_')) {
   exit;
}
class Coinremitter extends PaymentModule
{

   public function __construct()
   {
      new CR_Invoice();
      $this->name = 'coinremitter';
      $this->tab = 'wallets';
      $this->version = '1.0.2';
      $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
      $this->author = 'Coinremitter';
      $this->need_instance = 1;
      $this->is_eu_compatible = 1;
      $this->currencies = true;
      $this->currencies_mode = 'checkbox';
      $this->bootstrap = true;
      $this->controllers = array('redirect', 'callback', 'webhook');
      parent::__construct();

      $this->tabParentName = 'AdminTools';
      $this->displayName = $this->l('Coinremitter');
      $this->description = $this->l('Accept Bitcoin, Tron, Binance (BEP20), BitcoinCash, Ethereum, Litecoin, Dogecoin, Tether, Dash etc via Coinremitter.');
      $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
      $this->tabClassName = 'CoinremitterWallets';
      if (!count(Currency::checkPaymentCurrencies($this->id))) {
         $this->warning = $this->l('No currency has been set for this module.');
      }
   }

   public function install()
   {
      if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
         return false;
      }
      $this->registerHook('displayHeader');
      $this->registerHook('displayAdminOrder');
      $this->registerHook('displayOrderDetail');
      $this->registerHook('moduleRoutes');

      $sql = "CREATE TABLE IF NOT EXISTS `coinremitter_wallets`(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `wallet_name` varchar(255) NOT NULL COMMENT 'Wallet Name',
            `coin_symbol` varchar(255) NOT NULL COMMENT 'Coin Short Name',
            `coin_name` varchar(100) NOT NULL COMMENT 'Coin Full Name',
            `api_key` varchar(255) NOT NULL COMMENT 'API Key',
            `password` varchar(255) NOT NULL COMMENT 'Wallet Password',
            `minimum_invoice_amount` double(10,2) NOT NULL DEFAULT 0 COMMENT 'in fiat currency',
            `exchange_rate_multiplier` double(10,2) NOT NULL DEFAULT 1 COMMENT 'multiply order amount with this value',
            `unit_fiat_amount` double(20,4) NOT NULL DEFAULT 1 COMMENT 'crypto amount per fiat currency',
            `base_fiat_symbol` varchar(10) NOT NULL COMMENT 'Website base currency',
            `created_at` timestamp DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
         )";


      $sql2 = "CREATE TABLE IF NOT EXISTS `coinremitter_orders`(
            `id` int(11) NOT NULL AUTO_INCREMENT COMMENT 'ID',
            `order_id` varchar(255) NOT NULL COMMENT 'opencart orderid',
            `user_id` varchar(255) DEFAULT NULL COMMENT 'opencart user_id',
            `coin_symbol` varchar(10) NOT NULL COMMENT 'Coin symbol',
            `coin_name` varchar(100) NOT NULL COMMENT 'Coin full name',
            `crypto_amount` double(20,8) NOT NULL COMMENT 'Order total crypto amount',
            `fiat_symbol` varchar(10) NOT NULL COMMENT 'Fiat symbol',
            `fiat_amount` double(20,4) NOT NULL COMMENT 'Order amount in fiat currency',
            `paid_crypto_amount` double(20,8) NOT NULL DEFAULT 0 COMMENT 'Order crypto fiat amount',
            `paid_fiat_amount` double(20,4) NOT NULL DEFAULT 0 COMMENT 'Order paid fiat amount',
            `payment_address` varchar(255) NOT NULL COMMENT 'Payment address',
            `qr_code` text DEFAULT NULL COMMENT 'QR code',
            `order_status` TINYINT NOT NULL DEFAULT 0 COMMENT 'Order status; 0: pending, 1: paid, 2: under paid, 3: over paid, 4: expired, 5: cancelled',
            `transaction_meta` text DEFAULT NULL COMMENT 'Order transactions',
            `expiry_date` datetime DEFAULT NULL COMMENT 'Order expiry date',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Created At Date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Updated At Date',
            PRIMARY KEY (`id`)
         )";

      $db = Db::getInstance();
      $db->Execute($sql);
      $db->Execute($sql2);

      Configuration::updateValue('coinremitter_title', 'Payments by Cryptocurrency');
      Configuration::updateValue('coinremitter_desc', 'Secure, anonymous payment with cryptocurrency');
      Configuration::updateValue('coinremitter_ex_rate', 1);
      Configuration::updateValue('coinremitter_invoice_expiry', 60);
      Configuration::updateValue('coinremitter_order_status', 2);
      $id_tab = Tab::getIdFromClassName($this->tabClassName);
      if (!$id_tab) {
         $tab = new Tab();
         $tab->class_name = $this->tabClassName;
         $tab->id_parent = Tab::getIdFromClassName($this->tabParentName);
         $tab->module = $this->name;
         $tab->active = 1;
         $languages = Language::getLanguages();
         foreach ($languages as $language)
            $tab->name[$language['id_lang']] = $this->displayName;
         $tab->add();
      }

      return true;
   }

   protected function getConfigForm()
   {
      return array(
         'form' => array(
            'legend' => array(
               'title' => $this->l('Settings'),
               'icon' => 'icon-cogs',
            ),
            'input' => array(

               array(
                  'type' => 'text',
                  'label' => $this->l('Title'),
                  'name' => 'coinremitter_title',
                  'value' => 'Payments by Cryptocurrency',
                  'desc' => $this->l('Payment method title that the customer will see on your checkout'),
               ),
               array(
                  'type'  => 'text',
                  'label' => $this->l('Description'),
                  'name'  => 'coinremitter_desc',
                  'value' => ' Secure, anonymous payment with cryptocurrency. <a target="_blank" href="https://en.wikipedia.org/wiki/Cryptocurrency">What is it?</a>',
                  'desc'  => $this->l('Payment method description that the customer will see on your checkout'),
               ),
               array(
                  'type'  => 'text',
                  'label' => $this->l('Invoice expiry time (minutes)'),
                  'name'  => 'coinremitter_invoice_expiry',
                  'desc'  => $this->l('It indicates invoice validity. An invoice will not valid after expiry minutes. E.g if you set Invoice expiry time in Minutes 30 then the invoice will expire after 30 minutes.Set 0 to avoid expiry'),
                  'required' => true
               ),
               array(
                  'type'  => 'select',
                  'label' => $this->l('Order Status - Cryptocoin Payment Received'),
                  'name'  => 'coinremitter_order_status',
                  'desc'  => $this->l('When customer pay coinremitter invoice, What order status should be ? Set it here.'),
                  'options' => array(
                     'query' => $this->getOrderStates(),
                     'id' => 'id_option',
                     'name' => 'name',

                  ),

               ),
            ),

            'submit' => array(
               'title' => $this->l('Save'),
            ),
         ),
      );
   }

   public function getOrderStates()
   {
      $language = $this->context->employee->id_lang;
      $sql = 'SELECT * FROM ' . _DB_PREFIX_ . 'order_state_lang WHERE id_lang = ' . $language . ' ORDER BY name ASC';
      $results = Db::getInstance()->ExecuteS($sql);
      $i = 0;

      foreach ($results as $row) {
         $arr[$i]['id_option'] = $row['id_order_state'];
         $arr[$i]['name'] = $row['name'];
         $i++;
      }
      return $arr;
   }

   public function hookDisplayAdminOrder($param)
   {
      $order = new Order($param['id_order']);
      $check_pay = $this->getPaymentDetail($param['id_order']);
      $count = count($check_pay);
      //return $order->payment;
      if ($order->payment == 'Coinremitter' && $count > 0) {
         return $this->order_page_detail($param['id_order']);
      }
      return '';
   }

   public function hookDisplayOrderDetail($order)
   {

      $order = json_decode(json_encode($order), true);
      $orderid = Tools::getValue('id_order');
      $check_pay = $this->getPaymentDetail($orderid);
      if ($order['order']['payment'] == 'Coinremitter' && !empty($check_pay)) {
         return $this->front_order_page_detail($orderid);
      }
      return '';
   }

   public function getContent()
   {
      $output = null;
      if (Tools::isSubmit('submit_coinremitter')) {
         $output_array = $this->postProcess();
         if ($output_array['err'] == 0) {
            $output .= $this->displayConfirmation($this->l($output_array['msg']));
         } else {
            $output .= $this->displayError($this->l($output_array['msg']));
         }
      }

      $this->context->smarty->assign(array('module_dir' => $this->_path));

      return $output . $this->renderForm();
   }

   protected function getConfigFormValues()
   {
      return array(
         'coinremitter_order_status' => Configuration::get('coinremitter_order_status', true),
         'coinremitter_invoice_expiry' => Configuration::get('coinremitter_invoice_expiry', true),
         'coinremitter_desc' => Configuration::get('coinremitter_desc', true),
         'coinremitter_title' => Configuration::get('coinremitter_title', true),
      );
   }

   protected function renderForm()
   {
      $helper = new HelperForm();

      $helper->show_toolbar = false;
      $helper->table = $this->table;
      $helper->module = $this;
      $helper->default_form_language = $this->context->language->id;
      $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

      $helper->identifier = $this->identifier;
      $helper->submit_action = 'submit_coinremitter';
      $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
         . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
      $helper->token = Tools::getAdminTokenLite('AdminModules');

      $helper->tpl_vars = array(
         'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
         'languages' => $this->context->controller->getLanguages(),
         'id_language' => $this->context->language->id,
      );
      return $helper->generateForm(array($this->getConfigForm()));
   }

   public function hookModuleRoutes()
   {
      return [
         'module-coinremitter-ipn' => [
            'rule' => 'coinremitter/callback',
            'keywords' => [],
            'controller' => 'callback',
            'params' => [
               'fc' => 'module',
               'module' => 'coinremitter',
            ],
            'rule' => 'coinremitter/webhook',
            'keywords' => [],
            'controller' => 'webhook',
            'params' => [
               'fc' => 'module',
               'module' => 'coinremitter',
            ],
         ],
      ];
   }

   public function viewAccess($disable = false)
   {
      $result = true;
      return $result;
   }

   public function checkCurrency($cart)
   {
      $currency_order = new Currency($cart->id_currency);
      $currencies_module = $this->getCurrency($cart->id_currency);

      if (is_array($currencies_module)) {
         foreach ($currencies_module as $currency_module) {
            if ($currency_order->id == $currency_module['id_currency']) {
               return true;
            }
         }
      }
      return false;
   }

   public function postProcess()
   {
      //make validation
      $return_array = array('err' => 0, 'msg' => 'Configuration updated successfully');

      $form_values = $this->getConfigFormValues();
      foreach (array_keys($form_values) as $key) {
         if ($key == 'coinremitter_invoice_expiry') {
            $config_value = (int)Tools::getValue($key);
         } else {
            $config_value = Tools::getValue($key);
         }
         Configuration::updateValue($key, $config_value);
      }
      return $return_array;
   }

   public function hookPaymentOptions($params)
   {
      if (!$this->active || !empty($this->warning)) {
         return;
      }
      $paymentOption = new PaymentOption();
      $paymentOption->setCallToActionText(Configuration::get('coinremitter_title'))->setForm($this->generateForm());
      $payment_options = array($paymentOption);
      return $payment_options;
   }

   public function getWallets()
   {
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT id,coin_symbol,minimum_invoice_amount,unit_fiat_amount,base_fiat_symbol,exchange_rate_multiplier FROM $wtable";
      $results = Db::getInstance()->executes($bp_sql);
      return $results;
   }

   protected function generateForm()
   {

      $des = Configuration::get('coinremitter_desc');
      $wallets = $this->getWallets();
      $number_of_wallet = count($wallets);
      $cart = $this->context->cart;
      $validate_wallet = array();
      if ($number_of_wallet == 0) {

         $this->context->smarty->assign(array(
            'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
            'message' => 'No coin wallet setup',
            'description' => $des,
         ));
         return $this->context->smarty->fetch($this->local_path . 'views/templates/front/payment_form.tpl');
      }

      $cartRules = $cart->getCartRules();
      
      $couponAmount = 0;
      foreach ($cartRules as $cartRule) {
         if (isset($cartRule['value_real']) && $cartRule['value_real'] > 0) {
            $couponAmount += $cartRule['value_real'];
         }
      }
      // echo "<br>";
      // print_r($couponAmount);
      // die;

      $productTotal = $cart->getOrderTotal(true, Cart::ONLY_PRODUCTS);
      $productTotal = $productTotal - $couponAmount;
      $otherTotal = $cart->getOrderTotal(true, Cart::ONLY_SHIPPING);

      for ($i = 0; $i < $number_of_wallet; $i++) {
         $wallet = $wallets[$i];
         $orderTotal = ($productTotal * $wallet['exchange_rate_multiplier']) + $otherTotal;
         // $orderTotal = $orderTotal / $currency->conversion_rate;
         $orderTotal = Tools::convertPrice($orderTotal, Context::getContext()->currency, false);
         $orderTotal = number_format($orderTotal, 2, ".", "");
         if ($orderTotal >= $wallet['minimum_invoice_amount']) {
            array_push($validate_wallet, $wallet);
         }
      }
      $this->context->smarty->assign(array(
         'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
         'wallets' => $validate_wallet,
         'message' => 'Invoice amount is too low. Choose other payment method',
         'description' => $des,
      ));
      return $this->context->smarty->fetch($this->local_path . 'views/templates/front/payment_form.tpl');
   }

   public function order_page_detail($orderid)
   {
      //return $orderid;
      $this->updateOrderStatusInDetail($orderid);
      $sql = "SELECT * FROM `coinremitter_orders` WHERE `order_id`='$orderid' LIMIT 1";
      $order = Db::getInstance()->executes($sql);
      if (empty($order)) {
         return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/order_detail.tpl');
      }
      $order = $order[0];
      $url = "";
      $address = $order['payment_address'];
      $status = ORDER_STATUS[$order['order_status']];
      $date = $order['created_at'] . " (UTC)";
      $coin = $order['coin_symbol'];
      $base_currency = $order['fiat_symbol'];
      $paid_amt = $order['paid_crypto_amount'];
      $pending_amount = number_format($order['crypto_amount'] - $order['paid_crypto_amount'], 8, ".", "");
      $total_amount = $order['crypto_amount'];
      $expire_on = $expire_on = $order['expiry_date'] != "" ? $order['expiry_date'] . " (UTC)" : "-";
      $payment_history = $order['transaction_meta'] ? json_decode($order['transaction_meta'], true) : [];

      $this->context->smarty->assign(array(
         'url' => $url,
         'address' => $address,
         'status' => $status,
         'date_added' => $date,
         'coin' => $coin,
         'expire_on' => $expire_on,
         'base_currency' => $base_currency,
         'pending_amount' => number_format($pending_amount, 8, ".", ""),
         'paid_amount' => number_format($paid_amt, 8, ".", ""),
         'total_amount' => number_format($total_amount, 8, ".", ""),
         'payment_history' => $payment_history,
         'invoice_url' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=invoice&fc=module&module=coinremitter&order_id=' . $orderid
      ));
      return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/order_detail.tpl');
   }

   public function front_order_page_detail($orderid)
   {
      $this->updateOrderStatusInDetail($orderid);
      $sql = "SELECT * FROM coinremitter_orders WHERE order_id = '$orderid' LIMIT 1";
      $order = Db::getInstance()->executes($sql);

      if (empty($order)) {
         return $this->context->smarty->fetch($this->local_path . 'views/templates/admin/order_detail.tpl');
      }

      $order = $order[0];
      $address = $order['payment_address'];
      $status = ORDER_STATUS[$order['order_status']];
      $date = $order['created_at'] . " (UTC)";
      $coin = $order['coin_symbol'];
      $paid_amt = $order['paid_crypto_amount'];
      $pending_amount = number_format($order['crypto_amount'] - $order['paid_crypto_amount'], 8, ".", "");
      $total_amount = $order['crypto_amount'];
      $expire_on = $order['expiry_date'] != "" ? $order['expiry_date'] . " (UTC)" : "-";
      $payment_history = $order['transaction_meta'] ? json_decode($order['transaction_meta'], true) : [];

      $this->context->smarty->assign(array(
         'address' => $address,
         'status' => $status,
         'date' => $date,
         'coin' => $coin,
         'pending_amount' => number_format($pending_amount, 8, ".", ""),
         'paid_amount' => number_format($paid_amt, 8, ".", ""),
         'total_amount' => number_format($total_amount, 8, ".", ""),
         'payment_history' => $payment_history,
         'expiry_date' => $expire_on,
         'invoice_url' => _PS_BASE_URL_ . __PS_BASE_URI__ . 'index.php?controller=invoice&fc=module&module=coinremitter&order_id=' . $orderid
      ));
      return $this->context->smarty->fetch($this->local_path . 'views/templates/front/front_order_detail.tpl');
   }

   public function uninstall()
   {
      $table_name = 'coinremitter_wallets';
      $table_name2 = 'coinremitter_orders';
      $sql = "DROP TABLE IF EXISTS $table_name";
      $sql2 = "DROP TABLE IF EXISTS $table_name2";
      $db = Db::getInstance();
      $db->Execute($sql);
      $db->Execute($sql2);

      $id_tab = Tab::getIdFromClassName($this->tabClassName);
      if ($id_tab) {
         $tab = new Tab($id_tab);
         $tab->delete();
      }
      return parent::uninstall();
   }

   public function getPaymentDetail($orderid)
   {
      $results = [];
      $wtable = 'coinremitter_orders';
      $bp_sql = "SELECT * FROM $wtable WHERE order_id = '$orderid' LIMIT 1";
      $results = Db::getInstance()->executes($bp_sql);
      if (count($results) > 0) {
         $results =  $results[0];
      }
      return $results;
   }

   public function updateOrderStatusInDetail($orderid)
   {
      date_default_timezone_set("UTC");
      $order = new Order($orderid);
      $db = Db::getInstance();
      $sql = "SELECT * FROM `coinremitter_orders` WHERE `order_id` = '$orderid' LIMIT 1";
      $coinremitterOrder = $db->executes($sql);
      if (empty($coinremitterOrder)) {
         return;
      }
      $coinremitterOrder = $coinremitterOrder[0];
      $address = $coinremitterOrder['payment_address'];
      $coin = $coinremitterOrder['coin_symbol'];
      $status = $coinremitterOrder['order_status'];
      $expire_on = $coinremitterOrder['expiry_date'];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $order_status = $order->current_state;

      // if ($status != ORDER_STATUS_CODE['pending'] && $status != ORDER_STATUS_CODE['under_paid']) {
      //    return;
      // }
      // die;
      $sql = "SELECT * FROM `coinremitter_wallets` WHERE `coin_symbol`= '" . $coin . "'";
      $result = $db->executes($sql);
      if (empty($result)) {
         return;
      }
      $invoice = new CR_Invoice();
      $wallet = $result[0];
      $requestParam = [
         "api_key" => $wallet['api_key'],
         "password" => $invoice->decrypt($wallet['password']),
         "address" => $address,
      ];
      $transactionRes = $invoice->CR_getTransactionsByAddress($requestParam);
      if (!isset($transactionRes) || !$transactionRes['success']) {
         return;
      }
      $transactionData = $transactionRes['data'];
      $allTrx = $transactionData['transactions'];

      $date_diff = 0;
      if (empty($allTrx) && $expire_on != "") {
         $current = strtotime(date("Y-m-d H:i:s"));
         $expire_on = strtotime($expire_on);
         $date_diff = $expire_on - $current;
         if ($date_diff < 1 && ($order_status == 10 || $order_status == 6) && ORDER_STATUS_CODE['expired'] != $coinremitterOrder['order_status']) {
            $expiryStatus = ORDER_STATUS_CODE['expired'];
            $sql = "UPDATE `coinremitter_orders` SET `order_status`= $expiryStatus WHERE `payment_address`= '$address' ";
            $db->Execute($sql);
            $this->orderCancel($orderid);
            return;
         }
      }

      $sql = "SELECT * FROM `coinremitter_orders` WHERE `order_id` = '$orderid' LIMIT 1";
      $coinremitterOrder = $db->executes($sql);
      if (empty($coinremitterOrder)) {
         return;
      }
      $coinremitterOrder = $coinremitterOrder[0];
      $coinremitterOrder['transaction_meta'] = $coinremitterOrder['transaction_meta'] ? json_decode($coinremitterOrder['transaction_meta'], true) : [];
      $updateOrderRequired = false;
      $trxMeta = $coinremitterOrder['transaction_meta'];
      $total_paid = 0;
      foreach ($allTrx as $trx) {
         if (isset($trx['type']) && $trx['type'] == 'receive') {

            $fiat_amount = ($trx['amount'] * $coinremitterOrder['fiat_amount']) / $coinremitterOrder['crypto_amount'];
				$minFiatAmount = $wallet['minimum_invoice_amount'];
				if ($coinremitterOrder['fiat_symbol'] != 'USD') {
					$minFiatAmount = $wallet['minimum_invoice_amount'];
				}
				$minFiatAmount = number_format($minFiatAmount, 2, '.', '');
				$fiat_amount = number_format($fiat_amount, 2, '.', '');
				$currency = Currency::getIdByIsoCode($coinremitterOrder['fiat_symbol']);
				$currency = new Currency($currency);

				$fiat_amount = Tools::convertPrice($fiat_amount,$currency,false);
				if ($fiat_amount < $minFiatAmount) {
					continue;
				}


            $transactionInfo = $invoice->checkTransactionExists($coinremitterOrder['transaction_meta'], $trx['txid']);
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

      if (!$updateOrderRequired) {
         return;
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
      $trxMeta = json_encode($trxMeta);
      if ($coinremitterOrder['order_status'] == ORDER_STATUS_CODE['expired']) {
         $status = ORDER_STATUS_CODE['expired'];
      }
      $sql = "UPDATE `coinremitter_orders` SET `paid_crypto_amount`=" . $total_paid . ",`paid_fiat_amount`=" . $total_fiat_paid . ",`order_status`=" . $status . ",`transaction_meta`='" . $trxMeta . "' WHERE payment_address='" . $coinremitterOrder['payment_address'] . "'";
      $db->query($sql);

      if ($status == ORDER_STATUS_CODE['paid'] || $status == ORDER_STATUS_CODE['over_paid']) {
         $this->orderConfirm($orderid);
      }
      return true;
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
      header("Refresh:0");
   }

   public function orderConfirm($order_id)
   {
      $db = Db::getInstance();
      $order = new Order($order_id);
      $sql = "SELECT * FROM coinremitter_orders WHERE order_id='" . $order_id . "'";
      $order = $db->executes($sql);
      if (empty($order)) {
         return false;
      }
      $order = $order[0];
      if ($order['order_status'] != ORDER_STATUS_CODE["paid"] || $order['order_status'] != ORDER_STATUS_CODE['over_paid']) {
         return false;
      }
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
