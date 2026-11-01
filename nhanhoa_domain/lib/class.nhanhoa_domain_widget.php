<?php

/**
 * Shared base for the nhanhoa_domain client-area widgets.
 *
 * Follows the shape HostBill's own domain widgets use (see
 * includes/types/domains/widgets/eppcode and .../sync, whose templates are the
 * only readable part of core):
 *
 *   - the class is `Widget_<dirname>` in `widgets/<dirname>/widget.<dirname>.php`
 *     and renders `<dirname>.tpl` from the same directory;
 *   - `controller($service, &$module, &$smarty, &$params)` assigns template
 *     variables and acts only when `$params['widgetdo']` names this widget's
 *     action AND `$params['token_valid']` is set — the CSRF token comes from
 *     `{securitytoken}` in the template;
 *   - the form posts to `action=""`, as every core widget does. v2 posted to
 *     `$_SERVER['REQUEST_URI']` echoed into the markup, which is both
 *     unnecessary and an injection surface;
 *   - nothing calls the registrar on plain page render. v2's status widget hit
 *     the Nhan Hoa API on every single client-area page view.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

abstract class Nhanhoa_Domain_Widget extends DomainsWidget
{
    /** The module this widget belongs to. */
    const MODULE_FILE = 'class.nhanhoa_domain.php';

    /**
     * OPTION_UNIQUE | OPTION_DEFAULT_ON | OPTION_SHOW_WHEN_PENDING
     * | OPTION_SHOW_WHEN_PENDING_SYNC = 1 + 2 + 8 + 32.
     *
     * DEFAULT_ON matters: the v2 widgets registered with options=1, were never
     * attached to a single product in hb_widgets, and so never rendered.
     * SHOW_WHEN_PENDING matters even more — eKYC and the bản khai are exactly
     * what a client needs while the domain is still Pending Registration.
     */
    protected $info = [
        'appendtpl'  => false,
        'replacetpl' => false,
        'options'    => 43,
    ];

    /** Value of the `widgetdo` field this widget answers to. */
    protected $action = '';

    /** Prefix for every template variable this widget assigns. */
    protected $prefix = '';

    public function doesApply(&$module)
    {
        return $module->getModuleFilename() === self::MODULE_FILE;
    }

    /** True when this render is the widget's own, token-checked submit. */
    protected function isSubmitted(array $params)
    {
        return isset($params['widgetdo'])
            && $params['widgetdo'] === $this->action
            && !empty($params['token_valid']);
    }

    protected function assign(&$smarty, $key, $value)
    {
        $smarty->assign($this->prefix . '_' . $key, $value);
    }

    /**
     * Error text the module recorded via addError().
     *
     * DomainModule keeps it behind getLastError() (from MultiConnectionModule);
     * $this->error stays empty, so reading that property finds nothing.
     */
    protected function moduleError(&$module, $default)
    {
        $error = method_exists($module, 'getLastError') ? $module->getLastError() : '';

        if (is_array($error)) {
            $error = implode(' | ', array_filter(array_map('trim', $error)));
        }

        $error = trim((string) $error);

        return $error !== '' ? $error : $default;
    }

    /**
     * Vietnamese labels for the registrar's payload keys.
     *
     * Anything not listed here falls back to the raw key, which is still
     * better than v2's behaviour: it ran `|escape` over the whole `content`
     * array and printed the word "Array".
     */
    private $contentLabels = [
        'domainvn_cron_status'  => 'Mã trạng thái',
        'domainvn_cron_comment' => 'Trạng thái',
        'domainvn_time_create'  => 'Thời điểm tạo',
        'domain_cron_status'    => 'Mã trạng thái',
        'domain_cron_comment'   => 'Trạng thái',
        'domain_time_create'    => 'Thời điểm tạo',
        'ord_status'            => 'Mã trạng thái đơn',
        'ord_status_comment'    => 'Trạng thái đơn',
        'domain_status'         => 'Trạng thái tên miền',
        'domain_msg'            => 'Ghi chú',
        'can_renew'             => 'Cho phép gia hạn',
    ];

    /**
     * Flatten a nested API payload into label => value pairs for display.
     *
     * The registrar returns `content` as an object for .vn domains and as a
     * scalar elsewhere, and puts unix timestamps in its `*time*` keys.
     */
    protected function flatten($value, $prefix = '')
    {
        if (!is_array($value)) {
            return $prefix === '' ? [] : [$prefix => (string) $value];
        }

        $flat = [];
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $flat += $this->flatten($item, $prefix);
                continue;
            }

            $label = $this->contentLabels[$key] ?? ($prefix === '' ? (string) $key : $prefix . ' ' . $key);
            $flat[$label] = $this->readableValue($key, $item);
        }

        return $flat;
    }

    /** Unix timestamps arrive as bare integers in any `*time*` key. */
    private function readableValue($key, $value)
    {
        $value = (string) $value;

        if (stripos($key, 'time') !== false && ctype_digit($value) && strlen($value) === 10) {
            return date('d-m-Y H:i:s', (int) $value);
        }

        // The registrar leaks stray markup into its comments ('>Đã kích hoạt').
        return ltrim(strip_tags($value), '> ');
    }
}
