<?php

/**
 * REST handlers for the nhanhoa_cloud module — the client-facing Cloud VPS API.
 *
 * The manifest beside this file (nhanhoa_cloud_apiroutes.json) is the source of
 * truth: a method here with no manifest entry is unreachable, a `handle` naming
 * a missing method 404s at call time, and each `uuid` must stay stable.
 *
 * Every route is `"auth": true` and re-checks ownership — `auth` only proves a
 * client is calling, not that this client owns the service in the URL.
 *
 * The Nhan Hoa Cloud API has no power/reinstall/console command, so those routes
 * return a structured "managed in the Nhan Hoa panel" notice rather than
 * pretending to act. `info`/`status` return whitelisted identifiers only — never
 * the reseller credentials held on the server record.
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

class nhanhoa_cloud_apiroutes
{
    /** @return nhanhoa_cloud */
    protected function module($id)
    {
        return UserApi::module((int) $id, 'nhanhoa_cloud');
    }

    protected function owns($module, $id)
    {
        return $module->serviceBelongsToClient((int) $id, (int) UserApi::clientId());
    }

    protected function guard($id)
    {
        $module = $this->module($id);
        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);
            return null;
        }
        return $module;
    }

    /** GET /service/@id/nhanhoa_cloud/info — stored identifiers, no API call. */
    public function info($id)
    {
        $module = $this->guard($id);
        if (!$module) {
            return;
        }
        $s = $module->statusSummary();
        UserApi::render(['success' => true, 'info' => [
            'cloud_id'  => $s['cloud_id'],
            'order_id'  => $s['ord_id'],
            'order'     => $s['ord_name'],
            'plan'      => $s['plan'],
            'ip'        => $s['ip'],
            'status'    => $s['status_text'],
            'hb_status' => $s['hb_status'],
            'expiry'    => $s['end_time_fmt'],
        ]]);
    }

    /** GET /service/@id/nhanhoa_cloud/status — live sync from Nhan Hoa. */
    public function status($id)
    {
        $module = $this->guard($id);
        if (!$module) {
            return;
        }
        $s = $module->client_status();
        UserApi::render([
            'success'   => !empty($s['success']),
            'status'    => $s['status_text'],
            'hb_status' => $s['hb_status'],
            'expiry'    => $s['end_time_fmt'],
            'ip'        => $s['ip'],
        ]);
    }

    /** POST /service/@id/nhanhoa_cloud/power/@action — not supported via API. */
    public function power($id, $action)
    {
        $module = $this->guard($id);
        if (!$module) {
            return;
        }
        UserApi::render($module->client_power($action));
    }

    /** POST /service/@id/nhanhoa_cloud/reinstall — not supported via API. */
    public function reinstall($id)
    {
        $module = $this->guard($id);
        if (!$module) {
            return;
        }
        $params = UserApi::params();
        UserApi::render($module->client_reinstall(isset($params['os']) ? $params['os'] : ''));
    }

    /** GET /service/@id/nhanhoa_cloud/console — Nhan Hoa panel deep link. */
    public function console($id)
    {
        $module = $this->guard($id);
        if (!$module) {
            return;
        }
        UserApi::render($module->client_console());
    }
}
