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
        <p>Lấy liên kết ký số bản khai cho tên miền <strong>{$details.name|escape}</strong>.</p>

        {if $nhanhoa_domain_contract_error}
            <div class="alert alert-danger">{$nhanhoa_domain_contract_error|escape}</div>
        {/if}

        {if $nhanhoa_domain_contract_url}
            <div class="alert alert-success">
                <a href="{$nhanhoa_domain_contract_url|escape}" target="_blank" rel="noopener noreferrer">{$nhanhoa_domain_contract_url|escape}</a>
            </div>
        {elseif $nhanhoa_domain_contract_message}
            <div class="alert alert-info">{$nhanhoa_domain_contract_message|escape}</div>
        {/if}

        <form method="post" action="" style="margin:0; text-align:center;">
            <input type="hidden" name="widgetdo" value="nhanhoaDomainContract" />
            <button type="submit" class="padded btn btn-primary">Lấy link ký số</button>
            {securitytoken}
        </form>
    </div>
</div>
