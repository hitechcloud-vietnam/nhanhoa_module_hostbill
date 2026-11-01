<?php

/**
 * Nhan Hoa SSL — SSL provisioning module (SSLModule).
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'class.nhanhoa_ssl_api.php';

/**
 * Nhan Hoa (Nhân Hòa) SSL provisioning module for HostBill.
 *
 * Implements the HostBill SSLModule contract against the Nhan Hoa reseller API
 * (https://api.nhanhoa.com/). Every call is an HTTP POST to `?act=<cmd>`
 * carrying `auth-user`, `auth-pwd`, `auth-id`, `cmd` and a urlencoded
 * `formdata` string; responses are JSON normalised to `['status','msg',...]`
 * by the transport in lib/class.nhanhoa_ssl_api.php.
 *
 * IMPORTANT — the Nhan Hoa SSL API is intentionally minimal. As documented at
 * https://docs.nhanhoa.com/ssl.md it exposes only:
 *   - register_ssl  (serviceCode, domainNameList)            -> place an order
 *   - renew_ssl     (ord_id)                                 -> renew an order
 *   - get_infossl   (serviceCode, domainName)                -> status + ord_id
 *   - service_code  (group_id=4)                             -> SSL product list
 *   - get_balance   ()                                       -> reseller balance
 *
 * There is NO API for CSR generation/submission, no DCV (email/http/dns)
 * endpoints, no approver-email handling, no reissue, no cancel and no
 * certificate download. Nhan Hoa drives CSR collection, domain-control
 * validation and certificate delivery through its own customer portal and
 * e-mail flow, out of band from this API.
 *
 * This module therefore implements the whole SSLModule surface (so HostBill's
 * UI and cron behave), but the operations the API cannot perform are honest
 * no-ops that record a clear error rather than pretending to succeed. The order
 * lifecycle that IS supported — order, status-sync, renew — is fully wired.
 *
 * This is a fresh module. It does not depend on, extend or modify any existing
 * module; it only reuses the proven Nhan Hoa transport (renamed) and mirrors
 * the structure of the webnic_ssl module.
 *
 * @see https://docs.nhanhoa.com/ssl.md
 * @see https://docs.nhanhoa.com/service_code.md
 */
class nhanhoa_ssl extends SSLModule
{
    /**
     * Capability flags.
     *  - haveuser/haveadmin/havetpl: standard SSL module surfaces.
     *  - haveapi: exposes the REST routes under api/ to the User API plugin.
     */
    protected $info = ["haveuser" => true, "haveadmin" => true, "havetpl" => true, "haveapi" => true];

    protected $version = '1.0.0';
    protected $_repository = 'hosting_nhanhoa_ssl';
    protected $modname = 'Nhan Hoa SSL';
    protected $description = 'Nhan Hoa (Nhân Hòa) SSL provisioning module for HostBill by Pho Tue SoftWare Solutions JSC';

    /** Nhan Hoa service group id for SSL products (service_code cmd). */
    const SSL_GROUP_ID = 4;

    /**
     * Product-configuration options rendered on the HostBill product page.
     *  - serviceCode: loadable select populated by listProducts() (service_code
     *    cmd, group_id=4); falls back to a plain value if the list is empty.
     *  - SAN support is offered but speculative: the register_ssl API documents
     *    a single `domainNameList` field, so multi-domain is sent comma-joined.
     *    Leave the SAN limit blank (0) to disable the SAN form entirely.
     */
    protected $options = [
        'serviceCode' => [
            'name' => 'SSL Product (Nhan Hoa service code)',
            'value' => false,
            'type' => 'loadable',
            'default' => 'listProducts',
        ],
        \SSL\OPT\SAN_LIMIT => [
            'type' => 'input',
            'name' => 'Maximum SAN entries (0 = single-domain only)',
            'value' => false,
        ],
        'san' => [
            'name' => 'SAN',
            'forms' => 'san',
            'variable' => 'san',
        ],
    ];

    /**
     * Connection (server) fields. Nhan Hoa authenticates with the reseller API
     * username/password plus the numeric reseller account id (auth-id), mapped
     * to the first input field.
     */
    protected $serverFields = [
        self::CONNECTION_FIELD_HOSTNAME => false,
        self::CONNECTION_FIELD_IPADDRESS => false,
        self::CONNECTION_FIELD_USERNAME => true,
        self::CONNECTION_FIELD_PASSWORD => true,
        self::CONNECTION_FIELD_INPUT1 => true,
        self::CONNECTION_FIELD_INPUT2 => false,
        self::CONNECTION_FIELD_TEXTAREA => false,
        self::CONNECTION_FIELD_CHECKBOX => true,
        self::CONNECTION_FIELD_NAMESERVERS => false,
        self::CONNECTION_FIELD_MAXACCOUNTS => false,
        self::CONNECTION_FIELD_STATUSURL => false,
    ];

