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

<div>
	<h3>{l s='Redirect your customer' mod='mdstripe'}:</h3>
	<ul class="alert alert-info">
			<li>{l s='This action should be used to redirect your customer to the website of your payment processor' mod='mdstripe'}.</li>
	</ul>
	
	<div class="alert alert-warning">
		{l s='You can redirect your customer with an error message' mod='mdstripe'}:
		<a href="{$link->getModuleLink('mdstripe', 'redirect', ['action' => 'error'], true)|escape:'htmlall':'UTF-8'}" title="{l s='Look at the error' mod='mdstripe'}">
			<strong>{l s='Look at the error message' mod='mdstripe'}</strong>
		</a>
	</div>
	
	<div class="alert alert-success">
		{l s='You can also redirect your customer to the confirmation page' mod='mdstripe'}:
		<a href="{$link->getModuleLink('mdstripe', 'confirmation', ['cart_id' => $cart_id, 'secure_key' => $secure_key], true)|escape:'htmlall':'UTF-8'}" title="{l s='Confirm' mod='mdstripe'}">
			<strong>{l s='Go to the confirmation page' mod='mdstripe'}</strong>
		</a>
	</div>
</div>
