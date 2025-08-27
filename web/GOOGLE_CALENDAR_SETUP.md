# Google Calendar API Setup Guide

## Problem

You're getting a 403 error: "Method doesn't allow unregistered callers" because the Google Calendar API requires proper authentication.

## Solution: Use Service Account Authentication

### Step 1: Create a Service Account

1. Go to [Google Cloud Console](https://console.cloud.google.com/)
2. Select your project (or create a new one)
3. Navigate to "APIs & Services" > "Credentials"
4. Click "Create Credentials" > "Service Account"
5. Fill in the details:
   - **Name**: `calendar-api-service`
   - **Description**: `Service account for Calendar API access`
6. Click "Create and Continue"
7. Skip the optional steps and click "Done"

### Step 2: Enable Google Calendar API

1. Go to "APIs & Services" > "Library"
2. Search for "Google Calendar API"
3. Click on it and press "Enable"

### Step 3: Create and Download Service Account Key

1. Go back to "APIs & Services" > "Credentials"
2. Find your service account and click on it
3. Go to the "Keys" tab
4. Click "Add Key" > "Create new key"
5. Choose "JSON" format
6. Download the JSON file
7. **Rename it to** `service-account-key.json`
8. **Place it in** `config/google/service-account-key.json`

### Step 4: Share Calendar with Service Account

1. Go to [Google Calendar](https://calendar.google.com/)
2. Find your calendar in the left sidebar
3. Click the three dots next to it > "Settings and sharing"
4. Scroll down to "Share with specific people"
5. Click "Add people"
6. Add your service account email (found in the JSON file under `client_email`)
7. Give it "Make changes to events" permission
8. Click "Send"

### Step 5: Update Environment Variables (Optional)

If you want to use environment variables instead of the JSON file, update your `.env` file:

```env
###> google/apiclient ###
GOOGLE_AUTH_CONFIG=%kernel.project_dir%/config/google/service-account-key.json
###< google/apiclient ###
```

### Step 6: Test the Setup

Run your application and try to fetch calendar events. The 403 error should be resolved.

## Alternative: API Key Authentication (Limited)

If you only need to read public calendars, you can use an API key:

1. Go to "APIs & Services" > "Credentials"
2. Click "Create Credentials" > "API Key"
3. Copy the API key
4. Update your `.env` file:
   ```env
   GOOGLE_API_KEY=your-api-key-here
   ```
5. Update `GoogleCalendarService.php` to use API key instead of service account

## Security Notes

- **Never commit** the service account JSON file to version control
- Add `config/google/service-account-key.json` to your `.gitignore`
- The service account should have minimal required permissions
- Consider using Google Cloud Secret Manager for production environments

## Troubleshooting

- **403 Forbidden**: Make sure the calendar is shared with the service account email
- **401 Unauthorized**: Check that the service account key file is valid and in the correct location
- **Calendar not found**: Verify the calendar ID is correct and accessible
