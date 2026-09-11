@echo off
title vibe.Sınav Assessment System - LAN Multi-PC Server
color 0E
cls

echo =====================================================================
echo    vibe.Sınav ASSESSMENT SYSTEM
echo    [LAN MULTI-COMPUTER HOST SERVER]
echo =====================================================================
echo.

:: Detect PHP binary
set "PHP_BIN=php"
where php >nul 2>nul
if %errorlevel% neq 0 (
    if exist "C:\xampp\php\php.exe" (
        set "PHP_BIN=C:\xampp\php\php.exe"
    ) else (
        echo [ERROR] PHP was not found on your system!
        echo Please ensure PHP or XAMPP is installed.
        pause
        exit /b 1
    )
)

:: Check and start MySQL service/process if not running
tasklist /FI "IMAGENAME eq mysqld.exe" 2>NUL | find /I /N "mysqld.exe">NUL
if "%ERRORLEVEL%"=="0" (
    echo [OK] MySQL Database service is running.
) else (
    echo [INFO] Starting MySQL Database background process...
    if exist "C:\xampp\mysql\bin\mysqld.exe" (
        start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone >nul 2>nul
        timeout /t 2 /nobreak >nul
    ) else (
        echo [WARN] MySQL process not found. Ensure MySQL is running on port 3306.
    )
)

echo.
echo ---------------------------------------------------------------------
echo   YOUR LOCAL IP ADDRESS (IPv4):
for /f "tokens=4" %%a in ('route print ^| find " 0.0.0.0 " ^| find /v "0.0.0.0   0.0.0.0"') do (
    echo   Host Server IPv4: %%a
    echo   Student PC URL:   http://%%a:8000/index.php?page=test-portal
    echo   Admin Portal URL: http://%%a:8000/index.php?page=login
)
echo ---------------------------------------------------------------------
echo.
echo Opening Admin Dashboard on this Host PC...
start "" "http://localhost:8000/index.php?page=login"

echo.
echo =====================================================================
echo   SERVER RUNNING ON: http://0.0.0.0:8000/
echo   Candidates on other computers should open the Student PC URL above.
echo   Press Ctrl + C in this window to stop the server when finished.
echo =====================================================================
echo.

cd /d "%~dp0"
"%PHP_BIN%" -S 0.0.0.0:8000 -t "%~dp0"
pause
