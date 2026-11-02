{* Client-area overview for a Nhan Hoa Cloud VPS service. WebNIC accordion style.
   Variables (unchanged): $notice, $summary, $panel_url. *}
{literal}
<style>
ul.accor.nhanhoa-cloud { list-style: none; margin: 0 0 10px; padding: 0; }
ul.accor.nhanhoa-cloud > li { margin-bottom: 10px; border: 1px solid #e5e5e5; border-radius: 4px; }
ul.accor.nhanhoa-cloud > li > a { display: block; padding: 10px 15px; font-weight: 600; background: #f7f7f9; border-bottom: 1px solid #e5e5e5; text-decoration: none; }
ul.accor.nhanhoa-cloud > li > .sor { display: block; padding: 12px 15px; }
ul.accor.nhanhoa-cloud .sor .row { margin-bottom: 10px; }
ul.accor.nhanhoa-cloud .sor .btn { margin: 2px 2px 6px 0; }
</style>
{/literal}

{if $notice}
    <div class="alert {if $notice.success}alert-info{else}alert-warning{/if}">
        {$notice.message|escape}
        {if $notice.panel_url || $notice.url}
            <a href="{if $notice.url}{$notice.url}{else}{$notice.panel_url}{/if}" target="_blank" rel="noopener">
                {$notice.url|default:$notice.panel_url|escape}
            </a>
        {/if}
    </div>
{/if}

<ul class="accor white nhanhoa-cloud">
    <li>
        <a href="#">Cloud VPS — Nhân Hòa</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Trạng thái</strong></div>
                <div class="col-md-9">
                    {if $summary.hb_status}<span class="label label-default">{$summary.hb_status|escape}</span> {/if}
                    {$summary.status_text|default:'—'|escape}
                </div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Cloud ID</strong></div>
                <div class="col-md-9">{$summary.cloud_id|default:'—'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã đơn hàng</strong></div>
                <div class="col-md-9">{$summary.ord_name|default:$summary.ord_id|default:'—'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Gói dịch vụ</strong></div>
                <div class="col-md-9"><code>{$summary.plan|default:'—'|escape}</code></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Địa chỉ IP</strong></div>
                <div class="col-md-9">{$summary.ip|default:'—'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Ngày hết hạn</strong></div>
                <div class="col-md-9">{$summary.end_time_fmt|default:'—'|escape}</div>
            </div>

            <hr />
            <div class="row">
                <div class="col-md-12">
                    <a class="btn btn-default btn-sm" href="?vpsaction=status">Làm mới trạng thái</a>
                    <a class="btn btn-primary btn-sm" href="{$panel_url}" target="_blank" rel="noopener">Mở trang quản trị Nhân Hòa</a>
                </div>
            </div>
        </div>
    </li>

    <li>
        <a href="#">Điều khiển VPS</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-12">
                    <p class="text-muted">
                        Bật/tắt, khởi động lại, cài lại hệ điều hành và console/VNC được thực hiện
                        trong trang quản trị Nhân Hòa.
                    </p>
                    <a class="btn btn-default btn-sm" href="?vpsaction=start">Bật</a>
                    <a class="btn btn-default btn-sm" href="?vpsaction=stop">Tắt</a>
                    <a class="btn btn-default btn-sm" href="?vpsaction=reboot">Khởi động lại</a>
                    <a class="btn btn-default btn-sm" href="?vpsaction=reinstall">Cài lại OS</a>
                    <a class="btn btn-default btn-sm" href="?vpsaction=console">Console / VNC</a>
                </div>
            </div>
        </div>
    </li>
</ul>
