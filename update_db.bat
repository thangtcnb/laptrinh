@echo off
chcp 65001 > nul
echo ========================================================================
echo CAP NHAT CO SO DU LIEU - BANG DISCUSSIONS (LAPTRINH_DB)
echo ========================================================================

set MYSQL_CMD=mysql
where mysql >nul 2>&1
if %ERRORLEVEL% NEQ 0 (
    if exist "C:\xampp\mysql\bin\mysql.exe" (
        set MYSQL_CMD="C:\xampp\mysql\bin\mysql.exe"
    )
)

echo Dang chay script update_db.sql vao CSDL laptrinh_db bang mysql.exe...
%MYSQL_CMD% -u root --default-character-set=utf8mb4 < "%~dp0update_db.sql"

if %ERRORLEVEL% EQU 0 (
    echo ========================================================================
    echo Cap nhat CSDL thanh cong! Bang 'discussions' da duoc khoi tao / cap nhat.
    echo ========================================================================
) else (
    echo ========================================================================
    echo [THONG BAO] Khong the chay qua mysql.exe hoac co loi. Dang thu PHP Migration Runner...
    echo ========================================================================
    set PHP_CMD=php
    where php >nul 2>&1
    if %ERRORLEVEL% NEQ 0 (
        if exist "C:\xampp\php\php.exe" (
            set PHP_CMD="C:\xampp\php\php.exe"
        )
    )
    %PHP_CMD% "%~dp0database\run_migrations.php"
)

echo ========================================================================
echo HOAN THANH QUAR TRINH CAP NHAT CSDL!
echo ========================================================================
pause
