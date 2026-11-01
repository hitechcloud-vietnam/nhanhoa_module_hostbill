<?php

/**
 * Registrant profile as the registrar holds it: contacts, bản khai state and
 * order-processing state in one panel.
 *
 * getekycProfile() makes three registrar calls, so this widget never runs on a
 * plain render — only on its own token-checked submit.
 */

if (!defined('MAINDIR')) {
    die('Access denied');
}

require_once dirname(dirname(__DIR__)) . '/lib/class.nhanhoa_domain_widget.php';

class Widget_nhanhoa_domain_profile extends Nhanhoa_Domain_Widget
{
    protected $widgetfullname = 'nhanhoa_domain_profile';

    protected $description = 'Hồ sơ chủ thể tên miền tại Nhân Hòa: contact, bản khai, trạng thái xử lý.';

    protected $action = 'nhanhoaDomainProfile';

    protected $prefix = 'nhanhoa_domain_profile';

    /** Contact fields worth showing a client; the rest stay out of the panel. */
    private $contactLabels = [
        'companyname' => 'Tổ chức',
        'firstname'   => 'Họ tên',
        'nationalid'  => 'CMND/CCCD/Hộ chiếu',
        'taxid'       => 'Mã số thuế',
        'birthday'    => 'Ngày sinh',
        'email'       => 'Email',
        'phonenumber' => 'Điện thoại',
        'address1'    => 'Địa chỉ',
        'ward'        => 'Phường / Xã',
        'city'        => 'Quận / Huyện',
        'state'       => 'Tỉnh / Thành phố',
        'country'     => 'Quốc gia',
    ];

    public function controller($service, &$module, &$smarty, &$params)
    {
        $this->assign($smarty, 'summary', null);
        $this->assign($smarty, 'contacts', null);
        $this->assign($smarty, 'error', null);

        if (!$this->doesApply($module) || !$this->isSubmitted($params)) {
            return;
        }

        $profile = $module->getekycProfile();
        if (!is_array($profile)) {
            $this->assign($smarty, 'error', $this->moduleError($module, 'Không tải được hồ sơ chủ thể.'));
            return;
        }

        $summary = [
            'Tên miền'         => $profile['domain_name'],
            'Loại tên miền'    => $profile['is_vn_domain'] ? 'Tên miền .vn' : 'Tên miền quốc tế',
            'Trạng thái hồ sơ' => is_array($profile['declaration'])
                ? $profile['declaration']['declaration_status_text']
                : '',
        ];

        if (is_array($profile['processing'])) {
            $summary['Mã đơn']         = $profile['processing']['ord_id'];
            $summary['Kết thúc xử lý'] = $profile['processing']['ord_end_time'];
            $summary += $this->flatten($profile['processing']['content'], 'Chi tiết');
        }

        $this->assign($smarty, 'summary', array_filter($summary, 'strlen'));
        $this->assign($smarty, 'contacts', $this->readableContacts($profile['contacts']));
    }

    /**
     * Turn the stored contact rows into label => value pairs per contact type.
     * v2 dumped raw JSON into a <pre>, keys and all.
     */
    private function readableContacts($contacts)
    {
        if (!is_array($contacts)) {
            return null;
        }

        $types = [
            'registrant' => 'Chủ thể',
            'admin'      => 'Quản trị',
            'tech'       => 'Kỹ thuật',
            'billing'    => 'Thanh toán',
        ];

        $readable = [];
        foreach ($types as $key => $title) {
            if (empty($contacts[$key]) || !is_array($contacts[$key])) {
                continue;
            }

            $fields = [];
            foreach ($this->contactLabels as $field => $label) {
                if (isset($contacts[$key][$field]) && trim((string) $contacts[$key][$field]) !== '') {
                    $fields[$label] = (string) $contacts[$key][$field];
                }
            }

            if ($fields) {
                $readable[$title] = $fields;
            }
        }

        return $readable ?: null;
    }
}
