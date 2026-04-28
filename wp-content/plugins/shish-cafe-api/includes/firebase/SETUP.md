# Firebase Configuration Guide

## Setup Options

### Option 1: Using Config File (Recommended)

1. **Get your Firebase JSON key** from Firebase Console:
   - Project Settings → Service Accounts → Generate New Private Key

2. **Replace the placeholder** in `/includes/firebase/firebase-config.json`:
   - Open the file
   - Replace with your actual Firebase service account JSON
   - Save the file

The plugin will automatically load this file on startup.

---

### Option 2: Using WordPress Config Constant

Add this to your `wp-config.php` file before the line `/* That's all, stop editing! */`:

```php
// Firebase Service Account Key
define('SHISH_CAFE_FIREBASE_KEY', '{
  "type": "service_account",
  "project_id": "your-project-id",
  "private_key_id": "...",
  "private_key": "-----BEGIN PRIVATE KEY-----\n...\n-----END PRIVATE KEY-----\n",
  "client_email": "...",
  "client_id": "...",
  "auth_uri": "https://accounts.google.com/o/oauth2/auth",
  "token_uri": "https://oauth2.googleapis.com/token",
  "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
  "client_x509_cert_url": "...",
  "universe_domain": "googleapis.com"
}');
```

---

## How It Works

When a new order is submitted in WooCommerce:

1. ✅ Automatically captured via `woocommerce_new_order` hook
2. ✅ Sent to Firebase backend with:
   - `order_id`
   - `order_type` (defaults to 'takeaway')
   - `timestamp`
3. ✅ Firebase backend processes the notification
4. ✅ Logged in WordPress debug log

## Testing

Check your WordPress debug log at `/wp-content/debug.log` to see:
- "Order notification sent to Firebase for Order #..."
- Or error messages if Firebase is not configured

## Firebase Backend Structure

Your Firebase will receive data at path:
```
orders/new -> {order_id, order_type, timestamp}
orders/status-updates -> {order_id, old_status, new_status, timestamp}
```
