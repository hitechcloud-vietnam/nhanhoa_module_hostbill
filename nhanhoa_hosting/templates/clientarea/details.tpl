{*
    Client area for a Nhân Hòa Hosting service.

    Answers the four things the customer wants: which domain, which plan, the
    live order status and expiry, and where the control panel is.
*}
{if $nhsection eq 'billing'}
<div class="alert alert-info">
    Dịch vụ chưa hoạt động. Thông tin hoá đơn nằm ở khối bên dưới.
</div>
{else}

<div class="panel panel-default">
    <div class="panel-heading">Thông tin hosting</div>
    <div class="table-responsive">
        <table class="table table-condensed">
            <tr><th style="width:35%">Tên miền</th><td><strong>{$domain|escape}</strong></td></tr>
            <tr><th>Gói dịch vụ</th><td><code>{$hosting_code|escape}</code></td></tr>
            {if $order_name}<tr><th>Mã đơn hàng</th><td>{$order_name|escape}</td></tr>{/if}
            {if $order_status}<tr><th>Trạng thái</th><td>{$order_status|escape}</td></tr>{/if}
            {if $expiry}<tr><th>Ngày hết hạn</th><td>{$expiry|date_format:"%d/%m/%Y"}</td></tr>{/if}
        </table>
    </div>
    {if $cp_url}
    <div class="panel-footer">
        <a href="{$cp_url|escape}" target="_blank" rel="noopener" class="btn btn-primary">Mở control panel</a>
        <span class="text-muted" style="margin-left:8px">Đăng nhập bằng thông tin được cấp qua email.</span>
    </div>
    {/if}
</div>

{/if}
