<?php

/**
 * Nhan Hoa ZoneDNS — DNS provisioning module (DNSModule).
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.1.0
 */
/**
 * HostBill Module Component
 * Developed & Optimized for PHP 8.1 - 8.5 compatibility
 *
 * Nhan Hoa (Nhân Hòa) ZoneDNS module for HostBill.
 *
 * Greenfield DNSModule built on the Nhan Hoa reseller ZoneDNS API
 * (https://api.nhanhoa.com/, documented at https://docs.nhanhoa.com/zonedns.md).
 * Modelled on the webnic_dns / cloudns DNS modules: it implements the full
 * DNSModule surface plus ReverseDNS and DNSSECModule, with admin + user
 * controllers, templates and User API routes.
 *
 * ── What the ZoneDNS API actually offers ──────────────────────────────────
 * The ZoneDNS surface is record-only, and every call is keyed by domain name:
 *   - list_record   {domain_name}                       -> data.domain_record[]
 *   - add_record    {domain_name, record_name, record_type, record_value,
 *                    record_ttl, record_mx?}
 *   - edit_record   {domain_name, record_id, record_type, record_value,
 *                    record_ttl, record_mx?}            (cannot rename a record)
 *   - delete_record {domain_name, record_id}
 * Records carry a NATIVE integer id (record_id), so no synthesized ids are
 * needed. There is NO create/list/delete-zone command: a zone exists implicitly
 * because the domain belongs to the reseller account, so addZone/deleteZone are
 * best-effort local operations. There is NO reverse-DNS (.arpa) command and NO
 * DNSSEC command, so ReverseDNS/DNSSECModule are implemented gracefully as
 * "unsupported".
 *
 * ── Credentials (connected app, Settings -> Apps) ─────────────────────────
 *   Username            -> auth-user  (API username, server username field)
 *   Password            -> auth-pwd   (API password, server password field)
 *   Reseller ID (ID API)-> auth-id    (custom[auth_id])
 *   Debug log           -> custom[debug_log]
 *   Default Nameservers -> custom[default_ns]
 *
 * @author HiTechCloud
 */
#[\AllowDynamicProperties]
class nhanhoa_dns extends DNSModule implements ReverseDNS, DNSSECModule
{
    /**
     * Capability flags. haveapi exposes this module's REST routes
     * (api/class.nhanhoa_dns_apiroutes.php) through the User API plugin.
     * @var array
     */
    protected $info = ["haveuser" => true, "haveadmin" => true, "havetpl" => true, "haveapi" => true];

    /**
     * Module version. Increase on every method change.
     * @var string
     */
    protected $version = "1.1.0";

    protected $_repository = "hosting_nhanhoa_dns";

    /**
     * Module name, visible in admin portal.
     * @var string
     */
    protected $modname = "Nhan Hoa ZoneDNS";

    /**
     * Module description, visible in admin portal.
     * @var string
     */
    protected $description = "Nhan Hoa (Nhân Hòa) ZoneDNS module for HostBill";

    /**
     * Connected instance of PDO object.
     * @var PDO
     */
    protected $db;

    /**
     * Product-level options (Products & Services -> Product -> Connect with Module).
     * @var array
     */
    protected $options = [
        "maxdomain" => ["name" => "maxdomain", "value" => false, "type" => "input", "variable" => "domainnumber", "description" => ""],
        "dns_template" => ["name" => "dns_template", "value" => false, "type" => "input"],
        "ns1" => ["name" => "ns1", "value" => false, "type" => "input"],
        "ns2" => ["name" => "ns2", "value" => false, "type" => "input"],
        "ns3" => ["name" => "ns3", "value" => false, "type" => "input"],
        "ns4" => ["name" => "ns4", "value" => false, "type" => "input"],
        "soaemailclient" => ["name" => "soaemailclient", "value" => true, "default" => true, "type" => "check"],
        "soaemaildefault" => ["name" => "soaemaildefault", "value" => false, "type" => "input"],
        "soattldefault" => ["name" => "soattldefault", "value" => "3600", "default" => "3600", "type" => "input"],
        "hide_billing" => ["name" => "hide_billing", "value" => false, "type" => "check", "default" => false],
        "hide_zone_management" => ["name" => "hide_zone_management", "value" => false, "type" => false, "default" => false],
    ];

