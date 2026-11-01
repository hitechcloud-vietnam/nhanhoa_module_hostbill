<?php

/**
 * Nhan Hoa SSL — client-facing REST API handlers.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

/**
 * REST handlers for the nhanhoa_ssl module — the client-facing SSL API.
 *
 * The Nhan Hoa SSL API is read-light: it can report order status and knows
 * nothing of CSR/DCV self-service or certificate download (those are handled in
 * Nhan Hoa's portal, out of band). The exposed surface is therefore limited to
 * synchronising/returning certificate status and listing the validation methods
 * the product nominally supports.
 *
 * The manifest beside this file (nhanhoa_ssl_apiroutes.json) is the source of
 * truth: a method here with no manifest entry is unreachable, a `handle` naming
 * a missing method 404s at call time, and each `uuid` must stay stable.
 *
 * Every route is `"auth": true` and re-checks ownership — `auth` only proves a
 * client is calling, not that this client owns the service in the URL.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

class nhanhoa_ssl_apiroutes
{
    /**
     * The module, connected to the app behind one service.
     *
     * @return nhanhoa_ssl
     */
    protected function module($id)
    {
        return UserApi::module((int) $id, 'nhanhoa_ssl');
    }

    /**
     * auth:true proves a client is calling; it does not prove this client owns
     * this service (the service id is caller input). Admin-issued credentials
     * return no clientId() and fall through to false.
     */
    protected function owns($module, $id)
    {
        return $module->serviceBelongsToClient((int) $id, (int) UserApi::clientId());
    }

    /** GET /service/@id/nhanhoa_ssl/status */
    public function status($id)
    {
        $module = $this->module($id);
        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);
            return;
        }
        $sync = $module->getSynchInfo();
        if ($sync === false) {
            UserApi::render(['success' => false, 'error' => 'the certificate status could not be read']);
            return;
        }
        UserApi::render([
            'success'  => true,
            'next_due' => isset($sync['next_due']) ? $sync['next_due'] : null,
        ]);
    }

    /** GET /service/@id/nhanhoa_ssl/dcv-methods */
    public function dcvMethods($id)
    {
        $module = $this->module($id);
        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);
            return;
        }
        $methods = [];
        foreach ((array) $module->listDCVMethod() as $row) {
            if (is_array($row) && isset($row[0], $row[1])) {
                $methods[] = ['value' => $row[0], 'name' => $row[1]];
            }
        }
        UserApi::render(['success' => true, 'dcv_methods' => $methods]);
    }
}
