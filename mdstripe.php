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
    const MENU_SETTINGS = 1;

    const ZIPCODE = 'MDSTRIPE_ZIPCODE';
    const BITCOIN = 'MDSTRIPE_BITCOIN';
    const ALIPAY = 'MDSTRIPE_ALIPAY';

    const SECRET_KEY = 'MDSTRIPE_SECRET_KEY';
    const PUBLISHABLE_KEY = 'MDSTRIPE_PUBLISHABLE_KEY';

    public $module_url;

    public static $stripe_languages = array('zh', 'nl', 'en', 'fr', 'de', 'it', 'ja', 'es');

    /** @var int $menu Current menu */
    public $menu;

    /**
     * MDStripe constructor.
     */
    public function __construct()
    {
        $this->name = 'mdstripe';
        $this->tab = 'payments_gateways';
        $this->version = '0.6.0';
        $this->author = 'Michael Dekker';
        $this->need_instance = 0;

        $this->bootstrap = true;

        $this->controllers = array('hook', 'validation');

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

    /** @var array Hooks */
    public $hooks = array(
        'header',
        'backOfficeHeader',
        'displayPayment',
        'displayPaymentEU',
        'paymentReturn',
        'displayPaymentTop',
    );

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->module_url = Context::getContext()->link->getAdminLink('AdminModules', false).'&token='.Tools::getAdminTokenLite('AdminModules').'&'.http_build_query(array('configure' => $this->name));

        $output = '';

        $this->initNavigation();

        $output .= $this->postProcess();


        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'menutabs' => $this->initNavigation()
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/navbar.tpl');

        switch (Tools::getValue('menu')) {
            default:
                $this->menu = self::MENU_SETTINGS;
                return $output.$this->renderSettingsPage();
                break;
        }
    }

    /**
     * Initialize navigation
     *
     * @return array Menu items
     */
    protected function initNavigation()
    {
        $menu = array(
            self::MENU_SETTINGS => array(
                'short' => $this->l('Settings'),
                'desc' => $this->l('Module settings'),
                'href' => $this->module_url.'&menu='.self::MENU_SETTINGS,
                'active' => false,
                'icon' => 'icon-gears'
            ),
        );

        switch (Tools::getValue('menu')) {
            default:
                $this->menu = self::MENU_SETTINGS;
                $menu[self::MENU_SETTINGS]['active'] = true;
                break;
        }

        return $menu;
    }

    protected function renderSettingsPage()
    {
        $output = '';

        $this->context->smarty->assign(array(
            'module_url' => $this->module_url
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');

        $output .= $this->renderGeneralOptions();

        return $output;
    }

    protected function renderGeneralOptions()
    {
        $helper = new HelperOptions();
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;

        return $helper->generateOptions($this->getGeneralOptions());
    }

    protected function getGeneralOptions()
    {
        return array(
            'locales' => array(
                'title' => $this->l('API Settings'),
                'icon' => 'icon-server',
                'fields' => array(
                    self::SECRET_KEY => array(
                        'title' => $this->l('Secret key'),
                        'type' => 'text',
                        'name' => self::SECRET_KEY,
                        'value' => Configuration::get(self::SECRET_KEY),
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::PUBLISHABLE_KEY => array(
                        'title' => $this->l('Publishable key'),
                        'type' => 'text',
                        'name' => self::PUBLISHABLE_KEY,
                        'value' => Configuration::get(self::PUBLISHABLE_KEY),
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::ZIPCODE => array(
                        'title' => $this->l('Zipcode / postcode verification'),
                        'type' => 'bool',
                        'name' => self::ZIPCODE,
                        'value' => Configuration::get(self::ZIPCODE),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::BITCOIN=> array(
                        'title' => $this->l('Accept bitcoins'),
                        'type' => 'bool',
                        'name' => self::BITCOIN,
                        'value' => Configuration::get(self::BITCOIN),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::ALIPAY => array(
                        'title' => $this->l('Accept Alipay'),
                        'type' => 'bool',
                        'name' => self::ALIPAY,
                        'value' => Configuration::get(self::ALIPAY),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button'
                ),
            ),
        );
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        if ($this->menu == self::MENU_SETTINGS && Tools::isSubmit('submitOptionsconfiguration')) {
            $this->postProcessGeneralOptions();
        }
    }

    /**
     * Process ImageMagickOptions
     */
    protected function postProcessGeneralOptions()
    {
        $secret_key = Tools::getValue(self::SECRET_KEY);
        $publishable_key = Tools::getValue(self::PUBLISHABLE_KEY);
        $zipcode = (bool)Tools::getValue(self::ZIPCODE);
        $bitcoin = (bool)Tools::getValue(self::BITCOIN);
        $alipay = (bool)Tools::getValue(self::ALIPAY);

        if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            if (Shop::getContext() == Shop::CONTEXT_ALL) {
                $this->updateAllValue(self::SECRET_KEY, $secret_key);
                $this->updateAllValue(self::PUBLISHABLE_KEY, $publishable_key);
                $this->updateAllValue(self::ZIPCODE, $zipcode);
                $this->updateAllValue(self::BITCOIN, $bitcoin);
                $this->updateAllValue(self::ALIPAY, $alipay);
            } elseif (is_array(Tools::getValue('multishopOverrideOption'))) {
                $id_shop_group = (int)Shop::getGroupFromShop($this->getShopId(), true);
                $multishop_override = Tools::getValue('multishopOverrideOption');
                if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                    foreach (Shop::getShops(false, $this->getShopId()) as $id_shop) {
                        if ($multishop_override[self::SECRET_KEY]) {
                            Configuration::updateValue(self::SECRET_KEY, $secret_key, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::PUBLISHABLE_KEY]) {
                            Configuration::updateValue(self::PUBLISHABLE_KEY, $publishable_key, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::ZIPCODE]) {
                            Configuration::updateValue(self::ZIPCODE, $zipcode, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::BITCOIN]) {
                            Configuration::updateValue(self::BITCOIN, $bitcoin, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::ALIPAY]) {
                            Configuration::updateValue(self::ALIPAY, $alipay, false, $id_shop_group, $id_shop);
                        }
                    }
                } else {
                    $id_shop = (int)$this->getShopId();
                    if ($multishop_override[self::SECRET_KEY]) {
                        Configuration::updateValue(self::SECRET_KEY, $secret_key, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::PUBLISHABLE_KEY]) {
                        Configuration::updateValue(self::PUBLISHABLE_KEY, $publishable_key, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::ZIPCODE]) {
                        Configuration::updateValue(self::ZIPCODE, $zipcode, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::BITCOIN]) {
                        Configuration::updateValue(self::BITCOIN, $bitcoin, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::ALIPAY]) {
                        Configuration::updateValue(self::ALIPAY, $alipay, false, $id_shop_group, $id_shop);
                    }
                }
            }
        } else {
            Configuration::updateValue(self::SECRET_KEY, $secret_key);
            Configuration::updateValue(self::PUBLISHABLE_KEY, $publishable_key);
            Configuration::updateValue(self::ZIPCODE, $zipcode);
            Configuration::updateValue(self::BITCOIN, $bitcoin);
            Configuration::updateValue(self::ALIPAY, $alipay);
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
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'validation'),
            'id_cart' => (int)$cart->id,
        ));

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * Hook to Advcanced EU checkout
     *
     * @param $params Hook parameters
     * @return array Smarty variables
     */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return array();
        }

        $payment_options = array(
            'cta_text' => $this->l('Pay with Stripe'),
            'logo' => Media::getMediaPath($this->local_path.'views/img/stripebtnlogo.png'),
            'action' => $this->context->link->getModuleLink($this->name, 'eupayment', array(), true),
        );

        return $payment_options;
    }

    /**
     * This hook is used to display the order confirmation page.
     */
    public function hookPaymentReturn($params)
    {
        if ($this->active == false) {
            return '';
        }

        /** @var Order $order */
        $order = $params['objOrder'];

        if ($order->getCurrentOrderState()->id != Configuration::get('PS_OS_ERROR')) {
            $this->smarty->assign('status', 'ok');
        }

        $this->smarty->assign(array(
            'id_order' => $order->id,
            'reference' => $order->reference,
            'params' => $params,
            'total' => Tools::displayPrice($params['total_to_pay'], $params['currencyObj'], false),
        ));

        return $this->display(__FILE__, 'views/templates/front/confirmation.tpl');
    }

    /**
     * Hook to the top a payment page
     *
     * @param $params
     * @return string
     */
    public function hookDisplayPaymentTop($params)
    {
        $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');

        return '';
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key Configuration key
     * @param mixed $values Configuration values, can be string or array with id_lang as key
     * @param bool $html Contains HTML
     */
    public function updateAllValue($key, $values, $html = false)
    {
        foreach (Shop::getShops() as $shop) {
            Configuration::updateValue($key, $values, $html, $shop['id_shop_group'], $shop['id_shop']);
        }
        Configuration::updateGlobalValue($key, $values, $html);
    }

    /**
     * Get the Shop ID of the current context
     * Retrieves the Shop ID from the cookie
     *
     * @return int Shop ID
     */
    public function getShopId()
    {
        $cookie = Context::getContext()->cookie->getFamily('shopContext');

        return (int)Tools::substr($cookie['shopContext'], 2, count($cookie['shopContext']));
    }

    /**
     * Get the Stripe language
     *
     * @param string $locale IETF locale
     * @return string Stripe language
     */
    public static function getStripeLanguage($locale)
    {
        $language_iso = Tools::strtolower(Tools::substr($locale, 0, 2));

        if (in_array($language_iso, self::$stripe_languages)) {
            return $language_iso;
        }

        return 'en';
    }

    /**
     * Detect Back Office settings
     *
     * @return array Array with error message strings
     */
    protected function detectBOSettingsErrors()
    {
        $lang_id = Context::getContext()->language->id;
        $output = array();
        if (Configuration::get('PS_DISABLE_NON_NATIVE_MODULE')) {
            $output[] = $this->l('Non native modules such as this one are disabled. Go to').' "'.
                $this->getTabName('AdminParentPreferences', $lang_id).
                ' > '.
                $this->getTabName('AdminPerformance', $lang_id).
                '" '.$this->l('and make sure that the option').' "'.
                Translate::getAdminTranslation('Disable non PrestaShop modules', 'AdminPerformance').
                '" '.$this->l('is set to').' "'.
                Translate::getAdminTranslation('No', 'AdminPerformance').
                '"'.$this->l('.').'<br />';
        }
        return $output;
    }

    /**
     * Get Tab name from database
     * @param $class_name string Class name of tab
     * @param $id_lang int Language id
     *
     * @return string Returns the localized tab name
     */
    protected function getTabName($class_name, $id_lang)
    {
        if ($class_name == null || $id_lang == null) {
            return '';
        }

        $sql = new DbQuery();
        $sql->select('tl.`name`');
        $sql->from('tab_lang', 'tl');
        $sql->innerJoin('tab', 't', 't.`id_tab` = tl.`id_tab`');
        $sql->where('t.`class_name` = \''.pSQL($class_name).'\'');
        $sql->where('tl.`id_lang` = '.(int)$id_lang);

        try {
            return (string)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        } catch (Exception $e) {
            return $this->l('Unknown');
        }
    }
}
