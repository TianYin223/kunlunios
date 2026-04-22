package com.kunlun.studentapp.ui.login

import android.Manifest
import android.content.Context
import android.content.Intent
import android.content.pm.PackageManager
import android.os.Bundle
import android.os.Build
import android.view.View
import android.widget.ProgressBar
import android.widget.TextView
import android.widget.Toast
import androidx.activity.result.contract.ActivityResultContracts
import androidx.appcompat.app.AppCompatActivity
import androidx.core.content.ContextCompat
import androidx.lifecycle.lifecycleScope
import com.google.android.material.button.MaterialButton
import com.google.android.material.textfield.TextInputEditText
import com.kunlun.studentapp.R
import com.kunlun.studentapp.data.AppRepository
import com.kunlun.studentapp.data.SessionManager
import com.kunlun.studentapp.network.ApiClient
import com.kunlun.studentapp.network.NetworkErrorParser
import com.kunlun.studentapp.ui.main.MainActivity
import kotlinx.coroutines.launch

class LoginActivity : AppCompatActivity() {
    private lateinit var inputUsername: TextInputEditText
    private lateinit var inputPassword: TextInputEditText
    private lateinit var btnLogin: MaterialButton
    private lateinit var loginProgress: ProgressBar
    private lateinit var loginError: TextView

    private lateinit var sessionManager: SessionManager
    private lateinit var repository: AppRepository

    private val initialMediaPermissionLauncher =
        registerForActivityResult(ActivityResultContracts.RequestMultiplePermissions()) { _ ->
            markInitialPermissionPrompted()
        }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_login)

        sessionManager = SessionManager(applicationContext)
        if (sessionManager.isLoggedIn()) {
            openMain()
            return
        }

        repository = AppRepository(ApiClient.apiService, sessionManager)

        inputUsername = findViewById(R.id.inputUsername)
        inputPassword = findViewById(R.id.inputPassword)
        btnLogin = findViewById(R.id.btnLogin)
        loginProgress = findViewById(R.id.loginProgress)
        loginError = findViewById(R.id.loginError)

        requestInitialMediaPermissionsIfNeeded()
        btnLogin.setOnClickListener { doLogin() }
    }

    private fun requestInitialMediaPermissionsIfNeeded() {
        if (isInitialPermissionPrompted()) {
            return
        }

        val missingPermissions = listOf(
            Manifest.permission.CAMERA,
            currentGalleryPermission()
        ).distinct().filterNot(::isPermissionGranted)

        if (missingPermissions.isEmpty()) {
            markInitialPermissionPrompted()
            return
        }

        initialMediaPermissionLauncher.launch(missingPermissions.toTypedArray())
    }

    private fun currentGalleryPermission(): String {
        return if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.TIRAMISU) {
            Manifest.permission.READ_MEDIA_IMAGES
        } else {
            Manifest.permission.READ_EXTERNAL_STORAGE
        }
    }

    private fun isPermissionGranted(permission: String): Boolean {
        return ContextCompat.checkSelfPermission(
            this,
            permission
        ) == PackageManager.PERMISSION_GRANTED
    }

    private fun isInitialPermissionPrompted(): Boolean {
        return permissionPrefs().getBoolean(KEY_INITIAL_MEDIA_PERMISSION_PROMPTED, false)
    }

    private fun markInitialPermissionPrompted() {
        permissionPrefs()
            .edit()
            .putBoolean(KEY_INITIAL_MEDIA_PERMISSION_PROMPTED, true)
            .apply()
    }

    private fun permissionPrefs() =
        getSharedPreferences(PREFS_PERMISSION_NAME, Context.MODE_PRIVATE)

    private fun doLogin() {
        val username = inputUsername.text?.toString()?.trim().orEmpty()
        val password = inputPassword.text?.toString().orEmpty()

        if (username.isBlank() || password.isBlank()) {
            showInlineError(getString(R.string.msg_input_username_password))
            return
        }

        setLoading(true)
        lifecycleScope.launch {
            try {
                val response = repository.login(
                    username = username,
                    password = password,
                    deviceName = "android-native"
                )
                if (response.success && response.data != null) {
                    sessionManager.saveSession(response.data.token, response.data.user)
                    openMain()
                } else {
                    showInlineError(response.message)
                }
            } catch (error: Throwable) {
                showInlineError(NetworkErrorParser.toMessage(error))
            } finally {
                setLoading(false)
            }
        }
    }

    private fun setLoading(loading: Boolean) {
        btnLogin.isEnabled = !loading
        loginProgress.visibility = if (loading) View.VISIBLE else View.GONE
    }

    private fun showInlineError(message: String) {
        loginError.text = message
        loginError.visibility = View.VISIBLE
        Toast.makeText(this, message, Toast.LENGTH_SHORT).show()
    }

    private fun openMain() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }

    companion object {
        private const val PREFS_PERMISSION_NAME = "student_score_permissions"
        private const val KEY_INITIAL_MEDIA_PERMISSION_PROMPTED = "initial_media_permission_prompted"
    }
}
