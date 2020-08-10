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

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;


if (!defined('_PS_VERSION_')) {exit;}
class Coinremitter extends PaymentModule {
  public function __construct() {
        $this->name = 'coinremitter';
        $this->tab = 'wallets';
        $this->version = '1.0.1';
        $this->ps_versions_compliancy = array('min' => '1.7', 'max' => _PS_VERSION_);
        $this->author = 'Coinremitter';
        $this->need_instance = 1;
        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->controllers = array('redirect', 'callback');
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
        $this->registerHook('displayAdminOrderLeft');
        $this->registerHook('displayOrderDetail');
        $this->registerHook('moduleRoutes');

        $table_name = 'coinremitter_wallets';
        $table_name2 = 'coinremitter_payment';
        $table_name3 = 'coinremitter_order';

        $sql = "CREATE TABLE IF NOT EXISTS $table_name(
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `coin` varchar(255) NOT NULL,
        `coin_name` varchar(255) NOT NULL,
        `name` varchar(255) NOT NULL,
        `api_key` varchar(255) NOT NULL,
        `password` varchar(255) NOT NULL,
        `balance` varchar(255) NOT NULL,
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        )";


        $sql2 = "CREATE TABLE IF NOT EXISTS $table_name2(
          `id` int(11) NOT NULL AUTO_INCREMENT,
          `order_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'magento orderid',
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
          `expire_on` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'Invoice Url',
          `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        )";


        $sql3 = "CREATE TABLE IF NOT EXISTS $table_name3(
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `order_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'magento orderid',
        `invoice_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'Invocie orderid',
        `amountusd` varchar(255) NOT NULL DEFAULT '' COMMENT 'amount in usd',
        `crp_amount` varchar(255) NOT NULL DEFAULT '' COMMENT 'crp amount',
        `payment_status` varchar(255) NOT NULL DEFAULT '' COMMENT 'payment_status',
        `date_added` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`)
        )";

        $db = Db::getInstance();
        $db->Execute($sql);
        $db->Execute($sql2);
        $db->Execute($sql3);

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
                        'label' => $this->l('Exchange Rate Multiplier'),
                        'name'  => 'coinremitter_ex_rate',
                        'value' => '',
                        'desc'  => $this->l('The system will fetch LIVE cryptocurrency rates from coinremitter.com. Check here @ https://coinremitter.com/api/get-coin-rate  for current USD price Example: 1.05 - will add an extra 5% to the total price in bitcoin/altcoins, 0.85 - will be a 15% discount for the price in bitcoin/altcoins. Default: 1.00'),
                    ),
                    array(
                        'type'  => 'text',
                        'label' => $this->l('Invoice expiry time in Minutes'),
                        'name'  => 'coinremitter_invoice_expiry',
                        'desc'  => $this->l('It indicates invoice validity. An invoice will not valid after expiry minutes. E.g if you set Invoice expiry time in Minutes 30 then the invoice will expire after 30 minutes.Set 0 to avoid expiry'),

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
        $sql = 'SELECT * FROM ps_order_state_lang WHERE id_lang = '.$language.' ORDER BY name ASC';
        $results = Db::getInstance()->ExecuteS($sql);
        $i = 0;
        
        foreach ($results as $row) {
            $arr[$i]['id_option'] = $row['id_order_state'];
            $arr[$i]['name'] = $row['name'];
            $i++;
        }
        return $arr;
       
    }
    public function hookDisplayAdminOrderLeft($param){
        $order = new Order($param['id_order']);
        $check_pay = $this->getPaymenDetail($param['id_order']);
        $count = count($check_pay);
        if($order->payment == 'Coinremitter' && $count > 0){
            return $this->order_page_detail($param['id_order']);
        }
        return '';
    }
    public function hookDisplayOrderDetail($order){
     
        $order = json_decode(json_encode($order),true);
        $orderid = Tools::getValue('id_order');
        $check_pay = $this->getPaymenDetail($orderid);
        if($order['order']['payment'] == 'Coinremitter' && !empty($check_pay)){
            return $this->front_order_page_detail($orderid);
        }
        return '';
    }
    public function getContent()
    {
        if (Tools::isSubmit('submit_coinremitter')) {
            $this->postProcess();
        }

        $this->context->smarty->assign(array('module_dir' => $this->_path));

        return $this->renderForm();
    }
    
     protected function getConfigFormValues()
    {
        return array(
            'coinremitter_order_status' => Configuration::get('coinremitter_order_status', true),
            'coinremitter_invoice_expiry' => Configuration::get('coinremitter_invoice_expiry', true),
            'coinremitter_ex_rate' => Configuration::get('coinremitter_ex_rate', true),
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
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    public function hookPaymentOptions($params)
    {
        if (!$this->active || !empty($this->warning)) {
            return;
        }

        $paymentOption = new PaymentOption();
        $paymentOption->setCallToActionText(Configuration::get('coinremitter_title'))
            ->setForm($this->generateForm());
        $payment_options = array($paymentOption);

        return $payment_options;
    }

    public function getWallets(){
        $wtable = 'coinremitter_wallets';
        $bp_sql = "SELECT coin,id,coin_name FROM $wtable";
        $results = Db::getInstance()->executes($bp_sql);
        return $results;
    }
   
    protected function generateForm(){
        $wallet = $this->getWallets();
        $des = Configuration::get('coinremitter_desc');
        $this->context->smarty->assign(array(
            'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
            'wallets' => $wallet,
            'description' => $des,
        ));
        return $this->context->smarty->fetch($this->local_path.'views/templates/front/payment_form.tpl');
    }
    public function order_page_detail($orderid){
       
        $payment_detail= $this->getPaymenDetail($orderid);
        $payment_history = isset($payment_detail['payment_history'])?json_decode($payment_detail['payment_history'],true):[];
        $conversation_rate =isset($payment_detail['conversion_rate'])?json_decode($payment_detail['conversion_rate'],true):[];
        $paid_amt = isset($payment_detail['paid_amount'])?json_decode($payment_detail['paid_amount'],true):[];
        $total_amt = isset($payment_detail['total_amount'])?json_decode($payment_detail['total_amount'],true):[];
        $invoice_url = isset($payment_detail['invoice_url'])?$payment_detail['invoice_url']:'';
        $invoice_detail = isset($payment_detail)?$payment_detail:[];
        $this->context->smarty->assign(array(
            'action' => $this->context->link->getModuleLink($this->name, 'redirect', array(), true),
            'payment_history' => $payment_history,
            'conversation_rate'=>$conversation_rate,
            'inv_detail'=>$invoice_detail,
            'paid_amt'=>$paid_amt,
            'total_amt'=>$total_amt,
        ));
        return $this->context->smarty->fetch($this->local_path.'views/templates/admin/order_detail.tpl');
    }

    public function front_order_page_detail($orderid){   
        $payment_detail= $this->getPaymenDetail($orderid);
       
        $url = $payment_detail['invoice_url'];
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
        $panding_amount = $total_amount - $paid_amt;
        if($panding_amount < 0){
            $panding_amount = '0.00000000';
        }
        $this->context->smarty->assign(array(
            'url' => $payment_detail['invoice_url'],
            'invoice_id' => $payment_detail['invoice_id'],
            'status'=>$payment_detail['status'],
            'date'=>$payment_detail['date_added'],
            'coin'=>$payment_detail['coin'],
            'panding_amount'=>$panding_amount,
            'total_amount'=>$total_amount,
            'payment_history'=>$payment_history,
        ));
        return $this->context->smarty->fetch($this->local_path.'views/templates/front/front_order_detail.tpl');
    }

    public function uninstall(){
        $table_name = 'coinremitter_wallets';
        $table_name2 = 'coinremitter_payment';
        $table_name3 = 'coinremitter_order';
        $sql = "DROP TABLE $table_name";
        $sql2 = "DROP TABLE $table_name2";
        $sql3 = "DROP TABLE $table_name3";
        $db = Db::getInstance();
        $db->Execute($sql);
        $db->Execute($sql2);
        $db->Execute($sql3);

        $id_tab = Tab::getIdFromClassName($this->tabClassName);
       if ($id_tab) {
         $tab = new Tab($id_tab);
         $tab->delete();
       }
      return parent::uninstall();
    }
    public function getPaymenDetail($orderid){
        $results =[];
        $wtable = 'coinremitter_payment';
        $bp_sql = "SELECT * FROM $wtable WHERE order_id = '$orderid' LIMIT 1";
        $results = Db::getInstance()->executes($bp_sql);
        if(count($results) > 0){
            $results =  $results[0];
        }
        return $results;
    }
   
}