package com.kunlun.studentapp.ui.main

import android.content.Intent
import android.os.Bundle
import android.widget.Toast
import androidx.appcompat.app.AppCompatActivity
import androidx.lifecycle.lifecycleScope
import com.google.android.material.bottomnavigation.BottomNavigationView
import com.kunlun.studentapp.R
import com.kunlun.studentapp.data.AppRepository
import com.kunlun.studentapp.data.SessionManager
import com.kunlun.studentapp.network.ApiClient
import com.kunlun.studentapp.ui.login.LoginActivity
import com.kunlun.studentapp.ui.profile.ProfileFragment
import com.kunlun.studentapp.ui.submit.SubmitScoreFragment
import kotlinx.coroutines.launch

class MainActivity : AppCompatActivity(), ProfileFragment.Callbacks {
    private lateinit var bottomNav: BottomNavigationView
    private lateinit var sessionManager: SessionManager
    private lateinit var repository: AppRepository

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        setContentView(R.layout.activity_main)

        sessionManager = SessionManager(applicationContext)
        if (!sessionManager.isLoggedIn()) {
            openLogin()
            return
        }
        repository = AppRepository(ApiClient.apiService, sessionManager)

        bottomNav = findViewById(R.id.bottomNav)
        bottomNav.setOnItemSelectedListener { item ->
            when (item.itemId) {
                R.id.nav_submit -> {
                    showSubmitFragment()
                    true
                }

                R.id.nav_profile -> {
                    showProfileFragment()
                    true
                }

                else -> false
            }
        }

        if (savedInstanceState == null) {
            bottomNav.selectedItemId = R.id.nav_submit
        }
    }

    fun appRepository(): AppRepository = repository

    override fun onLogoutRequested() {
        lifecycleScope.launch {
            try {
                repository.logout()
            } catch (_: Throwable) {
                // Ignore and continue local logout.
            } finally {
                sessionManager.clear()
                Toast.makeText(this@MainActivity, getString(R.string.msg_logged_out), Toast.LENGTH_SHORT).show()
                openLogin()
            }
        }
    }

    fun forceRelogin(message: String = "") {
        sessionManager.clear()
        val toastMessage = if (message.isBlank()) getString(R.string.msg_session_expired) else message
        Toast.makeText(this, toastMessage, Toast.LENGTH_SHORT).show()
        openLogin()
    }

    private fun showSubmitFragment() {
        supportFragmentManager.beginTransaction()
            .replace(R.id.mainContainer, SubmitScoreFragment.newInstance())
            .commit()
    }

    private fun showProfileFragment() {
        supportFragmentManager.beginTransaction()
            .replace(R.id.mainContainer, ProfileFragment.newInstance())
            .commit()
    }

    private fun openLogin() {
        startActivity(Intent(this, LoginActivity::class.java))
        finish()
    }
}
