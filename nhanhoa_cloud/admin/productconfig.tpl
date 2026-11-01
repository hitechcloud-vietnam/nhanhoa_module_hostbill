{* Product configuration for a Nhan Hoa Cloud VPS product.
   Loadable selects (plan code, OS template) fall back to free-text until the
   admin presses "Get values from server". *}
<hr>
<div class="col-sm-12">
    <div class="form-inline" style="margin-bottom:10px;">
        <label style="margin: 10px 15px 10px 0;">Cloud VPS plan code</label>
        {if !is_array($options.vpscode.default)}
            <a class="vtip_description" title="Press <b>Get values from server</b> to load Cloud plan codes"></a>
            <input class="form-control" type="text" name="options[vpscode]" value="{$product.options.vpscode}">
        {else}
            <select name="options[vpscode]" class="form-control">
                {foreach from=$options.vpscode.default item=label key=code}
                    <option value="{$code|escape}" {if $product.options.vpscode == $code}selected{/if}>{$label|escape}</option>
                {/foreach}
            </select>
        {/if}
    </div>
</div>

<div class="col-sm-12">
    <div class="form-inline" style="margin-bottom:10px;">
        <label style="margin: 10px 15px 10px 0;">OS family</label>
        <select name="options[ostype]" class="form-control">
            <option value="0" {if $product.options.ostype != '1'}selected{/if}>Linux</option>
            <option value="1" {if $product.options.ostype == '1'}selected{/if}>Windows</option>
        </select>
    </div>
</div>

<div class="col-sm-12">
    <div class="form-inline" style="margin-bottom:10px;">
        <label style="margin: 10px 15px 10px 0;">OS template code</label>
        {if !is_array($options.ostemplate.default)}
            <a class="vtip_description" title="Press <b>Get values from server</b> to load OS templates"></a>
            <input class="form-control" type="text" name="options[ostemplate]" value="{$product.options.ostemplate}">
        {else}
            <select name="options[ostemplate]" class="form-control">
                {foreach from=$options.ostemplate.default item=label key=code}
                    <option value="{$code|escape}" {if $product.options.ostemplate == $code}selected{/if}>{$label|escape}</option>
                {/foreach}
            </select>
        {/if}
    </div>
</div>

<div class="col-sm-12">
    <div class="form-inline" style="margin-bottom:10px;">
        <label style="margin: 10px 15px 10px 0;">Registration months (min 3)</label>
        <select name="options[vpsmonth]" class="form-control">
            {foreach from=['3','6','12','24','36'] item=m}
                <option value="{$m}" {if $product.options.vpsmonth == $m}selected{/if}>{$m}</option>
            {/foreach}
        </select>
    </div>
</div>
