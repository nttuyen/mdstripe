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

class MdstripeValidationModuleFrontController extends ModuleFrontController
{
    /** @var MDStripe $module */
    public $module;

    public function postProcess()
    {
        if ((Tools::isSubmit('id_cart') == false) || (Tools::isSubmit('stripe-token') == false)) {
            $this->errors[] = $this->module->l('An error occurred. Please contact us for more information.');
            $this->setTemplate('error.tpl');

            return false;
        }

        $token = Tools::getValue('stripe-token');
        $id_cart = Tools::getValue('id_cart');

        $cart = new Cart((int)$id_cart);
        $customer = new Customer((int)$cart->id_customer);
        $currency = new Currency((int)$cart->id_currency);

        $stripe = array(
            'secret_key' => Configuration::get(MDStripe::SECRET_KEY),
            'publishable_key' => Configuration::get(MDStripe::PUBLISHABLE_KEY),
        );

        \Stripe\Stripe::setApiKey($stripe['secret_key']);

        try {
            $stripe_customer = \Stripe\Customer::create(array(
                'email' => $customer->email,
                'source' => $token
            ));
        } catch (Stripe\Error\InvalidRequest $e) {
            $this->errors[] = $e->getMessage();
            $this->setTemplate('error.tpl');

            return false;
        }

        $stripe_amount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), MDStripe::$zero_decimal_currencies)) {
            $stripe_amount = (int)($stripe_amount * 100);
        }

        $stripe_charge = \Stripe\Charge::create(array(
            'customer' => $stripe_customer->id,
            'amount' => $stripe_amount,
            'currency' => $currency->iso_code
        ));

        if ($stripe_charge->paid === true) {
            $payment_status = Configuration::get(MDStripe::STATUS_VALIDATED);
            $message = null;

            /**
             * Converting cart into a valid order
             */
            $currency_id = (int)Context::getContext()->currency->id;

            $this->module->validateOrder($id_cart, $payment_status, $cart->getOrderTotal(), 'Stripe', $message, array(),
                $currency_id, false, $cart->secure_key);

            /**
             * If the order has been validated we try to retrieve it
             */
            $id_order = Order::getOrderByCartId((int)$cart->id);

            if ($id_order) {
                /**
                 * The order has been placed so we redirect the customer on the confirmation page.
                 */
                Tools::redirect('index.php?controller=order-confirmation&id_cart='.$cart->id.'&id_module='.$this->module->id.'&id_order='.$id_order.'&key='.$customer->secure_key);
            } else {
                /**
                 * An error occurred and is shown on a new page.
                 */
                $this->errors[] = $this->module->l('An error occurred. Please contact us for more information.');
                $this->setTemplate('error.tpl');

                return false;
            }
        }

        /**
         * An error occurred and is shown on a new page.
         */
        $this->errors[] = $this->module->l('An error occurred. Please contact us for more information.');
        $this->setTemplate('error.tpl');
        return false;
    }
}
