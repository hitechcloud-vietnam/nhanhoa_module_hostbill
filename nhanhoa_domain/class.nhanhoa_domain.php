<?php

/**
 * Nhan Hoa Domain — standalone .vn / international domain registrar module.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.1.0
 */

/**
 * Nhan Hoa (Nhân Hòa) registrar module — nhanhoa_domain.
 *
 * Talks to the Nhan Hoa reseller API (https://api.nhanhoa.com/, documented at
 * https://docs.nhanhoa.com/). A standalone, self-contained registrar module
 * built along the lines the HostBill SDK documents for `DomainModule`:
 *
 *   - the HTTP transport lives in lib/class.nhanhoa_domain_api.php and always
 *     returns an array, so no method has to guard against a null response;
 *   - every registrar call goes through api() and is checked with
 *     Nhanhoa_Domain_Api::ok() instead of ad-hoc `strtolower($r['status'])`;
 *   - the .vn / international split is decided once, in isVnDomain().
 *
 * What v3 fixes relative to v2
 *   - LockDomain() pointed both nameservers at ns1.
 *   - check_input() lowercased the key and then compared it to 'ownerName',
 *     so registrant-name validation never ran.
 *   - getDomainInfo(), Unlock24h(), ResendVerify() and SkipVerifyEmail() read
 *     $this->name, which is only populated on admin paths; they now use
 *     getCurrentDomainName().
 *   - updateNameServers() indexed the result of getNameServers() without
 *     checking it succeeded, and returned false without a message when
 *     nothing changed.
 *   - synchInfo() built its return array from an undefined variable on the
 *     error path.
 *   - getContactInfo() for international domains used end(explode(...)) and
 *     indexed response keys that are not always present.
 *   - the 1000 second cURL timeout is now 60.
 *
 * What v3 adds
 *   - ZoneDNS record management (DomainModuleDNS: list/add/edit/delete).
 *   - checkBackorderStatus() for .vn backorders.
 *   - listUnsignedDomains() — domains still missing their bản khai.
 *   - getAdministrativeDivisions() as a read-only registry reference.
 *
 * Administrative divisions (2025 reform — 2-tier addresses):
 *   Vietnam's 2025 administrative reform (sắp xếp đơn vị hành chính) ABOLISHED
 *   the district (quận/huyện) tier. Addresses are now two tiers —
 *   Tỉnh/Thành phố (province/city) → Xã/Phường (ward/commune) — directly, with
 *   no district in between. This module therefore treats province + ward as the
 *   primary address tiers and the district tier as OPTIONAL/legacy. See the
 *   "CONVERSION NOTE" block above makeContact() for the full mapping and the
 *   backward-compatibility rules. See also README.md → "Địa danh 2-tier".
 */

use Domain\nhanhoa_domain\ORM\ORM_domcontacts as DContacts;

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'class.nhanhoa_domain_api.php';

