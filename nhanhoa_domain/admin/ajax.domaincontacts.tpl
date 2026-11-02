<ul class="accor white">
    {if $contacts_data.registrant}
        <li>
            <a href="#">Registrant</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-3"><strong>Tên</strong></div>
                    <div class="col-md-9">{$contacts_data.registrant.companyname|default:$contacts_data.registrant.firstname|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Email</strong></div>
                    <div class="col-md-9">{$contacts_data.registrant.email|default:$contacts_data.registrant.mail|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Điện thoại</strong></div>
                    <div class="col-md-9">{$contacts_data.registrant.phonenumber|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Địa chỉ</strong></div>
                    <div class="col-md-9">{$contacts_data.registrant.address1|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Handle</strong></div>
                    <div class="col-md-9">{$contacts_data.registrant.handle|default:'-'|escape}</div>
                </div>
            </div>
        </li>
    {/if}

    {if $contacts_data.admin}
        <li>
            <a href="#">Admin</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-3"><strong>Tên</strong></div>
                    <div class="col-md-9">{$contacts_data.admin.companyname|default:$contacts_data.admin.firstname|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Email</strong></div>
                    <div class="col-md-9">{$contacts_data.admin.email|default:$contacts_data.admin.mail|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Điện thoại</strong></div>
                    <div class="col-md-9">{$contacts_data.admin.phonenumber|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Địa chỉ</strong></div>
                    <div class="col-md-9">{$contacts_data.admin.address1|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Handle</strong></div>
                    <div class="col-md-9">{$contacts_data.admin.handle|default:'-'|escape}</div>
                </div>
            </div>
        </li>
    {/if}

    {if $contacts_data.tech}
        <li>
            <a href="#">Tech</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-3"><strong>Tên</strong></div>
                    <div class="col-md-9">{$contacts_data.tech.companyname|default:$contacts_data.tech.firstname|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Email</strong></div>
                    <div class="col-md-9">{$contacts_data.tech.email|default:$contacts_data.tech.mail|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Điện thoại</strong></div>
                    <div class="col-md-9">{$contacts_data.tech.phonenumber|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Địa chỉ</strong></div>
                    <div class="col-md-9">{$contacts_data.tech.address1|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Handle</strong></div>
                    <div class="col-md-9">{$contacts_data.tech.handle|default:'-'|escape}</div>
                </div>
            </div>
        </li>
    {/if}

    {if $contacts_data.billing}
        <li>
            <a href="#">Billing</a>
            <div class="sor">
                <div class="row">
                    <div class="col-md-3"><strong>Tên</strong></div>
                    <div class="col-md-9">{$contacts_data.billing.companyname|default:$contacts_data.billing.firstname|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Email</strong></div>
                    <div class="col-md-9">{$contacts_data.billing.email|default:$contacts_data.billing.mail|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Điện thoại</strong></div>
                    <div class="col-md-9">{$contacts_data.billing.phonenumber|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Địa chỉ</strong></div>
                    <div class="col-md-9">{$contacts_data.billing.address1|default:'-'|escape}</div>
                </div>
                <div class="row">
                    <div class="col-md-3"><strong>Handle</strong></div>
                    <div class="col-md-9">{$contacts_data.billing.handle|default:'-'|escape}</div>
                </div>
            </div>
        </li>
    {/if}

    <li>
        <a href="#">Dữ liệu thô (JSON)</a>
        <div class="sor">
            <pre>{$contacts_json|escape}</pre>
        </div>
    </li>
</ul>
