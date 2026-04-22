import SwiftUI

struct ScoreOptionChip: View {
    let title: String
    let isSelected: Bool
    let action: () -> Void

    var body: some View {
        Button(action: action) {
            Text(title)
                .font(.system(size: 20, weight: .semibold))
                .foregroundStyle(isSelected ? Color.white : Color(.label))
                .frame(minWidth: 72, minHeight: 44)
                .padding(.horizontal, 6)
                .background(
                    RoundedRectangle(cornerRadius: 14, style: .continuous)
                        .fill(isSelected ? Color(red: 0.07, green: 0.10, blue: 0.22) : Color(.systemGray6))
                )
        }
        .buttonStyle(.plain)
    }
}

