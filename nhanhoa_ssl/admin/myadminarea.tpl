<ul class="accor white cert">
    <li>
        <a href="#">Nhan Hoa SSL — Order Details</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Common name (domain)</strong></div>
                <div class="col-md-8">{$cert.cn|default:'N/A'}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Product (service code)</strong></div>
                <div class="col-md-8">{$cert.service_code|default:'N/A'}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Nhan Hoa order ID</strong></div>
                <div class="col-md-8">{$cert.order_id|default:'N/A'}</div>
            </div>
            {if $cert.ord_name}
                <div class="row">
                    <div class="col-md-3"><strong>Order name</strong></div>
                    <div class="col-md-8">{$cert.ord_name}</div>
                </div>
            {/if}
            <div class="row">
                <div class="col-md-3"><strong>Status</strong></div>
                <div class="col-md-8"><span class="label label-default">{$cert.status|default:'N/A'}</span></div>
            </div>
            {if $cert.status_raw}
                <div class="row">
                    <div class="col-md-3"><strong>Vendor status</strong></div>
                    <div class="col-md-8"><code>{$cert.status_raw|escape}</code></div>
                </div>
            {/if}
            <div class="row">
                <div class="col-md-3"><strong>Expires</strong></div>
                <div class="col-md-8">{$cert.expiry|default:'N/A'}</div>
            </div>
            {if $cert.san}
                <div class="row">
                    <div class="col-md-3"><strong>SAN</strong></div>
                    <div class="col-md-8">
                        {foreach from=$cert.san item=san}
                            <div>{$san|escape}</div>
                        {/foreach}
                    </div>
                </div>
            {/if}
            <div class="row">
                <div class="col-md-3"><strong>Actions</strong></div>
                <div class="col-md-8">
                    <a class="btn btn-default btn-sm" href="?cmd=accounts&action=edit&id={$details.id}&sync">Sync status</a>
                    <a class="btn btn-default btn-sm" href="?cmd=accounts&action=edit&id={$details.id}&renew"
                       onclick="return confirm('Send a renewal request for this SSL order to Nhan Hoa?');">Renew</a>
                </div>
            </div>
            <div class="row">
                <hr />
                <div class="col-md-12">
                    <p class="text-muted">
                        <strong>Note.</strong> The Nhan Hoa SSL API supports only
                        order placement, status sync and renewal. CSR submission,
                        domain-control validation (DCV), approver e-mail, reissue,
                        cancellation and certificate download are handled in the
                        <strong>Nhan Hoa customer portal</strong> (out of band from
                        this module).
                    </p>
                </div>
            </div>
        </div>
    </li>
</ul>
{literal}
    <style>
        .cert .sor .row { margin-bottom: 10px; }
    </style>
{/literal}
