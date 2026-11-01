<?php

/**
 * Cron for nhanhoa_email.
 *
 * One job: refresh each active service's order state from Nhan Hoa (get_infoemail)
 * so expiry dates and order ids stay current in HostBill without anyone opening the
 * page. The reseller API rate-limits lookups to one a minute, so this walks
 * services with a short pause and tolerates per-service failures.
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

class nhanhoa_email_controller extends HBController
{
    /** @var nhanhoa_email */
    public $module;

    /** @var PDO */
    protected $db;

    public function __construct()
    {
        $this->db = HBRegistry::singleton()->getObject('db');
    }

    public function call_Daily()
    {
        $accounts = HBLoader::LoadModel('Accounts');
        $servers  = HBLoader::LoadModel('Servers');

        $synced = 0;

        foreach ($this->activeServices() as $serviceId) {
            $service = $accounts->getAccount($serviceId);

            if (empty($service) || empty($service['server_id'])) {
                continue;
            }

            // Per-service try/catch: one unreachable reseller account must not stop
            // the rest of the run.
            try {
                $this->module->connect($servers->getServerDetails($service['server_id']));
                $this->module->setAccountConfig($accounts->getAccountModuleConfig($serviceId));
                $this->module->setAccount($service);

                $info = $this->module->getSynchInfo();

                if (!empty($info)) {
                    $accounts->updateExtraDetails($serviceId, $this->module->getDetails());
                    $synced++;
                }
            } catch (Exception $e) {
                echo 'nhanhoa_email: dịch vụ #' . $serviceId . ' lỗi — ' . $e->getMessage() . "\n";
                HBDebug::debug('nhanhoa_email cron', $e->getMessage());
            }

            // Stay under the one-lookup-a-minute throttle.
            usleep(1200000);
        }

        echo 'nhanhoa_email: đồng bộ ' . $synced . " dịch vụ\n";

        return true;
    }

    protected function activeServices()
    {
        $stmt = $this->db->prepare(
            "SELECT a.id
             FROM hb_accounts a
             JOIN hb_products_modules pm ON pm.product_id = a.product_id
             WHERE pm.module = :module_id
               AND a.server_id > 0
               AND a.status = 'Active'
             ORDER BY a.id"
        );
        $stmt->execute([':module_id' => (int) $this->module->getModuleId()]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $stmt->closeCursor();

        return $ids ?: [];
    }
}
