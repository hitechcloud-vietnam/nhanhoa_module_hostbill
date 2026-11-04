<?php

/**
 * Nhan Hoa (Nhân Hòa) Cloud VPS provisioning module for HostBill.
 *
 * Built from scratch against the public reseller API documented at
 * https://docs.nhanhoa.com/ (cloud.md, service_code.md, template_code.md,
 * service_used.md, balance.md). The transport in lib/ is adapted verbatim from
 * the Domain/nhanhoa_v3 module so both share one proven HTTP/JSON contract.
 *
 * === IMPORTANT: scope of the Nhan Hoa Cloud API ===
 * The Cloud VPS API is an ORDER / reseller API, not a hypervisor control API.
 * Only these commands exist:
 *     register_cloud   - place a Cloud VPS order            -> Create()
 *     renew_cloud      - renew an existing order            -> renew()
 *     list_cloud       - list orders by registrant email    -> locate cloud_id
 *     get_infocloud    - order status / expiry              -> sync() / status
 *     service_code     - Cloud plan codes (group_id=3)      -> loadable config
 *     template_code    - OS template codes (type 0/1)       -> loadable config
 *     service_used     - services in use (reporting)
 *     get_balance      - reseller balance                   -> testConnection()
 *
 * There is NO documented command for: suspend, unsuspend, terminate, change
 * plan, change password, power on/off/reboot, reinstall/rebuild OS, or
 * console/VNC. Those operations are performed by the end user inside the Nhan
 * Hoa control panel (https://cloud.nhanhoa.com / https://customer.nhanhoa.com).
 *
 * This module therefore implements the FULL HostingModule contract plus the
 * VPS controls, but is honest about the boundary: operations the API cannot
 * perform change only the local HostBill billing state and surface a clear
 * notice plus a deep link to the Nhan Hoa panel, instead of silently claiming
 * success. The control wiring (UI + controller actions + REST routes) is in
 * place so that if Nhan Hoa publishes control commands later, only the matching
 * method body needs filling in.
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.1.1
 */

#[\AllowDynamicProperties]
class nhanhoa_cloud extends HostingModule
{
    protected $_repository = "hosting_nhanhoa_cloud";
    protected $version     = "1.1.1";
    protected $modname     = "Nhan Hoa Cloud VPS";
    protected $description = "Nhân Hòa Cloud VPS provisioning (reseller API)";

    /** Nhan Hoa customer/control panel the end user manages the VPS from. */
    const PANEL_URL = "https://cloud.nhanhoa.com";

    /** @var Nhanhoa_Cloud_Api */
    protected $apiClient;

    /** @var array Server connection details as handed to connect(). */
    protected $connection = [];

    protected $info = [
        "isobserver" => true,
        "havetpl"    => true,
        "haveadmin"  => true,
        "haveuser"   => true,
        "haveapi"    => true,
    ];

    /**
     * Connection (server) fields.
     *   username -> auth-user
     *   password -> auth-pwd
     *   field1   -> auth-id  (reseller account ID)
     *   custom.debug_log -> write request/response to the module log
     */
    protected $serverFields = [
        "ssl"         => false,
        "maxaccounts" => false,
        "status_url"  => false,
        "hostname"    => false,
        "nameservers" => false,
        "ip"          => false,
        "username"    => true,
        "password"    => true,
        "field1"      => true,
        "field2"      => false,
        "custom"      => [
            "debug_log" => [
                "name" => "Debug log",
                "type" => "check",
                "description" => "Write every API request/response to logs/nhanhoa_cloud.log",
            ],
        ],
    ];

    protected $serverFieldsDescription = [
        "username" => "API Username (auth-user)",
        "password" => "API Password (auth-pwd)",
        "field1"   => "Reseller Account ID (auth-id)",
    ];

