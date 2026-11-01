<?php

namespace Domain\nhanhoa_domain\ORM;

use Illuminate\Database\Eloquent\Model as ORM_Model;

/**
 * Registrant/admin/tech/billing contacts mirrored from Nhan Hoa.
 *
 * Deliberately the same table nhanhoav2 writes, so a domain moved from v2 to
 * v3 keeps its contact history instead of re-syncing from the registrar.
 */
class ORM_domcontacts extends ORM_Model
{
    protected $table = 'hb_domain_contacts_nhanhoa';
    protected $guarded = [];
}
