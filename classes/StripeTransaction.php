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

class StripeTransaction extends ObjectModel
{
    const TYPE_CHARGE = 1;
    const TYPE_PARTIAL_REFUND = 2;
    const TYPE_FULL_REFUND = 3;
    const TYPE_CHARGE_FAIL = 4;

    const SOURCE_FRONT_OFFICE = 1;
    const SOURCE_BACK_OFFICE = 2;
    const SOURCE_WEBHOOK = 3;

    /** @var int $id_order */
    public $id_order;

    /** @var int $type */
    public $type;

    /** @var int $source */
    public $source;

    /** @var string $card_last_digits */
    public $card_last_digits;

    /** @var int $id_charge */
    public $id_charge;

    /** @var int $amount */
    public $amount;

    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = array(
        'table' => 'stripe_transaction',
        'primary' => 'id_stripe_transaction',
        'fields' => array(
            'id_order' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedId', 'required' => true),
            'type' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'source' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'required' => true),
            'card_last_digits' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'size' => 4),
            'id_charge' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true),
            'amount' => array('type' => self::TYPE_INT, 'validate' => 'isInt', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );

    /**
     * Get Customer ID by Charge ID
     *
     * @param int $id_charge Charge ID
     * @return int Cart ID
     */
    public static function getIdCustomerByCharge($id_charge)
    {
        $sql = new DbQuery();
        $sql->select('c.`id_customer`');
        $sql->from('stripe_transaction', 'st');
        $sql->innerJoin('orders', 'o', 'st.`id_order` = o.`id_order`');
        $sql->innerJoin('customer', 'c', 'o.`id_customer` = c.`id_customer`');
        $sql->where('st.`id_charge` = \''.pSQL($id_charge).'\'');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get Cart ID by Charge ID
     *
     * @param int $id_charge Charge ID
     * @return int Cart ID
     */
    public static function getIdCartByCharge($id_charge)
    {
        $sql = new DbQuery();
        $sql->select('c.`id_cart`');
        $sql->from('stripe_transaction', 'st');
        $sql->innerJoin('orders', 'o', 'st.`id_order` = o.`id_order`');
        $sql->innerJoin('cart', 'c', 'o.`id_cart` = c.`id_cart`');
        $sql->where('st.`id_charge` = \''.pSQL($$id_charge).'\'');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get Order ID by Charge ID
     *
     * @param int $id_charge Charge ID
     * @return int Order ID
     */
    public static function getIdOrderByCharge($id_charge)
    {
        $sql = new DbQuery();
        $sql->select('st.`id_order`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_charge` = \''.pSQL($id_charge).'\'');

        return (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
    }

    /**
     * Get refunded amount by Charge ID
     *
     * @param int $id_charge Charge ID
     * @return int $amount
     */
    public static function getRefundedAmount($id_charge)
    {
        $amount = 0;

        $sql = new DbQuery();
        $sql->select('st.`amount`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_charge` = \''.pSQL($id_charge).'\'');
        $sql->where('st.`type` = '.self::TYPE_PARTIAL_REFUND.' OR st.`type` = '.self::TYPE_FULL_REFUND);

        $db_amounts = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!is_array($db_amounts) || empty($db_amounts)) {
            return $amount;
        }

        foreach ($db_amounts as $db_amount) {
            $amount += (int)$db_amount['amount'];
        }

        return $amount;
    }

    /**
     * Get StripeTransactions by Order ID
     *
     * @param int $id_order Order ID
     * @param bool $count Return amount of transactions
     * @return array|false|mysqli_result|null|PDOStatement|resource
     * @throws PrestaShopDatabaseException
     */
    public static function getTransactionsByOrderId($id_order, $count = false)
    {
        $sql = new DbQuery();
        if ($count) {
            $sql->select('count(*)');
        } else {
            $sql->select('*');
        }
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_order` = '.(int)$id_order);

        if ($count) {
            return Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        }

        return Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);
    }

    /**
     * Get refunded amount by Order ID
     *
     * @param int $id_order Order ID
     * @return int $amount
     */
    public static function getRefundedAmountByOrderId($id_order)
    {
        $amount = 0;

        $sql = new DbQuery();
        $sql->select('st.`amount`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_order` = \''.pSQL($id_order).'\'');
        $sql->where('st.`type` = '.self::TYPE_PARTIAL_REFUND.' OR st.`type` = '.self::TYPE_FULL_REFUND);

        $db_amounts = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        if (!is_array($db_amounts) || empty($db_amounts)) {
            return $amount;
        }

        foreach ($db_amounts as $db_amount) {
            $amount += (int)$db_amount['amount'];
        }

        return $amount;
    }

    /**
     * Get Charge ID by Order ID
     *
     * @param int $id_order Order ID
     * @return bool|string Charge ID or false if not found
     */
    public static function getChargeByIdOrder($id_order)
    {
        $sql = new DbQuery();
        $sql->select('st.`id_charge`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_order` = '.(int)$id_order);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        if (isset($result[0]['id_charge'])) {
            return $result[0]['id_charge'];
        }

        return false;
    }
}