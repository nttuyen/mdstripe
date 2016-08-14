<?php
/**
 * 2016 Michael Dekker
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@michaeldekker.com so we can send you a copy immediately.
 *
 *  @author    Michael Dekker <prestashop@michaeldekker.com>
 *  @copyright 2016 Michael Dekker
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 */

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'stripe_transaction` (
    `id_stripe_transaction` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
    `id_order` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `type` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `source` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `card_last_digits` INT(4) UNSIGNED DEFAULT \'0\',
    `id_charge` VARCHAR(128),
    `amount` INT(11) UNSIGNED NOT NULL DEFAULT \'0\',
    `date_add` DATETIME,
    `date_upd` DATETIME,
    PRIMARY KEY  (`id_stripe_transaction`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';


foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
