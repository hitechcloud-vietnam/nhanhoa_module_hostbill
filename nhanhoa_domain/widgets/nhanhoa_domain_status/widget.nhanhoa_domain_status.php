<?php

/**
 * Nhan Hoa Domain — order-processing status client widget.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

/**
 * Registrar-side processing state for the domain order.
 *
 * v2 called the registrar on every client-area render. This one only calls on
 * its own token-checked submit, exactly like core's `sync` widget.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once dirname(dirname(__DIR__)) . '/lib/class.nhanhoa_domain_widget.php';

class Widget_nhanhoa_domain_status extends Nhanhoa_Domain_Widget
{
    protected $widgetfullname = 'nhanhoa_domain_status';

    protected $description = 'Trạng thái xử lý đơn tên miền và trạng thái hồ sơ tại Nhân Hòa.';

    protected $action = 'nhanhoaDomainStatus';

    protected $prefix = 'nhanhoa_domain_status';

    public function controller($service, &$module, &$smarty, &$params)
    {
        $this->assign($smarty, 'rows', null);
        $this->assign($smarty, 'declaration', null);
        $this->assign($smarty, 'error', null);

        if (!$this->doesApply($module) || !$this->isSubmitted($params)) {
            return;
        }

        $status = $module->getStatusDomain();
        if (!is_array($status)) {
            $this->assign($smarty, 'error', $this->moduleError($module, 'Không lấy được trạng thái xử lý tên miền.'));
            return;
        }

        $rows = [
            'Mã đơn'          => $status['ord_id'],
            'Tên đơn'         => $status['ord_name'],
            'Kết thúc xử lý'  => $status['ord_end_time'],
        ];
        // `content` is an object for .vn and a scalar elsewhere.
        $rows += $this->flatten($status['content'], 'Chi tiết');

        $this->assign($smarty, 'rows', array_filter($rows, 'strlen'));

        if (!$module->isVnDomain()) {
            return; // bản khai state is a .vn concept
        }

        $declaration = $module->Statusdomain();
        if (is_array($declaration)) {
            $this->assign($smarty, 'declaration', $declaration['declaration_status_text']);
        }
    }
}
