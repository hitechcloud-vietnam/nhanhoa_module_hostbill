{*
    Client area for a Nhân Hòa Hosting service. WebNIC accordion style (read-only).

    Answers the four things the customer wants: which domain, which plan, the
    live order status and expiry, and where the control panel is.

    Variables (unchanged): $nhsection, $domain, $hosting_code, $order_name,
    $order_status, $expiry, $cp_url.
*}
{literal}
<style>
ul.accor.nhanhoa-hosting { list-style: none; margin: 0; padding: 0; }
ul.accor.nhanhoa-hosting > li { margin-bottom: 10px; border: 1px solid #e5e5e5; border-radius: 4px; }
ul.accor.nhanhoa-hosting > li > a { display: block; padding: 10px 15px; font-weight: 600; background: #f7f7f9; border-bottom: 1px solid #e5e5e5; text-decoration: none; }
ul.accor.nhanhoa-hosting > li > .sor { display: block; padding: 12px 15px; }
ul.accor.nhanhoa-hosting .sor .row { margin-bottom: 10px; }
</style>
{/literal}
{if $nhsection eq 'billing'}
<div class="alert alert-info">
    Dịch vụ chưa hoạt động. Thông tin hoá đơn nằm ở khối bên dưới.
</div>
{else}

<ul class="accor white nhanhoa-hosting">
    <li>
        <a href="#">Thông tin hosting</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Tên miền</strong></div>
                <div class="col-md-9"><strong>{$domain|escape}</strong></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Gói dịch vụ</strong></div>
                <div class="col-md-9"><code>{$hosting_code|escape}</code></div>
            </div>
            {if $order_name}
                <div class="row">
                    <div class="col-md-3"><strong>Mã đơn hàng</strong></div>
                    <div class="col-md-9">{$order_name|escape}</div>
                </div>
            {/if}
            {if $order_status}
                <div class="row">
                    <div class="col-md-3"><strong>Trạng thái</strong></div>
                    <div class="col-md-9"><span class="label label-default">{$order_status|escape}</span></div>
                </div>
            {/if}
            {if $expiry}
                <div class="row">
                    <div class="col-md-3"><strong>Ngày hết hạn</strong></div>
                    <div class="col-md-9">{$expiry|date_format:"%d/%m/%Y"}</div>
                </div>
            {/if}
            {if $cp_url}
                <hr />
                <div class="row">
                    <div class="col-md-12">
                        <a href="{$cp_url|escape}" target="_blank" rel="noopener" class="btn btn-primary btn-sm">Mở control panel</a>
                        <span class="text-muted" style="margin-left:8px">Đăng nhập bằng thông tin được cấp qua email.</span>
                    </div>
                </div>
            {/if}
        </div>
    </li>
</ul>

{/if}
