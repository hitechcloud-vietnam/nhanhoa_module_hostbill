<link href="{$moduleurl}styles.css" rel="stylesheet" media="all" />
<table border="0" cellpadding="6" cellspacing="0" width="100%" style="margin-bottom:10px;">
    {if $server_fields.display.username}<tr><td align="right" width="165"><strong>{if $server_fields.description.username}{$server_fields.description.username}{else}{$lang.Username}{/if}</strong></td><td><input name="username" size="25" value="{$server.username}" class="inp"/> <a class="vtip_description" title="Nhan Hoa API username (auth-user). From customer.nhanhoa.com -> Dich vu -> Tai khoan API -> Cau hinh chi tiet."></a></td></tr>{/if}
    {if $server_fields.display.password}<tr><td align="right" width="165"><strong>{if $server_fields.description.password}{$server_fields.description.password}{else}{$lang.Password}{/if}</strong></td><td><input type="password" name="password" size="25" class="inp" value="{$server.password}" autocomplete="off"/> <a class="vtip_description" title="Nhan Hoa API password (auth-pwd)."></a></td></tr>{/if}
</table>
<div class="sectionhead">Nhan Hoa ZoneDNS Settings</div>
<table border="0" cellpadding="6" cellspacing="0" width="100%" style="margin-bottom:10px;">
    <tr>
        <td align="right" width="165"><strong>Reseller ID (ID API)</strong> <a class="vtip_description" title="Nhan Hoa reseller account ID API (auth-id). Provided by Nhan Hoa when you commit to API usage."></a></td>
        <td><input type="text" name="custom[auth_id]" size="25" value="{$server.custom.auth_id}" class="inp"/></td>
    </tr>
    <tr>
        <td align="right" width="165"><strong>Default Nameservers</strong> <a class="vtip_description" title="Comma/space-separated nameservers reported as the zone default (optional). Leave blank to use the Nhan Hoa platform fallback."></a></td>
        <td><input type="text" name="custom[default_ns]" size="40" value="{$server.custom.default_ns}" class="inp"/></td>
    </tr>
    <tr>
        <td align="right" width="165"><strong>Debug Log</strong> <a class="vtip_description" title="Write every API request/response to includes/modules/Hosting/nhanhoa_dns/nhanhoa_dns.log (passwords masked)."></a></td>
        <td><input type="checkbox" name="custom[debug_log]" id="debug_log" value="1" {if $server.custom.debug_log=='1'}checked="checked"{/if}/> Log API requests/responses</td>
    </tr>
</table>
