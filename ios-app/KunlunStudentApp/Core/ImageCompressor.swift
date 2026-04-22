import UIKit

enum ImageCompressorError: LocalizedError {
    case encodeFailed
    case tooLargeAfterCompression

    var errorDescription: String? {
        switch self {
        case .encodeFailed:
            return "照片解析失败，请重试"
        case .tooLargeAfterCompression:
            return "单张照片压缩后仍超过 5MB"
        }
    }
}

enum ImageCompressor {
    static func uploadReadyJPEGData(
        from image: UIImage,
        maxBytes: Int = AppConfig.maxUploadBytesPerImage,
        maxDimension: CGFloat = AppConfig.maxUploadImageDimension
    ) throws -> Data {
        let resized = resizeImageIfNeeded(image, maxDimension: maxDimension)

        var quality: CGFloat = 0.92
        let minQuality: CGFloat = 0.45

        guard var data = resized.jpegData(compressionQuality: quality) else {
            throw ImageCompressorError.encodeFailed
        }

        while data.count > maxBytes && quality > minQuality {
            quality -= 0.08
            guard let next = resized.jpegData(compressionQuality: quality) else {
                throw ImageCompressorError.encodeFailed
            }
            data = next
        }

        if data.count > maxBytes {
            throw ImageCompressorError.tooLargeAfterCompression
        }

        return data
    }

    private static func resizeImageIfNeeded(_ image: UIImage, maxDimension: CGFloat) -> UIImage {
        let width = image.size.width
        let height = image.size.height
        let longest = max(width, height)

        guard longest > maxDimension else {
            return image
        }

        let ratio = maxDimension / longest
        let newSize = CGSize(width: width * ratio, height: height * ratio)
        let format = UIGraphicsImageRendererFormat.default()
        format.scale = 1
        format.opaque = true

        let renderer = UIGraphicsImageRenderer(size: newSize, format: format)
        return renderer.image { _ in
            image.draw(in: CGRect(origin: .zero, size: newSize))
        }
    }
}

