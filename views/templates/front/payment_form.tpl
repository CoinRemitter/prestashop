<form action="{$action}" id="b2binpay-payment-form" method="POST" class="mt-1 ml-3">
	<label class="mt-1">Select Cryptocurrency</label>
	<select class="form-control form-control-select" name="coinremitter_select_coin">
      	{if isset($wallets) && count($wallets)} 
		 	{foreach from=$wallets item=wallet}
		   		<option>{{$wallet['coin']}}</option>
			{/foreach}
		{else}
	      <option>No coin wallet setup.</option>
	    {/if}
   	</select>
</form>
<div class="mt-1 ml-3">{$description}</div>
