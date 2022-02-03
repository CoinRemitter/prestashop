<form action="{$action}" id="b2binpay-payment-form" method="POST" class="mt-1 ml-3">
   {if isset($wallets) && count($wallets)}
		<label class="mt-1">Select Cryptocurrency</label>
		<select class="form-control form-control-select" name="coinremitter_select_coin"> 
			{foreach from=$wallets item=wallet}
				<option>{{$wallet['coin']}}</option>
			{/foreach}
		</select>
		<div class="mt-1 ml-3">{$description}</div>
	{else}
	   <span>{$message}</span>
	{/if}
</form>

