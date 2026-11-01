{* Nhan Hoa Email Hosting service-summary widget. *}
<div class="wbox">
    <div class="wbox_header">Nhân Hòa Email Hosting</div>
    <div class="wbox_content">
        {if $nhw_error}
            <div class="alert alert-danger">{$nhw_error|escape}</div>
        {/if}

        <table cellspacing="0" width="100%" class="data-table table table-striped">
            <tbody>
                <tr><td style="width:45%"><strong>Tên miền</strong></td><td>{$nhw_order.domain|default:'—'|escape}</td></tr>
                <tr><td><strong>Mã gói</strong></td><td>{if $nhw_order.service_code}<code>{$nhw_order.service_code|escape}</code>{else}—{/if}</td></tr>
                <tr><td><strong>Mã đơn hàng</strong></td><td>{if $nhw_order.ord_id}<code>{$nhw_order.ord_id|escape}</code>{else}<span class="text-muted">chưa có — bấm Đồng bộ ở trang dịch vụ</span>{/if}</td></tr>
                {if $nhw_order.ord_name}<tr><td><strong>Tên đơn</strong></td><td>{$nhw_order.ord_name|escape}</td></tr>{/if}
                <tr><td><strong>Hết hạn</strong></td><td>{$nhw_order.end_human|default:'—'|escape}</td></tr>
                {if $nhw_balance !== null}<tr><td><strong>Số dư đại lý</strong></td><td>{$nhw_balance|number_format} đ</td></tr>{/if}
            </tbody>
        </table>

        {if $nhw_used}
            <h3 class="summarize">Dịch vụ đang dùng</h3>
            <table cellspacing="0" width="100%" class="data-table table table-striped">
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
            <p style="margin-top:10px">
                <a href="{$nhw_panel|escape}" target="_blank" rel="noopener noreferrer" class="btn btn-default btn-sm">Control panel Nhân Hòa</a>
            </p>
        {/if}
    </div>
</div>
