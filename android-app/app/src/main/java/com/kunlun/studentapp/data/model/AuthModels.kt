package com.kunlun.studentapp.data.model

data class LoginRequest(
    val username: String,
    val password: String,
    val device_name: String
)

data class LoginData(
    val token: String,
    val token_type: String,
    val expires_at: String,
    val user: UserInfo
)

