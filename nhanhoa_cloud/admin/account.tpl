{* Admin-area service panel for a Nhan Hoa Cloud VPS account. WebNIC visual language. *}
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
                    {if $nh_admin_css}<link rel="stylesheet" href="{$nh_admin_css|escape}" />{/if}

                    {assign var=nhhb value=$summary.hb_status|default:''}
                    {if $nhhb eq ''}
                        {assign var=nhlabel value='label-default'}
                    {elseif stripos($nhhb,'active')!==false}
                        {assign var=nhlabel value='label-success'}
                    {elseif stripos($nhhb,'pending')!==false or stripos($nhhb,'process')!==false}
                        {assign var=nhlabel value='label-warning'}
                    {elseif stripos($nhhb,'suspend')!==false or stripos($nhhb,'terminat')!==false or stripos($nhhb,'cancel')!==false or stripos($nhhb,'fraud')!==false}
                        {assign var=nhlabel value='label-danger'}
                    {else}
                        {assign var=nhlabel value='label-default'}
                    {/if}

                    <ul class="accor white nhanhoa-cloud nhanhoa-cloud-panel">
                        <li>
                            <a href="#">Nhân Hòa Cloud VPS</a>
                            <div class="sor">
                                <div class="row">
                                    <div class="col-md-3"><strong>Status</strong></div>
                                    <div class="col-md-9">
                                        {if $summary.hb_status}<span class="label {$nhlabel}">{$summary.hb_status|escape}</span> {/if}
                                        {$summary.status_text|default:'(chưa đồng bộ)'|escape}
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><strong>Cloud ID</strong></div>
                                    <div class="col-md-9">{$summary.cloud_id|default:'—'|escape}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><strong>Order</strong></div>
                                    <div class="col-md-9">{$summary.ord_name|default:$summary.ord_id|default:'—'|escape}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><strong>Plan code</strong></div>
                                    <div class="col-md-9"><code>{$summary.plan|default:'—'|escape}</code></div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><strong>IP</strong></div>
                                    <div class="col-md-9">{$summary.ip|default:'—'|escape}</div>
                                </div>
                                <div class="row">
                                    <div class="col-md-3"><strong>Expiry</strong></div>
                                    <div class="col-md-9">{$summary.end_time_fmt|default:'—'|escape}</div>
                                </div>

                                <hr />
                                <div class="row">
                                    <div class="col-md-3"><strong>Actions</strong></div>
                                    <div class="col-md-9">
                                        <button class="btn btn-primary btn-sm" onclick="return nh_cloud_do('sync')">Sync status</button>
                                        <button class="btn btn-default btn-sm" onclick="return nh_cloud_do('renew')">Renew</button>
                                        <a class="btn btn-default btn-sm" href="{$summary.panel_url}" target="_blank" rel="noopener">Nhan Hoa panel</a>
                                    </div>
                                </div>

                                <hr />
                                <div class="row">
                                    <div class="col-md-12">
                                        <p class="text-muted" style="margin:0">
                                            Suspend/Unsuspend/Terminate change HostBill billing state only — the Nhan Hoa
                                            Cloud API has no lifecycle-control command. Manage the VPS in the panel.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </li>
                    </ul>
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
