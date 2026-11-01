<?php

/**
 * Admin-area controller for nhanhoa_domain.
 *
 * Reached as `?cmd=nhanhoa_domain&action=<method>`; the buttons in
 * admin/domaindetails.tpl call these over ajax_update().
 *
 * Every path that talks to the registrar first loads the domain into the
 * module (loadDomainContext), because the module reads $this->name and
 * $this->options from there.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

class nhanhoa_domain_controller extends HBController
{
    public $module = null;
    public $authorization = null;
    public $template = null;

    /** Directory this module lives in, for template paths. */
    private function moduleDir()
    {
        return APPDIR_MODULES . 'Domain' . DS . strtolower($this->module->getModuleDirName()) . DS;
    }

    private function adminTpl($name)
    {
        return $this->moduleDir() . 'admin' . DS . $name;
    }

    protected function loadDomainContext($id)
    {
        $this->module->getFromDB($id);
        $this->module->setDomainConfig(HBLoader::LoadModel('Domains')->getDomainModuleConfig($id));
    }

    protected function encodeData($data)
    {
        return json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    protected function maskSensitiveData($data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $sensitiveKeys = ['password', 'authcode', 'auth_code', 'token'];
        $masked = [];

        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), $sensitiveKeys, true)) {
                $masked[$key] = !empty($value) ? '******' : '';
                continue;
            }

            $masked[$key] = is_array($value) ? $this->maskSensitiveData($value) : $value;
        }

        return $masked;
    }

    /** Shared assigns for every ajax panel. */
    protected function assignCommon($params)
    {
        $this->template->assign('custom_template', $this->adminTpl('domaindetails.tpl'));
        $this->template->assign('domainid', (int) ($params['id'] ?? 0));
        $this->template->assign('modulename', $this->module->getModuleName());
        $this->template->assign('modname', $this->module->getModName());
        $this->template->assign('moduleid', $this->module->getModuleId());
    }

    protected function isAjaxLoad($params)
    {
        return Controller::isAjax() && !empty($params['token_valid']) && !empty($params['load']);
    }

    public function _default($params)
    {
        $this->template->pageTitle = Language::parse($this->module->getModName());
        $this->template->module_template_dir = rtrim($this->adminTpl(''), DS);
        $this->template->showtpl = 'default';
        $this->template->assign('moduleurl', Utilities::checkSecureURL(
            HBConfig::getConfig('InstallURL') . 'includes/modules/Domain/'
            . strtolower($this->module->getModuleDirName()) . '/admin/'
        ));
        $this->template->assign('modulename', $this->module->getModuleName());
        $this->template->assign('modname', $this->module->getModName());
        $this->template->assign('moduleid', $this->module->getModuleId());
    }

    public function domaindetails($params)
    {
        $this->assignCommon($params);

        if ($this->isAjaxLoad($params)) {
            $this->template->showtpl = $this->adminTpl('ajax.domaindetails.tpl');
            $this->loadDomainContext($params['id']);

            $details = [
                'domain_info'        => $this->module->getDomainInfo(),
                'processing_status'  => $this->module->getStatusDomain(),
                'declaration_status' => $this->module->Statusdomain(),
                'nameservers'        => $this->module->getNameServers(),
            ];

            $this->template->assign('details_data', $details);
            $this->template->assign('details_json', $this->encodeData($this->maskSensitiveData($details)));
        }

        $this->template->assign('domaininfo', $params['domain'] ?? []);
    }

    public function domaincontacts($params)
    {
        $this->assignCommon($params);

        if ($this->isAjaxLoad($params)) {
            $this->template->showtpl = $this->adminTpl('ajax.domaincontacts.tpl');
            $this->loadDomainContext($params['id']);

            $contacts = $this->module->getDomainContacts(true);
            $this->template->assign('contacts_data', $contacts);
            $this->template->assign('contacts_json', $this->encodeData($contacts));
        }
    }

    public function domainstatus($params)
    {
        $this->assignCommon($params);

        if ($this->isAjaxLoad($params)) {
            $this->template->showtpl = $this->adminTpl('ajax.domainstatus.tpl');
            $this->loadDomainContext($params['id']);

            $status = [
                'processing_status'  => $this->module->getStatusDomain(),
                'declaration_status' => $this->module->Statusdomain(),
            ];

            $this->template->assign('status_data', $status);
            $this->template->assign('status_json', $this->encodeData($status));
        }
    }

    public function domainaction($params)
    {
        $this->assignCommon($params);

        if (!$this->isAjaxLoad($params)) {
            return;
        }

        $this->template->showtpl = $this->adminTpl('ajax.domainactions.tpl');
        $this->loadDomainContext($params['id']);

        $result = $this->runAction($params['ac'] ?? '');

        if (is_array($result)) {
            $this->template->assign('message_data', $this->maskSensitiveData($result));
            $this->template->assign('message', $this->encodeData($this->maskSensitiveData($result)));
            return;
        }

        if (is_bool($result)) {
            $this->template->assign('message_success', $result);
            $this->template->assign('message', $result ? 'OK' : $this->lastError('ERROR'));
            return;
        }

        $text = (string) $result;
        $this->template->assign('message_text', $text);
        $this->template->assign('message_is_url', (bool) preg_match('#^https?://#i', $text));
        $this->template->assign('message', $text);
    }

    /** One place for the action switch, so adding a button is a one-line change. */
    private function runAction($action)
    {
        switch ($action) {
            case 'RefreshContacts':
                return $this->module->getDomainContacts(true);
            case 'Contract':
                return $this->module->Contract();
            case 'GetEkyc':
                return $this->module->getekyc();
            case 'GetEkycProfile':
                return $this->module->getekycProfile();
            case 'DeclarationStatus':
                return $this->module->Statusdomain();
            case 'ProcessingStatus':
                return $this->module->getStatusDomain();
            case 'ResendVerify':
                return $this->module->ResendVerify();
            case 'SkipVerifyEmail':
                return $this->module->SkipVerifyEmail();
            case 'Unlock24h':
                return $this->module->Unlock24h();
            case 'DnsRecords':
                return $this->module->dnsRecords();
            case 'DomainInfo':
                return $this->module->getDomainInfo();
            case 'BackorderStatus':
                return $this->module->checkBackorderStatus();
            case 'UnsignedDomains':
                return $this->module->listUnsignedDomains();
            default:
                return ['message' => 'Unsupported action'];
        }
    }

    private function lastError($fallback)
    {
        $error = method_exists($this->module, 'getLastError') ? $this->module->getLastError() : '';
        $error = is_array($error) ? implode(' | ', $error) : (string) $error;

        return trim($error) !== '' ? $error : $fallback;
    }
}
