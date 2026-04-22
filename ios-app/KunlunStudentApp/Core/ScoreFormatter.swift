import Foundation

enum ScoreFormatter {
    static func text(_ value: Double) -> String {
        let raw = String(format: "%.2f", value)
        let formatted = raw.replacingOccurrences(
            of: #"(\.\d*?[1-9])0+$|\.0+$"#,
            with: "$1",
            options: .regularExpression
        )
        return formatted.isEmpty ? "0" : formatted
    }
}

