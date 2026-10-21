@echo off
chcp 65001 > nul
echo ========================================================================
echo Dang thiet lap co so du lieu System Quan ly Cong viec - UFM Quang Ngai...
echo ========================================================================

:: Thiet lap duong dan den mysql.exe
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe

:: Kiem tra mysql.exe co ton tai trong duong dan XAMPP mac dinh khong
if exist "%MYSQL_PATH%" (
    set MYSQL_CMD="%MYSQL_PATH%"
) else (
    set MYSQL_CMD=mysql
)

echo [1/3] Dang xoa du lieu cu (database\clean_old.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\clean_old.sql
if %ERRORLEVEL% NEQ 0 (
    echo [LOI] Khong the nap file database\clean_old.sql. Vui long kiem tra XAMPP MySQL.
    pause
    exit /b %ERRORLEVEL%
)

echo [2/3] Dang khoi tao cau truc bang (database\schema.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\schema.sql
if %ERRORLEVEL% NEQ 0 (
    echo [LOI] Khong the nap file database\schema.sql.
    pause
    exit /b %ERRORLEVEL%
)

echo [3/3] Dang nap du lieu mau (database\seed.sql)...
%MYSQL_CMD% -h localhost -P 3306 -u root < database\seed.sql
if %ERRORLEVEL% NEQ 0 (
    echo [LOI] Khong the nap file database\seed.sql.
    pause
    exit /b %ERRORLEVEL%
)

echo ========================================================================
echo Thiet lap co so du lieu thanh cong!
echo ========================================================================
pause
