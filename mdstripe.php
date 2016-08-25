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

if (!defined('_PS_VERSION_')) {
    exit;
}

require_once dirname(__FILE__).'/vendor/autoload.php';
require_once dirname(__FILE__).'/classes/autoload.php';

/**
 * Class MDStripe
 */
class MDStripe extends PaymentModule
{
    const MENU_SETTINGS = 1;
    const MENU_TRANSACTIONS = 2;

    const ZIPCODE = 'MDSTRIPE_ZIPCODE';
    const COLLECT_BILLING = 'MDSTRIPE_COLLECT_BILLING';
    const COLLECT_SHIPPING = 'MDSTRIPE_COLLECT_SHIPPING';
    const BITCOIN = 'MDSTRIPE_BITCOIN';
    const ALIPAY = 'MDSTRIPE_ALIPAY';

    const SECRET_KEY = 'MDSTRIPE_SECRET_KEY';
    const PUBLISHABLE_KEY = 'MDSTRIPE_PUBLISHABLE_KEY';

    const SHOP_THUMB = 'MDSTRIPE_SHOP_THUMB';

    const STATUS_VALIDATED = 'MDSTRIPE_STAT_VALIDATED';
    const STATUS_PARTIAL_REFUND = 'MDSTRIPE_STAT_PART_REFUND';
    const USE_STATUS_PARTIAL_REFUND = 'MDSTRIPE_USE_STAT_PART_REFUND';
    const STATUS_REFUND = 'MDSTRIPE_STAT_REFUND';
    const USE_STATUS_REFUND = 'MDSTRIPE_USE_STAT_REFUND';
    const GENERATE_CREDIT_SLIP = 'MDSTRIPE_CREDIT_SLIP';

    const SHOW_PAYMENT_LOGOS = 'MDSTRIPE_PAYMENT_LOGOS';

    const OPTIONS_MODULE_SETTINGS = 1;

    const TLS_OK = 'MDSTRIPE_TLS_OK';
    const TLS_LAST_CHECK = 'MDSTRIPE_TLS_LAST_CHECK';

    const ENUM_TLS_OK = 1;
    const ENUM_TLS_ERROR = -1;

    const AUTO_UPDATE = true;
    const LAST_CHECK = 'MDSTRIPE_LAST_CHECK';
    const LAST_UPDATE = 'MDSTRIPE_LAST_UPDATE';

    const LATEST_VERSION = 'MDSTRIPE_LATEST_VERSION';
    const DOWNLOAD_URL = 'MDSTRIPE_DOWNLOAD_URL';
    const CHECK_INTERVAL = 86400;
    const UPDATE_INTERVAL = 60;

    const GITHUB_USER = 'firstred';
    const GITHUB_REPO = 'mdstripe';

    /** @var string $baseUrl Module base URL */
    public $baseUrl;
    public $latestVersion;
    public $lastCheck;
    public $downloadUrl;
    public $needsUpdate;

    public $moduleUrl;

    /** @var array Supported languages */
    public static $stripeLanguages = array('zh', 'nl', 'en', 'fr', 'de', 'it', 'ja', 'es');

    /** @var array Supported zero-decimal currencies */
    public static $zeroDecimalCurrencies =
        array('bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf', 'vdn', 'vuv', 'xaf', 'xof', 'xpf');

    /** @var int $menu Current menu */
    public $menu;

    /** @var array Hooks */
    public $hooks = array(
        'displayHeader',
        'backOfficeHeader',
        'payment',
        'displayPaymentEU',
        'paymentOptions',
        'paymentReturn',
        'displayPaymentTop',
        'displayAdminOrder',
    );

    /**
     * MDStripe constructor.
     */
    public function __construct()
    {
        $this->name = 'mdstripe';
        $this->tab = 'payments_gateways';
        $this->version = '1.0.8';
        $this->author = 'Michael Dekker';
        $this->need_instance = 1;

        $this->bootstrap = true;

        $this->controllers = array('hook', 'validation');

        $this->is_eu_compatible = 1;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';

        parent::__construct();

        $this->displayName = $this->l('Stripe');
        $this->description = $this->l('Accept payments with Stripe');

        $this->ps_versions_compliancy = array('min' => '1.6', 'max' => _PS_VERSION_);

        // Only check from Back Office
        if (Context::getContext()->cookie->id_employee) {
            if ($this->active && extension_loaded('curl') == false) {
                $this->context->controller->errors[] = $this->displayName.': '.$this->l('You have to enable the cURL extension on your server in order to use this module');
                $this->disable();

                return;
            }
            $this->lastCheck = Configuration::get(self::LAST_CHECK);
            $this->checkUpdate();
        }
    }

    /**
     * Install the module
     *
     * @return bool Whether the module has been successfully installed
     */
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->errors[] = $this->l('You have to enable the cURL extension on your server to install this module');

