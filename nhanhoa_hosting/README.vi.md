# Hosting Nhân Hòa (`nhanhoa_hosting`)

[English](README.md) | Tiếng Việt

Module HostBill loại Hosting đặt, đồng bộ và gia hạn đơn hosting qua API đại lý Nhân Hòa (`https://api.nhanhoa.com/`). HostBill không quản trị máy chủ hosting; API hiện chỉ cung cấp một số thao tác theo đơn hàng.

## Kết nối và sản phẩm

| Trường HostBill | Giá trị |
|---|---|
| Username / Password | `auth-user` / `auth-pwd` |
| Custom → Reseller ID | `auth-id` |
| Custom → Debug log | Ghi vào `nhanhoa_hosting.log` |

Các lựa chọn sản phẩm: `hostingCode` tải từ `service_code` nhóm 1; `os_template` là tùy chọn; `hosting_months` tối thiểu 6 tháng; `control_panel_url` là liên kết tĩnh, không phải SSO. Giá trị đã lưu của danh sách chọn vẫn được giữ lại khi API lỗi.

## Nghiệp vụ

| Nghiệp vụ | API |
|---|---|
| Kiểm tra kết nối | `get_balance` |
| Đặt đơn | `register_hosting` rồi thử đọc lại bằng `get_infohosting` |
| Gia hạn | `renew_hosting` (cần `ord_id`, tối thiểu 6 tháng) |
| Đồng bộ trạng thái/hạn | `get_infohosting` |
| Danh sách gói | `service_code` |

Đăng ký là bất đồng bộ và chỉ trả trạng thái/thông báo. Nếu chưa có mã đơn ngay, dịch vụ HostBill vẫn được tạo; có thể chạy **Đồng bộ** sau. Trang quản trị và client hiển thị thông tin đơn, hạn và liên kết portal; widget sử dụng thông tin đã lưu/cached lookup. Không cần cron để đọc hạn dùng.

## Cài đặt

1. Chép module vào `includes/modules/Hosting/nhanhoa_hosting/` và kích hoạt trong **Settings → Modules**.
2. Tạo Server/App với thông tin đại lý, chạy **Test connection**.
3. Kết nối sản phẩm với app, tải/chọn `hostingCode`, đặt `hosting_months` tối thiểu 6 và tùy chọn liên kết control panel.

## Giới hạn API

API hosting chỉ có `register_hosting`, `renew_hosting`, `get_infohosting` cùng các lệnh danh sách/tra cứu chung. Không hỗ trợ suspend, unsuspend, terminate, đổi gói/mật khẩu hoặc điều khiển máy chủ; các method vòng đời chỉ cập nhật trạng thái HostBill và thông báo cần thao tác thủ công tại `customer.nhanhoa.com`.

## Bảo mật và hỗ trợ

Debug log có thể chứa domain, mã đơn và phản hồi API. Giới hạn quyền truy cập, chỉ bật khi cần chẩn đoán và ẩn dữ liệu trước khi chia sẻ. Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
