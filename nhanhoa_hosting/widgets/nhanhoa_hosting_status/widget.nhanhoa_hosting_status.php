<?php

/**
 * Nhan Hoa Hosting — service summary widget.
 *
 * A small dashboard panel for the hosting service page (admin + client): the
 * stored order identity (domain, plan code, order id/name), the reseller account
 * balance and the "services in use" report (both throttle-cached), plus a link to
 * the Nhan Hoa control panel. It reads only stored details and cached lookups, so
 * it never fires an uncached, rate-limited order lookup on render.
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
class Widget_nhanhoa_hosting_status extends HostingWidget
{
    protected $widgetfullname = 'Nhân Hòa Hosting';

    protected $description = 'Tóm tắt dịch vụ hosting Nhân Hòa: đơn hàng, số dư đại lý, dịch vụ đang dùng và link control panel.';

    protected $display_groups = ['sidemenu' => 'Service side menu', 'apps' => 'Service apps menu'];

    protected $info = [
        'appendtpl'  => false,
        'replacetpl' => false,
        'options'    => self::OPTION_UNIQUE_DEFAULT | self::OPTION_SHOW_WHEN_PENDING | self::OPTION_SHOW_WHEN_SUSPENDED,
    ];

    public function clientFunction(&$module)
    {
        $data = [
            'nhw_domain'   => '',
            'nhw_code'     => '',
            'nhw_ord_id'   => '',
            'nhw_ord_name' => '',
            'nhw_balance'  => null,
            'nhw_used'     => [],
            'nhw_panel'    => '',
            'nhw_error'    => '',
        ];

        try {
            $data['nhw_domain']   = $module->serviceDomain();
            $data['nhw_code']     = $module->hostingCode();
            $data['nhw_ord_id']   = $module->orderId();
            $data['nhw_ord_name'] = $module->detail(nhanhoa_hosting::D_USERNAME, '');
            $data['nhw_panel']    = $module->controlPanelUrl();
            $data['nhw_balance']  = $module->getBalance();
            $data['nhw_used']     = $module->serviceUsed();
        } catch (Exception $e) {
            $data['nhw_error'] = $e->getMessage();
        }

        return ['nhanhoa_hosting_status.tpl', $data];
    }
}
