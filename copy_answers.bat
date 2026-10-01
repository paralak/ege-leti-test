@echo off
setlocal

rem Укажите папку на внешнем или сетевом диске, например:
rem set "DESTINATION=\\SERVER\ExamResults\Computer-01"
rem set "DESTINATION=Z:\ExamResults\Computer-01"
set "DESTINATION="

rem При ручном запуске берётся answers.xml из установленной рядом программы.
set "SOURCE=%~1"
if "%SOURCE%"=="" set "SOURCE=%~dp0www\variant\answers.xml"

rem Батник необязателен: любая проблема с настройкой или сетью завершается без ошибки.
if not exist "%SOURCE%" exit /b 0
if "%DESTINATION%"=="" exit /b 0
if not exist "%DESTINATION%\" exit /b 0

xcopy "%SOURCE%" "%DESTINATION%\" /Y /I /Q >nul 2>&1
exit /b 0
