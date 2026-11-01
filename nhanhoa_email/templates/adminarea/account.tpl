{*
    Module block on the Nhan Hoa Email Hosting service page (admin).

    Shows the live order state read from Nhan Hoa and the two actions the reseller
    API actually supports: Sync (get_infoemail) and Renew (renew_email).
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
            <tr><th style="width:35%">Tên miền</th><td><strong>{$nh_order.domain|escape}</strong></td></tr>
            <tr><th>Mã gói</th><td><code>{$nh_order.service_code|escape}</code></td></tr>
            <tr><th>Mã đơn hàng</th><td>{if $nh_order.ord_id}{$nh_order.ord_id|escape}{else}<span class="text-muted">chưa có — bấm Đồng bộ</span>{/if}</td></tr>
            <tr><th>Tên đơn hàng</th><td>{$nh_order.ord_name|escape}</td></tr>
            <tr><th>Ngày hết hạn</th><td>{if $nh_order.end_human}{$nh_order.end_human|escape}{else}<span class="text-muted">—</span>{/if}</td></tr>
            <tr><th>Số tháng (gói)</th><td>{$nh_order.months}</td></tr>
        </table>

        <form method="post" action="{$abase}" style="display:inline">
            {$securitytoken}
            <input type="hidden" name="nhdo" value="sync" />
            <button class="btn btn-sm btn-default" type="submit">Đồng bộ từ Nhân Hòa</button>
        </form>
        <form method="post" action="{$abase}" class="form-inline" style="display:inline">
            {$securitytoken}
            <input type="hidden" name="nhdo" value="renew" />
            <input type="number" name="months" class="form-control input-sm" style="width:80px" min="6" step="6" value="{$nh_order.months}" />
            <button class="btn btn-sm btn-default" type="submit">Gia hạn</button>
        </form>
    </div>

    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading">Gói dịch vụ</div>
            <div class="panel-body">
                {if $nh_plan_desc}{$nh_plan_desc}{else}<span class="text-muted">Không đọc được mô tả gói từ API.</span>{/if}
            </div>
        </div>
        <a href="{$nh_panel_url|escape}" target="_blank" class="btn btn-default btn-sm">Mở bảng điều khiển Nhân Hòa</a>
    </div>
</div>

<p class="text-muted small" style="margin-top:12px">
    <strong>Lưu ý:</strong> API đại lý Nhân Hòa chỉ hỗ trợ <em>đăng ký</em>, <em>gia hạn</em> và
    <em>đọc trạng thái</em> đơn hàng. Các thao tác Tạm ngưng / Huỷ / Đổi gói / Đổi mật khẩu
    chỉ cập nhật trạng thái trong HostBill và phải thực hiện tại
    <a href="{$nh_panel_url|escape}" target="_blank">customer.nhanhoa.com</a>.
</p>
