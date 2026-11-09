@echo off
rem Lancement local pour PowerShell ou cmd : scripts\serve.bat [port]
cd /d "%~dp0.."
set PORT=%1
if "%PORT%"=="" set PORT=8000
echo La France en Debat : http://127.0.0.1:%PORT%  (Ctrl+C pour arreter)
php -d variables_order=EGPCS -S 127.0.0.1:%PORT% -t public public/index.php
