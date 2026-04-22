package com.kunlun.studentapp.data

import android.content.Context
import com.kunlun.studentapp.data.model.UserInfo

class SessionManager(context: Context) {
    private val prefs = context.getSharedPreferences(PREF_NAME, Context.MODE_PRIVATE)

    fun getToken(): String = prefs.getString(KEY_TOKEN, "").orEmpty()

    fun isLoggedIn(): Boolean = getToken().isNotBlank()

    fun saveSession(token: String, user: UserInfo) {
        prefs.edit()
            .putString(KEY_TOKEN, token)
            .putInt(KEY_USER_ID, user.id)
            .putString(KEY_USERNAME, user.username)
            .putString(KEY_REAL_NAME, user.real_name)
            .putString(KEY_ROLE, user.role)
            .apply()
    }

    fun clear() {
        prefs.edit().clear().apply()
    }

    fun currentDisplayName(): String {
        return prefs.getString(KEY_REAL_NAME, "").orEmpty()
    }

    companion object {
        private const val PREF_NAME = "student_score_session"
        private const val KEY_TOKEN = "token"
        private const val KEY_USER_ID = "user_id"
        private const val KEY_USERNAME = "username"
        private const val KEY_REAL_NAME = "real_name"
        private const val KEY_ROLE = "role"
    }
}

