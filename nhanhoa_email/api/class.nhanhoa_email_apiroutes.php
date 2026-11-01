<?php

/**
 * REST routes for nhanhoa_email.
 *
 * Two routes, both `auth: true`, both verifying ownership themselves — `auth`
 * only proves *a* customer is calling; the service id is in the URL.
 *
 * Read-only by design: the reseller API exposes nothing a customer can write
 * (no mailbox management, no suspend), so there is no mutating route. Renewal is
 * a billing/admin action, not a self-service REST call.
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

class nhanhoa_email_apiroutes
{
    /** @return nhanhoa_email */
    protected function module($id)
    {
        return UserApi::module((int) $id, 'nhanhoa_email');
    }

    protected function owns($module, $id)
    {
        return $module && $module->serviceBelongsToClient((int) $id, (int) UserApi::clientId());
    }

    /**
     * GET /service/@id/nhemail/info
     *
     * Order summary the customer needs: domain, plan, order id, expiry, panel URL.
     */
    public function info($id)
    {
        $module = $this->module($id);

        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);

            return;
        }

        $order = $module->orderSummary();

        UserApi::render([
            'success'   => true,
            'domain'    => $order['domain'],
            'plan'      => $order['service_code'],
            'ord_id'    => $order['ord_id'],
            'ord_name'  => $order['ord_name'],
            'expires'   => $order['end_human'],
            'panel_url' => $module->controlPanelUrl(),
        ]);
    }

    /**
     * GET /service/@id/nhemail/sync
     *
     * Re-read the live order state from Nhan Hoa and return the refreshed summary.
     * Rate-limited upstream, so callers should not poll this tightly.
     */
    public function sync($id)
    {
        $module = $this->module($id);

        if (!$this->owns($module, $id)) {
            UserApi::render(['success' => false, 'error' => 'not your service']);

            return;
        }

        $module->getSynchInfo();
        $order = $module->orderSummary();

        UserApi::render([
            'success'  => true,
            'domain'   => $order['domain'],
            'plan'     => $order['service_code'],
            'ord_id'   => $order['ord_id'],
            'ord_name' => $order['ord_name'],
            'expires'  => $order['end_human'],
        ]);
    }
}
