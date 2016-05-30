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
<div class="panel">
	<h3><i class="icon icon-puzzle-piece"></i> {l s='Stripe BETA' mod='mdstripe'}</h3>
	<p>
		<strong>{l s='Accept payments with Stripe' mod='mdstripe'}</strong><br />
		{l s='Thank you for testing this module in the BETA phase.' mod='mdstripe'}
		{l s='This section will contain some basic info about the module.' mod='mdstripe'}<br />
		{l s='Keep an eye out for updates on this page:' mod='mdstripe'}
		<a href="https://github.com/firstred/mdstripe/releases">https://github.com/firstred/mdstripe/releases</a>
	</p>
</div>

<div class="panel">
	<h3><i class="icon icon-anchor"></i> {l s='Webhooks' mod='mdstripe'}</h3>
	<p>{l s='This module supports procesing refunds through webhooks' mod='mdstripe'}</p>
	<p>{l s='You can use the following URL:' mod='mdstripe'}<br/>
		<a href="{$stripe_webhook_url|escape:'htmlall':'UTF-8'}">{$stripe_webhook_url|escape:'htmlall':'UTF-8'}</a>
	</p>
</div>