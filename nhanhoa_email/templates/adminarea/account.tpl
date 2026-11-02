{*
    Module block on the Nhan Hoa Email Hosting service page (admin).
    WebNIC visual language: accordion + grid rows + btn-sm actions.

    Shows the live order state read from Nhan Hoa and the two actions the reseller
    API actually supports: Sync (get_infoemail) and Renew (renew_email).

    Variables (unchanged): $nh_order, $nh_plan_desc, $nh_panel_url, $module_error,
    $id, {$securitytoken}.
*}
{assign var=abase value="?cmd=accounts&action=details&id=`$id`"}

{if $nh_admin_css}<link rel="stylesheet" href="{$nh_admin_css|escape}" />{/if}

{if $module_error}
    <div class="alert alert-danger">
        {foreach from=$module_error item=err}<div>{$err|escape}</div>{/foreach}
    </div>
{/if}

<ul class="accor white nhanhoa-email nhanhoa-email-panel">
    <li>
        <a href="#">Nhân Hòa Email Hosting</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Tên miền</strong></div>
                <div class="col-md-9"><strong>{$nh_order.domain|default:'—'|escape}</strong></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã gói</strong></div>
                <div class="col-md-9"><code>{$nh_order.service_code|default:'—'|escape}</code></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã đơn hàng</strong></div>
                <div class="col-md-9">{if $nh_order.ord_id}<code>{$nh_order.ord_id|escape}</code>{else}<span class="text-muted">chưa có — bấm Đồng bộ</span>{/if}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Tên đơn hàng</strong></div>
                <div class="col-md-9">{$nh_order.ord_name|default:'—'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Ngày hết hạn</strong></div>
                <div class="col-md-9">{if $nh_order.end_human}{$nh_order.end_human|escape}{else}<span class="text-muted">—</span>{/if}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Số tháng (gói)</strong></div>
                <div class="col-md-9">{$nh_order.months|escape}</div>
            </div>

            <hr />
            <div class="row">
                <div class="col-md-3"><strong>Thao tác</strong></div>
                <div class="col-md-9">
                    <form method="post" action="{$abase}" class="form-inline">
                        {$securitytoken}
                        <input type="hidden" name="nhdo" value="sync" />
                        <button class="btn btn-default btn-sm" type="submit">Đồng bộ từ Nhân Hòa</button>
                    </form>
                    <form method="post" action="{$abase}" class="form-inline">
                        {$securitytoken}
                        <input type="hidden" name="nhdo" value="renew" />
                        <input type="number" name="months" class="form-control input-sm" style="width:80px" min="6" step="6" value="{$nh_order.months}" />
                        <button class="btn btn-primary btn-sm" type="submit">Gia hạn</button>
                    </form>
                    {if $nh_panel_url}
                        <a href="{$nh_panel_url|escape}" target="_blank" rel="noopener" class="btn btn-default btn-sm">Mở bảng điều khiển Nhân Hòa</a>
                    {/if}
                </div>
            </div>
        </div>
    </li>

    {if $nh_plan_desc}
        <li>
            <a href="#">Gói dịch vụ</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-12">{$nh_plan_desc}</div>
                </div>
            </div>
        </li>
    {/if}
</ul>

<p class="text-muted small" style="margin-top:12px">
    <strong>Lưu ý:</strong> API đại lý Nhân Hòa chỉ hỗ trợ <em>đăng ký</em>, <em>gia hạn</em> và
    <em>đọc trạng thái</em> đơn hàng. Các thao tác Tạm ngưng / Huỷ / Đổi gói / Đổi mật khẩu
    chỉ cập nhật trạng thái trong HostBill và phải thực hiện tại
    <a href="{$nh_panel_url|escape}" target="_blank">customer.nhanhoa.com</a>.
</p>
