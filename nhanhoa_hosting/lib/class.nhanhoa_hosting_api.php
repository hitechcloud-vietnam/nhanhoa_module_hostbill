<?php

/**
 * Transport for the Nhan Hoa reseller API (https://api.nhanhoa.com/).
 *
 * Documented at https://docs.nhanhoa.com/. Every call is an HTTP POST to
 * `?act=<cmd>` carrying the reseller credentials plus a `formdata` field that
 * is itself a urlencoded query string, not a nested array.
 *
 * Adapted verbatim (renamed) from the Domain module's nhanhoa_v3 transport so
 * the hosting module speaks to the registrar exactly the way the domain module
 * does — same throttle cache, same normalisation, same `ok()`/`msg()` helpers.
 *
 * The class is deliberately free of HostBill dependencies so it can be
 * exercised from a CLI probe without bootstrapping the application.
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

class Nhanhoa_hosting_Api
{
    const BASE_URL = 'https://api.nhanhoa.com/';

    /**
     * Interactive overall timeout. Lowered from 60s: a loadable admin select that
     * blocks must not hold the product/admin page open for a whole minute.
     */
    const TIMEOUT = 30;

    /** Connect timeout, kept short so an un-whitelisted/firewalled IP fails fast. */
    const CONNECT_TIMEOUT = 10;

    /**
     * After a transport-level failure (connect timeout, HTTP >= 400, or an
     * unparseable body) skip the network for this long on read-only lookups, so a
     * string of admin renders doesn't each wait out another full connect-timeout.
     */
    const NEG_CACHE_TTL = 45;

    /** Shown when a read-only lookup is short-circuited by the negative cache. */
    const DOWN_MSG = 'Không kết nối được API Nhân Hòa — kiểm tra thông tin API và IP whitelist tại customer.nhanhoa.com.';

    /**
     * Nhan Hoa throttles: repeat a lookup too quickly and every command comes
     * back "Thao tác quá nhanh vui lòng thử lại sau 1 phút!". Read-only lookups
     * are therefore cached for this long, which keeps an admin opening a product
     * form (three list calls in a row) from locking the reseller account out.
     */
    const CACHE_TTL = 60;

    /** Marker the registrar uses when the account is being throttled. */
    const THROTTLE_MARKER = 'quá nhanh';

    /** Commands that only read, and so are safe to serve from cache. */
    private static $cacheable = [
        'get_balance', 'service_code', 'template_code', 'service_used',
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

    /** True once credentials are present enough to attempt a call. */
    public function hasCredentials()
    {
        return $this->user !== '' && $this->password !== '' && $this->id !== '';
    }

    /**
     * Run one API command.
     *
     * Always returns an array. Transport failures are turned into the same
     * `['status' => 'error', 'msg' => ...]` shape the API itself uses, so no
     * caller has to guard against null before reading `$result['status']`.
     *
     * @param string $cmd        e.g. `register_hosting`
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

        // A recent transport failure: on a read-only lookup (the ones the admin
        // page fires on render) fail fast instead of waiting out the connect
        // timeout again. Write/provisioning commands still attempt the network.
        if ($cacheKey !== null && $this->negCacheActive()) {
            return $this->fail(self::DOWN_MSG);
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
            CURLOPT_CONNECTTIMEOUT => self::CONNECT_TIMEOUT,
            CURLOPT_NOSIGNAL       => 1,
            CURLOPT_USERAGENT      => 'HostBill nhanhoa_hosting',
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $this->lastRaw = is_string($body) ? $body : '';
        $this->log($cmd, $formdata, $this->lastRaw, $errno ? $error : null);

        if ($errno) {
            $this->negCacheMark();
            return $this->fail('Lỗi kết nối tới API Nhân Hòa: ' . $error);
        }

        if ($code >= 400) {
            $this->negCacheMark();
            return $this->fail('API Nhân Hòa trả về HTTP ' . $code . '.');
        }

        $decoded = $this->decode($body);
        if ($decoded === null) {
            $this->negCacheMark();
            return $this->fail('Phản hồi từ API không phải JSON hợp lệ.');
        }

        // The endpoint answered and parsed: it is reachable, so lift any
        // negative-cache marker even when the app-level status is an error.
        $this->negCacheClear();

        $result = $this->normalize($decoded);
        $this->cachePut($cacheKey, $result);

        return $result;
    }

    /**
     * Negative cache ("transport is down") marker, scoped to this reseller id.
     *
     * Uses HostBill's HBCache when present; otherwise a temp-file flag, so the
     * transport stays usable from a standalone CLI probe. Only read-only lookups
     * consult it (see call()), which is exactly where a stalled admin render hurts.
     */
    protected function negCacheId()
    {
        return 'nhanhoa_hosting_down_' . sha1($this->id);
    }

    protected function negCacheFlag()
    {
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . $this->negCacheId() . '.flag';
    }

    protected function negCacheActive()
    {
        if (class_exists('HBCache')) {
            return (bool) HBCache::get($this->negCacheId());
        }

        $f = $this->negCacheFlag();

        return is_file($f) && (time() - filemtime($f)) <= self::NEG_CACHE_TTL;
    }

    protected function negCacheMark()
    {
        if (class_exists('HBCache')) {
            @HBCache::set($this->negCacheId(), 1, self::NEG_CACHE_TTL);
            return;
        }

        @file_put_contents($this->negCacheFlag(), '1', LOCK_EX);
    }

    protected function negCacheClear()
    {
        if (class_exists('HBCache')) {
            @HBCache::set($this->negCacheId(), 0, 1);
            return;
        }

        @unlink($this->negCacheFlag());
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

        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'nhanhoa_hosting_'
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
            // Contact/addresses copied out of the client area can arrive
            // HTML-escaped; unescape &amp; before urlencoding.
            $pairs[] = $name . '=' . urlencode(str_replace('&amp;', '&', (string) $value));
        }

        return implode('&', $pairs);
    }

    /**
     * Decode a response body.
     *
     * Some commands answer with a JSON *string* containing JSON, so one decode
     * leaves a string behind. Decode again when that happens.
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
     */
    protected function normalize(array $result)
    {
        if (!isset($result['status']) && isset($result['success'])) {
            $result['status'] = $result['success'] ? 'ok' : 'error';
        }

        if (!isset($result['status'])) {
            // Some list endpoints (service_code) answer with a bare object or a
            // top-level list and no status field; treat a non-empty payload as
            // success rather than inventing an error.
            $result['status'] = !empty($result) ? 'ok' : 'error';
            if ($result['status'] === 'error' && !isset($result['msg'])) {
                $result['msg'] = 'Phản hồi API thiếu trường status.';
            }
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
            HBDebug::debug('nhanhoa_hosting ' . $cmd, ['formdata' => $this->mask($formdata), 'response' => $body]);
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
