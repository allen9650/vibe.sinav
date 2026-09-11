@echo off
title vibe.Sınav Assessment System - Student Portal
color 0A
cls

echo =====================================================================
echo    vibe.Sınav ASSESSMENT SYSTEM
echo    [STUDENT & CANDIDATE EXAMINATION PORTAL]
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

echo [INFO] Starting Local Examination Server on port 8000...
echo.
echo ---------------------------------------------------------------------
echo   STUDENT INSTRUCTIONS:
echo   1. Enter your assigned Roll Number.
echo   2. Review the competition instructions.
echo   3. Click "Start Test" and begin typing when the countdown starts.
echo ---------------------------------------------------------------------
echo.
echo Opening Student Portal in your browser...
start "" "http://localhost:8000/index.php?page=test-portal"

echo.
echo =====================================================================
echo   STUDENT PORTAL: http://localhost:8000/index.php?page=test-portal
echo   Press Ctrl + C in this window to stop the server when finished.
echo =====================================================================
echo.

cd /d "%~dp0"
"%PHP_BIN%" -S 0.0.0.0:8000 -t "%~dp0"
pause
