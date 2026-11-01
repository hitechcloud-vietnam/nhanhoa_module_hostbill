<?php

/**
 * Client area for nhanhoa_email.
 *
 * The customer needs four things, and this answers all four on the first screen:
 *   1. the domain their mailboxes live under
 *   2. the plan (and what it includes — mailbox count etc., from the plan text)
 *   3. when the service expires
 *   4. where to manage mailboxes (the Nhan Hoa mail panel)
 *
 * Everything here is read-only: the reseller API gives the customer nothing to
 * write (no mailbox management, no suspend). Mailbox administration happens in the
 * Nhan Hoa control panel, linked from this page.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

class nhanhoa_email_controller extends HBController
{
    /** @var nhanhoa_email */
    public $module = null;

    /** @var Smarty */
    public $template = null;

    /** @var UserAuthorization */
    public $authorization = null;

    protected $section = 'details';

    protected $clientareapath = '';

    protected $account = [];

    public function accountdetails($params)
    {
        $this->clientareapath = APPDIR_MODULES . $this->module->getModuleType() . DS
            . strtolower($this->module->getModuleName()) . DS . 'templates' . DS . 'clientarea' . DS;

        $this->account = isset($params['account']) && is_array($params['account']) ? $params['account'] : [];

        $status = isset($this->account['status']) ? $this->account['status'] : '';

        if ($status !== 'Active') {
            $this->section = 'billing';
        } else {
            $this->setupModule($params['service']);

            try {
                $this->nh_details($params);
            } catch (Exception $e) {
                Engine::addError($e->getMessage());
                $this->section = 'details';
            }
        }

        $this->template->assign('nhsection', $this->section);
        $this->template->assign('nhdir', $this->clientareapath);
        $this->template->assign('service_id', isset($this->account['id']) ? (int) $this->account['id'] : 0);
        $this->template->showtpl = $this->clientareapath . 'details.tpl';
    }

    protected function nh_details($params)
    {
        $this->section = 'details';
        $this->assignDetails();
    }

    protected function assignDetails()
    {
        $this->template->assign('order', $this->module->orderSummary());
        $this->template->assign('plan_desc', $this->module->planDescription());
        $this->template->assign('panel_url', $this->module->controlPanelUrl());
    }

    protected function setupModule($serviceId)
    {
        $accounts = HBLoader::LoadModel('Accounts');
        $service  = $accounts->getAccount((int) $serviceId);

        if (empty($service) || empty($service['server_id'])) {
            return false;
        }

        $servers = HBLoader::LoadModel('Servers');
        $this->module->connect($servers->getServerDetails($service['server_id']));
        $this->module->setAccountConfig($accounts->getAccountModuleConfig((int) $serviceId));
        $this->module->setAccount($service);

        return true;
    }

    protected function clientId()
    {
        if (is_object($this->authorization) && method_exists($this->authorization, 'get_id')) {
            $id = (int) $this->authorization->get_id();

            if ($id > 0) {
                return $id;
            }
        }

        return isset($_SESSION['clientid']) ? (int) $_SESSION['clientid'] : 0;
    }
}
