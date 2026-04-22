import Foundation

struct APIClient {
    let baseURL: URL
    private let sessionStore: SessionStore
    private let session: URLSession

    init(
        baseURL: URL = AppConfig.defaultAPIBaseURL,
        sessionStore: SessionStore,
        session: URLSession = .shared
    ) {
        self.baseURL = baseURL
        self.sessionStore = sessionStore
        self.session = session
    }

    func login(username: String, password: String, deviceName: String = AppConfig.sessionDeviceName) async throws -> LoginData {
        let payload = LoginRequest(
            username: username,
            password: password,
            deviceName: deviceName
        )
        var request = try jsonRequest(path: "api/v1/auth/login.php", method: "POST", body: payload, requiresAuth: false)
        request.timeoutInterval = AppConfig.requestTimeout

        let envelope: APIEnvelope<LoginData> = try await perform(request, responseType: LoginData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("登录响应缺少数据"))
        }
        return data
    }

    func logout() async throws {
        var request = try request(path: "api/v1/auth/logout.php", method: "POST", requiresAuth: true)
        request.timeoutInterval = AppConfig.requestTimeout
        let _: APIEnvelope<EmptyPayload> = try await perform(request, responseType: EmptyPayload.self)
    }

    func me() async throws -> MeData {
        let request = try request(path: "api/v1/me.php", method: "GET", requiresAuth: true)
        let envelope: APIEnvelope<MeData> = try await perform(request, responseType: MeData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("用户信息为空"))
        }
        return data
    }

    func scoreOptions() async throws -> ScoreOptionsData {
        let request = try request(path: "api/v1/score/options.php", method: "GET", requiresAuth: true)
        let envelope: APIEnvelope<ScoreOptionsData> = try await perform(request, responseType: ScoreOptionsData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("打分选项为空"))
        }
        return data
    }

    func scoreRecords(page: Int = 1, pageSize: Int = 20) async throws -> ScoreRecordsData {
        let request = try request(
            path: "api/v1/score/records.php",
            method: "GET",
            queryItems: [
                URLQueryItem(name: "page", value: "\(page)"),
                URLQueryItem(name: "page_size", value: "\(pageSize)")
            ],
            requiresAuth: true
        )
        let envelope: APIEnvelope<ScoreRecordsData> = try await perform(request, responseType: ScoreRecordsData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("记录数据为空"))
        }
        return data
    }

    func dormitories(keyword: String, limit: Int = 50) async throws -> DormitoryData {
        let request = try request(
            path: "api/v1/dormitories.php",
            method: "GET",
            queryItems: [
                URLQueryItem(name: "keyword", value: keyword),
                URLQueryItem(name: "limit", value: "\(limit)")
            ],
            requiresAuth: true
        )
        let envelope: APIEnvelope<DormitoryData> = try await perform(request, responseType: DormitoryData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("宿舍列表为空"))
        }
        return data
    }

    func submitScore(
        dormitoryNo: String,
        scoreType: String,
        score: Double,
        images: [Data]
    ) async throws -> SubmitScoreData {
        var builder = MultipartFormDataBuilder()
        builder.addText(name: "dormitory_no", value: dormitoryNo)
        builder.addText(name: "score_type", value: scoreType)
        builder.addText(name: "score", value: ScoreFormatter.text(score))

        for (index, imageData) in images.enumerated() {
            builder.addFile(
                MultipartFilePart(
                    fieldName: "images[]",
                    fileName: "photo_\(index)_\(UUID().uuidString).jpg",
                    mimeType: "image/jpeg",
                    data: imageData
                )
            )
        }

        let body = builder.finalize()
        var request = try request(path: "api/v1/score/submit.php", method: "POST", requiresAuth: true)
        request.timeoutInterval = AppConfig.requestTimeout
        request.httpBody = body
        request.setValue("multipart/form-data; boundary=\(builder.boundary)", forHTTPHeaderField: "Content-Type")

        let envelope: APIEnvelope<SubmitScoreData> = try await perform(request, responseType: SubmitScoreData.self)
        guard let data = envelope.data else {
            throw APIError.missingData(message: envelope.message.ifBlank("上报响应为空"))
        }
        return data
    }

    private func request(
        path: String,
        method: String,
        queryItems: [URLQueryItem] = [],
        requiresAuth: Bool
    ) throws -> URLRequest {
        guard var components = URLComponents(url: try makeURL(path: path), resolvingAgainstBaseURL: false) else {
            throw APIError.invalidURL
        }

        if !queryItems.isEmpty {
            components.queryItems = queryItems
        }

        guard let url = components.url else {
            throw APIError.invalidURL
        }

        var request = URLRequest(url: url)
        request.httpMethod = method
        request.setValue("application/json", forHTTPHeaderField: "Accept")

        if requiresAuth {
            guard let auth = sessionStore.authHeader() else {
                throw APIError.notLoggedIn
            }
            request.setValue(auth, forHTTPHeaderField: "Authorization")
        }

        return request
    }

    private func jsonRequest<Body: Encodable>(
        path: String,
        method: String,
        body: Body,
        requiresAuth: Bool
    ) throws -> URLRequest {
        var request = try request(path: path, method: method, requiresAuth: requiresAuth)
        let encoder = JSONEncoder()
        encoder.keyEncodingStrategy = .convertToSnakeCase
        request.httpBody = try encoder.encode(body)
        request.setValue("application/json; charset=utf-8", forHTTPHeaderField: "Content-Type")
        return request
    }

    private func makeURL(path: String) throws -> URL {
        let normalized = path.hasPrefix("/") ? String(path.dropFirst()) : path
        guard let url = URL(string: normalized, relativeTo: baseURL)?.absoluteURL else {
            throw APIError.invalidURL
        }
        return url
    }

    private func perform<T: Decodable>(
        _ request: URLRequest,
        responseType: T.Type
    ) async throws -> APIEnvelope<T> {
        let data: Data
        let response: URLResponse

        do {
            (data, response) = try await session.data(for: request)
        } catch {
            throw APIError.network(error: error)
        }

        guard let httpResponse = response as? HTTPURLResponse else {
            throw APIError.invalidResponse
        }

        let statusCode = httpResponse.statusCode
        let rawBody = String(data: data, encoding: .utf8) ?? ""

        let envelope: APIEnvelope<T>
        do {
            let decoder = JSONDecoder()
            decoder.keyDecodingStrategy = .convertFromSnakeCase
            envelope = try decoder.decode(APIEnvelope<T>.self, from: data)
        } catch {
            if statusCode == 401 {
                throw APIError.unauthorized(message: "登录已过期，请重新登录")
            }
            if !(200...299).contains(statusCode) {
                throw APIError.server(
                    message: "请求失败(\(statusCode))",
                    statusCode: statusCode,
                    rawBody: rawBody
                )
            }
            throw APIError.decodingFailed
        }

        if statusCode == 401 {
            throw APIError.unauthorized(message: envelope.message.ifBlank("登录已过期，请重新登录"))
        }

        if !(200...299).contains(statusCode) || !envelope.success {
            throw APIError.server(
                message: envelope.message.ifBlank("请求失败(\(statusCode))"),
                statusCode: statusCode,
                rawBody: rawBody
            )
        }

        return envelope
    }
}

private extension String {
    func ifBlank(_ fallback: String) -> String {
        trimmingCharacters(in: .whitespacesAndNewlines).isEmpty ? fallback : self
    }
}
