@echo off
chcp 65001 > nul
echo ========================================================================
echo CAP NHAT CO SO DU LIEU - SYSTEM QUAN LY CONG VIEC
echo ========================================================================

set PHP_EXE=C:\xampp\php\php.exe
if not exist %PHP_EXE% (
    set PHP_EXE=php
)

set SCRIPT_PATH=%~dp0update_db.php
if not exist "%SCRIPT_PATH%" (
    if exist "C:\xampp\htdocs\laptrinh\update_db.php" (
        set SCRIPT_PATH=C:\xampp\htdocs\laptrinh\update_db.php
    )
)

echo Dang thuc thi update_db.php qua PHP engine...
%PHP_EXE% -f "%SCRIPT_PATH%"

echo.
echo ========================================================================
echo HOAN THANH QUATRINH CAP NHAT CSDL!
echo ========================================================================
pause
