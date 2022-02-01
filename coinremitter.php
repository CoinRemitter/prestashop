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

   include dirname(__FILE__)."/CR/CR_Invoice.php";

   use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

   if (!defined('_PS_VERSION_')) {exit;}
   class Coinremitter extends PaymentModule {

      public function __construct() {
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
         $this->description = $this->l('Accept Bitcoin, BitcoinCash, Bitcoin Gold, Ethereum, Litecoin, Dogecoin, Ripple, Tether (USDT), Dash etc via Coinremitter.');
         $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
         $this->tabClassName = 'CoinremitterWallets';
         if (!count(Currency::checkPaymentCurrencies($this->id))) {
            $this->warning = $this->l('No currency has been set for this module.');
         }
      }

      public function install(){
         if (!parent::install() || !$this->registerHook('paymentOptions') || !$this->registerHook('paymentReturn')) {
            return false;
         }
         $this->registerHook('displayHeader');
         $this->registerHook('displayAdminOrder');
         $this->registerHook('displayOrderDetail');
         $this->registerHook('moduleRoutes');

         $table_name = 'coinremitter_wallets';
         $table_name2 = 'coinremitter_payment';
         $table_name3 = 'coinremitter_order';
         $table_name4 = 'coinremitter_webhook';

         $sql = "CREATE TABLE IF NOT EXISTS $table_name(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `coin` varchar(255) NOT NULL,
            `coin_name` varchar(255) NOT NULL,
            `name` varchar(255) NOT NULL,
            `api_key` varchar(255) NOT NULL,
            `password` varchar(255) NOT NULL,
            `exchange_rate_multiplier` varchar(255) NOT NULL DEFAULT 1 COMMENT 'between 1 to 100',
            `minimum_value` varchar(255) NOT NULL DEFAULT 0,
            `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0',
            `balance` varchar(255) NOT NULL,
            `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
         )";


         $sql2 = "CREATE TABLE IF NOT EXISTS $table_name2(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'prestashop orderid',
            `address` varchar(255) NOT NULL DEFAULT '' COMMENT 'address',
            `invoice_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Invocie orderid',
            `invoice_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Invoce Name',
            `marchant_name` varchar(255) NOT NULL DEFAULT '' COMMENT 'Marchant Name',
            `total_amount` mediumtext NOT NULL DEFAULT '' COMMENT 'Total Amount',
            `paid_amount` mediumtext NOT NULL DEFAULT '' COMMENT 'Paid Amount',
            `base_currancy` varchar(255) NOT NULL DEFAULT '' COMMENT 'Base Currancy',
            `description` varchar(255) NOT NULL DEFAULT '' COMMENT 'Description',
            `coin` varchar(255) NOT NULL DEFAULT '' COMMENT 'Coin',
            `payment_history` mediumtext NOT NULL COMMENT 'Payment History',
            `conversion_rate` mediumtext NOT NULL COMMENT 'Conversion Rate',
            `invoice_url` varchar(255) NOT NULL DEFAULT '' COMMENT 'Invoice Url',
            `status` varchar(100) NOT NULL COMMENT 'Invoice Status',
            `expire_on` VARCHAR(255) NOT NULL COMMENT 'Expire On',
            `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
         )";


         $sql3 = "CREATE TABLE IF NOT EXISTS $table_name3(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `order_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'prestashop orderid',
            `invoice_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Invocie orderid',
            `address` varchar(255) NOT NULL DEFAULT '' COMMENT 'address',
            `address_qrcode` varchar(255) NOT NULL DEFAULT '' COMMENT 'address qrcode',
            `amountusd` varchar(255) NOT NULL DEFAULT '' COMMENT 'amount in usd',
            `crp_amount` varchar(255) NOT NULL DEFAULT '' COMMENT 'crp amount',
            `payment_status` varchar(255) NOT NULL DEFAULT '' COMMENT 'payment_status',
            `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
         )";

         $sql4 = "CREATE TABLE IF NOT EXISTS $table_name4(
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `address` varchar(255) NOT NULL DEFAULT '' COMMENT 'wallet address',
            `transaction_id` varchar(255) NOT NULL COMMENT 'transaction id',
            `txId` varchar(255) NOT NULL COMMENT 'txId',
            `explorer_url` varchar(255) NOT NULL COMMENT 'explorer_url',
            `paid_amount` varchar(255) NOT NULL COMMENT 'Paid Amount',
            `coin` varchar(255) NOT NULL COMMENT 'Coin',
            `confirmations` int(11) NOT NULL,
            `paid_date` datetime NOT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Invoice Created Date',
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Invoice Updated Date',
            PRIMARY KEY (`id`)
         )";

         $db = Db::getInstance();
         $db->Execute($sql);
         $db->Execute($sql2);
         $db->Execute($sql3);
         $db->Execute($sql4);

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
            $tab->active=1;
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
         $sql = 'SELECT * FROM '._DB_PREFIX_.'order_state_lang WHERE id_lang = '.$language.' ORDER BY name ASC';
         $results = Db::getInstance()->ExecuteS($sql);
         $i = 0;
         
         foreach ($results as $row) {
            $arr[$i]['id_option'] = $row['id_order_state'];
            $arr[$i]['name'] = $row['name'];
            $i++;
         }
         return $arr;
         
      }

      public function hookDisplayAdminOrder($param){
         $order = new Order($param['id_order']);
         $check_pay = $this->getPaymentDetail($param['id_order']);
         $count = count($check_pay);
         //return $order->payment;
         if($order->payment == 'Coinremitter' && $count > 0){
            return $this->order_page_detail($param['id_order']);
         }
         return '';
      }

      public function hookDisplayOrderDetail($order){
      
         $order = json_decode(json_encode($order),true);
         $orderid = Tools::getValue('id_order');
         $check_pay = $this->getPaymentDetail($orderid);
         if($order['order']['payment'] == 'Coinremitter' && !empty($check_pay)){
            return $this->front_order_page_detail($orderid);
         }
         return '';
      }

      public function getContent()
      {   
         $output = null;
         if (Tools::isSubmit('submit_coinremitter')) {
            $output_array = $this->postProcess();
            if($output_array['err'] == 0){
               $output .= $this->displayConfirmation($this->l($output_array['msg']));
            }else{
               $output .= $this->displayError($this->l($output_array['msg']));
            }
         }

         $this->context->smarty->assign(array('module_dir' => $this->_path));

         return $output.$this->renderForm();
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

      public function viewAccess($disable = false){
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
         $return_array = array('err' => 0, 'msg' =>'Configuration updated successfully');

         $form_values = $this->getConfigFormValues();
         foreach (array_keys($form_values) as $key) {
            if($key == 'coinremitter_invoice_expiry'){
               $config_value = (int)Tools::getValue($key);
            }else{
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

      public function getWallets(){

         //check if is_valid key exists or not in coinremitter_wallet table if not exists then add
         $this->checkIsValidColumnExists();

         $wtable = 'coinremitter_wallets';
         $bp_sql = "SELECT coin,id,coin_name,api_key,password,minimum_value,exchange_rate_multiplier FROM $wtable WHERE `is_valid` = 1";
         $results = Db::getInstance()->executes($bp_sql);
         return $results;
      }

      protected function checkIsValidColumnExists(){
         if(!Db::getInstance()->Execute('SELECT `is_valid` from `coinremitter_wallets`')){
            Db::getInstance()->Execute("ALTER TABLE coinremitter_wallets ADD COLUMN `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0' AFTER `password`");
         }
      }
      
      protected function generateForm(){
         
         $wallet = $this->getWallets();
         $number_of_wallet = count($wallet);


         ///////////////get cart value and convert into respective crypto value//////////////
         $cart = $this->context->cart;
         $total = (string)$cart->getOrderTotal(true, Cart::BOTH);

         $currency = $this->context->currency;
         $invoice = new CR_Invoice();
         $add_param['fiat_symbol'] = $currency->iso_code;

         for($i=0; $i<$number_of_wallet; $i++){
            $add_param['fiat_amount'] = $total * $wallet[$i]['exchange_rate_multiplier'];
            $add_param['api_key'] = $wallet[$i]['api_key'];
            $add_param['password'] = $invoice->decrypt($wallet[$i]['password']);
            $add_param['coin'] = $wallet[$i]['coin'];
            
            $currency_data = $invoice->CR_getFiatToCrypToRate($add_param);
            
            if(!isset($currency_data['flag']) || $currency_data['flag'] != 1){
               $error_link = $this->context->link->getModuleLink('coinremitter','error',array('msg'=>$currency_data['msg']));
               Tools::redirect($error_link);
            }

            //if cart value is greater than minimum value of coin, than only that coin should display in dropdown
            if($currency_data['data']['crypto_amount'] >= $wallet[$i]['minimum_value']){
               $validate_wallet[$i]['coin'] = $wallet[$i]['coin'];
               $validate_wallet[$i]['id'] = $wallet[$i]['id'];
               $validate_wallet[$i]['coin_name'] = $wallet[$i]['coin_name'];
            }

         }
         
         $des = Configuration::get('coinremitter_desc');
         $this->context->smarty->assign(array(
            'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),  
            'wallets' => $validate_wallet,
            'description' => $des,
         ));
         return $this->context->smarty->fetch($this->local_path.'views/templates/front/payment_form.tpl');
      }

      public function order_page_detail($orderid){
         //return $orderid;
         $this->updateOrderStatusInDetail($orderid);
         $sql="SELECT * FROM coinremitter_payment WHERE order_id='$orderid' LIMIT 1";
         $results = Db::getInstance()->executes($sql);
         $payment_detail = $results[0];
         $url = "";
         $invoice_id = "";
         $address = "";
         $status = "";
         $date = "";
         $coin = "";
         $base_currency = "";
         $paid_amt = 0;
         $pending_amount  = 0;
         $total_amount = 0;
         $expire_on = "";
         $payment_history = [];

         if($payment_detail['invoice_id'] != ""){
            $paid_amt = isset($payment_detail['paid_amount'])?json_decode($payment_detail['paid_amount'],true):[];
            $total_amt = isset($payment_detail['total_amount'])?json_decode($payment_detail['total_amount'],true):[];
            $payment_history = json_decode($payment_detail['payment_history'],true);
            $coin = $payment_detail['coin'];
            if($paid_amt){
               $paid_amt =$paid_amt[$coin];   
            }else{
               $paid_amt = 0;            
            }
            $total_amount = $total_amt[$coin];
            $url = $payment_detail['invoice_url'];
            $invoice_id = $payment_detail['invoice_id'];
            $status = $payment_detail['status'];
            $date = $payment_detail['date_added'];
            $coin = $payment_detail['coin'];
            $base_currency = $payment_detail['base_currancy'];
            $expire_on = $payment_detail['expire_on'];
               
         } else {
            $sql = "SELECT co.address, co.crp_amount, co.payment_status, co.date_added, cp.coin, cp.base_currancy, cp.date_added, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id = '$orderid' LIMIT 1";
            $results = Db::getInstance()->executes($sql);
            $data = $results[0];
            $address = $data['address'];
            $coin = $data['coin'];
            $date = $data['date_added']." (UTC)";
            $status = $data['payment_status'];
            $total_amount = $data['crp_amount'];
            $base_currency = $data['base_currancy'];
            $expire_on = $data['expire_on'] != "" ? $data['expire_on']." (UTC)" : "-";

            $sql= "SELECT txId, explorer_url, paid_amount, confirmations, paid_date FROM coinremitter_webhook WHERE address='$address'";
            $results = Db::getInstance()->executes($sql);
            foreach ($results as $row) {
               if($row['confirmations'] >= 3)
                  $paid_amt += $row['paid_amount'];
               $r = array();
               $r['txid'] = $row['txId'];
               $r['explorer_url'] = $row['explorer_url'];
               $r['amount'] = $row['paid_amount'];
               $r['confirmation'] = $row['confirmations'];
               $r['date'] = $row['paid_date'];
               array_push($payment_history, $r);
            }
         }

         $pending_amount = $total_amount - $paid_amt;
         $this->context->smarty->assign(array(
            'url' => $url,
            'invoice_id' => $invoice_id,
            'address' => $address,
            'status' => $status,
            'date_added' => $date,
            'coin' => $coin,
            'expire_on' => $expire_on,
            'base_currency' => $base_currency,
            'pending_amount' => number_format($pending_amount,8,".",""),
            'paid_amount' => number_format($paid_amt,8,".",""),
            'total_amount' => number_format($total_amount,8,".",""),
            'payment_history' => $payment_history,
            'invoice_url' => _PS_BASE_URL_ . __PS_BASE_URI__ .'index.php?controller=invoice&fc=module&module=coinremitter&order_id='.$orderid
         ));
         return $this->context->smarty->fetch($this->local_path.'views/templates/admin/order_detail.tpl');
      }

      public function front_order_page_detail($orderid){
         $this->updateOrderStatusInDetail($orderid);
         $payment_detail= $this->getPaymentDetail($orderid);

         $url = "";
         $invoice_id = "";
         $address = "";
         $status = "";
         $date = "";
         $coin = "";
         $paid_amt = 0;
         $pending_amount  = 0;
         $total_amount = 0;
         $payment_history = [];

         if($payment_detail['invoice_id'] != ""){
         
            $paid_amt = isset($payment_detail['paid_amount'])?json_decode($payment_detail['paid_amount'],true):[];
            $total_amt = isset($payment_detail['total_amount'])?json_decode($payment_detail['total_amount'],true):[];
            $payment_history = json_decode($payment_detail['payment_history'],true);
            $coin = $payment_detail['coin'];
            if($paid_amt){
               $paid_amt =$paid_amt[$coin];   
            }else{
               $paid_amt = 0;            
            }
            $total_amount = $total_amt[$coin];
            $url = $payment_detail['invoice_url'];
            $invoice_id = $payment_detail['invoice_id'];
            $status = $payment_detail['status'];
            $date = $payment_detail['date_added'];
            $coin = $payment_detail['coin'];
               
         } else {
            $sql = "SELECT co.address, co.crp_amount, co.payment_status, co.date_added, cp.coin FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id = '$orderid' LIMIT 1";
            $results = Db::getInstance()->executes($sql);
            $data = $results[0];
            $address = $data['address'];
            $coin = $data['coin'];
            $date = $data['date_added'];
            $status = $data['payment_status'];
            $total_amount = $data['crp_amount'];

            $sql= "SELECT txId, explorer_url, paid_amount, confirmations FROM coinremitter_webhook WHERE address='$address'";
            $results = Db::getInstance()->executes($sql);
            foreach ($results as $row) {
               if($row['confirmations'] >= 3)
                  $paid_amt += $row['paid_amount'];
               $r = array();
               $r['txid'] = $row['txId'];
               $r['explorer_url'] = $row['explorer_url'];
               array_push($payment_history, $r);
            }
         }

         $pending_amount = $total_amount - $paid_amt;
         if($pending_amount < 0){
            $pending_amount = '0.00000000';
         }
         $this->context->smarty->assign(array(
            'url' => $url,
            'invoice_id' => $invoice_id,
            'address' => $address,
            'status' => $status,
            'date' => $date,
            'coin' => $coin,
            'pending_amount' => number_format($pending_amount,8,".",""),
            'paid_amount' => number_format($paid_amt,8,".",""),
            'total_amount' => number_format($total_amount,8,".",""),
            'payment_history' => $payment_history,
            'invoice_url' => _PS_BASE_URL_ . __PS_BASE_URI__ .'index.php?controller=invoice&fc=module&module=coinremitter&order_id='.$orderid
         ));
         return $this->context->smarty->fetch($this->local_path.'views/templates/front/front_order_detail.tpl');
      }

      public function uninstall(){
         $table_name = 'coinremitter_wallets';
         $table_name2 = 'coinremitter_payment';
         $table_name3 = 'coinremitter_order';
         $table_name4 = 'coinremitter_webhook';
         $sql = "DROP TABLE $table_name";
         $sql2 = "DROP TABLE $table_name2";
         $sql3 = "DROP TABLE $table_name3";
         $sql4 = "DROP TABLE $table_name4";
         $db = Db::getInstance();
         $db->Execute($sql);
         $db->Execute($sql2);
         $db->Execute($sql3);
         $db->Execute($sql4);

         $id_tab = Tab::getIdFromClassName($this->tabClassName);
         if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
         }
         return parent::uninstall();
      }

      public function getPaymentDetail($orderid){
         $results =[];
         $wtable = 'coinremitter_payment';
         $bp_sql = "SELECT * FROM $wtable WHERE order_id = '$orderid' LIMIT 1";
         $results = Db::getInstance()->executes($bp_sql);
         if(count($results) > 0){
            $results =  $results[0];
         }
         return $results;
      }
      
      public function updateOrderStatusInDetail($orderid)
      {
         date_default_timezone_set("UTC");
         $order = new Order($orderid);
         $db = Db::getInstance();
         $sql = "SELECT co.address, co.crp_amount, co.payment_status, co.date_added, cp.coin, cp.expire_on FROM coinremitter_order as co, coinremitter_payment as cp WHERE co.address = cp.address AND co.order_id = '$orderid' LIMIT 1";
         $results = $db->executes($sql);
         $data = $results[0];
         $address = $data['address'];
         $coin = $data['coin'];
         $status = $data['payment_status'];
         $expire_on = $data['expire_on'];

         if(($status == "Pending" || $status == "Under Paid") && $order->current_state == 10){

            $sql = "SELECT confirmations,paid_amount FROM coinremitter_webhook WHERE address= '".$address."'";
            $resultWebhook = $db->executes($sql);
            $total_paid1 = 0;
            foreach ($resultWebhook as $rowWebhook) {
               $total_paid1 += number_format($rowWebhook['paid_amount'],8,".","");
            }
            $order_status = $order->current_state;

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
               $this->orderCancel($orderid);
               return;
            }

            $invoice = new CR_Invoice();
            $sql="SELECT * FROM coinremitter_webhook WHERE address='".$address."'";
            $webhookData = $db->executes($sql);

            $sql = "SELECT * FROM coinremitter_wallets WHERE coin= '".$coin."'";
            $result = $db->executes($sql);
            $Wallets = $result[0];

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
            foreach ($resultWebhook as $rowWebhook) {
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
               if($status == "Paid" || $status == "Over Paid")
                  $this->orderConfirm($orderid);
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
         header("Refresh:0");
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
            header("Refresh:0");
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
