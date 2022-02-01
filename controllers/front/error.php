<?php

class CoinremitterErrorModuleFrontController extends ModuleFrontController {

	public function initContent() {
		parent::initContent(); 
		$msg = Tools::getValue('msg');
		if(!isset($msg) || $msg == ''){
			Tools::redirect('index.php?controller=404');
		}
		$this->context->smarty->assign(array(
         'error_msg' => $msg
      ));
		$this ->setTemplate('module:coinremitter/views/templates/front/error.tpl'); 
	}
}