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
 * Class MdstripeEupaymentModuleFrontController
 */
class MdstripeEupaymentModuleFrontController extends ModuleFrontController
{
    /**
     * MdstripeEupaymentModuleFrontController constructor.
     */
    public function __construct()
    {
        parent::__construct();

        $this->ssl = Tools::usingSecureMode();
    }

    /**
     * @throws PrestaShopException
     */
    public function initContent()
    {
        if (!Module::isEnabled('mdstripe')) {
            Tools::redirect('index.php?controller=order&step=1');
        }
        $cart = $this->context->cart;
        if ($cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0 || !$this->module->active) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $customer = new Customer($cart->id_customer);
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        require_once _PS_MODULE_DIR_.'mdstripe/mdstripe.php';

        parent::initContent();
        $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');

        /** @var Cookie $email */
        $cookie = $this->context->cookie;
        $stripeEmail = $cookie->email;

        /** @var Cart $cart */
        $cart = $this->context->cart;
        $currency = new Currency($cart->id_currency);

        $link = $this->context->link;

        $stripeAmount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), MdStripe::$zeroDecimalCurrencies)) {
            $stripeAmount = (int) ($stripeAmount * 100);
        }


        $this->context->smarty->assign(array(
            'stripe_email' => $stripeEmail,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => $stripeAmount,
            'stripe_confirmation_page' => $link->getModuleLink('mdstripe', 'validation'),
            'id_cart' => (int) $cart->id,
            'stripe_secret_key' => Configuration::get(MdStripe::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(MdStripe::PUBLISHABLE_KEY),
            'stripe_locale' => MdStripe::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => (bool) Configuration::get(MdStripe::ZIPCODE),
            'stripe_bitcoin' => (bool) Configuration::get(MdStripe::BITCOIN) && Tools::strtolower($currency->iso_code) === 'usd',
            'stripe_alipay' => (bool) Configuration::get(MdStripe::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
            'stripe_collect_billing' => Configuration::get(MdStripe::COLLECT_BILLING),
            'stripe_collect_shipping' => Configuration::get(MdStripe::COLLECT_SHIPPING),
            'autoplay' => true,
        ));

        $this->setTemplate('eupayment.tpl');
    }
}
