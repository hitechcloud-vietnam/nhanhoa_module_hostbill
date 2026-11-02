<ul class="accor white">
    <li>
        <a href="#">Kết quả thao tác</a>
        <div class="sor">
            {if isset($message_success)}
                <div class="row">
                    <div class="col-md-12">
                        {if $message_success}
                            <div class="alert alert-success" style="margin:0;">Thao tác thành công.</div>
                        {else}
                            <div class="alert alert-danger" style="margin:0;">Thao tác thất bại.</div>
                        {/if}
                    </div>
                </div>
            {elseif $message_text}
                <div class="row">
                    <div class="col-md-3"><strong>{if $message_is_url}Liên kết{else}Kết quả{/if}</strong></div>
                    <div class="col-md-9">
                        {if $message_is_url}
                            <a href="{$message_text|escape}" target="_blank" rel="noopener noreferrer">{$message_text|escape}</a>
                        {else}
                            {$message_text|escape}
                        {/if}
                    </div>
                </div>
            {elseif $message_data}
                {if $message_data.domain_name}
                    <div class="row">
                        <div class="col-md-3"><strong>Domain</strong></div>
                        <div class="col-md-9">{$message_data.domain_name|escape}</div>
                    </div>
                {/if}
                {if $message_data.declaration_status_text}
                    <div class="row">
                        <div class="col-md-3"><strong>Trạng thái hồ sơ</strong></div>
                        <div class="col-md-9"><span class="label label-default">{$message_data.declaration_status_text|escape}</span></div>
                    </div>
                {/if}
                {if $message_data.ord_name}
                    <div class="row">
                        <div class="col-md-3"><strong>Mã đơn</strong></div>
                        <div class="col-md-9">{$message_data.ord_name|escape}</div>
                    </div>
                {/if}
                {if $message_data.ord_end_time}
                    <div class="row">
                        <div class="col-md-3"><strong>Hạn xử lý</strong></div>
                        <div class="col-md-9">{$message_data.ord_end_time|escape}</div>
                    </div>
                {/if}
            {else}
                <div class="row">
                    <div class="col-md-12"><span class="label label-default">Chưa có dữ liệu phản hồi.</span></div>
                </div>
            {/if}
        </div>
    </li>
    <li>
        <a href="#">Dữ liệu thô</a>
        <div class="sor">
            <pre>{$message|escape}</pre>
        </div>
    </li>
</ul>
