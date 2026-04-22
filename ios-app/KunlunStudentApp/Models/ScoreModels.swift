import Foundation

struct ScoreOptionsData: Decodable {
    let scoreOptions: [Double]
    let dailyLimit: Int
    let currentWeek: String
    let currentMonth: String
    let weeklyMaxScore: Double
}

struct SubmitScoreData: Decodable {
    let recordId: Int
    let dormitoryNo: String
    let scoreType: String
    let score: Double
    let signedScore: Double
    let currentWeeklyScore: Double
    let currentMonthlyScore: Double
    let period: String
    let images: [String]
}

struct ScoreRecordsData: Decodable {
    let items: [ScoreRecord]
    let page: Int
    let pageSize: Int
    let total: Int
    let totalPages: Int
}

struct ScoreRecord: Decodable, Identifiable {
    let id: Int
    let dormitoryNo: String
    let scoreType: String
    let score: Double
    let signedScore: Double
    let reason: String?
    let period: String
    let createdAt: String
    let images: [String]?
    let imageCount: Int
}