    /**
     * Product configuration options.
     *   vpscode    - Cloud VPS plan code (loadable from service_code, group_id=3)
     *   ostype     - which OS family's template list to offer / register under
     *   ostemplate - OS template code (loadable from template_code)
     *   vpsmonth   - registration term in months (min 3)
     */
    protected $options = [
        "vpscode" => [
            "name"    => "vps plan code",
            "value"   => "",
            "type"    => "loadable",
            "default" => "getVpsPlans",
        ],
        "ostype" => [
            "name"    => "os family",
            "value"   => "0",
            "type"    => "select",
            "options" => ["0" => "Linux", "1" => "Windows"],
            "default" => "0",
        ],
        "ostemplate" => [
            "name"    => "os template code",
            "value"   => "",
            "type"    => "loadable",
            "default" => "getOsTemplates",
        ],
        "vpsmonth" => [
            "name"    => "registration months",
            "value"   => "12",
            "type"    => "select",
            "options" => ["3" => "3", "6" => "6", "12" => "12", "24" => "24", "36" => "36"],
            "default" => "12",
        ],
    ];

    /**
     * Per-account stored fields (hb_accounts.extra_details).
     *   option4 - hostname / domain (HostBill convention)
     *   cloud_id, ord_id, ord_name, ord_end_time - Nhan Hoa order identifiers
     *   vpscode - plan code the order was placed with (needed by get_infocloud)
     *   cloud_username, cloud_email - registrant identity used at order time
     *   main_ip - primary IP once known
     *   last_status - last textual status content from get_infocloud
     */
    protected $details = [
        "option4"        => ["name" => "domain",        "value" => false, "type" => "input", "default" => false],
        "cloud_id"       => ["name" => "Cloud ID",      "value" => false, "type" => "input", "default" => false],
        "ord_id"         => ["name" => "Order ID",      "value" => false, "type" => "input", "default" => false],
        "ord_name"       => ["name" => "Order Name",    "value" => false, "type" => "input", "default" => false],
        "ord_end_time"   => ["name" => "Order Expiry",  "value" => false, "type" => "input", "default" => false],
        "vpscode"        => ["name" => "Plan Code",     "value" => false, "type" => "input", "default" => false],
        "cloud_username" => ["name" => "Cloud Username","value" => false, "type" => "input", "default" => false],
        "cloud_email"    => ["name" => "Cloud Email",   "value" => false, "type" => "input", "default" => false],
        "main_ip"        => ["name" => "IP Address",    "value" => false, "type" => "input", "default" => false],
        "last_status"    => ["name" => "Last Status",   "value" => false, "type" => "input", "default" => false],
    ];

    /** Admin "run module command" actions. */
    protected $commands = [
        "Create"    => "Create / Register Cloud VPS",
        "Suspend"   => "Suspend (billing only)",
        "Unsuspend" => "Unsuspend (billing only)",
        "Terminate" => "Terminate (billing only)",
        "renew"     => "Renew at Nhan Hoa",
        "sync"      => "Sync status from Nhan Hoa",
    ];

    public function __construct()
    {
        parent::__construct();
    }

    public function install()
    {
        return true;
    }

    public function upgrade($previous)
    {
        return true;
    }

    // ---------------------------------------------------------------------
    // Connection / API
    // ---------------------------------------------------------------------

    public function connect($connect)
    {
        $this->connection = is_array($connect) ? $connect : [];
    }

    public function getConnection()
    {
        return $this->connection;
    }

    /** Lazily build the transport from the connected server credentials. */
    public function api($new = false)
    {
        if (!$this->apiClient || $new) {
            require_once __DIR__ . DS . "lib" . DS . "class.nhanhoa_cloud_api.php";
            $debug   = !empty($this->connection["custom"]["debug_log"]);
            $logFile = defined("APPDIR") ? APPDIR . "logs" . DS . "nhanhoa_cloud.log" : null;
            $this->apiClient = new Nhanhoa_Cloud_Api(
                isset($this->connection["username"]) ? $this->connection["username"] : "",
                isset($this->connection["password"]) ? $this->connection["password"] : "",
                isset($this->connection["field1"]) ? $this->connection["field1"] : "",
                $debug,
                $logFile
            );
        }

        return $this->apiClient;
    }