    protected $serverFieldsDescription = [
        self::CONNECTION_FIELD_USERNAME => 'API Username (auth-user)',
        self::CONNECTION_FIELD_PASSWORD => 'API Password (auth-pwd)',
        self::CONNECTION_FIELD_INPUT1 => 'Reseller Account ID (auth-id)',
        self::CONNECTION_FIELD_CHECKBOX => 'Write API calls to nhanhoa_ssl.log',
    ];

    /**
     * Per-service stored details. Visible fields (domain, order id, status) are
     * shown on the service page; the hidden fields persist order state across
     * calls (service code, raw status/order name/expiry, and — for reference
     * only, since the API never consumes them — any CSR / DCV / contact data
     * HostBill collected on the order form).
     */
    protected $details = [
        \SSL\CN => [
            'name' => 'domain',
            'type' => 'input',
        ],
        \SSL\ORDERID => [
            'name' => 'Order ID',
            'type' => 'input',
        ],
        \SSL\STATUS => [
            'name' => 'Certificate Status',
            'type' => 'select',
            'default' => [
                \SSL\STATUS\INCOMPLETE,
                \SSL\STATUS\PENDING,
                \SSL\STATUS\PROCESSING,
                \SSL\STATUS\VALIDATION,
                \SSL\STATUS\ISSUED,
                \SSL\STATUS\CANCELLED,
                \SSL\STATUS\EXPIRED,
            ],
        ],
        \SSL\CSR => [
            'type' => 'hidden',
        ],
        \SSL\DCV => [
            'type' => 'hidden',
        ],
        \SSL\DCV_DETAILS => [
            'type' => 'hidden',
        ],
        \SSL\CONTACTS => [
            'type' => 'hidden',
        ],
        \SSL\SAN => [
            'type' => 'hidden',
        ],
        'serviceCode' => [
            'type' => 'hidden',
        ],
        'ordName' => [
            'type' => 'hidden',
        ],
        'ordEndTime' => [
            'type' => 'hidden',
        ],
        'statusRaw' => [
            'type' => 'hidden',
        ],
    ];

    /**
     * Admin/client service commands beyond the SSL defaults.
     *
     * Reissue is deliberately NOT listed: the Nhan Hoa API has no reissue
     * endpoint, so exposing a button that can only fail would mislead. The
     * Reissue() method still exists (SSLModule contract) and reports the
     * limitation when called.
     */
    protected $commands = [];

    /** Connection data supplied by connect(): username, password, field1 (auth-id). */
    private $connect_data = [];

    /** @var Nhanhoa_ssl_Api|null Lazily-built transport. */
    private $apiClient = null;

    // ------------------------------------------------------------------
    // SSLModule lifecycle / discovery
    // ------------------------------------------------------------------

    /** Receive the server connection parameters for this call. */
    public function connect($connect)
    {
        $this->connect_data = (array) $connect;
    }

    /** Default billing/order term, in months. */
    protected function getDefaultPeriod()
    {
        return 12;
    }

