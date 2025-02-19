<div class="row">
   <div class="col-md-12 d-print-block right-column">
      <div>
         <div class="card">
            <div class="card-header">
               <h3 class="card-header-title modal-title" style="margin-left:20px;">
                  Payment Detail (Coinremitter)
               </h3>
            </div>
            <div class="card-body">
               <table class="table">
                  <thead>
                     <tr>
                        <th>Address</th>
                        <th>
                           Base Currency
                        </th>
                        <th>
                           Coin
                        </th>
                        <th>
                           Status
                        </th>
                        <th>
                           Created at
                        </th>
                        {if $status == "Expired"}
                           <th>
                              Expire On
                           </th>
                        {/if}
                     </tr>
                  </thead>
                  <tbody>
                     <tr class="cellProduct">
                        <td style="height: 50px;">
                           {if isset($invoice_id) && $invoice_id!= ""} 
                              {$invoice_id}  -  <a href="{$url}">view</a>
                           {else}
                              {$address}
                           {/if}
                        </td>
                        <td>
                           {$base_currency}
                        </td>
                        <td>
                           {$coin}
                        </td>
                        <td>
                           {$status}
                        </td>
                        <td>
                           {$date_added}
                        </td>
                        {if $status == "Expired"}
                           <td>
                              {$expire_on}
                           </td>
                        {/if}
                     </tr>
                  </tbody>
               </table>
               <br>
               <div class="row">
                  <div class="col-md-3 order-md-2">
                     <div class="info-block">
                        <div class="row">
                           <div class="col-sm text-center">
                              <p class="text-muted mb-0"><strong>Order Amount</strong></p>
                              <strong>{$total_amount} {$coin}</strong>
                           </div>
                        </div>
                        <br>
                        <div class="row">
                           <div class="col-sm text-center">
                              <p class="text-muted mb-0"><strong>Paid Amount</strong></p>
                              <strong>{$paid_amount} {$coin}</strong>
                           </div>
                        </div>
                        <br>
                        <div class="row">
                           <div class="col-sm text-center">
                              <p class="text-muted mb-0"><strong>Pending Amount</strong></p>
                              <strong>{$pending_amount} {$coin}</strong>
                           </div>
                        </div>
                     </div>
                  </div>
                  <div class="col-md-9 order-md-1">
                     <br>
                     <h3>
                        Payment History
                     </h3>
                     <table class="table">
                        <thead>
                           <tr>
                              <th>
                                 Transaction Id
                              </th>
                              <th>
                                 Amount
                              </th>
                              <th>
                                 Confirmations
                              </th>
                              <th>
                                 Date
                              </th>
                           </tr>
                        </thead>
                        <tbody>
                           {if isset($payment_history) && count($payment_history)} 
                              {foreach from=$payment_history item=phistory}
                                 <tr class="cellProduct">
                                    <td style="height: 50px;">
                                       <a href="{$phistory['explorer_url']}">{substr($phistory['txid'],0,30)}...</a>
                                    </td>
                                    <td>
                                       {$phistory['amount']} {$coin}
                                    </td>
                                    <td>
                                       {$phistory['confirmations']}
                                    </td>
                                    <td>
                                       {$phistory['date']} (UTC)
                                    </td>
                                 </tr>
                              {/foreach}
                           {else}
                              <tr><td style="text-align: center;height: 50px;" colspan="4">No Transaction Found</td></tr>
                           {/if}
                        </tbody>
                     </table>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </div>
</div>
<br>