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

function upgrade_module_1_1_0($module)
{
    /** @var MdStripe $module */
    foreach ($module->hooks as $hook) {
        $module->registerHook($hook);
    }

    $hookHeaderExceptions = array(
        'address',
        'addresses',
        'attachment',
        'auth',
        'bestsales',
        'cart',
        'category',
        'changecurrency',
        'cms',
        'compare',
        'contact',
        'discount',
        'getfile',
        'guesttracking',
        'history',
        'identity',
        'index',
        'manufacturer',
        'myaccount',
        'newproducts',
        'pagenotfound',
        'parentorder',
        'password',
        'pdfinvoice',
        'pdforderreturn',
        'pdforderslip',
        'pricesdrop',
        'product',
        'search',
        'sitemap',
        'statistics',
        'stores',
        'supplier',
        'module-'.$module->name.'-eupayment',
    );

    $allModuleControllers = array();
    $modulesWithControllers = Dispatcher::getModuleControllers('front');

    foreach ($modulesWithControllers as $module => $moduleControllers) {
        foreach ($moduleControllers as $cont) {
            $allModuleControllers[] = 'module-'.$module.'-'.$cont;
        }
    }

    $hookHeaderExceptions = array_merge($hookHeaderExceptions, $allModuleControllers);

    $hookHeaderId = (int) Hook::getIdByName('displayHeader');

    if ($hookHeaderId) {
        foreach (Shop::getShops() as $shop) {
            Db::getInstance()->delete(
                'hook_module_exceptions',
                '`id_module` = '.(int) $module->id.' AND `id_hook` = '.(int) $hookHeaderId.' AND `id_shop` = '.(int) $shop['id_shop']
            );

            foreach ($hookHeaderExceptions as $exception) {
                Db::getInstance()->insert(
                    'hook_module_exceptions',
                    array(
                        'id_module' => (int) $module->id,
                        'id_hook' => (int) $hookHeaderId,
                        'id_shop' => (int) $shop['id_shop'],
                        'file_name' => pSQL($exception),
                    ),
                    false,
                    true,
                    Db::INSERT_IGNORE
                );
            }
        }
    }

    return true;
}
