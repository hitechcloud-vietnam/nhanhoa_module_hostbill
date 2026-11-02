<ul class="accor white">
    <li>
        <a href="#">Chi tiết Nhân Hòa Domain</a>
        <div class="sor">
            <div class="row">
                <div class="col-md-3"><strong>Domain</strong></div>
                <div class="col-md-9">{$details_data.domain_info.domain|default:'-'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Login URL</strong></div>
                <div class="col-md-9">
                    {if $details_data.domain_info.login_url}
                        <a href="{$details_data.domain_info.login_url|escape}" target="_blank" rel="noopener noreferrer">{$details_data.domain_info.login_url|escape}</a>
                    {else}
                        -
                    {/if}
                </div>
            </div>
            <hr/>
            <div class="row">
                <div class="col-md-3"><strong>Tiến trình</strong></div>
                <div class="col-md-9">{$details_data.processing_status.content.domainvn_cron_comment|default:'-'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Mã đơn</strong></div>
                <div class="col-md-9">{$details_data.processing_status.ord_name|default:'-'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Trạng thái hồ sơ</strong></div>
                <div class="col-md-9">
                    {if $details_data.declaration_status.declaration_status_text}
                        <span class="label label-default">{$details_data.declaration_status.declaration_status_text|escape}</span>
                    {else}
                        -
                    {/if}
                </div>
            </div>
            <hr/>
            <div class="row">
                <div class="col-md-3"><strong>eKYC — Domain</strong></div>
                <div class="col-md-9">{$details_data.ekyc_profile.domain_name|default:'-'|escape}</div>
            </div>
            <div class="row">
                <div class="col-md-3"><strong>Loại domain</strong></div>
                <div class="col-md-9">
                    {if $details_data.ekyc_profile.is_vn_domain}
                        <span class="label label-info">Tên miền .vn</span>
                    {else}
                        <span class="label label-default">Tên miền quốc tế</span>
                    {/if}
                </div>
            </div>
        </div>
    </li>
    <li>
        <a href="#">Dữ liệu thô (JSON)</a>
        <div class="sor">
            <pre>{$details_json|escape}</pre>
        </div>
    </li>
</ul>
