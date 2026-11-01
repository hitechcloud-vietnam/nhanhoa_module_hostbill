<?php

/**
 * Nhan Hoa Domain — Eloquent model for mirrored registrant contacts.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

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
