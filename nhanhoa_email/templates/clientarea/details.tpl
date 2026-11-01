{*
    Client area for a Nhan Hoa Email Hosting service.

    Answers the four questions a customer has: which domain, which plan (and what
    it includes), when it expires, and where to manage mailboxes. Read-only — the
    reseller API gives the customer nothing to change here; mailboxes are managed
    in the Nhan Hoa mail panel.
*}
{if $nhsection eq 'details'}
<div class="panel panel-default">
    <div class="panel-heading">Email Hosting của bạn</div>
    <div class="table-responsive">
        <table class="table table-condensed">
            <tr><th style="width:35%">Tên miền</th><td><strong>{$order.domain|escape}</strong></td></tr>
            <tr><th>Gói</th><td><code>{$order.service_code|escape}</code></td></tr>
            <tr><th>Mã đơn hàng</th><td>{if $order.ord_name}{$order.ord_name|escape}{elseif $order.ord_id}{$order.ord_id|escape}{else}<span class="text-muted">đang cập nhật</span>{/if}</td></tr>
            <tr><th>Ngày hết hạn</th><td>{if $order.end_human}{$order.end_human|escape}{else}<span class="text-muted">—</span>{/if}</td></tr>
        </table>
    </div>
    <div class="panel-footer">
        <a href="{$panel_url|escape}" target="_blank" class="btn btn-primary">
            Quản lý hộp thư trên bảng điều khiển Nhân Hòa
        </a>
        <span class="text-muted small">Đăng nhập, tạo và đổi mật khẩu hộp thư tại bảng điều khiển của Nhân Hòa.</span>
    </div>
</div>

{if $plan_desc}
<div class="panel panel-default">
    <div class="panel-heading">Gói dịch vụ bao gồm</div>
    <div class="panel-body">{$plan_desc}</div>
</div>
{/if}
{/if}

{if $nhsection eq 'billing'}
<div class="alert alert-info">
    Dịch vụ chưa hoạt động. Thông tin hoá đơn nằm ở khối bên dưới.
</div>
{/if}
