<div class="box">
   <h3>{l s=' Payment Detail (Coinremitter) ' d='Shop.Theme.Customeraccount'}</h3>
   <div style="overflow-y:auto">
      <table class="table table-bordered">
         <thead class="thead-default">
            <tr>
               {if isset($invoice_id) && $invoice_id!= ""} 
                  <th>{l s='Invoice Id' d='Shop.Theme.Global'}</th>
               {else}
                  <th>{l s='Address' d='Shop.Theme.Global'}</th>
               {/if}
               <th>{l s='Coin' d='Shop.Theme.Global'}</th>
               <th>{l s='Total Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Paid Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Pending Amount' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Payment Status' d='Shop.Theme.Checkout'}</th>
               <th>{l s='Date ' d='Shop.Theme.Checkout'}</th>
            </tr>
         </thead>
         <tbody>
            <tr>
               {if isset($invoice_id) && $invoice_id!= ""} 
                  <td>{$invoice_id}  -  <a href="{$url}">view</a></td>
               {else}
                  <td>{$address}</td>
               {/if}
               <td>{$coin}</td>
               <td>{$total_amount} {$coin}</td>
               <td>{$paid_amount} {$coin}</td>
               <td>{$pending_amount} {$coin}</td>
               <td>{$status}</td>
               <td>{$date}</td>
            </tr>
         </tbody>
      </table>
   </div>
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
                  <td><a href="{$phistory['explorer_url']}" target="_blank">{substr($phistory['txid'],0,30)}...</a></td>
               </tr>
            {/foreach}
         {else}
            <tr><td style="text-align: center;">-</td></tr>
         {/if}
      </tbody>
   </table>
   <div>
      {if ($status=="Pending" || $status == "Under Paid") && $invoice_id == ""}
         <a class="btn btn-primary form-control-submit" href="{$invoice_url}">
            Pay Now
         </a>
      {/if}
   </div>
</div>