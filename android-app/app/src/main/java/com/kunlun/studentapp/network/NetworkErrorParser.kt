package com.kunlun.studentapp.network

import org.json.JSONObject
import retrofit2.HttpException

object NetworkErrorParser {
    fun toMessage(error: Throwable): String {
        return when (error) {
            is HttpException -> {
                val body = error.response()?.errorBody()?.string().orEmpty()
                if (body.isNotBlank()) {
                    try {
                        val json = JSONObject(body)
                        json.optString("message").ifBlank { "请求失败(${error.code()})" }
                    } catch (_: Exception) {
                        "请求失败(${error.code()})"
                    }
                } else {
                    "请求失败(${error.code()})"
                }
            }

            is IllegalStateException -> error.message ?: "请先登录"
            else -> error.message ?: "网络异常，请稍后重试"
        }
    }
}