class nhanhoa_domain extends DomainModule implements
    Observer,
    DomainSuggestionsInterface,
    DomainPriceImport,
    DomainModuleListing,
    DomainLookupInterface,
    DomainPremiumInterface,
    DomainBulkLookupInterface,
    DomainModuleDNS
{
    /** Shared with nhanhoav2 on purpose: same rows, no re-sync after a switch. */
    const CONTACTS_TABLE = 'hb_domain_contacts_nhanhoa';

    /** TTL Nhan Hoa applies when a record does not carry one. */
    const DEFAULT_TTL = 300;

    protected $version = '1.1.0';

    protected $modname = 'Nhan Hoa Domain';

    protected $description = 'Nhan Hoa domain registrar module by Pho Tue SoftWare Solutions JSC';

    protected $info = [
        self::INFO_HEADER_JS => true,
        self::INFO_HAVEADMIN => true,   // admin/class.nhanhoa_domain_controller.php
        self::INFO_HAVETPL   => true,   // admin/*.tpl
    ];

    protected $lang = [
        'english' => [
            'nhanhoa_domainusername'  => 'User Name',
            'nhanhoa_domainpassword'  => 'Password',
            'nhanhoa_domainid'        => 'User ID',
            'nhanhoa_domaindebug_log' => 'Write API calls to nhanhoa_domain.log',
        ],
    ];

    protected $commands = [
        'Register',
        'Transfer',
        'Renew',
        'ContactInfo',
        'RegisterNameServers',
        'DNSmanagement',
        'getNameServers',
        'EppCode',
        'Unlock24h',
        'ResendVerify',
        'SkipVerifyEmail',
        'ManualRegister',
        'LockDomain',
        'Contract',
        'getStatusDomain',
        'checkBackorderStatus',
        'getDomainInfo',
        'updateRegistrarLock',
        'updateNameServers',
        'updateContactInfo',
        'Statusdomain',
        'backorderDomainVN',
        'getekyc',
        'getekycProfile',
        'listUnsignedDomains',
        'getAdministrativeDivisions',
    ];

    protected $clientCommands = [
        'ContactInfo',
        'DNSmanagement',
        'Unlock24h',
        'EmailForwarding',
        'RegisterNameServers',
        'EppCode',
        'Contract',
        'Statusdomain',
        'getStatusDomain',
        'getekyc',
        'getekycProfile',
    ];

    protected $configuration = [
        'username'  => ['value' => '', 'type' => 'input',    'default' => false],
        'password'  => ['value' => '', 'type' => 'password', 'default' => false],
        'id'        => ['value' => '', 'type' => 'input',    'default' => false],
        'debug_log' => ['value' => 0,  'type' => 'check',    'default' => 0,
                        'description' => 'Ghi toàn bộ request/response ra nhanhoa_domain.log trong thư mục module.'],
    ];

    /**
     * Core reads this before Register() to decide whether the registrant is a
     * company; keeping the v2 default avoids an undefined-index on order forms
     * that have not collected contacts yet.
     */
    protected $domain_contacts = [
        'registrant' => ['firstname' => ''],
    ];

    /** @var Nhanhoa_Domain_Api|null */
    private $apiClient = null;

    public function __construct()
    {
        parent::__construct();
        #licensesnippet#
        $this->ensureContactsTable();
    }

    public function upgrade($old)
    {
        // No schema change yet; ensureContactsTable() is idempotent.
    }

    // ─── Transport ───────────────────────────────────────────────────────────

    /**
     * Lazily built so a module instance that never talks to the registrar
     * (config screen, menu rendering) costs nothing.
     */
    protected function api()
    {
        if ($this->apiClient === null) {
            $this->apiClient = new Nhanhoa_Domain_Api(
                $this->configuration['username']['value'],
                $this->configuration['password']['value'],
                $this->configuration['id']['value'],
                !empty($this->configuration['debug_log']['value']),
                __DIR__ . DIRECTORY_SEPARATOR . 'nhanhoa_domain.log'
            );
        }

        return $this->apiClient;
    }

    /**
     * One registrar call. Kept protected and named `get()` so the method bodies
     * ported from v1/v2 read the same.
     */
    protected function get($cmd, $formdata = [], $postfields = [])
    {
        return $this->api()->call($cmd, (array) $formdata, (array) $postfields);
    }

    public function testConnection()
    {
        $result = $this->get('get_balance');
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo('Số dư tài khoản ' . number_format((float) ($result['balance'] ?? 0)));
        return true;
    }

    // ─── Domain identity helpers ─────────────────────────────────────────────

    public function getCurrentDomainName()
    {
        if (!empty($this->name)) {
            return $this->name;
        }

        if (!empty($this->options['sld']) && !empty($this->options['tld'])) {
            return $this->options['sld'] . '.' . $this->options['tld'];
        }

        return '';
    }

    public function isVnDomain($domainName = '')
    {
        $domainName = $domainName ?: $this->getCurrentDomainName();
        return (bool) preg_match('/\.vn$/i', $domainName);
    }

    /** `register_domainvn` vs `register_domain`, decided in one place. */
    protected function cmdFor($base)
    {
        return $this->isVnDomain() ? $base . 'vn' : $base;
    }

    /**
     * domainNameList / domainName / domainExt / domainYear — the block every
     * write command starts with.
     */
    protected function domainFormdata()
    {
        return [
            'domainNameList' => $this->getCurrentDomainName(),
            'domainName'     => $this->options['sld'] ?? '',
            'domainExt'      => $this->options['tld'] ?? '',
            'domainYear'     => $this->options['numyears'] ?? 1,
        ];
    }

    /**
     * domainDNS1..5 with their resolved IPs.
     *
     * @param array $override ns1..ns5 to send instead of $this->options
     */
    protected function nameserverFormdata(array $override = [])
    {
        $formdata = [];
        for ($i = 1; $i <= 5; $i++) {
            $ns = $override['ns' . $i] ?? ($this->options['ns' . $i] ?? '');
            if ($ns === '' || $ns === null) {
                continue;
            }
            $formdata['domainDNS' . $i] = $ns;
            $formdata['domainIP' . $i]  = gethostbyname($ns);
        }

        return $formdata;
    }

    /** Nameservers currently set on the order form, in order, gaps removed. */
    protected function requestedNameServers()
    {
        $list = [];
        for ($i = 1; $i <= 5; $i++) {
            $ns = $this->options['ns' . $i] ?? '';
            if ($ns !== '') {
                $list[] = $ns;
            }
        }

        return $list;
    }

    // ─── Contact storage ─────────────────────────────────────────────────────

    protected function ensureContactsTable()
    {
        if (empty($this->db)) {
            return false;
        }

        $sql = 'CREATE TABLE IF NOT EXISTS `' . self::CONTACTS_TABLE . '` (
            `id` int(10) NOT NULL AUTO_INCREMENT,
            `client_id` int(11) DEFAULT NULL,
            `domain_id` int(11) DEFAULT NULL,
            `registrant` tinyint(1) DEFAULT NULL,
            `esigned` int(11) DEFAULT NULL,
            `sync` tinyint(1) DEFAULT NULL,
            `type` varchar(11) DEFAULT \'0\',
            `handle` varchar(55) NOT NULL DEFAULT \'\',
            `name` varchar(255) DEFAULT NULL,
            `nationalid` varchar(55) DEFAULT \'\',
            `householdsid` varchar(100) DEFAULT NULL,
            `taxid` varchar(55) DEFAULT \'\',
            `position` varchar(100) DEFAULT NULL,
            `phonenumber` varchar(35) DEFAULT NULL,
            `email` varchar(100) DEFAULT NULL,
            `gender` varchar(10) DEFAULT NULL,
            `birthday` varchar(55) DEFAULT NULL,
            `country` varchar(35) DEFAULT NULL,
            `state` varchar(255) DEFAULT NULL,
            `city` varchar(255) DEFAULT NULL,
            `ward` varchar(255) DEFAULT NULL,
            `address1` varchar(255) DEFAULT \'\',
            `address2` varchar(255) DEFAULT \'\',
            `postcode` varchar(35) DEFAULT \'\',
            `created_at` timestamp NULL DEFAULT NULL,
            `updated_at` timestamp NULL DEFAULT NULL,
            `notes` text DEFAULT NULL,
            `language` varchar(35) DEFAULT \'\',
            PRIMARY KEY (`id`),
            KEY `code` (`handle`),
            KEY `iddp` (`nationalid`),
            KEY `taxid` (`taxid`),
            KEY `handle` (`handle`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci';

        try {
            $this->db->exec($sql);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_domain ensureContactsTable', $e->getMessage());
            return false;
        }

        return true;
    }

    protected function buildLocalContactHandle($type)
    {
        return sprintf('NHANHOA-%s-%s', (string) ($this->domain_id ?: 0), strtoupper($type));
    }

    protected function normalizeStoredContactName(array $contact)
    {
        if (!empty($contact['companyname'])) {
            return $contact['companyname'];
        }

        if (!empty($contact['lastname']) && !empty($contact['firstname'])) {
            return trim($contact['lastname'] . ' ' . $contact['firstname']);
        }

        return $contact['firstname'] ?? '';
    }

    protected function syncContactsToSql(array $contacts)
    {
        if (empty($this->domain_id)) {
            return $contacts;
        }

        $this->ensureContactsTable();

        foreach (['registrant', 'admin', 'tech', 'billing'] as $type) {
            if (empty($contacts[$type]) || !is_array($contacts[$type])) {
                continue;
            }

            $contact = $contacts[$type];
            $handle  = $this->buildLocalContactHandle($type);

            try {
                $record = DContacts::where('domain_id', $this->domain_id)
                    ->where('handle', $handle)
                    ->first();

                if (!$record) {
                    $record            = new DContacts();
                    $record->domain_id = $this->domain_id;
                    $record->handle    = $handle;
                }

                $record->client_id   = $this->client_data['id'] ?? null;
                $record->registrant  = $type === 'registrant' ? 1 : 0;
                $record->sync        = 1;
                $record->type        = !empty($contact['companyname']) ? 'org' : 'ind';
                $record->name        = $this->normalizeStoredContactName($contact);
                $record->nationalid  = $contact['nationalid'] ?? '';
                $record->taxid       = $contact['taxid'] ?? '';
                $record->position    = $contact['position'] ?? ($contact['Chức danh'] ?? '');
                $record->phonenumber = $contact['phonenumber'] ?? '';
                $record->email       = $contact['email'] ?? ($contact['mail'] ?? '');
                $record->gender      = $contact['gender'] ?? '';
                $record->birthday    = $contact['birthday'] ?? '';
                $record->country     = $contact['country'] ?? 'VN';
                $record->state       = $contact['state'] ?? '';
                $record->city        = $contact['city'] ?? '';
                $record->ward        = $contact['ward'] ?? '';
                $record->address1    = $contact['address1'] ?? '';
                $record->address2    = $contact['address2'] ?? '';
                $record->postcode    = $contact['postcode'] ?? '';
                $record->notes       = json_encode($contact, JSON_UNESCAPED_UNICODE);
                $record->save();

                $contacts[$type]['handle'] = $record->handle;
                $contacts[$type]['type']   = $record->type;
            } catch (Exception $e) {
                HBDebug::debug('nhanhoa_domain syncContactsToSql', [
                    'domain_id' => $this->domain_id,
                    'type'      => $type,
                    'error'     => $e->getMessage(),
                ]);
            }
        }

        return $contacts;
    }

    protected function getStoredContacts()
    {
        if (empty($this->domain_id)) {
            return [];
        }

        $this->ensureContactsTable();

        try {
            $rows = DContacts::where('domain_id', $this->domain_id)->get();
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_domain getStoredContacts', $e->getMessage());
            return [];
        }

        if (!$rows || !$rows->count()) {
            return [];
        }

        $contacts = [];
        foreach ($rows as $row) {
            $type = $row->registrant ? 'registrant' : strtolower(str_replace(
                sprintf('NHANHOA-%s-', (string) $this->domain_id),
                '',
                $row->handle
            ));

            if (!in_array($type, ['registrant', 'admin', 'tech', 'billing'], true)) {
                continue;
            }

            $contact = [
                'handle'      => $row->handle,
                'type'        => $row->type,
                'companyname' => $row->type === 'org' ? $row->name : '',
                'firstname'   => $row->type === 'ind' ? $row->name : '',
                'lastname'    => '',
                'email'       => $row->email,
                'address1'    => $row->address1,
                'address2'    => $row->address2,
                'city'        => $row->city,
                'state'       => $row->state,
                'ward'        => $row->ward,
                'country'     => $row->country,
                'postcode'    => $row->postcode,
                'phonenumber' => $row->phonenumber,
                'nationalid'  => $row->nationalid,
                'taxid'       => $row->taxid,
                'birthday'    => $row->birthday,
                'gender'      => $row->gender,
                'position'    => $row->position,
            ];

            if (!empty($row->notes)) {
                $decoded = json_decode($row->notes, true);
                if (is_array($decoded)) {
                    $contact = array_merge($decoded, $contact);
                }
            }

            $contacts[$type] = $contact;
        }

        return $contacts;
    }

    protected function persistAndReturnContacts(array $contacts)
    {
        $this->syncContactsToSql($contacts);
        $stored = $this->getStoredContacts();

        return !empty($stored) ? $stored : $contacts;
    }

    public function getDomainContacts($forceRemoteSync = false)
    {
        $stored = $this->getStoredContacts();
        if (!$forceRemoteSync && !empty($stored)) {
            return $stored;
        }

        return $this->getContactInfo();
    }

    public function getDeclarationStatusLabel($statusValue)
    {
        $statusLabels = [
            0 => 'Đang chờ duyệt',
            1 => 'Hợp lệ',
            2 => 'Không hợp lệ',
            3 => 'Chưa có hồ sơ',
        ];

        if ($statusValue === null || $statusValue === '') {
            return 'Không xác định';
        }

        return $statusLabels[(int) $statusValue] ?? 'Không xác định';
    }

    /**
     * Prefix a province/city with its administrative type.
     *
     * Carried over unchanged from v2 — this list reflects the CURRENT
     * administrative divisions (Huế is a centrally-governed city). The
     * registrar documentation is still on the old list; do not sync this to it.
     */
    private function formatCityName($cityName)
    {
        $cityName = trim((string) $cityName);
        if ($cityName === '') {
            return '';
        }

        if (strtoupper($cityName) === 'TP HCM') {
            return 'Thành phố Hồ Chí Minh';
        }

        $majorCities = ['Hồ Chí Minh', 'Hải Phòng', 'Cần Thơ', 'Hà Nội', 'Đà Nẵng', 'Huế'];

        return in_array($cityName, $majorCities, true)
            ? 'Thành phố ' . $cityName
            : 'Tỉnh ' . $cityName;
    }

    /**
     * ═══════════════════════════════════════════════════════════════════════
     * CONVERSION NOTE — Vietnam 2025 administrative reform (3-tier → 2-tier)
     * ═══════════════════════════════════════════════════════════════════════
     * Vietnam's 2025 reform (sắp xếp đơn vị hành chính) ABOLISHED the district
     * (quận/huyện) tier. Addresses are now:
     *
     *     Tỉnh / Thành phố (province/city)  →  Xã / Phường (ward/commune)
     *
     * i.e. the ward/commune now sits DIRECTLY under the province/city, with no
     * district in between.
     *
     * Field mapping (HostBill contact → Nhan Hoa registrar payload):
     *   HostBill state → ownerCity      (tỉnh / thành phố)   — PRIMARY
     *   HostBill ward  → ownerTown      (phường / xã)        — PRIMARY (now)
     *   HostBill city  → ownerDistrict  (quận / huyện)       — LEGACY / optional
     *
     * What changed here vs. the old 3-tier mapping:
     *   - province + ward are the primary tiers; a district is NO LONGER required
     *     for a new registration.
     *   - ownerDistrict is still DOCUMENTED as a required field by the registrar
     *     (domainvn.md marks ownerDistrict √; ownerTown is optional). Because the
     *     live API may still reject an empty ownerDistrict, it is populated
     *     DEFENSIVELY: the stored legacy district is used when present, otherwise
     *     it falls back to the ward value so the required field is never empty.
     *     (Live acceptance of a ward value / empty value in the district slot
     *     could not be verified offline — see README.md → "Địa danh 2-tier".)
     *
     * How existing stored 3-tier addresses map to the new model:
     *   - a contact saved before the reform still carries its district in the
     *     HostBill "city" field; that value is kept and sent as ownerDistrict, so
     *     nothing breaks (backward compatible).
     *   - new 2-tier contacts have an empty "city" (district); the ward is sent
     *     as both ownerTown and the defensive ownerDistrict.
     *   - ADMINS SHOULD re-verify stored registrant addresses after migrating to
     *     the new division list (drop the obsolete district, keep the ward under
     *     its province/city).
     *
     * list_district is retained only as a LEGACY lookup (see
     * getAdministrativeDivisions()); the registrar exposes no province→ward
     * direct listing.
     * ═══════════════════════════════════════════════════════════════════════
     *
     * Build the registrar's contact fields from HostBill's contact arrays.
     */
    protected function makeContact($type = 'registrant', $cdata = false)
    {
        $formdata   = [];
        $registrant = $this->options['registrant'] ?? [];

        if (!empty($registrant['companyname'])) {
            $formdata['owner_type']    = 1;
            $formdata['congty_ten']    = $registrant['companyname'];
            $formdata['congty_mst']    = $registrant['taxid'] ?? '';
            $formdata['congty_diachi'] = implode(', ', array_filter([
                $registrant['address1'] ?? '',
                $registrant['city'] ?? '',
                $registrant['state'] ?? '',
                $registrant['country'] ?? '',
            ]));
            $formdata['congty_dt'] = $this->formatPhone($registrant);
        }

        $prefixes = [
            'registrant' => 'owner',
            'admin'      => 'admin',
            'tech'       => 'tech',
            'billing'    => 'billing',
        ];

        if (isset($prefixes[$type])) {
            $c      = $this->options[$type] ?? [];
            $prefix = $prefixes[$type];

            if ($type === 'registrant') {
                $formdata['ord_owner_type'] = !empty($c['companyname']) ? 1 : 0;
                $formdata['ownerName']      = !empty($c['companyname'])
                    ? $c['companyname']
                    : trim(($c['lastname'] ?? '') . ' ' . ($c['firstname'] ?? ''));
                $formdata['ownerMail'] = $c['email'] ?? '';
            } else {
                $formdata[$prefix . 'Name'] = trim(($c['lastname'] ?? '') . ' ' . ($c['firstname'] ?? ''));
            }

            // 2-tier address (2025 reform): province (City) + ward (Town) are the
            // primary tiers; district is legacy. See the CONVERSION NOTE above.
            $ward        = $c['ward'] ?? '';
            $legacyDist  = $c['city'] ?? ''; // HostBill "city" held the old quận/huyện

            $formdata[$prefix . 'Address']  = $c['address1'] ?? '';
            $formdata[$prefix . 'Town']     = $ward;              // phường / xã (primary)
            // ownerDistrict is still marked required by the registrar API even
            // though the district tier was abolished; populate it defensively so
            // new 2-tier addresses (no district) are still accepted.
            $formdata[$prefix . 'District'] = $legacyDist !== '' ? $legacyDist : $ward;
            $formdata[$prefix . 'City']     = $c['state'] ?? '';  // tỉnh / thành (primary)
            $formdata[$prefix . 'Country']  = ($c['country'] ?? '') === 'VN' ? 'Vietnam' : ($c['country'] ?? '');
            $formdata[$prefix . 'Email']    = $c['email'] ?? '';
            $formdata[$prefix . 'Gender']   = $c['gender'] ?? '';
            $formdata[$prefix . 'Birthday'] = !empty($c['birthday']) ? $c['birthday'] : '01/01/1990';
            $formdata[$prefix . 'PersonID'] = $c['nationalid'] ?? '';
            $formdata[$prefix . 'Phone']    = $this->formatPhone($c);
        }

        // International domains carry a single flat contact block.
        if ($type === 'all') {
            $c = $registrant;
            $formdata['domain_realname'] = trim(($c['lastname'] ?? '') . ' ' . ($c['firstname'] ?? ''));
            $formdata['domain_address']  = implode(', ', array_filter([$c['address1'] ?? '', $c['city'] ?? '']));
            $formdata['domain_city']     = $c['state'] ?? '';
            $formdata['domain_country']  = ($c['country'] ?? '') === 'VN' ? 'Viet Nam' : ($c['country'] ?? '');
            $formdata['domain_company']  = $c['companyname'] ?? '';
            $formdata['domain_email']    = $c['email'] ?? '';
            $formdata['domain_username'] = $c['email'] ?? '';
            $formdata['domain_phone']    = $this->formatPhone($c);
        }

        return $formdata;
    }

    /** `+84.916193084`, falling back to whatever the client entered. */
    protected function formatPhone(array $contact)
    {
        $voice = $contact['phonenumber'] ?? '';
        $phone = Utilities::get_phone_info($voice, $contact['country'] ?? '');
        if (!empty($phone['number'])) {
            $voice = '+' . $phone['ccode'] . '.' . $phone['number'];
        }

        return $voice;
    }

    // ─── Availability ────────────────────────────────────────────────────────

    public function checkAvailability()
    {
        $result = $this->get('whois', [
            'domain' => $this->options['sld'] ?? '',
            'ext'    => $this->options['tld'] ?? '',
            'type'   => '0',
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result, 'Wrong response from the server while checking availability'));
            return false;
        }

        $return = ['available' => true];
        if (!empty($result['premium'])) {
            $return['premium'] = [
                'register' => $result['premium_price'] ?? 0,
                'currency' => 'VND',
            ];
        }

        return $return;
    }

    public function lookupDomain($sld, $tld, $settings = [])
    {
        $return = ['available' => false];
        $result = $this->get('whois', [
            'domain' => $sld,
            'ext'    => $tld,
            'type'   => '0',
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            return $return;
        }

        if (isset($result['domain_status']) && (int) $result['domain_status'] === 0) {
            $return['available'] = true;
            if (!empty($result['premium'])) {
                $return['premium'] = [
                    'register' => $result['premium_price'] ?? 0,
                    'currency' => 'VND',
                ];
            }
        }

        return $return;
    }

    public function lookupBulkDomains($sld, $tlds = [])
    {
        $result = $this->get('whois_multi', [
            'domain' => $sld,
            'ext'    => implode(',', (array) $tlds),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result) || empty($result['data'])) {
            return false;
        }

        $return = [];
        foreach ($result['data'] as $tld) {
            if (empty($tld['ext'])) {
                continue;
            }
            $entry = ['available' => isset($tld['domain_status']) && (int) $tld['domain_status'] === 0];
            if (!empty($tld['premium'])) {
                $entry['premium'] = [
                    'register' => $tld['premium_price'] ?? 0,
                    'currency' => 'VND',
                ];
            }
            $return['.' . ltrim($tld['ext'], '.')] = $entry;
        }

        return $return;
    }

    public function suggestDomains($sld, $tld, $settings = [])
    {
        // Nhan Hoa exposes no suggestion endpoint; core falls back to its own.
        return [];
    }

    // ─── Register / Transfer / Renew ─────────────────────────────────────────

    public function ManualRegister()
    {
        $this->options['manual_reg'] = true;

        return $this->Register();
    }

    public function Register()
    {
        $availability = $this->lookupDomain($this->options['sld'], '.' . $this->options['tld']);
        if (empty($availability['available'])) {
            $this->addError('Tên miền không thể đăng ký');
            return false;
        }

        if (!$this->passesRegistrationPolicy()) {
            return false;
        }

        $formdata = array_merge(
            $this->domainFormdata(),
            ['idprotection' => $this->options['idprotection'] ?? 0],
            $this->nameserverFormdata(),
            $this->contactFormdata()
        );

        $this->updateExtended([
            'registrant' => $this->domain_contacts['registrant'] ?? [],
            'admin'      => $this->domain_contacts['admin'] ?? [],
            'tech'       => $this->domain_contacts['tech'] ?? [],
            'billing'    => $this->domain_contacts['billing'] ?? [],
        ]);

        $check = $this->check_input($formdata);
        if ($check['status'] !== 'success') {
            $this->addError($check['msg']);
            return false;
        }

        $result = $this->get($this->cmdFor('register_domain'), $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addDomain('Active');
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã gửi yêu cầu đăng ký tên miền.'));

        return true;
    }

    /**
     * .vn TLDs that need the registrant checked before the order is sent, and
     * the two that are restricted to one registrant kind.
     */
    protected function passesRegistrationPolicy()
    {
        $needsReview = [
            'gov.vn', 'name.vn', 'vn', 'id.vn', 'edu.vn',
            'thanhphohochiminh.vn', 'angiang.vn', 'bacgiang.vn', 'backan.vn', 'baclieu.vn', 'bacninh.vn',
            'baria-vungtau.vn', 'bentre.vn', 'binhdinh.vn', 'binhduong.vn', 'binhphuoc.vn', 'binhthuan.vn',
            'camau.vn', 'cantho.vn', 'caobang.vn', 'daklak.vn', 'daknong.vn', 'danang.vn', 'dienbien.vn',
            'dongnai.vn', 'dongthap.vn', 'gialai.vn', 'hagiang.vn', 'haiduong.vn', 'haiphong.vn', 'hanam.vn',
            'hanoi.vn', 'hatay.vn', 'hatinh.vn', 'haugiang.vn', 'hoabinh.vn', 'hungyen.vn', 'khanhhoa.vn',
            'kiengiang.vn', 'kontum.vn', 'laichau.vn', 'lamdong.vn', 'langson.vn', 'laocai.vn', 'longan.vn',
            'namdinh.vn', 'nghean.vn', 'ninhbinh.vn', 'ninhthuan.vn', 'phutho.vn', 'phuyen.vn', 'quangbinh.vn',
            'quangnam.vn', 'quangngai.vn', 'quangninh.vn', 'quangtri.vn', 'soctrang.vn', 'sonla.vn', 'tayninh.vn',
            'thaibinh.vn', 'thainguyen.vn', 'thanhhoa.vn', 'thuathienhue.vn', 'tiengiang.vn', 'travinh.vn',
            'tuyenquang.vn', 'vinhlong.vn', 'vinhphuc.vn', 'yenbai.vn', 'org.vn',
        ];

        $tld       = $this->options['tld'] ?? '';
        $isCompany = !empty($this->domain_contacts['registrant']['companyname']);

        if (in_array($tld, $needsReview, true) && empty($this->options['manual_reg'])) {
            $this->addError('Cần kiểm tra thông tin chủ thể trước khi đăng ký.');
            return false;
        }

        if ($tld === 'org.vn' && !$isCompany) {
            $this->addError('Đối tượng đăng ký phải là công ty/tổ chức.');
            return false;
        }

        if ($tld === 'name.vn' && $isCompany) {
            $this->addError('Đối tượng đăng ký phải là cá nhân.');
            return false;
        }

        return true;
    }

    /** Four contact blocks for .vn, one flat block for everything else. */
    protected function contactFormdata()
    {
        if (!$this->isVnDomain()) {
            return $this->makeContact('all');
        }

        return array_merge(
            $this->makeContact('registrant'),
            $this->makeContact('admin'),
            $this->makeContact('tech'),
            $this->makeContact('billing')
        );
    }

    public function Transfer()
    {
        $formdata = array_merge(
            $this->domainFormdata(),
            [
                'idprotection' => $this->options['idprotection'] ?? 0,
                'auth-code'    => $this->options['epp_code'] ?? '',
            ],
            $this->nameserverFormdata(),
            $this->contactFormdata()
        );

        $result = $this->get($this->cmdFor('transfer_domain'), $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addDomain('Pending Transfer');
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã gửi yêu cầu chuyển tên miền.'));

        return true;
    }

    public function Renew()
    {
        $formdata               = $this->domainFormdata();
        $formdata['domainYear'] = $this->period;

        $result = $this->get($this->cmdFor('renew_domain'), $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        if ($this->status == HBC\DOMAIN_STATE_EXPIRED) {
            $this->addDomain('Expired');
        }

        $this->addPeriod();
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã gia hạn tên miền.'));
        $this->addDomain('Active');

        return true;
    }

    /**
     * .vn backorder. The registrar reuses the ordinary register payload, which
     * is why this reads like Register().
     */
    public function backorderDomainVN()
    {
        $formdata = array_merge(
            $this->domainFormdata(),
            ['idprotection' => $this->options['idprotection'] ?? 0],
            $this->nameserverFormdata(),
            $this->contactFormdata()
        );

        $check = $this->check_input($formdata);
        if ($check['status'] !== 'success') {
            $this->addError($check['msg']);
            return false;
        }

        $result = $this->get('backorder_domainvn', $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã gửi yêu cầu giữ chỗ tên miền.'));

        return true;
    }

    /**
     * Backorder progress for a .vn domain. Declared as a command in v2 but
     * never implemented, so the admin button did nothing.
     */
    public function checkBackorderStatus()
    {
        if (!$this->isVnDomain()) {
            $this->addError('Backorder chỉ áp dụng cho tên miền .vn');
            return false;
        }

        $result = $this->get('check_backorder_domainvn', [
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã lấy trạng thái giữ chỗ tên miền.'));

        return $result;
    }

    // ─── Nameservers ─────────────────────────────────────────────────────────

    public function getNameServers()
    {
        $result = $this->get($this->cmdFor('get_infodomain'), [
            'is_whmcs'   => '1',
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $content = $result['content'] ?? [];
        $keys    = $this->isVnDomain()
            ? ['subDomainName1', 'subDomainName2', 'subDomainName3', 'subDomainName4', 'subDomainName5']
            : ['dns_inter', 'dns_inter2', 'dns_inter3', 'dns_inter4', 'dns_inter5'];

        $data = [];
        foreach ($keys as $key) {
            if (!empty($content[$key])) {
                $data[] = $content[$key];
            }
        }

        return $data;
    }

    public function updateNameServers()
    {
        $current   = $this->getNameServers();
        $requested = $this->requestedNameServers();

        if ($current === false) {
            // getNameServers() already reported why.
            return false;
        }

        $change = $this->diffNameServers($current, $requested);
        if (empty($change)) {
            $this->addInfo('Name server không thay đổi.');
            return true;
        }

        $formdata = array_merge($this->domainFormdata(), $this->nameserverFormdata());
        $result   = $this->get($this->cmdFor('change_dnsdomain'), $formdata);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->logAction(['action' => 'Change Nameserver', 'result' => false, 'change' => $change, 'error' => true]);
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->logAction(['action' => 'Change Nameserver', 'result' => true, 'change' => $change, 'error' => false]);
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã cập nhật name server.'));

        return true;
    }

    /** Per-slot before/after, for the domain log. */
    protected function diffNameServers(array $current, array $requested)
    {
        $change = [];
        $slots  = max(count($current), count($requested));

        for ($i = 0; $i < $slots; $i++) {
            $from = $current[$i] ?? '';
            $to   = $requested[$i] ?? '';
            if ($from === $to) {
                continue;
            }
            $change[] = [
                'from' => $from === '' ? 'empty' : $from,
                'to'   => $to === '' ? 'empty' : $to,
                'name' => 'Name Server ' . ($i + 1),
            ];
        }

        return $change;
    }

    /**
     * Park the domain on the house nameservers.
     *
     * v2 sent ns1.photuesoftware.vn twice; the second slot is ns2.
     */
    public function LockDomain()
    {
        $house   = ['ns1' => 'ns1.photuesoftware.vn', 'ns2' => 'ns2.photuesoftware.vn'];
        $current = $this->getNameServers();
        if ($current === false) {
            return false;
        }

        $change = $this->diffNameServers($current, array_values($house));
        if (empty($change)) {
            $this->addInfo('Tên miền đã trỏ về name server khoá.');
            return true;
        }

        $formdata = array_merge($this->domainFormdata(), $this->nameserverFormdata($house));
        $result   = $this->get($this->cmdFor('change_dnsdomain'), $formdata);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->logAction(['action' => 'Lock Domain', 'result' => false, 'change' => $change, 'error' => true]);
            $this->addError(Nhanhoa_Domain_Api::msg($result, 'Đã xảy ra lỗi, vui lòng thử lại'));
            return false;
        }

        $this->logAction(['action' => 'Lock Domain', 'result' => true, 'change' => $change, 'error' => false]);
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã khoá domain theo yêu cầu'));

        return true;
    }

    // ─── Contacts on the registrar ───────────────────────────────────────────

    public function getContactInfo()
    {
        $result = $this->get($this->cmdFor('get_infodomain'), [
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (Nhanhoa_Domain_Api::ok($result) && !empty($result['content'])) {
            $contact = $this->isVnDomain()
                ? $this->mapVnContacts($result['content'])
                : $this->mapInternationalContacts($result['content']);

            if (!empty($contact)) {
                return $this->persistAndReturnContacts($contact);
            }
        } elseif (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
        }

        $stored = $this->getStoredContacts();

        return !empty($stored) ? $stored : false;
    }

    /**
     * The .vn payload keys are literal `newDomain.*` strings, and the
     * province/district/ward triple lands in state/city/ward — see makeContact().
     */
    protected function mapVnContacts(array $r)
    {
        $g = static function ($key, $default = '') use ($r) {
            return isset($r[$key]) && $r[$key] !== null ? $r[$key] : $default;
        };

        $country = $g('ownerCountry') ?: 'VN';
        $address = [
            'ward'    => $g('ownerTownList'),
            'city'    => $g('ownerDistrictList'),
            'state'   => $this->formatCityName($g('ownerProvinceList')),
            'country' => $country,
        ];

        $nameservers = [];
        for ($i = 1; $i <= 4; $i++) {
            if ($g('subDomainName' . $i) !== '') {
                $nameservers['NS' . $i]    = $g('subDomainName' . $i);
                $nameservers['IP NS' . $i] = $g('subDomainIp' . $i);
            }
        }

        $contact = [];

        if ((int) $g('ord_owner_type', 0) === 1) {
            $contact['registrant'] = array_merge([
                'companyname'   => $g('newDomain.ownerName'),
                'Tên Giao Dịch' => $g('newDomain.transactionName'),
                'taxid'         => $g('newDomain.ownerTax'),
                'mail'          => $g('newDomain.ownerMail'),
                'firstname'     => $g('newDomain.adminName'),
                'Chức danh'     => $g('newDomain.admin2Position'),
                'birthday'      => $g('newDomain.adminbirthDate'),
                'nationalid'    => $g('newDomain.admin2IDPP'),
                'phonenumber'   => $g('newDomain.ownerPhone'),
                'address1'      => $g('newDomain.ownerAddress'),
            ], $address, $nameservers);
        } else {
            $contact['registrant'] = array_merge([
                'firstname'   => $g('newDomain.ownerName'),
                'birthday'    => $g('newDomain.ownerbirthDate'),
                'nationalid'  => $g('newDomain.adminIDPP'),
                'gender'      => $g('newDomain.adminGender'),
                'email'       => $this->options['registrant']['email'] ?? $g('newDomain.ownerMail'),
                'phonenumber' => $g('newDomain.ownerPhone'),
                'address1'    => $g('newDomain.ownerAddress'),
            ], $address, $nameservers);
        }

        $contact['admin'] = array_merge([
            'firstname'   => $g('newDomain.adminName'),
            'birthday'    => $g('newDomain.adminbirthDate'),
            'nationalid'  => $g('newDomain.adminIDPP'),
            'gender'      => $g('newDomain.adminGender'),
            'email'       => $g('newDomain.adminEmail'),
            'phonenumber' => $g('newDomain.ownerPhone'),
            'address1'    => $g('newDomain.ownerAddress'),
        ], $address);

        $contact['tech'] = [
            'firstname'   => $g('newDomain.techName'),
            'birthday'    => $g('newDomain.techbirthDate'),
            'nationalid'  => $g('newDomain.techIDPP'),
            'gender'      => $g('newDomain.techGender', $g('newDomain.adminGender')),
            'email'       => $g('newDomain.techEmail', $g('newDomain.adminEmail')),
            'phonenumber' => $g('newDomain.techPhone', $g('newDomain.ownerPhone')),
            'address1'    => $g('techAddress'),
            'ward'        => $g('techTownList'),
            'city'        => $g('techDistrictList'),
            'state'       => $this->formatCityName($g('techProvinceList')),
            'country'     => $country,
        ];

        $contact['billing'] = [
            'firstname'   => $g('cus_payment_realname'),
            'birthday'    => $g('newDomain.adminbirthDate'),
            'email'       => $g('cus_payment_email'),
            'phonenumber' => $g('cus_payment_phone'),
            'address1'    => $g('cus_payment_address'),
            'ward'        => $g('billingTownList'),
            'city'        => $g('billingDistrictList'),
            'state'       => $this->formatCityName($g('billingProvinceList')),
            'country'     => $g('adminCountry') ?: 'VN',
        ];

        return $contact;
    }

    protected function mapInternationalContacts(array $r)
    {
        $realname = trim((string) ($r['domain_realname'] ?? ''));
        $parts    = $realname === '' ? [] : preg_split('/\s+/', $realname);
        $first    = $parts ? array_pop($parts) : '';
        $last     = implode(' ', $parts);

        $addressParts = array_map('trim', explode(',', (string) ($r['domain_address'] ?? '')));
        $addressParts = array_values(array_filter($addressParts, 'strlen'));
        $district     = count($addressParts) > 1 ? array_pop($addressParts) : '';
        $street       = implode(', ', $addressParts);

        $base = [
            'firstname'   => $first,
            'lastname'    => $last,
            'email'       => $r['domain_email'] ?? '',
            'address1'    => $street,
            'city'        => $district,
            'state'       => $r['domain_city'] ?? '',
            'country'     => !empty($r['domain_country']) ? $r['domain_country'] : 'VN',
            'phonenumber' => $r['domain_phone'] ?? '',
        ];

        return [
            'registrant' => array_merge($base, ['companyname' => $r['domain_company'] ?? '']),
            'admin'      => $base,
            'tech'       => $base,
            'billing'    => $base,
        ];
    }

    public function updateContactInfo()
    {
        $formdata = array_merge(
            ['is_whmcs' => 1],
            $this->domainFormdata(),
            $this->nameserverFormdata(),
            $this->contactFormdata()
        );

        $result = $this->get($this->cmdFor('change_infodomain'), $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->logAction(['action' => 'Update Contact Info', 'result' => false, 'change' => false, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã cập nhật thông tin tên miền.'));
        $this->logAction(['action' => 'Update Contact Info', 'result' => true, 'change' => false, 'error' => false]);

        return true;
    }

    // ─── Lock / EPP / ID protection ──────────────────────────────────────────

    public function getRegistrarLock()
    {
        $result = $this->get('domain_tf_status', [
            'is_whmcs'   => '1',
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $lock = $result['lockstatus'] ?? 'unlocked';
        $this->addInfo('Domain is ' . $lock);

        return $lock === 'locked';
    }

    public function updateRegistrarLock()
    {
        $cmd    = ((int) ($this->options['registrarLock'] ?? 0) === 1) ? 'domain_lock' : 'domain_unlock';
        $result = $this->get($cmd, [
            'is_whmcs'   => '1',
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            $this->logAction(['action' => 'Domain Registrar Lock', 'result' => false, 'change' => $cmd, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            return false;
        }

        $this->logAction(['action' => 'Domain Registrar Lock', 'result' => true, 'change' => $cmd, 'error' => false]);

        return true;
    }

    public function getEppCode()
    {
        if ($this->isVnDomain()) {
            $this->addError('Không khả dụng với tên miền .vn');
            return false;
        }

        $result = $this->get('domain_authcode', [
            'is_whmcs'   => '1',
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->logAction(['action' => 'Get Epp Code', 'result' => false, 'change' => false, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $epp = (string) ($result['authcode'] ?? '');
        $this->logAction(['action' => 'Get Epp Code', 'result' => Nhanhoa_Domain_Api::msg($result, 'ok'), 'change' => $epp, 'error' => false]);

        return 'The EPP key is: ' . $epp;
    }

    public function updateIDProtection()
    {
        $wanted = $this->options['idprotection'] ?? 0;
        if (isset($this->details['idprotection']) && $this->details['idprotection'] == $wanted) {
            $this->addError('idprotection is same configuration');
            return false;
        }

        $result = $this->get('whois_protect', [
            'domainName' => $this->getCurrentDomainName(),
            'protect'    => $wanted,
        ]);

        $change = ((int) $wanted === 1)
            ? ['from' => 'Disabled', 'to' => 'Enabled']
            : ['from' => 'Enabled', 'to' => 'Disabled'];

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            $this->logAction(['action' => 'Update ID Protection', 'result' => false, 'change' => false, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            return false;
        }

        $this->addDomain('Active');
        $this->addPeriod();
        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Đã cập nhật ẩn thông tin whois.'));
        $this->logAction(['action' => 'Update ID Protection', 'result' => true, 'change' => $change, 'error' => false]);

        return true;
    }

    // ─── Sync ────────────────────────────────────────────────────────────────

    public function synchInfo()
    {
        $return = [];
        $result = $this->get('domain_sync', [
            'is_whmcs'   => '1',
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            if ($this->status === 'Active') {
                $return['status'] = 'TransferredOut';
            }
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            HBEventManager::notify('after_nhanhoadomainsynch', [
                'domain_id' => $this->domain_id,
                'name'      => $this->name,
                'synch'     => $return,
            ]);

            return $return;
        }

        if (!empty($result['endtime'])) {
            $return['expires'] = date('Y-m-d', (int) $result['endtime']);
        }

        $map = [
            'Active'                => 'Active',
            'Expired'               => 'Expired',
            'Suspended'             => 'Expired',
            'Expired and Cancelled' => 'TransferredOut',
        ];

        $currentstatus = $result['currentstatus'] ?? '';
        if (isset($map[$currentstatus])) {
            $return['status'] = $map[$currentstatus];
        } elseif ($currentstatus === 'InActive') {
            $return['status'] = $this->domain_type === 'Transfer' ? 'Pending Transfer' : 'Pending Registration';
        }

        $ns = $this->getNameServers();
        if (is_array($ns)) {
            $return['ns'] = $ns;
        }

        if (!$this->isVnDomain()) {
            $return['reglock'] = $this->getRegistrarLock();
        }

        HBEventManager::notify('after_nhanhoadomainsynch', [
            'domain_id' => $this->domain_id,
            'name'      => $this->name,
            'synch'     => $return,
        ]);

        return $return;
    }

    protected function isExpired()
    {
        return false;
    }

    public function expiredDomains()
    {
        return false;
    }

    public function getAutoRenew()
    {
        return false;
    }

    public function updateAutoRenew()
    {
        return false;
    }

    // ─── Listing and pricing ─────────────────────────────────────────────────

    /**
     * Domains held at the registrar. type=1 is .vn, type=0 international.
     */
    public function ListDomains()
    {
        $domains = [];

        foreach ([1, 0] as $type) {
            $result = $this->get('list_domain', ['type' => $type]);
            if (!Nhanhoa_Domain_Api::ok($result)) {
                $this->addError(Nhanhoa_Domain_Api::msg($result));
                continue;
            }

            foreach ((array) ($result['data'] ?? []) as $domain) {
                if (empty($domain['domain'])) {
                    continue;
                }

                $expires = DateTime::createFromFormat('d-m-Y', (string) ($domain['expired_date'] ?? ''));
                $created = new DateTime();
                $created->modify('-3 day');

                $domains[] = [
                    'name'         => $domain['domain'],
                    'period'       => '1',
                    'status'       => 'Active',
                    'date_created' => $created->format('Y-m-d'),
                    'expires'      => $expires ? $expires->format('Y-m-d') : null,
                ];
            }
        }

        return $domains;
    }

    /** Domains at the registrar that still have no bản khai on file. */
    public function listUnsignedDomains()
    {
        $result = $this->get('list_domain_unsign');
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $rows = (array) ($result['data'] ?? []);
        $this->addInfo(sprintf('Có %d tên miền thiếu bản khai.', count($rows)));

        return $rows;
    }

    public function getDomainPrices()
    {
        $domains = [];

        // `.dghc` is the registrar's single price row for every province TLD.
        $provinceDomains = [
            'angiang.vn', 'bacgiang.vn', 'backan.vn', 'baclieu.vn', 'bacninh.vn',
            'baria-vungtau.vn', 'bentre.vn', 'binhdinh.vn', 'binhduong.vn', 'binhphuoc.vn',
            'binhthuan.vn', 'camau.vn', 'cantho.vn', 'caobang.vn', 'daklak.vn', 'daknong.vn',
            'danang.vn', 'dienbien.vn', 'dongnai.vn', 'dongthap.vn', 'gialai.vn', 'hagiang.vn',
            'haiduong.vn', 'haiphong.vn', 'hanam.vn', 'hanoi.vn', 'hatay.vn', 'hatinh.vn',
            'haugiang.vn', 'hoabinh.vn', 'hungyen.vn', 'khanhhoa.vn', 'kiengiang.vn', 'kontum.vn',
            'laichau.vn', 'lamdong.vn', 'langson.vn', 'laocai.vn', 'longan.vn', 'namdinh.vn',
            'nghean.vn', 'ninhbinh.vn', 'ninhthuan.vn', 'phutho.vn', 'phuyen.vn', 'quangbinh.vn',
            'quangnam.vn', 'quangngai.vn', 'quangninh.vn', 'quangtri.vn', 'soctrang.vn', 'sonla.vn',
            'tayninh.vn', 'thaibinh.vn', 'thainguyen.vn', 'thanhhoa.vn', 'thuathienhue.vn',
            'tiengiang.vn', 'travinh.vn', 'tuyenquang.vn', 'vinhlong.vn', 'vinhphuc.vn', 'yenbai.vn',
            'org.vn',
        ];

        foreach ([1, 2] as $type) {
            $result = $this->get('domain_pricing', ['type' => $type]);
            if (!Nhanhoa_Domain_Api::ok($result)) {
                $this->addError(Nhanhoa_Domain_Api::msg($result));
                continue;
            }

            foreach ((array) ($result['data'] ?? []) as $domain) {
                $name = $domain['name'] ?? '';
                if ($name === '' || strpos($name, '_') !== false) {
                    continue;
                }

                if ($name === '.dghc') {
                    foreach ($provinceDomains as $province) {
                        $domains['.' . $province] = $this->priceLadder($domain);
                    }
                    continue;
                }

                if (substr($name, -3) === '.vn') {
                    $domain['price_transfer'] = 0;
                }

                $domains[$name] = $this->priceLadder($domain);
            }
        }

        return ['VND' => $domains];
    }

    /** Years 1..9 built from the registrar's single-year prices. */
    protected function priceLadder(array $domain)
    {
        $register = (float) ($domain['price_register'] ?? 0);
        $renew    = (float) ($domain['price_renew'] ?? 0);
        $transfer = (float) ($domain['price_transfer'] ?? 0);

        $period = [];
        for ($i = 1; $i < 10; $i++) {
            $row = ['period' => $i, 'redemption' => 15 * $renew];
            if ($register) {
                $row['register'] = $register + ($i - 1) * $renew;
            }
            if ($renew) {
                $row['renew'] = $i * $renew;
            }
            if ($transfer) {
                $row['transfer'] = $transfer + ($i - 1) * $renew;
            }
            $period[$i] = $row;
        }

        return $period;
    }

    // ─── ZoneDNS ─────────────────────────────────────────────────────────────

    /**
     * Records currently in the zone, in the shape core's DNS management page
     * expects: `hosts` plus the record types the registrar accepts.
     */
    public function getDNSmanagement()
    {
        $records = $this->dnsRecords();
        if ($records === false) {
            return [];
        }

        $hosts = [];
        foreach ($records as $record) {
            $hosts[] = [
                'id'         => $record['record_id'] ?? null,
                'hostname'   => $record['record_name'] ?? '',
                'address'    => $record['record_value'] ?? '',
                'recordtype' => $record['record_type'] ?? '',
                'priority'   => $record['record_mx'] ?? null,
            ];
        }

        return [
            'hosts'   => $hosts,
            'records' => ['A', 'AAAA', 'CNAME', 'MX', 'NS', 'TXT', 'SRV'],
        ];
    }

    /**
     * Apply the DNS form.
     *
     * Nhan Hoa has no bulk replace, so this reconciles: rows that kept their id
     * are edited, new rows added, and ids that vanished from the form deleted.
     * A renamed record is delete + add, because edit_record cannot move a name.
     */
    public function updateDNSManagement()
    {
        $domain   = $this->getCurrentDomainName();
        $existing = $this->dnsRecords();
        if ($existing === false) {
            return false;
        }

        $byId = [];
        foreach ($existing as $record) {
            $byId[(string) ($record['record_id'] ?? '')] = $record;
        }

        $seen = [];
        $ok   = true;

        for ($i = 1; isset($this->options['HostName' . $i]); $i++) {
            $hostname = trim((string) $this->options['HostName' . $i]);
            $address  = trim((string) ($this->options['Address' . $i] ?? ''));
            $type     = strtoupper(trim((string) ($this->options['RecordType' . $i] ?? 'A')));
            $priority = $this->options['Priority' . $i] ?? '';
            $ttl      = (int) ($this->options['TTL' . $i] ?? self::DEFAULT_TTL);
            $id       = (string) ($this->options['RecordId' . $i] ?? '');

            if ($hostname === '' || $address === '') {
                continue;
            }

            if ($id !== '' && isset($byId[$id])) {
                $seen[$id] = true;
                $old       = $byId[$id];

                if (($old['record_name'] ?? '') !== $hostname) {
                    // edit_record cannot rename; replace instead.
                    $ok = $this->deleteDnsRecord($domain, $id) && $ok;
                    $ok = $this->addDnsRecord($domain, $hostname, $type, $address, $ttl, $priority) && $ok;
                    continue;
                }

                if (($old['record_type'] ?? '') === $type
                    && (string) ($old['record_value'] ?? '') === $address
                    && (int) ($old['record_ttl'] ?? 0) === $this->clampTtl($ttl)
                    && (string) ($old['record_mx'] ?? '') === (string) $priority) {
                    continue; // unchanged
                }

                $ok = $this->editDnsRecord($domain, $id, $type, $address, $ttl, $priority) && $ok;
                continue;
            }

            $ok = $this->addDnsRecord($domain, $hostname, $type, $address, $ttl, $priority) && $ok;
        }

        foreach ($byId as $id => $record) {
            if ($id !== '' && !isset($seen[$id])) {
                $ok = $this->deleteDnsRecord($domain, $id) && $ok;
            }
        }

        return $ok;
    }

    /** Raw ZoneDNS record list, or false when the call failed. */
    public function dnsRecords($domain = '')
    {
        $domain = $domain ?: $this->getCurrentDomainName();
        $result = $this->get('list_record', ['domain_name' => $domain]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        return (array) ($result['data']['domain_record'] ?? []);
    }

    public function addDnsRecord($domain, $name, $type, $value, $ttl = self::DEFAULT_TTL, $priority = '')
    {
        $formdata = [
            'domain_name'  => $domain,
            'record_name'  => $name,
            'record_type'  => $type,
            'record_value' => $value,
            'record_ttl'   => $this->clampTtl($ttl),
        ];
        if ($type === 'MX') {
            $formdata['record_mx'] = $priority === '' ? 10 : $priority;
        }

        return $this->dnsWrite('add_record', $formdata);
    }

    public function editDnsRecord($domain, $recordId, $type, $value, $ttl = self::DEFAULT_TTL, $priority = '')
    {
        $formdata = [
            'domain_name'  => $domain,
            'record_id'    => $recordId,
            'record_type'  => $type,
            'record_value' => $value,
            'record_ttl'   => $this->clampTtl($ttl),
        ];
        if ($type === 'MX') {
            $formdata['record_mx'] = $priority === '' ? 10 : $priority;
        }

        return $this->dnsWrite('edit_record', $formdata);
    }

    public function deleteDnsRecord($domain, $recordId)
    {
        return $this->dnsWrite('delete_record', [
            'domain_name' => $domain,
            'record_id'   => $recordId,
        ]);
    }

    protected function dnsWrite($cmd, array $formdata)
    {
        $result = $this->get($cmd, $formdata);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            $this->logAction(['action' => 'DNS ' . $cmd, 'result' => false, 'change' => $formdata, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            return false;
        }

        $this->logAction(['action' => 'DNS ' . $cmd, 'result' => true, 'change' => $formdata, 'error' => false]);

        return true;
    }

    /** ZoneDNS accepts 300..86400. */
    protected function clampTtl($ttl)
    {
        $ttl = (int) $ttl;
        if ($ttl < 300) {
            return 300;
        }

        return $ttl > 86400 ? 86400 : $ttl;
    }

    // ─── .vn paperwork ───────────────────────────────────────────────────────

    /** Link to the registrar's e-signature page for the bản khai. */
    public function Contract()
    {
        $cmd    = $this->isVnDomain() ? 'get_sign' : 'get_sign_qt';
        $result = $this->get($cmd, ['domain_name' => $this->getCurrentDomainName()]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError('API báo lỗi: ' . Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $signUrl = $result['sign_url'] ?? '';
        if ($signUrl === '') {
            $this->addError('API không trả về link ký số.');
            return false;
        }

        $this->addInfo("Lấy link ký số thành công: <a href='{$signUrl}' target='_blank'>{$signUrl}</a>");

        return $signUrl;
    }

    /** eKYC link for a .vn registrant. */
    public function getekyc()
    {
        if (!$this->isVnDomain()) {
            $this->addError('eKYC chỉ hỗ trợ cho tên miền .vn');
            return false;
        }

        $result = $this->get('get_link_ekyc_tmvn', ['domain_name' => $this->getCurrentDomainName()]);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError('API báo lỗi: ' . Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $url = $result['ekyc_url'] ?? '';
        if ($url === '') {
            $this->addError('API không trả về link eKYC.');
            return false;
        }

        $this->addInfo("Lấy link eKYC thành công: <a href='{$url}' target='_blank'>{$url}</a>");

        return $url;
    }

    /** Everything an admin needs on one screen. */
    public function getekycProfile()
    {
        $domainName = $this->getCurrentDomainName();

        $isVn = $this->isVnDomain($domainName);

        return [
            'domain_name'  => $domainName,
            'is_vn_domain' => $isVn,
            'contacts'     => $this->getDomainContacts(true),
            // check_declaration only means anything for a .vn bản khai.
            'declaration'  => $isVn ? $this->Statusdomain() : false,
            'processing'   => $this->getStatusDomain(),
        ];
    }

    /** Bản khai review state. */
    public function Statusdomain()
    {
        $domainName = $this->getCurrentDomainName();
        $result     = $this->get('check_declaration', ['domainName' => $domainName]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError('API trả về lỗi: ' . Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $statusValue = $result['declaration_status'] ?? null;
        $statusText  = $this->getDeclarationStatusLabel($statusValue);

        $this->addInfo(
            'Module (nhanhoa_domain): Kiểm tra trạng thái hồ sơ' . PHP_EOL
            . 'Tên miền: ' . ($result['domain_name'] ?? $domainName) . PHP_EOL
            . 'Trạng thái: ' . $statusText . PHP_EOL
        );

        return [
            'domain_name'             => $result['domain_name'] ?? $domainName,
            'declaration_status'      => $statusValue,
            'declaration_status_text' => $statusText,
        ];
    }

    /** Order-processing state at the registrar. */
    public function getStatusDomain()
    {
        $result = $this->get($this->cmdFor('get_statusdomain'), [
            'domainName' => $this->getCurrentDomainName(),
        ]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError('API báo lỗi: ' . Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo('Kiểm tra trạng thái xử lý tên miền thành công.');

        $endTime = $result['ord_end_time'] ?? null;

        return [
            'content'      => $result['content'] ?? [],
            'ord_id'       => $result['ord_id'] ?? null,
            'ord_name'     => $result['ord_name'] ?? null,
            'ord_end_time' => $endTime ? date('d-m-Y H:i:s', (int) $endTime) : null,
        ];
    }

    /** ZoneDNS control-panel credentials for the domain. */
    public function getDomainInfo()
    {
        $result = $this->get('info_control', ['domain_name' => $this->getCurrentDomainName()]);
        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        $this->addInfo(
            'Lấy thông tin quản lý tên miền thành công.' . PHP_EOL
            . 'Login URL: ' . ($result['login_url'] ?? '') . PHP_EOL
            . 'Domain: ' . ($result['domain'] ?? '') . PHP_EOL
            . 'Password: ' . ($result['password'] ?? '') . PHP_EOL
            . 'Bạn có thể sao chép thông tin này.'
        );

        return [
            'login_url' => $result['login_url'] ?? '',
            'domain'    => $result['domain'] ?? '',
            'password'  => $result['password'] ?? '',
        ];
    }

    public function Unlock24h()
    {
        return $this->simpleDomainCommand('unlock24h', 'Unlock 24h');
    }

    public function ResendVerify()
    {
        if ($this->isVnDomain()) {
            $this->addError('Không khả dụng với tên miền .vn');
            return false;
        }

        return $this->simpleDomainCommand('resend_verify_domain', 'Resend verify email');
    }

    public function SkipVerifyEmail()
    {
        if ($this->isVnDomain()) {
            $this->addError('Không khả dụng với tên miền .vn');
            return false;
        }

        return $this->simpleDomainCommand('skip_verify_domain', 'Skip verify email');
    }

    /** Commands whose only argument is the domain name. */
    protected function simpleDomainCommand($cmd, $action)
    {
        $result = $this->get($cmd, ['domainName' => $this->getCurrentDomainName()]);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            $this->logAction(['action' => $action, 'result' => false, 'change' => false, 'error' => Nhanhoa_Domain_Api::msg($result)]);
            return false;
        }

        $this->addInfo(Nhanhoa_Domain_Api::msg($result, 'Thực hiện thành công.'));
        $this->logAction(['action' => $action, 'result' => true, 'change' => Nhanhoa_Domain_Api::msg($result, ''), 'error' => false]);

        return true;
    }

    /**
     * Registry list of countries / provinces / districts / wards.
     *
     * Read-only reference for admins comparing an order against what the
     * registrar will accept. NOT used to rewrite contact data.
     *
     * 2025 reform note: the district tier is abolished for new addresses (see the
     * CONVERSION NOTE on makeContact()). The registrar exposes NO province→ward
     * direct listing, so `list_district` is kept purely as a LEGACY lookup, and
     * the 'ward' level below is a legacy, district-chained lookup:
     *   - 'district' lists quận/huyện under a province  (parent = cityId)
     *   - 'ward'     lists phường/xã  under a district  (parent = districtId)
     * diadanh.md documents the ward listing keyed by `districtId`; the `cmd`
     * shown in that doc section is `list_district` (a copy/paste artifact — the
     * distinguishing input is the parent id). The exact ward cmd name could not
     * be verified offline; see README.md → "Địa danh 2-tier".
     *
     * @param string $level  country|province|district|ward
     * @param string $parent cityId (for district) or districtId (for ward)
     */
    public function getAdministrativeDivisions($level = 'province', $parent = '')
    {
        $cmds = [
            'country'  => ['cmd' => 'list_country',  'parentKey' => null],
            'province' => ['cmd' => 'list_province', 'parentKey' => null],
            'district' => ['cmd' => 'list_district', 'parentKey' => 'cityId'],
            // Legacy: ward/commune under a district id (see note above).
            'ward'     => ['cmd' => 'list_district', 'parentKey' => 'districtId'],
        ];

        if (!isset($cmds[$level])) {
            $this->addError('Cấp địa danh không hợp lệ.');
            return false;
        }

        $spec     = $cmds[$level];
        $formdata = [];
        if ($spec['parentKey'] !== null && $parent !== '') {
            $formdata[$spec['parentKey']] = $parent;
        }

        $result = $this->get($spec['cmd'], $formdata);

        if (!Nhanhoa_Domain_Api::ok($result)) {
            $this->addError(Nhanhoa_Domain_Api::msg($result));
            return false;
        }

        return (array) ($result['data'] ?? []);
    }

    // ─── Validation ──────────────────────────────────────────────────────────

    /**
     * Pre-flight the registrar payload.
     *
     * v2 lowercased $key and then compared it to 'ownerName', so none of the
     * registrant-name rules ever fired. They do now, which is why the
     * organisation keyword list below is wider than v2's.
     */
    protected function check_input($data = [])
    {
        $msg       = [];
        $ownerType = (int) ($data['ord_owner_type'] ?? 0);

        foreach ($data as $key => $value) {
            $lkey  = strtolower((string) $key);
            $value = trim((string) $value);

            if (preg_match('/[*]/', $value)) {
                $msg[] = 'Dữ liệu không được có dấu (*) ' . $value;
            }

            if (strpos($lkey, 'birthday') !== false && $ownerType === 0 && $value !== '') {
                if (!$this->isValidBirthday($value)) {
                    $msg[] = 'Ngày sinh không đúng định dạng (dd/mm/yyyy) hoặc tuổi nhỏ hơn 15! ' . $value;
                }
            }

            if (strpos($lkey, 'address') !== false && $value === '') {
                $msg[] = 'Vui lòng không bỏ trống địa chỉ chủ thể';
            }

            if ($lkey === 'ownername' && $value === '') {
                $msg[] = $ownerType === 1
                    ? 'Quý khách đăng ký với hồ sơ công ty / tổ chức. Vui lòng nhập tên công ty / tổ chức'
                    : 'Vui lòng không bỏ trống tên chủ thể';
            }

            if ($lkey === 'ownerpersonid' && $ownerType === 0) {
                if ($value === '') {
                    $msg[] = 'Vui lòng không bỏ trống số CMND/CCCD/Passport';
                } elseif (!preg_match('/^(0[0-9]|1[0-9]|2[0-7]|280|281|285|29|3[0-8]|40)[0-9]{6,}/', $value)
                    && !preg_match('/^[A-Z]{1,2}[0-9]+[A-Z0-9]*/i', $value)) {
                    return ['status' => 'error', 'msg' => 'Thông tin số CMND/Passport không hợp lệ. Vui lòng kiểm tra lại'];
                }
            }

            if ($lkey === 'ownername' && $ownerType === 1 && $value !== '' && !$this->looksLikeOrganisation($value)) {
                $msg[] = 'Hồ sơ công ty nhưng tên chủ thể không phải là công ty / tổ chức';
            }
        }

        if ($msg) {
            return ['status' => 'error', 'msg' => $msg];
        }

        return ['status' => 'success', 'msg' => ''];
    }

    /**
     * Does the registrant name read as an organisation?
     *
     * The list is broader than v2's twelve keywords on purpose: that check was
     * dead code, so switching it on with the short list would have started
     * rejecting legitimate registrants (hợp tác xã, trường, viện, …).
     */
    protected function looksLikeOrganisation($name)
    {
        $normalised = strtolower($this->convertAccentsAndSpecialToNormal($name));

        $keywords = [
            'tap doan', 'tong cong ty', 'cong ty', 'doanh nghiep', 'hop tac xa', 'to chuc',
            'hoi dong', 'co quan', 'lien doan', 'lien hiep', 'cong dong', 'uy ban', 'truong ban',
            'ban quan', 'benh vien', 'phong kham', 'trung tam', 'truong', 'hoc vien', 'vien',
            'quy ', 'hiep hoi', 'hoi ', 'cau lac bo', 'nha xuat ban', 'chi nhanh', 'van phong',
            'so ', 'phong ', 'cuc ', 'vu ', 'bo ', 'ngan hang', 'nha may', 'xi nghiep', 'htx',
        ];

        foreach ($keywords as $keyword) {
            if (strpos($normalised, $keyword) !== false) {
                return true;
            }
        }

        return false;
    }

    protected function isValidBirthday($cnt_birthday)
    {
        $parts = explode('/', (string) $cnt_birthday);
        if (count($parts) !== 3) {
            return false;
        }

        [$dd, $mm, $yyyy] = $parts;
        if (!ctype_digit($dd) || !ctype_digit($mm) || !ctype_digit($yyyy)) {
            return false;
        }

        if (!checkdate((int) $mm, (int) $dd, (int) $yyyy)) {
            return false;
        }

        return (int) date('Y') - (int) $yyyy >= 15;
    }

    /**
     * @param string $str
     * @return string
     */
    private function convertAccentsAndSpecialToNormal($str)
    {
        $str = preg_replace('/(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)/u', 'a', $str);
        $str = preg_replace('/(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)/u', 'e', $str);
        $str = preg_replace('/(ì|í|ị|ỉ|ĩ)/u', 'i', $str);
        $str = preg_replace('/(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)/u', 'o', $str);
        $str = preg_replace('/(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)/u', 'u', $str);
        $str = preg_replace('/(ỳ|ý|ỵ|ỷ|ỹ)/u', 'y', $str);
        $str = preg_replace('/(đ)/u', 'd', $str);
        $str = preg_replace('/(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)/u', 'A', $str);
        $str = preg_replace('/(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)/u', 'E', $str);
        $str = preg_replace('/(Ì|Í|Ị|Ỉ|Ĩ)/u', 'I', $str);
        $str = preg_replace('/(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)/u', 'O', $str);
        $str = preg_replace('/(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)/u', 'U', $str);
        $str = preg_replace('/(Ỳ|Ý|Ỵ|Ỷ|Ỹ)/u', 'Y', $str);
        $str = preg_replace('/(Đ)/u', 'D', $str);

        return $str;
    }

    // ─── Misc ────────────────────────────────────────────────────────────────

    /** Bản khai template stored in HostBill, newest first. */
    public function getTemplate()
    {
        $q = $this->db->query("SELECT * FROM hb_templates WHERE `name` IN ('BanKhaiVN', 'BanKhaiVN-custom') ORDER BY `updated` DESC LIMIT 1");
        $d = $q->fetch(PDO::FETCH_ASSOC);
        $q->closeCursor();

        return $d;
    }

    protected function getDomainFile($domain_id)
    {
        if (!$domain_id) {
            return false;
        }

        $q = $this->db->prepare('SELECT * FROM hb_downloads WHERE `rel_type` & :type AND `rel_id` = :id ORDER BY `id` DESC LIMIT 1');
        $q->execute([':type' => Download::TYPE_DOMAIN, ':id' => $domain_id]);
        $file = $q->fetch(PDO::FETCH_ASSOC);
        $q->closeCursor();

        if (!$file) {
            return false;
        }

        return Engine::singleton()->getConfig('HBDownloadsDir') . DS . $file['filename'];
    }

    public function setProduct($id)
    {
    }

    public function checkDomainRegistrant()
    {
        return $this->domain_contacts['registrant'] ?? [];
    }
}
