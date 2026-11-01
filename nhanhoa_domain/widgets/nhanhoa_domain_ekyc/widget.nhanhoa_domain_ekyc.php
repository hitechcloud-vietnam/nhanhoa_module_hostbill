<?php

/**
 * eKYC link for a .vn registrant.
 *
 * nhanhoa_domain::getekyc() refuses international domains itself and records the
 * reason with addError(), so the widget just relays whatever it says.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once dirname(dirname(__DIR__)) . '/lib/class.nhanhoa_domain_widget.php';

class Widget_nhanhoa_domain_ekyc extends Nhanhoa_Domain_Widget
{
    protected $widgetfullname = 'nhanhoa_domain_ekyc';

    protected $description = 'Lấy link eKYC chủ thể tên miền .vn từ Nhân Hòa.';

    protected $action = 'nhanhoaDomainEkyc';

    protected $prefix = 'nhanhoa_domain_ekyc';

    public function controller($service, &$module, &$smarty, &$params)
    {
        $this->assign($smarty, 'url', null);
        $this->assign($smarty, 'error', null);
        $this->assign($smarty, 'is_vn', false);

        if (!$this->doesApply($module)) {
            return;
        }

        $this->assign($smarty, 'is_vn', $module->isVnDomain());

        if (!$this->isSubmitted($params)) {
            return;
        }

        $result = $module->getekyc();

        if (is_string($result) && $result !== '') {
            $this->assign($smarty, 'url', $result);
            return;
        }

        $this->assign($smarty, 'error', $this->moduleError($module, 'Không thể lấy link eKYC.'));
    }
}
