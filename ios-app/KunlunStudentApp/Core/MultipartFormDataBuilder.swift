import Foundation

struct MultipartFilePart {
    let fieldName: String
    let fileName: String
    let mimeType: String
    let data: Data
}

struct MultipartFormDataBuilder {
    let boundary: String
    private(set) var body = Data()

    init(boundary: String = "Boundary-\(UUID().uuidString)") {
        self.boundary = boundary
    }

    mutating func addText(name: String, value: String) {
        body.appendString("--\(boundary)\r\n")
        body.appendString("Content-Disposition: form-data; name=\"\(name)\"\r\n\r\n")
        body.appendString("\(value)\r\n")
    }

    mutating func addFile(_ part: MultipartFilePart) {
        body.appendString("--\(boundary)\r\n")
        body.appendString(
            "Content-Disposition: form-data; name=\"\(part.fieldName)\"; filename=\"\(part.fileName)\"\r\n"
        )
        body.appendString("Content-Type: \(part.mimeType)\r\n\r\n")
        body.append(part.data)
        body.appendString("\r\n")
    }

    mutating func finalize() -> Data {
        body.appendString("--\(boundary)--\r\n")
        return body
    }
}

private extension Data {
    mutating func appendString(_ value: String) {
        if let data = value.data(using: .utf8) {
            append(data)
        }
    }
}

