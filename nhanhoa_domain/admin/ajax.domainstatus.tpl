<div class="well" style="margin-bottom:12px;">
    <h4 style="margin:0 0 10px;">Trạng thái domain / hồ sơ</h4>

    {if $status_data.processing_status}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #d8e7ff; border-left:4px solid #3a87ad; border-radius:8px; background:#f8fbff;">
            <h5 style="margin:0 0 8px;">Trạng thái xử lý</h5>
            <div><strong>Mã đơn:</strong> {$status_data.processing_status.ord_name|default:'-'|escape}</div>
            <div><strong>Order ID:</strong> {$status_data.processing_status.ord_id|default:'-'|escape}</div>
            <div><strong>Hạn xử lý:</strong> {$status_data.processing_status.ord_end_time|default:'-'|escape}</div>
            <div><strong>Tiến trình:</strong>
                <span style="display:inline-block; margin-left:4px; padding:2px 8px; border-radius:10px; background:#d9edf7; color:#245269; font-weight:600;">
                    {$status_data.processing_status.content.domainvn_cron_comment|default:'-'|escape}
                </span>
            </div>
        </div>
    {/if}

    {if $status_data.declaration_status}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #dff0d8; border-left:4px solid #5cb85c; border-radius:8px; background:#f8fff8;">
            <h5 style="margin:0 0 8px;">Trạng thái hồ sơ</h5>
            <div><strong>Domain:</strong> {$status_data.declaration_status.domain_name|default:'-'|escape}</div>
            <div><strong>Mã trạng thái:</strong> {$status_data.declaration_status.declaration_status|default:'-'|escape}</div>
            <div><strong>Diễn giải:</strong> {$status_data.declaration_status.declaration_status_text|default:'-'|escape}</div>
        </div>
    {else}
        <div style="margin-bottom:10px; padding:10px; border:1px solid #eee; border-radius:6px; background:#fcfcfc; color:#666;">
            Chưa lấy được trạng thái hồ sơ từ API.
        </div>
    {/if}

    <details>
        <summary style="cursor:pointer; font-weight:600;">Xem dữ liệu thô (JSON)</summary>
        <pre style="white-space:pre-wrap; max-height:320px; overflow:auto; margin-top:8px; padding:10px; border:1px solid #ddd; border-radius:6px; background:#fcfcfc;">{$status_json|escape}</pre>
    </details>
</div>
