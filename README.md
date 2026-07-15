# TADA Site Agent

WordPress plugin (**Edge agent**) của **TADA** — thu thập tín hiệu sức khỏe website (SEO, cấu hình Rank Math) và cung cấp qua **REST API xác thực HMAC-SHA256** cho nền tảng phân tích của TADA. **Read-only** — không ghi bất kỳ dữ liệu nào của WordPress/Rank Math.

- **Author:** TADA — https://tada.vn
- **License:** GPL-2.0-or-later
- **Self-update:** GitHub Releases + Plugin Update Checker → nút **Update** hiện ngay trong WP Admin.

## Cài đặt
1. Tải `tada-site-agent.zip` từ [Releases](https://github.com/tadavn/tada-site-agent/releases).
2. WP Admin → Plugins → Add New → Upload Plugin → chọn zip → **Activate**.
3. Vào menu **TADA Site Agent** (sidebar) → đặt **Secret Key** (≥ 16 ký tự) → copy key sang dashboard TADA.

## Bảo mật
- HMAC-SHA256 (`X-Tada-Site-Agent-Timestamp` + `X-Tada-Site-Agent-Signature`).
- Chống replay (cửa sổ ±300s) · Rate limit 10 req/phút · Log 50 lần auth gần nhất.
- Read-only tuyệt đối; zero write.

## REST API
- `POST /wp-json/tada-site-agent/v1/scan` — quét cấu hình Rank Math (cần HMAC).
- `GET  /wp-json/tada-site-agent/v1/ping` — health check, trả version.

## Yêu cầu
- PHP ≥ 7.4 · WordPress ≥ 5.6 · (tùy chọn) Rank Math SEO để làm giàu dữ liệu.
