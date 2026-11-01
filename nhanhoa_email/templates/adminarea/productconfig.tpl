{*
    Product Connection tab for Nhan Hoa Email Hosting.

    Lists the plan codes currently available from the reseller API so the admin can
    see which codes are valid for the "Mã gói Email Hosting" option.
*}
{if $product_error}
    <div class="alert alert-warning">
        <strong>Không đọc được danh sách gói:</strong> {$product_error|escape}
        <div class="small">Vẫn có thể nhập tay mã gói ở ô "Mã gói (nhập tay)".</div>
    </div>
{/if}

{if $nh_codes}
<table class="table table-condensed">
    <thead><tr><th style="width:40%">Mã gói (serviceCode)</th><th>Mô tả</th></tr></thead>
    <tbody>
    {foreach from=$nh_codes item=c}
        <tr>
            <td><code>{$c.service_code|escape}</code></td>
            <td>{if $c.service_content}{$c.service_content}{else}<span class="text-muted">—</span>{/if}</td>
        </tr>
    {/foreach}
    </tbody>
</table>
{/if}

<p class="text-muted small">
    Một dịch vụ = một tên miền. Khi cấp dịch vụ, module gọi <code>register_email</code> với
    <code>serviceCode</code>, tên miền của dịch vụ và số tháng (tối thiểu 6), rồi gọi
    <code>get_infoemail</code> để lấy <code>ord_id</code>. API đại lý không có lệnh tạm ngưng,
    huỷ, đổi gói hay đổi mật khẩu — những việc đó làm tại customer.nhanhoa.com.
</p>
