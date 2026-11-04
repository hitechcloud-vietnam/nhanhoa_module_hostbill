# Email Hosting Nhân Hòa (`nhanhoa_email`)

[English](README.md) | Tiếng Việt

Module HostBill loại Hosting đặt, đồng bộ và gia hạn đơn Email Hosting qua API đại lý tại `https://api.nhanhoa.com/`. Đây là module cấp phát đơn hàng, không phải module điều khiển mail server; quản trị mailbox thực hiện tại portal Nhân Hòa.

## Kết nối và gói dịch vụ

| Trường | Giá trị |
|---|---|
| Username / Password | `auth-user` / `auth-pwd` |
| ID đại lý (custom `auth_id`) | `auth-id` |
| Debug log (custom `debug_log`) | Ghi vào `nhanhoa_email.log` |

`serviceCode` lấy từ `service_code` nhóm 2; nếu không tải được danh sách, có thể dùng `serviceCodeText`. `serviceMonth` tối thiểu 6 tháng. Mỗi dịch vụ gắn với một domain; thông tin đơn được lưu trong tùy chọn dịch vụ và bảng `hb_nhanhoa_email_orders`.

## Nghiệp vụ và đồng bộ

| Nghiệp vụ | API |
|---|---|
| Đăng ký và đọc đơn | `register_email`, `get_infoemail` |
| Gia hạn | `renew_email` (cần `ord_id`, tối thiểu 6 tháng) |
| Đồng bộ/cron | `get_infoemail` |
| Kiểm tra kết nối | `get_balance` |

`register_email` có thể trả về trước khi ID/trạng thái sẵn sàng; cần đồng bộ lại và xác nhận đơn tại portal trước khi xử lý thanh toán hoặc vòng đời. HostBill cron chạy tác vụ đồng bộ định kỳ; cần bảo đảm `bin/cron.php` hoạt động. Ánh xạ trạng thái là best-effort vì API chỉ tài liệu hóa một phần nội dung trả về.

REST route `info` và `sync` yêu cầu xác thực, kiểm tra chủ sở hữu dịch vụ và chỉ cung cấp dữ liệu cần thiết. Trang quản trị có đồng bộ/gia hạn; khu vực client và widget hiển thị domain, gói, hạn dùng và liên kết portal.

## Cài đặt

1. Chép vào `includes/modules/Hosting/nhanhoa_email/` và kích hoạt để tạo bảng đơn hàng.
2. Tạo Server/App với thông tin đại lý và chạy **Test connection**.
3. Kết nối sản phẩm, chọn `serviceCode` hoặc nhập `serviceCodeText`, đặt `serviceMonth` tối thiểu 6.
4. Cấu hình HostBill cron để tác vụ đồng bộ hàng ngày có thể chạy.

## Giới hạn API

API chỉ hỗ trợ đăng ký, gia hạn và đọc thông tin đơn (cùng các lệnh danh sách/tra cứu chung). Không có lệnh suspend/unsuspend, terminate trực tiếp, đổi gói, đổi mật khẩu hoặc quản lý mailbox. Các nút vòng đời trong HostBill chỉ cập nhật trạng thái cục bộ và ghi thông báo; thao tác thật cần thực hiện tại portal Nhân Hòa. Dịch vụ chấm dứt theo ngày hết hạn nếu không gia hạn.

## Bảo mật và hỗ trợ

Log có thể chứa domain, mã đơn và phản hồi nhà cung cấp. Không gửi thông tin đại lý, mật khẩu mailbox hoặc dữ liệu khách hàng. Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
