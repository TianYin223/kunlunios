import Foundation

struct LoginRequest: Encodable {
    let username: String
    let password: String
    let deviceName: String
}

struct LoginData: Decodable {
    let token: String
    let tokenType: String
    let expiresAt: String
    let user: UserInfo
}

