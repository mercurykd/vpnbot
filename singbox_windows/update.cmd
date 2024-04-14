@echo off
setlocal

set "exe_path=sing-box.exe"
set "URL=~url~"
set "FileName=config.json"
set "PreviousHash="

taskkill /IM "%exe_path%" /F
powershell -command "(New-Object System.Net.WebClient).DownloadFile('%URL%', '%FileName%')"
for /F "skip=1 delims=" %%H in ('2^>nul CertUtil -hashfile "%FileName%" SHA256') do (
    if not defined PreviousHash set "PreviousHash=%%H"
)
start "" /B "%CD%\%exe_path%" run -c "%FileName%"
echo "%PreviousHash%"
:loop
powershell -command "(New-Object System.Net.WebClient).DownloadFile('%URL%', '%FileName%')"
for /F "skip=1 delims=" %%H in ('2^>nul CertUtil -hashfile "%FileName%" SHA256') do (
    if not defined HASH set "HASH=%%H"
)
set "CurrentHash=%HASH%"
set "HASH="
echo "%CurrentHash%"
if "%CurrentHash%" NEQ "%PreviousHash%" (
    echo "change"
    set "PreviousHash=%CurrentHash%"
    taskkill /IM "%exe_path%" /F
    start "" /B "%CD%\%exe_path%" run -c "%FileName%"
) else (
    echo "not change"
)

timeout /t 15 /nobreak

goto :loop
