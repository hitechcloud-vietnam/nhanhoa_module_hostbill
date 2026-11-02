{* Product configuration for a Nhan Hoa Cloud VPS product. WebNIC accordion style.
   Loadable selects (plan code, OS template) fall back to free-text until the
   admin presses "Get values from server".

   Variables (unchanged): $options.vpscode, $options.ostemplate, $product.options. *}
{if $nh_admin_css}<link rel="stylesheet" href="{$nh_admin_css|escape}" />{/if}
<hr>
<ul class="accor white nhanhoa-cloud nhanhoa-cloud-panel">
    <li>
        <a href="#">Cấu hình Nhân Hòa Cloud VPS</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Cloud VPS plan code</strong></div>
                <div class="col-md-9">
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

            <div class="row">
                <div class="col-md-3"><strong>OS family</strong></div>
                <div class="col-md-9">
                    <select name="options[ostype]" class="form-control">
                        <option value="0" {if $product.options.ostype != '1'}selected{/if}>Linux</option>
                        <option value="1" {if $product.options.ostype == '1'}selected{/if}>Windows</option>
                    </select>
                </div>
            </div>

            <div class="row">
                <div class="col-md-3"><strong>OS template code</strong></div>
                <div class="col-md-9">
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

            <div class="row">
                <div class="col-md-3"><strong>Registration months (min 3)</strong></div>
                <div class="col-md-9">
                    <select name="options[vpsmonth]" class="form-control">
                        {foreach from=['3','6','12','24','36'] item=m}
                            <option value="{$m}" {if $product.options.vpsmonth == $m}selected{/if}>{$m}</option>
                        {/foreach}
                    </select>
                </div>
            </div>
        </div>
    </li>
</ul>
