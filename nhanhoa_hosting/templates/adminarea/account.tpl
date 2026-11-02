{*
    Module block on the Nhân Hòa Hosting service page (admin).
    WebNIC visual language: accordion + grid rows + status label + btn-sm actions.

    Order id / name / status / expiry are read live from Nhan Hoa each time the
    page opens. The reseller API has no suspend/terminate/change-plan command, so
    those are not offered here — only Sync and Renew.

    Variables (unchanged): $nh_domain, $nh_code, $nh_order_id, $nh_info,
    $nh_balance, $nh_months, $nh_cp_url, $module_error, $id, {$securitytoken}.
*}
{assign var=abase value="?cmd=accounts&action=details&id=`$id`"}

{if $nh_admin_css}<link rel="stylesheet" href="{$nh_admin_css|escape}" />{/if}

{if $module_error}
    <div class="alert alert-danger">
        {foreach from=$module_error item=err}<div>{$err|escape}</div>{/foreach}
    </div>
{/if}

{assign var=nhstatus value=$nh_info.status|default:''}
{if $nhstatus eq ''}
    {assign var=nhlabel value='label-default'}
{elseif stripos($nhstatus,'active')!==false or stripos($nhstatus,'hoạt')!==false or stripos($nhstatus,'success')!==false or stripos($nhstatus,'ok')!==false}
    {assign var=nhlabel value='label-success'}
{elseif stripos($nhstatus,'pending')!==false or stripos($nhstatus,'process')!==false or stripos($nhstatus,'chờ')!==false or stripos($nhstatus,'wait')!==false}
    {assign var=nhlabel value='label-warning'}
{elseif stripos($nhstatus,'expire')!==false or stripos($nhstatus,'hết')!==false or stripos($nhstatus,'suspend')!==false or stripos($nhstatus,'cancel')!==false or stripos($nhstatus,'huỷ')!==false}
    {assign var=nhlabel value='label-danger'}
{else}
    {assign var=nhlabel value='label-default'}
{/if}

<ul class="accor white nhanhoa-hosting nhanhoa-hosting-panel">
    <li>
        <a href="#">Nhân Hòa Hosting</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Tên miền</strong></div>
                <div class="col-md-9">{$nh_domain|default:'—'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã gói (hostingCode)</strong></div>
                <div class="col-md-9"><code>{$nh_code|default:'—'|escape}</code></div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã đơn hàng (ord_id)</strong></div>
                <div class="col-md-9">{if $nh_order_id}<code>{$nh_order_id|escape}</code>{else}<span class="text-muted">chưa có — bấm Đồng bộ</span>{/if}</div>
            </div>
            {if $nh_info}
                <div class="row">
                    <div class="col-md-3"><strong>Tên đơn</strong></div>
                    <div class="col-md-9">{$nh_info.ord_name|default:'—'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Trạng thái</strong></div>
                    <div class="col-md-9">
                        {if $nh_info.content}{$nh_info.content|escape} {/if}
                        <span class="label {$nhlabel}">{$nh_info.status|default:'N/A'|escape}</span>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Hết hạn</strong></div>
                    <div class="col-md-9">{if $nh_info.ord_end_time}{$nh_info.ord_end_time|date_format:"%Y-%m-%d"}{else}—{/if}</div>
                </div>
            {/if}
            <div class="row">
                <div class="col-md-3"><strong>Số dư đại lý</strong></div>
                <div class="col-md-9">{if $nh_balance !== null}{$nh_balance|number_format} đ{else}<span class="text-muted">—</span>{/if}</div>
            </div>
            {if $nh_cp_url}
                <div class="row">
                    <div class="col-md-3"><strong>Control panel</strong></div>
                    <div class="col-md-9"><a href="{$nh_cp_url|escape}" target="_blank" rel="noopener">{$nh_cp_url|escape}</a></div>
                </div>
            {/if}

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
                        <input type="text" name="months" class="form-control input-sm" size="3" value="{$nh_months}" title="Số tháng (≥6)" />
                        <button class="btn btn-primary btn-sm" type="submit">Gia hạn</button>
                    </form>
                    {if $nh_cp_url}
                        <a class="btn btn-default btn-sm" href="{$nh_cp_url|escape}" target="_blank" rel="noopener">Mở control panel</a>
                    {/if}
                </div>
            </div>

            <hr />
            <div class="row">
                <div class="col-md-12">
                    <p class="help-block" style="margin:0">
                        API đại lý Nhân Hòa không hỗ trợ tạm ngưng / huỷ / đổi gói tự động.
                        Những thao tác đó thực hiện tại
                        <a href="https://customer.nhanhoa.com" target="_blank" rel="noopener">customer.nhanhoa.com</a>.
                    </p>
                </div>
            </div>
        </div>
    </li>
</ul>
