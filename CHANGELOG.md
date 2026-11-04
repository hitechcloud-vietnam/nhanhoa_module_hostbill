# Changelog

## 1.1.1 - 2026-11-04

### nhanhoa_cloud
- Khi API không phản hồi, các tra cứu chỉ đọc thất bại nhanh trong thời gian cache âm; form vẫn giữ lựa chọn đã lưu và hiển thị thông báo lỗi có hướng dẫn.

### nhanhoa_dns
- Chuẩn hóa metadata phiên bản và bổ sung tài liệu tiếng Việt; không thay đổi hành vi API.

### nhanhoa_domain
- Các tra cứu chỉ đọc thất bại nhanh sau lỗi kết nối gần đây; form giá giữ lại kết quả đã tải được nếu một loại tên miền khác lỗi.
- Bổ sung thông báo quản trị có hướng dẫn kiểm tra kết nối và IP whitelist.

### nhanhoa_email
- Khi API không phản hồi, tra cứu chỉ đọc thất bại nhanh trong thời gian cache âm; trường chọn gói giữ giá trị đã lưu và nêu rõ lỗi kết nối.

### nhanhoa_hosting
- Khi API không phản hồi, các danh sách cấu hình giữ lựa chọn đã lưu và hiển thị lỗi thay vì để trống im lặng.

### nhanhoa_ssl
- Chuẩn hóa metadata phiên bản và bổ sung tài liệu tiếng Việt; không thay đổi hành vi API.

All six modules report version 1.1.1. Read-only API lookups now avoid repeating connection waits immediately after transport failures; affected configuration forms preserve saved selections and surface actionable errors. Documentation is available in English and Vietnamese.

Tất cả sáu module dùng phiên bản 1.1.1. Tra cứu API chỉ đọc không lặp lại thời gian chờ kết nối ngay sau lỗi transport; các form cấu hình liên quan giữ lựa chọn đã lưu và hiển thị lỗi có hướng dẫn. Tài liệu có cả tiếng Anh và tiếng Việt.

## 1.1.0 - 2026-11-02

### nhanhoa_cloud
- Refreshed admin account and product configuration screens, client-area overview, and status widget.
- Added the admin stylesheet URL to the module controller.

### nhanhoa_dns
- Added DNS admin styling and exposed the module asset URL to its configuration screens.

### nhanhoa_domain
- Refreshed the domain admin overview, status, details, contacts, and action screens.

### nhanhoa_email
- Refreshed admin account and product configuration screens, client-area details, and status widget.
- Added the admin stylesheet URL to the module controller.

### nhanhoa_hosting
- Refreshed admin account and product configuration screens, client-area details, and status widget.
- Added the admin stylesheet URL to the module controller.

### nhanhoa_ssl
- Refreshed the SSL admin service screen and added the admin stylesheet URL to its controller.

All six modules now report version 1.1.0. This release focuses on interface and styling updates; it does not change provider API behavior.