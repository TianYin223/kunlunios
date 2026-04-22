import Foundation

struct APIEnvelope<T: Decodable>: Decodable {
    let success: Bool
    let message: String
    let data: T?
}

struct EmptyPayload: Decodable {}

