@if "%DEBUG%"=="" @echo off
@rem ##########################################################################
@rem
@rem  Gradle startup script for Windows
@rem
@rem ##########################################################################

setlocal EnableExtensions

set DIRNAME=%~dp0
if "%DIRNAME%"=="" set DIRNAME=.
set APP_BASE_NAME=%~n0
set APP_HOME=%DIRNAME%

for %%i in ("%APP_HOME%") do set APP_HOME=%%~fi

set DEFAULT_JVM_OPTS="-Xmx64m" "-Xms64m"

if not defined JAVA_HOME if exist "%USERPROFILE%\.jdk\jdk-21.0.13.0.2+1\bin\java.exe" set "JAVA_HOME=%USERPROFILE%\.jdk\jdk-21.0.13.0.2+1"

if defined JAVA_HOME goto findJavaFromJavaHome

set JAVA_EXE=java.exe
%JAVA_EXE% -version >NUL 2>&1
if %ERRORLEVEL% equ 0 goto execute

echo. 1>&2
echo ERROR: JAVA_HOME is not set and no 'java' command could be found in your PATH. 1>&2
echo Please set the JAVA_HOME variable in your environment to match the location of your Java installation. 1>&2
exit /b 1

:findJavaFromJavaHome
set "JAVA_HOME=%JAVA_HOME:"=%"
set "JAVA_EXE=%JAVA_HOME%\bin\java.exe"

if exist "%JAVA_EXE%" goto execute

echo. 1>&2
echo ERROR: JAVA_HOME is set to an invalid directory: %JAVA_HOME% 1>&2
echo Please set the JAVA_HOME variable in your environment to match the location of your Java installation. 1>&2
exit /b 1

:execute
"%JAVA_EXE%" %DEFAULT_JVM_OPTS% %JAVA_OPTS% %GRADLE_OPTS% "-Dorg.gradle.appname=%APP_BASE_NAME%" -jar "%APP_HOME%\gradle\wrapper\gradle-wrapper.jar" %*
exit /b %ERRORLEVEL%