            return false;
        }

        if (!parent::install()) {
            parent::uninstall();

            return false;
        }

        foreach ($this->hooks as $hook) {
            $this->registerHook($hook);
        }

        require_once dirname(__FILE__).'/sql/install.php';

        Configuration::updateGlobalValue(self::LATEST_VERSION, '0.0.0');

        return true;
    }

    /**
     * Uninstall the module
     *
     * @return bool Whether the module has been successfully installed
     */
    public function uninstall()
    {
        foreach ($this->hooks as $hook) {
            $this->unregisterHook($hook);
        }

        Configuration::deleteByName(self::SECRET_KEY);
        Configuration::deleteByName(self::PUBLISHABLE_KEY);
        Configuration::deleteByName(self::USE_STATUS_REFUND);
        Configuration::deleteByName(self::USE_STATUS_PARTIAL_REFUND);
        Configuration::deleteByName(self::STATUS_PARTIAL_REFUND);
        Configuration::deleteByName(self::STATUS_REFUND);
        Configuration::deleteByName(self::GENERATE_CREDIT_SLIP);
        Configuration::deleteByName(self::ZIPCODE);
        Configuration::deleteByName(self::ALIPAY);
        Configuration::deleteByName(self::BITCOIN);
        Configuration::deleteByName(self::SHOW_PAYMENT_LOGOS);

        Configuration::deleteByName(self::LAST_CHECK);
        Configuration::deleteByName(self::LATEST_VERSION);
        Configuration::deleteByName(self::DOWNLOAD_URL);

        return parent::uninstall();
    }

    /**
     * Load the configuration form
     *
     * @return string HTML
     */
    public function getContent()
    {
        $this->makeModuleTrusted();

        $output = '';

        $this->initNavigation();

        $this->moduleUrl = Context::getContext()->link->getAdminLink('AdminModules', false).'&token='.Tools::getAdminTokenLite('AdminModules').'&'.http_build_query(array(
            'configure' => $this->name,
        ));

        $this->baseUrl = $this->context->link->getAdminLink('AdminModules', true).'&'.http_build_query(array(
                'configure' => $this->name,
                'tab_module' => $this->tab,
                'module_name' => $this->name,
            ));

        $output .= $this->postProcess();


        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'menutabs' => $this->initNavigation(),
            'stripe_webhook_url' => $this->context->link->getModuleLink($this->name, 'hook', array(), Tools::usingSecureMode()),
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/navbar.tpl');

        switch (Tools::getValue('menu')) {
            case self::MENU_TRANSACTIONS:
                return $output.$this->renderTransactionsPage();
            default:
                $this->menu = self::MENU_SETTINGS;

                return $output.$this->renderSettingsPage();
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
                'href' => $this->moduleUrl.'&menu='.self::MENU_SETTINGS,
                'active' => false,
                'icon' => 'icon-gears',
            ),
            self::MENU_TRANSACTIONS => array(
                'short' => $this->l('Transactions'),
                'desc' => $this->l('Stripe transactions'),
                'href' => $this->moduleUrl.'&menu='.self::MENU_TRANSACTIONS,
                'active' => false,
                'icon' => 'icon-credit-card',
            ),
        );

        switch (Tools::getValue('menu')) {
            case self::MENU_TRANSACTIONS:
                $this->menu = self::MENU_TRANSACTIONS;
                $menu[self::MENU_TRANSACTIONS]['active'] = true;
                break;
            default:
                $this->menu = self::MENU_SETTINGS;
                $menu[self::MENU_SETTINGS]['active'] = true;
                break;
        }

        return $menu;
    }

    /**
     * Render the general settings page
     *
     * @return string HTML
     * @throws Exception
     * @throws SmartyException
     */
    protected function renderSettingsPage()
    {
        $output = '';

        $this->context->smarty->assign(array(
            'module_url' => $this->moduleUrl.'&menu='.self::MENU_SETTINGS,
            'tls_ok' => (int) Configuration::get(self::TLS_OK),
            'curentVersion' => $this->version,
            'latestVersion' => $this->latestVersion,
            'lastCheck' => $this->lastCheck,
            'needsUpdate' => $this->needsUpdate,
            'baseUrl' => $this->baseUrl,
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/versioncheck.tpl');
        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/tlscheck.tpl');

        $output .= $this->renderGeneralOptions();

        return $output;
    }

    /**
     * Render the General options form
     *
     * @return string HTML
     */
    protected function renderGeneralOptions()
    {
        $helper = new HelperOptions();
        $helper->id = self::OPTIONS_MODULE_SETTINGS;
        $helper->module = $this;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->title = $this->displayName;
        $helper->show_toolbar = false;

        return $helper->generateOptions(array_merge($this->getGeneralOptions(), $this->getOrderOptions()));
    }

    /**
     * Get available general options
     *
     * @return array General options
     */
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
                    self::COLLECT_BILLING => array(
                        'title' => $this->l('Collect billing address'),
                        'type' => 'bool',
                        'name' => self::COLLECT_BILLING,
                        'value' => Configuration::get(self::COLLECT_BILLING),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::COLLECT_SHIPPING => array(
                        'title' => $this->l('Collect shipping address'),
                        'type' => 'bool',
                        'name' => self::COLLECT_SHIPPING,
                        'value' => Configuration::get(self::COLLECT_SHIPPING),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::ZIPCODE => array(
                        'title' => $this->l('Zipcode / postcode verification'),
                        'type' => 'bool',
                        'name' => self::ZIPCODE,
                        'value' => Configuration::get(self::ZIPCODE),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::BITCOIN => array(
                        'title' => $this->l('Accept Bitcoins'),
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
                    self::SHOW_PAYMENT_LOGOS => array(
                        'title' => $this->l('Show payment logos'),
                        'type' => 'bool',
                        'name' => self::SHOW_PAYMENT_LOGOS,
                        'value' => Configuration::get(self::SHOW_PAYMENT_LOGOS),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ),
            ),
        );
    }

    /**
     * Get available options for orders
     *
     * @return array Order options
     */
    protected function getOrderOptions()
    {
        $orderStatuses = OrderState::getOrderStates($this->context->language->id);

        $statusValidated = (int) Configuration::get(self::STATUS_VALIDATED);
        if ($statusValidated < 1) {
            $statusValidated = (int) Configuration::get('PS_OS_PAYMENT');
        }

        $statusPartialRefund = (int) Configuration::get(self::STATUS_PARTIAL_REFUND);
        if ($statusPartialRefund < 1) {
            $statusPartialRefund = (int) Configuration::get('PS_OS_REFUND');
        }

        $statusRefund = (int) Configuration::get(self::STATUS_REFUND);
        if ($statusRefund < 1) {
            $statusRefund = (int) Configuration::get('PS_OS_REFUND');
        }

        return array(
            'orders' => array(
                'title' => $this->l('Order Settings'),
                'icon' => 'icon-credit-card',
                'fields' => array(
                    self::STATUS_VALIDATED => array(
                        'title' => $this->l('Payment accepted status'),
                        'des' => $this->l('Order status to use when the payment is accepted'),
                        'type' => 'select',
                        'list' => $orderStatuses,
                        'identifier' => 'id_order_state',
                        'name' => self::STATUS_VALIDATED,
                        'value' => $statusValidated,
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::USE_STATUS_PARTIAL_REFUND => array(
                        'title' => $this->l('Use partial refund status'),
                        'type' => 'bool',
                        'name' => self::USE_STATUS_PARTIAL_REFUND,
                        'value' => Configuration::get(self::USE_STATUS_PARTIAL_REFUND),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::STATUS_PARTIAL_REFUND => array(
                        'title' => $this->l('Partial refund status'),
                        'desc' => $this->l('Order status to use when the order is partially refunded'),
                        'type' => 'select',
                        'list' => $orderStatuses,
                        'identifier' => 'id_order_state',
                        'name' => self::STATUS_PARTIAL_REFUND,
                        'value' => $statusPartialRefund,
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::USE_STATUS_REFUND => array(
                        'title' => $this->l('Use refund status'),
                        'type' => 'bool',
                        'name' => self::USE_STATUS_REFUND,
                        'value' => Configuration::get(self::USE_STATUS_REFUND),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                    self::STATUS_REFUND => array(
                        'title' => $this->l('Refund status'),
                        'desc' => $this->l('Order status to use when the order is refunded'),
                        'type' => 'select',
                        'list' => $orderStatuses,
                        'identifier' => 'id_order_state',
                        'name' => self::PUBLISHABLE_KEY,
                        'value' => $statusRefund,
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::GENERATE_CREDIT_SLIP => array(
                        'title' => $this->l('Generate credit slip'),
                        'desc' => $this->l('Automatically generate a credit slip when the order is fully refunded'),
                        'type' => 'bool',
                        'name' => self::GENERATE_CREDIT_SLIP,
                        'value' => Configuration::get(self::GENERATE_CREDIT_SLIP),
                        'validation' => 'isBool',
                        'cast' => 'intval',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button',
                ),
            ),
        );
    }

    /**
     * Render the transactions page
     *
     * @return string HTML
     * @throws Exception
     * @throws SmartyException
     */
    protected function renderTransactionsPage()
    {
        $output = '';

        $this->context->smarty->assign(array(
            'module_url' => $this->moduleUrl.'&menu='.self::MENU_TRANSACTIONS,
        ));

        $output .= $this->renderTransactionsList();

        return $output;
    }

    /**
     * Render the transactions list
     *
     * @return string HTML
     * @throws PrestaShopDatabaseException
     */
    protected function renderTransactionsList()
    {
        $fieldsList = array(
            'id_stripe_transaction' => array('title' => $this->l('ID'), 'width' => 'auto'),
            'type_icon' => array('type' => 'type_icon', 'title' => $this->l('Type'), 'width' => 'auto', 'color' => 'color', 'text' => 'type_text'),
            'amount' => array('type' => 'price', 'title' => $this->l('Amount'), 'width' => 'auto'),
            'card_last_digits' => array('type' => 'text', 'title' => $this->l('Credit card (last 4 digits)'), 'width' => 'auto'),
            'source_text' => array('type' => 'stripe_source', 'title' => $this->l('Source'), 'width' => 'auto'),
            'date_upd' => array('type' => 'datetime', 'title' => $this->l('Date & time'), 'width' => 'auto'),
        );

        if (Tools::isSubmit('submitResetstripe_transaction')) {
            $cookie = $this->context->cookie;
            foreach ($fieldsList as $fieldName => $field) {
                unset($cookie->{'stripe_transactionFilter_'.$fieldName});
                unset($_POST['stripe_transactionFilter_'.$fieldName]);
                unset($_GET['stripe_transactionFilter_'.$fieldName]);
            }
            unset($this->context->cookie->{'stripe_transactionOrderby'});
            unset($this->context->cookie->{'stripe_transactionOrderWay'});


            $cookie->write();
        }

        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from('stripe_transaction');

        $listTotal = (int) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

        $pagination = (int) $this->getSelectedPagination('stripe_transaction');
        $currentPage = (int) $this->getSelectedPage('stripe_transaction', $listTotal);

        $helperList = new HelperList();
        $helperList->id = 1;
        $helperList->shopLinkType = false;

        $helperList->list_id = 'stripe_transaction';

        $helperList->module = $this;

        $helperList->bulk_actions = array(
            'delete' => array(
                'text' => $this->l('Delete selected'),
                'confirm' => $this->l('Delete selected items?'),
            ),
        );

        $helperList->actions = array('View');

        $helperList->page = $currentPage;

        $helperList->_defaultOrderBy = 'id_stripe_transaction';

        if (Tools::isSubmit('stripe_transactionOrderby')) {
            $helperList->orderBy = Tools::getValue('stripe_transactionOrderby');
            $this->context->cookie->{'stripe_transactionOrderby'} = $helperList->orderBy;
        } elseif (!empty($this->context->cookie->{'stripe_transactionOrderby'})) {
            $helperList->orderBy = $this->context->cookie->{'stripe_transactionOrderby'};
        } else {
            $helperList->orderBy = 'id_stripe_transaction';
        }

        if (Tools::isSubmit('stripe_transactionOrderway')) {
            $helperList->orderWay = Tools::strtoupper(Tools::getValue('stripe_transactionOrderway'));
            $this->context->cookie->{'stripe_transactionOrderway'} = Tools::getValue('stripe_transactionOrderway');
        } elseif (!empty($this->context->cookie->{'stripe_transactionOrderway'})) {
            $helperList->orderWay = Tools::strtoupper($this->context->cookie->{'stripe_transactionOrderway'});
        } else {
            $helperList->orderWay = 'DESC';
        }

        $filterSql = $this->getSQLFilter($helperList, $fieldsList);

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('stripe_transaction', 'st');
        $sql->orderBy('`'.bqSQL($helperList->orderBy).'` '.pSQL($helperList->orderWay));
        $sql->where('1 '.$filterSql);
        $sql->limit($pagination, $currentPage - 1);

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        foreach ($results as &$result) {
            // Process results
            $currency = $this->getCurrencyIdByOrderId($result['id_order']);
            if (!in_array(Tools::strtolower($currency->iso_code), MDStripe::$zeroDecimalCurrencies)) {
                $result['amount'] = (float) ($result['amount'] / 100);
            }
            $result['id_currency'] = $currency->id;
            switch ($result['type']) {
                case StripeTransaction::TYPE_CHARGE:
                    $result['color'] = '#32CD32';
                    $result['type_icon'] = 'credit-card';
                    $result['type_text'] = $this->l('Charged');
                    break;
                case StripeTransaction::TYPE_PARTIAL_REFUND:
                    $result['color'] = '#FF8C00';
                    $result['type_icon'] = 'undo';
                    $result['type_text'] = $this->l('Partial refund');
                    break;
                case StripeTransaction::TYPE_FULL_REFUND:
                    $result['color'] = '#ec2e15';
                    $result['type_icon'] = 'undo';
                    $result['type_text'] = $this->l('Full refund');
                    break;
                default:
                    $result['color'] = '';
                    break;
            }

            switch ($result['source']) {
                case StripeTransaction::SOURCE_FRONT_OFFICE:
                    $result['source_text'] = $this->l('Front Office');
                    break;
                case StripeTransaction::SOURCE_BACK_OFFICE:
                    $result['source_text'] = $this->l('Back Office');
                    break;
                case StripeTransaction::SOURCE_WEBHOOK:
                    $result['source_text'] = $this->l('Webhook');
                    break;
                default:
                    $result['source_text'] = $this->l('Unknown');
                    break;
            }
        }

        $helperList->listTotal = count($results);

        $helperList->identifier = 'id_stripe_transaction';
        $helperList->title = $this->l('Transactions');
        $helperList->token = Tools::getAdminTokenLite('AdminModules');
        $helperList->currentIndex = AdminController::$currentIndex.'&'.http_build_query(array(
            'configure' => $this->name,
            'menu' => self::MENU_TRANSACTIONS,
        ));

        $helperList->table = 'stripe_transaction';

        $helperList->bulk_actions = false;

        return $helperList->generateList($results, $fieldsList);
    }

    /**
     * Save form data.
     */
    protected function postProcess()
    {
        $output = '';

        if (Tools::isSubmit('orderstriperefund') && Tools::isSubmit('stripe_refund_order') && Tools::isSubmit('stripe_refund_amount')) {
            $this->processRefund();
        } elseif ($this->menu == self::MENU_SETTINGS) {
            if (Tools::isSubmit('submitOptionsconfiguration')) {
                $output .= $this->postProcessGeneralOptions();
                $output .= $this->postProcessOrderOptions();
            }

            if (Tools::isSubmit('checktls') && (bool) Tools::getValue('checktls')) {
                $output .= $this->tlsCheck();
            }
        }
    }

    /**
     * Process General Options
     */
    protected function postProcessGeneralOptions()
    {
        $secretKey = Tools::getValue(self::SECRET_KEY);
        $publishableKey = Tools::getValue(self::PUBLISHABLE_KEY);
        $zipcode = (bool) Tools::getValue(self::ZIPCODE);
        $bitcoin = (bool) Tools::getValue(self::BITCOIN);
        $alipay = (bool) Tools::getValue(self::ALIPAY);
        $showPaymentLogos = (bool) Tools::getValue(self::SHOW_PAYMENT_LOGOS);
        $collectBilling = (bool) Tools::getValue(self::COLLECT_BILLING);
        $collectShipping = (bool) Tools::getValue(self::COLLECT_SHIPPING);

        if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            if (Shop::getContext() == Shop::CONTEXT_ALL) {
                $this->updateAllValue(self::SECRET_KEY, $secretKey);
                $this->updateAllValue(self::PUBLISHABLE_KEY, $publishableKey);
                $this->updateAllValue(self::ZIPCODE, $zipcode);
                $this->updateAllValue(self::BITCOIN, $bitcoin);
                $this->updateAllValue(self::ALIPAY, $alipay);
                $this->updateAllValue(self::SHOW_PAYMENT_LOGOS, $showPaymentLogos);
                $this->updateAllValue(self::COLLECT_BILLING, $collectBilling);
                $this->updateAllValue(self::COLLECT_SHIPPING, $collectShipping);
            } elseif (is_array(Tools::getValue('multishopOverrideOption'))) {
                $idShopGroup = (int) Shop::getGroupFromShop($this->getShopId(), true);
                $multishopOverride = Tools::getValue('multishopOverrideOption');
                if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                    foreach (Shop::getShops(false, $this->getShopId()) as $idShop) {
                        if (isset($multishopOverride[self::SECRET_KEY]) && $multishopOverride[self::SECRET_KEY]) {
                            Configuration::updateValue(self::SECRET_KEY, $secretKey, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::PUBLISHABLE_KEY]) && $multishopOverride[self::PUBLISHABLE_KEY]) {
                            Configuration::updateValue(self::PUBLISHABLE_KEY, $publishableKey, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::ZIPCODE]) && $multishopOverride[self::ZIPCODE]) {
                            Configuration::updateValue(self::ZIPCODE, $zipcode, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::BITCOIN]) && $multishopOverride[self::BITCOIN]) {
                            Configuration::updateValue(self::BITCOIN, $bitcoin, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::ALIPAY]) && $multishopOverride[self::ALIPAY]) {
                            Configuration::updateValue(self::ALIPAY, $alipay, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::SHOW_PAYMENT_LOGOS]) && $multishopOverride[self::SHOW_PAYMENT_LOGOS]) {
                            Configuration::updateValue(self::SHOW_PAYMENT_LOGOS, $showPaymentLogos, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::COLLECT_BILLING]) && $multishopOverride[self::COLLECT_BILLING]) {
                            Configuration::updateValue(self::COLLECT_BILLING, $collectBilling, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::COLLECT_SHIPPING]) && $multishopOverride[self::COLLECT_SHIPPING]) {
                            Configuration::updateValue(self::COLLECT_SHIPPING, $collectShipping, false, $idShopGroup, $idShop);
                        }
                    }
                } else {
                    $idShop = (int) $this->getShopId();
                    if (isset($multishopOverride[self::SECRET_KEY]) && $multishopOverride[self::SECRET_KEY]) {
                        Configuration::updateValue(self::SECRET_KEY, $secretKey, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::PUBLISHABLE_KEY]) && $multishopOverride[self::PUBLISHABLE_KEY]) {
                        Configuration::updateValue(self::PUBLISHABLE_KEY, $publishableKey, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::ZIPCODE]) && $multishopOverride[self::ZIPCODE]) {
                        Configuration::updateValue(self::ZIPCODE, $zipcode, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::BITCOIN]) && $multishopOverride[self::BITCOIN]) {
                        Configuration::updateValue(self::BITCOIN, $bitcoin, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::ALIPAY]) && $multishopOverride[self::ALIPAY]) {
                        Configuration::updateValue(self::ALIPAY, $alipay, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::SHOW_PAYMENT_LOGOS]) && $multishopOverride[self::SHOW_PAYMENT_LOGOS]) {
                        Configuration::updateValue(self::SHOW_PAYMENT_LOGOS, $showPaymentLogos, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::COLLECT_BILLING]) && $multishopOverride[self::COLLECT_BILLING]) {
                        Configuration::updateValue(self::COLLECT_BILLING, $collectBilling, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::COLLECT_SHIPPING]) && $multishopOverride[self::COLLECT_SHIPPING]) {
                        Configuration::updateValue(self::COLLECT_SHIPPING, $collectShipping, false, $idShopGroup, $idShop);
                    }
                }
            }
        } else {
            Configuration::updateValue(self::SECRET_KEY, $secretKey);
            Configuration::updateValue(self::PUBLISHABLE_KEY, $publishableKey);
            Configuration::updateValue(self::ZIPCODE, $zipcode);
            Configuration::updateValue(self::BITCOIN, $bitcoin);
            Configuration::updateValue(self::ALIPAY, $alipay);
            Configuration::updateValue(self::SHOW_PAYMENT_LOGOS, $showPaymentLogos);
            Configuration::updateValue(self::COLLECT_BILLING, $collectBilling);
            Configuration::updateValue(self::COLLECT_SHIPPING, $collectShipping);
        }
    }

    /**
     * Process Order Options
     */
    protected function postProcessOrderOptions()
    {
        $statusValidated = Tools::getValue(self::STATUS_VALIDATED);
        $useStatusRefund = Tools::getValue(self::USE_STATUS_REFUND);
        $statusRefund = Tools::getValue(self::STATUS_REFUND);
        $useStatusPartialRefund = Tools::getValue(self::USE_STATUS_PARTIAL_REFUND);
        $statusPartialRefund = Tools::getValue(self::STATUS_PARTIAL_REFUND);
        $generateCreditSlip = (bool) Tools::getValue(self::GENERATE_CREDIT_SLIP);

        if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            if (Shop::getContext() == Shop::CONTEXT_ALL) {
                $this->updateAllValue(self::STATUS_VALIDATED, $statusValidated);
                $this->updateAllValue(self::USE_STATUS_REFUND, $useStatusRefund);
                $this->updateAllValue(self::STATUS_REFUND, $statusRefund);
                $this->updateAllValue(self::STATUS_PARTIAL_REFUND, $statusPartialRefund);
                $this->updateAllValue(self::USE_STATUS_PARTIAL_REFUND, $useStatusPartialRefund);
                $this->updateAllValue(self::GENERATE_CREDIT_SLIP, $generateCreditSlip);
            } elseif (is_array(Tools::getValue('multishopOverrideOption'))) {
                $idShopGroup = (int) Shop::getGroupFromShop($this->getShopId(), true);
                $multishopOverride = Tools::getValue('multishopOverrideOption');
                if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                    foreach (Shop::getShops(false, $this->getShopId()) as $idShop) {
                        if (isset($multishopOverride[self::STATUS_VALIDATED]) && $multishopOverride[self::STATUS_VALIDATED]) {
                            Configuration::updateValue(self::STATUS_VALIDATED, $statusValidated, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::USE_STATUS_REFUND]) && $multishopOverride[self::USE_STATUS_REFUND]) {
                            Configuration::updateValue(self::USE_STATUS_REFUND, $useStatusRefund, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::STATUS_REFUND]) && $multishopOverride[self::STATUS_REFUND]) {
                            Configuration::updateValue(self::STATUS_REFUND, $statusRefund, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::USE_STATUS_PARTIAL_REFUND]) && $multishopOverride[self::USE_STATUS_PARTIAL_REFUND]) {
                            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $useStatusPartialRefund, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::STATUS_PARTIAL_REFUND]) && $multishopOverride[self::STATUS_PARTIAL_REFUND]) {
                            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $statusPartialRefund, false, $idShopGroup, $idShop);
                        }
                        if (isset($multishopOverride[self::GENERATE_CREDIT_SLIP]) && $multishopOverride[self::GENERATE_CREDIT_SLIP]) {
                            Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generateCreditSlip, false, $idShopGroup, $idShop);
                        }
                    }
                } else {
                    $idShop = (int) $this->getShopId();
                    if (isset($multishopOverride[self::STATUS_VALIDATED]) && $multishopOverride[self::STATUS_VALIDATED]) {
                        Configuration::updateValue(self::STATUS_VALIDATED, $statusValidated, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::USE_STATUS_REFUND]) && $multishopOverride[self::USE_STATUS_REFUND]) {
                        Configuration::updateValue(self::USE_STATUS_REFUND, $useStatusRefund, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::STATUS_REFUND]) && $multishopOverride[self::STATUS_REFUND]) {
                        Configuration::updateValue(self::STATUS_REFUND, $statusRefund, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::USE_STATUS_PARTIAL_REFUND]) && $multishopOverride[self::USE_STATUS_PARTIAL_REFUND]) {
                        Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $useStatusPartialRefund, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::STATUS_PARTIAL_REFUND]) && $multishopOverride[self::STATUS_PARTIAL_REFUND]) {
                        Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $statusPartialRefund, false, $idShopGroup, $idShop);
                    }
                    if (isset($multishopOverride[self::GENERATE_CREDIT_SLIP]) && $multishopOverride[self::GENERATE_CREDIT_SLIP]) {
                        Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generateCreditSlip, false, $idShopGroup, $idShop);
                    }
                }
            }
        } else {
            Configuration::updateValue(self::STATUS_VALIDATED, $statusValidated);
            Configuration::updateValue(self::USE_STATUS_REFUND, $useStatusRefund);
            Configuration::updateValue(self::STATUS_REFUND, $statusRefund);
            Configuration::updateValue(self::USE_STATUS_PARTIAL_REFUND, $useStatusPartialRefund);
            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $statusPartialRefund);
            Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generateCreditSlip);
        }
    }

    /**
     *
     */
    protected function processRefund()
    {
        $idOrder = (int) Tools::getValue('stripe_refund_order');
        $amount = (float) Tools::getValue('stripe_refund_amount');

        $idCharge = StripeTransaction::getChargeByIdOrder($idOrder);
        $order = new Order($idOrder);
        $currency = new Currency($order->id_currency);
        $orderTotal = $order->getTotalPaid();

        if (!in_array(Tools::strtolower($currency->iso_code), self::$zeroDecimalCurrencies)) {
            $amount = (int) ($amount * 100);
            $orderTotal = (int) ($orderTotal * 100);
        }

        $amountRefunded = StripeTransaction::getRefundedAmountByOrderId($idOrder);

        try {
            \Stripe\Stripe::setApiKey(Configuration::get(MDStripe::SECRET_KEY));
            \Stripe\Refund::create(array(
                'charge' => $idCharge,
                'amount' => $amount,
                'metadata' => array(
                    'from_back_office' => 'true',
                ),
            ));
        } catch (\Stripe\Error\InvalidRequest $e) {
            $this->context->controller->errors[] = sprintf('Invalid Stripe request: %s', $e->getMessage());

            return;
        }

        if (Configuration::get(MDStripe::USE_STATUS_REFUND) && 0 === (int) ($orderTotal - ($amountRefunded + $amount))) {
            // Full refund
            if (Configuration::get(MDStripe::GENERATE_CREDIT_SLIP)) {
                $sql = new DbQuery();
                $sql->select('od.`id_order_detail`, od.`product_quantity`');
                $sql->from('order_detail', 'od');
                $sql->where('od.`id_order` = '.(int) $order->id);

                $fullProductList = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

                if (is_array($fullProductList) && !empty($fullProductList)) {
                    $productList = array();
                    $quantityList = array();
                    foreach ($fullProductList as $dbOrderDetail) {
                        $idOrderDetail = (int) $dbOrderDetail['id_order_detail'];
                        $productList[] = (int) $idOrderDetail;
                        $quantityList[$idOrderDetail] = (int) $dbOrderDetail['product_quantity'];
                    }
                    OrderSlip::createOrderSlip($order, $productList, $quantityList, $order->getShipping());
                }
            }

            $transaction = new StripeTransaction();
            $transaction->card_last_digits = (int) StripeTransaction::getLastFourDigitsByChargeId($idCharge);
            $transaction->id_charge = $idCharge;
            $transaction->amount = $amount;
            $transaction->id_order = $order->id;
            $transaction->type = StripeTransaction::TYPE_FULL_REFUND;
            $transaction->source = StripeTransaction::SOURCE_BACK_OFFICE;
            $transaction->add();

            $orderHistory = new OrderHistory();
            $orderHistory->id_order = $order->id;
            $orderHistory->changeIdOrderState((int) Configuration::get(MDStripe::STATUS_REFUND), $idOrder);
            $orderHistory->addWithemail(true);
        } else {
            $transaction = new StripeTransaction();
            $transaction->card_last_digits = (int) StripeTransaction::getLastFourDigitsByChargeId($idCharge);
            $transaction->id_charge = $idCharge;
            $transaction->amount = $amount;
            $transaction->id_order = $order->id;
            $transaction->type = StripeTransaction::TYPE_PARTIAL_REFUND;
            $transaction->source = StripeTransaction::SOURCE_BACK_OFFICE;
            $transaction->add();

            if (Configuration::get(MDStripe::USE_STATUS_PARTIAL_REFUND)) {
                $orderHistory = new OrderHistory();
                $orderHistory->id_order = $order->id;
                $orderHistory->changeIdOrderState((int) Configuration::get(MDStripe::STATUS_PARTIAL_REFUND), $idOrder);
                $orderHistory->addWithemail(true);
            }
        }

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true).'&vieworder&id_order='.$idOrder);
    }

    /**
     * This method is used to render the payment button,
     * Take care if the button should be displayed or not.
     */
    public function hookPayment($params)
    {
        /** @var Cookie $cookie */
        if (Module::isEnabled('onepagecheckoutps') && !isset($params['cookie'])) {
            $cookie = $this->context->cookie;
        } else {
            $cookie = $params['cookie'];
        }

        $idCurrency = $params['cart']->id_currency;
        $currency = new Currency((int) $idCurrency);

        if (in_array($currency->iso_code, $this->limited_currencies) == false)
            return false;

        $this->checkShopThumb();

        $stripeEmail = $cookie->email;

        /** @var Cart $cart */
        $cart = $params['cart'];
        $currency = new Currency($cart->id_currency);

        $link = $this->context->link;

        $stripeAmount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), self::$zeroDecimalCurrencies)) {
            $stripeAmount = (int) ($stripeAmount * 100);
        }

        $this->context->smarty->assign(array(
            'stripe_email' => $stripeEmail,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => $stripeAmount,
            'id_cart' => (int) $cart->id,
            'stripe_secret_key' => Configuration::get(self::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(self::PUBLISHABLE_KEY),
            'stripe_locale' => self::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => (bool) Configuration::get(self::ZIPCODE),
            'stripe_bitcoin' => (bool) Configuration::get(self::BITCOIN) && Tools::strtolower($currency->iso_code) === 'usd',
            'stripe_alipay' => (bool) Configuration::get(self::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'validation', array(), Tools::usingSecureMode()),
            'showPaymentLogos' => Configuration::get(self::SHOW_PAYMENT_LOGOS),
            'stripeShopThumb' => $this->context->link->getMediaLink('/modules/mdstripe/views/img/shop'.$this->getShopId().'.jpg'),
            'stripe_collect_billing' => Configuration::get(self::COLLECT_BILLING),
            'stripe_collect_shipping' => Configuration::get(self::COLLECT_SHIPPING),
        ));

        if (Module::isEnabled('onepagecheckoutps')) {
            return $this->context->smarty->fetch($this->local_path.'views/templates/front/eupayment.tpl');
        }

        return $this->display(__FILE__, 'views/templates/hook/payment.tpl');
    }

    /**
     * Hook to Advcanced EU checkout
     *
     * @param array $params Hook parameters
     * @return array Smarty variables
     */
    public function hookDisplayPaymentEU($params)
    {
        if (!$this->active) {
            return array();
        }

        $this->checkShopThumb();

        $paymentOptions = array(
            'cta_text' => $this->l('Pay with Stripe'),
            'logo' => Media::getMediaPath($this->local_path.'views/img/stripebtnlogo.png'),
            'action' => $this->context->link->getModuleLink($this->name, 'eupayment', array(), Tools::usingSecureMode()),
            'stripeShopThumb' => $this->context->link->getMediaLink('/modules/mdstripe/views/img/shop'.$this->getShopId().'.jpg'),
        );

        return $paymentOptions;
    }

    /**
     * Hook to the new PS 1.7 payment options hook
     *
     * @param array $params Hook parameters
     * @return array|bool
     * @throws Exception
     * @throws SmartyException
     */
    public function hookPaymentOptions($params)
    {
        if (version_compare(_PS_VERSION_, '1.7.0.0', '<')) {
            return false;
        }
        if (!$this->active) {
            return array();
        }

        $this->checkShopThumb();

        /** @var Cookie $email */
        $cookie = $params['cookie'];
        $stripeEmail = $cookie->email;

        /** @var Cart $cart */
        $cart = $params['cart'];
        $currency = new Currency($cart->id_currency);

        $link = $this->context->link;

        $stripeAmount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), self::$zeroDecimalCurrencies)) {
            $stripeAmount = (int) ($stripeAmount * 100);
        }

        $this->context->smarty->assign(array(
            'stripe_email' => $stripeEmail,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => $stripeAmount,
            'id_cart' => (int) $cart->id,
            'stripe_secret_key' => Configuration::get(self::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(self::PUBLISHABLE_KEY),
            'stripe_locale' => self::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => (bool) Configuration::get(self::ZIPCODE),
            'stripe_bitcoin' => (bool) Configuration::get(self::BITCOIN) && Tools::strtolower($currency->iso_code) === 'usd',
            'stripe_alipay' => (bool) Configuration::get(self::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'validation', array(), Tools::usingSecureMode()),
            'stripeShopThumb' => $this->context->link->getMediaLink('/modules/mdstripe/views/img/shop'.$this->getShopId().'.jpg'),
            'stripe_collect_billing' => Configuration::get(self::COLLECT_BILLING),
            'stripe_collect_shipping' => Configuration::get(self::COLLECT_SHIPPING),
        ));

        $externalOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Stripe'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), Tools::usingSecureMode()))
            ->setInputs(array(
                'token' => array(
                    'name' => 'mdstripe-token',
                    'type' => 'hidden',
                    'value' => '',
                ),
                'id_cart' => array(
                    'name' => 'mdstripe-id_cart',
                    'type' => 'hidden',
                    'value' => $cart->id,
                ),
            ))
            ->setAdditionalInformation($this->context->smarty->fetch('module:mdstripe/views/templates/hook/17payment.tpl'));

        return array($externalOption);
    }

    /**
     * This hook is used to display the order confirmation page.
     *
     * @param array $params Hook parameters
     * @return string Hook HTML
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
     * @param array $params Hook parameters
     * @return string Hook HTML
     */
    public function hookDisplayPaymentTop($params)
    {
        $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');
        $this->context->controller->addCSS($this->local_path.'/views/css/front.css');

        return '';
    }

    /**
     * Hook to header: <head></head>
     *
     * @param array $params Hook parameters
     */
    public function hookHeader($params)
    {
        $this->makeModuleTrusted();

        if (Tools::getValue('module') === 'onepagecheckoutps' ||
            Tools::getValue('controller') === 'order-opc' ||
            Tools::getValue('controller') === 'orderopc' ||
            Tools::getValue('controller') === 'order') {
            $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');
        }
    }

    /**
     * Hook to back office header: <head></head>
     *
     * @param array $params Hook parameters
     */
    public function hookDisplayBackOfficeHeader($params)
    {
        $this->makeModuleTrusted();
    }

    /**
     * Display on Back Office order page
     *
     * @param array $params Hok parameters
     * @return string Hook HTML
     * @throws Exception
     * @throws SmartyException
     */
    public function hookDisplayAdminOrder($params)
    {
        if (StripeTransaction::getTransactionsByOrderId($params['id_order'], true)) {
            $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js');
            $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css');

            $order = new Order($params['id_order']);
            $orderCurrency = new Currency($order->id_currency);

            $totalRefundLeft = $order->getTotalPaid();
            if (!in_array(Tools::strtolower($orderCurrency->iso_code), MDStripe::$zeroDecimalCurrencies)) {
                $totalRefundLeft = (int) (Tools::ps_round($totalRefundLeft * 100, 0));
            }

            $amount = (int) StripeTransaction::getRefundedAmountByOrderId($order->id);

            $totalRefundLeft -= $amount;

            if (!in_array(Tools::strtolower($orderCurrency->iso_code), MDStripe::$zeroDecimalCurrencies)) {
                $totalRefundLeft = (float) ($totalRefundLeft / 100);
            }

            $this->context->smarty->assign(array(
                'stripe_transaction_list' => $this->renderAdminOrderTransactionList($params['id_order']),
                'stripe_currency_symbol' => $orderCurrency->sign,
                'stripe_total_amount' => $totalRefundLeft,
                'stripe_module_refund_action' => $this->context->link->getAdminLink('AdminModules', true).
                    '&configure=mdstripe&tab_module=payments_gateways&module_name=mdstripe&orderstriperefund',
                'id_order' => (int) $order->id,
            ));

            return $this->context->smarty->fetch($this->local_path.'views/templates/admin/adminorder.tpl');
        }

        return '';
    }

    /**
     * Render the admin order transaction list
     *
     * @param int $idOrder Order ID
     * @return string Transaction list HTML
     */
    protected function renderAdminOrderTransactionList($idOrder)
    {
        $results = StripeTransaction::getTransactionsByOrderId($idOrder);

        $order = new Order($idOrder);
        $currency = new Currency($order->id_currency);

        if (!in_array(Tools::strtolower($currency->iso_code), MDStripe::$zeroDecimalCurrencies)) {
            foreach ($results as &$result) {
                // Process results
                $result['amount'] = (float) ($result['amount'] / 100);
                switch ($result['type']) {
                    case StripeTransaction::TYPE_CHARGE:
                        $result['color'] = '#32CD32';
                        $result['type_icon'] = 'credit-card';
                        $result['type_text'] = $this->l('Charged');
                        break;
                    case StripeTransaction::TYPE_PARTIAL_REFUND:
                        $result['color'] = '#FF8C00';
                        $result['type_icon'] = 'undo';
                        $result['type_text'] = $this->l('Partial refund');
                        break;
                    case StripeTransaction::TYPE_FULL_REFUND:
                        $result['color'] = '#ec2e15';
                        $result['type_icon'] = 'undo';
                        $result['type_text'] = $this->l('Full refund');
                        break;
                    default:
                        $result['color'] = '';
                        break;
                }

                switch ($result['source']) {
                    case StripeTransaction::SOURCE_FRONT_OFFICE:
                        $result['source_text'] = $this->l('Front Office');
                        break;
                    case StripeTransaction::SOURCE_BACK_OFFICE:
                        $result['source_text'] = $this->l('Back Office');
                        break;
                    case StripeTransaction::SOURCE_WEBHOOK:
                        $result['source_text'] = $this->l('Webhook');
                        break;
                    default:
                        $result['source_text'] = $this->l('Unknown');
                        break;
                }
            }
        }

        $helperList = new HelperList();
        $helperList->id = 1;

        $helperList->list_id = 'stripe_transaction';
        $helperList->shopLinkType = false;

        $helperList->no_link = true;

        $helperList->_defaultOrderBy = 'date_add';

        $helperList->simple_header = true;

        $helperList->module = $this;

        $fieldsList = array(
            'id_stripe_transaction' => array('title' => $this->l('ID'), 'width' => 'auto'),
            'type_icon' => array('type' => 'type_icon', 'title' => $this->l('Type'), 'width' => 'auto', 'color' => 'color', 'text' => 'type_text'),
            'amount' => array('type' => 'price', 'title' => $this->l('Amount'), 'width' => 'auto'),
            'card_last_digits' => array('type' => 'text', 'title' => $this->l('Credit card (last 4 digits)'), 'width' => 'auto'),
            'source_text' => array('type' => 'stripe_source', 'title' => $this->l('Source'), 'width' => 'auto'),
            'date_upd' => array('type' => 'datetime', 'title' => $this->l('Date & time'), 'width' => 'auto'),
        );

        $helperList->identifier = 'id_stripe_transaction';
        $helperList->title = $this->l('Transactions');
        $helperList->token = Tools::getAdminTokenLite('AdminOrders');
        $helperList->currentIndex = AdminController::$currentIndex.'&'.http_build_query(array(
            'id_order' => $idOrder,
        ));

        // Hide actions
        $helperList->tpl_vars['show_filters'] = false;
        $helperList->actions = false;
        $helperList->bulk_actions = array();

        $helperList->table = 'stripe_transaction';

        return $helperList->generateList($results, $fieldsList);
    }

    /**
     * Update configuration value in ALL contexts
     *
     * @param string $key    Configuration key
     * @param mixed  $values Configuration values, can be string or array with id_lang as key
     * @param bool   $html   Contains HTML
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
        return (int) Context::getContext()->shop->id;
    }

    /**
     * Get the Stripe language
     *
     * @param string $locale IETF locale
     * @return string Stripe language
     */
    public static function getStripeLanguage($locale)
    {
        $languageIso = Tools::strtolower(Tools::substr($locale, 0, 2));

        if (in_array($languageIso, self::$stripeLanguages)) {
            return $languageIso;
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
        $langId = Context::getContext()->language->id;
        $output = array();
        if (Configuration::get('PS_DISABLE_NON_NATIVE_MODULE')) {
            $output[] = $this->l('Non native modules such as this one are disabled. Go to').' "'.
                $this->getTabName('AdminParentPreferences', $langId).
                ' > '.
                $this->getTabName('AdminPerformance', $langId).
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
     * @param $className string Class name of tab
     * @param $idLang int Language id
     *
     * @return string Returns the localized tab name
     */
    protected function getTabName($className, $idLang)
    {
        if ($className == null || $idLang == null) {
            return '';
        }

        $sql = new DbQuery();
        $sql->select('tl.`name`');
        $sql->from('tab_lang', 'tl');
        $sql->innerJoin('tab', 't', 't.`id_tab` = tl.`id_tab`');
        $sql->where('t.`class_name` = \''.pSQL($className).'\'');
        $sql->where('tl.`id_lang` = '.(int) $idLang);

        try {
            return (string) Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);
        } catch (Exception $e) {
            return $this->l('Unknown');
        }
    }

    /**
     * Check if TLS 1.2 is supported
     */
    protected function tlsCheck()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://tlstest.paypal.com/',
        ));
        $result = curl_exec($curl);

        if ($result == 'PayPal_Connection_OK') {
            $this->updateAllValue(self::TLS_OK, self::ENUM_TLS_OK);
        } else {
            $this->updateAllValue(self::TLS_OK, self::ENUM_TLS_ERROR);
        }
    }

    /**
     * Get selected pagination
     *
     * @param int $idList
     * @param int $defaultPagination
     * @return mixed
     */
    protected function getSelectedPagination($idList, $defaultPagination = 50)
    {
        $selectedPagination = Tools::getValue(
            $idList.'_pagination',
            isset($this->context->cookie->{$idList.'_pagination'}) ? $this->context->cookie->{$idList.'_pagination'} : $defaultPagination
        );

        return $selectedPagination;
    }

    /**
     * Get selected page
     *
     * @param int $idList List ID
     * @param int $listTotal Total list items
     *
     * @return int|mixed
     */
    protected function getSelectedPage($idList, $listTotal)
    {
        /* Determine current page number */
        $page = (int) Tools::getValue('submitFilter'.$idList);

        if (!$page) {
            $page = 1;
        }

        $totalPages = max(1, ceil($listTotal / $this->getSelectedPagination($idList)));

        if ($page > $totalPages) {
            $page = $totalPages;
        }

        $this->page = (int) $page;

        return $page;
    }

    /**
     * @param $helperList
     * @param $fieldsList
     * @return array|string
     */
    protected function getSQLFilter($helperList, $fieldsList)
    {
        /** @var HelperList $helperList */
        if (!isset($helperList->list_id)) {
            $helperList->list_id = $helperList->table;
        }

        $prefix = '';
        $sqlFilter = '';

        if (isset($helperList->list_id)) {
            foreach ($_POST as $key => $value) {
                if ($value === '') {
                    unset($helperList->context->cookie->{$prefix.$key});
                } elseif (stripos($key, $helperList->list_id.'Filter_') === 0) {
                    $helperList->context->cookie->{$prefix.$key} = !is_array($value) ? $value : serialize($value);
                } elseif (stripos($key, 'submitFilter') === 0) {
                    $helperList->context->cookie->$key = !is_array($value) ? $value : serialize($value);
                }
            }

            foreach ($_GET as $key => $value) {
                if (stripos($key, $helperList->list_id.'Filter_') === 0) {
                    $helperList->context->cookie->{$prefix.$key} = !is_array($value) ? $value : serialize($value);
                } elseif (stripos($key, 'submitFilter') === 0) {
                    $helperList->context->cookie->$key = !is_array($value) ? $value : serialize($value);
                }
                if (stripos($key, $helperList->list_id.'Orderby') === 0 && Validate::isOrderBy($value)) {
                    if ($value === '' || $value == $helperList->_defaultOrderBy) {
                        unset($helperList->context->cookie->{$prefix.$key});
                    } else {
                        $helperList->context->cookie->{$prefix.$key} = $value;
                    }
                } elseif (stripos($key, $helperList->list_id.'Orderway') === 0 && Validate::isOrderWay($value)) {
                    if ($value === '' || $value == $helperList->_defaultOrderWay) {
                        unset($helperList->context->cookie->{$prefix.$key});
                    } else {
                        $helperList->context->cookie->{$prefix.$key} = $value;
                    }
                }
            }
        }

        $filters = $helperList->context->cookie->getFamily($prefix.$helperList->list_id.'Filter_');
        $definition = false;
        if (isset($helperList->className) && $helperList->className) {
            $definition = ObjectModel::getDefinition($helperList->className);
        }

        foreach ($filters as $key => $value) {
            /* Extracting filters from $_POST on key filter_ */
            if ($value != null && !strncmp($key, $prefix.$helperList->list_id.'Filter_', 7 + Tools::strlen($prefix.$helperList->list_id))) {
                $key = Tools::substr($key, 7 + Tools::strlen($prefix.$helperList->list_id));
                /* Table alias could be specified using a ! eg. alias!field */
                $tmpTab = explode('!', $key);
                $filter = count($tmpTab) > 1 ? $tmpTab[1] : $tmpTab[0];

                if ($field = $this->filterToField($fieldsList, $key, $filter)) {
                    $type = (array_key_exists('filter_type', $field) ? $field['filter_type'] : (array_key_exists('type', $field) ? $field['type'] : false));
                    if (($type == 'date' || $type == 'datetime') && is_string($value)) {
                        $value = Tools::unSerialize($value);
                    }
                    $key = isset($tmpTab[1]) ? $tmpTab[0].'.`'.$tmpTab[1].'`' : '`'.$tmpTab[0].'`';
                    $sqlFilter = & $helperList->_filter;

                    /* Only for date filtering (from, to) */
                    if (is_array($value)) {
                        if (isset($value[0]) && !empty($value[0])) {
                            if (!Validate::isDate($value[0])) {
                                return $this->displayError('The \'From\' date format is invalid (YYYY-MM-DD)');
                            } else {
                                $sqlFilter .= ' AND '.pSQL($key).' >= \''.pSQL(Tools::dateFrom($value[0])).'\'';
                            }
                        }

                        if (isset($value[1]) && !empty($value[1])) {
                            if (!Validate::isDate($value[1])) {
                                return $this->displayError('The \'To\' date format is invalid (YYYY-MM-DD)');
                            } else {
                                $sqlFilter .= ' AND '.pSQL($key).' <= \''.pSQL(Tools::dateTo($value[1])).'\'';
                            }
                        }
                    } else {
                        $sqlFilter .= ' AND ';
                        $checkKey = ($key == $helperList->identifier || $key == '`'.$helperList->identifier.'`');
                        $alias = ($definition && !empty($definition['fields'][$filter]['shop'])) ? 'sa' : 'a';

                        if ($type == 'int' || $type == 'bool') {
                            $sqlFilter .= (($checkKey || $key == '`active`') ?  $alias.'.' : '').pSQL($key).' = '.(int)$value.' ';
                        } elseif ($type == 'decimal') {
                            $sqlFilter .= ($checkKey ?  $alias.'.' : '').pSQL($key).' = '.(float)$value.' ';
                        } elseif ($type == 'select') {
                            $sqlFilter .= ($checkKey ?  $alias.'.' : '').pSQL($key).' = \''.pSQL($value).'\' ';
                        } elseif ($type == 'price') {
                            $value = (float)str_replace(',', '.', $value);
                            $sqlFilter .= ($checkKey ?  $alias.'.' : '').pSQL($key).' = '.pSQL(trim($value)).' ';
                        } else {
                            $sqlFilter .= ($checkKey ?  $alias.'.' : '').pSQL($key).' LIKE \'%'.pSQL(trim($value)).'%\' ';
                        }
                    }
                }
            }
        }

        return $sqlFilter;
    }

    /**
     * @param $fieldsList
     * @param $key
     * @param $filter
     * @return bool
     */
    protected function filterToField($fieldsList, $key, $filter)
    {
        foreach ($fieldsList as $field) {
            if (array_key_exists('filter_key', $field) && $field['filter_key'] == $key) {
                return $field;
            }
        }
        if (array_key_exists($filter, $fieldsList)) {
            return $fieldsList[$filter];
        }
        
        return false;
    }

    /**
     * @param $idOrder
     * @return Currency
     */
    protected function getCurrencyIdByOrderId($idOrder)
    {
        $order = new Order($idOrder);
        if (Validate::isLoadedObject($order)) {
            $currency = new Currency($order->id_currency);
        } else {
            $currency = new Currency((int) Configuration::get('PS_CURRENCY_DEFAULT'));
        }

        return $currency;
    }

    /**
     * Check if shop thumbnail exists
     */
    protected function checkShopThumb()
    {
        $dbShopThumb = Configuration::get(self::SHOP_THUMB);
        if (empty($dbShopThumb) || !file_exists(_PS_IMG_.$dbShopThumb)) {
            ImageManager::resize(
                _PS_IMG_DIR_.Configuration::get('PS_LOGO'),
                _PS_MODULE_DIR_.'mdstripe/views/img/shop'.$this->getShopId().'.jpg',
                128,
                128
            );
        }
    }

    /**
     * Check for module updates
     */
    protected function checkUpdate()
    {
        $lastCheck = (int) Configuration::get(self::LAST_CHECK);
        $lastUpdate = (int) Configuration::get(self::LAST_UPDATE);
        if ($lastCheck < (time() - self::CHECK_INTERVAL) || Tools::getValue($this->name.'CheckUpdate')) {
            $this->lastCheck = time();
            Configuration::updateGlobalValue(self::LAST_CHECK, time());
            // Initialize GitHub Client
            $client = new \Github\Client(
                new \Github\HttpClient\CachedHttpClient(array('cache_dir' => '/tmp/github-api-cache'))
            );

            // Check the release tag
            try {
                $latestRelease = $client->api('repo')->releases()->latest(self::GITHUB_USER, self::GITHUB_REPO);
                if (isset($latestRelease['tag_name']) && $latestRelease['tag_name']) {
                    if (version_compare($this->version, $latestRelease['tag_name'], '<') &&
                        isset($latestRelease['assets'][0]['browser_download_url'])) {
                        Configuration::updateGlobalValue(self::LATEST_VERSION, $latestRelease['tag_name']);
                        Configuration::updateGlobalValue(self::DOWNLOAD_URL, $latestRelease['assets'][0]['browser_download_url']);
                        $this->latestVersion = $latestRelease['tag_name'];
                        $this->downloadUrl = $latestRelease['assets'][0]['browser_download_url'];
                    }
                }
            } catch (Exception $e) {
                $this->addWarning($e->getMessage());
            }
        } else {
            $this->latestVersion = Configuration::get(self::LATEST_VERSION);
            $this->downloadUrl = Configuration::get(self::DOWNLOAD_URL);
        }
        $this->needsUpdate = version_compare($this->version, $this->latestVersion, '<');
        if ($this->needsUpdate &&
            (self::AUTO_UPDATE && $lastUpdate < (time() - self::UPDATE_INTERVAL) || Tools::getValue($this->name.'ApplyUpdate'))
        ) {
            $zipLocation = _PS_MODULE_DIR_.$this->name.'.zip';
            if (@!file_exists($zipLocation)) {
                file_put_contents($zipLocation, fopen($this->downloadUrl, 'r'));
            }
            if (@file_exists($zipLocation)) {
                $this->extractArchive($zipLocation);
            } else {
                // We have an outdated URL, reset last check
                Configuration::updateGlobalValue(self::LAST_CHECK, 0);
            }
        }
    }

    /**
     * Add information message
     *
     * @param string $message Message
     */
    protected function addInformation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->informations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->informations[] = $message;
        }
    }

    /**
     * Add confirmation message
     *
     * @param string $message Message
     */
    protected function addConfirmation($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->confirmations[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->confirmations[] = $message;
        }
    }

    /**
     * Add warning message
     *
     * @param string $message Message
     */
    protected function addWarning($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->warnings[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Add error message
     *
     * @param string $message Message
     */
    protected function addError($message)
    {
        if (!Tools::isSubmit('configure')) {
            $this->context->controller->errors[] = '<a href="'.$this->baseUrl.'">'.$this->displayName.': '.$message.'</a>';
        } else {
            // Do not add error in this case
            // It will break execution of AdminController
            $this->context->controller->warnings[] = $message;
        }
    }

    /**
     * Extract module archive
     *
     * @param string $file     File location
     * @param bool   $redirect Whether there should be a redirection after extracting
     * @return bool
     */
    protected function extractArchive($file, $redirect = true)
    {
        $zipFolders = array();
        $tmpFolder = _PS_MODULE_DIR_.'selfupdate'.md5(time());
        if (@!file_exists($file)) {
            $this->addError($this->l('Module archive could not be downloaded'));

            return false;
        }
        $success = false;
        if (Tools::substr($file, -4) == '.zip') {
            if (Tools::ZipExtract($file, $tmpFolder) && file_exists($tmpFolder.DIRECTORY_SEPARATOR.$this->name)) {
                if (@rename(_PS_MODULE_DIR_.$this->name, _PS_MODULE_DIR_.$this->name.'backup') && @rename($tmpFolder.DIRECTORY_SEPARATOR.$this->name, _PS_MODULE_DIR_.$this->name)) {
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name.'backup');
                    $success = true;
                } else {
                    if (file_exists(_PS_MODULE_DIR_.$this->name.'backup')) {
                        $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$this->name);
                        @rename(_PS_MODULE_DIR_.$this->name.'backup', _PS_MODULE_DIR_.$this->name);
                    }
                }
            }
        } else {
            require_once(_PS_TOOL_DIR_.'tar/Archive_Tar.php');
            $archive = new Archive_Tar($file);
            if ($archive->extract($tmpFolder)) {
                $zipFolders = scandir($tmpFolder);
                if ($archive->extract(_PS_MODULE_DIR_)) {
                    $success = true;
                }
            }
        }
        if (!$success) {
            $this->addError($this->l('There was an error while extracting the update (file may be corrupted).'));
            // Force a new check
            Configuration::updateGlobalValue(self::LAST_CHECK, 0);
        } else {
            //check if it's a real module
            foreach ($zipFolders as $folder) {
                if (!in_array($folder, array('.', '..', '.svn', '.git', '__MACOSX')) && !Module::getInstanceByName($folder)) {
                    $this->addError(sprintf($this->l('The module %1$s that you uploaded is not a valid module.'), $folder));
                    $this->recursiveDeleteOnDisk(_PS_MODULE_DIR_.$folder);
                }
            }
        }
        @unlink($file);
        $this->recursiveDeleteOnDisk($tmpFolder);
        if ($success) {
            Configuration::updateGlobalValue(self::LAST_UPDATE, (int) time());
            if (function_exists('opcache_reset')) {
                opcache_reset();
            }
            if ($redirect) {
                Tools::redirectAdmin($this->context->link->getAdminLink('AdminModules', true).'&doNotAutoUpdate=1');
            }
        }
        
        return $success;
    }

    /**
     * Delete folder recursively
     *
     * @param string $dir Directory
     */
    protected function recursiveDeleteOnDisk($dir)
    {
        if (strpos(realpath($dir), realpath(_PS_MODULE_DIR_)) === false) {
            return;
        }
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != '.' && $object != '..') {
                    if (filetype($dir.'/'.$object) == 'dir') {
                        $this->recursiveDeleteOnDisk($dir.'/'.$object);
                    } else {
                        @unlink($dir.'/'.$object);
                    }
                }
            }
            reset($objects);
            rmdir($dir);
        }
    }

    /**
     * Make this module trusted and add it to the active payments list
     *
     * @return void
     */
    protected function makeModuleTrusted()
    {
        // Remove untrusted
        $untrustedXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_UNTRUSTED_MODULES_LIST);
        $module = $untrustedXml->xpath('//module[@name="'.$this->name.'"]');
        if (empty($module)) {
            // Module list has not been refreshed, return
            return;
        }
        unset($module[0][0]);
        $untrustedXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_UNTRUSTED_MODULES_LIST);

        // Add untrusted
        $trustedXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_TRUSTED_MODULES_LIST);
        /** @var SimpleXMLElement $modules */
        @$modules = $trustedXml->xpath('//modules')[0];
        if (empty($modules)) {
            return;
        }
        /** @var SimpleXMLElement $module */
        $module = $modules->addChild('module');
        $module->addAttribute('name', $this->name);
        $trustedXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_TRUSTED_MODULES_LIST);

        // Add to active payments list
        $modulesTabXml = simplexml_load_file(_PS_ROOT_DIR_.Module::CACHE_FILE_TAB_MODULES_LIST);

        $moduleFound = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]/module[@name="mdstripe"]');
        if (!empty($moduleFound)) {
            return;
        }

        // Find highest position
        /** @var array $modules */
        $modules = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]/module');
        $highestPosition = 0;
        foreach ($modules as $module) {
            /** @var SimpleXMLElement $module */
            foreach ($module->attributes() as $name => $attribute) {
                if ($name == 'position' && $attribute[0] > $highestPosition) {
                    $highestPosition = (int) $attribute[0];
                }
            }
        }
        $highestPosition++;
        /** @var SimpleXMLElement $modules */
        @$modules = $modulesTabXml->xpath('//tab[@class_name="AdminPayment"]')[0];
        if (empty($modules)) {
            return;
        }
        $module = $modules->addChild('module');
        $module->addAttribute('name', $this->name);
        $module->addAttribute('position', $highestPosition);
        $modulesTabXml->saveXML(_PS_ROOT_DIR_.Module::CACHE_FILE_TAB_MODULES_LIST);
    }
}
