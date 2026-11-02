{*
    Client area for a Nhan Hoa Email Hosting service. WebNIC accordion style.

    Answers the four questions a customer has: which domain, which plan (and what
    it includes), when it expires, and where to manage mailboxes. Read-only — the
    reseller API gives the customer nothing to change here; mailboxes are managed
    in the Nhan Hoa mail panel.

    Variables (unchanged): $nhsection, $order, $plan_desc, $panel_url.
*}
{literal}
<style>
ul.accor.nhanhoa-email { list-style: none; margin: 0 0 10px; padding: 0; }
ul.accor.nhanhoa-email > li { margin-bottom: 10px; border: 1px solid #e5e5e5; border-radius: 4px; }
ul.accor.nhanhoa-email > li > a { display: block; padding: 10px 15px; font-weight: 600; background: #f7f7f9; border-bottom: 1px solid #e5e5e5; text-decoration: none; }
ul.accor.nhanhoa-email > li > .sor { display: block; padding: 12px 15px; }
ul.accor.nhanhoa-email .sor .row { margin-bottom: 10px; }
</style>
{/literal}
{if $nhsection eq 'details'}
<ul class="accor white nhanhoa-email">
    <li>
        <a href="#">Email Hosting của bạn</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Tên miền</strong></div>
                <div class="col-md-9"><strong>{$order.domain|escape}</strong></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Gói</strong></div>
                <div class="col-md-9"><code>{$order.service_code|escape}</code></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã đơn hàng</strong></div>
                <div class="col-md-9">{if $order.ord_name}{$order.ord_name|escape}{elseif $order.ord_id}{$order.ord_id|escape}{else}<span class="text-muted">đang cập nhật</span>{/if}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Ngày hết hạn</strong></div>
                <div class="col-md-9">{if $order.end_human}{$order.end_human|escape}{else}<span class="text-muted">—</span>{/if}</div>
            </div>
            <hr />
            <div class="row">
                <div class="col-md-12">
                    <a href="{$panel_url|escape}" target="_blank" class="btn btn-primary btn-sm">
                        Quản lý hộp thư trên bảng điều khiển Nhân Hòa
                    </a>
                    <span class="text-muted small" style="margin-left:8px">Đăng nhập, tạo và đổi mật khẩu hộp thư tại bảng điều khiển của Nhân Hòa.</span>
                </div>
            </div>
        </div>
    </li>

    {if $plan_desc}
        <li>
            <a href="#">Gói dịch vụ bao gồm</a>
            <div class="sor">
                <div class="row"><div class="col-md-12">{$plan_desc}</div></div>
            </div>
        </li>
    {/if}
</ul>
{/if}

{if $nhsection eq 'billing'}
<div class="alert alert-info">
    Dịch vụ chưa hoạt động. Thông tin hoá đơn nằm ở khối bên dưới.
</div>
{/if}
