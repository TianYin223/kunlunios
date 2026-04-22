import Foundation

final class SessionStore: ObservableObject {
    @Published private(set) var token: String
    @Published private(set) var currentUser: UserInfo?

    private let defaults: UserDefaults

    private enum Keys {
        static let token = "ios_student_score_token"
        static let currentUser = "ios_student_score_current_user"
    }

    init(defaults: UserDefaults = .standard) {
        self.defaults = defaults
        token = defaults.string(forKey: Keys.token) ?? ""

        if let data = defaults.data(forKey: Keys.currentUser),
           let user = try? JSONDecoder().decode(UserInfo.self, from: data) {
            currentUser = user
        } else {
            currentUser = nil
        }
    }

    var isLoggedIn: Bool {
        !token.trimmingCharacters(in: .whitespacesAndNewlines).isEmpty
    }

    func saveSession(token: String, user: UserInfo) {
        self.token = token
        currentUser = user
        defaults.set(token, forKey: Keys.token)
        if let data = try? JSONEncoder().encode(user) {
            defaults.set(data, forKey: Keys.currentUser)
        }
    }

    func clear() {
        token = ""
        currentUser = nil
        defaults.removeObject(forKey: Keys.token)
        defaults.removeObject(forKey: Keys.currentUser)
    }

    func authHeader() -> String? {
        guard isLoggedIn else { return nil }
        return "Bearer \(token)"
    }
}

