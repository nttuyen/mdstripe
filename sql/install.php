<?php

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'stripe_transaction` (
    `id_stripe_transaction` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `type` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `card_last_digits` INT(4) UNSIGNED DEFAULT \'0\',
    `charge_token` VARCHAR(128),
    `amount` DECIMAL(20, 6) UNSIGNED NOT NULL DEFAULT \'0\',
    `date_add` DATETIME,
    `date_upd` DATETIME,
    PRIMARY KEY  (`id_stripe_transaction`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';


foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}