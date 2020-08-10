<div class="box">
   <h3>{l s=' Payment Detail (Coinremitter) ' d='Shop.Theme.Customeraccount'}</h3>
  <table class="table table-bordered">
    <thead class="thead-default">
      <tr>
        <th>{l s='Invoice Id' d='Shop.Theme.Global'}</th>
        <th>{l s='Coin' d='Shop.Theme.Global'}</th>
        <th>{l s='Amount' d='Shop.Theme.Checkout'}</th>
        <th>{l s='Pending Amount' d='Shop.Theme.Checkout'}</th>
        <th>{l s='Payment Status' d='Shop.Theme.Checkout'}</th>
        <th>{l s='Date ' d='Shop.Theme.Checkout'}</th>
        </tr>
    </thead>
    <tbody>
        <tr>
          <td>{$invoice_id}  -  <a href="{$url}">view</a></td>
          <td>{$coin}</td>
          <td>{$total_amount} {$coin}</td>
          <td>{$panding_amount} {$coin}</td>
          <td>{$status}</td>
          <td>{$date}</td>
        </tr>
    </tbody>
  </table>
    <table class="table table-bordered">
    <thead class="thead-default">
      <tr>
        <th>{l s='Transaction Ids' d='Shop.Theme.Global'}</th>
      </tr>
    </thead>
    <tbody>
        {if isset($payment_history) && count($payment_history)} 
            {foreach from=$payment_history item=phistory}
              <tr>
                 <td><a href="{$phistory['explorer_url']}">{substr($phistory['txid'],0,30)}...</a></td>
              </tr>
            {/foreach}
        {else}
            <tr><td style="text-align: center;">-</td></tr>
        {/if}
    </tbody>
  </table>
</div>