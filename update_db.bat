@echo off
chcp 65001 > nul
title Cap nhat Co so du lieu - System Quan ly Cong viec UFM

echo ========================================================================
echo          CAP NHAT CO SO DU LIEU - SYSTEM QUAN LY CONG VIEC
echo ========================================================================
echo.

set MYSQL_EXE=C:\xampp\mysql\bin\mysql.exe
if not exist "%MYSQL_EXE%" (
    set MYSQL_EXE=mysql
)

set SQL_MIGRATION=%~dp0database\migrations\update_roles_and_users.sql

echo [1/2] Dang nhap migration SQL qua mysql CLI (laptrinh_db)...
"%MYSQL_EXE%" -u root laptrinh_db < "%SQL_MIGRATION%" 2>nul
if %errorlevel% equ 0 (
    echo  -^> [THANH CONG] Nhap du lieu SQL qua mysql CLI thanh cong!
) else (
    echo  -^> [THONG BAO] mysql CLI khong kha dung hoac chuyen tiep qua PHP runner...
)

echo.
echo [2/2] Dang thuc thi dong bo qua PHP Engine...

set PHP_EXE=C:\xampp\php\php.exe
if not exist "%PHP_EXE%" (
    set PHP_EXE=php
)

set SCRIPT_PATH=%~dp0update_db.php

"%PHP_EXE%" -f "%SCRIPT_PATH%"
if %errorlevel% equ 0 (
    echo.
    echo ========================================================================
    echo  [THANH CONG] HOAN THANH CAP NHAT ROLE PHO_PHONG VA USER MOI VAO CSDL!
    echo ========================================================================
) else (
    echo.
    echo ========================================================================
    echo  [LOI] QUA TRINH CAP NHAT CSDL GAP SU CO! VUI LONG KIEM TRA KET NOI.
    echo ========================================================================
)

echo.
pause
