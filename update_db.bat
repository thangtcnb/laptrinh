@echo off
chcp 65001 > nul
echo ========================================================================
echo Cap nhat Co so du lieu An toan (Safe Migration)...
echo KHONG XOA DU LIEU DANH SACH CONG VIEC, NGUOI DUNG VA PHONG BAN HIENTAI!
echo ========================================================================

:: Thiet lap duong dan den php.exe
set PHP_CMD=php
where php >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\php\php.exe" (
        set PHP_CMD="C:\xampp\php\php.exe"
    )
)

%PHP_CMD% "%~dp0database\run_migrations.php"
if %ERRORLEVEL% EQU 0 (
    echo ========================================================================
    echo Cap nhat Migration CSDL thanh cong! Toan bo du lieu cu duoc giu nguyen!
    echo ========================================================================
    pause
    exit /b 0
) else (
    echo ========================================================================
    echo [LOI] Khong the thuc thi Migration CSDL qua PHP. Vui long kiem tra XAMPP MySQL.
    echo ========================================================================
    pause
    exit /b %ERRORLEVEL%
)
