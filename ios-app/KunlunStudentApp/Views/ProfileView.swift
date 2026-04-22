import SwiftUI

struct ProfileView: View {
    @EnvironmentObject private var appViewModel: AppViewModel

    @State private var meData: MeData?
    @State private var records: [ScoreRecord] = []
    @State private var isLoading = false
    @State private var errorText = ""
    @State private var isLoggingOut = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 12) {
                    profileCard
                    recordsCard
                }
                .padding(16)
            }
            .background(Color(.systemGray6))
            .navigationTitle("用户中心")
            .navigationBarTitleDisplayMode(.inline)
            .refreshable {
                await loadData()
            }
            .task {
                if meData == nil {
                    await loadData()
                }
            }
        }
    }

    private var profileCard: some View {
        VStack(alignment: .leading, spacing: 8) {
            Text("用户中心")
                .font(.title3.bold())

            Text("姓名：\(meData?.user.realName ?? "-")")
            Text("账号：\(meData?.user.username ?? "-")")
            Text("当前星期：\(meData?.settings.currentWeek ?? "-")")
            Text("当前月份：\(meData?.settings.currentMonth ?? "-")")
            Text("今日上报：\(meData?.todaySubmitCount ?? 0) / \(meData?.settings.dailyLimit ?? 0)")
            Text("扣分选项：\(scoreOptionsText)")

            HStack(spacing: 10) {
                Button("刷新") {
                    Task { await loadData() }
                }
                .buttonStyle(.borderedProminent)
                .tint(Color(red: 0.07, green: 0.10, blue: 0.22))
                .disabled(isLoading || isLoggingOut)

                Button("退出登录") {
                    Task { await logout() }
                }
                .buttonStyle(.bordered)
                .disabled(isLoading || isLoggingOut)
            }
            .padding(.top, 6)

            if isLoading {
                ProgressView()
                    .padding(.top, 4)
            }

            if !errorText.isEmpty {
                Text(errorText)
                    .foregroundStyle(.red)
                    .font(.footnote)
                    .padding(.top, 2)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .stroke(Color(.systemGray5), lineWidth: 1)
        )
    }

    private var recordsCard: some View {
        VStack(alignment: .leading, spacing: 10) {
            Text("我的上报记录")
                .font(.headline)

            if records.isEmpty {
                Text("暂无记录")
                    .foregroundStyle(.secondary)
                    .font(.subheadline)
                    .padding(.vertical, 8)
            } else {
                LazyVStack(spacing: 0) {
                    ForEach(records) { item in
                        RecordRow(item: item)
                        Divider()
                    }
                }
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
        .padding(16)
        .background(Color.white)
        .clipShape(RoundedRectangle(cornerRadius: 16, style: .continuous))
        .overlay(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .stroke(Color(.systemGray5), lineWidth: 1)
        )
    }

    private var scoreOptionsText: String {
        guard let options = meData?.settings.scoreOptions, !options.isEmpty else {
            return "-"
        }
        return options.map { ScoreFormatter.text($0) }.joined(separator: ",")
    }

    private func loadData() async {
        isLoading = true
        errorText = ""
        defer { isLoading = false }

        do {
            let me = try await appViewModel.apiClient.me()
            meData = me

            do {
                let recordResult = try await appViewModel.apiClient.scoreRecords(page: 1, pageSize: 20)
                records = recordResult.items
            } catch {
                records = me.recentRecords
            }
        } catch let apiError as APIError {
            if case .unauthorized = apiError {
                appViewModel.handleUnauthorized(message: apiError.errorDescription ?? "登录已过期，请重新登录")
                return
            }
            errorText = apiError.errorDescription ?? "加载失败"
        } catch {
            errorText = error.localizedDescription
        }
    }

    private func logout() async {
        isLoggingOut = true
        defer { isLoggingOut = false }
        await appViewModel.logout()
    }
}
