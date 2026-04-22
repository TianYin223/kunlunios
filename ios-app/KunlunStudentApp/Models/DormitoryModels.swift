import Foundation

struct DormitoryData: Decodable {
    let items: [DormitoryItem]
    let keyword: String
}

struct DormitoryItem: Decodable, Identifiable {
    let id: Int
    let dormitoryNo: String
    let weeklyScore: Double
    let monthlyScore: Double
}

