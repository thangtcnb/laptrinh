@echo off
chcp 65001 > nul
echo ========================================================================
echo Dang thiet lap va cap nhat CSDL System Quan ly Cong viec - UFM...
echo ========================================================================

:: Thiet lap duong dan den mysql.exe
set MYSQL_PATH=C:\xampp\mysql\bin\mysql.exe

if exist "%MYSQL_PATH%" (
    set MYSQL_CMD="%MYSQL_PATH%"
) else (
    set MYSQL_CMD=mysql
)

echo [Nang cap] Thuc thi Migration cap nhat CSDL an toàn (update_db.bat)...
call update_db.bat

echo ========================================================================
echo Thiet lap va Migration co so du lieu thanh cong!
echo ========================================================================
pause
