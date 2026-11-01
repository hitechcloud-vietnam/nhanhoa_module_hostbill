<div class="well" style="margin-bottom:12px;">
    <h4 style="margin:0 0 10px;">Contact local / remote sync</h4>

    {if $contacts_data.registrant}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #dff0d8; border-left:4px solid #5cb85c; border-radius:8px; background:#f8fff8;">
            <h5 style="margin:0 0 8px;">Registrant</h5>
            <div><strong>Tên:</strong> {$contacts_data.registrant.companyname|default:$contacts_data.registrant.firstname|default:'-'|escape}</div>
            <div><strong>Email:</strong> {$contacts_data.registrant.email|default:$contacts_data.registrant.mail|default:'-'|escape}</div>
            <div><strong>Điện thoại:</strong> {$contacts_data.registrant.phonenumber|default:'-'|escape}</div>
            <div><strong>Địa chỉ:</strong> {$contacts_data.registrant.address1|default:'-'|escape}</div>
            <div><strong>Handle:</strong> {$contacts_data.registrant.handle|default:'-'|escape}</div>
        </div>
    {/if}

    {if $contacts_data.admin}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #d8e7ff; border-left:4px solid #3a87ad; border-radius:8px; background:#f8fbff;">
            <h5 style="margin:0 0 8px;">Admin</h5>
            <div><strong>Tên:</strong> {$contacts_data.admin.companyname|default:$contacts_data.admin.firstname|default:'-'|escape}</div>
            <div><strong>Email:</strong> {$contacts_data.admin.email|default:$contacts_data.admin.mail|default:'-'|escape}</div>
            <div><strong>Điện thoại:</strong> {$contacts_data.admin.phonenumber|default:'-'|escape}</div>
            <div><strong>Địa chỉ:</strong> {$contacts_data.admin.address1|default:'-'|escape}</div>
            <div><strong>Handle:</strong> {$contacts_data.admin.handle|default:'-'|escape}</div>
        </div>
    {/if}

    {if $contacts_data.tech}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #f3e7c7; border-left:4px solid #f0ad4e; border-radius:8px; background:#fffcf3;">
            <h5 style="margin:0 0 8px;">Tech</h5>
            <div><strong>Tên:</strong> {$contacts_data.tech.companyname|default:$contacts_data.tech.firstname|default:'-'|escape}</div>
            <div><strong>Email:</strong> {$contacts_data.tech.email|default:$contacts_data.tech.mail|default:'-'|escape}</div>
            <div><strong>Điện thoại:</strong> {$contacts_data.tech.phonenumber|default:'-'|escape}</div>
            <div><strong>Địa chỉ:</strong> {$contacts_data.tech.address1|default:'-'|escape}</div>
            <div><strong>Handle:</strong> {$contacts_data.tech.handle|default:'-'|escape}</div>
        </div>
    {/if}

    {if $contacts_data.billing}
        <div style="margin-bottom:10px; padding:12px; border:1px solid #eedbf2; border-left:4px solid #a86eb9; border-radius:8px; background:#fcf7ff;">
            <h5 style="margin:0 0 8px;">Billing</h5>
            <div><strong>Tên:</strong> {$contacts_data.billing.companyname|default:$contacts_data.billing.firstname|default:'-'|escape}</div>
            <div><strong>Email:</strong> {$contacts_data.billing.email|default:$contacts_data.billing.mail|default:'-'|escape}</div>
            <div><strong>Điện thoại:</strong> {$contacts_data.billing.phonenumber|default:'-'|escape}</div>
            <div><strong>Địa chỉ:</strong> {$contacts_data.billing.address1|default:'-'|escape}</div>
            <div><strong>Handle:</strong> {$contacts_data.billing.handle|default:'-'|escape}</div>
        </div>
    {/if}

    <details>
        <summary style="cursor:pointer; font-weight:600;">Xem dữ liệu thô (JSON)</summary>
        <pre style="white-space:pre-wrap; max-height:320px; overflow:auto; margin-top:8px; padding:10px; border:1px solid #ddd; border-radius:6px; background:#fcfcfc;">{$contacts_json|escape}</pre>
    </details>
</div>
