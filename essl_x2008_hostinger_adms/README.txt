eSSL X2008 ADMS -> Hostinger Shared Hosting
===============================================

DEVICE:
- Model shown: eSSL X2008
- Serial: NYU7262500437
- Firmware family shown: ZAM70.NF24A
- ADMS mode is available on the device.

HOSTINGER:
1. Create a MySQL database.
2. Import database.sql in phpMyAdmin.
3. Edit config.php with database name/user/password.
4. Upload all files into the website's public_html.
5. Make sure HTTPS is enabled for the domain.

IMPORTANT:
The device is configured for ADMS HTTP push. Shared hosting cannot normally expose
a custom server listener such as port 8081. Therefore this package uses normal web
hosting URLs and Apache rewrite rules:
  /iclock/cdata
  /iclock/getrequest

Try first with:
  https://YOUR-DOMAIN/iclock/getrequest?SN=NYU7262500437

If the device firmware cannot POST ADMS over HTTPS/443, a VPS or vendor cloud/relay
will be required. Do NOT open a random 8081 port on shared hosting; it is not a VPS.

DEVICE SETTINGS:
- Server Mode: ADMS
- If "Enable Domain Name" is ON, use your domain if the firmware supports HTTPS.
- If the firmware exposes Server Port, use the web service port supported by the
  device/hosting. For standard HTTPS this is 443.
- Proxy: OFF
- Device must have internet access.

ATTENDANCE:
When a user punches face/fingerprint, the device should POST ATTLOG data to
/iclock/cdata. The receiver stores it in essl_attendance.

PP SOFTWARE:
Your PP software can read the same MySQL table, or consume:
  /api/attendance?from=YYYY-MM-DD%2000:00:00&to=YYYY-MM-DD%2023:59:59
with header:
  X-API-Key: CHANGE_THIS_API_KEY

For production, replace the demo API key and ideally map the eSSL employee PIN
to your PP software employee code.

SECURITY:
- Do not store face/fingerprint templates unless genuinely required.
- Keep database credentials outside public access where Hostinger permits.
- Use HTTPS.
- Restrict/rotate API keys.
