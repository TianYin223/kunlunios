import AVFoundation
import Photos
import PhotosUI
import SwiftUI
import UIKit

private enum SubmitScoreType: String {
    case subtract
    case add
}

private struct SelectedPhoto: Identifiable {
    let id = UUID()
    let image: UIImage
}

struct SubmitScoreView: View {
    @EnvironmentObject private var appViewModel: AppViewModel

    @State private var dormitoryNo = ""
    @State private var scoreType: SubmitScoreType = .subtract
    @State private var scoreOptions: [Double] = [0.5, 1.0, 1.5, 2.0]
    @State private var selectedScore = 0.5

    @State private var selectedPhotos: [SelectedPhoto] = []
    @State private var pickerItems: [PhotosPickerItem] = []
    @State private var showCamera = false

    @State private var isLoadingOptions = false
    @State private var isSubmitting = false

    @State private var submitMessage = ""
    @State private var submitSuccess = false
    @State private var debugMessage = ""

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 12) {
                    VStack(alignment: .leading, spacing: 12) {
                        Text("打分上报")
                            .font(.system(size: 34, weight: .bold))
                        Text("现场检查打分，流程简洁稳定。")
                            .font(.footnote)
                            .foregroundStyle(.secondary)

                        TextField("宿舍号", text: $dormitoryNo)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .padding()
                            .background(Color(.systemBackground))
                            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                            .overlay(
                                RoundedRectangle(cornerRadius: 12, style: .continuous)
                                    .stroke(Color(.systemGray4), lineWidth: 1)
                            )
                            .padding(.top, 2)

                        VStack(alignment: .leading, spacing: 10) {
                            Text("打分类型")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)

                            Picker("打分类型", selection: $scoreType) {
                                Text("减分").tag(SubmitScoreType.subtract)
                                Text("加分").tag(SubmitScoreType.add)
                            }
                            .pickerStyle(.segmented)
                        }
                        .padding(.top, 2)

                        VStack(alignment: .leading, spacing: 10) {
                            Text(scoreType == .add ? "加分固定为 0 分" : "扣分值")
                                .font(.subheadline)
                                .foregroundStyle(.secondary)

                            if scoreType == .add {
                                Text("0")
                                    .font(.title3.bold())
                                    .padding(10)
                                    .frame(minWidth: 80)
                                    .background(Color(.systemGray6))
                                    .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                            } else {
                                LazyVGrid(
                                    columns: [GridItem(.adaptive(minimum: 70), spacing: 10)],
                                    spacing: 10
                                ) {
                                    ForEach(scoreOptions, id: \.self) { item in
                                        ScoreOptionChip(
                                            title: ScoreFormatter.text(item),
                                            isSelected: abs(item - selectedScore) < 0.0001
                                        ) {
                                            selectedScore = item
                                        }
                                    }
                                }
                            }
                        }

                        Text(photoText)
                            .font(.body)
                            .foregroundStyle(.secondary)
                            .padding(.top, 4)

                        Button("拍照") {
                            openCamera()
                        }
                        .frame(maxWidth: .infinity, minHeight: 48)
                        .buttonStyle(.borderedProminent)
                        .tint(Color(red: 0.07, green: 0.10, blue: 0.22))
                        .disabled(isSubmitting)

                        PhotosPicker(
                            selection: $pickerItems,
                            maxSelectionCount: max(0, AppConfig.maxPhotoCount - selectedPhotos.count),
                            matching: .images
                        ) {
                            Text("相册选择")
                                .frame(maxWidth: .infinity, minHeight: 48)
                        }
                        .buttonStyle(.bordered)
                        .disabled(isSubmitting || selectedPhotos.count >= AppConfig.maxPhotoCount)
                        .onChange(of: pickerItems) { newItems in
                            Task {
                                await handlePickerItems(newItems)
                                pickerItems = []
                            }
                        }

                        Button("清空照片") {
                            selectedPhotos.removeAll()
                            showMessage("已清空照片", success: true)
                        }
                        .frame(maxWidth: .infinity, minHeight: 48)
                        .buttonStyle(.bordered)
                        .disabled(isSubmitting || selectedPhotos.isEmpty)

                        Text("每次上报需拍摄 4-10 张现场照片。")
                            .font(.body)
                            .foregroundStyle(.secondary)

                        Button {
                            Task { await submit() }
                        } label: {
                            if isSubmitting {
                                ProgressView()
                                    .progressViewStyle(.circular)
                                    .frame(maxWidth: .infinity, minHeight: 50)
                            } else {
                                Text("提交上报")
                                    .frame(maxWidth: .infinity, minHeight: 50)
                                    .font(.headline)
                            }
                        }
                        .buttonStyle(.borderedProminent)
                        .tint(Color(red: 0.07, green: 0.10, blue: 0.22))
                        .disabled(isSubmitting || isLoadingOptions)

                        if isLoadingOptions {
                            ProgressView()
                                .padding(.top, 4)
                        }

                        if !submitMessage.isEmpty {
                            Text(submitMessage)
                                .foregroundStyle(submitSuccess ? Color.green : Color.red)
                                .font(.body)
                        }

#if DEBUG
                        if !debugMessage.isEmpty {
                            Text(debugMessage)
                                .font(.caption)
                                .foregroundStyle(.red)
                                .padding(.top, 4)
                        }
#endif
                    }
                    .padding(20)
                    .background(Color.white)
                    .clipShape(RoundedRectangle(cornerRadius: 18, style: .continuous))
                    .overlay(
                        RoundedRectangle(cornerRadius: 18, style: .continuous)
                            .stroke(Color(.systemGray5), lineWidth: 1)
                    )
                }
                .padding(16)
            }
            .background(Color(.systemGray6))
            .navigationTitle("打分上报")
            .navigationBarTitleDisplayMode(.inline)
            .task {
                if scoreOptions.isEmpty {
                    scoreOptions = [0.5, 1.0, 1.5, 2.0]
                    selectedScore = scoreOptions[0]
                }
                await loadScoreOptions()
            }
        }
        .sheet(isPresented: $showCamera) {
            CameraImagePicker { image in
                guard selectedPhotos.count < AppConfig.maxPhotoCount else {
                    showMessage("最多上传 10 张照片", success: false)
                    return
                }
                selectedPhotos.append(SelectedPhoto(image: image))
            }
        }
    }

    private var photoText: String {
        if selectedPhotos.isEmpty {
            return "照片：未拍摄"
        }
        return "照片：已拍摄 \(selectedPhotos.count) 张"
    }

    private func openCamera() {
        guard selectedPhotos.count < AppConfig.maxPhotoCount else {
            showMessage("最多上传 10 张照片", success: false)
            return
        }

        guard UIImagePickerController.isSourceTypeAvailable(.camera) else {
            showMessage("当前设备不支持拍照", success: false)
            return
        }

        let status = AVCaptureDevice.authorizationStatus(for: .video)
        switch status {
        case .authorized:
            showCamera = true
        case .notDetermined:
            AVCaptureDevice.requestAccess(for: .video) { granted in
                DispatchQueue.main.async {
                    if granted {
                        showCamera = true
                    } else {
                        showMessage("请先授予相机权限后再拍照", success: false)
                    }
                }
            }
        default:
            showMessage("请先授予相机权限后再拍照", success: false)
        }
    }

    private func handlePickerItems(_ items: [PhotosPickerItem]) async {
        guard !items.isEmpty else { return }

        let remaining = AppConfig.maxPhotoCount - selectedPhotos.count
        guard remaining > 0 else {
            showMessage("最多上传 10 张照片", success: false)
            return
        }

        let targetItems = Array(items.prefix(remaining))
        var added = 0

        for item in targetItems {
            guard let data = try? await item.loadTransferable(type: Data.self),
                  let image = UIImage(data: data) else {
                continue
            }
            selectedPhotos.append(SelectedPhoto(image: image))
            added += 1
        }

        let hasOverflow = items.count > remaining
        let hasReadFailure = added < targetItems.count
        if added == 0 {
            showMessage("从相册读取照片失败，请重试", success: false)
            return
        }

        if hasOverflow || hasReadFailure {
            showMessage("已添加 \(added) 张，部分照片未加入（超出上限或读取失败）", success: false)
        } else {
            showMessage("已从相册添加 \(added) 张照片", success: true)
        }
    }

    private func loadScoreOptions() async {
        isLoadingOptions = true
        defer { isLoadingOptions = false }

        do {
            let options = try await appViewModel.apiClient.scoreOptions()
            let values = options.scoreOptions.isEmpty ? [0.5, 1.0, 1.5, 2.0] : options.scoreOptions
            scoreOptions = values
            if !values.contains(where: { abs($0 - selectedScore) < 0.0001 }) {
                selectedScore = values[0]
            }
        } catch let error as APIError {
            if case .unauthorized = error {
                appViewModel.handleUnauthorized(message: error.errorDescription ?? "登录已过期，请重新登录")
                return
            }
            showMessage(error.errorDescription ?? "加载选项失败", success: false)
        } catch {
            showMessage(error.localizedDescription, success: false)
        }
    }

    private func submit() async {
        let room = dormitoryNo.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !room.isEmpty else {
            showMessage("请输入宿舍号", success: false)
            return
        }

        guard selectedPhotos.count >= AppConfig.minPhotoCount,
              selectedPhotos.count <= AppConfig.maxPhotoCount else {
            showMessage("请先拍摄 4-10 张照片再提交", success: false)
            return
        }

        isSubmitting = true
        defer { isSubmitting = false }

        let scoreValue = scoreType == .add ? 0.0 : selectedScore
        do {
            let uploadImages = try selectedPhotos.map {
                try ImageCompressor.uploadReadyJPEGData(from: $0.image)
            }

            let result = try await appViewModel.apiClient.submitScore(
                dormitoryNo: room,
                scoreType: scoreType.rawValue,
                score: scoreValue,
                images: uploadImages
            )

            let signed = result.signedScore
            let scoreText = signed >= 0 ? "+\(ScoreFormatter.text(signed))" : ScoreFormatter.text(signed)
            showMessage("上报成功：\(room) \(scoreText)", success: true)
            dormitoryNo = ""
            selectedPhotos.removeAll()
            debugMessage = ""
        } catch let apiError as APIError {
            if case .unauthorized = apiError {
                appViewModel.handleUnauthorized(message: apiError.errorDescription ?? "登录已过期，请重新登录")
                return
            }
            showMessage(apiError.errorDescription ?? "打分失败，请稍后重试", success: false)
#if DEBUG
            debugMessage = buildDebugText(
                error: apiError,
                room: room,
                scoreType: scoreType.rawValue,
                scoreValue: scoreValue,
                photoCount: selectedPhotos.count
            )
#endif
        } catch {
            showMessage(error.localizedDescription, success: false)
#if DEBUG
            debugMessage = """
            [DEBUG submit throwable]
            endpoint=\(AppConfig.defaultAPIBaseURL.absoluteString)api/v1/score/submit.php
            type=\(String(describing: type(of: error)))
            message=\(error.localizedDescription)
            dormitory_no=\(room)
            score_type=\(scoreType.rawValue)
            score=\(scoreValue.cleanScoreText)
            photo_count=\(selectedPhotos.count)
            """
#endif
        }
    }

    private func buildDebugText(
        error: APIError,
        room: String,
        scoreType: String,
        scoreValue: Double,
        photoCount: Int
    ) -> String {
        """
        [DEBUG submit]
        endpoint=\(AppConfig.defaultAPIBaseURL.absoluteString)api/v1/score/submit.php
        dormitory_no=\(room)
        score_type=\(scoreType)
        score=\(ScoreFormatter.text(scoreValue))
        photo_count=\(photoCount)
        \(error.debugText)
        """
    }

    private func showMessage(_ text: String, success: Bool) {
        submitMessage = text
        submitSuccess = success
        if success {
            debugMessage = ""
        }
    }
}
