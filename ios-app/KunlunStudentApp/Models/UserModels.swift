import Foundation

struct UserInfo: Codable, Equatable {
    let id: Int
    let username: String
    let realName: String
    let role: String
}

struct MeSettings: Decodable {
    let currentWeek: String
    let currentMonth: String
    let dailyLimit: Int
    let scoreOptions: [Double]
    let weeklyMaxScore: Double
}

struct MeData: Decodable {
    let user: UserInfo
    let settings: MeSettings
    let todaySubmitCount: Int
    let recentRecords: [ScoreRecord]
}

