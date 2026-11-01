<?php

/**
 * Nhan Hoa Cloud VPS — service summary widget.
 *
 * A small dashboard panel for the Cloud VPS service page (admin + client): the
 * stored status summary (plan, cloud/order id, IP, status, expiry), the reseller
 * account balance and the "services in use" report (both throttle-cached), plus a
 * link to the Nhan Hoa control panel. It reads only stored details and cached
 * lookups, so it never fires an uncached, rate-limited order lookup on render.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.2026-11-02
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

#[\AllowDynamicProperties]
class Widget_nhanhoa_cloud_status extends HostingWidget
{
    protected $widgetfullname = 'Nhân Hòa Cloud VPS';

    protected $description = 'Tóm tắt dịch vụ Cloud VPS Nhân Hòa: trạng thái, IP, ngày hết hạn, số dư đại lý và link control panel.';

    protected $display_groups = ['sidemenu' => 'Service side menu', 'apps' => 'Service apps menu'];

    protected $info = [
        'appendtpl'  => false,
        'replacetpl' => false,
        'options'    => self::OPTION_UNIQUE_DEFAULT | self::OPTION_SHOW_WHEN_PENDING | self::OPTION_SHOW_WHEN_SUSPENDED,
    ];

    public function clientFunction(&$module)
    {
        $data = [
            'nhw_summary' => [],
            'nhw_balance' => null,
            'nhw_used'    => [],
            'nhw_panel'   => nhanhoa_cloud::PANEL_URL,
            'nhw_error'   => '',
        ];

        try {
            $data['nhw_summary'] = $module->statusSummary();
            $data['nhw_balance'] = $module->getBalance();
            $data['nhw_used']    = $module->serviceUsed();
        } catch (Exception $e) {
            $data['nhw_error'] = $e->getMessage();
        }

        return ['nhanhoa_cloud_status.tpl', $data];
    }
}
