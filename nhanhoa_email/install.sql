-- Bảng thuộc về nhanhoa_email.

-- Bản ghi đơn hàng Email Hosting đã cấp qua API đại lý Nhân Hòa.
--
-- API đại lý định danh một đơn hàng bằng cặp (serviceCode, domainName) -> ord_id,
-- và chỉ get_infoemail mới trả về ord_id. Bảng này giữ lại ánh xạ đó ở phía
-- HostBill để: (1) biết dịch vụ nào ứng với đơn hàng nào khi API bị giới hạn tần
-- suất, (2) tra nhanh đơn hàng tại Nhân Hòa khi cần, (3) lưu trạng thái cục bộ cho
-- các thao tác mà API đại lý không hỗ trợ (tạm ngưng/huỷ/đổi gói).
--
-- Thông tin chính của dịch vụ vẫn nằm ở account_details (option1..option5); bảng
-- này là bản ghi phụ để tra cứu và kiểm toán, không phải nguồn sự thật.
CREATE TABLE IF NOT EXISTS `hb_nhanhoa_email_orders` (
  `id`            INT(11) NOT NULL AUTO_INCREMENT,
  `account_id`    INT(11) NOT NULL DEFAULT 0,
  `client_id`     INT(11) NOT NULL DEFAULT 0,
  `server_id`     INT(11) NOT NULL DEFAULT 0,
  `domain`        VARCHAR(253) NOT NULL DEFAULT '',
  `service_code`  VARCHAR(191) NOT NULL DEFAULT '',
  `ord_id`        INT(11) NOT NULL DEFAULT 0,
  `ord_name`      VARCHAR(191) NOT NULL DEFAULT '',
  `end_time`      INT(11) NOT NULL DEFAULT 0,
  `status`        ENUM('active','suspended','terminated') NOT NULL DEFAULT 'active',
  `remote_status` VARCHAR(191) NOT NULL DEFAULT '',
  `created`       DATETIME DEFAULT NULL,
  `updated`       DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  -- Một dịch vụ HostBill ứng với đúng một đơn hàng Email Hosting.
  UNIQUE KEY `account_id` (`account_id`),
  KEY `client_id` (`client_id`),
  KEY `domain` (`domain`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
