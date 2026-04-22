import Foundation
import CoreGraphics

enum AppConfig {
    static let appName = "昆仑宿舍学生管理系统"
    static let defaultAPIBaseURL = URL(string: "https://kl.siyun223.com/")!
    static let sessionDeviceName = "ios-native"

    static let minPhotoCount = 4
    static let maxPhotoCount = 10
    static let maxUploadBytesPerImage = 5 * 1024 * 1024
    static let maxUploadImageDimension: CGFloat = 1920
    static let requestTimeout: TimeInterval = 40
}
