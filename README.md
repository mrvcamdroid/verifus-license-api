# License Server API for Verifus App

This is a simple PHP-based license server for Verifus/FoxCam Android App.

## 📡 API Endpoints

### `POST /verify.php`
Activates a license key for a device.

**Request Body (JSON):**
```json
{
  "key": "TEST-1234-ABCD-5678",
  "android_id": "device_unique_id",
  "device_model": "Phone Model"
}