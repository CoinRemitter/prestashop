<?php
abstract class AbstractRestController extends ModuleFrontController
{
   public function init() {

      parent::init();
      switch ($_SERVER['REQUEST_METHOD']) {
         case 'GET':
            $this->processGetRequest();
            break;
         case 'POST':
            $this->processPostRequest();
            break;
         default:
                // throw some error or whatever
      }
   }

   abstract public function processGetRequest();
   abstract public function processPostRequest();
}
