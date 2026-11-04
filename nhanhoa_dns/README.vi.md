# Nhân Hòa ZoneDNS (`nhanhoa_dns`)

[English](README.md) | Tiếng Việt

Module HostBill `DNSModule` quản lý bản ghi ZoneDNS qua API đại lý Nhân Hòa (`https://api.nhanhoa.com/`, tài liệu tại <https://docs.nhanhoa.com/zonedns.md>). Zone được xác định theo tên miền thuộc tài khoản đại lý; module không tạo hoặc xóa zone.

## Kết nối và cấu hình

| Trường HostBill | Giá trị |
|---|---|
| Username / Password | `auth-user` / `auth-pwd` |
| Reseller ID (ID API) | `auth-id` (`custom[auth_id]`) |
| Debug log | `custom[debug_log]`, ghi vào `nhanhoa_dns.log` |
| Default Nameservers | `custom[default_ns]`, mặc định `ns1/ns2.nhanhoa.com` |

Tùy chọn sản phẩm gồm giới hạn domain, DNS template, NS1–NS4, SOA và các cờ hiển thị quản lý zone. **Test connection** gọi `get_balance`. Các lệnh tra cứu chỉ đọc được cache 60 giây để tránh giới hạn tần suất API.

## Chức năng

Module quản lý bản ghi A, AAAA, CNAME, MX, TXT, NS, SRV và CAA bằng `list_record`, `add_record`, `edit_record`, `delete_record`. ID bản ghi là ID thật từ API. Do API không đổi tên bản ghi, thao tác đổi tên được thực hiện bằng xóa rồi thêm. TTL được giới hạn trong 300–86400 giây; MX priority và định dạng SRV/CAA được chuyển đổi theo yêu cầu API.

Controller quản trị kết nối app và cấu hình DNS template. Khu vực client dùng giao diện DNS chung của HostBill. REST route yêu cầu xác thực và xác minh quyền sở hữu dịch vụ.

## Cài đặt

1. Chép vào `includes/modules/Hosting/nhanhoa_dns/`.
2. Tạo connected app/server với thông tin đại lý; có thể cấu hình nameserver mặc định.
3. Tạo sản phẩm DNS, kết nối module, cấu hình giới hạn/template rồi chạy **Test connection**.

## Giới hạn API

API ZoneDNS chỉ quản lý bản ghi. Không có lệnh tạo/xóa/liệt kê zone, reverse DNS hoặc DNSSEC. Các thao tác tương ứng của HostBill chỉ có thể xử lý cục bộ hoặc trả về không hỗ trợ; zone domain và thao tác ngoài API cần được kiểm tra tại portal/nhà cung cấp.

## Bảo mật và hỗ trợ

Thông tin request/response trong debug log có thể chứa domain và dữ liệu DNS. Chỉ bật log khi chẩn đoán, giới hạn quyền đọc và ẩn dữ liệu trước khi chia sẻ. Xem [hướng dẫn bảo mật](../SECURITY.md), [hỗ trợ](../SUPPORT.md) và [quy tắc thương hiệu](../BRAND.md).

## Phiên bản và giấy phép

Phiên bản module hiện tại: **1.1.1**. Xem [changelog](../CHANGELOG.md). Giấy phép MIT; © 2026 **HiTechCloud by Pho Tue SoftWare Solutions JSC**.
