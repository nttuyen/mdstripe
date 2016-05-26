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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/vendor/autoload.php';

class MDStripe extends PaymentModule
{
    /**
     * MDStripe constructor.
     */
    public function __construct()
    {
        $this->name = 'mdstripe';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.0';
        $this->author = 'Michael Dekker';
        $this->need_instance = 0;

        $this->bootstrap = true;

        $this->controllers = array('webhooks', 'confirmation');

        parent::__construct();

        $this->displayName = $this->l('Stripe');
        $this->description = $this->l('Accept payments with Stripe');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);
    }

    /**
     * Install the module
     *
     * @return bool Whether the module has been successfully installed
     */
    public function install()
    {
        if (extension_loaded('curl') == false)
        {
            $this->_errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        return parent::install();
    }

    /**
     * Uninstall the module
     *
     * @return bool Whether the module has been successfully installed
     */
    public function uninstall()
    {
        return parent::uninstall();
    }

    public $hooks = array('header', 'backOfficeHeader', 'payment', 'paymentReturn', 'displayPaymentTop');

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        /**
         * If values have been submitted in the form, process.
         */
        if (((bool)Tools::isSubmit('submitMdstripeModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);

        $output = $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        return $output.$this->renderForm();
    }

    /**
     * Create the form that will be displayed in the configuration of your module.
     */
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);

        $helper->identifier = $this->identifier;
        $helper->submit_action = 'submitMdstripeModule';
        $helper->currentIndex = $this->context->link->getAdminLink('AdminModules', false)
            .'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(), /* Add values for your inputs */
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }

    /**
     * Create the structure of your form.
     */
    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'MDSTRIPE_LIVE_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Use this module in live mode'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => true,
                                'label' => $this->l('Enabled')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => false,
                                'label' => $this->l('Disabled')
                            )
                        ),
                    ),
                    array(
                        'col' => 3,
                        'type' => 'text',
                        'prefix' => '<i class="icon icon-envelope"></i>',
                        'desc' => $this->l('Enter a valid email address'),
                        'name' => 'MDSTRIPE_ACCOUNT_EMAIL',
                        'label' => $this->l('Email'),
                    ),
                    array(
                        'type' => 'password',
                        'name' => 'MDSTRIPE_ACCOUNT_PASSWORD',
                        'label' => $this->l('Password'),
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                ),
            ),
        );
    }

    /**
     * Set values for the inputs.
     */
    protected function getConfigFormValues()
    {
        return array(
            'MDSTRIPE_LIVE_MODE' => Configuration::get('MDSTRIPE_LIVE_MODE', true),
            'MDSTRIPE_ACCOUNT_EMAIL' => Configuration::get('MDSTRIPE_ACCOUNT_EMAIL', 'contact@prestashop.com'),
            'MDSTRIPE_ACCOUNT_PASSWORD' => Configuration::get('MDSTRIPE_ACCOUNT_PASSWORD', null),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }
    }

    /**
    * Add the CSS & JavaScript files you want to be loaded in the BO.
    */
    public function hookBackOfficeHeader()
    {
        if (Tools::getValue('module_name') == $this->name) {
            $this->context->controller->addJS($this->_path.'views/js/back.js');
            $this->context->controller->addCSS($this->_path.'views/css/back.css');
        }
    }

    /**
     * Add the CSS & JavaScript files you want to be added on the FO.
     */
    public function hookHeader()
    {
        $this->context->controller->addJS($this->_path.'/views/js/front.js');
        $this->context->controller->addCSS($this->_path.'/views/css/front.css');
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
//        $currency_id = $params['cart']->id_currency;
//        $currency = new Currency((int)$currency_id);
//
//        if (in_array($currency->iso_code, $this->limited_currencies) == false) {
//            return false;
//        }

        /** @var Cookie $email */
        $cookie = $params['cookie'];
        $stripe_email = $cookie->email;

        /** @var Cart $cart */
        $cart = $params['cart'];
        $currency = new Currency($cart->id_currency);
        $amount = $cart->getOrderTotal(true);

        $link = $this->context->link;
        
        $this->smarty->assign(array(
            'module_dir' => $this->_path,
            'stripe_email' => $stripe_email,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => (int)$amount * 100,
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'confirmation'),
            'id_cart' => (int)$cart->id,
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }
//
//    /**
//     * This hook is used to display the order confirmation page.
//     */
//    public function hookPaymentReturn($params)
//    {
//        if ($this->active == false) {
//            return;
//        }
//
//        /** @var Order $order */
//        $order = $params['objOrder'];
//
//        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
//            $this->smarty->assign('status', 'ok');
//        }
//
//        $this->smarty->assign(array(
//            'id_order' => $order->id,
//            'reference' => $order->reference,
//            'params' => $params,
//            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
//        ));
//
//        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
//    }

    public function hookDisplayPaymentTop()
    {
        $this->context->controller->addJS('https://js.stripe.com/v2/');

        return $this->context->smarty->fetch($this->local_path.'views/templates/hook/stripebtn.tpl');
    }
}
