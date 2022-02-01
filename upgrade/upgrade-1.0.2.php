<?php
/**
 * File: /upgrade/upgrade-1.0.2.php
 */
function upgrade_module_1_0_2($object) {
   
   // Process Module upgrade to 1.0.2
   $db = Db::getInstance();

   $sql = "ALTER TABLE `coinremitter_payment` CHANGE `expire_on` `expire_on` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Expire on'";
   $db->Execute($sql);

   $sql = "ALTER TABLE `coinremitter_payment` ADD `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Address' AFTER `order_id`";
   $db->Execute($sql);

   $sql = "ALTER TABLE `coinremitter_order` ADD `address` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Address' AFTER `order_id`, ADD `address_qrcode` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'address qrcode' AFTER `address`";
   $db->Execute($sql);

   $sql = "ALTER TABLE `coinremitter_wallets` ADD `exchange_rate_multiplier` VARCHAR(255) NOT NULL DEFAULT '1' COMMENT 'between 1 to 100' AFTER `password`, ADD `minimum_value` VARCHAR(255) NOT NULL DEFAULT '0' AFTER `exchange_rate_multiplier`";

   $db->Execute($sql);
   
   $sql = "CREATE TABLE IF NOT EXISTS coinremitter_webhook(
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
   $db->Execute($sql);
   
   $object->registerHook('displayAdminOrder');

   return true;
}