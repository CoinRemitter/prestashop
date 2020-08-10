<div class="panel">
  <div class="panel-heading">
    {l s='Add Wallet' mod='coinremitter'}
  </div>
  <form action="index.php?controller=CoinremitterWallets&action=save&token={Tools::getValue('token')}" method="POST">
  <div class="panel-body bootstrap form-horizontal">
      <div class="form-wrapper">
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Select Wallet Coin" data-html="true">Select  Coin</span>
            </label>
            <div class="col-lg-9">                      
              <select name="coin" class=" fixed-width-xl" id="id_tax_rules_group">
                {if isset($coins) && count($coins)} 
                  {foreach from=$coins item=coin}
                    <option value="{$coin['value']}">{$coin['label']}</option>
                  {/foreach}
                {else}
                  <option>No coin.</option>
                {/if}
              </select>
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Api Key" data-html="true">Api Key </span>
            </label>
            <div class="col-lg-9">                      
              <input class="form-control " type="text" name="wallets_api" value="{$params['wallets_api']}">
            </div>
          </div>
          <div class="form-group">
            <label class="control-label col-lg-3">
              <span title="" data-toggle="tooltip" class="label-tooltip" data-original-title="Wallet password" data-html="true">Password</span>
            </label>
            <div class="col-lg-9">                      
               <input class="form-control " type="password" name="wallet_password" value="{$params['wallet_password']}">
            </div>
          </div>
      </div>
    </div>
    <div class="panel-footer">
      <a href="index.php?controller=CoinremitterWallets&token={Tools::getValue('token')}" class="btn btn-danger pull-left"><i class="process-icon-cancel"></i> Cancel</a>
        <button type="submit" class="btn btn-default pull-right" name="create_wallet"><i class="process-icon-save"></i> Save</button>
    </div>
  </form>
</divs