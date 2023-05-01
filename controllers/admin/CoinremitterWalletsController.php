<?php
use Symfony\Component\Translation\Translator;
use Symfony\Component\Translation\TranslatorInterface;   

class CoinremitterWalletsController extends ModuleAdminController{
   public $api_url = '' ;
   public $success_msg = '';
   public $invoice = '';

   public function __construct(){
      parent::__construct();
      $this->success_msg = $this->_getSession()->get('displaySuccess');
      $this->invoice = new CR_Invoice();
      
   }
   public function init(){
      parent::init();
      $this->name = 'coinremitter';
      $this->bootstrap = true;
   }
   public function initContent(){

      parent::initContent();

      //check if is_valid key is exists or not in coinremitter_wallet table if not exists then add
      $this->checkIsValidColumnExists();

      //update all wallets' balances
      $this->update_wallet_balance();

      if(Tools::isSubmit('create_wallet')) {
         $this->AddWallet();
      }

      if(Tools::isSubmit('update_wallet')) {
         $this->UpdateWallet();
      }
      $wallets  = $this->getWallets();
      $action = Tools::getValue('action');
      $post_param = Tools::getAllValues();

      if($action){
         if($action == 'create'){
            $p['wallets_api'] = '';
            $p['wallet_password'] ='';
            $p['coinremitter_ex_rate'] = '1';
            $p['minimum_value'] = '0.01'; 
            $coins =$this->getSelectCoinList();
            $this->context->smarty->assign(array(
               'coins'=>$coins,
               'params'=>$p,
            ));
            $this->setTemplate('create.tpl');
         }else if ($action == 'edite') {
            $id = Tools::getValue('id');
            $wallets = $this->getWalletById($id);
            $wallets['password'] = $this->invoice->decrypt($wallets['password']);
            $this->context->smarty->assign(array(
              'wallet'=>$wallets,
            ));
            $this->setTemplate('edite.tpl');
         }else if ($action == 'delete') {
            $id = Tools::getValue('id');
            $this->getWalletDelete($id);
         }else if($action == 'save'){
            $coins =$this->getSelectCoinList();
            $this->context->smarty->assign(array(
               'coins'=>$coins,
               'params'=>$post_param,
            ));
            $this->setTemplate('create.tpl');
         }else if($action == 'up'){

            $id = Tools::getValue('id');
            $wallets = $this->getWalletById($id);
            $up['api_key'] = $post_param['wallets_api'];
            $up['password'] = $post_param['wallet_password'];
            $up['exchange_rate_multiplier'] = $post_param['coinremitter_ex_rate'];
            $up['minimum_value'] = $post_param['minimum_value'];
            $up['id'] =$id; 
            $up['coin'] = $wallets['coin'];
            $this->context->smarty->assign(array(
              'wallet'=>$up,
            ));
            $this->setTemplate('edite.tpl');
         }
      }else{
         $msg = '';
         if($this->success_msg){
            $msg = $this->displaySuccess($this->success_msg);
         }
         foreach ($wallets as &$wallet) {
            if($wallet['is_valid'] == 1){
               $balance = number_format($wallet['balance'],8,'.','');
            }else{
               $balance = '<span title="Invalid API key or password. Please check credential again."><i class="material-icons">error</i></span>';
            }
            $wallet['balance'] = $balance;
         }
         $this->_getSession()->remove('displaySuccess');
         $this->context->smarty->assign(array(
            'success_msg' =>$msg,
            'wallets'=>$wallets,
            'img_path' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/img/'),
            'webhook_url' => $this->context->link->getModuleLink('coinremitter','webhook'),
         ));
         $this->setTemplate('index.tpl');
      }
   }
   public function getWallets(){
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT * FROM $wtable";
      $results = Db::getInstance()->executes($bp_sql);
      return $results;
  }
  public function getWalletById($id){
      $wtable = 'coinremitter_wallets';
      $bp_sql = "SELECT * FROM $wtable WHERE id = '$id' LIMIT 1";
      $results = Db::getInstance()->executes($bp_sql);
      if (count($results) == 1){
         return $results[0];
      }
   }

