# API v1（安卓端）

## 鉴权方式

- 登录后返回 `token`
- 后续请求 Header 带：`Authorization: Bearer <token>`

## 接口

- `POST /api/v1/auth/login.php`
- `POST /api/v1/auth/logout.php`
- `GET /api/v1/me.php`
- `GET /api/v1/dormitories.php?keyword=&limit=50`
- `GET /api/v1/score/options.php`
- `POST /api/v1/score/submit.php`
- `GET /api/v1/score/records.php?page=1&page_size=20`

## 打分上报规则（与网页端一致）

- 宿舍必须存在且启用
- 每次必须上传 4-10 张图片
- 仅支持 `JPG/PNG/WEBP/GIF`，单张最大 `5MB`
- 减分值必须在系统配置可选项内
- 每个宿舍受每日上报次数限制
- 减分后周/月分数不能低于 `0`

