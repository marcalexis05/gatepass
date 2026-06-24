@echo off
setlocal enabledelayedexpansion

rem Default setting
set TUNNEL_SILENT=false

rem Read .env file line by line
if exist .env (
    for /f "usebackq tokens=1,2 delims==" %%i in (".env") do (
        set key=%%i
        set value=%%j
        if "!key!"=="TUNNEL_SILENT" set TUNNEL_SILENT=!value!
    )
)

if "!TUNNEL_SILENT!"=="true" (
    cscript //nologo run_silent.vbs
    exit /b
)

title Concentrix ^| Gatepass - Public Web Tunnel
echo Starting Concentrix ^| Gatepass Public Web Tunnel...
echo Make sure XAMPP Apache and MySQL are running first!
echo.
c:\xampp\php\php.exe tunnel.php
pause
