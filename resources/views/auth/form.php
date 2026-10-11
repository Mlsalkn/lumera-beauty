<?php
$register = $mode === 'register';
$notice = $_SESSION['notice'] ?? null; unset($_SESSION['notice']);
$fields = $register ? ['first_name' => ['Ad', 'text', 'given-name'], 'last_name' => ['Soyad', 'text', 'family-name'], 'email' => ['E-posta', 'email', 'email'], 'phone' => ['Telefon', 'tel', 'tel']] : ['email' => ['E-posta', 'email', 'username']];
?>
<section class="container auth-section"><div class="row g-5 align-items-start">
<div class="col-lg-5 auth-intro"><p class="eyebrow">LUMÉRA HESABINIZ</p><h1><?= $register ? 'Kendinize ayırdığınız<br>zaman burada başlar.' : 'Yeniden<br><em>hoş geldiniz.</em>' ?></h1><p class="lead mt-4">Kişisel bakım yolculuğunuz için size özel bir alan.</p><p>Hesabınızla panelinize güvenle erişin; bakımınızı planlayın ve randevularınızı takip edin.</p></div>
<div class="col-lg-6 offset-lg-1"><div class="auth-card"><h2><?= $register ? 'Üye olun' : 'Giriş yapın' ?></h2><p class="text-muted mb-4"><?= $register ? 'Müşteri hesabınızı birkaç adımda oluşturun.' : 'E-posta ve şifrenizle devam edin.' ?></p>
<?php if ($notice): ?><div class="alert alert-success" role="status"><?= e($notice) ?></div><?php endif ?>
<?php if ($errors): ?><div class="alert alert-danger" role="alert"><strong>İşleminiz tamamlanamadı.</strong><ul class="mb-0"><?php foreach ($errors as $error): ?><li><?= e($error) ?></li><?php endforeach ?></ul></div><?php endif ?>
<form method="post" action="<?= e(url($mode)) ?>" class="auth-form">
<?= App\Core\Csrf::field() ?>
<div class="row g-3">
<?php foreach ($fields as $field => [$label, $type, $autocomplete]): ?>
<div class="<?= in_array($field, ['first_name','last_name'], true) ? 'col-sm-6' : 'col-12' ?>"><label class="form-label" for="<?= e($field) ?>"><?= e($label) ?></label><input class="form-control <?= isset($errors[$field]) ? 'is-invalid' : '' ?>" id="<?= e($field) ?>" name="<?= e($field) ?>" type="<?= e($type) ?>" autocomplete="<?= e($autocomplete) ?>" maxlength="<?= $field === 'email' ? 190 : ($field === 'phone' ? 25 : 60) ?>" value="<?= e($old[$field] ?? '') ?>" required <?= isset($errors[$field]) ? 'aria-invalid="true" aria-describedby="error-' . e($field) . '"' : '' ?>><?php if (isset($errors[$field])): ?><div id="error-<?= e($field) ?>" class="invalid-feedback"><?= e($errors[$field]) ?></div><?php endif ?></div>
<?php endforeach ?>
<div class="col-12"><label class="form-label" for="password">Şifre</label><input class="form-control" id="password" name="password" type="password" autocomplete="<?= $register ? 'new-password' : 'current-password' ?>" <?= $register ? 'minlength="12"' : '' ?> required><?php if ($register): ?><div class="form-text">En az 12 karakter, en fazla 72 bayt. Şifrenizi kimseyle paylaşmayın.</div><?php endif ?></div>
<?php if ($register): ?><div class="col-12"><label class="form-label" for="password_confirmation">Şifre tekrarı</label><input class="form-control" id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required></div><?php endif ?>
<div class="col-12 mt-4"><button class="btn btn-lumera w-100" type="submit"><?= $register ? 'Hesabımı oluştur' : 'Giriş yap' ?> <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></button></div>
</div></form><p class="mt-4 mb-0 text-center"><?= $register ? 'Zaten üye misiniz?' : 'Henüz hesabınız yok mu?' ?> <a href="<?= e(url($register ? 'login' : 'register')) ?>"><?= $register ? 'Giriş yapın' : 'Üye olun' ?></a></p>
<?php if (!$register): ?><p class="mt-3 text-center"><a href="<?= e(url('forgot-password')) ?>">Şifremi unuttum</a> · <a href="<?= e(url('resend-verification')) ?>">E-postamı doğrula</a></p><?php endif ?></div></div></div></section>
