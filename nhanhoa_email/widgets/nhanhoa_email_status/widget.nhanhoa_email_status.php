<?php

/**
 * Nhan Hoa Email Hosting — service summary widget.
 *
 * A small dashboard panel for the email-hosting service page (admin + client):
 * the stored order summary (domain, plan, order id/name, expiry), the reseller
 * account balance and the "services in use" report (both throttle-cached), plus a
 * link to the Nhan Hoa control panel. It reads only stored details and cached
 * lookups, so it never fires an uncached, rate-limited order lookup on render.
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

#[\AllowDynamicProperties]
class Widget_nhanhoa_email_status extends HostingWidget
{
    protected $widgetfullname = 'Nhân Hòa Email Hosting';

    protected $description = 'Tóm tắt dịch vụ Email Hosting Nhân Hòa: đơn hàng, ngày hết hạn, số dư đại lý và link control panel.';

    protected $display_groups = ['sidemenu' => 'Service side menu', 'apps' => 'Service apps menu'];

    protected $info = [
        'appendtpl'  => false,
        'replacetpl' => false,
        'options'    => self::OPTION_UNIQUE_DEFAULT | self::OPTION_SHOW_WHEN_PENDING | self::OPTION_SHOW_WHEN_SUSPENDED,
    ];

    public function clientFunction(&$module)
    {
        $data = [
            'nhw_order'   => [],
            'nhw_balance' => null,
            'nhw_used'    => [],
            'nhw_panel'   => '',
            'nhw_error'   => '',
        ];

        try {
            $data['nhw_order']   = $module->orderSummary();
            $data['nhw_panel']   = $module->controlPanelUrl();
            $data['nhw_balance'] = $module->getBalance();
            $data['nhw_used']    = $module->serviceUsed();
        } catch (Exception $e) {
            $data['nhw_error'] = $e->getMessage();
        }

        return ['nhanhoa_email_status.tpl', $data];
    }
}
