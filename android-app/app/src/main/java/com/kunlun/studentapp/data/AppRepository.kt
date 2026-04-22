package com.kunlun.studentapp.data

import com.kunlun.studentapp.data.model.ApiEnvelope
import com.kunlun.studentapp.data.model.DormitoryData
import com.kunlun.studentapp.data.model.LoginData
import com.kunlun.studentapp.data.model.LoginRequest
import com.kunlun.studentapp.data.model.MeData
import com.kunlun.studentapp.data.model.ScoreOptionsData
import com.kunlun.studentapp.data.model.ScoreRecordsData
import com.kunlun.studentapp.data.model.SubmitScoreData
import com.kunlun.studentapp.network.ApiService
import okhttp3.MultipartBody
import okhttp3.RequestBody

class AppRepository(
    private val apiService: ApiService,
    private val sessionManager: SessionManager
) {
    suspend fun login(username: String, password: String, deviceName: String): ApiEnvelope<LoginData> {
        return apiService.login(
            LoginRequest(
                username = username,
                password = password,
                device_name = deviceName
            )
        )
    }

    suspend fun logout(): ApiEnvelope<Any?> {
        return apiService.logout(authHeader())
    }

    suspend fun me(): ApiEnvelope<MeData> {
        return apiService.me(authHeader())
    }

    suspend fun dormitories(keyword: String): ApiEnvelope<DormitoryData> {
        return apiService.dormitories(authHeader(), keyword)
    }

    suspend fun scoreOptions(): ApiEnvelope<ScoreOptionsData> {
        return apiService.scoreOptions(authHeader())
    }

    suspend fun submitScore(
        dormitoryNo: RequestBody,
        scoreType: RequestBody,
        score: RequestBody,
        images: List<MultipartBody.Part>
    ): ApiEnvelope<SubmitScoreData> {
        return apiService.submitScore(
            authorization = authHeader(),
            dormitoryNo = dormitoryNo,
            scoreType = scoreType,
            score = score,
            images = images
        )
    }

    suspend fun scoreRecords(page: Int = 1, pageSize: Int = 20): ApiEnvelope<ScoreRecordsData> {
        return apiService.scoreRecords(authHeader(), page, pageSize)
    }

    private fun authHeader(): String {
        val token = sessionManager.getToken()
        check(token.isNotBlank()) { "未登录，请先登录" }
        return "Bearer $token"
    }
}
