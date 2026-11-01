{* Admin-area service panel for a Nhan Hoa Cloud VPS account. *}
<form action="" method="post" id="account_form">
    <input type="hidden" value="{$details.firstpayment}" name="firstpayment" />
    <input type="hidden" name="account_id" value="{$details.id}" id="account_id" />
    <div class="blu">{include file='_common/accounts_nav.tpl'}</div>

    <div id="ticketbody">
        {include file='_common/accounts_billing.tpl'}

        <div class="tab_container">
            <div class="tab_content" style="display: block;">
                {include file='_common/accounts_details.tpl'}

                <div id="nhanhoa-cloud-panel" style="padding:8px 0;">
                    <h4>Nhân Hòa Cloud VPS</h4>
                    <table class="data-table">
                        <tbody>
                            <tr><td style="width:220px;"><b>Status</b></td>
                                <td>{$summary.hb_status|escape} {$summary.status_text|default:'(chưa đồng bộ)'|escape}</td></tr>
                            <tr><td><b>Cloud ID</b></td><td>{$summary.cloud_id|default:'—'|escape}</td></tr>
                            <tr><td><b>Order</b></td><td>{$summary.ord_name|default:$summary.ord_id|default:'—'|escape}</td></tr>
                            <tr><td><b>Plan code</b></td><td>{$summary.plan|default:'—'|escape}</td></tr>
                            <tr><td><b>IP</b></td><td>{$summary.ip|default:'—'|escape}</td></tr>
                            <tr><td><b>Expiry</b></td><td>{$summary.end_time_fmt|default:'—'|escape}</td></tr>
                        </tbody>
                    </table>
                    <div style="margin-top:8px;">
                        <button class="btn btn-info btn-sm" onclick="return nh_cloud_do('sync')">Sync status</button>
                        <button class="btn btn-default btn-sm" onclick="return nh_cloud_do('renew')">Renew</button>
                        <a class="btn btn-default btn-sm" href="{$summary.panel_url}" target="_blank" rel="noopener">Nhan Hoa panel</a>
                    </div>
                    <p class="text-muted" style="margin-top:6px;">
                        Suspend/Unsuspend/Terminate change HostBill billing state only — the Nhan Hoa
                        Cloud API has no lifecycle-control command. Manage the VPS in the panel.
                    </p>
                </div>
            </div>
        </div>

        <div class="clear"></div>
        {include file='_common/accounts_multimodules.tpl'}
        {include file='_common/noteseditor.tpl'}
    </div>

    <div class="blu">{include file='_common/accounts_nav.tpl'}</div>
    {securitytoken}
</form>
{literal}
<script type="text/javascript">
    function nh_cloud_do(action) {
        var url = '?cmd=accounts&action=edit&id={/literal}{$details.id}{literal}&service={/literal}{$details.id}{literal}';
        $.post(url, {vpsdo: action}, function (data) {
            var r = parse_response(data);
            $('#nhanhoa-cloud-panel').replaceWith(r);
        });
        return false;
    }
</script>
{/literal}
