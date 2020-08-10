
   <div class="panel">
      <div class="panel-heading">
         <i class="icon-credit-card"></i>
         Payment Detail (Coinremitter) 
     </div>
      <!-- Tab nav -->

      <hr>
      <!-- Tab nav -->
      <ul class="nav nav-tabs" id="myTab">
         <li class="active">
            <a href="#order_detail">
            Order Detail ( Coinremitter ) </a>
         </li>
         <li>
            <a href="#c_rate">
            Conversation Rate</a>
         </li>
         <li>
            <a href="#p_history">
            Payment History</a>
         </li>
      </ul>
      <!-- Tab content -->
      <div class="tab-content panel">
         <!-- Tab shipping -->
         <div class="tab-pane active" id="order_detail">
            <h4 class="visible-print">Order Detail</h4>
             <div class="row">
	            <div class="col-xs-6">
	                <dl class="well list-detail">
	                     <table class="table">
                           <thead>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Invoice Id</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['invoice_id']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Invoice Name</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['invoice_name']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Base Currency</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['base_currancy']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Coin</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['coin']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Status</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['status']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Created on </span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['date_added']}</span>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Invoice URl</span>
                                 </th>
                                  <th>
                                    <a  href="{$inv_detail['invoice_url']}" target="_blanck" class="title_box ">{substr($inv_detail['invoice_url'],0,30)}...</a>
                                 </th>
                              </tr>
                              <tr>  
                                 <th>
                                    <span class="title_box ">Description</span>
                                 </th>
                                  <th>
                                    <span class="title_box ">{$inv_detail['description']}</span>
                                 </th>
                              </tr>
                           </thead>
                        </table>
	                </dl>
                  
	           </div>
	           <div class="col-xs-6">
	                <dl class="well list-detail">
	                    <span><b>Total Amount</b></span>
                       <hr>
                       <table class="table">
                           <thead>
                              {if isset($total_amt) && count($total_amt)} 
                                 {foreach from=$total_amt key=k item=tamt}
                                    <tr>  
                                       <th>
                                          <span class="title_box ">{$k}</span>
                                       </th>
                                        <th>
                                          <span class="title_box ">{$tamt}</span>
                                       </th>
                                    </tr>
                                 {/foreach}
                              {/if}
                              
                           </thead>
                        </table>
	                </dl>
                   <dl class="well list-detail">
                       <span><b>Paid Amount</b></span>
                       <hr>
                       <table class="table">
                           <thead>
                              {if isset($paid_amt) && count($paid_amt)} 
                                 {foreach from=$paid_amt key=k item=pamt}
                                    <tr>  
                                       <th>
                                          <span class="title_box ">{$k}</span>
                                       </th>
                                        <th>
                                          <span class="title_box ">{$pamt}</span>
                                       </th>
                                    </tr>
                                 {/foreach}
                              {/if}
                              
                           </thead>
                        </table>
                   </dl>
	           </div>
          </div>
            	
         </div>
         <!-- Tab returns -->
         <div class="tab-pane" id="c_rate">
            <h4 class="visible-print">Conversation Rate </h4>
             <div class="form-horizontal">
               <div class="table-responsive">
                   <table class="table" id="shipping_table">
                     <thead>
                        <tr>                 
                        {if isset($conversation_rate) && count($conversation_rate)} 
                           {foreach from=$conversation_rate key=k item=crate}
                              <th>
                                 <span class="title_box ">{str_replace("_"," To ",$k)}  </span>
                              </th>
                           {/foreach}
                        {/if}
                        </tr>
                     </thead>
                     <tbody>
                         {if isset($conversation_rate) && count($conversation_rate)} 
                           {foreach from=$conversation_rate key=k item=crate}
                                 {$c=explode("_",$k)}
                                 <th>
                                    <span class="title_box ">{$crate} {$c[1]}</span>
                                 </th>
                           {/foreach}
                        {/if}
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
          <div class="tab-pane" id="p_history">
            <h4 class="visible-print">Payment History </h4>
            <!-- Return block -->
            <div class="form-horizontal">
               <div class="table-responsive">
                  <table class="table" id="shipping_table">
                     <thead>
                        <tr>
                           <th>
                              <span class="title_box ">Trx Id	</span>
                           </th>
                           <th>
                              <span class="title_box ">Amount</span>
                           </th>
                           <th>
                              <span class="title_box ">Confirmation</span>
                           </th>
                           <th>
                              <span class="title_box ">Date</span>
                           </th>
                        </tr>
                     </thead>
                     <tbody>
                     	{if isset($payment_history) && count($payment_history)} 
	                     	{foreach from=$payment_history item=phistory}
	                        <tr>
	                           <td><a href="{$phistory['explorer_url']}">{substr($phistory['txid'],0,30)}...</a></td>
	                           <td>{$phistory['amount']} {$inv_detail['coin']}</td>
	                           <td class="text-center">{$phistory['confirmation']}</td>
	                           <td>{$phistory['date']}</td>
	                        </tr>
	                        {/foreach}
                        {else}
					      <option>No Wallets.</option>
					    {/if}
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
      </div>
      <script>
         $('#myTab a').click(function (e) {
           e.preventDefault()
           $(this).tab('show')
         })
      </script>
   </div>