<div class="panel">
  <div class="panel-heading">
    {l s='EditeWallet' mod='wallets'}
  </div>
  <form action="index.php?controller=CoinremitterWallets&action=up&id={$wallet['id']}&token={Tools::getValue('token')}" method="POST">
  <div class="panel-body bootstrap form-horizontal">
      <div class="form-wrapper">
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Select Wallet Coin" data-html="true">Select  Coin</span>
            </label>
            <div class="col-lg-9">                      
              <input type="hidden" name="wallet_id" value="{$wallet['id']}">
              <select name="coin" class="fixed-width-xl" disabled=""> 
                  <option value="{$wallet['coin']}" selected="selected">{$wallet['coin']}</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Api Key" data-html="true">Api Key </span>
            </label>
            <div class="col-lg-9">                      
              <input class="form-control " type="text" name="wallets_api" value="{$wallet['api_key']}">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Wallet password" data-html="true">Password</span>
            </label>
            <div class="col-lg-9">                      
               <input class="form-control " type="password" name="wallet_password" value="{$wallet['password']}">
            </div>
          </div>
      </div>
    </div>
    <div class="panel-footer">
      <a href="index.php?controller=CoinremitterWallets&token={Tools::getValue('token')}" class="btn btn-danger pull-left"> <i class="process-icon-cancel"></i> Cancel</a>
        <button type="submit" class="btn btn-default pull-right" name="update_wallet"><i class="process-icon-save"></i> Update Wallet</button>
    </div>
  </form>
</divs