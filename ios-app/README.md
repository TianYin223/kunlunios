# Kunlun iOS App

这是为现有 `学生管理系统` 后端接口开发的 iOS 客户端（SwiftUI）。

## 已实现功能

- 登录 / 退出登录
- 打分上报
- 拍照上传
- 相册多选上传
- 提交前图片压缩（单张不超过 5MB）
- 用户中心信息
- 我的上报记录
- 首次启动自动申请相机与相册权限

## 接口地址

默认接口地址在 [AppConfig.swift](./KunlunStudentApp/App/AppConfig.swift)：

- `https://kl.siyun223.com/`

如需切换，直接修改 `defaultAPIBaseURL`。

## 在 Mac 上生成 Xcode 工程

当前仓库已提供 `project.yml`（XcodeGen 配置）。

1. 安装 XcodeGen：`brew install xcodegen`
2. 进入 `ios-app` 目录执行：`xcodegen generate`
3. 打开 `KunlunStudentApp.xcodeproj`
4. 选择签名团队与证书后编译到真机

如果你不想安装 XcodeGen：

1. 在 Xcode 新建一个 `App`（SwiftUI）工程
2. 把 `ios-app/KunlunStudentApp` 下源码与资源拖入工程
3. 保持 `Info.plist` 的权限文案与 `Assets.xcassets` 图标资源

## 无 Mac 云打包（unsigned IPA）

仓库已提供 GitHub Actions 工作流：

- [ios-unsigned-ipa.yml](../.github/workflows/ios-unsigned-ipa.yml)

它会在云端 `macOS runner` 编译并产出：

- `KunlunStudentApp-unsigned.ipa`

注意：这是**未签名** IPA，不能直接安装。  
你可以用爱思助手 Apple ID 签名后安装（7 天测试）。

### 触发方法

1. 把仓库推送到 GitHub
2. 打开仓库 Actions
3. 运行 `iOS Unsigned IPA` 工作流（`workflow_dispatch`）
4. 下载 artifacts 里的 `KunlunStudentApp-unsigned-ipa`

## 证书分发说明（你当前场景）

你说内部不超过 20 人，技术上建议优先两种：

- Apple Developer Program + Ad Hoc（需登记每台设备 UDID）
- Apple Developer Program + TestFlight（不用登记 UDID，更省维护）

“免费个人账号（Personal Team）”不适合多人长期分发。
