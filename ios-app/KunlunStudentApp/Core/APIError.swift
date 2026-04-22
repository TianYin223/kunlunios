import Foundation

enum APIError: LocalizedError {
    case invalidURL
    case invalidResponse
    case notLoggedIn
    case missingData(message: String)
    case unauthorized(message: String)
    case server(message: String, statusCode: Int, rawBody: String)
    case decodingFailed
    case network(error: Error)

    var errorDescription: String? {
        switch self {
        case .invalidURL:
            return "接口地址无效"
        case .invalidResponse:
            return "服务器响应异常"
        case .notLoggedIn:
            return "未登录，请先登录"
        case let .missingData(message):
            return message
        case let .unauthorized(message):
            return message.isEmpty ? "登录已过期，请重新登录" : message
        case let .server(message, _, _):
            return message.isEmpty ? "请求失败，请稍后重试" : message
        case .decodingFailed:
            return "服务器返回格式异常"
        case let .network(error):
            let text = error.localizedDescription.trimmingCharacters(in: .whitespacesAndNewlines)
            return text.isEmpty ? "网络异常，请稍后重试" : text
        }
    }

    var debugText: String {
        switch self {
        case .invalidURL:
            return "invalid_url"
        case .invalidResponse:
            return "invalid_response"
        case .notLoggedIn:
            return "not_logged_in"
        case let .missingData(message):
            return "missing_data: \(message)"
        case let .unauthorized(message):
            return "unauthorized: \(message)"
        case let .server(message, statusCode, rawBody):
            return """
            server_error
            status_code=\(statusCode)
            message=\(message)
            raw_body=\(rawBody)
            """
        case .decodingFailed:
            return "decoding_failed"
        case let .network(error):
            return "network_error: \(error)"
        }
    }
}

