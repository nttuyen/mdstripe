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

require_once dirname(__FILE__).'/../../vendor/autoload.php';
require_once dirname(__FILE__).'/../../classes/autoload.php';

class MdstripeHookModuleFrontController extends ModuleFrontController
{
    /** @var MDStripe $module */
    public $module;

    public function postProcess()
    {
        $body = Tools::file_get_contents('php://input');

        if (!empty($body) && $data = Tools::jsonDecode($body, true)) {
            // Verify with Stripe
            \Stripe\Stripe::setApiKey(Configuration::get(MDStripe::SECRET_KEY));
            $event = \Stripe\Event::retrieve($data['id']);
            switch ($data['type']) {
                case 'charge.refunded':
                    $this->processRefund($event);
                    die('ok');
                    break;
                default:
                    die('ok');
                    break;
            }
        }

        header('Content-Type: text/plain');
        die('not processed');
    }

    /**
     * Process refund event
     *
     * @param \Stripe\Event $event
     */
    protected function processRefund($event)
    {
        /** @var \Stripe\Charge $charge */
        $charge = $event->data['object'];

        $refunds = array();
        $previous_attributes = array();

        if (isset($charge['previous_attributes'][0]['refunds']['data'])) {
            foreach ($charge['previous_attributes'][0]['refunds']['data'] as $previous_attribute) {
                $previous_attributes[] = $previous_attribute['id'];
            }
        }

        // Remove previous attributes
        foreach ($charge['refunds']['data'] as $refund) {
            if (!in_array($refund['id'], $previous_attributes)) {
                $refunds[] = $refund;
            }
        }

        foreach ($refunds as $refund) {
            if (isset($refund['metadata']['from_back_office']) && $refund['metadata']['from_back_office'] == 'true') {
                die('not processed');
            }
        }

        if (!$id_order = StripeTransaction::getIdOrderByCharge($charge->id)) {
            die('ok');
        }

        $order = new Order($id_order);

        $total_amount = $order->getTotalPaid();

        if (!in_array($charge->currency, MDStripe::$zero_decimal_currencies)) {
            $total_amount = (int)(Tools::ps_round($total_amount * 100, 0));
        }

        $amount_refunded = (int)$charge->amount_refunded;

        if (Configuration::get(MDStripe::USE_STATUS_REFUND) && (int)($amount_refunded - $total_amount) === 0) {
            // Full refund
            if (Configuration::get(MDStripe::GENERATE_CREDIT_SLIP)) {
                $sql = new DbQuery();
                $sql->select('od.`id_order_detail`, od.`product_quantity`');
                $sql->from('order_detail', 'od');
                $sql->where('od.`id_order` = '.(int)$order->id);

                $full_product_list = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (is_array($full_product_list) && !empty($full_product_list)) {
                    $product_list = array();
                    $quantity_list = array();
                    foreach ($full_product_list as $db_order_detail) {
                        $id_order_detail = (int)$db_order_detail['id_order_detail'];
                        $product_list[] = (int)$id_order_detail;
                        $quantity_list[$id_order_detail] = (int)$db_order_detail['product_quantity'];
                    }
                    OrderSlip::createOrderSlip($order, $product_list, $quantity_list, $order->getShipping());
                }
            }

            $transaction = new StripeTransaction();
            $transaction->card_last_digits = (int)$charge->source['last4'];
            $transaction->id_charge = $charge->id;
            $transaction->amount = $charge->amount_refunded - StripeTransaction::getRefundedAmount($charge->id);
            $transaction->id_order = $order->id;
            $transaction->type = StripeTransaction::TYPE_FULL_REFUND;
            $transaction->source = StripeTransaction::SOURCE_WEBHOOK;
            $transaction->add();

            $order_history = new OrderHistory();
            $order_history->id_order = $order->id;
            $order_history->changeIdOrderState((int)Configuration::get(MDStripe::STATUS_REFUND), $id_order);
            $order_history->addWithemail(true);
        } else {
            $transaction = new StripeTransaction();
            $transaction->card_last_digits = (int)$charge->source['last4'];
            $transaction->id_charge = $charge->id;
            $transaction->amount = $charge->amount_refunded - StripeTransaction::getRefundedAmount($charge->id);
            $transaction->id_order = $order->id;
            $transaction->type = StripeTransaction::TYPE_PARTIAL_REFUND;
            $transaction->source = StripeTransaction::SOURCE_WEBHOOK;
            $transaction->add();

            if (Configuration::get(MDStripe::USE_STATUS_PARTIAL_REFUND)) {
                $order_history = new OrderHistory();
                $order_history->id_order = $order->id;
                $order_history->changeIdOrderState((int)Configuration::get(MDStripe::STATUS_PARTIAL_REFUND), $id_order);
                $order_history->addWithemail(true);
            }
        }
    }
}
