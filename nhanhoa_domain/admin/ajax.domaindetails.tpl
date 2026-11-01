<div class="well" style="margin-bottom:12px;">
    <h4 style="margin:0 0 10px;">Chi tiết Nhân Hòa Domain</h4>

    <div style="display:flex; flex-wrap:wrap; gap:10px; margin-bottom:10px;">
        <div style="flex:1 1 220px; min-width:220px; padding:12px; border:1px solid #d8e7ff; border-left:4px solid #3a87ad; border-radius:8px; background:#f8fbff;">
            <div style="font-weight:700; margin-bottom:8px;">Thông tin quản lý tên miền</div>
            <div><strong>Domain:</strong> {$details_data.domain_info.domain|default:'-'|escape}</div>
            <div><strong>Login URL:</strong>
                {if $details_data.domain_info.login_url}
                    <a href="{$details_data.domain_info.login_url|escape}" target="_blank" rel="noopener noreferrer">{$details_data.domain_info.login_url|escape}</a>
                {else}
                    -
                {/if}
            </div>
        </div>

        <div style="flex:1 1 220px; min-width:220px; padding:12px; border:1px solid #dff0d8; border-left:4px solid #5cb85c; border-radius:8px; background:#f8fff8;">
            <div style="font-weight:700; margin-bottom:8px;">Trạng thái domain / hồ sơ</div>
            <div><strong>Tiến trình:</strong> {$details_data.processing_status.content.domainvn_cron_comment|default:'-'|escape}</div>
            <div><strong>Mã đơn:</strong> {$details_data.processing_status.ord_name|default:'-'|escape}</div>
            <div><strong>Trạng thái hồ sơ:</strong> {$details_data.declaration_status.declaration_status_text|default:'-'|escape}</div>
        </div>

        <div style="flex:1 1 220px; min-width:220px; padding:12px; border:1px solid #f3e7c7; border-left:4px solid #f0ad4e; border-radius:8px; background:#fffcf3;">
            <div style="font-weight:700; margin-bottom:8px;">eKYC Profile</div>
            <div><strong>Domain:</strong> {$details_data.ekyc_profile.domain_name|default:'-'|escape}</div>
            <div><strong>Loại domain:</strong>
                {if $details_data.ekyc_profile.is_vn_domain}
                    <span style="display:inline-block; padding:2px 8px; border-radius:10px; background:#d9edf7; color:#245269; font-weight:600;">Tên miền .vn</span>
                {else}
                    <span style="display:inline-block; padding:2px 8px; border-radius:10px; background:#eee; color:#555; font-weight:600;">Tên miền quốc tế</span>
                {/if}
            </div>
        </div>
    </div>

    <details>
        <summary style="cursor:pointer; font-weight:600;">Xem dữ liệu thô (JSON)</summary>
        <pre style="white-space:pre-wrap; max-height:320px; overflow:auto; margin-top:8px; padding:10px; border:1px solid #ddd; border-radius:6px; background:#fcfcfc;">{$details_json|escape}</pre>
    </details>
</div>
