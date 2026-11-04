# Đăng ký tên miền Nhân Hòa (`nhanhoa_domain`)

[English](README.md) | Tiếng Việt

Module registrar cho HostBill, kế nhiệm `nhanhoav2`; hai module có thể cùng cài đặt và dùng chung bảng mirror liên hệ `hb_domain_contacts_nhanhoa`.

## API và lưu trữ

API dùng `POST https://api.nhanhoa.com/?act=<cmd>` cùng `auth-user`, `auth-pwd`, `auth-id`, `cmd` và `formdata` dạng query string đã mã hóa URL. Một số phản hồi là JSON lồng và có `success: true`; transport chuẩn hóa kết quả và cache các truy vấn chỉ đọc trong 60 giây.

Module hỗ trợ kiểm tra/tra cứu/đăng ký/chuyển/renew domain, khóa registrar, EPP code, bảo vệ thông tin, cập nhật nameserver/contact, đồng bộ, giá, backorder, danh sách domain chưa khai báo, tra cứu địa danh và quản lý bản ghi DNS. Danh sách lệnh/method đầy đủ nằm trong README tiếng Anh.

## Địa chỉ hành chính Việt Nam

Sau cải cách hành chính 2025, tỉnh/thành và xã/phường là hai cấp chính; quận/huyện là trường legacy. Mapping liên hệ:

| HostBill | Registrar | Ý nghĩa |
|---|---|---|
| `state` | `ownerCity` | Tỉnh/thành, cấp chính |
| `ward` | `ownerTown` | Xã/phường, cấp chính |
| `city` | `ownerDistrict` | Quận/huyện, legacy/tùy chọn |

Địa chỉ cũ vẫn gửi quận/huyện đã lưu. Với địa chỉ mới không có quận/huyện, module gửi giá trị phường/xã vào `ownerDistrict` để tương thích trường bắt buộc được tài liệu registrar mô tả; việc API đang hoạt động chấp nhận fallback này chưa thể xác minh ngoại tuyến. Quản trị viên cần kiểm tra lại địa chỉ chủ thể sau khi chuyển đổi. API không cung cấp danh sách phường trực tiếp theo tỉnh; tra cứu ward hiện dùng cách tương thích legacy.

## Giao diện và cài đặt

Module có controller quản trị, widget khu vực client và bảng mirror liên hệ được tạo khi cài đặt/khởi tạo. Để cài, chép vào `includes/modules/Domain/nhanhoa_domain/`, kích hoạt module, nhập username/password/user ID đại lý và chạy **Test connection** (`get_balance`). Có thể bật log `nhanhoa_domain.log`; log che mật khẩu nhưng các thông tin tên miền/liên hệ khác vẫn có thể nhạy cảm.

## Giới hạn

Một số lệnh xử lý theo cơ chế bất đồng bộ hoặc phụ thuộc xác nhận của registrar. Đối chiếu trạng thái đơn, hồ sơ chủ thể, EPP và thao tác thực tế với portal Nhân Hòa. Không công khai dữ liệu đăng ký, mã EPP hoặc thông tin cá nhân.

## Bảo mật và hỗ trợ

Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
