import Combine
import Foundation

@MainActor
final class AppViewModel: ObservableObject {
    @Published private(set) var isLoggedIn: Bool
    @Published var loginErrorMessage = ""
    @Published var entryMessage = ""
    @Published var isLoggingIn = false

    let sessionStore: SessionStore
    let apiClient: APIClient

    private var cancellables = Set<AnyCancellable>()

    init(
        sessionStore: SessionStore = SessionStore(),
        baseURL: URL = AppConfig.defaultAPIBaseURL
    ) {
        self.sessionStore = sessionStore
        apiClient = APIClient(baseURL: baseURL, sessionStore: sessionStore)
        isLoggedIn = sessionStore.isLoggedIn

        sessionStore.$token
            .receive(on: RunLoop.main)
            .sink { [weak self] token in
                self?.isLoggedIn = !token.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
            }
            .store(in: &cancellables)
    }

    func login(username: String, password: String) async {
        let usernameValue = username.trimmingCharacters(in: .whitespacesAndNewlines)
        let passwordValue = password.trimmingCharacters(in: .whitespacesAndNewlines)

        guard !usernameValue.isEmpty, !passwordValue.isEmpty else {
            loginErrorMessage = "请输入用户名和密码"
            return
        }

        isLoggingIn = true
        loginErrorMessage = ""

        defer { isLoggingIn = false }

        do {
            let result = try await apiClient.login(
                username: usernameValue,
                password: passwordValue,
                deviceName: AppConfig.sessionDeviceName
            )
            sessionStore.saveSession(token: result.token, user: result.user)
            entryMessage = ""
        } catch {
            loginErrorMessage = (error as? APIError)?.errorDescription ?? error.localizedDescription
        }
    }

    func logout() async {
        do {
            try await apiClient.logout()
        } catch {
            // Even if server logout fails, clear local session.
        }
        sessionStore.clear()
        entryMessage = "已退出登录"
    }

    func handleUnauthorized(message: String = "登录已过期，请重新登录") {
        sessionStore.clear()
        entryMessage = message
    }
}

