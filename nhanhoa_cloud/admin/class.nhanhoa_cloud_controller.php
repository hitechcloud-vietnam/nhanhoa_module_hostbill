<?php

/**
 * Admin-area controller for the Nhan Hoa Cloud VPS module.
 *
 * Renders the service detail panel in the admin account page and handles the
 * in-panel actions (sync status, renew) dispatched through the `vpsdo` param,
 * mirroring the ovhdedicated admin controller pattern.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.2026-11-02
 */

#[\AllowDynamicProperties]
class nhanhoa_cloud_Controller extends HBController
{
    /** @var nhanhoa_cloud */
    public $module;

    public function accountdetails($params)
    {
        $adminarea = APPDIR_MODULES . $this->module->getModuleType() . DS
            . strtolower($this->module->getModuleName()) . DS . "admin" . DS;

        $typetemplates = [];
        $typetemplates["adminaccounts"]["details"]["replace"] = $adminarea . "account.tpl";
        $this->adminareatpl = $typetemplates["adminaccounts"]["details"]["replace"];
        $this->template->assign("typetemplates", $typetemplates);
        $this->template->assign("moduletpldir", $adminarea);
        $this->template->assign("nh_admin_css", $this->adminCssUrl());

        if (isset($params["vpsdo"]) && is_callable([$this, "vpsdo_" . $params["vpsdo"]])) {
            $this->setupModule($params["id"]);
            $this->{"vpsdo_" . $params["vpsdo"]}($params);
            $this->template->assign("vpsdo", $params["vpsdo"]);
        } else {
            // Default view: show whatever identifiers are stored, no API call.
            $this->setupModule($params["id"]);
            $this->template->assign("summary", $this->module->statusSummary());
        }
    }

    /** Pull a fresh status from Nhan Hoa and re-render the panel. */
    public function vpsdo_sync($params)
    {
        $this->module->sync();
        $this->template->assign("summary", $this->module->statusSummary());
        $this->template->showtpl = $this->adminareatpl;
    }

    /** Renew the order at Nhan Hoa. */
    public function vpsdo_renew($params)
    {
        $months = isset($params["months"]) ? (int) $params["months"] : null;
        $this->module->renew($months);
        $this->template->assign("summary", $this->module->statusSummary());
        $this->template->showtpl = $this->adminareatpl;
    }

    /** Product configuration tab (loadable plan/template selects). */
    public function productdetails($params)
    {
        $adminarea = APPDIR_MODULES . $this->module->getModuleType() . DS
            . strtolower($this->module->getModuleName()) . DS . "admin" . DS;
        $this->template->assign("customconfig", $adminarea . "productconfig.tpl");
        $this->template->assign("module_templates", $adminarea);
        $this->template->assign("modulename", strtolower($this->module->getModuleName()));
        $this->template->assign("nh_admin_css", $this->adminCssUrl());
    }

    /** Absolute URL to this module's admin stylesheet (WebNIC re-skin). */
    protected function adminCssUrl()
    {
        return Utilities::checkSecureURL(
            HBConfig::getConfig("InstallURL")
            . "includes/modules/" . $this->module->getModuleType() . "/"
            . strtolower($this->module->getModuleName()) . "/admin/styles.css"
        );
    }

    /** Connect the module to the server + account behind a service id. */
    protected function setupModule($service)
    {
        $a = HBLoader::LoadModel("Accounts");
        $data = $a->getAccount($service);
        $account_config = $a->getAccountModuleConfig($service);
        $s = HBLoader::LoadModel("Servers");
        $servdata = $s->getServerDetails($data["server_id"]);
        $this->module->connect($servdata);
        $this->module->setAccountConfig($account_config);
        $this->module->setAccount($data);
        if (!empty($data["client_id"])) {
            $this->module->setClientDetails($data["client_id"]);
        }
    }
}
