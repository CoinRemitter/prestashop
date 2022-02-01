<?php

class CoinremitterCancelModuleFrontController extends ModuleFrontController {

   public function initContent() {

      $order_id = Tools::getValue('order_id');

      $this->context->smarty->assign([
         'order_id' => $order_id,
      ]);
      $this->setTemplate('module:coinremitter/views/templates/front/cancel.tpl');
      parent::initContent();
   }  
}