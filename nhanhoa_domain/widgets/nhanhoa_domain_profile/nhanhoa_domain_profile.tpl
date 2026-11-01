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
        {if $nhanhoa_domain_profile_error}
            <div class="alert alert-danger">{$nhanhoa_domain_profile_error|escape}</div>
        {/if}

        {if $nhanhoa_domain_profile_summary}
            <table cellspacing="0" cellpadding="0" border="0" width="100%" class="checker table table-striped">
                {foreach from=$nhanhoa_domain_profile_summary key=label item=value}
                    <tr>
                        <td width="40%"><strong>{$label|escape}</strong></td>
                        <td>{$value|escape}</td>
                    </tr>
                {/foreach}
            </table>
        {elseif !$nhanhoa_domain_profile_error}
            <p>Nhấn nút bên dưới để tải hồ sơ chủ thể mới nhất từ Nhân Hòa.</p>
        {/if}

        {if $nhanhoa_domain_profile_contacts}
            {foreach from=$nhanhoa_domain_profile_contacts key=title item=fields}
                <div style="margin-top:10px;">
                    <strong>{$title|escape}</strong>
                    <table cellspacing="0" cellpadding="0" border="0" width="100%" class="checker table table-striped">
                        {foreach from=$fields key=label item=value}
                            <tr>
                                <td width="40%">{$label|escape}</td>
                                <td>{$value|escape}</td>
                            </tr>
                        {/foreach}
                    </table>
                </div>
            {/foreach}
        {/if}

        <form method="post" action="" style="margin:10px 0 0; text-align:center;">
            <input type="hidden" name="widgetdo" value="nhanhoaDomainProfile" />
            <button type="submit" class="padded btn btn-primary">Tải hồ sơ chủ thể</button>
            {securitytoken}
        </form>
    </div>
</div>
