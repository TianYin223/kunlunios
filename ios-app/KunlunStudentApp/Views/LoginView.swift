import AVFoundation
import Photos
import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var appViewModel: AppViewModel
    @AppStorage("ios_initial_media_permission_prompted") private var mediaPermissionPrompted = false

    @State private var username = ""
    @State private var password = ""

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                VStack(alignment: .leading, spacing: 10) {
                    Text("昆仑宿舍学生管理系统")
                        .font(.system(size: 30, weight: .bold))
                    Text("用户中心 + 打分上报")
                        .font(.subheadline)
                        .foregroundStyle(.secondary)

                    VStack(spacing: 12) {
                        TextField("用户名", text: $username)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .padding()
                            .background(Color(.systemBackground))
                            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))

                        SecureField("密码", text: $password)
                            .textInputAutocapitalization(.never)
                            .autocorrectionDisabled()
                            .padding()
                            .background(Color(.systemBackground))
                            .clipShape(RoundedRectangle(cornerRadius: 12, style: .continuous))
                    }
                    .padding(.top, 8)

                    Button {
                        Task { await appViewModel.login(username: username, password: password) }
                    } label: {
                        if appViewModel.isLoggingIn {
                            ProgressView()
                                .progressViewStyle(.circular)
                                .frame(maxWidth: .infinity, minHeight: 48)
                        } else {
                            Text("登录")
                                .font(.headline)
                                .frame(maxWidth: .infinity, minHeight: 48)
                        }
                    }
                    .disabled(appViewModel.isLoggingIn)
                    .buttonStyle(.borderedProminent)
                    .tint(Color(red: 0.07, green: 0.10, blue: 0.22))
                    .padding(.top, 6)

                    if !appViewModel.loginErrorMessage.isEmpty {
                        Text(appViewModel.loginErrorMessage)
                            .font(.callout)
                            .foregroundStyle(.red)
                            .padding(.top, 4)
                    }
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
        .task {
            await requestInitialPermissionsIfNeeded()
            if !appViewModel.entryMessage.isEmpty && appViewModel.loginErrorMessage.isEmpty {
                appViewModel.loginErrorMessage = appViewModel.entryMessage
                appViewModel.entryMessage = ""
            }
        }
    }

    private func requestInitialPermissionsIfNeeded() async {
        guard !mediaPermissionPrompted else { return }
        mediaPermissionPrompted = true

        _ = await withCheckedContinuation { continuation in
            AVCaptureDevice.requestAccess(for: .video) { granted in
                continuation.resume(returning: granted)
            }
        }

        if #available(iOS 14, *) {
            _ = await withCheckedContinuation { continuation in
                PHPhotoLibrary.requestAuthorization(for: .readWrite) { status in
                    continuation.resume(returning: status)
                }
            }
        } else {
            _ = await withCheckedContinuation { continuation in
                PHPhotoLibrary.requestAuthorization { status in
                    continuation.resume(returning: status)
                }
            }
        }
    }
}

