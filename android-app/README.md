# Android App (User Center + Score Submit)

This native Android project (Kotlin + XML) reuses your existing PHP backend.
No separate app server deployment is required.

## Scope

- Login (`inspector` role only)
- Score submit:
  - Manual dormitory number input
  - Add/Subtract type selection (same rule as web)
  - Camera capture only (no album picker)
  - 4-10 photos required
  - Auto-compress photos before upload to fit backend 5MB limit
- User center:
  - Profile + current settings
  - Recent submit records
- Logout

## API Base URL

Default is already set to:

`https://kl.siyun223.com/`

If your site is under a sub-path, include it and keep trailing slash, for example:

`https://kl.siyun223.com/student/`

## Run

1. Execute DB upgrade script on server DB: `upgrade_api_tokens.sql`
2. Open `android-app` with Android Studio
3. Sync Gradle and run `app`

You can override URL in:

- `android-app/gradle.properties`
- or `android-app/local.properties` (same key `APP_API_BASE_URL`)