    /**
     * Verify credentials by checking the reseller balance — the cheapest
     * authenticated read the API offers.
     */
    public function testConnection()
    {
        try {
            $this->get('get_balance');
            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Loadable source for the serviceCode option: Nhan Hoa SSL products as
     * [service_code, "service_code — short description"] pairs, from the
     * service_code command with group_id = 4 (SSL).
     */
    public function listProducts()
    {
        try {
            $list = [];
            foreach ($this->fetchSslProducts() as $product) {
                $code = (string) ($product['service_code'] ?? '');
                if ($code === '') {
                    continue;
                }
                $label = $this->summariseServiceContent((string) ($product['service_content'] ?? ''));
                $list[] = [$code, $label === '' ? $code : ($code . ' — ' . $label)];
            }
            return $list;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * DCV methods the module can present. The Nhan Hoa API performs no DCV
     * itself (validation is handled in Nhan Hoa's portal / by e-mail), so this
     * advertises the conventional set for display only; the change/resend/detail
     * operations below are honest no-ops.
     */
    public function listDCVMethod()
    {
        return [
            [\SSL\DCV\EMAIL, 'Email'],
            [\SSL\DCV\DNS, 'DNS'],
            [\SSL\DCV\HTTP, 'HTTP'],
        ];
    }

    /** Contact fields for this product's certificate type (Nhan Hoa SSL = DV). */
    public function CertContacts($params)
    {
        return SSLType::contacts_init($this->getProductCertType(), $params);
    }

    /**
     * Declare the ordering capabilities HostBill should present.
     *
     * The register_ssl API accepts only a service code and a domain list: it
     * never receives a CSR, contacts or a DCV choice. CSR is therefore not
     * required (CSR_LATER = true), DCV is left to Nhan Hoa, and SAN is offered
     * only when the product is configured with a SAN limit.
     */
    public function CertOptions($product)
    {
        $sanLimit = (int) ($this->options[\SSL\OPT\SAN_LIMIT]['value'] ?: 0);
        $allowSan = $sanLimit > 0;

        return [
            \SSL\OPT\CSR => false,
            \SSL\CSR_LATER => true,
            \SSL\OPT\DCV => [\SSL\DCV\EMAIL, \SSL\DCV\DNS, \SSL\DCV\HTTP],
            \SSL\OPT\WILDCARD => false,
            \SSL\OPT\SAN_LIMIT => $allowSan ? $sanLimit : 0,
            \SSL\OPT\SAN_DOMAIN => $allowSan,
            \SSL\OPT\SAN_PUBLICIPS => false,
        ];
    }

    // ------------------------------------------------------------------
    // Order lifecycle
    // ------------------------------------------------------------------

    /**
     * Place a new Nhan Hoa SSL order: register_ssl with the configured service
     * code and the certificate's domain list (common name, plus any SANs joined
     * with commas). Stores a CSR HostBill may have collected for reference only
     * — the API does not accept it — then best-effort captures the Nhan Hoa
     * order id via a follow-up get_infossl.
     */
    public function Create()
    {
        try {
            $serviceCode = $this->getConfiguredServiceCode();
            $commonName = $this->getCommonName();
            if ($commonName === '') {
                throw new Exception('No domain/common name is available for the SSL order.');
            }

            $this->get('register_ssl', [
                'serviceCode' => $serviceCode,
                'domainNameList' => $this->buildDomainNameList(),
            ]);

            $this->details[\SSL\CN]['value'] = $commonName;
            $this->details['serviceCode']['value'] = $serviceCode;
            $this->details[\SSL\STATUS]['value'] = \SSL\STATUS\VALIDATION;

            // Best effort: the order is queued, so info may not be ready yet.
            $this->refreshOrderInfo($serviceCode, $commonName, false);

            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Renew the certificate: renew_ssl with the Nhan Hoa order id, resolving the
     * order id first via get_infossl when it is not already stored.
     */
    public function Renewal()
    {
        try {
            $serviceCode = $this->getConfiguredServiceCode();
            $commonName = $this->getCommonName();
            $ordId = $this->requireOrderId($serviceCode, $commonName);

            $this->get('renew_ssl', ['ord_id' => $ordId]);

            // Refresh status/expiry after the renewal request is accepted.
            $this->refreshOrderInfo($serviceCode, $commonName, false);

            return true;
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Reissue is not supported by the Nhan Hoa SSL API (no reissue endpoint).
     * The method exists to satisfy the SSLModule contract and reports the
     * limitation clearly instead of failing silently.
     */
    public function Reissue()
    {
        $this->addError('Nhan Hoa SSL API does not support certificate reissue. Please reissue the certificate from the Nhan Hoa customer portal.');
        return false;
    }

    /**
     * The Nhan Hoa SSL API has no cancel/terminate endpoint, so termination is
     * recorded locally only. Any actual cancellation/refund must be handled in
     * the Nhan Hoa portal.
     */
    public function Terminate()
    {
        $this->details[\SSL\STATUS]['value'] = \SSL\STATUS\CANCELLED;
        $this->details['statusRaw']['value'] = 'CANCELLED (local only — Nhan Hoa API has no cancel endpoint)';
        $this->addInfo('Marked cancelled locally. The Nhan Hoa SSL API has no cancel endpoint; cancel/refund the order in the Nhan Hoa portal if required.');
        return true;
    }

    // ------------------------------------------------------------------
    // Domain Control Validation (DCV) — not exposed by the Nhan Hoa API
    // ------------------------------------------------------------------

    /** Email DCV approver candidates: not provided by the Nhan Hoa API. */
    public function CertDCVEmail()
    {
        return [];
    }

    /** HTTP (file) DCV details: not provided by the Nhan Hoa API. */
    public function CertDcvHttp()
    {
        return [];
    }

    /** DNS (TXT) DCV records: not provided by the Nhan Hoa API. */
    public function CertDcvDns()
    {
        return [];
    }

    /** Changing DCV method is not supported by the Nhan Hoa API. */
    public function CertDCVMethodChange($method, $email = false)
    {
        $this->addError('Nhan Hoa SSL API does not support changing the domain-control-validation method. Manage validation from the Nhan Hoa customer portal.');
        return false;
    }

    /** Resending the approver email is not supported by the Nhan Hoa API. */
    public function CertDCVEmailResend()
    {
        $this->addError('Nhan Hoa SSL API does not support resending the approver email. Manage validation from the Nhan Hoa customer portal.');
        return false;
    }

    // ------------------------------------------------------------------
    // Synchronisation & download
    // ------------------------------------------------------------------

    /**
     * Pull the latest order status from Nhan Hoa (get_infossl), map it to a
     * HostBill SSL status, fire the issued-certificate notification on
     * transition, and return the certificate expiry as next_due when known.
     */
    public function getSynchInfo()
    {
        try {
            $serviceCode = $this->getConfiguredServiceCode();
            $commonName = $this->getCommonName();
            if ($commonName === '') {
                throw new Exception('No domain/common name is stored for this service.');
            }

            $oldStatus = $this->details[\SSL\STATUS]['value'];
            $info = $this->refreshOrderInfo($serviceCode, $commonName, true);

            $this->notifyWhenCertIssued($oldStatus);

            if (!empty($info['ord_end_time'])) {
                $expire = $this->formatExpiry($info['ord_end_time']);
                if ($expire !== '') {
                    return ['next_due' => $expire];
                }
            }

            return [true];
        } catch (Exception $e) {
            $this->addError($e->getMessage());
            return false;
        }
    }

    /**
     * Certificate download is not supported by the Nhan Hoa API. The issued
     * certificate is delivered by Nhan Hoa out of band (portal / email).
     */
    public function CertDownload()
    {
        $this->addError('Nhan Hoa SSL API does not provide certificate download. Download the issued certificate from the Nhan Hoa customer portal or the delivery email.');
        return [];
    }

    // ------------------------------------------------------------------
    // Vendor reads / state helpers
    // ------------------------------------------------------------------

    /**
     * Fetch get_infossl and persist order id, order name, raw status and expiry
     * onto the service, mapping the vendor status to a HostBill SSL status.
     *
     * @param bool $throwOnError true to surface API errors, false to ignore
     *                           them (e.g. right after placing an order that is
     *                           still queued and not yet queryable).
     * @return array the (possibly empty) get_infossl payload.
     */
    protected function refreshOrderInfo($serviceCode, $commonName, $throwOnError = true)
    {
        try {
            $info = $this->get('get_infossl', [
                'serviceCode' => $serviceCode,
                'domainName' => $commonName,
            ]);
        } catch (Exception $e) {
            if ($throwOnError) {
                throw $e;
            }
            return [];
        }

        if (!empty($info['ord_id'])) {
            $this->details[\SSL\ORDERID]['value'] = (string) $info['ord_id'];
        }
        if (!empty($info['ord_name'])) {
            $this->details['ordName']['value'] = (string) $info['ord_name'];
        }
        if (!empty($info['ord_end_time'])) {
            $this->details['ordEndTime']['value'] = (string) $info['ord_end_time'];
        }

        $raw = (string) ($info['content'] ?? ($info['msg'] ?? ''));
        if ($raw !== '') {
            $this->details['statusRaw']['value'] = $raw;
        }
        $this->details[\SSL\STATUS]['value'] = $this->mapVendorStatus($raw, $info);

        return $info;
    }

    /**
     * Resolve the Nhan Hoa order id, from storage if present, otherwise via a
     * get_infossl lookup. Throws when it cannot be determined.
     */
    protected function requireOrderId($serviceCode, $commonName)
    {
        $ordId = trim((string) ($this->details[\SSL\ORDERID]['value'] ?? ''));
        if ($ordId !== '') {
            return $ordId;
        }

        if ($commonName !== '') {
            $info = $this->refreshOrderInfo($serviceCode, $commonName, false);
            if (!empty($info['ord_id'])) {
                return (string) $info['ord_id'];
            }
        }

        throw new Exception('Unable to determine the Nhan Hoa SSL order id (ord_id) for this service.');
    }

    /** Nhan Hoa SSL products (service_code, group_id = 4). */
    protected function fetchSslProducts()
    {
        $result = $this->get('service_code', ['group_id' => self::SSL_GROUP_ID]);

        // The API may answer either a flat {service_code, service_content}, a
        // list under `data`, or a list under `service_code`. Normalise all.
        if (isset($result['data']) && is_array($result['data'])) {
            return $this->normaliseProductRows($result['data']);
        }
        if (isset($result['service_code']) && is_array($result['service_code'])) {
            return $this->normaliseProductRows($result['service_code']);
        }
        if (!empty($result['service_code']) && is_scalar($result['service_code'])) {
            return [[
                'service_code' => (string) $result['service_code'],
                'service_content' => (string) ($result['service_content'] ?? ''),
            ]];
        }

        // Fall back: a bare numerically-indexed list of rows.
        return $this->normaliseProductRows($result);
    }

    /** Keep only array rows that carry a service_code. */
    protected function normaliseProductRows($rows)
    {
        $out = [];
        foreach ((array) $rows as $row) {
            if (is_array($row) && !empty($row['service_code'])) {
                $out[] = [
                    'service_code' => (string) $row['service_code'],
                    'service_content' => (string) ($row['service_content'] ?? ''),
                ];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------------
    // Local state accessors
    // ------------------------------------------------------------------

    /** The certificate common name: stored CN, else the service domain. */
    protected function getCommonName()
    {
        $cn = trim((string) ($this->details[\SSL\CN]['value'] ?? ''));
        if ($cn !== '') {
            return $cn;
        }
        return trim((string) ($this->account_details['domain'] ?? ''));
    }

    /** Non-empty SAN names stored for this service. */
    protected function getSanNames()
    {
        return array_values(array_filter((array) ($this->details[\SSL\SAN]['value'] ?? [])));
    }

    /**
     * The domainNameList value for register_ssl: the common name, plus any SANs
     * joined with commas. NOTE: the API documents a single `domainNameList`
     * field; the comma-joined multi-domain form is an assumption pending
     * confirmation from Nhan Hoa (see module header / report).
     */
    protected function buildDomainNameList()
    {
        $names = [$this->getCommonName()];
        foreach ($this->getSanNames() as $san) {
            $names[] = $san;
        }
        $names = array_values(array_unique(array_filter($names)));
        return implode(',', $names);
    }

    /** Require and return the configured Nhan Hoa service code. */
    protected function getConfiguredServiceCode()
    {
        $code = trim((string) ($this->options['serviceCode']['value'] ?? ''));
        if ($code === '') {
            $code = trim((string) ($this->details['serviceCode']['value'] ?? ''));
        }
        if ($code === '') {
            throw new Exception('Nhan Hoa SSL service code is not configured for this HostBill product.');
        }
        return $code;
    }

    /**
     * Certificate type for Nhan Hoa SSL. The API exposes no cert-type metadata
     * and never consumes contacts/organization data, so DV (domain validation)
     * is used — the lightest contact set.
     */
    protected function getProductCertType()
    {
        return 'DV';
    }

    // ------------------------------------------------------------------
    // Status mapping
    // ------------------------------------------------------------------

    /**
     * Map a Nhan Hoa SSL status onto a HostBill SSL status. The API reports
     * status as a free-text `content` string (Vietnamese), so matching is by
     * keyword; the expiry timestamp is used as a secondary signal.
     */
    protected function mapVendorStatus($raw, array $info = [])
    {
        $text = $this->normaliseText($raw);

        // Activated / issued.
        if ($this->textHasAny($text, ['kich hoat', 'da cap', 'hoan thanh', 'active', 'issued', 'completed', 'thanh cong'])) {
            // Guard against an activated-but-already-expired order.
            if (!empty($info['ord_end_time']) && (int) $info['ord_end_time'] > 0 && (int) $info['ord_end_time'] < time()) {
                return \SSL\STATUS\EXPIRED;
            }
            return \SSL\STATUS\ISSUED;
        }

        if ($this->textHasAny($text, ['het han', 'expire'])) {
            return \SSL\STATUS\EXPIRED;
        }

        if ($this->textHasAny($text, ['huy', 'tu choi', 'cancel', 'revoke', 'reject', 'that bai', 'failed'])) {
            return \SSL\STATUS\CANCELLED;
        }

        if ($this->textHasAny($text, ['cho', 'dang xu ly', 'xac thuc', 'xu ly', 'pending', 'process', 'validation', 'validate'])) {
            return \SSL\STATUS\VALIDATION;
        }

        return \SSL\STATUS\PROCESSING;
    }

    /** Lower-case, accent-stripped text for keyword matching. */
    protected function normaliseText($value)
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        if (function_exists('iconv')) {
            $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
            if ($ascii !== false) {
                $value = strtolower($ascii);
            }
        }
        // Drop any remaining non-letter noise so matching stays robust.
        $value = preg_replace('/[^a-z0-9\s]/', '', $value);
        return preg_replace('/\s+/', ' ', (string) $value);
    }

    /** True when $haystack contains any of the given needles. */
    protected function textHasAny($haystack, array $needles)
    {
        foreach ($needles as $needle) {
            if ($needle !== '' && strpos($haystack, $needle) !== false) {
                return true;
            }
        }
        return false;
    }

    /** Short, plain-text summary of a product's HTML service_content for labels. */
    protected function summariseServiceContent($html)
    {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/', ' ', $text));
        if (mb_strlen($text, 'UTF-8') > 80) {
            $text = mb_substr($text, 0, 77, 'UTF-8') . '...';
        }
        return $text;
    }

    /** Format a Nhan Hoa expiry (unix timestamp or date) as YYYY-MM-DD. */
    protected function formatExpiry($value)
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (ctype_digit($value)) {
            return date('Y-m-d', (int) $value);
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    // ------------------------------------------------------------------
    // Transport
    // ------------------------------------------------------------------

    /** Lazily-built Nhan Hoa API transport from the server connection fields. */
    protected function api()
    {
        if ($this->apiClient === null) {
            $this->apiClient = new Nhanhoa_ssl_Api(
                (string) ($this->connect_data['username'] ?? ''),
                (string) ($this->connect_data['password'] ?? ''),
                (string) ($this->connect_data['field1'] ?? ''),
                !empty($this->connect_data['secure']),
                __DIR__ . DIRECTORY_SEPARATOR . 'nhanhoa_ssl.log'
            );
        }
        return $this->apiClient;
    }

    /**
     * Run one API command and return its payload, throwing on any non-ok
     * response so the try/catch bodies above can present a single error.
     */
    protected function get($cmd, array $formdata = [])
    {
        $result = $this->api()->call($cmd, $formdata);
        if (!Nhanhoa_ssl_Api::ok($result)) {
            throw new Exception(Nhanhoa_ssl_Api::msg($result));
        }
        return $result;
    }

    // ------------------------------------------------------------------
    // Admin service-page view
    // ------------------------------------------------------------------

    /**
     * Flat, read-only snapshot of the stored certificate/order state for the
     * admin service page (admin/myadminarea.tpl). Reads persisted $details only
     * — no API call — so it is safe to render without a connection. The $set
     * argument is accepted for signature compatibility with the generic SSL
     * admin flow but is unused: the Nhan Hoa API exposes no DCV self-service.
     *
     * @param array|false $set unused (API has no DCV method-change surface)
     * @return array
     */
    public function CertDetails($set = false)
    {
        $status = (string) ($this->details[\SSL\STATUS]['value'] ?? '');

        return [
            'cn'           => (string) ($this->details[\SSL\CN]['value'] ?? ''),
            'order_id'     => (string) ($this->details[\SSL\ORDERID]['value'] ?? ''),
            'service_code' => (string) ($this->details['serviceCode']['value'] ?? ''),
            'status'       => $status,
            \SSL\STATUS    => $status,
            'ord_name'     => (string) ($this->details['ordName']['value'] ?? ''),
            'expiry'       => $this->formatExpiry($this->details['ordEndTime']['value'] ?? ''),
            'status_raw'   => (string) ($this->details['statusRaw']['value'] ?? ''),
            'san'          => $this->getSanNames(),
        ];
    }

    // ------------------------------------------------------------------
    // Ownership check (used by the REST api routes)
    // ------------------------------------------------------------------

    /**
     * True when the given HostBill account/service belongs to the given client.
     * The REST routes call this to enforce ownership — auth alone only proves a
     * client is calling, not that this client owns the service id in the URL.
     */
    public function serviceBelongsToClient($accountId, $clientId)
    {
        if (!$accountId || !$clientId) {
            return false;
        }

        $stmt = $this->db->prepare('SELECT client_id FROM hb_accounts WHERE id = :id');
        $stmt->execute([':id' => (int) $accountId]);
        $owner = (int) $stmt->fetchColumn();
        $stmt->closeCursor();

        return $owner === (int) $clientId;
    }
}
