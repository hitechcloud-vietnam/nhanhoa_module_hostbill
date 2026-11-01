<?php

/**
 * Client-area controller for the Nhan Hoa Cloud VPS module.
 *
 * Renders the service overview (status, expiry, IP) and handles the client VPS
 * control actions through GET params. Because the Nhan Hoa Cloud API has no
 * power/reinstall/console commands, the control actions surface a clear notice
 * and a deep link to the Nhan Hoa panel; the status action is fully live.
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
        $clientareadir = APPDIR_MODULES . $this->module->getModuleType() . DS
            . strtolower($this->module->getModuleName()) . DS . "templates" . DS . "clientarea" . DS;

        // Handle a control action if one was requested.
        $notice = null;
        if (isset($_GET["vpsaction"])) {
            $notice = $this->handleAction($_GET["vpsaction"], $_GET);
        }

        $summary = $this->module->statusSummary();

        $this->template->assign("summary", $summary);
        $this->template->assign("notice", $notice);
        $this->template->assign("panel_url", nhanhoa_cloud::PANEL_URL);
        $this->template->assign("clientareadir", $clientareadir);
        $this->template->render($clientareadir . "overview.tpl");
    }

    /** Dispatch a client control action to the module. */
    protected function handleAction($action, $get)
    {
        switch ($action) {
            case "start":
            case "stop":
            case "reboot":
                return $this->module->client_power($action);
            case "reinstall":
                return $this->module->client_reinstall(isset($get["os"]) ? $get["os"] : "");
            case "console":
                return $this->module->client_console();
            case "status":
            default:
                return $this->module->client_status();
        }
    }
}
