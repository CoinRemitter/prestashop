<?php
class AdminControllerCore extends ObjectModel {
   public $id;
   public $sku;
   public $name;
   public $created;
   public static $definition = [
      'table' => 'coinremitter_wallets',
      'primary' => 'id',
      'fields' => [
         'sku' =>  ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required'=>true],
         'name' =>  ['type' => self::TYPE_STRING, 'validate' => 'isAnything', 'required'=>true],
         'created' =>  ['type' => self::TYPE_DATE, 'validate' => 'isDateFormat'],
      ],
   ];
}