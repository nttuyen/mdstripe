{*
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
*}
{if (isset($status) == true) && ($status == 'ok')}
	<h3>{l s='Your order on %s is complete.' sprintf=[$shop_name] mod='mdstripe'}</h3>
	<p>
		<br />- {l s='Amount' mod='mdstripe'} : <span class="price"><strong>{$total|escape:'htmlall':'UTF-8'}</strong></span>
		<br />- {l s='Reference' mod='mdstripe'} : <span class="reference"><strong>{$reference|escape:'html':'UTF-8'}</strong></span>
		<br /><br />{l s='An email has been sent with this information.' mod='mdstripe'}
		<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='mdstripe'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='mdstripe'}</a>
	</p>
{else}
	<h3>{l s='Your order on %s has not been accepted.' sprintf=[$shop_name] mod='mdstripe'}</h3>
	<p>
	<p>
		<br />- {l s='Reference' mod='mdstripe'} <span class="reference"> <strong>{$reference|escape:'html':'UTF-8'}</strong></span>
		<br /><br />{l s='Please, try to order again.' mod='mdstripe'}
		<br /><br />{l s='If you have questions, comments or concerns, please contact our' mod='mdstripe'} <a href="{$link->getPageLink('contact', true)|escape:'html':'UTF-8'}">{l s='expert customer support team.' mod='mdstripe'}</a>
	</p>
{/if}
<hr />
