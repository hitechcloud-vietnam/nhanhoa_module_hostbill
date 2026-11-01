<?php

/**
 * Nhan Hoa Domain — reseller API transport (HTTP/cURL).
 *
 * @package   HiTechCloud Nhan Hoa Suite
 * @author    HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @copyright 2026 HiTechCloud by Pho Tue SoftWare Solutions JSC
 * @license   MIT
 * @version   1.0.0
 */

/**
 * Transport for the Nhan Hoa reseller API (https://api.nhanhoa.com/).
 *
 * Documented at https://docs.nhanhoa.com/. Every call is an HTTP POST to
 * `?act=<cmd>` carrying the reseller credentials plus a `formdata` field that
 * is itself a urlencoded query string, not a nested array.
 *
 * The class is deliberately free of HostBill dependencies so it can be
 * exercised from a CLI probe without bootstrapping the application.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

class Nhanhoa_Domain_Api
{
    const BASE_URL = 'https://api.nhanhoa.com/';

    /** Nhan Hoa answers well inside a minute; v2's 1000s timeout hung workers. */
    const TIMEOUT = 60;

    /**
     * Nhan Hoa throttles: repeat a lookup too quickly and every command comes
     * back "Thao tác quá nhanh vui lòng thử lại sau 1 phút!". Read-only lookups
     * are therefore cached for this long, which is what keeps a client
     * refreshing a widget — or an admin panel that fires three lookups in a row
     * — from locking the reseller account out for a minute.
     */
    const CACHE_TTL = 60;

    /** Marker the registrar uses when the account is being throttled. */
    const THROTTLE_MARKER = 'quá nhanh';

    /** Commands that only read, and so are safe to serve from cache. */
    private static $cacheable = [
        'get_balance', 'whois', 'whois_multi', 'get_infodomain', 'get_infodomainvn',
        'get_statusdomain', 'get_statusdomainvn', 'check_declaration', 'check_backorder_domainvn',
        'domain_tf_status', 'domain_pricing', 'list_domain', 'list_domain_unsign',
        'list_record', 'info_control', 'list_province', 'list_district', 'list_country',
        'service_code', 'template_code', 'service_used',
    ];

    /** @var string */
    protected $user;

    /** @var string */
    protected $password;

    /** @var string */
    protected $id;

    /** @var bool Write every request/response to the module log file. */
    protected $debug = false;

    /** @var string|null */
    protected $logFile;

    /** @var string Last raw response body, for diagnostics. */
    protected $lastRaw = '';

    public function __construct($user, $password, $id, $debug = false, $logFile = null)
    {
        $this->user     = (string) $user;
        $this->password = (string) $password;
        $this->id       = (string) $id;
        $this->debug    = (bool) $debug;
        $this->logFile  = $logFile;
    }

    /**
     * Run one API command.
     *
     * Always returns an array. Transport failures are turned into the same
     * `['status' => 'error', 'msg' => ...]` shape the API itself uses, so no
     * caller has to guard against null before reading `$result['status']` —
     * that null check is the single most common crash in the v1/v2 modules.
     *
     * @param string $cmd        e.g. `register_domainvn`
     * @param array  $formdata   the `formdata` payload
     * @param array  $postfields extra top-level POST fields (rarely needed)
     */
    public function call($cmd, array $formdata = [], array $postfields = [])
    {
        if ($cmd === '') {
            return $this->fail('Không có lệnh để thực thi API.');
        }

        $cacheKey = $this->cacheKey($cmd, $formdata);
        $cached   = $this->cacheGet($cacheKey);
        if ($cached !== null) {
            return $cached;
        }

        $postfields['auth-user'] = $this->user;
        $postfields['auth-pwd']  = $this->password;
        $postfields['auth-id']   = $this->id;
        $postfields['cmd']       = $cmd;
        $postfields['formdata']  = $this->encodeFormdata($formdata);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::BASE_URL . '?act=' . rawurlencode($cmd),
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postfields,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => false,
            CURLOPT_TIMEOUT        => self::TIMEOUT,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_USERAGENT      => 'HostBill nhanhoa_domain',
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastRaw = is_string($body) ? $body : '';
        $this->log($cmd, $formdata, $this->lastRaw, $errno ? $error : null);

        if ($errno) {
            return $this->fail('Lỗi kết nối tới API Nhân Hòa: ' . $error);
        }

        if ($code >= 400) {
            return $this->fail('API Nhân Hòa trả về HTTP ' . $code . '.');
        }

        $decoded = $this->decode($body);
        if ($decoded === null) {
            return $this->fail('Phản hồi từ API không phải JSON hợp lệ.');
        }

        $result = $this->normalize($decoded);
        $this->cachePut($cacheKey, $result);

        return $result;
    }

    /** True when this response is the registrar's throttle notice. */
    public static function throttled($result)
    {
        return is_array($result)
            && !self::ok($result)
            && mb_stripos(self::msg($result, ''), self::THROTTLE_MARKER) !== false;
    }

    /** Cache path for a read-only command, or null when it must not be cached. */
    protected function cacheKey($cmd, array $formdata)
    {
        if (!in_array($cmd, self::$cacheable, true)) {
            return null;
        }

        ksort($formdata);

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nhanhoa_domain_'
            . sha1($this->id . '|' . $cmd . '|' . json_encode($formdata)) . '.json';
    }

    protected function cacheGet($key)
    {
        if ($key === null || !is_file($key) || (time() - filemtime($key)) > self::CACHE_TTL) {
            return null;
        }

        $raw = @file_get_contents($key);
        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    protected function cachePut($key, array $result)
    {
        // Never cache a throttle notice: it would outlive the throttle itself.
        if ($key === null || !self::ok($result)) {
            return;
        }

        @file_put_contents($key, json_encode($result, JSON_UNESCAPED_UNICODE), LOCK_EX);
    }

    /** Raw body of the most recent call. */
    public function getLastRaw()
    {
        return $this->lastRaw;
    }

    /** True when the API reported success, whichever flag it used. */
    public static function ok($result)
    {
        return is_array($result) && isset($result['status'])
            && strtolower((string) $result['status']) === 'ok';
    }

    /** Error text of a failed call, never empty. */
    public static function msg($result, $fallback = 'Lỗi không xác định từ API Nhân Hòa.')
    {
        if (is_array($result) && !empty($result['msg'])) {
            return is_scalar($result['msg'])
                ? (string) $result['msg']
                : json_encode($result['msg'], JSON_UNESCAPED_UNICODE);
        }

        return $fallback;
    }

    protected function encodeFormdata(array $formdata)
    {
        $pairs = [];
        foreach ($formdata as $name => $value) {
            if (is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_UNICODE);
            }
            // v2 unescaped &amp; before encoding; contact addresses copied out
            // of the client area still arrive HTML-escaped.
            $pairs[] = $name . '=' . urlencode(str_replace('&amp;', '&', (string) $value));
        }

        return implode('&', $pairs);
    }

    /**
     * Decode a response body.
     *
     * Some commands (`list_record`) answer with a JSON *string* containing
     * JSON, so one decode leaves a string behind. Decode again when that
     * happens instead of handing callers something they cannot index.
     */
    protected function decode($body)
    {
        if (!is_string($body) || $body === '') {
            return null;
        }

        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }

        if (is_string($decoded)) {
            $inner = json_decode($decoded, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($inner)) {
                return $inner;
            }
        }

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Give every response the same `status` / `msg` keys.
     *
     * ZoneDNS answers `{"success":true,"data":{...}}` while everything else
     * answers `{"status":"ok",...}`.
     */
    protected function normalize(array $result)
    {
        if (!isset($result['status']) && isset($result['success'])) {
            $result['status'] = $result['success'] ? 'ok' : 'error';
        }

        if (!isset($result['status'])) {
            $result['status'] = 'error';
            $result['msg']    = isset($result['msg']) ? $result['msg'] : 'Phản hồi API thiếu trường status.';
        }

        return $result;
    }

    protected function fail($msg)
    {
        return ['status' => 'error', 'msg' => $msg];
    }

    protected function log($cmd, array $formdata, $body, $curlError = null)
    {
        if (class_exists('HBDebug')) {
            HBDebug::debug('nhanhoa_domain ' . $cmd, ['formdata' => $this->mask($formdata), 'response' => $body]);
        }

        if (!$this->debug || !$this->logFile) {
            return;
        }

        $line = sprintf(
            "[%s] cmd=%s formdata=%s%s response=%s\n",
            date('Y-m-d H:i:s'),
            $cmd,
            json_encode($this->mask($formdata), JSON_UNESCAPED_UNICODE),
            $curlError ? ' curl_error=' . $curlError : '',
            $body
        );

        @file_put_contents($this->logFile, $line, FILE_APPEND | LOCK_EX);
    }

    protected function mask(array $formdata)
    {
        foreach (['auth-pwd', 'password', 'auth-code', 'authcode'] as $key) {
            if (isset($formdata[$key]) && $formdata[$key] !== '') {
                $formdata[$key] = '******';
            }
        }

        return $formdata;
    }
}
