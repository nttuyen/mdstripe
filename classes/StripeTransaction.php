<?php

class StripeTransaction extends ObjectModel
{
    const TYPE_CHARGE = 1;
    const TYPE_PARTIAL_REFUND = 2;
    const TYPE_FULL_REFUND = 3;

    /** @var int $id_order */
    public $id_order;

    /** @var int $type */
    public $type;

    /** @var string $card_last_digits */
    public $card_last_digits;

    /** @var int $charge_token */
    public $charge_token;

    /** @var float $amount */
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
            'card_last_digits' => array('type' => self::TYPE_INT, 'validate' => 'isUnsignedInt', 'size' => 4),
            'charge_token' => array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true),
            'amount' => array('type' => self::TYPE_FLOAT, 'validate' => 'isFloat', 'required' => true),
            'date_add' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
            'date_upd' => array('type' => self::TYPE_DATE, 'validate' => 'isDate'),
        ),
    );
}