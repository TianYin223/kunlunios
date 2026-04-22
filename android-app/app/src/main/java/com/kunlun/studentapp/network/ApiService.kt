package com.kunlun.studentapp.network

import com.kunlun.studentapp.data.model.ApiEnvelope
import com.kunlun.studentapp.data.model.DormitoryData
import com.kunlun.studentapp.data.model.LoginData
import com.kunlun.studentapp.data.model.LoginRequest
import com.kunlun.studentapp.data.model.MeData
import com.kunlun.studentapp.data.model.ScoreOptionsData
import com.kunlun.studentapp.data.model.ScoreRecordsData
import com.kunlun.studentapp.data.model.SubmitScoreData
import okhttp3.MultipartBody
import okhttp3.RequestBody
import retrofit2.http.Body
import retrofit2.http.GET
import retrofit2.http.Header
import retrofit2.http.Multipart
import retrofit2.http.POST
import retrofit2.http.Part
import retrofit2.http.Query

interface ApiService {
    @POST("api/v1/auth/login.php")
    suspend fun login(
        @Body request: LoginRequest
    ): ApiEnvelope<LoginData>

    @POST("api/v1/auth/logout.php")
    suspend fun logout(
        @Header("Authorization") authorization: String
    ): ApiEnvelope<Any?>

    @GET("api/v1/me.php")
    suspend fun me(
        @Header("Authorization") authorization: String
    ): ApiEnvelope<MeData>

    @GET("api/v1/dormitories.php")
    suspend fun dormitories(
        @Header("Authorization") authorization: String,
        @Query("keyword") keyword: String,
        @Query("limit") limit: Int = 50
    ): ApiEnvelope<DormitoryData>

    @GET("api/v1/score/options.php")
    suspend fun scoreOptions(
        @Header("Authorization") authorization: String
    ): ApiEnvelope<ScoreOptionsData>

    @Multipart
    @POST("api/v1/score/submit.php")
    suspend fun submitScore(
        @Header("Authorization") authorization: String,
        @Part("dormitory_no") dormitoryNo: RequestBody,
        @Part("score_type") scoreType: RequestBody,
        @Part("score") score: RequestBody,
        @Part images: List<MultipartBody.Part>
    ): ApiEnvelope<SubmitScoreData>

    @GET("api/v1/score/records.php")
    suspend fun scoreRecords(
        @Header("Authorization") authorization: String,
        @Query("page") page: Int = 1,
        @Query("page_size") pageSize: Int = 20
    ): ApiEnvelope<ScoreRecordsData>
}

