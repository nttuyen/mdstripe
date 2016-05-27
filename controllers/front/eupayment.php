<?php
/**
* 2007-2015 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2015 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

require_once dirname(__FILE__).'/../../vendor/autoload.php';

class MdstripeEupaymentModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        if (!Module::isEnabled('mdstripe')) {
            return;
        }

        require_once _PS_MODULE_DIR_.'mdstripe/mdstripe.php';

        parent::initContent();
        $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');

        /** @var Cookie $email */
        $cookie = $this->context->cookie;
        $stripe_email = $cookie->email;

        /** @var Cart $cart */
        $cart = $this->context->cart;
        $currency = new Currency($cart->id_currency);
        $amount = $cart->getOrderTotal(true);

        $link = $this->context->link;

        $this->context->smarty->assign(array(
            'stripe_email' => $stripe_email,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => (int)$amount * 100,
            'stripe_confirmation_page' => $link->getModuleLink('mdstripe', 'validation'),
            'id_cart' => (int)$cart->id,
            'stripe_secret_key' => Configuration::get(MDStripe::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(MDStripe::PUBLISHABLE_KEY),
            'stripe_locale' => MDStripe::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => Configuration::get(MDStripe::ZIPCODE),
            'stripe_bitcoin' => Configuration::get(MDStripe::BITCOIN),
            'stripe_alipay' => Configuration::get(MDStripe::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
        ));

        $this->setTemplate('eupayment.tpl');
    }
}
