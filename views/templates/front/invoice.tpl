{extends file='page.tpl'}
{block name='page_content'}
	<h2>Order Invoice #{$order_id} (Order Reference - {$reference})</h2>
  	<main id="site-content" class="crypto-invoice-order" role="main">
      <div class="cr-plugin-copy">
         <p>Copied</p>
      </div>
      <article class="post-8 page type-page status-publish hentry" id="post-8">
         <input type="hidden" id="address" value="{$order_address}">
         <input type="hidden" id="order_id" value="{$order_id}">
         <input type="hidden" id="image_path" value="{$img_path}">
         <input type="hidden" id="coin" value="{$coin}">
         <input type="hidden" id="expire_on">
         <div class="post-inner thin ">
            <div class="entry-content">
               <div class="cr-plugin-main-box clearfix">
                  <div class="cr-plugin-left">
                     <div class="cr-plugin-shipping cr-plugin-shadow cr-plugin-mr-top clearfix">
                        <div class="cr-plugin-shipping-address">
                           <h3 class="cr-plugin-title">Billing Address</h3>
                           <p>
                              <address>{$order.addresses.delivery.formatted nofilter}</address>
                           </p>
                        </div>
                        <div class="cr-plugin-billing-address">
                           <h3 class="cr-plugin-title">Shipping Address</h3>
                           <p>
                              <address>{$order.addresses.invoice.formatted nofilter}</address>
                           </p>
                        </div>
                     </div>
                     <div class="cr-plugin-cart-summary cr-plugin-shadow cr-plugin-mr-top">
                        <h3 class="cr-plugin-title">Cart Summary</h3>
                        <div class="cr-plugin-cart-table">
                           <div class="cr-plugin-cart-table-box">
                              <table>
                                 <thead>
                                    <tr>
                                       <th>Product Info</th>
                                       <th style="text-align: center;">Quantity</th>
                                       <th style="text-align: right;">Price</th>
                                    </tr>
                                 </thead>
                                 <tbody>

                                    {foreach from=$products key=team item=product}

                                    <tr>
                                       <td style="width: 300px;">
                                          <div class="cr-plugin-cart-img">
                                             <a href=""><img src="{$product.image}" align=""></a>
                                          </div>
                                          <div class="cr-plugin-cart-des">
                                             <p>{$product.name}</p>
                                          </div>
                                       </td>
                                       <td style="text-align: center;">
                                          <span>{$product.quantity}</span>
                                       </td>
                                       <td style="text-align: right;">
                                          <span>{$orderCurrencySymbol}{$product.price}</span>
                                       </td>
                                    </tr>
                                    {/foreach}
                                 </tbody>
                              </table>
                           </div>
                        </div>
                        <div class="cr-plugin-payment-detail">
                           <h3 class="cr-plugin-title">Payment Details</h3>
                           <ul>
                              <li>Total <span>{$orderCurrencySymbol}{$subTotal}</span></li>
                              <li>Shipping  Fee <span>{$orderCurrencySymbol}{$shippingAmount}</span></li>
                              <li>Total Taxes ({$carrier_tax_rate}%)<span>{$orderCurrencySymbol}{$taxAmount}</span></li>
                           </ul>
                           <ul class="cr-plugin-payment-grand">
                              <li>Grand Total <span>{$orderCurrencySymbol}{$grandTotal}</span></li>
                           </ul>
                        </div>
                     </div>
                  </div>
                  <div class="cr-plugin-right">
                     <div class="cr-plugin-billing-main cr-plugin-shadow">
                        <h3 class="cr-plugin-title">Billing Address</h3>
                        <div class="cr-plugin-timer" id="timerStatus"></div>
                        <div class="cr-plugin-billing-box">
                           <div class="cr-plugin-billing-code">
                              <img class="copyToClipboard" data-copy-detail="{$order_address}" src="{$qr_code}" title="click to copy" align="">
                           </div>
                           <div class="cr-plugin-billing-amount">
                              <ul>
                                 <li>
                                    <span>Address</span>
                                    <p class="copyToClipboard" title="click to copy" data-copy-detail="{$order_address}">{$order_address}</p>
                                 </li>
                                 <li>
                                    <span>Amount</span>
                                    <p class="copyToClipboard" title="click to copy" data-copy-detail="{$totalAmount}">{$totalAmount} {$coin}</p>
                                 </li>
                              </ul>
                           </div>
                        </div>
                     </div>
                     <div class="cr-plugin-payment-history cr-plugin-shadow cr-plugin-mr-top">
                        <h3 class="cr-plugin-title">Payment  History</h3>
                        <div class="cr-plugin-timer" id="paymentStatus"></div>
                        <div class="cr-plugin-history-list" id="cr-plugin-history-list">
                           <div class="cr-plugin-history-box">
                              <div class="cr-plugin-history">
                                 
                              </div>
                           </div>
                        </div>
                        <div class="cr-plugin-history-footer">
                           <ul class="clearfix">
                              <li>Paid <span id="paid-amt">0.00000000 {$coin}</span></li>
                              <li>Pending <span id="pending-amt">{$totalAmount} {$coin}</span></li>
                           </ul>
                        </div>
                     </div>
                     <div class="cr-plugin-brand">
                        <span style="">Secured by</span>
                        <a href="https://coinremitter.com" target="_blank">
                           <img src="{$img_path}logo.png">
                        </a>
                     </div>
                  </div>
               </div>
            </div>
         </div>
      </article>
   </main>
{/block}