  public function getWalletDelete($id){
      $wtable = 'coinremitter_wallets';
      $bp_d = "DELETE FROM $wtable WHERE id = '$id'";
      $db = Db::getInstance();
      $db->Execute($bp_d);
      $this->_getSession()->set('displaySuccess', 'Wallet Delete Successfully');
      Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token='.Tools::getValue('token'));
   }
   public function AddWallet(){
      $post_param = Tools::getAllValues();

      
      $postData = [
         'api_key'=>$post_param['wallets_api'],
         'password'=>$post_param['wallet_password'],
         'coin'=>$post_param['coin'],
      ];
      $result = $this->invoice->CR_getBalance($postData);
      /*download wallet image if not exist*/
      $coin_image_path = dirname(__DIR__,2).'/img/'.strtolower($post_param['coin']).'.png';
      if(!file_exists($coin_image_path)){
         $url = "https://coinremitter.com/assets/img/home-coin/coin/".strtolower($post_param['coin']).".png";
         if (getimagesize($url)) {
            copy($url,$coin_image_path);
         }
      }
    
      if($result['flag'] == 1){

         //validation
         $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];
      
         if($coinremitter_ex_rate_value == ''){

            return $this->displayWarning('Exchange rate multiplier field is required'); 
            
         }else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)){
                  
            return $this->displayWarning('Exchange rate multiplier field is invalid');
            
         }else if($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101){

            return $this->displayWarning('Exchange rate multiplier field should be between 0 to 101');
         }

         $minimum_value = $post_param['minimum_value'];

         if($minimum_value == ''){

            return $this->displayWarning('Minimum value field is required'); 
            
         }else if($minimum_value < 0.0001 || $minimum_value >= 1000000){

            return $this->displayWarning('Invoice Minimum value should be between 0.0001 to 1000000');
         }


         $wdata = $result['data']; 
         $coin_name = $wdata['coin_name'];
         $wallets_name =$wdata['wallet_name'];
         $wallet_table = 'coinremitter_wallets';
         $coin = isset($post_param['coin'])?$post_param['coin']:'';
         $api_key = $post_param['wallets_api'];
         $password = $this->invoice->encrypt($post_param['wallet_password']);
         $balance = $wdata['balance'];
         $wallet_add = "INSERT INTO $wallet_table (coin,coin_name,name,api_key,password,balance,exchange_rate_multiplier,minimum_value,date_added) VALUES ('$coin','$coin_name','$wallets_name','$api_key','$password','$balance','$coinremitter_ex_rate_value','$minimum_value',NOW())";
         $db = Db::getInstance();
         $db->Execute($wallet_add);
         $this->_getSession()->set('displaySuccess', 'Wallet Add Successfully');
         Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token='.Tools::getValue('token'));    
      }else{
         $this->displayWarning($result['msg']);    
      }
   }

   public function UpdateWallet(){
      $post_param = Tools::getAllValues();

      $wallet_id = $post_param['wallet_id'];
      $wallet = $this->getWalletById($wallet_id);
    
      if($wallet){
         $postData = [
            'api_key'=>$post_param['wallets_api'],
            'password'=>$post_param['wallet_password'],
            'coin'=>$wallet['coin'],
         ];

         $result = $this->invoice->CR_getBalance($postData);
         $wallet_table = 'coinremitter_wallets';
         if($result['flag'] == 1){

            //validation
            $coinremitter_ex_rate_value = $post_param['coinremitter_ex_rate'];
      
            if($coinremitter_ex_rate_value == ''){

               return $this->displayWarning('Exchange rate multiplier field is required'); 
               
            }else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $coinremitter_ex_rate_value)){
                     
               return $this->displayWarning('Exchange rate multiplier field is invalid');
               
            }else if($coinremitter_ex_rate_value <= 0 || $coinremitter_ex_rate_value >= 101){

               return $this->displayWarning('Exchange rate multiplier field should be between 0 to 101');
            }

            $minimum_value = $post_param['minimum_value'];

            if($minimum_value == ''){

               return $this->displayWarning('Minimum value field is required'); 
               
            }else if(!preg_match('/^[0-9]+(\.[0-9]{1,2})?$/', $minimum_value)){
                     
               return $this->displayWarning('Minimum value field is invalid'); 

            }else if($minimum_value < 0.01 || $minimum_value >= 1000000){

               return $this->displayWarning('Invoice Minimum value should be between 0.01 to 1000000');
            }

            $wdata = $result['data']; 
            $api_key = $post_param['wallets_api'];
            $password = $this->invoice->encrypt($post_param['wallet_password']);
            $balance = $wdata['balance'];
            $is_valid = 1;
            $wallet_name = $wdata['wallet_name'];
            $wallet_up = "UPDATE $wallet_table SET api_key = '$api_key',password = '$password',is_valid = $is_valid,name='$wallet_name',balance='$balance',exchange_rate_multiplier='$coinremitter_ex_rate_value',minimum_value='$minimum_value' WHERE id = '$wallet_id'";
            $db = Db::getInstance();
            $db->Execute($wallet_up);
            $this->_getSession()->set('displaySuccess', 'Wallet Updated Successfully');
            Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token='.Tools::getValue('token'));    
         }else{
            //if wallet deleted from coinremitter merchant site then update balance as 0 and is_valid = 0 in prestashop db 
            if(is_array($wallet)){
               $wallet['password'] = $this->invoice->decrypt($wallet['password']);
               $get_bal_res = $this->invoice->CR_getBalance($wallet);
               if(isset($get_bal_res['flag']) && $get_bal_res['flag'] != 1 ){
                  $balance = 0;
                  $is_valid = 0;
                  Db::getInstance()->Execute("UPDATE $wallet_table SET balance='$balance',is_valid = $is_valid WHERE id = '$wallet_id'");
               }
            }
            $this->displayWarning($result['msg']);    
         }
      }
   }
  
   public function getSelectCoinList(){
      $walletArr =[];
      $results = $this->getWallets();
      if ($results) {
         foreach ($results as $key => $value) {
            array_push($walletArr, $value['coin']);
         }   
      }
      $coin = [];
      $data = $this->invoice->CR_getCoin();
      if ($data) {
         foreach ($data as $key => $value) {
            $c['value'] = $value['symbol'];
            $c['label'] = $value['symbol'];
            if ($walletArr) {
               if (!in_array($c['value'], $walletArr)) {
                  array_push($coin, $c);   
               }
            }else{
               array_push($coin, $c);
            }
         }    
      }
      return $coin;
   }
  
   public function displaySuccess($information){
      $output = '<div class="alert alert-success" role="alert"><button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true"><i class="material-icons">close</i></span></button><div class="alert-text"><p>'.$information.'</p></div></div>';
      return $output;
   }
   private function _getSession()
   {
      return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('session');
   }
  
   public function update_wallet_balance(){
      $wallets  = $this->getWallets();
      if($wallets){
         $wallet_table = 'coinremitter_wallets';
         foreach ($wallets as $w) {
            $postData = [
               'api_key'=>$w['api_key'],
               'password'=>$this->invoice->decrypt($w['password']),
               'coin'=>$w['coin'],
            ];
            $wallet_id = $w['id'];
            $result = $this->invoice->CR_getBalance($postData);
            if($result['flag'] == 1){
               $balance = $result['data']['balance'];
               $is_valid = 1;
            }else{
               $balance = 0;
               $is_valid = 0;
            }
            $wallet_up = "UPDATE ".$wallet_table." SET `balance` = '".$balance."',`is_valid` = ".$is_valid." WHERE id = ".$wallet_id;
            $db = Db::getInstance();
            $db->Execute($wallet_up);
         }
      }
   }

   protected function checkIsValidColumnExists(){
      if(!Db::getInstance()->Execute('SELECT `is_valid` from `coinremitter_wallets`')){
         Db::getInstance()->Execute("ALTER TABLE coinremitter_wallets ADD COLUMN `is_valid` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1 on valid wallet else 0' AFTER `password`");
      }
   }
}
