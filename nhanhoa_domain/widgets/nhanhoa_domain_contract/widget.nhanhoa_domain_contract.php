<?php

/**
 * Nhan Hoa Domain — contract / e-signature client widget.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

/**
 * Bản khai / e-signature link for a Nhan Hoa domain.
 *
 * Calls nhanhoa_domain::Contract(), which returns the registrar's sign URL.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once dirname(dirname(__DIR__)) . '/lib/class.nhanhoa_domain_widget.php';

class Widget_nhanhoa_domain_contract extends Nhanhoa_Domain_Widget
{
    protected $widgetfullname = 'nhanhoa_domain_contract';

    protected $description = 'Lấy link ký số bản khai tên miền từ Nhân Hòa.';

    protected $action = 'nhanhoaDomainContract';

    protected $prefix = 'nhanhoa_domain_contract';

    public function controller($service, &$module, &$smarty, &$params)
    {
        $this->assign($smarty, 'url', null);
        $this->assign($smarty, 'message', null);
        $this->assign($smarty, 'error', null);

        if (!$this->doesApply($module) || !$this->isSubmitted($params)) {
            return;
        }

        $result = $module->Contract();

        if (is_string($result) && preg_match('#^https?://#i', $result)) {
            $this->assign($smarty, 'url', $result);
            return;
        }

        if (is_string($result) && trim($result) !== '') {
            $this->assign($smarty, 'message', $result);
            return;
        }

        $this->assign($smarty, 'error', $this->moduleError($module, 'Không thể lấy link ký số bản khai.'));
    }
}