    public function testConnection()
    {
        $res = $this->api()->call("get_balance");
        if (Nhanhoa_Cloud_Api::ok($res)) {
            $bal = isset($res["balance"]) ? $res["balance"] : "?";
            $this->addInfo("Kết nối thành công. Số dư tài khoản đại lý: " . $bal);
            return true;
        }
        $this->addError("Không kết nối được tới API Nhân Hòa: " . Nhanhoa_Cloud_Api::msg($res));
        return false;
    }

    // ---------------------------------------------------------------------
    // Loadable product-config option sources
    // ---------------------------------------------------------------------

    /**
     * Cloud VPS plan codes (service_code, group_id = 3). Returns a map of
     * code => label for a <select>. On failure returns [] so the config form
     * falls back to a free-text field, per HostBill's loadable convention.
     */
    /** Admin notice shown when a loadable lookup can't reach the registrar. */
    protected function apiDownMessage()
    {
        return "Không kết nối được API Nhân Hòa — kiểm tra thông tin API và IP whitelist tại customer.nhanhoa.com.";
    }

    /**
     * Degraded return for a loadable <select> whose feed failed: surface the
     * notice and keep the currently-saved value selectable, so re-saving the
     * product form during an outage does not wipe it. The admin page renders
     * immediately instead of hanging on the API. Returns a code => label map.
     */
    protected function loadableFallback($savedValue, $detail = "")
    {
        $this->addError("nhanhoa_cloud: " . ($detail !== "" ? $detail . " — " : "") . $this->apiDownMessage());

        $out   = ["" => "⚠ " . $this->apiDownMessage()];
        $saved = trim((string) $savedValue);
        if ($saved !== "") {
            $out[$saved] = $saved . " (giá trị đã lưu)";
        }

        return $out;
    }

    public function getVpsPlans($product_id = false, $clientarea = false)
    {
        $res = $this->api()->call("service_code", ["group_id" => "3"]);
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            return $this->loadableFallback($this->resource("vpscode"), Nhanhoa_Cloud_Api::msg($res));
        }

