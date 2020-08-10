{if $success_msg neq '' }
	{$success_msg}
	  
{/if}

<div class="panel">
	<div class="panel-heading">
		{l s='Walles list' mod='wallets'}
		<span class="panel-heading-action">
			<a id="desc-order-new" class="list-toolbar-btn" href="index.php?controller=CoinremitterWallets&action=create&token={Tools::getValue('token')}">
				<span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Add new wallet" data-html="true" data-placement="top">
					<i class="process-icon-new"></i>
				</span>
			</a>
		</span>
	</div>
	<div class="panel-body">
		<table class="table grid-table">
		<thead>
			<th>{l s="ID" mod='quickupdate'}</th>
			<th>{l s="Logo" mod='quickupdate'}</th>
			<th>{l s="Coin" mod='quickupdate'}</th>
			<th>{l s="Coin Name" mod='quickupdate'}</th>
			<th>{l s="Wallte Name" mod='quickupdate'}</th>
			<th>{l s="Balance" mod='quickupdate'}</th>
			<th>{l s="Created" mod='quickupdate'}</th>
			<th class="text-right">Action</th>
		</thead>
		<tbody>
		{if isset($wallets) && count($wallets)}	
		{$index=1}	
		{foreach from=$wallets item=wallet}
				{$logo=strtolower($wallet['coin'])}
			<tr>
				<td class="pointer">{$index}</td>
				<td class="pointer"><img src="{$img_path}{$logo}.png" class="img-thumbnail" width="50" /></td>
				<td class="pointer">{$wallet['coin']}</td>

				<td class="pointer">{$wallet['coin_name']}</td>
				<td class="pointer">{$wallet['name']}</td>
				<td class="pointer">{$wallet['balance']}</td>
				<td class="pointer">{$wallet['date_added']}</td>
				<td>
					<div class="btn-group-action">				
						<div class="btn-group pull-right">
						<a href="index.php?controller=CoinremitterWallets&id={$wallet['id']}&action=edite&token={Tools::getValue('token')}" title="Edit" class="edit btn btn-default">
							<i class="icon-pencil"></i> Edit
						</a>
						<button class="btn btn-default dropdown-toggle" data-toggle="dropdown">
							<i class="icon-caret-down"></i>&nbsp;
						</button>
						<ul class="dropdown-menu">
							<li>
								<a href="index.php?controller=CoinremitterWallets&id={$wallet['id']}&action=delete&token={Tools::getValue('token')}" title="Delete" class="delete">
									<i class="icon-trash"></i> Delete
								</a>
							</li>
						</ul>
						</div>
					</div>
				</td>
			</tr>
			{assign var=index value=$index+1} 
		{/foreach}
		{else} 
			<tr>
				<td class="list-empty" colspan="7">
					<div class="list-empty-msg">
						<i class="icon-warning-sign list-empty-icon"></i>
						No records found
					</div>
				</td>
			</tr>
		{/if}	
		</tbody>
		</table>
	</div>
</div>