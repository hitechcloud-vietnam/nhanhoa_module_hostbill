<div class="well" style="margin-bottom:12px;">
    <h4 style="margin:0 0 10px;">Kết quả thao tác</h4>

    {if isset($message_success)}
        <div style="padding:10px 12px; border-radius:8px; {if $message_success}background:#eaf9ea; border:1px solid #8ed08e; color:#1f6b1f;{else}background:#fff2f2; border:1px solid #ef9a9a; color:#8b1f1f;{/if}">
            {if $message_success}Thao tác thành công.{else}Thao tác thất bại.{/if}
        </div>
    {elseif $message_text}
        <div style="padding:10px 12px; border:1px solid #ddd; border-left:4px solid #3a87ad; border-radius:8px; background:#fafafa;">
            {if $message_is_url}
                <strong>Liên kết:</strong>
                <a href="{$message_text|escape}" target="_blank" rel="noopener noreferrer">{$message_text|escape}</a>
            {else}
                {$message_text|escape}
            {/if}
        </div>
    {elseif $message_data}
        <div style="padding:10px 12px; border:1px solid #ddd; border-left:4px solid #5cb85c; border-radius:8px; background:#fafafa;">
            {if $message_data.domain_name}<div><strong>Domain:</strong> {$message_data.domain_name|escape}</div>{/if}
            {if $message_data.declaration_status_text}<div><strong>Trạng thái hồ sơ:</strong> {$message_data.declaration_status_text|escape}</div>{/if}
            {if $message_data.ord_name}<div><strong>Mã đơn:</strong> {$message_data.ord_name|escape}</div>{/if}
            {if $message_data.ord_end_time}<div><strong>Hạn xử lý:</strong> {$message_data.ord_end_time|escape}</div>{/if}
        </div>
    {else}
        <div style="padding:10px 12px; border:1px solid #eee; border-radius:6px; background:#fcfcfc; color:#666;">
            Chưa có dữ liệu phản hồi.
        </div>
    {/if}

    <details>
        <summary style="cursor:pointer; font-weight:600;">Xem dữ liệu thô</summary>
        <pre style="white-space:pre-wrap; max-height:320px; overflow:auto; margin-top:8px; padding:10px; border:1px solid #ddd; border-radius:6px; background:#fcfcfc;">{$message|escape}</pre>
    </details>
</div>
