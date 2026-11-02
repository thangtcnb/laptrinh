@echo off
chcp 65001 > nul
setlocal enabledelayedexpansion
title Cập Nhật Cơ Sở Dữ Liệu - Hệ Thống Quản Lý Công Việc UFM

echo ========================================================================
echo          CÔNG CỤ CẬP NHẬT CƠ SỞ DỮ LIỆU - HỆ THỐNG UFM
echo ========================================================================
echo.

set PHP_EXE=

:: 1. Kiem tra php trong bien moi truong PATH
where php.exe >nul 2>&1
if %errorlevel% equ 0 (
    set PHP_EXE=php.exe
    goto :FoundPHP
)

:: 2. Kiem tra cac duong dan XAMPP pho bien
if exist "C:\xampp\php\php.exe" (
    set "PHP_EXE=C:\xampp\php\php.exe"
    goto :FoundPHP
)
if exist "D:\xampp\php\php.exe" (
    set "PHP_EXE=D:\xampp\php\php.exe"
    goto :FoundPHP
)
if exist "E:\xampp\php\php.exe" (
    set "PHP_EXE=E:\xampp\php\php.exe"
    goto :FoundPHP
)

:: 3. Kiem tra cac duong dan Laragon pho bien
for /f "delims=" %%I in ('dir /b /s "C:\laragon\bin\php\php.exe" 2^>nul') do (
    if exist "%%I" (
        set "PHP_EXE=%%I"
        goto :FoundPHP
    )
)
for /f "delims=" %%I in ('dir /b /s "D:\laragon\bin\php\php.exe" 2^>nul') do (
    if exist "%%I" (
        set "PHP_EXE=%%I"
        goto :FoundPHP
    )
)

:: 4. Kiem tra cac duong dan cai dat PHP thong dung khac
if exist "C:\php\php.exe" (
    set "PHP_EXE=C:\php\php.exe"
    goto :FoundPHP
)
if exist "D:\php\php.exe" (
    set "PHP_EXE=D:\php\php.exe"
    goto :FoundPHP
)
if exist "C:\tools\php\php.exe" (
    set "PHP_EXE=C:\tools\php\php.exe"
    goto :FoundPHP
)
for /f "delims=" %%I in ('dir /b /s "C:\Program Files\PHP\php.exe" 2^>nul') do (
    if exist "%%I" (
        set "PHP_EXE=%%I"
        goto :FoundPHP
    )
)

:: 5. Neu chua tim thay, yeu cau nguoi dung nhap duong dan hoac keo tha
echo [THONG BAO] Khong tim thay php.exe tu dong o cac thu muc mac dinh (XAMPP/Laragon).
echo Ban co the:
echo   - Nhap duong dan thu muc chua php.exe (vi du: C:\xampp\php)
echo   - Hoac keo tha file php.exe truc tiep vao cua so nay roi an Enter.
echo.
set /p USER_INPUT="Duong dan php.exe: "
set USER_INPUT=%USER_INPUT:"=%

if exist "%USER_INPUT%" (
    if exist "%USER_INPUT%\php.exe" (
        set "PHP_EXE=%USER_INPUT%\php.exe"
    ) else (
        set "PHP_EXE=%USER_INPUT%"
    )
    goto :FoundPHP
)

echo [LOI] Khong tim thay chuong trinh PHP tai: "%USER_INPUT%"
echo Vui long kiem tra lai duong dan hoac cai dat PHP/XAMPP.
goto :End

:FoundPHP
echo [PHP Engine] Da tim thay PHP: "%PHP_EXE%"
echo.
echo [Tien trinh] Dang thuc thi dong bo CSDL theo config.php va cac migration...
echo ------------------------------------------------------------------------

"%PHP_EXE%" -f "%~dp0update_db.php"

if %errorlevel% equ 0 (
    echo ------------------------------------------------------------------------
    echo ========================================================================
    echo  [THANH CONG] HOAN TAT CAP NHAT CO SO DU LIEU!
    echo  - Da dong bo toan bo bang va chay tat ca migration moi nhat.
    echo  - Da dam bao tai khoan Truong phong: username: tp / password: tp
    echo ========================================================================
) else (
    echo ------------------------------------------------------------------------
    echo ========================================================================
    echo  [CANH BAO] QUA TRINH CAP NHAT CSDL GAP SU CO!
    echo  Vui long kiem tra file config.php va dam bao MySQL/MariaDB dang khoi dong.
    echo ========================================================================
)

:End
echo.
pause
