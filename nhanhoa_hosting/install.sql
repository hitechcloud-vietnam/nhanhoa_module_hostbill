-- Tables for nhanhoa_hosting.
--
-- The service's own state (domain, plan code, order id/name) lives in
-- hb_accounts extra_details, so no table is strictly required for operation.
-- This optional map keeps a quick account -> Nhan Hoa order lookup for cron
-- sync and reporting; install() is idempotent and tolerates its absence.
CREATE TABLE IF NOT EXISTS `hb_nhanhoa_hosting_orders` (
  `id`          INT(11) NOT NULL AUTO_INCREMENT,
  `account_id`  INT(11) NOT NULL DEFAULT 0,
  `client_id`   INT(11) NOT NULL DEFAULT 0,
  `server_id`   INT(11) NOT NULL DEFAULT 0,
  `domain`      VARCHAR(253) NOT NULL DEFAULT '',
  `hosting_code` VARCHAR(191) NOT NULL DEFAULT '',
  `ord_id`      VARCHAR(64) NOT NULL DEFAULT '',
  `ord_name`    VARCHAR(191) NOT NULL DEFAULT '',
  `status`      VARCHAR(32) NOT NULL DEFAULT '',
  `end_time`    INT(11) NOT NULL DEFAULT 0,
  `created`     DATETIME DEFAULT NULL,
  `updated`     DATETIME DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `account_id` (`account_id`),
  KEY `ord_id` (`ord_id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
