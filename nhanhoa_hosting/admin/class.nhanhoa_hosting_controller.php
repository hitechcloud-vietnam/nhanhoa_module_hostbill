<?php

/**
 * Admin controller for nhanhoa_hosting.
 *
 * Two core entry points: productdetails() for the product's Connection tab
 * (where the loadable plan/template dropdowns are fed) and accountdetails() for
 * the service page (sync from Nhan Hoa, renew, read order status).
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

    public function beforeCall($params)
    {
    }

    /**
     * Product → Connect with App form.
     *
     * Feeds the loadable selects. We prefill server-side on page load (reliable)
     * and also answer the core `make=loadoptions` XHR that fires when the admin
     * changes the selected server.
     */
    public function productdetails($params)
    {
        $dir = $this->templateDir();

        $this->template->assign('customconfig', $dir . 'productconfig.tpl');
        $this->template->assign('module_templates', $dir);

        // The server is picked on the form, so the module is not connected yet.
        if (!empty($params['server_id'])) {
            $servers = HBLoader::LoadModel('Servers');
            $this->module->connect($servers->getServerDetails((int) $params['server_id']));
        }

        $serviceCodes  = [];
        $templateCodes = [];
        $error         = '';

        try {
            $serviceCodes  = $this->module->listServiceCodes(1);
            $templateCodes = $this->module->listTemplateCodes(0);
        } catch (Exception $e) {
            $error = $e->getMessage();
        }

        $moduleError = (array) $this->module->getLastError();
        if ($moduleError) {
            $error = $error === '' ? implode('; ', $moduleError) : $error;
        }

        // Core's loadable XHR: return just the <option> fragment and stop.
        if ((isset($params['make']) ? $params['make'] : '') === 'loadoptions') {
            $which = isset($params['which']) ? (string) $params['which'] : 'hostingCode';
            $this->template->assign('loadlist', $which === 'os_template' ? $templateCodes : $serviceCodes);
            $this->template->render($dir . 'ajax.options.tpl');

            return;
        }

        $opts = $this->module->getOptions();
        $this->template->assign('options', isset($opts['simple']) ? $opts['simple'] : $opts);
        $this->template->assign('service_codes', $serviceCodes);
        $this->template->assign('template_codes', $templateCodes);
        $this->template->assign('product_error', $error);
    }

    /**
     * Service page.
     */
    public function accountdetails($params)
    {
        $serviceId = (int) (isset($params['id']) ? $params['id'] : 0);
        $path      = $this->templatePath('adminarea', 'account.tpl');

        $this->template->assign('custom_template', $path);

        $typetemplates = $this->template->getTemplateVars('typetemplates');

        if (is_array($typetemplates)) {
            $typetemplates['module']['replace'] = $path;
            $this->template->assign('typetemplates', $typetemplates);
        }

        $action = isset($params['nhdo']) ? (string) $params['nhdo'] : '';

        if ($action !== '' && !empty($params['token_valid']) && $this->setupModule($serviceId)) {
            $handler = 'nhdo_' . $action;

            if (is_callable([$this, $handler])) {
                $this->{$handler}($params, $serviceId);
            }
        } elseif ($serviceId) {
            $this->setupModule($serviceId);
        }

        $this->assignService($serviceId);
    }

    protected function nhdo_sync($params, $serviceId)
    {
        $this->module->getSynchInfo();
        HBLoader::LoadModel('Accounts')->updateExtraDetails($serviceId, $this->module->getDetails());
        Engine::addInfo('nhanhoa_hosting: đã đồng bộ với Nhân Hòa.');
    }

    protected function nhdo_renew($params, $serviceId)
    {
        $months = isset($params['months']) ? (int) $params['months'] : null;
        $result = $this->module->Renew($months);

        if ($result === true) {
            $this->module->getSynchInfo();
            HBLoader::LoadModel('Accounts')->updateExtraDetails($serviceId, $this->module->getDetails());
            Engine::addInfo('nhanhoa_hosting: đã gửi yêu cầu gia hạn.');
        } else {
            Engine::addError('nhanhoa_hosting: ' . $result);
        }
    }

    protected function assignService($serviceId)
    {
        $info = null;

        try {
            $info = $this->module->fetchOrderInfo($this->module->serviceDomain(), $this->module->hostingCode());
        } catch (Exception $e) {
            Engine::addError('nhanhoa_hosting: ' . $e->getMessage());
        }

        $this->template->assign('nh_domain', $this->module->serviceDomain());
        $this->template->assign('nh_code', $this->module->hostingCode());
        $this->template->assign('nh_order_id', $this->module->orderId());
        $this->template->assign('nh_info', $info);
        $this->template->assign('nh_balance', $this->module->getBalance());
        $this->template->assign('nh_months', $this->module->hostingMonths());
        $this->template->assign('nh_cp_url', $this->module->controlPanelUrl());
        $this->template->assign('module_error', (array) $this->module->getLastError());
    }

    protected function templatePath($area, $file)
    {
        return APPDIR_MODULES . 'Hosting' . DS . strtolower($this->module->getModuleName())
            . DS . 'templates' . DS . $area . DS . $file;
    }

    protected function templateDir()
    {
        return APPDIR_MODULES
            . $this->module->getModuleType() . DS
            . strtolower($this->module->getModuleName()) . DS
            . 'templates' . DS . 'adminarea' . DS;
    }

    protected function setupModule($serviceId)
    {
        if (!$serviceId) {
            return false;
        }

        $accounts = HBLoader::LoadModel('Accounts');
        $service  = $accounts->getAccount($serviceId);

        if (empty($service) || empty($service['server_id'])) {
            Engine::addError('nhanhoa_hosting: dịch vụ này chưa được gán app.');

            return false;
        }

        $servers = HBLoader::LoadModel('Servers');
        $this->module->connect($servers->getServerDetails($service['server_id']));
        $this->module->setAccountConfig($accounts->getAccountModuleConfig($serviceId));
        $this->module->setAccount($service);

        return true;
    }
}
