{* Client-area overview for a Nhan Hoa Cloud VPS service. *}
<div class="nhanhoa-cloud panel panel-default">
    <div class="panel-heading">
        <h3 class="panel-title">Cloud VPS — Nhân Hòa</h3>
    </div>
    <div class="panel-body">

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

        <table class="table table-striped">
            <tbody>
                <tr><th style="width:220px;">Trạng thái</th>
                    <td>
                        {if $summary.hb_status}<span class="label label-default">{$summary.hb_status|escape}</span>{/if}
                        {$summary.status_text|default:'—'|escape}
                    </td></tr>
                <tr><th>Cloud ID</th><td>{$summary.cloud_id|default:'—'|escape}</td></tr>
                <tr><th>Mã đơn hàng</th><td>{$summary.ord_name|default:$summary.ord_id|default:'—'|escape}</td></tr>
                <tr><th>Gói dịch vụ</th><td>{$summary.plan|default:'—'|escape}</td></tr>
                <tr><th>Địa chỉ IP</th><td>{$summary.ip|default:'—'|escape}</td></tr>
                <tr><th>Ngày hết hạn</th><td>{$summary.end_time_fmt|default:'—'|escape}</td></tr>
            </tbody>
        </table>

        <div class="btn-toolbar" role="toolbar" style="margin-top:12px;">
            <a class="btn btn-default btn-sm" href="?vpsaction=status">Làm mới trạng thái</a>
            <a class="btn btn-primary btn-sm" href="{$panel_url}" target="_blank" rel="noopener">Mở trang quản trị Nhân Hòa</a>
        </div>

        <hr>
        <h4>Điều khiển VPS</h4>
        <p class="text-muted">
            Bật/tắt, khởi động lại, cài lại hệ điều hành và console/VNC được thực hiện
            trong trang quản trị Nhân Hòa.
        </p>
        <div class="btn-group" role="group">
            <a class="btn btn-default btn-sm" href="?vpsaction=start">Bật</a>
            <a class="btn btn-default btn-sm" href="?vpsaction=stop">Tắt</a>
            <a class="btn btn-default btn-sm" href="?vpsaction=reboot">Khởi động lại</a>
            <a class="btn btn-default btn-sm" href="?vpsaction=reinstall">Cài lại OS</a>
            <a class="btn btn-default btn-sm" href="?vpsaction=console">Console / VNC</a>
        </div>

    </div>
</div>
