<div style="margin:10px 0;">
    <ul class="accor">
        <li>
            <a>Nhân Hòa Domain Actions</a>
            <div style="padding:8px 0 2px;">
                <div style="margin-bottom:10px;">
                    <div style="font-weight:700; margin-bottom:6px; color:#555;">Tổng quan</div>
                    <a href="#" onclick="return loadNhanhoaDetails('{$domainid}')" class="btn btn-primary btn-sm">Chi tiết domain</a>
                    <a href="#" onclick="return loadNhanhoaContacts('{$domainid}')" class="btn btn-primary btn-sm">Hồ sơ contact</a>
                    <a href="#" onclick="return loadNhanhoaStatus('{$domainid}')" class="btn btn-info btn-sm">Trạng thái hồ sơ</a>
                </div>

                <div style="margin-bottom:10px;">
                    <div style="font-weight:700; margin-bottom:6px; color:#555;">Hồ sơ .vn / eKYC</div>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'GetEkyc')" class="btn btn-success btn-sm">Lấy link eKYC</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'GetEkycProfile')" class="btn btn-success btn-sm">eKYC profile</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'Contract')" class="btn btn-warning btn-sm">Link ký số</a>
                </div>

                <div style="margin-bottom:10px;">
                    <div style="font-weight:700; margin-bottom:6px; color:#555;">Đồng bộ / kiểm tra</div>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'ProcessingStatus')" class="btn btn-info btn-sm">Trạng thái xử lý</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'DeclarationStatus')" class="btn btn-info btn-sm">Trạng thái hồ sơ</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'RefreshContacts')" class="btn btn-primary btn-sm">Đồng bộ contact SQL</a>
                </div>

                <div style="margin-bottom:10px;">
                    <div style="font-weight:700; margin-bottom:6px; color:#555;">ZoneDNS / tiện ích</div>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'DnsRecords')" class="btn btn-primary btn-sm">Danh sách DNS record</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'DomainInfo')" class="btn btn-warning btn-sm">Thông tin quản lý ZoneDNS</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'BackorderStatus')" class="btn btn-info btn-sm">Trạng thái backorder</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'UnsignedDomains')" class="btn btn-default btn-sm">TM thiếu bản khai</a>
                </div>

                <div style="margin-bottom:6px;">
                    <div style="font-weight:700; margin-bottom:6px; color:#555;">Hành động khác</div>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'Unlock24h')" class="btn btn-danger btn-sm">Unlock 24h</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'ResendVerify')" class="btn btn-default btn-sm">Resend verify</a>
                    <a href="#" onclick="return loadNhanhoaAction('{$domainid}', 'SkipVerifyEmail')" class="btn btn-default btn-sm">Skip verify</a>
                </div>
            </div>
            <div class="row" style="margin-top:10px;">
                <div class="col-md-6"><div id="nh-domain-primary"></div></div>
                <div class="col-md-6"><div id="nh-domain-secondary"></div></div>
            </div>
        </li>
    </ul>
</div>

{literal}
<script>
function loadNhanhoaDetails(id) {
    ajax_update('?cmd=nhanhoa_domain&action=domaindetails&id=' + id, { load: 1 }, '#nh-domain-primary', true);
    return false;
}
function loadNhanhoaContacts(id) {
    ajax_update('?cmd=nhanhoa_domain&action=domaincontacts&id=' + id, { load: 1 }, '#nh-domain-primary', true);
    return false;
}
function loadNhanhoaStatus(id) {
    ajax_update('?cmd=nhanhoa_domain&action=domainstatus&id=' + id, { load: 1 }, '#nh-domain-secondary', true);
    return false;
}
function loadNhanhoaAction(id, action) {
    ajax_update('?cmd=nhanhoa_domain&action=domainaction&ac=' + action + '&id=' + id, { load: 1 }, '#nh-domain-secondary', true);
    return false;
}
</script>
{/literal}
