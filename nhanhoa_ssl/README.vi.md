# Chứng chỉ SSL Nhân Hòa (`nhanhoa_ssl`)

[English](README.md) | Tiếng Việt

Module SSL cho HostBill đặt, đồng bộ và gia hạn chứng chỉ qua API đại lý Nhân Hòa (`https://api.nhanhoa.com/`). API tối giản; CSR, xác thực domain và bàn giao chứng chỉ do portal/email Nhân Hòa xử lý.

## Kết nối và cấu hình sản phẩm

| Trường HostBill | Giá trị |
|---|---|
| Username / Password | `auth-user` / `auth-pwd` |
| Input 1 | `auth-id` (ID đại lý) |
| Checkbox ghi log | Ghi request API vào `nhanhoa_ssl.log` |

Chọn SSL Product từ `service_code` nhóm 4. Có thể đặt số SAN tối đa; 0 sẽ tắt form SAN. **Test connection** gọi `get_balance`.

## Nghiệp vụ và giao diện

| Nghiệp vụ | API |
|---|---|
| Đặt đơn | `register_ssl` |
| Gia hạn | `renew_ssl` |
| Trạng thái, mã đơn, hạn | `get_infossl` |
| Danh sách gói | `service_code` nhóm 4 |
| Kiểm tra kết nối | `get_balance` |

Trang quản trị hiển thị common name, gói, mã/tên đơn, trạng thái, hạn và SAN; có thao tác đồng bộ/gia hạn. Khu vực client dùng giao diện SSL HostBill tiêu chuẩn. REST route yêu cầu xác thực và kiểm tra chủ sở hữu dịch vụ.

## Cài đặt

1. Chép module vào `includes/modules/Hosting/nhanhoa_ssl/`.
2. Tạo connected app/server với thông tin đại lý.
3. Tạo sản phẩm SSL, kết nối module và chọn service code.
4. Chạy **Test connection**, sau đó đặt đơn theo quy trình SSL của HostBill.

## Giới hạn API

API chỉ có đăng ký, gia hạn, đọc thông tin, danh sách gói và số dư. Không hỗ trợ tạo/gửi CSR, DCV (email/HTTP/DNS), gửi lại xác nhận, cấp lại, hủy/hoàn tiền hoặc tải chứng chỉ. Các thao tác này thực hiện ngoài API tại portal Nhân Hòa; các method tương ứng trong HostBill không giả lập thành công. SAN được gửi ghép bằng dấu phẩy trong `domainNameList`; cách này cần được nhà cung cấp xác nhận.

## Bảo mật và hỗ trợ

Không ghi hoặc chia sẻ private key, CSR nhạy cảm, thông tin đăng nhập hay dữ liệu khách hàng. Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
