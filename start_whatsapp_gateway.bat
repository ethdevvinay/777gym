@echo off
TITLE THE CLUB 777 - Free & Open-Source WhatsApp Gateway [Anti-Ban Protected]
COLOR 0A
CLS

echo =====================================================================
echo       THE CLUB 777 - FREE & OPEN-SOURCE WHATSAPP GATEWAY
echo       100%% Free | Zero Subscription Fee | Anti-Ban Protected
echo =====================================================================
echo.

:: Check Node.js
where node >nul 2>nul
if %errorlevel% neq 0 (
    COLOR 0C
    echo [ERROR] Node.js is NOT found on your system!
    echo Please install Node.js from https://nodejs.org/ (LTS version).
    echo After installing, restart this script.
    echo.
    pause
    exit /b 1
)

echo [OK] Node.js detected:
node -v
echo.

:: Navigate to gateway folder
cd /d "%~dp0whatsapp_gateway"

:: Check package.json
if not exist "package.json" (
    COLOR 0C
    echo [ERROR] Cannot find package.json in %~dp0whatsapp_gateway
    pause
    exit /b 1
)

:: Check node_modules
if not exist "node_modules" (
    echo [INFO] Installing required dependencies (one-time setup)...
    call npm install
    if %errorlevel% neq 0 (
        COLOR 0C
        echo [ERROR] Failed to install npm dependencies.
        pause
        exit /b 1
    )
)

echo [STARTING] Launching WhatsApp Gateway Service on Port 3001...
echo ---------------------------------------------------------------------
echo 1. Open your Gym Software: http://localhost/GYM/index.php?page=communication
echo 2. Go to WhatsApp Settings to scan the QR Code from your phone.
echo 3. To scan: Open WhatsApp on phone -> Settings -> Linked Devices -> Link a Device.
echo 4. Keep this black terminal window OPEN in background for automated messages & PDFs.
echo ---------------------------------------------------------------------
echo.

:run_loop
node server.js
echo.
echo [WARNING] WhatsApp Gateway stopped. Restarting in 5 seconds...
timeout /t 5 /nobreak >nul
goto run_loop
