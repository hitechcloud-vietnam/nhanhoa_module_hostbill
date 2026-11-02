<?php

/**
 * Nhân Hòa Hosting — bán và tự động hoá hosting mua lại qua API đại lý Nhân Hòa.
 *
 * Nhân Hòa là một *nhà cung cấp lại* (reseller upstream): HostBill không quản lý
 * máy chủ hosting, nó đặt hàng hosting từ kho của Nhân Hòa qua API đại lý
 * (https://api.nhanhoa.com/) và theo dõi vòng đời đơn hàng đó.
 *
 * API HOSTING CỦA NHÂN HÒA RẤT HẸP — ĐÂY LÀ RÀNG BUỘC LỚN NHẤT CỦA MODULE.
 *
 * Toàn bộ API hosting (https://docs.nhanhoa.com/#/hosting) chỉ có BA lệnh:
 *
 *   register_hosting   đặt một đơn hosting mới (hostingCode + domain + số tháng)
 *   renew_hosting      gia hạn một đơn đã có (ord_id + số tháng)
 *   get_infohosting    đọc ord_id / tên đơn / ngày hết hạn / trạng thái
 *
 * KHÔNG có lệnh suspend, unsuspend, terminate, đổi gói hay đổi mật khẩu, và
 * KHÔNG có đăng nhập một chạm (SSO) vào control panel. Những phương thức vòng
 * đời đó vẫn được hiện thực đầy đủ ở đây (HostBill gọi chúng), nhưng chúng chỉ
 * có thể cập nhật trạng thái phía HostBill và ghi lại một cảnh báo rằng thao tác
 * tương ứng phải làm thủ công tại https://customer.nhanhoa.com — API đại lý
 * không cho làm tự động. Xem README.
 *
 * register_hosting trả lời *bất đồng bộ* ("Hosting đã được gửi yêu cầu đăng ký"):
 * nó không trả về ord_id ngay. Create() vì vậy đặt đơn, rồi cố đọc ord_id bằng
 * get_infohosting; nếu Nhân Hòa chưa cấp kịp thì đơn vẫn được coi là tạo thành
 * công và ord_id được điền sau bằng nút "Đồng bộ" ở trang admin hoặc cron.
 *
 * Kiểm tra lại contract:
 *   cd /home/HiTechCloud_HostBill_WHMCS_Tools_SDK/hostbill-sdk
 *   PHP_INI_SCAN_DIR=/dev/null php -d extension=tokenizer hb class HostingModule
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.1.0
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once __DIR__ . DS . 'lib' . DS . 'class.nhanhoa_hosting_api.php';

class nhanhoa_hosting extends HostingModule
{
    const LOG_FILE = 'nhanhoa_hosting.log';

    /** Service detail slots. Keys are fixed (HostBill stores them verbatim). */
    const D_USERNAME = 'option1';   // ord_name, e.g. ORD411257
    const D_PASSWORD = 'option2';   // control-panel password (API never returns one)
    const D_DOMAIN   = 'option4';   // hosting domain
    const D_CODE     = 'option5';   // Nhan Hoa service (plan) code
    const D_ORDID    = 'option6';   // Nhan Hoa order id, used by renew/get_info

    protected $version = '1.1.0';

    protected $modname = 'Nhân Hòa Hosting';

    protected $description = 'Đặt và quản lý hosting mua lại qua API đại lý Nhân Hòa: tạo đơn, gia hạn, đồng bộ trạng thái và hiển thị thông tin cho khách.';

    protected $info = [
        self::INFO_HAVEADMIN => true,
        self::INFO_HAVEUSER  => true,
        self::INFO_HAVETPL   => true,
        self::INFO_HAVEAPI   => true,
        self::INFO_HAVECRON  => true,
    ];

    /**
     * App (server) connection fields.
     *
     *   username  -> auth-user   (API user)
     *   password  -> auth-pwd    (API password)
     *   custom.reseller_id -> auth-id (reseller account ID)
     */
    protected $serverFields = [
        self::CONNECTION_FIELD_USERNAME => true,
        self::CONNECTION_FIELD_PASSWORD => true,
        self::CONNECTION_FIELD_CUSTOM   => [
            'reseller_id' => [
                'type'        => 'input',
                'name'        => 'Reseller ID (auth-id)',
                'description' => 'ID tài khoản đại lý Nhân Hòa — chính là giá trị auth-id của API. Xem tại customer.nhanhoa.com.',
            ],
            'debug_log' => [
                'type'        => 'check',
                'name'        => 'Ghi log gỡ lỗi',
                'description' => 'Ghi toàn bộ request/response API vào ' . self::LOG_FILE . ' (mật khẩu được che). Chỉ bật khi cần chẩn đoán.',
            ],
        ],
    ];

    protected $serverFieldsDescription = [
        self::CONNECTION_FIELD_USERNAME => 'API user của đại lý Nhân Hòa (auth-user).',
        self::CONNECTION_FIELD_PASSWORD => 'API password của đại lý Nhân Hòa (auth-pwd).',
    ];

    protected $commands = [
        'Create',
        'Suspend',
        'Unsuspend',
        'Terminate',
        'ChangePackage',
        'ChangePassword',
    ];

    /**
     * Product configuration options.
     *
     *   hostingCode  — loadable, fed by service_code (group_id=1)
     *   os_template  — loadable, fed by template_code (VPS-oriented, optional)
     *   hosting_months — plain input, minimum 6 (API rule)
     */
    protected $options = [
        'hostingCode' => [
            'name'        => 'Mã gói hosting (hostingCode)',
            'type'        => self::OPTIONS_TYPE_LOAD,
            'value'       => false,
            'variable'    => 'hostingCode',
            'description' => 'Mã dịch vụ hosting của Nhân Hòa. Nạp từ API (service_code, group_id=1) sau khi chọn app.',
        ],
        'os_template' => [
            'name'        => 'Template hệ điều hành (tuỳ chọn)',
            'type'        => self::OPTIONS_TYPE_LOAD,
            'value'       => false,
            'variable'    => 'os_template',
            'description' => 'Nạp từ API template_code. API này dành cho Cloud VPS; với hosting thường bỏ trống. Không gửi lên khi đặt hosting trừ khi được điền.',
        ],
        'hosting_months' => [
            'name'        => 'Số tháng đăng ký',
            'type'        => self::OPTIONS_TYPE_INPUT,
            'value'       => '12',
            'variable'    => 'hosting_months',
            'description' => 'Số tháng gửi cho register_hosting/renew_hosting. Nhân Hòa yêu cầu tối thiểu 6; giá trị nhỏ hơn sẽ được nâng lên 6.',
        ],
        'control_panel_url' => [
            'name'        => 'URL control panel hiện cho khách',
            'type'        => self::OPTIONS_TYPE_INPUT,
            'value'       => '',
            'variable'    => 'control_panel_url',
            'description' => 'Link control panel (vd https://cpanel.nhanhoa.com). Nhân Hòa không có SSO qua API nên đây chỉ là liên kết tĩnh. Bỏ trống = ẩn.',
        ],
    ];

    protected $details = [
        'option1' => ['name' => 'username',   'value' => false, 'type' => 'input', 'default' => false],
        'option2' => ['name' => 'password',   'value' => false, 'type' => 'input', 'default' => false],
        'option4' => ['name' => 'domain',     'value' => false, 'type' => 'input', 'default' => false],
        'option5' => ['name' => 'hostingCode','value' => false, 'type' => 'input', 'default' => false],
        'option6' => ['name' => 'order_id',   'value' => false, 'type' => 'input', 'default' => false],
    ];

    /** @var Nhanhoa_hosting_Api|null */
    protected $api = null;

    protected $connect_data = [];

    // =================================================================
    // Module lifecycle
    // =================================================================

    public function install()
    {
        $sql = @file_get_contents(__DIR__ . DS . 'install.sql');

        if ($sql === false) {
            // No schema is required for normal operation (details live in
            // hb_accounts); a missing install.sql is not fatal.
            return true;
        }

        $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $this->db->exec($statement);
            } catch (Exception $e) {
                $this->addError('nhanhoa_hosting: cài đặt thất bại — ' . $e->getMessage());

                return false;
            }
        }

        return true;
    }

    public function upgrade($old)
    {
        return $this->install();
    }

    public function uninstall()
    {
        return true;
    }

    // =================================================================
    // Connection
    // =================================================================

    public function connect($server)
    {
        $this->connect_data = is_array($server) ? $server : [];
        $custom             = $this->customFields();

        $this->api = new Nhanhoa_hosting_Api(
            isset($this->connect_data['username']) ? $this->connect_data['username'] : '',
            isset($this->connect_data['password']) ? $this->connect_data['password'] : '',
            isset($custom['reseller_id']) ? $custom['reseller_id'] : '',
            !empty($custom['debug_log']),
            __DIR__ . DS . self::LOG_FILE
        );

        $this->connected = $this->api->hasCredentials();

        return $this->connected;
    }

    /**
     * @return Nhanhoa_hosting_Api
     * @throws Exception
     */
    public function api()
    {
        if (!$this->api) {
            throw new Exception('nhanhoa_hosting: chưa kết nối app. Kiểm tra sản phẩm đã chọn app chưa.');
        }

        return $this->api;
    }

    protected function customFields()
    {
        return isset($this->connect_data['custom']) && is_array($this->connect_data['custom'])
            ? $this->connect_data['custom']
            : [];
    }

    /** One API command, always returning the normalised array. */
    protected function get($cmd, array $formdata = [], array $postfields = [])
    {
        return $this->api()->call($cmd, $formdata, $postfields);
    }

    public function testConnection($log = null)
    {
        try {
            $result = $this->get('get_balance');
        } catch (Exception $e) {
            $this->addError('Nhân Hòa: ' . $e->getMessage());

            return false;
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            $this->addError('Nhân Hòa: ' . Nhanhoa_hosting_Api::msg($result));

            return false;
        }

        $this->addInfo(sprintf(
            'Kết nối Nhân Hòa OK — số dư tài khoản đại lý: %s đ',
            number_format((float) (isset($result['balance']) ? $result['balance'] : 0))
        ));

        return true;
    }

    // =================================================================
    // Service lifecycle
    // =================================================================

    /**
     * Provision a hosting order with register_hosting.
     *
     * register_hosting returns only {status,msg} and is asynchronous, so after a
     * successful request we try get_infohosting to capture the order id. Failing
     * to read it back is NOT a provisioning failure — the order was placed, and
     * the id is filled in later by the admin "Đồng bộ" button or cron.
     */
    public function Create()
    {
        $domain = $this->serviceDomain();

        if ($domain === '') {
            return $this->fail('Create', 'dịch vụ không có tên miền hợp lệ (kiểm tra ô Domain của dịch vụ).');
        }

        $code = $this->hostingCode();

        if ($code === '') {
            return $this->fail('Create', 'chưa chọn mã gói hosting (hostingCode) trong cấu hình sản phẩm.');
        }

        $months = $this->hostingMonths();

        try {
            $result = $this->get('register_hosting', [
                'hostingCode'    => $code,
                'domainNameList' => $domain,
                'hostingMonth'   => $months,
            ]);
        } catch (Exception $e) {
            return $this->fail('Create', $e->getMessage());
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            return $this->fail('Create', Nhanhoa_hosting_Api::msg($result));
        }

        // Store what we know immediately so the service is never left blank.
        $this->setDetail(self::D_DOMAIN, $domain);
        $this->setDetail(self::D_CODE, $code);

        // Control-panel password: the API never issues one, so keep whatever
        // HostBill generated for the service (available via setRootPass/details).
        $generated = $this->detail(self::D_PASSWORD, '');
        if ($generated !== '') {
            $this->setDetail(self::D_PASSWORD, $generated);
        }

        // Best-effort read-back of the order id (async; may not be ready yet).
        $this->fetchOrderInfo($domain, $code);

        $this->accountLog('register_hosting ' . $domain . ' (' . $code . ')', true,
            Nhanhoa_hosting_Api::msg($result, ''), 'nhCreate');

        return true;
    }

    /**
     * Suspend.
     *
     * The Nhan Hoa reseller API has NO suspend command. HostBill still marks the
     * service suspended; we record the intent and raise a visible notice that the
     * hosting must be suspended by hand at customer.nhanhoa.com. Returning true
     * keeps HostBill's lifecycle state consistent with the admin's action.
     */
    public function Suspend()
    {
        $this->addInfo($this->unsupportedNotice('tạm ngưng (suspend)'));
        $this->accountLog('suspend (no API) — cần thao tác thủ công', true, '', 'nhSuspend');

        return true;
    }

    public function Unsuspend()
    {
        $this->addInfo($this->unsupportedNotice('mở lại (unsuspend)'));
        $this->accountLog('unsuspend (no API) — cần thao tác thủ công', true, '', 'nhUnsuspend');

        return true;
    }

    /**
     * Terminate.
     *
     * No terminate command exists either. We clear nothing on the Nhan Hoa side;
     * cancellation of a reseller hosting order is a manual/billing action at
     * customer.nhanhoa.com. HostBill is allowed to mark the service terminated.
     */
    public function Terminate()
    {
        $this->addInfo($this->unsupportedNotice('huỷ dịch vụ (terminate)'));
        $this->accountLog('terminate (no API) — cần thao tác thủ công', true, '', 'nhTerminate');

        return true;
    }

    /**
     * Change package.
     *
     * No "change plan" command exists. We update the stored plan code so the
     * service reflects the new product, and warn that the actual plan change must
     * be arranged with Nhan Hoa.
     */
    public function ChangePackage()
    {
        $code = $this->hostingCode();

        if ($code !== '') {
            $this->setDetail(self::D_CODE, $code);
        }

        $this->addInfo($this->unsupportedNotice('đổi gói (change package)')
            . ' Mã gói lưu trong HostBill đã được cập nhật thành "' . $code . '".');
        $this->accountLog('changepackage -> ' . $code . ' (no API)', true, '', 'nhChangePackage');

        return true;
    }

    /**
     * Change password.
     *
     * The API returns no control-panel credentials and offers no password
     * command. We store the new password locally so the client area shows what
     * the admin set, and note it must also be changed in the control panel.
     */
    public function ChangePassword()
    {
        $new = $this->detail(self::D_PASSWORD, '');

        if ($new === '' && isset($this->account_details['password'])) {
            $new = (string) $this->account_details['password'];
        }

        if ($new !== '') {
            $this->setDetail(self::D_PASSWORD, $new);
        }

        $this->addInfo($this->unsupportedNotice('đổi mật khẩu (change password)')
            . ' Mật khẩu mới được lưu trong HostBill nhưng phải đổi trong control panel.');
        $this->accountLog('changepassword (no API)', true, '', 'nhChangePassword');

        return true;
    }

    /**
     * Renew — extend the order with renew_hosting.
     *
     * Not a core lifecycle keyword, so it is driven from the admin panel (and can
     * be wired to billing hooks). Needs the stored order id.
     *
     * @return true|string true on success, otherwise an error string.
     */
    public function Renew($months = null)
    {
        $ordId = $this->detail(self::D_ORDID, '');

        if ($ordId === '') {
            // Try to resolve it first.
            $this->fetchOrderInfo($this->serviceDomain(), $this->hostingCode());
            $ordId = $this->detail(self::D_ORDID, '');
        }

        if ($ordId === '') {
            return 'chưa biết mã đơn hàng (ord_id) — hãy Đồng bộ trước.';
        }

        $months = $months === null ? $this->hostingMonths() : max(6, (int) $months);

        try {
            $result = $this->get('renew_hosting', [
                'ord_id'       => $ordId,
                'hostingMonth' => $months,
            ]);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            return Nhanhoa_hosting_Api::msg($result);
        }

        $this->accountLog('renew_hosting ord=' . $ordId . ' months=' . $months, true, '', 'nhRenew');

        return true;
    }

    /**
     * Read the live order back from Nhan Hoa and refresh stored details + status.
     *
     * @return array the (possibly updated) $this->details
     */
    public function getSynchInfo()
    {
        $this->fetchOrderInfo($this->serviceDomain(), $this->hostingCode());

        return $this->details;
    }

    public function Synchronize($db, $id)
    {
        return $this->getSynchInfo();
    }

    /**
     * get_infohosting -> store ord_id / ord_name / expiry / status.
     *
     * @return array|null the normalised order info, or null when unavailable.
     */
    public function fetchOrderInfo($domain, $code)
    {
        $domain = trim((string) $domain);
        $code   = trim((string) $code);

        if ($domain === '' || $code === '') {
            return null;
        }

        try {
            $result = $this->get('get_infohosting', [
                'hostingCode' => $code,
                'domainName'  => $domain,
            ]);
        } catch (Exception $e) {
            $this->addError('Nhân Hòa: ' . $e->getMessage());

            return null;
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            // Not necessarily an error right after create — the order may not be
            // queryable yet. Surface the message only for diagnostics.
            HBDebug::debug('nhanhoa_hosting', 'get_infohosting: ' . Nhanhoa_hosting_Api::msg($result, ''));

            return null;
        }

        if (!empty($result['ord_id'])) {
            $this->setDetail(self::D_ORDID, (string) $result['ord_id']);
        }

        if (!empty($result['ord_name'])) {
            $this->setDetail(self::D_USERNAME, (string) $result['ord_name']);
        }

        $this->setDetail(self::D_DOMAIN, $domain);
        $this->setDetail(self::D_CODE, $code);

        return [
            'ord_id'       => isset($result['ord_id']) ? (string) $result['ord_id'] : '',
            'ord_name'     => isset($result['ord_name']) ? (string) $result['ord_name'] : '',
            'ord_end_time' => isset($result['ord_end_time']) ? (int) $result['ord_end_time'] : 0,
            'content'      => isset($result['content']) ? (string) $result['content'] : '',
            'status'       => $this->mapStatus(isset($result['content']) ? $result['content'] : ''),
        ];
    }

    /**
     * Map a Nhan Hoa order "content" string to a HostBill service status.
     *
     * The API returns Vietnamese human text ("Đã kích hoạt." = activated), not a
     * code, so we match on substrings.
     */
    public function mapStatus($content)
    {
        $c = mb_strtolower(trim((string) $content));

        if ($c === '') {
            return 'Pending';
        }
        if (mb_strpos($c, 'kích hoạt') !== false || mb_strpos($c, 'active') !== false) {
            return 'Active';
        }
        if (mb_strpos($c, 'tạm ngưng') !== false || mb_strpos($c, 'tạm dừng') !== false
            || mb_strpos($c, 'suspend') !== false) {
            return 'Suspended';
        }
        if (mb_strpos($c, 'huỷ') !== false || mb_strpos($c, 'hủy') !== false
            || mb_strpos($c, 'hết hạn') !== false || mb_strpos($c, 'terminate') !== false) {
            return 'Terminated';
        }

        return 'Pending';
    }

    // =================================================================
    // Loadable option lists (fed to the product config form)
    // =================================================================

    /**
     * Hosting plan codes — service_code with group_id=1.
     *
     * @return array<string,string> code => label
     */
    public function listServiceCodes($groupId = 1)
    {
        try {
            $result = $this->get('service_code', ['group_id' => (int) $groupId]);
        } catch (Exception $e) {
            $this->addError('nhanhoa_hosting: ' . $e->getMessage());

            return [];
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            $this->addError('nhanhoa_hosting: ' . Nhanhoa_hosting_Api::msg($result));

            return [];
        }

        $out  = [];
        $rows = $this->extractRows($result);

        foreach ($rows as $row) {
            if (!is_array($row) || empty($row['service_code'])) {
                continue;
            }

            $code  = (string) $row['service_code'];
            $label = $code;

            if (!empty($row['service_content'])) {
                $plain = trim(preg_replace('/\s+/', ' ', strip_tags((string) $row['service_content'])));
                if ($plain !== '') {
                    $label = $code . ' — ' . mb_substr($plain, 0, 80);
                }
            }

            $out[$code] = $label;
        }

        return $out;
    }

    /**
     * OS templates — template_code. VPS-oriented; offered for completeness.
     *
     * @return array<string,string> template_code => label
     */
    public function listTemplateCodes($type = 0)
    {
        try {
            $result = $this->get('template_code', ['type' => (int) $type]);
        } catch (Exception $e) {
            $this->addError('nhanhoa_hosting: ' . $e->getMessage());

            return [];
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            $this->addError('nhanhoa_hosting: ' . Nhanhoa_hosting_Api::msg($result));

            return [];
        }

        $out = [];

        foreach ($this->extractRows($result) as $row) {
            if (!is_array($row) || empty($row['template_code'])) {
                continue;
            }

            $key       = isset($row['template_id']) ? (string) $row['template_id'] : (string) $row['template_code'];
            $out[$key] = (string) $row['template_code'];
        }

        return $out;
    }

    /** Reseller balance (đ). */
    public function getBalance()
    {
        try {
            $result = $this->get('get_balance');
        } catch (Exception $e) {
            return null;
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            return null;
        }

        return isset($result['balance']) ? (float) $result['balance'] : 0.0;
    }

    /**
     * service_used — reseller-wide "services in use" report (docs: service_used.md).
     *
     * This is a reseller-account-level report (service groups and their order
     * counts), not a per-service call, so it is offered for admin dashboards and
     * the service widget rather than the provisioning flow. Returns the normalised
     * rows, or [] when the API is unreachable/unauthenticated.
     *
     * @return array<int,array>
     */
    public function serviceUsed()
    {
        try {
            $result = $this->get('service_used');
        } catch (Exception $e) {
            return [];
        }

        if (!Nhanhoa_hosting_Api::ok($result)) {
            return [];
        }

        return $this->extractRows($result);
    }

    /**
     * Pull the list of rows out of whatever envelope the API used: a `data`
     * array, a bare numeric array, or a single associative row.
     */
    protected function extractRows(array $result)
    {
        if (isset($result['data']) && is_array($result['data'])) {
            return array_values($result['data']);
        }

        // A bare list: numeric keys holding arrays.
        $numeric = array_filter($result, 'is_array');
        if ($numeric && array_keys($numeric) === range(0, count($numeric) - 1)) {
            return array_values($numeric);
        }

        // A single row delivered flat (the documented example for service_code).
        if (isset($result['service_code']) || isset($result['template_code'])) {
            return [$result];
        }

        return [];
    }

    // =================================================================
    // Config / detail helpers
    // =================================================================

    /** Selected hosting plan code from product config. */
    public function hostingCode()
    {
        $code = trim((string) $this->optionValue('hostingCode', ''));

        return $code !== '' ? $code : trim((string) $this->detail(self::D_CODE, ''));
    }

    /** Selected OS template (optional, VPS-oriented). */
    public function osTemplate()
    {
        return trim((string) $this->optionValue('os_template', ''));
    }

    /** Registration term in months, clamped to the API minimum of 6. */
    public function hostingMonths()
    {
        $months = (int) $this->optionValue('hosting_months', 12);

        return max(6, $months);
    }

    public function controlPanelUrl()
    {
        return trim((string) $this->optionValue('control_panel_url', ''));
    }

    public function orderId()
    {
        return trim((string) $this->detail(self::D_ORDID, ''));
    }

    /** The service's hosting domain. */
    public function serviceDomain()
    {
        foreach ([$this->detail(self::D_DOMAIN, ''), isset($this->account_details['domain']) ? $this->account_details['domain'] : ''] as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    public function optionValue($name, $default = '')
    {
        if (isset($this->account_config[$name])) {
            $value = $this->configValue($this->account_config[$name]);

            if ($value !== false && $value !== '' && $value !== null) {
                return $value;
            }
        }

        if (isset($this->options[$name]) && array_key_exists('value', $this->options[$name])) {
            $value = $this->options[$name]['value'];

            if ($value !== false && $value !== '' && $value !== null) {
                return $value;
            }
        }

        return $default;
    }

    public function optionFlag($name)
    {
        $value = $this->optionValue($name, '');

        return !($value === '' || $value === '0' || $value === 0 || $value === false || $value === null);
    }

    protected function configValue($config)
    {
        if (!is_array($config)) {
            return $config;
        }

        if (isset($config['type'])) {
            switch ($config['type']) {
                case 'input':
                case 'textarea':
                case 'password':
                    return isset($config['value']) ? $config['value'] : false;

                case 'select':
                case 'radio':
                case 'searchselect':
                case 'loadable':
                    return isset($config['variable_id']) ? $config['variable_id']
                        : (isset($config['value']) ? $config['value'] : false);

                case 'qty':
                case 'slider':
                case 'sliderinput':
                    return isset($config['qty']) ? $config['qty'] : false;
            }
        }

        foreach (['variable_id', 'qty', 'value'] as $key) {
            if (isset($config[$key]) && $config[$key] !== '' && $config[$key] !== false) {
                return $config[$key];
            }
        }

        return false;
    }

    public function detail($key, $default = '')
    {
        if (isset($this->details[$key]['value']) && $this->details[$key]['value'] !== false && $this->details[$key]['value'] !== '') {
            return $this->details[$key]['value'];
        }

        if (isset($this->account_details[$key]) && $this->account_details[$key] !== '') {
            return $this->account_details[$key];
        }

        return $default;
    }

    protected function setDetail($key, $value)
    {
        $this->details[$key]['value'] = $value;
        $this->account_details[$key]  = $value;

        return $this;
    }

    protected function unsupportedNotice($what)
    {
        return 'Nhân Hòa: API đại lý không hỗ trợ ' . $what . ' tự động. '
            . 'Hãy thực hiện thủ công tại https://customer.nhanhoa.com.';
    }

    protected function fail($what, $message)
    {
        $this->addError('Nhân Hòa ' . $what . ': ' . $message);
        $this->accountLog($what, false, $message, 'nh' . $what);

        return false;
    }

    public function accountId()
    {
        return (int) (isset($this->account_details['id']) ? $this->account_details['id'] : 0);
    }

    public function clientId()
    {
        foreach ([$this->account_details, $this->client_data] as $source) {
            if (!empty($source['client_id'])) {
                return (int) $source['client_id'];
            }
        }

        return isset($this->client_data['id']) ? (int) $this->client_data['id'] : 0;
    }

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

    /** Values core injects into service emails and templates. */
    public function PrepareSpecific($details)
    {
        $details = is_array($details) ? $details : [];

        $details['domain']       = $this->serviceDomain();
        $details['hostingCode']  = $this->hostingCode();
        $details['order_id']     = $this->orderId();
        $details['order_name']   = $this->detail(self::D_USERNAME, '');

        return $details;
    }

    public function getExtraFields()
    {
        return array_filter([
            'Tên miền'    => $this->serviceDomain(),
            'Mã gói'      => $this->hostingCode(),
            'Mã đơn hàng' => $this->orderId(),
            'Tên đơn'     => $this->detail(self::D_USERNAME, ''),
        ], function ($value) {
            return $value !== '';
        });
    }
}
