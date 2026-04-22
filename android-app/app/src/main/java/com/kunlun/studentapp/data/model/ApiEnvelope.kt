package com.kunlun.studentapp.data.model

data class ApiEnvelope<T>(
    val success: Boolean,
    val message: String,
    val data: T?
)

