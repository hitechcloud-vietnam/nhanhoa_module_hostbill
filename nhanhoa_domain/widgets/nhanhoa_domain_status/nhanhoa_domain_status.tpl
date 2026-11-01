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
        {if $nhanhoa_domain_status_error}
            <div class="alert alert-danger">{$nhanhoa_domain_status_error|escape}</div>
        {/if}

        {if $nhanhoa_domain_status_rows}
            <table cellspacing="0" cellpadding="0" border="0" width="100%" class="checker table table-striped">
                {if $nhanhoa_domain_status_declaration}
                    <tr>
                        <td width="40%"><strong>Trạng thái hồ sơ</strong></td>
                        <td>{$nhanhoa_domain_status_declaration|escape}</td>
                    </tr>
                {/if}
                {foreach from=$nhanhoa_domain_status_rows key=label item=value}
                    <tr>
                        <td width="40%"><strong>{$label|escape}</strong></td>
                        <td>{$value|escape}</td>
                    </tr>
                {/foreach}
            </table>
        {elseif !$nhanhoa_domain_status_error}
            <p>Nhấn nút bên dưới để hỏi trạng thái xử lý tại Nhân Hòa cho tên miền <strong>{$details.name|escape}</strong>.</p>
        {/if}

        <form method="post" action="" style="margin:10px 0 0; text-align:center;">
            <input type="hidden" name="widgetdo" value="nhanhoaDomainStatus" />
            <button type="submit" class="padded btn btn-primary">Kiểm tra trạng thái</button>
            {securitytoken}
        </form>
    </div>
</div>
