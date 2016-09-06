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

/**
 * Class MdstripeValidationModuleFrontController
 */
class MdstripeValidationModuleFrontController extends ModuleFrontController
{
    /** @var MdStripe $module */
    public $module;

    /**
     * Post process
     *
     * @return bool Whether the info has been successfully processed
     * @throws PrestaShopException
     */
    public function postProcess()
    {
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $orderProcess = Configuration::get('PS_ORDER_PROCESS_TYPE') ? 'order-opc' : 'order';
        $this->context->smarty->assign(array(
            'orderLink' => $this->context->link->getPageLink($orderProcess, true),
        ));

        if ((Tools::isSubmit('mdstripe-id_cart') == false) || (Tools::isSubmit('mdstripe-token') == false)) {
            $error = $this->module->l('An error occurred. Please contact us for more information.', 'validation');
            if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                $this->errors[] = $error;
                $this->setTemplate('error.tpl');
            } else {
                $this->context->smarty->assign('errors', $error);
                $this->setTemplate('error17.tpl');
            }

            return false;
        }

        $token = Tools::getValue('mdstripe-token');
        $idCart = Tools::getValue('mdstripe-id_cart');

        $cart = new Cart((int) $idCart);
        $customer = new Customer((int) $cart->id_customer);
        $currency = new Currency((int) $cart->id_currency);

        $stripe = array(
            'secret_key' => Configuration::get(MdStripe::SECRET_KEY),
            'publishable_key' => Configuration::get(MdStripe::PUBLISHABLE_KEY),
        );

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        try {
            $stripeCustomer = \Stripe\Customer::create(array(
                'email' => $customer->email,
                'source' => $token
            ));
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                $this->errors[] = $error;
                $this->setTemplate('error.tpl');
            } else {
                $this->context->smarty->assign('errors', $error);
                $this->setTemplate('error17.tpl');
            }

            return false;
        }

        $stripeAmount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), MdStripe::$zeroDecimalCurrencies)) {
            $stripeAmount = (int) ($stripeAmount * 100);
        }

        try {
            $stripeCharge = \Stripe\Charge::create(
                array(
                    'customer' => $stripeCustomer->id,
                    'amount' => $stripeAmount,
                    'currency' => Tools::strtolower($currency->iso_code),
                )
            );
        } catch (Exception $e) {
            $error = $e->getMessage();
            if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                $this->errors[] = $error;
                $this->setTemplate('error.tpl');
            } else {
                $this->context->smarty->assign('errors', $error);
                $this->setTemplate('error17.tpl');
            }

            return false;
        }

        if ($stripeCharge->paid === true) {
            $paymentStatus = Configuration::get(MdStripe::STATUS_VALIDATED);
            $message = null;

            /**
             * Converting cart into a valid order
             */
            $currencyId = (int) Context::getContext()->currency->id;

            $this->module->validateOrder($idCart, $paymentStatus, $cart->getOrderTotal(), 'Stripe', $message, array(), $currencyId, false, $cart->secure_key);

            /**
             * If the order has been validated we try to retrieve it
             */
            $idOrder = Order::getOrderByCartId((int)$cart->id);

            if ($idOrder) {
                // Log transaction
                $stripeTransaction = new StripeTransaction();
                $stripeTransaction->card_last_digits = (int) $stripeCharge->source['last4'];
                $stripeTransaction->id_charge = $stripeCharge->id;
                $stripeTransaction->amount = $stripeAmount;
                $stripeTransaction->id_order = $idOrder;
                $stripeTransaction->type = StripeTransaction::TYPE_CHARGE;
                $stripeTransaction->source = StripeTransaction::SOURCE_FRONT_OFFICE;
                $stripeTransaction->add();

                /**
                 * The order has been placed so we redirect the customer on the confirmation page.
                 */
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$idOrder.'&key='.$customer->secure_key);
            } else {
                /**
                 * An error occurred and is shown on a new page.
                 */
                $error = $this->module->l('An error occurred. Please contact us for more information.', 'validation');
                if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
                    $this->errors[] = $error;
                    $this->setTemplate('error.tpl');
                } else {
                    $this->context->smarty->assign('errors', $error);
                    $this->setTemplate('error17.tpl');
                }

                return false;
            }
        } else {
            $stripeTransaction = new StripeTransaction();
            $stripeTransaction->card_last_digits = (int) $stripeCharge->source['last4'];
            $stripeTransaction->id_charge = $stripeCharge->id;
            $stripeTransaction->amount = 0;
            $stripeTransaction->id_order = 0;
            $stripeTransaction->type = StripeTransaction::TYPE_CHARGE_FAIL;
            $stripeTransaction->source = StripeTransaction::SOURCE_FRONT_OFFICE;
            $stripeTransaction->add();
        }

        /**
         * An error occurred and is shown on a new page.
         */
        $error = $this->module->l('An error occurred. Please contact us for more information.', 'validation');
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            $this->errors[] = $error;
            $this->setTemplate('error.tpl');
        } else {
            $this->context->smarty->assign('errors', $error);
            $this->setTemplate('error17.tpl');
        }

        return false;
    }
}
