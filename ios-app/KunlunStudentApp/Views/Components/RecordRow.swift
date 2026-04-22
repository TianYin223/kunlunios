import SwiftUI

struct RecordRow: View {
    let item: ScoreRecord

    private var scoreText: String {
        if item.signedScore >= 0 {
            return "+\(formatScore(item.signedScore))"
        }
        return formatScore(item.signedScore)
    }

    private var scoreColor: Color {
        item.signedScore >= 0 ? .green : .red
    }

    var body: some View {
        VStack(alignment: .leading, spacing: 4) {
            Text("\(item.dormitoryNo)  \(scoreText)")
                .font(.headline)
                .foregroundStyle(scoreColor)
            Text("星期 \(item.period) | 时间 \(item.createdAt) | 照片 \(item.imageCount) 张")
                .font(.footnote)
                .foregroundStyle(.secondary)
        }
        .padding(.vertical, 6)
    }

    private func formatScore(_ value: Double) -> String {
        let raw = String(format: "%.2f", value)
        return raw.replacingOccurrences(of: #"(\.\d*?[1-9])0+$|\.0+$"#, with: "$1", options: .regularExpression)
    }
}

