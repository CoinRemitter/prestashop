{extends file='page.tpl'}
{block name='page_content'}
   <div class="row">
      <div class="col-md-12">
         <h3 class="h1 card-title">
            <i class="material-icons rtl-no-flip done" style="color: #f00;font-size: 30px;">clear</i>Your order is Canceled
         </h3>
         <p>
            Your order <a href="index.php?controller=order-detail&id_order={$order_id}">#{$order_id}</a> has been cancelled successfully.
         </p>
      </div>
   </div>
{/block}