<ul class="accor white">
    {if $status_data.processing_status}
        <li>
            <a href="#">Trạng thái xử lý</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-3"><strong>Mã đơn</strong></div>
                    <div class="col-md-9">{$status_data.processing_status.ord_name|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Order ID</strong></div>
                    <div class="col-md-9">{$status_data.processing_status.ord_id|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Hạn xử lý</strong></div>
                    <div class="col-md-9">{$status_data.processing_status.ord_end_time|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Tiến trình</strong></div>
                    <div class="col-md-9"><span class="label label-info">{$status_data.processing_status.content.domainvn_cron_comment|default:'-'|escape}</span></div>
                </div>
            </div>
        </li>
    {/if}

    <li>
        <a href="#">Trạng thái hồ sơ</a>
        <div class="sor">
            {if $status_data.declaration_status}
                <div class="row">
                    <div class="col-md-3"><strong>Domain</strong></div>
                    <div class="col-md-9">{$status_data.declaration_status.domain_name|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Mã trạng thái</strong></div>
                    <div class="col-md-9">{$status_data.declaration_status.declaration_status|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Diễn giải</strong></div>
                    <div class="col-md-9">
                        {if $status_data.declaration_status.declaration_status_text}
                            <span class="label label-default">{$status_data.declaration_status.declaration_status_text|escape}</span>
                        {else}
                            -
                        {/if}
                    </div>
                </div>
            {else}
                <div class="row">
                    <div class="col-md-12"><span class="label label-warning">Chưa lấy được trạng thái hồ sơ từ API.</span></div>
                </div>
            {/if}
        </div>
    </li>

    <li>
        <a href="#">Dữ liệu thô (JSON)</a>
        <div class="sor">
            <pre>{$status_json|escape}</pre>
        </div>
    </li>
</ul>
