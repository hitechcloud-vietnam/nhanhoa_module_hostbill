<?php

/**
 * Email Hosting resold through Nhan Hoa (Nhân Hòa).
 *
 * This is a RESELLER provisioning module, not a control-panel module. Nhan Hoa
 * runs the mail platform; HostBill only sends provisioning intents through the
 * reseller API at https://api.nhanhoa.com/ and reads the order back. That shapes
 * every decision here, and the biggest one is what the API does NOT expose.
 *
 * WHAT THE API SUPPORTS (docs.nhanhoa.com/email.md):
 *   register_email   create a service        -> Create()
 *   renew_email      extend a service        -> renewOrder() / admin "Renew"
 *   get_infoemail    read order id + status  -> getSynchInfo() / Create() follow-up
 *   service_code     list plan codes         -> loadServiceCodes() (loadable)
 *   get_balance      reseller balance        -> testConnection()
 *
 * WHAT IT DOES NOT SUPPORT — and so is handled honestly, not faked:
 *   There is no suspend, unsuspend, terminate, change-package, change-password or
 *   mailbox-management command in the reseller API. Nhan Hoa drives those from its
 *   own panel (customer.nhanhoa.com). The lifecycle methods below therefore keep
 *   HostBill's local state consistent and LOG + record what a human must still do
 *   upstream, rather than pretend an API call happened. Suspending here does not
 *   stop mail at Nhan Hoa; termination is effected by letting the term lapse (do
 *   not renew). This is the real contract of the API, documented at each method.
 *
 * ONE SERVICE = ONE DOMAIN. Email Hosting is sold per domain (domainNameList),
 * and the order is identified upstream by (serviceCode, domainName) -> ord_id.
 *
 * Verify the parent contract with:
 *   /home/HiTechCloud_HostBill_WHMCS_Tools_SDK/hostbill-sdk/hb class HostingModule
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.1.1
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once __DIR__ . DS . 'lib' . DS . 'class.nhanhoa_email_api.php';

class nhanhoa_email extends HostingModule
{
    const ORDERS_TABLE = 'hb_nhanhoa_email_orders';

    const LOG_FILE = 'nhanhoa_email.log';

    /** Service-detail slots. Keys are HostBill's option1..optionN; never rename. */
    const D_ORDID   = 'option1';
    const D_ORDNAME = 'option2';
    const D_END     = 'option3';
    const D_DOMAIN  = 'option4';
    const D_CODE    = 'option5';

    protected $version = '1.1.1';

    protected $modname = 'Nhan Hoa Email Hosting';

    protected $description = 'Bán lại Email Hosting của Nhân Hòa qua API đại lý: đăng ký, gia hạn, đọc trạng thái đơn hàng. API đại lý không có lệnh tạm ngưng/huỷ/đổi mật khẩu — các thao tác đó ghi nhận tại HostBill và thực hiện ở customer.nhanhoa.com.';

    protected $info = [
        self::INFO_HAVEADMIN => true,
        self::INFO_HAVEUSER  => true,
        self::INFO_HAVETPL   => true,
        self::INFO_HAVEAPI   => true,
        self::INFO_HAVECRON  => true,
    ];

    protected $serverFields = [
        self::CONNECTION_FIELD_USERNAME => true,
        self::CONNECTION_FIELD_PASSWORD => true,
        self::CONNECTION_FIELD_CUSTOM   => [
            'auth_id' => [
                'type'        => 'input',
                'name'        => 'ID đại lý (auth-id)',
                'description' => 'ID tài khoản đại lý Nhân Hòa, ví dụ 37254. Lấy trong mục API ở customer.nhanhoa.com.',
            ],
            'debug_log' => [
                'type'        => 'check',
                'name'        => 'Ghi log gỡ lỗi',
                'description' => 'Ghi request/response (đã che mật khẩu) vào includes/modules/Hosting/nhanhoa_email/' . self::LOG_FILE,
            ],
        ],
    ];

    protected $serverFieldsDescription = [
        self::CONNECTION_FIELD_USERNAME => 'User API Nhân Hòa (auth-user)',
        self::CONNECTION_FIELD_PASSWORD => 'Password API Nhân Hòa (auth-pwd)',
    ];

    /** Admin service-page action buttons. */
    protected $commands = [
        'Create',
        'Suspend',
        'Unsuspend',
        'Terminate',
        'ChangePackage',
    ];

    protected $options = [
        // Loadable from service_code (group_id 2). The method returns [code,label]
        // pairs; if the API is unreachable the admin can still type a code.
        'serviceCode' => [
            'name'        => 'Mã gói Email Hosting',
            'type'        => self::OPTIONS_TYPE_LOAD,
            'value'       => false,
            'default'     => 'loadServiceCodes',
            'variable'    => 'serviceCode',
            'description' => 'Mã dịch vụ gói email hosting của Nhân Hòa (nhóm Email). Danh sách nạp từ API; nếu không nạp được, nhập tay mã dịch vụ.',
        ],
        // Fallback when the loadable list cannot be fetched, and the value that
        // actually gets sent when an admin prefers to pin a code by hand.
        'serviceCodeText' => [
            'name'        => 'Mã gói (nhập tay)',
            'type'        => self::OPTIONS_TYPE_INPUT,
            'value'       => '',
            'variable'    => 'serviceCodeText',
            'description' => 'Dùng khi không nạp được danh sách ở trên. Nếu điền, giá trị này được ưu tiên.',
        ],
        'serviceMonth' => [
            'name'        => 'Số tháng đăng ký',
            'type'        => self::OPTIONS_TYPE_INPUT,
            'value'       => '12',
            'variable'    => 'serviceMonth',
            'description' => 'Nhân Hòa yêu cầu tối thiểu 6 tháng (6, 12, 24, 36). Giá trị dưới 6 sẽ bị nâng lên 6.',
        ],
    ];

    protected $details = [
        'option1' => ['name' => 'ord_id', 'value' => false, 'type' => 'input', 'default' => false],
        'option2' => ['name' => 'ord_name', 'value' => false, 'type' => 'input', 'default' => false],
        'option3' => ['name' => 'end_time', 'value' => false, 'type' => 'input', 'default' => false],
        'option4' => ['name' => 'domain', 'value' => false, 'type' => 'input', 'default' => false],
        'option5' => ['name' => 'service_code', 'value' => false, 'type' => 'input', 'default' => false],
    ];

    /** @var Nhanhoa_Email_Api|null */
    protected $api = null;

    protected $connect_data = [];

    // =================================================================
    // Module lifecycle
    // =================================================================

    public function install()
    {
        $sql = @file_get_contents(__DIR__ . DS . 'install.sql');

        if ($sql === false) {
            $this->addError('nhanhoa_email: không đọc được install.sql');

            return false;
        }

        $sql = preg_replace('/^\s*(--|#).*$/m', '', $sql);

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            try {
                $this->db->exec($statement);
            } catch (Exception $e) {
                $this->addError('nhanhoa_email: cài đặt thất bại — ' . $e->getMessage());

                return false;
            }
        }

        return true;
    }

    public function upgrade($old)
    {
        if (!$this->install()) {
            return false;
        }

        // Daily sync task: refresh order state/expiry from Nhan Hoa unattended.
        try {
            $q = $this->db->prepare(
                "REPLACE INTO `hb_cron_tasks` (`task`, `name`, `status`, `run_every`, `run_every_time`, `profile_id`)
                 VALUES (:task, :name, 1, 'Time', '1200', 1)"
            );
            $q->execute([
                ':task' => 'custom:' . $this->getModuleId() . ':call_Daily',
                ':name' => 'Module - ' . $this->getModName() . ', daily order sync',
            ]);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_email', 'không đăng ký được cron: ' . $e->getMessage());
        }

        return true;
    }

    public function uninstall()
    {
        // Keep the orders table: it is the only local record mapping an order id
        // back to a service, and that mapping is what a human needs to find the
        // service at Nhan Hoa after HostBill forgets it.
        return true;
    }

    // =================================================================
    // Connection
    // =================================================================

    public function connect($server)
    {
        $this->connect_data = is_array($server) ? $server : [];
        $custom             = $this->customFields();

        $this->api = new Nhanhoa_Email_Api(
            isset($this->connect_data['username']) ? $this->connect_data['username'] : '',
            isset($this->connect_data['password']) ? $this->connect_data['password'] : '',
            isset($custom['auth_id']) ? $custom['auth_id'] : '',
            !empty($custom['debug_log']),
            __DIR__ . DS . self::LOG_FILE
        );

        $this->connected = $this->api->hasCredentials();

        return $this->connected;
    }

    /**
     * @return Nhanhoa_Email_Api
     * @throws Exception
     */
    public function api()
    {
        if (!$this->api) {
            throw new Exception('nhanhoa_email: chưa kết nối app. Kiểm tra sản phẩm đã chọn app chưa.');
        }

        return $this->api;
    }

    protected function customFields()
    {
        return isset($this->connect_data['custom']) && is_array($this->connect_data['custom'])
            ? $this->connect_data['custom']
            : [];
    }

    public function testConnection($log = null)
    {
        try {
            $result = $this->api()->balance();
        } catch (Exception $e) {
            $this->addError('Nhân Hòa: ' . $e->getMessage());

            return false;
        }

        if (!Nhanhoa_Email_Api::ok($result)) {
            $this->addError('Nhân Hòa: ' . Nhanhoa_Email_Api::msg($result));

            return false;
        }

        $balance = isset($result['balance']) ? $result['balance'] : null;

        $this->addInfo(sprintf(
            'Kết nối Nhân Hòa thành công.%s',
            $balance !== null ? ' Số dư tài khoản đại lý: ' . number_format((float) $balance) . ' đ.' : ''
        ));

        return true;
    }

    // =================================================================
    // Service lifecycle
    // =================================================================

    /**
     * Provision: register_email, then read the order back to learn its id.
     *
     * register_email answers only {status,msg} — it never returns the order id —
     * so the id, name and end time are fetched with get_infoemail immediately
     * after. That follow-up can be throttled (Nhan Hoa rate-limits lookups to one
     * a minute), so a failure to read it back is NOT a failure to create: the
     * domain and plan are persisted regardless and the id is filled in on the next
     * sync. Blocking Create() on the read-back would leave a created-but-unlinked
     * service that re-runs register_email and double-charges the reseller account.
     */
    public function Create()
    {
        $domain = $this->serviceDomain();

        if ($domain === '') {
            return $this->fail('Create', 'dịch vụ không có tên miền hợp lệ (kiểm tra ô Domain của dịch vụ)');
        }

        $code = $this->serviceCode();

        if ($code === '') {
            return $this->fail('Create', 'chưa chọn mã gói Email Hosting trong cấu hình sản phẩm');
        }

        $months = $this->serviceMonths();

        try {
            $result = $this->api()->registerEmail($code, $domain, $months);
        } catch (Exception $e) {
            return $this->fail('Create', $e->getMessage());
        }

        if (!Nhanhoa_Email_Api::ok($result)) {
            return $this->fail('Create', Nhanhoa_Email_Api::msg($result));
        }

        // Persist what we already know before the read-back, so the service is
        // never left unlinked if get_infoemail is throttled.
        $this->setDetail(self::D_DOMAIN, $domain);
        $this->setDetail(self::D_CODE, $code);

        $info = $this->readOrder($code, $domain);

        $this->recordOrder($domain, $code);
        $this->accountLog(
            'register_email ' . $domain . ' (' . $code . ', ' . $months . ' tháng)',
            true,
            '',
            'nhEmailCreate'
        );

        if ($info === null) {
            $this->addInfo('Nhân Hòa: đã gửi yêu cầu đăng ký. Chưa đọc được mã đơn hàng ngay (có thể do giới hạn tần suất) — bấm "Đồng bộ" sau ít phút để cập nhật mã đơn.');
        }

        return true;
    }

    /**
     * Suspend.
     *
     * The reseller API has NO suspend command: Nhan Hoa does not let a reseller
     * stop mail flow remotely. Returning false would make HostBill retry forever
     * and never mark the service suspended, so this keeps HostBill's state honest,
     * records the intent, and tells staff plainly that the mail platform must be
     * locked from customer.nhanhoa.com. It does not stop mail by itself.
     */
    public function Suspend()
    {
        $this->markOrders('suspended');
        $this->accountLog('suspend (local only — API không hỗ trợ)', true, '', 'nhEmailSuspend');
        $this->addInfo('Nhân Hòa: API đại lý không có lệnh tạm ngưng. Dịch vụ đã đánh dấu tạm ngưng trong HostBill; để thực sự ngừng thư, khoá đơn hàng tại customer.nhanhoa.com.');

        return true;
    }

    /** Unsuspend: mirror of Suspend — local state only, no API command exists. */
    public function Unsuspend()
    {
        $this->markOrders('active');
        $this->accountLog('unsuspend (local only — API không hỗ trợ)', true, '', 'nhEmailUnsuspend');
        $this->addInfo('Nhân Hòa: API đại lý không có lệnh mở lại. Đã đánh dấu hoạt động trong HostBill; nếu trước đó đã khoá tại Nhân Hòa, mở lại tại customer.nhanhoa.com.');

        return true;
    }

    /**
     * Terminate.
     *
     * The reseller API has NO terminate command. Termination is effected by not
     * renewing: the service lapses at ord_end_time. This records that locally and
     * tells staff to confirm non-renewal at Nhan Hoa. Returning true lets HostBill
     * complete its own teardown.
     */
    public function Terminate()
    {
        $this->markOrders('terminated');
        $this->accountLog('terminate (local only — API không hỗ trợ)', true, '', 'nhEmailTerminate');
        $this->addInfo('Nhân Hòa: API đại lý không có lệnh huỷ. Dịch vụ hết hạn khi tới ord_end_time nếu không gia hạn — kiểm tra lại tại customer.nhanhoa.com để chắc chắn không tự gia hạn.');

        return true;
    }

    /**
     * Change package.
     *
     * There is no plan-change command: a different plan means a different
     * serviceCode, i.e. effectively a new registration at Nhan Hoa. This updates
     * the plan code HostBill stores and flags that the actual migration must be
     * arranged with Nhan Hoa; it does not re-register automatically, because that
     * would create a second paid order under the same domain.
     */
    public function ChangePackage()
    {
        $newCode = $this->serviceCode();

        if ($newCode !== '') {
            $this->setDetail(self::D_CODE, $newCode);
            $this->updateOrderCode($newCode);
        }

        $this->accountLog('change package -> ' . $newCode . ' (local only — API không hỗ trợ)', true, '', 'nhEmailChangePackage');
        $this->addInfo('Nhân Hòa: API đại lý không có lệnh đổi gói. Mã gói mới đã lưu trong HostBill; việc chuyển gói thực tế cần liên hệ Nhân Hòa — module không tự đăng ký lại để tránh tạo đơn hàng trùng.');

        return true;
    }

    /**
     * Change password.
     *
     * The reseller API exposes no mailbox or control-panel password. Mailbox
     * passwords are managed by the customer in the Nhan Hoa mail panel. Reported
     * plainly instead of silently succeeding.
     */
    public function ChangePassword()
    {
        $this->accountLog('change password (không hỗ trợ qua API)', true, '', 'nhEmailChangePassword');
        $this->addError('Nhân Hòa: API đại lý không quản lý mật khẩu hộp thư. Đổi mật khẩu hộp thư trong bảng điều khiển email của Nhân Hòa.');

        return true;
    }

    /**
     * Renew: renew_email with the stored order id.
     *
     * Not part of the HostingModule method contract (renewal is billing-side), so
     * it is exposed as an explicit action for the admin controller and cron rather
     * than run implicitly. Needs ord_id, which only exists once get_infoemail has
     * been read at least once.
     *
     * @return true|string  true on success, error text otherwise
     */
    public function renewOrder($months = null)
    {
        $ordId = (int) $this->detail(self::D_ORDID, 0);

        if (!$ordId) {
            // Try once to resolve it from (code, domain) before giving up.
            $this->getSynchInfo();
            $ordId = (int) $this->detail(self::D_ORDID, 0);
        }

        if (!$ordId) {
            return 'chưa có mã đơn hàng (ord_id) — bấm Đồng bộ để lấy mã trước khi gia hạn';
        }

        $months = $months === null ? $this->serviceMonths() : max(6, (int) $months);

        try {
            $result = $this->api()->renewEmail($ordId, $months);
        } catch (Exception $e) {
            return $e->getMessage();
        }

        if (!Nhanhoa_Email_Api::ok($result)) {
            $this->accountLog('renew_email #' . $ordId, false, Nhanhoa_Email_Api::msg($result), 'nhEmailRenew');

            return Nhanhoa_Email_Api::msg($result);
        }

        $this->accountLog('renew_email #' . $ordId . ' (' . $months . ' tháng)', true, '', 'nhEmailRenew');
        $this->getSynchInfo();

        return true;
    }

    /** Read the live order state back from Nhan Hoa and refresh stored details. */
    public function getSynchInfo()
    {
        $domain = $this->serviceDomain();
        $code   = $this->serviceCode(true);

        if ($domain === '' || $code === '') {
            return [];
        }

        if ($this->readOrder($code, $domain) === null) {
            return [];
        }

        return $this->details;
    }

    public function Synchronize($db, $id)
    {
        return $this->getSynchInfo();
    }

    /**
     * get_infoemail -> persist ord_id/name/end_time, map status.
     *
     * @return array|null the raw info array, or null when it could not be read
     */
    protected function readOrder($code, $domain)
    {
        try {
            $info = $this->api()->getInfoEmail($code, $domain);
        } catch (Exception $e) {
            $this->addError('Nhân Hòa: ' . $e->getMessage());

            return null;
        }

        if (!Nhanhoa_Email_Api::ok($info)) {
            // A throttle notice is not an error worth surfacing on Create.
            if (!Nhanhoa_Email_Api::throttled($info)) {
                $this->addError('Nhân Hòa: ' . Nhanhoa_Email_Api::msg($info));
            }

            return null;
        }

        if (!empty($info['ord_id'])) {
            $this->setDetail(self::D_ORDID, (string) $info['ord_id']);
        }

        if (!empty($info['ord_name'])) {
            $this->setDetail(self::D_ORDNAME, (string) $info['ord_name']);
        }

        if (!empty($info['ord_end_time'])) {
            $this->setDetail(self::D_END, (string) $info['ord_end_time']);
        }

        $this->setDetail(self::D_DOMAIN, $domain);
        $this->setDetail(self::D_CODE, $code);

        $this->updateOrderFromInfo($info);

        return $info;
    }

    /**
     * Map a Nhan Hoa order state to a HostBill account status.
     *
     * get_infoemail reports service state in free-text `content` (the documented
     * example is "Đã kích hoạt."); only that one value is documented, so this is a
     * best-effort keyword map and unknown text is returned verbatim for a human to
     * read rather than forced into a wrong bucket. FLAGGED as uncertain.
     */
    public static function mapStatus($content)
    {
        $text = mb_strtolower(trim((string) $content));

        if ($text === '') {
            return 'Unknown';
        }

        if (mb_strpos($text, 'hết hạn') !== false || mb_strpos($text, 'expired') !== false) {
            return 'Expired';
        }

        if (mb_strpos($text, 'khóa') !== false || mb_strpos($text, 'khoá') !== false
            || mb_strpos($text, 'tạm ngưng') !== false || mb_strpos($text, 'suspend') !== false) {
            return 'Suspended';
        }

        if ((mb_strpos($text, 'chưa') !== false && mb_strpos($text, 'kích hoạt') !== false)
            || mb_strpos($text, 'chờ') !== false || mb_strpos($text, 'đang xử lý') !== false
            || mb_strpos($text, 'pending') !== false) {
            return 'Pending';
        }

        if (mb_strpos($text, 'kích hoạt') !== false || mb_strpos($text, 'active') !== false) {
            return 'Active';
        }

        return $content;
    }

    // =================================================================
    // Service identity + local order record
    // =================================================================

    public function serviceDomain()
    {
        foreach ([$this->detail(self::D_DOMAIN, ''), isset($this->account_details['domain']) ? $this->account_details['domain'] : ''] as $candidate) {
            $domain = $this->validDomain($candidate);

            if ($domain !== '') {
                return $domain;
            }
        }

        return '';
    }

    /**
     * Configured plan code.
     *
     * The hand-typed field wins (it is how an admin pins a code when the loadable
     * list is unreachable); otherwise the loadable selection; otherwise, when
     * $allowStored is set, the code persisted on the service.
     */
    public function serviceCode($allowStored = false)
    {
        $text = trim((string) $this->optionValue('serviceCodeText', ''));

        if ($text !== '') {
            return $text;
        }

        $picked = trim((string) $this->optionValue('serviceCode', ''));

        if ($picked !== '') {
            return $picked;
        }

        return $allowStored ? trim((string) $this->detail(self::D_CODE, '')) : '';
    }

    public function serviceMonths()
    {
        return max(6, (int) $this->optionValue('serviceMonth', 12));
    }

    /** Current order state for templates: stored details + mapped status. */
    public function orderSummary()
    {
        return [
            'domain'       => $this->serviceDomain(),
            'service_code' => $this->detail(self::D_CODE, $this->serviceCode()),
            'ord_id'       => $this->detail(self::D_ORDID, ''),
            'ord_name'     => $this->detail(self::D_ORDNAME, ''),
            'end_time'     => $this->detail(self::D_END, ''),
            'end_human'    => $this->endTimeHuman(),
            'months'       => $this->serviceMonths(),
        ];
    }

    public function endTimeHuman()
    {
        $ts = (int) $this->detail(self::D_END, 0);

        return $ts > 0 ? date('Y-m-d', $ts) : '';
    }

    /**
     * Plan description from service_code (contains mailbox count etc.).
     *
     * The reseller API has no per-order mailbox count; the plan's service_content
     * HTML is the closest thing, so it is surfaced as the plan's quota. Returns ''
     * when the list cannot be fetched.
     */
    public function planDescription($code = null)
    {
        $code = $code ?: $this->detail(self::D_CODE, $this->serviceCode());

        if ($code === '') {
            return '';
        }

        try {
            foreach ($this->api()->serviceCodes(2) as $row) {
                if (isset($row['service_code']) && $row['service_code'] === $code) {
                    return isset($row['service_content']) ? (string) $row['service_content'] : '';
                }
            }
        } catch (Exception $e) {
            return '';
        }

        return '';
    }

    /** Nhan Hoa customer control panel — there is no SSO, just the login URL. */
    public function controlPanelUrl()
    {
        return 'https://customer.nhanhoa.com';
    }

    /** Admin notice shown when a loadable lookup can't reach the registrar. */
    protected function apiDownMessage()
    {
        return 'Không kết nối được API Nhân Hòa — kiểm tra thông tin API và IP whitelist tại customer.nhanhoa.com.';
    }

    /**
     * Degraded return for the plan-code <select> when its feed failed: surface
     * the notice and keep the currently-saved code selectable so re-saving the
     * product form during an outage does not wipe it. The admin page renders
     * immediately instead of hanging on the API. Shape matches the loadable
     * contract: a list of [value, label] pairs.
     */
    protected function loadableFallback($detail = '')
    {
        $this->addError('nhanhoa_email: ' . ($detail !== '' ? $detail . ' — ' : '') . $this->apiDownMessage());

        $ret   = [['', '⚠ ' . $this->apiDownMessage()]];
        $saved = trim((string) $this->optionValue('serviceCode', ''));
        if ($saved !== '') {
            $ret[] = [$saved, $saved . ' (giá trị đã lưu)'];
        }

        return $ret;
    }

    /** Loadable option provider: [code, label] pairs from service_code group 2. */
    public function loadServiceCodes()
    {
        try {
            $api = $this->api();
        } catch (Exception $e) {
            return $this->loadableFallback($e->getMessage());
        }

        // Probe once so a transport failure is distinguishable from an empty but
        // successful list; serviceCodes() below is then served from the throttle
        // cache, so this costs no extra round-trip on the happy path.
        $probe = $api->call('service_code', ['group_id' => 2]);
        if (!Nhanhoa_Email_Api::ok($probe)) {
            return $this->loadableFallback(Nhanhoa_Email_Api::msg($probe));
        }

        $ret = [];
        foreach ($api->serviceCodes(2) as $row) {
            if (empty($row['service_code'])) {
                continue;
            }

            $ret[] = [$row['service_code'], $row['service_code']];
        }

        return $ret;
    }

    /**
     * service_used — reseller-wide "services in use" report (docs: service_used.md).
     *
     * Reseller-account-level reporting (service groups + order counts), not a
     * per-service call; surfaced for admin dashboards and the service widget.
     * Returns the normalised rows, or [] when the API is unreachable.
     *
     * @return array<int,array>
     */
    public function serviceUsed()
    {
        try {
            $result = $this->api()->call('service_used');
        } catch (Exception $e) {
            return [];
        }

        if (!Nhanhoa_Email_Api::ok($result)) {
            return [];
        }

        return isset($result['data']) && is_array($result['data'])
            ? array_values($result['data'])
            : [];
    }

    /** get_balance — reseller account balance in đồng, or null when unavailable. */
    public function getBalance()
    {
        try {
            $result = $this->api()->balance();
        } catch (Exception $e) {
            return null;
        }

        if (!Nhanhoa_Email_Api::ok($result)) {
            return null;
        }

        return isset($result['balance']) ? (float) $result['balance'] : 0.0;
    }

    protected function recordOrder($domain, $code)
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO ' . self::ORDERS_TABLE . " (account_id, client_id, server_id, domain, service_code,
                     ord_id, ord_name, end_time, status, created, updated)
                 VALUES (:account, :client, :server, :domain, :code, :ordid, :ordname, :endtime, 'active', NOW(), NOW())
                 ON DUPLICATE KEY UPDATE service_code = :code2, ord_id = :ordid2, ord_name = :ordname2,
                     end_time = :endtime2, status = 'active', updated = NOW()"
            );

            $stmt->execute([
                ':account'   => $this->accountId(),
                ':client'    => $this->clientId(),
                ':server'    => isset($this->account_details['server_id']) ? (int) $this->account_details['server_id'] : 0,
                ':domain'    => $domain,
                ':code'      => $code,
                ':ordid'     => (int) $this->detail(self::D_ORDID, 0),
                ':ordname'   => $this->detail(self::D_ORDNAME, ''),
                ':endtime'   => (int) $this->detail(self::D_END, 0),
                ':code2'     => $code,
                ':ordid2'    => (int) $this->detail(self::D_ORDID, 0),
                ':ordname2'  => $this->detail(self::D_ORDNAME, ''),
                ':endtime2'  => (int) $this->detail(self::D_END, 0),
            ]);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_email', 'không ghi được bảng đơn hàng: ' . $e->getMessage());
        }
    }

    protected function updateOrderFromInfo($info)
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE ' . self::ORDERS_TABLE . ' SET ord_id = :ordid, ord_name = :ordname, end_time = :endtime,
                     remote_status = :rstatus, updated = NOW() WHERE account_id = :id'
            );
            $stmt->execute([
                ':ordid'   => (int) (isset($info['ord_id']) ? $info['ord_id'] : $this->detail(self::D_ORDID, 0)),
                ':ordname' => isset($info['ord_name']) ? (string) $info['ord_name'] : $this->detail(self::D_ORDNAME, ''),
                ':endtime' => (int) (isset($info['ord_end_time']) ? $info['ord_end_time'] : $this->detail(self::D_END, 0)),
                ':rstatus' => isset($info['content']) ? (string) $info['content'] : '',
                ':id'      => $this->accountId(),
            ]);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_email', 'không cập nhật được đơn hàng: ' . $e->getMessage());
        }
    }

    protected function updateOrderCode($code)
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE ' . self::ORDERS_TABLE . ' SET service_code = :code, updated = NOW() WHERE account_id = :id'
            );
            $stmt->execute([':code' => $code, ':id' => $this->accountId()]);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_email', 'không cập nhật được mã gói: ' . $e->getMessage());
        }
    }

    protected function markOrders($status)
    {
        try {
            $stmt = $this->db->prepare(
                'UPDATE ' . self::ORDERS_TABLE . ' SET status = :status, updated = NOW() WHERE account_id = :id'
            );
            $stmt->execute([':status' => $status, ':id' => $this->accountId()]);
        } catch (Exception $e) {
            HBDebug::debug('nhanhoa_email', 'không cập nhật được trạng thái đơn hàng: ' . $e->getMessage());
        }
    }

    // =================================================================
    // Utilities
    // =================================================================

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
                    return isset($config['variable_id']) ? $config['variable_id'] : false;

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

    protected function fail($what, $message)
    {
        $this->addError('Nhân Hòa ' . $what . ': ' . $message);
        $this->accountLog($what, false, $message, 'nhEmail' . $what);

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

    /** Lenient domain validation, no HostBill dependency. */
    public function validDomain($domain)
    {
        $domain = strtolower(trim((string) $domain));
        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#/.*$#', '', $domain);

        if ($domain === '' || strpos($domain, '.') === false) {
            return '';
        }

        return preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,}$/i', $domain)
            ? $domain
            : '';
    }

    /** Values core injects into service emails and templates. */
    public function PrepareSpecific($details)
    {
        $details = is_array($details) ? $details : [];

        $details['domain']       = $this->serviceDomain();
        $details['service_code'] = $this->detail(self::D_CODE, $this->serviceCode());
        $details['ord_id']       = $this->detail(self::D_ORDID, '');
        $details['ord_name']     = $this->detail(self::D_ORDNAME, '');
        $details['end_time']     = $this->endTimeHuman();
        $details['panel_url']    = $this->controlPanelUrl();

        return $details;
    }

    public function getExtraFields()
    {
        return array_filter([
            'Tên miền'       => $this->serviceDomain(),
            'Mã gói'         => $this->detail(self::D_CODE, ''),
            'Mã đơn hàng'    => $this->detail(self::D_ORDID, ''),
            'Tên đơn hàng'   => $this->detail(self::D_ORDNAME, ''),
            'Ngày hết hạn'   => $this->endTimeHuman(),
        ], function ($value) {
            return $value !== '';
        });
    }
}