    protected $details = [];

    protected $serverFields;
    protected $serverFieldsDescription;

    /**
     * Data passed in via connect().
     * @var array
     * @see connect()
     */
    private $connect_data;

    /**
     * Supported record types. ZoneDNS has no "list record types" endpoint, so
     * this static list is authoritative.
     * @var array
     */
    protected $supported_records = ["A", "AAAA", "CNAME", "MX", "TXT", "NS", "SRV", "CAA"];

    protected $name_servers = [];

    /**
     * Conservative Nhan Hoa platform nameserver fallback. The configured
     * "Default Nameservers" app field overrides this; see getDefaultNameservers.
     * @var array
     */
    protected $fallback_ns = ["ns1.nhanhoa.com", "ns2.nhanhoa.com"];

    /** @var Nhanhoa_Dns_Api|null */
    protected $_api;

    public function __construct()
    {
        parent::__construct();
    }

    public function connect($connect)
    {
        $this->connect_data = $connect;
        $this->_api = null;
    }

    public function install()
    {
        $this->upgrade("1.0");
    }

    public function upgrade($old)
    {
        if (version_compare($old, "1.0.0", "<")) {
            LangEdit::addTranslation("dnssec_not_available", "DNSSEC is not available for this zone", "clientarea", "client");
        }
    }

    /* ------------------------------------------------------------------ */
    /* Config / credential accessors                                       */
    /* ------------------------------------------------------------------ */

    /**
     * Read a server custom field (saved via name="custom[...]" in appconfig.tpl).
     * Checks the several shapes HostBill may hand back for a connected app.
     */
    protected function getCustom($key, $default = null)
    {
        if (isset($this->connect_data["custom"][$key])) {
            return $this->connect_data["custom"][$key];
        }
        if (isset($this->srv_data["custom"][$key])) {
            return $this->srv_data["custom"][$key];
        }
        if (isset($this->connect_data[$key])) {
            return $this->connect_data[$key];
        }
        return $default;
    }

    /**
     * Read a product option value.
     */
    protected function getOption($key, $default = null)
    {
        if (isset($this->options[$key]["value"]) && $this->options[$key]["value"] !== false && $this->options[$key]["value"] !== "") {
            return $this->options[$key]["value"];
        }
        return $default;
    }

    /**
     * Module log file for the debug transport.
     */
    protected function logFile()
    {
        $dir = APPDIR_MODULES . $this->getModuleType() . DS . strtolower($this->getModuleName());
        return $dir . DS . "nhanhoa_dns.log";
    }

    /**
     * Lazily build the Nhan Hoa API transport from the connected credentials.
     * @return Nhanhoa_Dns_Api
     */
    protected function api()
    {
        if ($this->_api instanceof Nhanhoa_Dns_Api) {
            return $this->_api;
        }
        require_once __DIR__ . DIRECTORY_SEPARATOR . "lib" . DIRECTORY_SEPARATOR . "class.nhanhoa_dns_api.php";
        $user  = (string) ($this->connect_data["username"] ?? "");
        $pwd   = (string) ($this->connect_data["password"] ?? "");
        $id    = (string) $this->getCustom("auth_id", "");
        $debug = (string) $this->getCustom("debug_log") === "1";
        $this->_api = new Nhanhoa_Dns_Api($user, $pwd, $id, $debug, $this->logFile());
        return $this->_api;
    }

    /**
     * Run one API command and return the normalized response array.
     */
    protected function get($cmd, array $formdata = [])
    {
        return $this->api()->call($cmd, $formdata);
    }

