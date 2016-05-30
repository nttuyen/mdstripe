{capture name='stripe_yes'}{l s='Yes' mod='mdstripe'}{/capture}
{capture name='stripe_no'}{l s='No' mod='mdstripe'}{/capture}
{capture name='stripe_sure'}{l s='Are you sure?' mod='mdstripe'}{/capture}
{capture name='stripe_go_refund'}{l s='Do you want to refund this order?' mod='mdstripe'}{/capture}

<script type="text/javascript">
	var stripe_total_amount = '{$stripe_total_amount|escape:'javascript':'UTF-8'}';

	$(document).ready(function() {
		$('#stripe_partial_refund_button').click(function () {
			stripeConfirmation();
		});

		$('#stripe_full_refund_button').click(function () {
			$('#stripe_refund_amount').val(stripe_total_amount);
			stripeConfirmation();
		});

		function stripeConfirmation() {
			swal({
				title: '{$smarty.capture.stripe_sure|escape:'javascript':'UTF-8'}',
				text: '{$smarty.capture.stripe_go_refund|escape:'javascript':'UTF-8'}',
				type: 'warning',
				showCancelButton: true,
				confirmButtonColor: '#DD6B55',
				confirmButtonText: '{$smarty.capture.stripe_yes|escape:'javascript':'UTF-8'}',
				cancelButtonText: '{$smarty.capture.stripe_no|escape:'javascript':'UTF-8'}',
				closeOnConfirm: false
			}, function () {
				$form = $('#stripe_refund');
				$form.get(0).submit();
			});
		}
	});
</script>

<div class="panel">
	<div class="panel-heading">
		<i class="icon icon-credit-card"></i> <span>{l s='Stripe' mod='mdstripe'}</span>
	</div>
	{$stripe_transaction_list}
	<br />
	<div class="row-margin-bottom row-margin-top order_action clearfix">
		<div class="col-md-1 col-sm-3 col-xs-4">
			<button id="stripe_full_refund_button" type="button" class="btn btn-default"><i class="icon icon-undo"></i> {l s='Full refund' mod='mdstripe'}</button>
		</div>
		<form id="stripe_refund" action="{$stripe_module_refund_action|escape:'htmlall':'UTF-8'}&id_order={$id_order|escape:'htmlall':'UTF-8'}" method="post">
			<input type="hidden" id="stripe_refund_order" name="stripe_refund_order" value="{$id_order|escape:'htmlall':'UTF-8'}">
			<div class="input-group col-md-2 col-sm-9 col-xs-8">
				<span class="input-group-addon">
					{$stripe_currency_symbol}
				</span>
				<input type="text" id="stripe_refund_amount" name="stripe_refund_amount" class="form-control" placeholder="{l s='Remaining: ' mod='mdstripe'} {$stripe_total_amount|escape:'htmlall':'UTF-8'}">
				<span class="input-group-btn">
					<button id="stripe_partial_refund_button" class="btn btn-default" type="button"><i class="icon icon-undo"></i> {l s='Partial Refund' mod='mdstripe'}</button>
				</span>
			</div>
		</form>
	</div>
</div>