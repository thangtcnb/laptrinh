@echo off
chcp 65001 > nul
echo ========================================================================
echo Dang thiet lap va cap nhat CSDL System Quan ly Cong viec - UFM...
echo ========================================================================

echo [Nang cap] Thuc thi Migration cap nhat CSDL an toan (update_db.bat)...
call update_db.bat

echo ========================================================================
echo Thiet lap va Migration co so du lieu thanh cong!
echo ========================================================================
pause
