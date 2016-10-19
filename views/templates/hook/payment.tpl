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
<!-- mdstripe views/templates/hook/payment.tpl -->
{if $smarty.const._PS_VERSION_|@addcslashes:'\'' < '1.6'}
	<form id="stripe-form" action="{$stripe_confirmation_page|escape:'htmlall':'UTF-8'}" method="POST">
		<input type="hidden" name="mdstripe-id_cart" value="{$id_cart|escape:'htmlall':'UTF-8'}">
	</form>
	<p class="payment_module" id="mdstripe_payment_button">
		<a id="mdstripe_payment_link" href="#" title="{l s='Pay with Stripe' mod='mdstripe'}">
			<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/stripebtnlogo15.png" alt="{l s='Pay with Stripe' mod='mdstripe'}" width="108" height="46" />
			{l s='Pay with Stripe' mod='mdstripe'}
			{if $showPaymentLogos}
				<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/creditcards.png" alt="{l s='Credit cards' mod='mdstripe'}" />
				{if $stripe_alipay}<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/alipay.png" alt="{l s='Alipay' mod='mdstripe'}" />{/if}
				{if $stripe_bitcoin}<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/bitcoin.png" alt="{l s='Bitcoin' mod='mdstripe'}" />{/if}
			{/if}
		</a>
	</p>
{else}
	<div class="row">
		<form id="stripe-form" action="{$stripe_confirmation_page|escape:'htmlall':'UTF-8'}" method="POST">
			<input type="hidden" name="mdstripe-id_cart" value="{$id_cart|escape:'htmlall':'UTF-8'}">
		</form>
		<div class="col-xs-12 col-md-12">
			<p class="payment_module" id="mdstripe_payment_button">
				<a id="mdstripe_payment_link" href="#" title="{l s='Pay with Stripe' mod='mdstripe'}">
					<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/stripebtnlogo.png" alt="{l s='Pay with Stripe' mod='mdstripe'}" width="64" height="64" />
					{l s='Pay with Stripe' mod='mdstripe'}
					{if $showPaymentLogos}
						<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/creditcards.png" alt="{l s='Credit cards' mod='mdstripe'}" />
						{if $stripe_alipay}<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/alipay.png" alt="{l s='Alipay' mod='mdstripe'}" />{/if}
						{if $stripe_bitcoin}<img src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/bitcoin.png" alt="{l s='Bitcoin' mod='mdstripe'}" />{/if}
					{/if}
				</a>
			</p>
		</div>
	</div>
{/if}

<script type="text/javascript">
	var handler = StripeCheckout.configure({
		key: '{$stripe_publishable_key|escape:'javascript':'UTF-8'}',
		image: '{$stripeShopThumb|escape:'javascript':'UTF-8'}',
		locale: 'auto',
		token: function (token) {
			var $form = $('#stripe-form');
			{* Insert the token into the form so it gets submitted to the server: *}
			$form.append($('<input type="hidden" name="mdstripe-token" />').val(token.id));

			{* Submit the form: *}
			$form.get(0).submit();
		}
	});

	$('#mdstripe_payment_link').on('click', function(e) {
		{* Open Checkout with further options: *}
		handler.open({
			name: '{$stripe_shopname|escape:'javascript':'UTF-8'}',
			zipCode: {if $stripe_zipcode}true{else}false{/if},
			bitcoin: {if $stripe_bitcoin}true{else}false{/if},
			alipay: {if $stripe_alipay}true{else}false{/if},
			currency: '{$stripe_currency|escape:'javascript':'UTF-8'}',
			amount: '{$stripe_amount|escape:'javascript':'UTF-8'}',
			email: '{$stripe_email|escape:'javascript':'UTf-8'}',
			billingAddress: {if $stripe_collect_billing}true{else}false{/if},
			shippingAddress: {if $stripe_collect_shipping}true{else}false{/if}
		});
		e.preventDefault();
	});
</script>
<style>
	#stripe-apple-pay-method {
		display: none;
	}

	#stripe-apple-pay-logo {
		background-color: black !important;
		background-image: url('{$module_dir|escape:'htmlall':'UTF-8'}views/img/apple_pay_logo_black.png');
		background-position: center;
		background-size: auto 44px;
		background-origin: content-box;
		background-repeat: no-repeat;
		width: 140px;
		height: 44px;
		padding: 0;
		border-radius: 10px;
		border: none;
	}
</style>
<div class="row" id="stripe-apple-pay-method">
	<div class="col-xs-12">
		<p id="mdstripe_apple_payment_button" class="payment_module">
			<a id="stripe-apple-pay-button" href="#" class="stripeapplepay" title="Pay with Apple Pay">
				<img id="stripe-apple-pay-logo" src="{$module_dir|escape:'htmlall':'UTF-8'}/views/img/apple_pay_logo_black.png" alt="{l s='Pay with Apple Pay' mod='mdstripe'}" />
				Pay with Apple Pay
			</a>
		</p>
	</div>
</div>
<script type="text/javascript">
	$(document).ready(function () {
		Stripe.setPublishableKey('{$stripe_publishable_key|escape:'javascript':'UTF-8'}');

		Stripe.applePay.checkAvailability(function (available) {
			if (available) {
				$('#stripe-apple-pay-method').show();
				$('#stripe-apple-pay-button').on('click', function () {
					var paymentRequest = {
						countryCode: '{$stripe_country|escape:'javascript':'UTF-8'}',
						currencyCode: '{$stripe_currency|escape:'javascript':'UTF-8'}',
						total: {
							label: '{$stripe_shopname|escape:'javascript':'UTF-8'}',
							amount: '{$stripe_amount_string|escape:'javascript':'UTF-8'}'
						}
					};

					var session = Stripe.applePay.buildSession(paymentRequest,
							function(result, completion) {

								$.post('{$stripe_ajax_validation|escape:'javascript':'UTF-8'}', {
									'mdstripe-token': result.token.id,
									'mdstripe-id_cart': '{$id_cart|escape:'javascript':'UTF-8'}',
								}).done(function(result) {
									completion(ApplePaySession.STATUS_SUCCESS);
									{* You can now redirect the user to a receipt page, etc. *}
									window.location.href = '{$stripe_ajax_confirmation_page|escape:'javascript':'UTF-8'}' + '&id_order=' + result.idOrder;
								}).fail(function() {
									completion(ApplePaySession.STATUS_FAILURE);
								});

							}, function(error) {
								console.log(error.message);
							});

					session.begin();
				});
			}
		});
	});
</script>
<!-- /mdstripe views/templates/hook/payment.tpl -->
