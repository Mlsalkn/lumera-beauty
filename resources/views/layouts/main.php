<!doctype html>
<html lang="tr">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="Luméra Beauty — güzellik ve kişisel bakım için zarif bir deneyim.">
  <title><?= e($title ?? 'Luméra Beauty') ?></title>
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap/bootstrap.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/vendor/bootstrap-icons/bootstrap-icons.min.css')) ?>">
  <link rel="stylesheet" href="<?= e(url('assets/css/app.css')) ?>">
  <?php if(!empty($isHome)): ?><link rel="stylesheet" href="<?= e(url('assets/css/home.css?v=20261011')) ?>"><?php endif ?>
  <script defer src="<?= e(url('assets/vendor/bootstrap/bootstrap.bundle.min.js')) ?>"></script>
  <script defer src="<?= e(url('assets/js/app.js')) ?>"></script>
</head>
<body>
<a class="skip-link" href="#main">İçeriğe geç</a>
<header class="site-header"><nav class="navbar navbar-expand-lg container py-4" aria-label="Ana menü">
  <a class="navbar-brand brand" href="<?= e(url()) ?>">LUMÉRA<span>BEAUTY & WELLNESS</span></a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Menüyü aç"><i class="bi bi-list" aria-hidden="true"></i></button>
  <div class="collapse navbar-collapse justify-content-end" id="mainNav"><div class="navbar-nav align-items-md-center gap-md-4">
    <a class="nav-link" href="<?= e(url('about')) ?>">Hakkımızda</a><a class="nav-link" href="<?= e(url('services')) ?>">Bakımlar</a><a class="nav-link" href="<?= e(url('experts')) ?>">Uzmanlar</a><a class="nav-link" href="<?= e(url('reviews')) ?>">Deneyimler</a><a class="nav-link" href="<?= e(url('contact')) ?>">İletişim</a>
    <?php $navUser = App\Services\AuthService::current(); if ($navUser): ?>
    <a class="nav-link" href="<?= e(url(App\Services\AuthService::panel($navUser['role']))) ?>">Panelim</a>
    <form method="post" action="<?= e(url('logout')) ?>"><?= App\Core\Csrf::field() ?><button type="submit" class="btn btn-outline-dark">Çıkış</button></form>
    <?php else: ?><a class="nav-link" href="<?= e(url('login')) ?>">Giriş yap</a><a class="btn btn-lumera" href="<?= e(url('register')) ?>">Üye ol</a><?php endif ?>
  </div></div>
</nav></header>
<main id="main"><?= $content ?></main>
<footer class="container py-5 d-flex flex-wrap justify-content-between gap-3"><div class="brand">LUMÉRA<span>GÜZELLİĞİNİZE AYIRDIĞINIZ ZAMAN.</span></div><small>© <?= date('Y') ?> Luméra Beauty · Melisa Alkan · <a href="mailto:alkanmelisa12@gmail.com">alkanmelisa12@gmail.com</a> · <a href="<?= e(url('support')) ?>">Destek</a></small></footer>
</body></html>
