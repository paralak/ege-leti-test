@echo off
setlocal

rem Укажите папку на внешнем или сетевом диске, например:
rem set "DESTINATION=\\SERVER\ExamResults\Computer-01"
rem set "DESTINATION=Z:\ExamResults\Computer-01"
set "DESTINATION="

rem При ручном запуске берётся answers.xml из установленной рядом программы.
set "SOURCE=%~1"
if "%SOURCE%"=="" set "SOURCE=%~dp0www\variant\answers.xml"
set "TARGET_NAME=%~2"
if "%TARGET_NAME%"=="" set "TARGET_NAME=answers.xml"

rem Батник необязателен: любая проблема с настройкой или сетью завершается без ошибки.
if not exist "%SOURCE%" exit /b 0
if "%DESTINATION%"=="" exit /b 0
if not exist "%DESTINATION%\" exit /b 0

rem Временная локальная копия позволяет xcopy сохранить файл под именем с номером КИМ.
set "STAGING=%TEMP%\ege-leti-answers-%RANDOM%-%RANDOM%"
mkdir "%STAGING%" >nul 2>&1
if not exist "%STAGING%\" exit /b 0
copy /Y "%SOURCE%" "%STAGING%\%TARGET_NAME%" >nul 2>&1
if not exist "%STAGING%\%TARGET_NAME%" goto cleanup

xcopy "%STAGING%\%TARGET_NAME%" "%DESTINATION%\" /Y /I /Q >nul 2>&1

:cleanup
del /Q "%STAGING%\%TARGET_NAME%" >nul 2>&1
rmdir "%STAGING%" >nul 2>&1
exit /b 0
