{*
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
*}

<div class="row">
	<form id="stripe-form" action="{$stripe_confirmation_page|escape:'htmlall':'UTF-8'}" method="POST">
		<input type="hidden" name="id_cart" value="{$id_cart|escape:'htmlall':'UTF-8'}">
	</form>
	<div class="col-xs-12 col-md-12">
		<p class="payment_module" id="mdstripe_payment_button">
			{if $cart->getOrderTotal() < 2}
				<a href="">
					<img src="{$domain|cat:$payment_button|escape:'html':'UTF-8'}" alt="{l s='Pay with Stripe' mod='mdstripe'}" />
					{l s='Minimum amount required in order to pay with my payment module:' mod='mdstripe'} {convertPrice price=2}
				</a>
			{else}
				<a id="mdstripe_payment_link" href="#" title="{l s='Pay with Stripe' mod='mdstripe'}">
					<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/stripebtnlogo.png" alt="{l s='Pay with Stripe' mod='mdstripe'}" width="64" height="64" />
					{l s='Pay with Stripe' mod='mdstripe'}
				</a>
			{/if}
		</p>
	</div>
	<script type="text/javascript">
		var handler = StripeCheckout.configure({
			key: 'pk_test_g4xEGpWUVb8DZSdophAK4jcD',
			image: '/img/logo.jpg',
			locale: 'auto',
			token: function (token) {
				var $form = $('#stripe-form');
				// Insert the token into the form so it gets submitted to the server:
				$form.append($('<input type="hidden" name="stripe-token" />').val(token.id));

				// Submit the form:
				$form.get(0).submit();
			}
		});

		$('#mdstripe_payment_link').on('click', function(e) {
			// Open Checkout with further options:
			handler.open({
				name: '{$shopname|escape:'javascript':'UTF-8'}',
				zipCode: true,
				bitcoin: false,
				alipay: false,
				currency: '{$stripe_currency|escape:'javascript':'UTF-8'}',
				amount: '{$stripe_amount|escape:'javascript':'UTF-8'}',
				email: '{$stripe_email|escape:'javascript':'UTf-8'}'
			});
			e.preventDefault();
		});
	</script>
</div>
