<div class="wbox">
    <div class="wbox_header">
        {assign var=widg_name value="`$widget.name`_widget"}
        {if $lang[$widg_name]}{$lang[$widg_name]}
        {elseif $lang[$widget.name]}{$lang[$widget.name]}
        {elseif $widget.fullname}{$widget.fullname}
        {else}{$widget.name}
        {/if}
    </div>
    <div class="wbox_content">
        {if $nhanhoa_domain_ekyc_is_vn}
            <p>Định danh điện tử chủ thể cho tên miền <strong>{$details.name|escape}</strong>.</p>
        {else}
            <p>eKYC chỉ áp dụng cho tên miền <code>.vn</code>.</p>
        {/if}

        {if $nhanhoa_domain_ekyc_error}
            <div class="alert alert-danger">{$nhanhoa_domain_ekyc_error|escape}</div>
        {/if}

        {if $nhanhoa_domain_ekyc_url}
            <div class="alert alert-success">
                <a href="{$nhanhoa_domain_ekyc_url|escape}" target="_blank" rel="noopener noreferrer">{$nhanhoa_domain_ekyc_url|escape}</a>
            </div>
        {/if}

        {if $nhanhoa_domain_ekyc_is_vn}
            <form method="post" action="" style="margin:0; text-align:center;">
                <input type="hidden" name="widgetdo" value="nhanhoaDomainEkyc" />
                <button type="submit" class="padded btn btn-primary">Lấy link eKYC</button>
                {securitytoken}
            </form>
        {/if}
    </div>
</div>
