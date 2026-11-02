<?php

/**
 * Nhan Hoa ZoneDNS — admin-area controller.
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
 * Admin-side controller for the nhanhoa_dns DNS module. Mirrors the cloudns
 * admin controller: it connects the module to the configured app (server)
 * before each call and wires the product/app configuration templates plus the
 * shared DNS Templates tab. ZoneDNS has no whitelabel / subscription / premium
 * surface, so no extra admin tabs are added.
 */
require_once APPDIR_LIBS . "dnsfunctions" . DS . "class.dnstemplates_controller.php";
#[\AllowDynamicProperties]
class nhanhoa_dns_controller extends DNSTemplates_Controller
{
    /**
     * @var nhanhoa_dns
     */
    public $module;

    public function beforeCall($params)
    {
        if (isset($params["account"]["server_id"])) {
            $s = HBLoader::LoadModel("Servers");
            $this->module->connect($s->getServerDetails($params["account"]["server_id"]));
        }
        parent::beforeCall($params);
    }

    public function appdetails($params)
    {
        parent::productdetails($params);
        $this->template->assign("custom_template", APPDIR_MODULES . $this->module->getModuleType() . DS . strtolower($this->module->getModuleName()) . DS . "templates" . DS . "appconfig.tpl");
        $this->template->assign("moduleurl", Utilities::checkSecureURL(HBConfig::getConfig("InstallURL") . "includes/modules/Hosting/nhanhoa_dns/admin/"));
        return true;
    }

    public function productdetails($params)
    {
        $s = HBLoader::LoadModel("Servers");
        if (isset($params["server_id"]) && is_numeric($params["server_id"])) {
            $this->module->connect($s->getServerDetails($params["server_id"]));
        } else {
            if (!empty($params["server"]) && is_array($params["server"])) {
                foreach ($params["server"] as $server) {
                    if (is_numeric($server)) {
                        $this->module->connect($s->getServerDetails($server));
                    }
                }
            }
        }
        parent::productdetails($params);
        $this->template->assign("customconfig", APPDIR_MODULES . $this->module->getModuleType() . DS . strtolower($this->module->getModuleName()) . DS . "templates" . DS . "productconfig.tpl");
        $this->template->assign("moduleurl", Utilities::checkSecureURL(HBConfig::getConfig("InstallURL") . "includes/modules/Hosting/nhanhoa_dns/admin/"));
        $this->template->assign("extra_tabs", ["DNS Templates" => MAINDIR . "includes/types/_common/dns_templates.tpl"]);
        return true;
    }
}

?>