    protected function ok($result)
    {
        return Nhanhoa_Dns_Api::ok($result);
    }

    protected function errMsg($result)
    {
        return Nhanhoa_Dns_Api::msg($result);
    }

    /* ------------------------------------------------------------------ */
    /* Lifecycle                                                           */
    /* ------------------------------------------------------------------ */

    /**
     * Probe the reseller credentials with a cheap read-only call.
     * get_balance authenticates (auth-user/auth-pwd/auth-id) and touches no zone.
     */
    public function testConnection()
    {
        $resp = $this->get("get_balance");
        if ($this->ok($resp)) {
            return true;
        }
        $this->addError("Nhan Hoa authentication failed: " . $this->errMsg($resp));
        return false;
    }

    public function Create()
    {
        return true;
    }

    public function Terminate()
    {
        return true;
    }

    /* ------------------------------------------------------------------ */
    /* Zones                                                               */
    /* ------------------------------------------------------------------ */

    /**
     * "Create" a zone. ZoneDNS has no create-zone command: a zone exists because
     * the domain is registered in the reseller account, and records are written
     * directly against domain_name. This therefore just validates the name and
     * accepts, so HostBill can create its local zone and manage records.
     */
    public function addZone($domain, $ip = false)
    {
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            $this->addError("Domain name is empty");
            return false;
        }
        $this->addInfo("Nhan Hoa ZoneDNS ready for existing reseller domain: " . $domain);
        return true;
    }

    /**
     * "Delete" a zone. ZoneDNS has no delete-zone command (the zone follows the
     * reseller domain), so this is a best-effort local removal only; the
     * registrar-side zone and its records are left intact.
     */
    public function deleteZone($domain)
    {
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            return false;
        }
        $this->addInfo("Local zone removed; Nhan Hoa ZoneDNS has no delete-zone command, registrar zone left intact: " . $domain);
        return true;
    }

    /**
     * Zones belonging to this service. ZoneDNS has no "list zones" command, so
     * the authoritative list is HostBill's own DNS domains for this account
     * (each belongs to the service and is managed directly by domain name).
     */
    public function listZones()
    {
        $zones = [];
        if (empty($this->account_details["id"])) {
            return $zones;
        }
        $userzones = HBLoader::LoadComponent("DnsManage")->listAccountDomains($this->account_details["id"]);
        foreach ($userzones as $zone) {
            $name = (string) $zone[DnsManage::COLUMN_DOMAIN_NAME];
            if ($name !== "") {
                $zones[] = ["id" => $zone[DnsManage::COLUMN_RELATED_ID], "domain" => $name];
            }
        }
        return $zones;
    }

    /**
     * Zones available for import. ZoneDNS exposes no zone-enumeration command,
     * so there is nothing to import from the registrar side.
     */
    public function importZones()
    {
        return [];
    }

    /**
     * Reverse (.arpa) zones. ZoneDNS has no reverse-DNS command.
     */
    public function getArpaZones()
    {
        return [];
    }

    /* ------------------------------------------------------------------ */
    /* Records                                                             */
    /* ------------------------------------------------------------------ */

    /**
     * Read a zone's records, normalized to HostBill's record array shape.
     * GET list_record -> data.domain_record[] (native record_id per row).
     */
    public function getZone($domain)
    {
        $records = [];
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            return $records;
        }
        foreach ($this->rawRecords($domain) as $rec) {
            $type = strtoupper((string) ($rec["record_type"] ?? ""));
            $host = (string) ($rec["record_name"] ?? "");
            $ttl  = (int) ($rec["record_ttl"] ?? 3600);
            $value = (string) ($rec["record_value"] ?? "");
            $priority = 0;
            if ($type === "MX") {
                $priority = (int) ($rec["record_mx"] ?? 0);
            } elseif ($type === "SRV") {
                $priority = $this->srvPriority($value);
            }
            $records[] = [
                "id" => (string) ($rec["record_id"] ?? ""),
                "type" => $type,
                "ttl" => (string) $ttl,
                "name" => $this->fromHost($host, $domain),
                "priority" => $priority,
                "content" => $this->contentOut($type, $value),
            ];
        }
        return $records;
    }

    /**
     * Add a record. POST add_record.
     */
    public function addRecord($domain, $data = [])
    {
        if (empty($data) || empty($domain)) {
            return false;
        }
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            return false;
        }
        $type = strtoupper((string) ($data["type"] ?? ""));
        $host = $this->toHost($data["name"] ?? "", $domain);
        $value = $this->buildValue($type, $data);
        if ($type === "" || $value === "") {
            $this->addError("Record type and value are required");
            return false;
        }
        $formdata = [
            "domain_name" => $domain,
            "record_name" => $host,
            "record_type" => $type,
            "record_value" => $value,
            "record_ttl" => $this->fixTtl($data["ttl"] ?? 3600),
        ];
        if ($type === "MX") {
            $formdata["record_mx"] = $this->mxPriority($data);
        }
        return $this->write("add_record", $formdata, "Record added: " . $type . " " . $host);
    }

    /**
     * Edit a record by its native id. PUT/POST edit_record.
     *
     * edit_record cannot rename a record, so a changed host (or a record that
     * can no longer be found) is handled as delete + add, exactly like the
     * nhanhoa_v3 domain module reconciler.
     */
    public function editRecord($record, $data = [], $domain = false)
    {
        if (empty($data) || empty($domain) || $record === "" || $record === null) {
            return false;
        }
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            return false;
        }
        $type = strtoupper((string) ($data["type"] ?? ""));
        $host = $this->toHost($data["name"] ?? "", $domain);
        $value = $this->buildValue($type, $data);
        if ($type === "" || $value === "") {
            $this->addError("Record type and value are required");
            return false;
        }

        $old = $this->findRecord($domain, (string) $record);
        $oldHost = $old ? $this->normHost((string) ($old["record_name"] ?? "")) : null;
        if ($old === false || $oldHost !== $host) {
            // Rename / unknown record: edit_record cannot move a name -> replace.
            if ($old !== false) {
                $this->write("delete_record", ["domain_name" => $domain, "record_id" => (string) $record]);
            }
            return $this->addRecord($domain, $data);
        }

        $formdata = [
            "domain_name" => $domain,
            "record_id" => (string) $record,
            "record_type" => $type,
            "record_value" => $value,
            "record_ttl" => $this->fixTtl($data["ttl"] ?? 3600),
        ];
        if ($type === "MX") {
            $formdata["record_mx"] = $this->mxPriority($data);
        }
        return $this->write("edit_record", $formdata, "Record updated: " . $type . " " . $host);
    }

    /**
     * Delete one record by its native id. DELETE delete_record.
     */
    public function deleteRecord($record, $domain = false)
    {
        if ($record === "" || $record === null || empty($domain)) {
            return false;
        }
        $domain = $this->getDomainName($domain);
        if (empty($domain)) {
            return false;
        }
        return $this->write("delete_record", [
            "domain_name" => $domain,
            "record_id" => (string) $record,
        ], "Record deleted: " . $record);
    }

    /**
     * Supported record types (static; ZoneDNS has no discovery endpoint).
     */
    public function getSupportedRecords()
    {
        return $this->supported_records;
    }

    /**
     * Default nameservers: configured list, else the Nhan Hoa platform fallback.
     */
    public function getDefaultNameservers()
    {
        if (!empty($this->name_servers)) {
            return $this->name_servers;
        }
        $nameservers = [];
        $configured = (string) $this->getCustom("default_ns");
        if ($configured !== "") {
            foreach (preg_split('/[\s,]+/', $configured) as $ns) {
                $ns = trim($ns);
                if ($ns !== "") {
                    $nameservers[] = ["name" => $ns, "ip" => ""];
                }
            }
        }
        if (empty($nameservers)) {
            foreach ($this->fallback_ns as $ns) {
                $nameservers[] = ["name" => $ns, "ip" => ""];
            }
        }
        $this->name_servers = $nameservers;
        return $nameservers;
    }

    public function getDomainLimit()
    {
        return $this->options["maxdomain"]["value"];
    }

    /* ------------------------------------------------------------------ */
    /* DNSSEC (DNSSECModule) — not supported by the ZoneDNS API            */
    /* ------------------------------------------------------------------ */

    /**
     * ZoneDNS exposes no DNSSEC command; report "no DNSSEC" rather than error.
     */
    public function getZoneSecurity($domain)
    {
        return [];
    }

    public function secureZone($domain)
    {
        $this->addError("dnssec_not_available");
        return false;
    }

    public function deleteZoneSecurity($domain)
    {
        return false;
    }

    public function rectifyZone($domain)
    {
        return false;
    }

    /* ------------------------------------------------------------------ */
    /* Visibility toggles                                                  */
    /* ------------------------------------------------------------------ */

    public function showBillingInfo()
    {
        return $this->options["hide_billing"]["value"] == "1" ? false : true;
    }

    public function showZoneManagement()
    {
        return $this->options["hide_zone_management"]["value"] == "1" ? false : true;
    }

    /* ------------------------------------------------------------------ */
    /* Record helpers                                                      */
    /* ------------------------------------------------------------------ */

    /**
     * Raw ZoneDNS record rows for a zone (data.domain_record[]), or [] on error.
     */
    protected function rawRecords($domain)
    {
        $resp = $this->get("list_record", ["domain_name" => $domain]);
        if (!$this->ok($resp)) {
            return [];
        }
        $data = $resp["data"] ?? [];
        if (isset($data["domain_record"]) && is_array($data["domain_record"])) {
            return $data["domain_record"];
        }
        // Defensive: some deployments return the list directly under data.
        return is_array($data) ? array_values(array_filter($data, "is_array")) : [];
    }

    /**
     * Locate one raw record row by its native id, or false when not present.
     * @return array|false
     */
    protected function findRecord($domain, $recordId)
    {
        foreach ($this->rawRecords($domain) as $rec) {
            if ((string) ($rec["record_id"] ?? "") === (string) $recordId) {
                return $rec;
            }
        }
        return false;
    }

    /**
     * Run a mutating ZoneDNS command, surfacing the API's own message.
     */
    protected function write($cmd, array $formdata, $info = "")
    {
        $resp = $this->get($cmd, $formdata);
        if ($this->ok($resp)) {
            if ($info !== "") {
                $this->addInfo($info);
            }
            return true;
        }
        $this->addError($this->errMsg($resp));
        return false;
    }

    /**
     * MX priority for the record_mx field (Nhan Hoa stores it separately from
     * record_value). Defaults to 10 when unset, matching nhanhoa_v3.
     */
    protected function mxPriority($data)
    {
        $priority = $data["priority"] ?? "";
        if ($priority === "" || $priority === null) {
            return 10;
        }
        return (int) $priority;
    }

    /**
     * Build a ZoneDNS record_value from HostBill record data.
     *
     * MX  : record_value is the mail host only (priority -> record_mx).
     * SRV : ZoneDNS has no priority field for SRV, so the whole
     *       "priority weight port target" is packed into record_value.
     * CAA : "flag tag \"value\"".
     * else: the content string as-is.
     */
    protected function buildValue($type, $data)
    {
        $content = $data["content"] ?? "";
        $priority = isset($data["priority"]) ? (int) $data["priority"] : 0;
        if ($type === "MX") {
            $target = is_array($content) ? ($content[0] ?? "") : $content;
            return trim((string) $target);
        }
        if ($type === "SRV") {
            if (is_array($content)) {
                $weight = $content[0] ?? 0;
                $port = $content[1] ?? 0;
                $target = $content[2] ?? "";
                return trim($priority . " " . $weight . " " . $port . " " . $target);
            }
            // Already a full value; prefix priority only when absent.
            $parts = preg_split('/\s+/', trim((string) $content));
            if (count($parts) >= 4) {
                return trim((string) $content);
            }
            return trim($priority . " " . (string) $content);
        }
        if ($type === "CAA") {
            if (is_array($content)) {
                $flag = $content[0] ?? 0;
                $tag = $content[1] ?? "issue";
                $val = $content[2] ?? "";
                return trim($flag . " " . $tag . ' "' . trim((string) $val, '"') . '"');
            }
            return (string) $content;
        }
        if (is_array($content)) {
            return (string) ($content[0] ?? "");
        }
        return (string) $content;
    }

    /**
     * Split a stored record_value back into HostBill's {content}. For SRV the
     * priority is stripped off the front so it is reported separately.
     */
    protected function contentOut($type, $value)
    {
        if ($type === "SRV") {
            $parts = preg_split('/\s+/', trim((string) $value));
            if (count($parts) >= 4 && is_numeric($parts[0])) {
                return implode(" ", array_slice($parts, 1));
            }
        }
        return (string) $value;
    }

    /**
     * Leading SRV priority from a packed "priority weight port target" value.
     */
    protected function srvPriority($value)
    {
        $parts = preg_split('/\s+/', trim((string) $value));
        return (count($parts) >= 4 && is_numeric($parts[0])) ? (int) $parts[0] : 0;
    }

    /* ------------------------------------------------------------------ */
    /* Misc helpers                                                        */
    /* ------------------------------------------------------------------ */

    /**
     * Resolve a domain name from a HostBill DNS domain id (or pass through a
     * literal name).
     */
    protected function getDomainName($domain)
    {
        if (is_string($domain) && !is_numeric($domain)) {
            return $domain;
        }
        $dnsman = HBLoader::LoadComponent("DnsManage");
        $dom = $dnsman->getDomain($this->account_details["id"] ?? null, $domain);
        if ($dom) {
            return $dom[DnsManage::COLUMN_DOMAIN_NAME];
        }
        return false;
    }

    /**
     * FQDN record name -> Nhan Hoa relative host ("@" for the apex).
     */
    protected function toHost($name, $domain)
    {
        $name = rtrim((string) $name, ".");
        $domain = rtrim((string) $domain, ".");
        if ($name === "" || $name === "@" || strcasecmp($name, $domain) === 0) {
            return "@";
        }
        if (substr($name, -strlen("." . $domain)) === "." . $domain) {
            $name = substr($name, 0, -strlen("." . $domain));
        }
        return $name === "" ? "@" : $name;
    }

    /**
     * Nhan Hoa relative host -> FQDN record name for HostBill display.
     */
    protected function fromHost($host, $domain)
    {
        $host = (string) $host;
        if ($host === "" || $host === "@") {
            return $domain;
        }
        if (strcasecmp(rtrim($host, "."), rtrim((string) $domain, ".")) === 0) {
            return $domain;
        }
        return $host . "." . $domain;
    }

    /**
     * Normalize a raw Nhan Hoa record_name to the "@"/relative form toHost emits,
     * so rename detection in editRecord compares like with like.
     */
    protected function normHost($host)
    {
        $host = rtrim((string) $host, ".");
        return $host === "" ? "@" : $host;
    }

    /**
     * Snap a TTL into the Nhan Hoa ZoneDNS range (300..86400).
     */
    protected function fixTtl($ttl)
    {
        $ttl = (int) $ttl;
        if ($ttl < 300) {
            return 300;
        }
        return $ttl > 86400 ? 86400 : $ttl;
    }
}

?>
