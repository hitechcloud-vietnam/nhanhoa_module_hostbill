{*
    Product Connection tab for Nhan Hoa Email Hosting. WebNIC accordion style.

    Lists the plan codes currently available from the reseller API so the admin can
    see which codes are valid for the "Mã gói Email Hosting" option.

    Variables (unchanged): $product_error, $nh_codes.
*}
{if $nh_admin_css}<link rel="stylesheet" href="{$nh_admin_css|escape}" />{/if}

{if $product_error}
    <div class="alert alert-warning">
        <strong>Không đọc được danh sách gói:</strong> {$product_error|escape}
        <div class="small">Vẫn có thể nhập tay mã gói ở ô "Mã gói (nhập tay)".</div>
    </div>
{/if}

<ul class="accor white nhanhoa-email nhanhoa-email-panel">
    <li>
        <a href="#">Mã gói Email Hosting (serviceCode)</a>
        <div class="sor">
            {if $nh_codes}
                <table class="table table-striped table-condensed">
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
            {else}
                <p class="text-muted">Chưa có danh sách mã gói — chọn server rồi lưu để nạp từ API.</p>
            {/if}

            <p class="text-muted small" style="margin:0">
                Một dịch vụ = một tên miền. Khi cấp dịch vụ, module gọi <code>register_email</code> với
                <code>serviceCode</code>, tên miền của dịch vụ và số tháng (tối thiểu 6), rồi gọi
                <code>get_infoemail</code> để lấy <code>ord_id</code>. API đại lý không có lệnh tạm ngưng,
                huỷ, đổi gói hay đổi mật khẩu — những việc đó làm tại customer.nhanhoa.com.
            </p>
        </div>
    </li>
</ul>
