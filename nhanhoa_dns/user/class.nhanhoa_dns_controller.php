<?php

/**
 * Nhan Hoa ZoneDNS — client-area controller.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */
/**
 * HostBill Module Component
 * Developed & Optimized for PHP 8.1 - 8.5 compatibility
 *
 * Client-side controller for the nhanhoa_dns DNS module. Mirrors the cloudns
 * user controller: it inherits the generic DNS client area (zone list, record
 * table, add/edit/delete) from DNSClient_Controller. ZoneDNS has no extra
 * client-facing surface (no forwarding / premium), so nothing is added here.
 */
require_once APPDIR_LIBS . "dnsfunctions" . DS . "class.dnscontroller.php";
#[\AllowDynamicProperties]
class nhanhoa_dns_controller extends DNSClient_Controller
{
    /**
     * @var nhanhoa_dns
     */
    public $module;

    public function beforeCall($params)
    {
        $this->domain_indent = DnsManage::COLUMN_RELATED_ID;
        parent::beforeCall($params);
    }

    public function accountdetails($params)
    {
        parent::accountdetails($params);
    }
}

?>
