<?php
include dirname(__DIR__, 2) . "/CR/CR_Invoice.php";

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
     	$wallets  = self::getWallets();
     	$action = Tools::getValue('action');
     	$post_param = Tools::getAllValues();
      $this->update_wallet_balance();
     	if(Tools::isSubmit('create_wallet')) {
           self::AddWallet();
      }

      if(Tools::isSubmit('update_wallet')) {
         self::UpdateWallet();
      }

     	if($action){
        if($action == 'create'){
            $p['wallets_api'] = '';
            $p['wallet_password'] ='';
            $coins =self::getSelectCoinList();
            $this->context->smarty->assign(array(
             'coins'=>$coins,
             'params'=>$p,
            ));

            $this->setTemplate('create.tpl');
        }else if ($action == 'edite') {
            $id = Tools::getValue('id');
            $wallets = self::getWalletById($id);

            $wallets['password'] = $this->invoice->decrypt($wallets['password']);
            $this->context->smarty->assign(array(
              'wallet'=>$wallets,
            ));
            $this->setTemplate('edite.tpl');
        }else if ($action == 'delete') {
              $id = Tools::getValue('id');
              self::getWalletDelete($id);
        }else if($action == 'save'){
            $coins =self::getSelectCoinList();
            $this->context->smarty->assign(array(
             'coins'=>$coins,
             'params'=>$post_param,
            ));
            $this->setTemplate('create.tpl');
     	  }else if($action == 'up'){
            $id = Tools::getValue('id');
            $wallets = self::getWalletById($id);
            $up['api_key'] = $post_param['wallets_api'];
            $up['password'] = $post_param['wallet_password'];
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
              $msg = self::displaySuccess($this->success_msg);
            }
         		$this->context->smarty->assign(array(
                'success_msg' =>$msg,
    	      		'wallets'=>$wallets,
                'img_path' => Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/img/'),
          	));
            $this->success_msg = '';
            $this->_getSession()->set('displaySuccess','');
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
    if (count($results) == 1):
        return $results[0];
    endif;
  }

  public function getWalletDelete($id){
    $wtable = 'coinremitter_wallets';
    $bp_d = "DELETE FROM $wtable WHERE id = '$id'";
    $db = Db::getInstance();
    $db->Execute($bp_d);
    $this->_getSession()->set('displaySuccess', 'Wallet Delete Success Fully');
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
    if($result['flag'] == 1){
        $wdata = $result['data']; 
        $coin_name = $wdata['coin_name'];
        $wallets_name =$wdata['wallet_name'];
        $wallet_table = 'coinremitter_wallets';
        $coin = isset($post_param['coin'])?$post_param['coin']:'';
        $api_key = $post_param['wallets_api'];
        $password = $this->invoice->encrypt($post_param['wallet_password']);
        $balance = $wdata['balance'];
        $wallet_add = "INSERT INTO $wallet_table (coin,coin_name,name,api_key,password,balance,date_added)
                VALUES ('$coin','$coin_name','$wallets_name','$api_key','$password',$balance,NOW())";
        $db = Db::getInstance();
        $db->Execute($wallet_add);
        $this->_getSession()->set('displaySuccess', 'Wallet Add Success Fully');
        Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token='.Tools::getValue('token'));    
    }else{
      $this->displayWarning($result['msg']);    
    }
    
  }


  public function UpdateWallet(){
    $post_param = Tools::getAllValues();
    $wallet_id = $post_param['wallet_id'];
    $wallet = self::getWalletById($wallet_id);
    if($wallet){
      $postData = [
        'api_key'=>$post_param['wallets_api'],
        'password'=>$post_param['wallet_password'],
        'coin'=>$wallet['coin'],
      ];
      $result = $this->invoice->CR_getBalance($postData);
      if($result['flag'] == 1){
          $wdata = $result['data']; 
          $wallet_table = 'coinremitter_wallets';
          $api_key = $post_param['wallets_api'];
          $password = $this->invoice->encrypt($post_param['wallet_password']);
          $balance = $wdata['balance'];
          $wallet_name = $wdata['wallet_name'];
          $wallet_up = "UPDATE $wallet_table SET api_key = '$api_key',password = '$password',name='$wallet_name',balance='$balance' WHERE id = '$wallet_id'";
          $db = Db::getInstance();
          $db->Execute($wallet_up);
          $this->_getSession()->set('displaySuccess', 'Wallet Update Success Fully');
          Tools::redirectAdmin('index.php?controller=CoinremitterWallets&token='.Tools::getValue('token'));    
      }else{
        $this->displayWarning($result['msg']);    
      }
    }
  }
  public function getSelectCoinList(){
    $walletArr =[];
    $results = self::getWallets();
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
      $output = '<div class="alert alert-success" role="alert">
                  <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true"><i class="material-icons">close</i></span>
                  </button>
                  <div class="alert-text">
                    <p>'.$information.'</p>
                  </div>
                </div>';
      return $output;
  }
  private function _getSession()
  {
    return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('session');
  }
  
  public function update_wallet_balance(){
      $wallets  = $this->getWallets();
      if($wallets){
        foreach ($wallets as $w) {
          $postData = [
            'api_key'=>$w['api_key'],
            'password'=>$this->invoice->decrypt($w['password']),
            'coin'=>$w['coin'],
          ];
          $wallet_id = $w['id'];
          $result = $this->invoice->CR_getBalance($postData);
          if($result['flag'] = 1){
            $balance = $result['data']['balance'];
            $wallet_table = 'coinremitter_wallets';
            $wallet_up = "UPDATE $wallet_table SET balance = '$balance' WHERE id = '$wallet_id'";
            $db = Db::getInstance();
            $db->Execute($wallet_up);
          }
        }
      }
  }
}