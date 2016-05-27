<div class="row">
	<form id="stripe-payment-form" action="{$stripe_confirmation_page|escape:'htmlall':'UTF-8'}" method="POST">
		<input type="hidden" name="id_cart" value="{$id_cart|escape:'htmlall':'UTF-8'}">
	</form>
	<a id="mdstripe_payment_link" href="#" title="{l s='Pay with Stripe' mod='mdstripe'}" class="btn btn-default">
		{l s='Pay with Stripe' mod='mdstripe'}
	</a>
	<script type="text/javascript">
		var handler = StripeCheckout.configure({
			key: 'pk_test_g4xEGpWUVb8DZSdophAK4jcD',
			image: '/img/logo.jpg',
			locale: 'auto',
			token: function (token) {
				var $form = $('#stripe-payment-form');
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