        $rows = $this->rowsFrom($res);
        $out  = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row["service_code"])) {
                continue;
            }
            $label = strip_tags((string) (isset($row["service_content"]) ? $row["service_content"] : ""));
            $label = trim(preg_replace('/\s+/', " ", $label));
            if ($label === "") {
                $label = $row["service_code"];
            } elseif (mb_strlen($label) > 90) {
                $label = mb_substr($label, 0, 90) . "…";
            }
            $out[$row["service_code"]] = $row["service_code"] . " — " . $label;
        }

        return $out;
    }

    /**
     * OS template codes (template_code). `type` 0 = Linux, 1 = Windows; driven
     * by the product's `ostype` option, defaulting to Linux. Returns a map of
     * templateCode => templateCode for a <select>.
     */
    public function getOsTemplates($product_id = false, $clientarea = false)
    {
        $type = (string) $this->resource("ostype");
        if ($type !== "1") {
            $type = "0";
        }

        $res = $this->api()->call("template_code", ["type" => $type]);
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            return $this->loadableFallback($this->resource("ostemplate"), Nhanhoa_Cloud_Api::msg($res));
        }

        $rows = $this->rowsFrom($res);
        $out  = [];
        foreach ($rows as $row) {
            if (!is_array($row) || empty($row["template_code"])) {
                continue;
            }
            $out[$row["template_code"]] = $row["template_code"];
        }

        return $out;
    }

    /**
     * Normalise the many shapes Nhan Hoa list endpoints use into a flat list of
     * associative rows. Some commands answer {status,data:[...]}, some a bare
     * list, some a single object.
     */
    protected function rowsFrom($res)
    {
        if (!is_array($res)) {
            return [];
        }
        $data = isset($res["data"]) ? $res["data"] : $res;
        if (!is_array($data)) {
            return [];
        }
        // Single associative row -> wrap it.
        if (array_keys($data) !== range(0, count($data) - 1)) {
            // It's associative; could be one row or the raw response envelope.
            if (isset($data["service_code"]) || isset($data["template_code"])) {
                return [$data];
            }
            // Strip status/msg envelope keys, keep numeric-indexed children.
            $rows = [];
            foreach ($data as $k => $v) {
                if (in_array($k, ["status", "msg", "content"], true)) {
                    continue;
                }
                if (is_array($v)) {
                    $rows[] = $v;
                }
            }
            return $rows;
        }

        return $data;
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
        $res = $this->api()->call("service_used");
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            return [];
        }

        return $this->rowsFrom($res);
    }

    /** get_balance — reseller account balance, or null when unavailable. */
    public function getBalance()
    {
        $res = $this->api()->call("get_balance");
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            return null;
        }

        return isset($res["balance"]) ? (float) $res["balance"] : 0.0;
    }

    // ---------------------------------------------------------------------
    // Lifecycle
    // ---------------------------------------------------------------------

    /**
     * Provision a Cloud VPS via register_cloud, then best-effort resolve and
     * persist the Nhan Hoa cloud_id / order identifiers.
     *
     * register_cloud returns only {status,msg} — it does NOT return the new
     * cloud_id. We therefore look the order up with list_cloud by the
     * registrant email and store whatever identifiers we can resolve. The exact
     * list_cloud row shape is undocumented, so the resolution is defensive and
     * never fails the order on its own.
     */
    public function Create()
    {
        $vpscode = trim((string) $this->resource("vpscode"));
        $ostpl   = trim((string) $this->resource("ostemplate"));
        $months  = (int) $this->resource("vpsmonth");
        if ($months < 3) {
            $months = 3;
        }

        if ($vpscode === "") {
            $this->addError("Thiếu mã gói Cloud VPS (vps plan code) trong cấu hình sản phẩm.");
            return false;
        }
        if ($ostpl === "") {
            $this->addError("Thiếu mã template hệ điều hành (os template code) trong cấu hình sản phẩm.");
            return false;
        }

        $client = $this->clientData();
        $email  = isset($client["email"]) ? $client["email"] : "";
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addError("Email khách hàng không hợp lệ, không thể đăng ký Cloud VPS.");
            return false;
        }

        $username = $this->details["option4"]["value"]
            ?: ($this->account_details["username"] ?? ("vps" . ($this->account_details["id"] ?? "")));
        $realname = trim((($client["firstname"] ?? "") . " " . ($client["lastname"] ?? "")));
        if ($realname === "") {
            $realname = $username;
        }

        $formdata = [
            "vpsCode"        => $vpscode,
            "vpsMonth"       => (string) $months,
            "osTemplateCode" => $ostpl,
            "cloud_username" => $username,
            "cloud_email"    => $email,
            "cloud_realname" => $realname,
            "cloud_company"  => isset($client["companyname"]) ? $client["companyname"] : "",
            "cloud_address"  => isset($client["address1"]) ? $client["address1"] : "",
            "cloud_phone"    => isset($client["phonenumber"]) ? $client["phonenumber"] : (isset($client["phone"]) ? $client["phone"] : ""),
            "cloud_note"     => "HostBill service #" . ($this->account_details["id"] ?? ""),
        ];

        $res = $this->api()->call("register_cloud", $formdata);
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            $this->addError("Đăng ký Cloud VPS thất bại: " . Nhanhoa_Cloud_Api::msg($res));
            return false;
        }

        // Persist what we know immediately.
        $this->details["vpscode"]["value"]        = $vpscode;
        $this->details["cloud_username"]["value"]  = $username;
        $this->details["cloud_email"]["value"]     = $email;
        $this->details["option4"]["value"]         = $username;

        // Best-effort: resolve the new order from list_cloud by email.
        $this->resolveOrder($email, $vpscode);

        $this->persistDetails("Create", "Registered Cloud VPS plan " . $vpscode);
        $this->addInfo("Đã gửi yêu cầu đăng ký Cloud VPS: " . Nhanhoa_Cloud_Api::msg($res));
        return true;
    }

    /**
     * Look the just-ordered VPS up via list_cloud (keyed by registrant email)
     * and store cloud_id / ord_* / IP. Silent on failure: the order is already
     * placed and status can be reconciled later via sync().
     */
    protected function resolveOrder($email, $vpscode)
    {
        $res = $this->api()->call("list_cloud", [], ["email" => $email]);
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            return;
        }

        $rows  = $this->rowsFrom($res);
        $match = null;
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Prefer a row whose plan code matches; otherwise take the last.
            $rowCode = $row["vpsCode"] ?? ($row["service_code"] ?? ($row["vpscode"] ?? ""));
            if ($vpscode !== "" && $rowCode === $vpscode) {
                $match = $row;
            }
            if ($match === null) {
                $match = $row;
            }
        }
        if (!is_array($match)) {
            return;
        }

        $cloudId = $match["cloud_id"] ?? ($match["id"] ?? ($match["cloudId"] ?? ""));
        $ip      = $match["ip"] ?? ($match["main_ip"] ?? ($match["ipaddress"] ?? ""));
        if ($cloudId !== "") {
            $this->details["cloud_id"]["value"] = (string) $cloudId;
        }
        if ($ip !== "") {
            $this->details["main_ip"]["value"] = (string) $ip;
        }

        // With a cloud_id + plan code we can pull the order record.
        if ($this->details["cloud_id"]["value"]) {
            $info = $this->api()->call("get_infocloud", [
                "vpsCode"  => $vpscode,
                "cloud_id" => $this->details["cloud_id"]["value"],
            ]);
            $this->applyInfo($info);
        }
    }

    /** Copy fields from a get_infocloud response into local details. */
    protected function applyInfo($info)
    {
        if (!Nhanhoa_Cloud_Api::ok($info)) {
            return false;
        }
        foreach (["ord_id", "ord_name", "ord_end_time"] as $k) {
            if (!empty($info[$k])) {
                $this->details[$k]["value"] = (string) $info[$k];
            }
        }
        if (!empty($info["content"])) {
            $this->details["last_status"]["value"] = (string) $info["content"];
        }
        return true;
    }

    /**
     * Suspend. The Nhan Hoa Cloud API exposes no suspend command, so this
     * changes only the local HostBill billing state. The VPS keeps running at
     * Nhan Hoa until the order is suspended in their panel.
     */
    public function Suspend()
    {
        $this->addInfo("API Nhân Hòa không có lệnh tạm ngưng Cloud VPS. Trạng thái chỉ thay đổi trong HostBill; "
            . "vui lòng tạm ngưng dịch vụ trong trang quản trị Nhân Hòa nếu cần: " . self::PANEL_URL);
        return true;
    }

    /** Unsuspend. Billing-state only (see Suspend()). */
    public function Unsuspend()
    {
        $this->addInfo("API Nhân Hòa không có lệnh mở lại Cloud VPS. Trạng thái chỉ thay đổi trong HostBill; "
            . "quản lý tại: " . self::PANEL_URL);
        return true;
    }

    /**
     * Terminate. The API exposes no delete/terminate command, so this changes
     * only the local billing state and clears the stored order link. The order
     * must be cancelled in the Nhan Hoa panel to stop renewal/charges.
     */
    public function Terminate()
    {
        $this->addInfo("API Nhân Hòa không có lệnh hủy Cloud VPS. Dịch vụ được đánh dấu hủy trong HostBill; "
            . "vui lòng hủy đơn hàng trong trang quản trị Nhân Hòa để dừng gia hạn: " . self::PANEL_URL);
        $this->details["cloud_id"]["value"] = "";
        $this->persistDetails("Terminate", "Cleared Nhan Hoa order link (billing-only termination)");
        return true;
    }

    /**
     * Change plan. The API has no resize/change-plan command. Flagged; the plan
     * must be changed by placing a new order or via the Nhan Hoa panel.
     */
    public function ChangePackage()
    {
        $this->addError("API Nhân Hòa không hỗ trợ đổi gói (resize) Cloud VPS. "
            . "Vui lòng thực hiện trong trang quản trị Nhân Hòa: " . self::PANEL_URL);
        return false;
    }

    /**
     * Change password. The API has no password-reset command for Cloud VPS.
     * Flagged; the password is reset from inside the VPS or the Nhan Hoa panel.
     */
    public function ChangePassword(&$newpassword)
    {
        $this->addError("API Nhân Hòa không hỗ trợ đổi mật khẩu Cloud VPS qua API. "
            . "Vui lòng đổi trong hệ điều hành của VPS hoặc trang quản trị Nhân Hòa: " . self::PANEL_URL);
        return false;
    }

    // ---------------------------------------------------------------------
    // Admin commands
    // ---------------------------------------------------------------------

    /** Renew the Cloud VPS order (renew_cloud). Uses the product's term. */
    public function renew($months = null)
    {
        $ordId = (string) $this->details["ord_id"]["value"];
        if ($ordId === "") {
            $this->addError("Chưa có mã đơn hàng (ord_id). Hãy chạy 'Sync status' trước khi gia hạn.");
            return false;
        }
        $months = (int) ($months ?: $this->resource("vpsmonth"));
        if ($months < 3) {
            $months = 3;
        }

        $res = $this->api()->call("renew_cloud", [
            "ord_id"       => $ordId,
            "serviceMonth" => (string) $months,
        ]);
        if (!Nhanhoa_Cloud_Api::ok($res)) {
            $this->addError("Gia hạn thất bại: " . Nhanhoa_Cloud_Api::msg($res));
            return false;
        }

        $this->sync();
        $this->addInfo("Đã gửi yêu cầu gia hạn " . $months . " tháng: " . Nhanhoa_Cloud_Api::msg($res));
        return true;
    }

    /**
     * Refresh order status/expiry from get_infocloud and map it onto the
     * HostBill account status. Returns the normalised info array.
     */
    public function sync()
    {
        $vpscode = (string) $this->details["vpscode"]["value"];
        $cloudId = (string) $this->details["cloud_id"]["value"];

        // If we never captured cloud_id, try once more from list_cloud.
        if ($cloudId === "" && $this->details["cloud_email"]["value"]) {
            $this->resolveOrder($this->details["cloud_email"]["value"], $vpscode);
            $cloudId = (string) $this->details["cloud_id"]["value"];
        }

        if ($vpscode === "" || $cloudId === "") {
            $this->addError("Thiếu vpsCode hoặc cloud_id, không thể đồng bộ trạng thái.");
            return false;
        }

        $info = $this->api()->call("get_infocloud", ["vpsCode" => $vpscode, "cloud_id" => $cloudId]);
        if (!$this->applyInfo($info)) {
            $this->addError("Đồng bộ thất bại: " . Nhanhoa_Cloud_Api::msg($info));
            return false;
        }

        $this->persistDetails("Sync", "Synced status: " . $this->details["last_status"]["value"]);
        $this->addInfo("Trạng thái: " . $this->details["last_status"]["value"]);

        $hbStatus = $this->mapStatus($this->details["last_status"]["value"]);
        if ($hbStatus) {
            $this->changeAccountStatus($hbStatus);
        }
        return $info;
    }

    /** Return a status + expiry summary for client/admin display. */
    public function statusSummary()
    {
        return [
            "cloud_id"     => (string) $this->details["cloud_id"]["value"],
            "ord_id"       => (string) $this->details["ord_id"]["value"],
            "ord_name"     => (string) $this->details["ord_name"]["value"],
            "ip"           => (string) $this->details["main_ip"]["value"],
            "plan"         => (string) $this->details["vpscode"]["value"],
            "status_text"  => (string) $this->details["last_status"]["value"],
            "hb_status"    => $this->mapStatus($this->details["last_status"]["value"]),
            "end_time"     => (string) $this->details["ord_end_time"]["value"],
            "end_time_fmt" => $this->details["ord_end_time"]["value"]
                ? date("Y-m-d", (int) $this->details["ord_end_time"]["value"]) : "",
            "panel_url"    => self::PANEL_URL,
        ];
    }

    /**
     * Map a Nhan Hoa textual order status (get_infocloud `content`, Vietnamese)
     * onto a HostBill account status.
     */
    public function mapStatus($text)
    {
        $t = mb_strtolower((string) $text);
        if ($t === "") {
            return "";
        }
        if (mb_strpos($t, "kích hoạt") !== false || mb_strpos($t, "hoạt động") !== false || mb_strpos($t, "active") !== false) {
            return "Active";
        }
        if (mb_strpos($t, "tạm ngưng") !== false || mb_strpos($t, "khóa") !== false || mb_strpos($t, "suspend") !== false) {
            return "Suspended";
        }
        if (mb_strpos($t, "hủy") !== false || mb_strpos($t, "chấm dứt") !== false || mb_strpos($t, "terminate") !== false) {
            return "Terminated";
        }
        if (mb_strpos($t, "chờ") !== false || mb_strpos($t, "đang xử lý") !== false || mb_strpos($t, "pending") !== false) {
            return "Pending";
        }
        return "";
    }

    // ---------------------------------------------------------------------
    // Client-area VPS controls
    //
    // The Nhan Hoa Cloud API has no power/reinstall/console commands, so these
    // return a structured notice rather than silently failing. The console
    // action returns the panel deep link, which IS actionable today.
    // ---------------------------------------------------------------------

    public function client_power($action)
    {
        $action = (string) $action;
        return $this->unsupportedControl("power/" . $action,
            "Bật/tắt/khởi động lại VPS được thực hiện trong trang quản trị Nhân Hòa.");
    }

    public function client_reinstall($os = "")
    {
        return $this->unsupportedControl("reinstall",
            "Cài lại hệ điều hành được thực hiện trong trang quản trị Nhân Hòa.");
    }

    /** Console/VNC: the panel is the actionable entry point. */
    public function client_console()
    {
        return [
            "success"   => true,
            "available" => true,
            "type"      => "panel_link",
            "url"       => self::PANEL_URL,
            "message"   => "Mở console/VNC trong trang quản trị Nhân Hòa.",
        ];
    }

    public function client_status()
    {
        $info = $this->sync();
        $summary = $this->statusSummary();
        $summary["success"] = (bool) $info;
        return $summary;
    }

    protected function unsupportedControl($op, $message)
    {
        return [
            "success"   => false,
            "available" => false,
            "op"        => $op,
            "message"   => $message,
            "panel_url" => self::PANEL_URL,
        ];
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    /** Full client record for the account under management. */
    protected function clientData()
    {
        if (!empty($this->client_data) && is_array($this->client_data)) {
            return $this->client_data;
        }
        $cid = $this->account_details["client_id"] ?? 0;
        if ($cid) {
            try {
                $this->client_data = HBLoader::LoadModel("Clients")->getClient($cid);
            } catch (Exception $e) {
                $this->client_data = [];
            }
        }
        return is_array($this->client_data) ? $this->client_data : [];
    }

    /** Persist $this->details back to the account and write an action log. */
    protected function persistDetails($action, $changes = "")
    {
        if (empty($this->account_details["id"])) {
            return;
        }
        try {
            $a = HBLoader::LoadModel("Accounts");
            $a->updateExtraDetails($this->account_details["id"], $this->getDetails());
            $a->logAction($this->account_details["id"], $this->getModuleId(), false, true, $action, $changes, false);
        } catch (Exception $e) {
            // Non-fatal: provisioning already happened at Nhan Hoa.
        }
    }

    protected function changeAccountStatus($status)
    {
        if (empty($this->account_details["id"])) {
            return;
        }
        try {
            HBLoader::LoadModel("Accounts")->markAsStatus($this->account_details["id"], $status);
        } catch (Exception $e) {
        }
    }

    /** Read a stored per-account detail value. */
    public function getDetail($name)
    {
        return isset($this->details[$name]) ? $this->details[$name]["value"] : null;
    }

    public function getAccountDetails()
    {
        return $this->details;
    }

    /** Ownership guard for the REST routes (mirrors easydcim). */
    public function serviceBelongsToClient($service_id, $client_id)
    {
        $service_id = (int) $service_id;
        $client_id  = (int) $client_id;
        if ($service_id <= 0 || $client_id <= 0) {
            return false;
        }
        try {
            $q = $this->db->prepare("SELECT 1 FROM hb_accounts WHERE id = ? AND client_id = ? LIMIT 1");
            $q->execute([$service_id, $client_id]);
            $ok = (bool) $q->fetchColumn();
            $q->closeCursor();
            return $ok;
        } catch (Exception $e) {
            return false;
        }
    }
}
