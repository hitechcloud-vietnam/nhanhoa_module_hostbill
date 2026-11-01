{*
    Module block on the Nhân Hòa Hosting service page (admin).

    Order id / name / status / expiry are read live from Nhan Hoa each time the
    page opens. The reseller API has no suspend/terminate/change-plan command, so
    those are not offered here — only Sync and Renew.
*}
{assign var=abase value="?cmd=accounts&action=details&id=`$id`"}

{if $module_error}
    <div class="alert alert-danger">
        {foreach from=$module_error item=err}<div>{$err|escape}</div>{/foreach}
    </div>
{/if}

<div class="row">
    <div class="col-md-7">
        <table class="table table-condensed">
            <tr><th style="width:35%">Tên miền</th><td><strong>{$nh_domain|escape}</strong></td></tr>
            <tr><th>Mã gói (hostingCode)</th><td><code>{$nh_code|escape}</code></td></tr>
            <tr><th>Mã đơn hàng (ord_id)</th><td>{if $nh_order_id}<code>{$nh_order_id|escape}</code>{else}<span class="text-muted">chưa có — bấm Đồng bộ</span>{/if}</td></tr>
            {if $nh_info}
            <tr><th>Tên đơn</th><td>{$nh_info.ord_name|escape}</td></tr>
            <tr><th>Trạng thái</th><td>{$nh_info.content|escape} <span class="label label-default">{$nh_info.status|escape}</span></td></tr>
            <tr><th>Hết hạn</th><td>{if $nh_info.ord_end_time}{$nh_info.ord_end_time|date_format:"%Y-%m-%d"}{else}—{/if}</td></tr>
            {/if}
            {if $nh_cp_url}
            <tr><th>Control panel</th><td><a href="{$nh_cp_url|escape}" target="_blank" rel="noopener">{$nh_cp_url|escape}</a></td></tr>
            {/if}
        </table>
    </div>

    <div class="col-md-5">
        <table class="table table-condensed">
            <tr><th>Số dư đại lý</th><td>{if $nh_balance !== null}{$nh_balance|number_format} đ{else}<span class="text-muted">—</span>{/if}</td></tr>
        </table>

        <form method="post" action="{$abase}" style="display:inline">
            {$securitytoken}
            <input type="hidden" name="nhdo" value="sync" />
            <button class="btn btn-sm btn-default" type="submit">Đồng bộ từ Nhân Hòa</button>
        </form>

        <form method="post" action="{$abase}" class="form-inline" style="display:inline; margin-left:6px">
            {$securitytoken}
            <input type="hidden" name="nhdo" value="renew" />
            <input type="text" name="months" class="form-control input-sm" size="3" value="{$nh_months}" title="Số tháng (≥6)" />
            <button class="btn btn-sm btn-primary" type="submit">Gia hạn</button>
        </form>

        <p class="help-block" style="margin-top:10px">
            API đại lý Nhân Hòa không hỗ trợ tạm ngưng / huỷ / đổi gói tự động.
            Những thao tác đó thực hiện tại <a href="https://customer.nhanhoa.com" target="_blank" rel="noopener">customer.nhanhoa.com</a>.
        </p>
    </div>
</div>
