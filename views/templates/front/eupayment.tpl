<div class="row">
	<form id="stripe-form" action="{$stripe_confirmation_page|escape:'htmlall':'UTF-8'}" method="POST">
		<input type="hidden" name="id_cart" value="{$id_cart|escape:'htmlall':'UTF-8'}">
	</form>
	<a id="mdstripe_payment_link" href="#" title="{l s='Pay with Stripe' mod='mdstripe'}" class="btn btn-default">
		{l s='Pay with Stripe' mod='mdstripe'}
	</a>
	<script type="text/javascript">
		function openStripeHandler(e) {
			// Open Checkout with further options:
			handler.open({
				name: '{$stripe_shopname|escape:'javascript':'UTF-8'}',
				zipCode: {if $stripe_zipcode}true{else}false{/if},
				bitcoin: {if $stripe_bitcoin}true{else}false{/if},
				alipay: {if $stripe_alipay}true{else}false{/if},
				currency: '{$stripe_currency|escape:'javascript':'UTF-8'}',
				amount: '{$stripe_amount|escape:'javascript':'UTF-8'}',
				email: '{$stripe_email|escape:'javascript':'UTf-8'}',
			});
			if (typeof e !== 'undefined' && typeof e !== 'function') {
				e.preventDefault();
			}
		}

		var handler = StripeCheckout.configure({
			key: '{$stripe_publishable_key|escape:'javascript':'UTF-8'}',
			image: '/img/logo.jpg',
			locale: '{$stripe_locale|escape:'javascript':'UTF-8'}',
			token: function (token) {
				var $form = $('#stripe-form');
				// Insert the token into the form so it gets submitted to the server:
				$form.append($('<input type="hidden" name="stripe-token" />').val(token.id));

				// Submit the form:
				$form.get(0).submit();
			}
		});

		$(document).ready(openStripeHandler);
		$('#mdstripe_payment_link').click(openStripeHandler);
	</script>
</div>