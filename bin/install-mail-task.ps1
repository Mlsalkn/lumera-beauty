param([switch]$Install)
$ErrorActionPreference='Stop'
$projectRoot=Split-Path $PSScriptRoot -Parent
$phpPath='C:\xampp\php\php.exe'
if(-not $Install){Write-Host 'Kurulum için -Install kullanın. SMTP ve APP_URL ayarlarını önce doğrulayın. Görev yalnızca mevcut kullanıcı oturumu açıkken çalışır.';exit 0}
if(-not(Test-Path -LiteralPath $phpPath)){throw 'PHP çalıştırıcısı bulunamadı.'}
& $phpPath (Join-Path $PSScriptRoot 'check-readiness.php')
if ($LASTEXITCODE -ne 0) { throw 'SMTP/HTTPS ön kontrolü başarısız; görev kurulmadı.' }
$taskName='LumeraBeautyMailQueue'
if(Get-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue){throw 'Mevcut görev bulundu; üzerine yazılmadı.'}
$action=New-ScheduledTaskAction -Execute $phpPath -Argument ('"'+(Join-Path $PSScriptRoot 'process-mail.php')+'" --run --reminders') -WorkingDirectory $projectRoot
$trigger=New-ScheduledTaskTrigger -Once -At (Get-Date).AddMinutes(1) -RepetitionInterval (New-TimeSpan -Minutes 5)
$principal=New-ScheduledTaskPrincipal -UserId ([System.Security.Principal.WindowsIdentity]::GetCurrent().Name) -LogonType Interactive -RunLevel Limited
$settings=New-ScheduledTaskSettingsSet -MultipleInstances IgnoreNew -StartWhenAvailable -ExecutionTimeLimit (New-TimeSpan -Minutes 4)
Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings -Description 'Luméra Beauty e-posta kuyruğu ve hatırlatmalar; SMTP kapalıyken gönderim yapmaz.' | Select-Object TaskName,State
