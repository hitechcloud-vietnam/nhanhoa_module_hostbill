<?php

/**
 * REST routes for nhanhoa_hosting.
 *
 * Both routes are auth'd and self-check ownership — `auth` only proves *a*
 * client is calling; the service id is in the URL.
 *
 * Read-only: the Nhan Hoa reseller API has no suspend/terminate/renew-by-client
 * surface, and renew is a billing action, so there is nothing destructive to
 * expose here.
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

class nhanhoa_hosting_apiroutes
{
    /** @return nhanhoa_hosting */
    protected function module($id)
    {
        return UserApi::module((int) $id, 'nhanhoa_hosting');
    }

    protected function owns($module, $id)
    {
        return $module && $module->serviceBelongsToClient((int) $id, (int) UserApi::clientId());
    }

    /**
     * GET /service/@id/nhanhoa/info
     *
     * Domain, plan code, order id/name, status and expiry.
     */
    public function info($id)
    {
        $module = $this->module($id);

        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);

            return;
        }

        $info = $module->fetchOrderInfo($module->serviceDomain(), $module->hostingCode());

        UserApi::render([
            'success'      => true,
            'domain'       => $module->serviceDomain(),
            'hosting_code' => $module->hostingCode(),
            'order_id'     => $module->orderId(),
            'order_name'   => $info && isset($info['ord_name']) ? $info['ord_name'] : '',
            'status'       => $info && isset($info['status']) ? $info['status'] : 'Pending',
            'status_text'  => $info && isset($info['content']) ? $info['content'] : '',
            'expires'      => $info && isset($info['ord_end_time']) ? (int) $info['ord_end_time'] : 0,
            'control_panel'=> $module->controlPanelUrl(),
        ]);
    }

    /**
     * GET /service/@id/nhanhoa/status
     *
     * Just the mapped HostBill status, read live.
     */
    public function status($id)
    {
        $module = $this->module($id);

        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);

            return;
        }

        $info = $module->fetchOrderInfo($module->serviceDomain(), $module->hostingCode());

        UserApi::render([
            'success' => true,
            'status'  => $info && isset($info['status']) ? $info['status'] : 'Pending',
            'text'    => $info && isset($info['content']) ? $info['content'] : '',
        ]);
    }
}
