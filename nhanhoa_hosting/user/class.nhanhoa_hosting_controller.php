<?php

/**
 * Client area for nhanhoa_hosting.
 *
 * The customer sees the four things that matter for a reseller hosting order:
 * the domain, the plan, the order status/expiry read live from Nhan Hoa, and a
 * link to the control panel (static — Nhan Hoa has no API SSO).
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

class nhanhoa_hosting_controller extends HBController
{
    /** @var nhanhoa_hosting */
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
                $this->assignDetails();
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

    protected function assignDetails()
    {
        $info = null;

        try {
            $info = $this->module->fetchOrderInfo($this->module->serviceDomain(), $this->module->hostingCode());
        } catch (Exception $e) {
            // Non-fatal: still show stored details.
        }

        $this->template->assign('domain', $this->module->serviceDomain());
        $this->template->assign('hosting_code', $this->module->hostingCode());
        $this->template->assign('order_id', $this->module->orderId());
        $this->template->assign('order_name', $info && !empty($info['ord_name']) ? $info['ord_name'] : '');
        $this->template->assign('expiry', $info && !empty($info['ord_end_time']) ? (int) $info['ord_end_time'] : 0);
        $this->template->assign('order_status', $info && !empty($info['content']) ? $info['content'] : '');
        $this->template->assign('cp_url', $this->module->controlPanelUrl());
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
