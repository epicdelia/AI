@echo off
REM Windows Make wrapper for Creator Studio
REM Forwards targets to WSL GNU Make if available, or provides quick dispatch
where wsl >nul 2>nul
if %ERRORLEVEL% EQU 0 (
    wsl make %*
) else (
    echo [ERROR] WSL or GNU Make was not found in PATH.
    echo Please run via Docker: docker compose exec laravel make %*
    exit /b 1
)

