<?php

/**
 * Nhan Hoa SSL — admin-area controller.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 *
 * Admin service-page controller for the nhanhoa_ssl module. Mirrors the
 * comodossl / daousign_ssl_v3 admin controllers: it renders a dedicated service
 * page (admin/myadminarea.tpl) showing the Nhan Hoa order status, product
 * (service code), order id and expiry, and wires Sync and Renew buttons to the
 * module's existing getSynchInfo() / Renewal() methods.
 *
 * The Nhan Hoa SSL API is intentionally minimal (register_ssl / renew_ssl /
 * get_infossl + service_code / get_balance): there is no CSR/DCV self-service,
 * reissue, cancel or certificate download, so this page offers only the
 * operations the API actually supports and states the rest are handled in the
 * Nhan Hoa customer portal.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

#[\AllowDynamicProperties]
class nhanhoa_ssl_Controller extends HBController
{
    /**
     * @var nhanhoa_ssl
     */
    public $module;

    public function beforeCall($params)
    {
    }

    /**
     * Connect the module to the service's server and load its account/config so
     * API-backed actions (sync, renew) have credentials and order context.
     */
    protected function setupModule($service)
    {
        $accounts = HBLoader::LoadModel('Accounts');
        $data = $accounts->getAccount($service);
        $accountConfig = $accounts->getAccountModuleConfig($service);
        $servers = HBLoader::LoadModel('Servers');
        $this->module->connect($servers->getServerDetails($data['server_id']));
        $this->module->setAccountConfig($accountConfig);
        $this->module->setAccount($data);
        return true;
    }

    /**
     * Render the admin service page and handle its actions.
     *
     *   ?cmd=accounts&action=edit&id=<id>&sync   -> get_infossl + persist details
     *   ?cmd=accounts&action=edit&id=<id>&renew  -> renew_ssl   + persist details
     */
    public function accountdetails($params)
    {
        $this->template->assign('custom_template', __DIR__ . DS . 'myadminarea.tpl');
        $this->template->assign('moduleurl', Utilities::checkSecureURL(HBConfig::getConfig('InstallURL') . 'includes/modules/Hosting/nhanhoa_ssl/admin/'));

        if (isset($params['sync']) && $this->setupModule($params['id'])) {
            $sync = $this->module->getSynchInfo();
            if ($sync !== false) {
                $accounts = HBLoader::LoadModel('Accounts');
                $accounts->updateExtraDetails($params['id'], $this->module->getDetails());
                Engine::addInfo('Nhan Hoa SSL information synchronized');
            } else {
                Engine::addError('Unable to synchronize Nhan Hoa SSL information');
            }
            return Utilities::redirect('?cmd=accounts&action=edit&id=' . $params['id']);
        }

        if (isset($params['renew']) && $this->setupModule($params['id'])) {
            if ($this->module->Renewal()) {
                $accounts = HBLoader::LoadModel('Accounts');
                $accounts->updateExtraDetails($params['id'], $this->module->getDetails());
                Engine::addInfo('Nhan Hoa SSL renewal request sent');
            } else {
                Engine::addError('Unable to renew the Nhan Hoa SSL order');
            }
            return Utilities::redirect('?cmd=accounts&action=edit&id=' . $params['id']);
        }

        $this->template->assign('cert', $this->module->CertDetails());
    }
}
