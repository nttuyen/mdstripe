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

use /** @noinspection PhpUndefinedNamespaceInspection */
    PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class MDStripe extends PaymentModule
{
    const MENU_SETTINGS = 1;
    const MENU_TRANSACTIONS = 2;

    const ZIPCODE = 'MDSTRIPE_ZIPCODE';
    const BITCOIN = 'MDSTRIPE_BITCOIN';
    const ALIPAY = 'MDSTRIPE_ALIPAY';

    const SECRET_KEY = 'MDSTRIPE_SECRET_KEY';
    const PUBLISHABLE_KEY = 'MDSTRIPE_PUBLISHABLE_KEY';

    const STATUS_VALIDATED = 'MDSTRIPE_STAT_VALIDATED';
    const STATUS_PARTIAL_REFUND = 'MDSTRIPE_STAT_PART_REFUND';
    const USE_STATUS_PARTIAL_REFUND = 'MDSTRIPE_USE_STAT_PART_REFUND';
    const STATUS_REFUND = 'MDSTRIPE_STAT_REFUND';
    const USE_STATUS_REFUND = 'MDSTRIPE_USE_STAT_REFUND';
    const GENERATE_CREDIT_SLIP = 'MDSTRIPE_CREDIT_SLIP';

    const OPTIONS_MODULE_SETTINGS = 1;

    const TLS_OK = 'MDSTRIPE_TLS_OK';
    const TLS_LAST_CHECK = 'MDSTRIPE_TLS_LAST_CHECK';

    const ENUM_TLS_OK = 1;
    const ENUM_TLS_ERROR = -1;

    public $module_url;

    /** @var array Supported languages */
    public static $stripe_languages = array('zh', 'nl', 'en', 'fr', 'de', 'it', 'ja', 'es');

    /** @var array Supported zero-decimal currencies */
    public static $zero_decimal_currencies =
        array('bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg', 'rwf',
        'vdn', 'vuv', 'xaf', 'xof', 'xpf');

    /** @var int $menu Current menu */
    public $menu;

    /**
     * MDStripe constructor.
     */
    public function __construct()
    {
        $this->name = 'mdstripe';
        $this->tab = 'payments_gateways';
        $this->version = '0.9.0';
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

        require_once dirname(__FILE__).'/sql/install.php';

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
        'paymentOptions',
        'paymentReturn',
        'displayPaymentTop',
        'displayAdminOrder',
    );

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        $this->registerHook('paymentOptions');
        $output = '';

        $this->initNavigation();

        $this->module_url = Context::getContext()->link->getAdminLink('AdminModules', false).'&token='.Tools::getAdminTokenLite('AdminModules').'&'.
            http_build_query(array(
                'configure' => $this->name,
            ));

        $output .= $this->postProcess();


        $this->context->smarty->assign(array(
            'module_dir' => $this->_path,
            'menutabs' => $this->initNavigation(),
            'stripe_webhook_url' => $this->context->link->getModuleLink($this->name, 'hook', array(), true),
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
                'href' => $this->module_url.'&menu='.self::MENU_SETTINGS,
                'active' => false,
                'icon' => 'icon-gears',
            ),
            self::MENU_TRANSACTIONS => array(
                'short' => $this->l('Transactions'),
                'desc' => $this->l('Stripe transactions'),
                'href' => $this->module_url.'&menu='.self::MENU_TRANSACTIONS,
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
            'module_url' => $this->module_url.'&menu='.self::MENU_SETTINGS,
            'tls_ok' => (int)Configuration::get(self::TLS_OK)
        ));

        $output .= $this->context->smarty->fetch($this->local_path.'views/templates/admin/configure.tpl');
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
                        'cast' => 'strval'
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
                ),
                'submit' => array(
                    'title' => $this->l('Save'),
                    'class' => 'button'
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
        $order_statuses = OrderState::getOrderStates($this->context->language->id);

        $status_validated = (int)Configuration::get(self::STATUS_VALIDATED);
        if ($status_validated < 1) {
            $status_validated = (int)Configuration::get('PS_OS_PAYMENT');
        }

        $status_partial_refund = (int)Configuration::get(self::STATUS_PARTIAL_REFUND);
        if ($status_partial_refund < 1) {
            $status_partial_refund = (int)Configuration::get('PS_OS_REFUND');
        }

        $status_refund = (int)Configuration::get(self::STATUS_REFUND);
        if ($status_refund < 1) {
            $status_refund = (int)Configuration::get('PS_OS_REFUND');
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
                        'list' => $order_statuses,
                        'identifier' => 'id_order_state',
                        'name' => self::STATUS_VALIDATED,
                        'value' => $status_validated,
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::USE_STATUS_PARTIAL_REFUND=> array(
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
                        'list' => $order_statuses,
                        'identifier' => 'id_order_state',
                        'name' => self::STATUS_PARTIAL_REFUND,
                        'value' => $status_partial_refund,
                        'validation' => 'isString',
                        'cast' => 'strval',
                    ),
                    self::USE_STATUS_REFUND=> array(
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
                        'list' => $order_statuses,
                        'identifier' => 'id_order_state',
                        'name' => self::PUBLISHABLE_KEY,
                        'value' => $status_refund,
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
                    'class' => 'button'
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
            'module_url' => $this->module_url.'&menu='.self::MENU_TRANSACTIONS,
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
        $fields_list = array(
            'id_stripe_transaction' => array('title' => $this->l('ID'), 'width' => 'auto'),
            'type_icon' => array('type' => 'type_icon', 'title' => $this->l('Type'), 'width' => 'auto', 'color' => 'color', 'text' => 'type_text'),
            'amount' => array('type' => 'price', 'title' => $this->l('Amount'), 'width' => 'auto'),
            'card_last_digits' => array('type' => 'text', 'title' => $this->l('Credit card (last 4 digits)'), 'width' => 'auto'),
            'source_text' => array('type' => 'stripe_source', 'title' => $this->l('Source'), 'width' => 'auto'),
            'date_upd' => array('type' => 'datetime', 'title' => $this->l('Date & time'), 'width' => 'auto'),
        );

        if (Tools::isSubmit('submitResetstripe_transaction')) {
            $cookie = $this->context->cookie;
            foreach ($fields_list as $field_name => $field) {
                unset($cookie->{'stripe_transactionFilter_'.$field_name});
                unset($_POST['stripe_transactionFilter_'.$field_name]);
                unset($_GET['stripe_transactionFilter_'.$field_name]);
            }
            unset($this->context->cookie->{'stripe_transactionOrderby'});
            unset($this->context->cookie->{'stripe_transactionOrderWay'});


            $cookie->write();
        }

        $sql = new DbQuery();
        $sql->select('COUNT(*)');
        $sql->from('stripe_transaction');

        $list_total = (int)Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

        $pagination = (int)$this->getSelectedPagination('stripe_transaction');
        $current_page = (int)$this->getSelectedPage('stripe_transaction', $list_total);

        $helper_list = new HelperList();

        $helper_list->list_id = 'stripe_transaction';

        $helper_list->module = $this;

        $helper_list->bulk_actions = array(
            'delete'=>array(
                'text'=>$this->l('Delete selected'),
                'confirm'=>$this->l('Delete selected items?'))
        );

        $helper_list->actions = array('View');

        $helper_list->page = $current_page;

        $helper_list->_defaultOrderBy = 'id_stripe_transaction';

        if (Tools::isSubmit('stripe_transactionOrderby')) {
            $helper_list->orderBy = Tools::getValue('stripe_transactionOrderby');
            $this->context->cookie->{'stripe_transactionOrderby'} = $helper_list->orderBy;
        } elseif (!empty($this->context->cookie->{'stripe_transactionOrderby'})) {
            $helper_list->orderBy = $this->context->cookie->{'stripe_transactionOrderby'};
        } else {
            $helper_list->orderBy = 'id_stripe_transaction';
        }

        if (Tools::isSubmit('stripe_transactionOrderway')) {
            $helper_list->orderWay = Tools::strtoupper(Tools::getValue('stripe_transactionOrderway'));
            $this->context->cookie->{'stripe_transactionOrderway'} = Tools::getValue('stripe_transactionOrderway');
        } elseif (!empty($this->context->cookie->{'stripe_transactionOrderway'})) {
            $helper_list->orderWay = Tools::strtoupper($this->context->cookie->{'stripe_transactionOrderway'});
        } else {
            $helper_list->orderWay = 'DESC';
        }

        $filter_sql = $this->getSQLFilter($helper_list, $fields_list);

        $sql = new DbQuery();
        $sql->select('*');
        $sql->from('stripe_transaction', 'st');
        $sql->orderBy('`'.bqSQL($helper_list->orderBy).'` '.pSQL($helper_list->orderWay));
        $sql->where('1 '.$filter_sql);
        $sql->limit($pagination, $current_page - 1);

        $results = Db::getInstance(_PS_USE_SQL_SLAVE_)->executeS($sql);

        foreach ($results as &$result) {
            // Process results
            $currency = $this->getCurrencyIdByOrderId($result['id_order']);
            if (!in_array(Tools::strtolower($currency->iso_code), MDStripe::$zero_decimal_currencies)) {
                $result['amount'] = (float)($result['amount'] / 100);
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

        $helper_list->listTotal = count($results);

        $helper_list->identifier = 'id_stripe_transaction';
        $helper_list->title = $this->l('Transactions');
        $helper_list->token = Tools::getAdminTokenLite('AdminModules');
        $helper_list->currentIndex = AdminController::$currentIndex.'&'.
            http_build_query(array(
                    'configure' => $this->name,
                    'menu' => self::MENU_TRANSACTIONS
                )
            );

        $helper_list->table = 'stripe_transaction';

        $helper_list->bulk_actions = false;

        return $helper_list->generateList($results, $fields_list);
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

            if (Tools::isSubmit('checktls') && (bool)Tools::getValue('checktls')) {
                $output .= $this->tlsCheck();
            }
        }
    }

    /**
     * Process General Options
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
     * Process Order Options
     */
    protected function postProcessOrderOptions()
    {
        $status_validated = Tools::getValue(self::STATUS_VALIDATED);
        $use_status_refund = Tools::getValue(self::USE_STATUS_REFUND);
        $status_refund = Tools::getValue(self::STATUS_REFUND);
        $use_status_partial_refund = Tools::getValue(self::USE_STATUS_PARTIAL_REFUND);
        $status_partial_refund = Tools::getValue(self::STATUS_PARTIAL_REFUND);
        $generate_credit_slip = (bool)Tools::getValue(self::GENERATE_CREDIT_SLIP);

        if (Configuration::get('PS_MULTISHOP_FEATURE_ACTIVE')) {
            if (Shop::getContext() == Shop::CONTEXT_ALL) {
                $this->updateAllValue(self::STATUS_VALIDATED, $status_validated);
                $this->updateAllValue(self::USE_STATUS_REFUND, $use_status_refund);
                $this->updateAllValue(self::STATUS_REFUND, $status_refund);
                $this->updateAllValue(self::STATUS_PARTIAL_REFUND, $status_partial_refund);
                $this->updateAllValue(self::USE_STATUS_PARTIAL_REFUND, $use_status_partial_refund);
                $this->updateAllValue(self::GENERATE_CREDIT_SLIP, $generate_credit_slip);
            } elseif (is_array(Tools::getValue('multishopOverrideOption'))) {
                $id_shop_group = (int)Shop::getGroupFromShop($this->getShopId(), true);
                $multishop_override = Tools::getValue('multishopOverrideOption');
                if (Shop::getContext() == Shop::CONTEXT_GROUP) {
                    foreach (Shop::getShops(false, $this->getShopId()) as $id_shop) {
                        if ($multishop_override[self::STATUS_VALIDATED]) {
                            Configuration::updateValue(self::STATUS_VALIDATED, $status_validated, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::USE_STATUS_REFUND]) {
                            Configuration::updateValue(self::USE_STATUS_REFUND, $use_status_refund, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::STATUS_REFUND]) {
                            Configuration::updateValue(self::STATUS_REFUND, $status_refund, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::USE_STATUS_PARTIAL_REFUND]) {
                            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $use_status_partial_refund, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::STATUS_PARTIAL_REFUND]) {
                            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $status_partial_refund, false, $id_shop_group, $id_shop);
                        }
                        if ($multishop_override[self::GENERATE_CREDIT_SLIP]) {
                            Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generate_credit_slip, false, $id_shop_group, $id_shop);
                        }
                    }
                } else {
                    $id_shop = (int)$this->getShopId();
                    if ($multishop_override[self::STATUS_VALIDATED]) {
                        Configuration::updateValue(self::STATUS_VALIDATED, $status_validated, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::USE_STATUS_REFUND]) {
                        Configuration::updateValue(self::USE_STATUS_REFUND, $use_status_refund, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::STATUS_REFUND]) {
                        Configuration::updateValue(self::STATUS_REFUND, $status_refund, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::USE_STATUS_PARTIAL_REFUND]) {
                        Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $use_status_partial_refund, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::STATUS_PARTIAL_REFUND]) {
                        Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $status_partial_refund, false, $id_shop_group, $id_shop);
                    }
                    if ($multishop_override[self::GENERATE_CREDIT_SLIP]) {
                        Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generate_credit_slip, false, $id_shop_group, $id_shop);
                    }
                }
            }
        } else {
            Configuration::updateValue(self::STATUS_VALIDATED, $status_validated);
            Configuration::updateValue(self::USE_STATUS_REFUND, $use_status_refund);
            Configuration::updateValue(self::STATUS_REFUND, $status_refund);
            Configuration::updateValue(self::USE_STATUS_PARTIAL_REFUND, $use_status_partial_refund);
            Configuration::updateValue(self::STATUS_PARTIAL_REFUND, $status_partial_refund);
            Configuration::updateValue(self::GENERATE_CREDIT_SLIP, $generate_credit_slip);
        }
    }

    /**
     *
     */
    protected function processRefund()
    {
        $id_order = (int)Tools::getValue('stripe_refund_order');
        $amount = (float)Tools::getValue('stripe_refund_amount');

        $sql = new DbQuery();
        $sql->select('st.`id_charge`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_order` = '.(int)$id_order);

        $result = Db::getInstance(_PS_USE_SQL_SLAVE_)->getRow($sql);

        $id_charge = '';
        if (isset($result['id_charge'])) {
            $id_charge = $result['id_charge'];
        }

        $order = new Order($id_order);
        $currency = new Currency($order->id_currency);

        if (!in_array(Tools::strtolower($currency->iso_code), self::$zero_decimal_currencies)) {
            $amount = (int)($amount * 100);
        }

        \Stripe\Stripe::setApiKey(Configuration::get(MDStripe::SECRET_KEY));
        \Stripe\Refund::create(array(
            'charge' => $id_charge,
            'amount' => $amount,
            'metadata' => array(
               'from_back_office' => true
            ),
        ));

        $sql = new DbQuery();
        $sql->select('st.`card_last_digits`');
        $sql->from('stripe_transaction', 'st');
        $sql->where('st.`id_charge` = \''.pSQL($id_charge).'\'');

        $last_digits = Db::getInstance(_PS_USE_SQL_SLAVE_)->getValue($sql);

        $transaction = new StripeTransaction();
        $transaction->card_last_digits = (int)$last_digits;
        $transaction->id_charge = $id_charge;
        $transaction->amount = $amount;
        $transaction->id_order = $order->id;
        $transaction->type = StripeTransaction::TYPE_FULL_REFUND;
        $transaction->source = StripeTransaction::SOURCE_WEBHOOK;
        $transaction->add();

        Tools::redirectAdmin($this->context->link->getAdminLink('AdminOrders', true).'&vieworder&id_order='.$id_order);
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

        $stripe_email = $cookie->email;

        /** @var Cart $cart */
        $cart = $params['cart'];
        $currency = new Currency($cart->id_currency);

        $link = $this->context->link;

        $stripe_amount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), self::$zero_decimal_currencies)) {
            $stripe_amount = (int)($stripe_amount * 100);
        }

        $this->context->smarty->assign(array(
            'stripe_email' => $stripe_email,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => $stripe_amount,
            'id_cart' => (int)$cart->id,
            'stripe_secret_key' => Configuration::get(self::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(self::PUBLISHABLE_KEY),
            'stripe_locale' => self::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => (bool)Configuration::get(self::ZIPCODE),
            'stripe_bitcoin' => (bool)Configuration::get(self::BITCOIN) && Tools::strtolower($currency->iso_code) === 'usd',
            'stripe_alipay' => (bool)Configuration::get(self::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'validation'),
        ));

        if (Module::isEnabled('onepagecheckoutps')) {
            return $this->context->smarty->fetch($this->local_path.'views/templates/front/eupayment.tpl');
        }

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
     * Hook to the new PS 1.7 payment options hook
     *
     * @param $params Hook parameters
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

        /** @var Cookie $email */
        $cookie = $params['cookie'];
        $stripe_email = $cookie->email;

        /** @var Cart $cart */
        $cart = $params['cart'];
        $currency = new Currency($cart->id_currency);

        $link = $this->context->link;

        $stripe_amount = $cart->getOrderTotal();
        if (!in_array(Tools::strtolower($currency->iso_code), self::$zero_decimal_currencies)) {
            $stripe_amount = (int)($stripe_amount * 100);
        }

        $this->context->smarty->assign(array(
            'stripe_email' => $stripe_email,
            'stripe_currency' => $currency->iso_code,
            'stripe_amount' => $stripe_amount,
            'id_cart' => (int)$cart->id,
            'stripe_secret_key' => Configuration::get(self::SECRET_KEY),
            'stripe_publishable_key' => Configuration::get(self::PUBLISHABLE_KEY),
            'stripe_locale' => self::getStripeLanguage($this->context->language->language_code),
            'stripe_zipcode' => (bool)Configuration::get(self::ZIPCODE),
            'stripe_bitcoin' => (bool)Configuration::get(self::BITCOIN) && Tools::strtolower($currency->iso_code) === 'usd',
            'stripe_alipay' => (bool)Configuration::get(self::ALIPAY),
            'stripe_shopname' => $this->context->shop->name,
            'stripe_confirmation_page' => $link->getModuleLink($this->name, 'validation'),
        ));

        $externalOption = new PaymentOption();
        $externalOption->setCallToActionText($this->l('Pay with Stripe'))
            ->setAction($this->context->link->getModuleLink($this->name, 'validation', array(), true))
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
        $this->context->controller->addCSS($this->local_path.'/views/css/front.css');

        return '';
    }

    /**
     * Hook to header: <head></head>
     *
     * @param $params Hook parameters
     */
    public function hookHeader($params)
    {
        if (Tools::getValue('module') === 'onepagecheckoutps' ||
            Tools::getValue('controller') === 'order-opc' ||
            Tools::getValue('controller') === 'orderopc' ||
            Tools::getValue('controller') === 'order') {
            $this->context->controller->addJS('https://checkout.stripe.com/checkout.js');
        }
    }

    public function hookDisplayAdminOrder($params)
    {
        if (StripeTransaction::getTransactionsByOrderId($params['id_order'], true)) {
            $this->context->controller->addJS('https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.js');
            $this->context->controller->addCSS('https://cdnjs.cloudflare.com/ajax/libs/sweetalert/1.1.3/sweetalert.min.css');

            $order = new Order($params['id_order']);
            $order_currency = new Currency($order->id_currency);

            $total_refund_left = $order->getTotalPaid();
            if (!in_array(Tools::strtolower($order_currency->iso_code), MDStripe::$zero_decimal_currencies)) {
                $total_refund_left = (int)(Tools::ps_round($total_refund_left * 100, 0));
            }

            $amount = (int)StripeTransaction::getRefundedAmountByOrderId($order->id);

            $total_refund_left -= $amount;

            if (!in_array(Tools::strtolower($order_currency->iso_code), MDStripe::$zero_decimal_currencies)) {
                $total_refund_left = (float)($total_refund_left / 100);
            }
            
            $this->context->smarty->assign(array(
                'stripe_transaction_list' => $this->renderAdminOrderTransactionList($params['id_order']),
                'stripe_currency_symbol' => $order_currency->sign,
                'stripe_total_amount' => $total_refund_left,
                'stripe_module_refund_action' => $this->context->link->getAdminLink('AdminModules', true).
                    '&configure=mdstripe&tab_module=payments_gateways&module_name=mdstripe&orderstriperefund',
                'id_order' => (int)$order->id,
            ));

            return $this->context->smarty->fetch($this->local_path.'views/templates/admin/adminorder.tpl');
        }

        return '';
    }

    protected function renderAdminOrderTransactionList($id_order)
    {
        $results = StripeTransaction::getTransactionsByOrderId($id_order);

        $order = new Order($id_order);
        $currency = new Currency($order->id_currency);

        if (!in_array(Tools::strtolower($currency->iso_code), MDStripe::$zero_decimal_currencies)) {
            foreach ($results as &$result) {
                // Process results
                $result['amount'] = (float)($result['amount'] / 100);
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

        $helper_list = new HelperList();

        $helper_list->list_id = 'stripe_transaction';
        $helper_list->shopLinkType = false;

        $helper_list->no_link = true;

        $helper_list->_defaultOrderBy = 'date_add';

        $helper_list->simple_header = true;

        $helper_list->module = $this;

        $fields_list = array(
            'id_stripe_transaction' => array('title' => $this->l('ID'), 'width' => 'auto'),
            'type_icon' => array('type' => 'type_icon', 'title' => $this->l('Type'), 'width' => 'auto', 'color' => 'color', 'text' => 'type_text'),
            'amount' => array('type' => 'price', 'title' => $this->l('Amount'), 'width' => 'auto'),
            'card_last_digits' => array('type' => 'text', 'title' => $this->l('Credit card (last 4 digits)'), 'width' => 'auto'),
            'source_text' => array('type' => 'stripe_source', 'title' => $this->l('Source'), 'width' => 'auto'),
            'date_upd' => array('type' => 'datetime', 'title' => $this->l('Date & time'), 'width' => 'auto'),
        );

        $helper_list->identifier = 'id_stripe_transaction';
        $helper_list->title = $this->l('Transactions');
        $helper_list->token = Tools::getAdminTokenLite('AdminOrders');
        $helper_list->currentIndex = AdminController::$currentIndex.'&'.
            http_build_query(array(
                    'id_order' => $id_order,
                )
            );

        // Hide actions
        $helper_list->tpl_vars['show_filters'] = false;
        $helper_list->actions = true;
        $helper_list->bulk_actions = false;

        $helper_list->table = 'stripe_transaction';

        return $helper_list->generateList($results, $fields_list);
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

    /**
     * Check if TLS 1.2 is supported
     */
    protected function tlsCheck()
    {
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => 'https://tlstest.paypal.com/'
        ));
        $result = curl_exec($curl);

        if ($result == 'PayPal_Connection_OK') {
            $this->updateAllValue(self::TLS_OK, self::ENUM_TLS_OK);
        } else {
            $this->updateAllValue(self::TLS_OK, self::ENUM_TLS_ERROR);
        }
    }

    protected function getSelectedPagination($list_id, $default_pagination = 50)
    {
        $selected_pagination = Tools::getValue($list_id.'_pagination',
            isset($this->context->cookie->{$list_id.'_pagination'}) ? $this->context->cookie->{$list_id.'_pagination'} : $default_pagination
        );

        return $selected_pagination;
    }

    protected function getSelectedPage($list_id, $list_total)
    {
        /* Determine current page number */
        $page = (int)Tools::getValue('submitFilter'.$list_id);

        if (!$page) {
            $page = 1;
        }

        $total_pages = max(1, ceil($list_total / $this->getSelectedPagination($list_id)));

        if ($page > $total_pages) {
            $page = $total_pages;
        }

        $this->page = (int)$page;

        return $page;
    }

    protected function getSQLFilter($helper_list, $fields_list)
    {
        /** @var HelperList $helper_list */
        if (!isset($helper_list->list_id)) {
            $helper_list->list_id = $helper_list->table;
        }

        $prefix = '';
        $sql_filter = '';

        if (isset($helper_list->list_id)) {
            foreach ($_POST as $key => $value) {
                if ($value === '') {
                    unset($helper_list->context->cookie->{$prefix.$key});
                } elseif (stripos($key, $helper_list->list_id.'Filter_') === 0) {
                    $helper_list->context->cookie->{$prefix.$key} = !is_array($value) ? $value : serialize($value);
                } elseif (stripos($key, 'submitFilter') === 0) {
                    $helper_list->context->cookie->$key = !is_array($value) ? $value : serialize($value);
                }
            }

            foreach ($_GET as $key => $value) {
                if (stripos($key, $helper_list->list_id.'Filter_') === 0) {
                    $helper_list->context->cookie->{$prefix.$key} = !is_array($value) ? $value : serialize($value);
                } elseif (stripos($key, 'submitFilter') === 0) {
                    $helper_list->context->cookie->$key = !is_array($value) ? $value : serialize($value);
                }
                if (stripos($key, $helper_list->list_id.'Orderby') === 0 && Validate::isOrderBy($value)) {
                    if ($value === '' || $value == $helper_list->_defaultOrderBy) {
                        unset($helper_list->context->cookie->{$prefix.$key});
                    } else {
                        $helper_list->context->cookie->{$prefix.$key} = $value;
                    }
                } elseif (stripos($key, $helper_list->list_id.'Orderway') === 0 && Validate::isOrderWay($value)) {
                    if ($value === '' || $value == $helper_list->_defaultOrderWay) {
                        unset($helper_list->context->cookie->{$prefix.$key});
                    } else {
                        $helper_list->context->cookie->{$prefix.$key} = $value;
                    }
                }
            }
        }

        $filters = $helper_list->context->cookie->getFamily($prefix.$helper_list->list_id.'Filter_');
        $definition = false;
        if (isset($helper_list->className) && $helper_list->className) {
            $definition = ObjectModel::getDefinition($helper_list->className);
        }

        foreach ($filters as $key => $value) {
            /* Extracting filters from $_POST on key filter_ */
            if ($value != null && !strncmp($key, $prefix.$helper_list->list_id.'Filter_', 7 + Tools::strlen($prefix.$helper_list->list_id))) {
                $key = Tools::substr($key, 7 + Tools::strlen($prefix.$helper_list->list_id));
                /* Table alias could be specified using a ! eg. alias!field */
                $tmp_tab = explode('!', $key);
                $filter = count($tmp_tab) > 1 ? $tmp_tab[1] : $tmp_tab[0];

                if ($field = $this->filterToField($fields_list, $key, $filter)) {
                    $type = (array_key_exists('filter_type', $field) ? $field['filter_type'] : (array_key_exists('type', $field) ? $field['type'] : false));
                    if (($type == 'date' || $type == 'datetime') && is_string($value)) {
                        $value = Tools::unSerialize($value);
                    }
                    $key = isset($tmp_tab[1]) ? $tmp_tab[0].'.`'.$tmp_tab[1].'`' : '`'.$tmp_tab[0].'`';
                    $sql_filter = & $helper_list->_filter;

                    /* Only for date filtering (from, to) */
                    if (is_array($value)) {
                        if (isset($value[0]) && !empty($value[0])) {
                            if (!Validate::isDate($value[0])) {
                                return $this->displayError('The \'From\' date format is invalid (YYYY-MM-DD)');
                            } else {
                                $sql_filter .= ' AND '.pSQL($key).' >= \''.pSQL(Tools::dateFrom($value[0])).'\'';
                            }
                        }

                        if (isset($value[1]) && !empty($value[1])) {
                            if (!Validate::isDate($value[1])) {
                                return $this->displayError('The \'To\' date format is invalid (YYYY-MM-DD)');
                            } else {
                                $sql_filter .= ' AND '.pSQL($key).' <= \''.pSQL(Tools::dateTo($value[1])).'\'';
                            }
                        }
                    } else {
                        $sql_filter .= ' AND ';
                        $check_key = ($key == $helper_list->identifier || $key == '`'.$helper_list->identifier.'`');
                        $alias = ($definition && !empty($definition['fields'][$filter]['shop'])) ? 'sa' : 'a';

                        if ($type == 'int' || $type == 'bool') {
                            $sql_filter .= (($check_key || $key == '`active`') ?  $alias.'.' : '').pSQL($key).' = '.(int)$value.' ';
                        } elseif ($type == 'decimal') {
                            $sql_filter .= ($check_key ?  $alias.'.' : '').pSQL($key).' = '.(float)$value.' ';
                        } elseif ($type == 'select') {
                            $sql_filter .= ($check_key ?  $alias.'.' : '').pSQL($key).' = \''.pSQL($value).'\' ';
                        } elseif ($type == 'price') {
                            $value = (float)str_replace(',', '.', $value);
                            $sql_filter .= ($check_key ?  $alias.'.' : '').pSQL($key).' = '.pSQL(trim($value)).' ';
                        } else {
                            $sql_filter .= ($check_key ?  $alias.'.' : '').pSQL($key).' LIKE \'%'.pSQL(trim($value)).'%\' ';
                        }
                    }
                }
            }
        }

        return $sql_filter;
    }

    protected function filterToField($fields_list, $key, $filter)
    {
        foreach ($fields_list as $field) {
            if (array_key_exists('filter_key', $field) && $field['filter_key'] == $key) {
                return $field;
            }
        }
        if (array_key_exists($filter, $fields_list)) {
            return $fields_list[$filter];
        }
        return false;
    }

    protected function getCurrencyIdByOrderId($id_order)
    {
        $order = new Order($id_order);
        if (Validate::isLoadedObject($order)) {
            $currency = new Currency($order->id_currency);
        } else {
            $currency = new Currency((int)Configuration::get('PS_CURRENCY_DEFAULT'));
        }

        return $currency;
    }
}
