{*
    Product → Connect with App form for Nhân Hòa Hosting.

    The plan (hostingCode) and template dropdowns are loadable: the controller
    prefills them server-side ($service_codes / $template_codes) and also answers
    the core make=loadoptions XHR via ajax.options.tpl.

    The current saved value is always kept as a selectable option so a product
    configured earlier never loses its selection if the API is briefly down.
*}
{if $product_error}
    <div class="alert alert-warning">
        <strong>Không nạp được danh sách từ Nhân Hòa:</strong> {$product_error|escape}
        <div class="small">Bạn vẫn có thể lưu — giá trị đã chọn được giữ nguyên.</div>
    </div>
{/if}

<div class="form-group">
    <label>Mã gói hosting (hostingCode)</label>
    <select name="options[hostingCode]" id="nh_hostingCode" class="form-control">
        {assign var=curcode value=$options.hostingCode.value}
        <option value="">— chọn gói —</option>
        {foreach from=$service_codes key=code item=label}
            <option value="{$code|escape}" {if $curcode == $code}selected="selected"{/if}>{$label|escape}</option>
        {/foreach}
        {if $curcode && !isset($service_codes[$curcode])}
            <option value="{$curcode|escape}" selected="selected">{$curcode|escape} (đã lưu)</option>
        {/if}
    </select>
    <p class="help-block">Lấy từ API <code>service_code</code> (group_id=1). Đây là mã gửi cho <code>register_hosting</code>.</p>
</div>

<div class="form-group">
    <label>Template hệ điều hành (tuỳ chọn)</label>
    <select name="options[os_template]" id="nh_os_template" class="form-control">
        {assign var=curtpl value=$options.os_template.value}
        <option value="">— không dùng —</option>
        {foreach from=$template_codes key=tid item=label}
            <option value="{$label|escape}" {if $curtpl == $label}selected="selected"{/if}>{$label|escape}</option>
        {/foreach}
        {if $curtpl && !in_array($curtpl, $template_codes)}
            <option value="{$curtpl|escape}" selected="selected">{$curtpl|escape} (đã lưu)</option>
        {/if}
    </select>
    <p class="help-block">Lấy từ API <code>template_code</code>. API này dành cho <strong>Cloud VPS</strong>; với hosting thường để trống.</p>
</div>

<div class="form-group">
    <label>Số tháng đăng ký</label>
    <input type="text" name="options[hosting_months]" class="form-control" value="{$options.hosting_months.value|escape}" />
    <p class="help-block">Nhân Hòa yêu cầu tối thiểu 6 (ví dụ 6, 12, 24, 36). Giá trị nhỏ hơn 6 sẽ tự nâng lên 6.</p>
</div>

<div class="form-group">
    <label>URL control panel hiện cho khách</label>
    <input type="text" name="options[control_panel_url]" class="form-control" value="{$options.control_panel_url.value|escape}" placeholder="https://cpanel.nhanhoa.com" />
    <p class="help-block">Nhân Hòa không có SSO qua API — đây chỉ là liên kết tĩnh hiện trong client area. Bỏ trống để ẩn.</p>
</div>
