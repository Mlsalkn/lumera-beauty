$ErrorActionPreference = 'Stop'
$adminName = Read-Host 'Yönetici ad soyad'
$adminEmail = Read-Host 'Yönetici e-posta'
$securePassword = Read-Host 'Şifre (en az 12 karakter)' -AsSecureString
$secureConfirmation = Read-Host 'Şifre tekrarı' -AsSecureString
$passwordPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
$confirmationPointer = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($secureConfirmation)
try {
    $adminPayload = @{
        name = $adminName
        email = $adminEmail
        password = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($passwordPointer)
        password_confirmation = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($confirmationPointer)
    } | ConvertTo-Json -Compress
    $OutputEncoding = [System.Text.UTF8Encoding]::new($false)
    $adminPayload | & 'C:\xampp\php\php.exe' (Join-Path $PSScriptRoot 'create-admin.php')
    if ($LASTEXITCODE -ne 0) { throw 'Hesap oluşturulamadı.' }
} finally {
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($passwordPointer)
    [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($confirmationPointer)
    $adminPayload = $null
    $securePassword.Dispose()
    $secureConfirmation.Dispose()
}
