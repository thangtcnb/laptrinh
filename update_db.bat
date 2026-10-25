@echo off
chcp 65001 > nul
echo ========================================================================
echo Cap nhat Co so du lieu An toan (Safe Migration) - laptrinh_db...
echo KHONG XOA DU LIEU DANH SACH CONG VIEC, NGUOI DUNG VA PHONG BAN HIENTAI!
echo ========================================================================

:: Thiet lap duong dan den mysql.exe
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe

if exist "%MYSQL_PATH%" (
    set MYSQL_CMD="%MYSQL_PATH%"
) else (
    set MYSQL_CMD=mysql
)

echo [1/4] Dang thuc thi Migration 20261023_comments (database\update_20261023_comments.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\update_20261023_comments.sql >nul 2>&1

echo [2/4] Dang thuc thi Migration update_v2.sql (database\migrations\update_v2.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\migrations\update_v2.sql

echo [3/4] Dang thuc thi Migration Lich hop BGD (database\migrations\update_bgd_schedules.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\migrations\update_bgd_schedules.sql

echo [4/5] Dang thuc thi Migration Approval & Priority (database\migrations\update_20261025_approval_priority.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\migrations\update_20261025_approval_priority.sql
if %ERRORLEVEL% NEQ 0 (
    echo [LOI] Khong the thuc thi database\migrations\update_20261025_approval_priority.sql. Vui long kiem tra XAMPP MySQL.
    pause
    exit /b %ERRORLEVEL%
)

echo [5/5] Dang thuc thi Migration Batch Approval (database\migrations\update_20261026_batch_approval.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\migrations\update_20261026_batch_approval.sql
if %ERRORLEVEL% NEQ 0 (
    echo [LOI] Khong the thuc thi database\migrations\update_20261026_batch_approval.sql. Vui long kiem tra XAMPP MySQL.
    pause
    exit /b %ERRORLEVEL%
)

echo ========================================================================
echo Cap nhat Migration CSDL thanh cong! Toan bo du lieu cu duoc giu nguyen!
echo ========================================================================
pause
