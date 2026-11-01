<?php

/**
 * Admin controller for nhanhoa_email.
 *
 * Two core-named entry points: productdetails() for the product Connection tab,
 * accountdetails() for the service page. The service page shows the live order
 * state read from Nhan Hoa and offers the two actions the API actually supports —
 * sync (get_infoemail) and renew (renew_email). Suspend/terminate/change-package
 * are the core service buttons and are handled by the module's lifecycle methods.
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

    public function beforeCall($params)
    {
    }

    public function productdetails($params)
    {
        $error = '';
        $codes = [];

        if (!empty($params['server_id'])) {
            $servers = HBLoader::LoadModel('Servers');
            $this->module->connect($servers->getServerDetails($params['server_id']));

            try {
                $codes = $this->module->api()->serviceCodes(2);

                if ($codes === [] && $this->module->api()->getLastRaw() === '') {
                    $error = 'Chưa đọc được danh sách mã gói (kiểm tra thông tin kết nối API).';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
        }

        $this->template->assign('customconfig', $this->templatePath('adminarea', 'productconfig.tpl'));
        $this->template->assign('nh_codes', $codes);
        $this->template->assign('product_error', $error);
    }

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
        $sync = $this->module->getSynchInfo();

        if (empty($sync)) {
            Engine::addError('nhanhoa_email: ' . implode('; ', (array) $this->module->getLastError()));

            return;
        }

        HBLoader::LoadModel('Accounts')->updateExtraDetails($serviceId, $this->module->getDetails());
        Engine::addInfo('nhanhoa_email: đã đồng bộ với Nhân Hòa.');
    }

    protected function nhdo_renew($params, $serviceId)
    {
        $months = isset($params['months']) ? (int) $params['months'] : null;
        $result = $this->module->renewOrder($months);

        if ($result === true) {
            HBLoader::LoadModel('Accounts')->updateExtraDetails($serviceId, $this->module->getDetails());
            Engine::addInfo('nhanhoa_email: đã gửi yêu cầu gia hạn.');
        } else {
            Engine::addError('nhanhoa_email: ' . $result);
        }
    }

    protected function assignService($serviceId)
    {
        $this->template->assign('nh_order', $this->module->orderSummary());
        $this->template->assign('nh_plan_desc', $this->module->planDescription());
        $this->template->assign('nh_panel_url', $this->module->controlPanelUrl());
        $this->template->assign('module_error', (array) $this->module->getLastError());
    }

    protected function templatePath($area, $file)
    {
        return APPDIR_MODULES . 'Hosting' . DS . strtolower($this->module->getModuleName())
            . DS . 'templates' . DS . $area . DS . $file;
    }

    protected function setupModule($serviceId)
    {
        if (!$serviceId) {
            return false;
        }

        $accounts = HBLoader::LoadModel('Accounts');
        $service  = $accounts->getAccount($serviceId);

        if (empty($service) || empty($service['server_id'])) {
            Engine::addError('nhanhoa_email: dịch vụ này chưa được gán app.');

            return false;
        }

        $servers = HBLoader::LoadModel('Servers');
        $this->module->connect($servers->getServerDetails($service['server_id']));
        $this->module->setAccountConfig($accounts->getAccountModuleConfig($serviceId));
        $this->module->setAccount($service);

        return true;
    }
}
