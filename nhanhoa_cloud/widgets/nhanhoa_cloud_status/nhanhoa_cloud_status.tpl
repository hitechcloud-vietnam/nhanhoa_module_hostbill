{* Nhan Hoa Cloud VPS service-summary widget — WebNIC widget look (.wbox + striped table). *}
<div class="wbox">
    <div class="wbox_header">Nhân Hòa Cloud VPS</div>
    <div class="wbox_content">
        {if $nhw_error}
            <div class="alert alert-danger">{$nhw_error|escape}</div>
        {/if}

        <table cellspacing="0" cellpadding="0" border="0" width="100%" class="checker table table-striped">
            <tbody>
                <tr><td style="width:45%"><strong>Trạng thái</strong></td><td>{if $nhw_summary.hb_status}<span class="label label-default">{$nhw_summary.hb_status|escape}</span>{else}—{/if} {if $nhw_summary.status_text}<span class="text-muted">({$nhw_summary.status_text|escape})</span>{/if}</td></tr>
                <tr><td><strong>Mã gói</strong></td><td>{if $nhw_summary.plan}<code>{$nhw_summary.plan|escape}</code>{else}—{/if}</td></tr>
                <tr><td><strong>Cloud ID</strong></td><td>{$nhw_summary.cloud_id|default:'—'|escape}</td></tr>
                <tr><td><strong>Mã đơn hàng</strong></td><td>{$nhw_summary.ord_name|default:$nhw_summary.ord_id|default:'—'|escape}</td></tr>
                <tr><td><strong>IP</strong></td><td>{$nhw_summary.ip|default:'—'|escape}</td></tr>
                <tr><td><strong>Hết hạn</strong></td><td>{$nhw_summary.end_time_fmt|default:'—'|escape}</td></tr>
                {if $nhw_balance !== null}<tr><td><strong>Số dư đại lý</strong></td><td>{$nhw_balance|number_format} đ</td></tr>{/if}
            </tbody>
        </table>

        {if $nhw_used}
            <h3 class="summarize">Dịch vụ đang dùng</h3>
            <table cellspacing="0" cellpadding="0" border="0" width="100%" class="checker table table-striped">
                <tbody>
                    {foreach from=$nhw_used item=grp}
                        <tr>
                            <td style="width:70%">{$grp.service_group_name|default:$grp.service_grou_name|default:'—'|escape}</td>
                            <td>{$grp.service_group_count|default:'0'|escape}</td>
                        </tr>
                    {/foreach}
                </tbody>
            </table>
        {/if}

        {if $nhw_panel}
            <p style="margin:10px 0 0; text-align:center;">
                <a href="{$nhw_panel|escape}" target="_blank" rel="noopener noreferrer" class="padded btn btn-primary">Control panel Nhân Hòa</a>
            </p>
        {/if}
    </div>
</div>
