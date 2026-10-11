<?php require __DIR__.'/_start.php'; ?>
<nav class="customer-tabs" aria-label="Randevu filtreleri"><?php foreach(['upcoming'=>'Yaklaşan','pending'=>'Beklemede','confirmed'=>'Onaylandı','completed'=>'Tamamlandı','cancelled'=>'İptal','past'=>'Geçmiş','all'=>'Tümü'] as $key=>$label): ?><a class="<?= $filter===$key?'is-current':'' ?>" href="<?= e(url('customer/appointments?filter='.$key)) ?>"><?= e($label) ?></a><?php endforeach ?></nav><section class="customer-card"><?php $appointmentRows=$appointments;require __DIR__.'/_appointments.php'; ?></section>
<?php require __DIR__.'/_end.php'; ?>
