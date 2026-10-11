<?php
declare(strict_types=1);
namespace App\Services;
final class ImageUploadService
{
 public function store(array $file):string
 {
  if(!isset($file['tmp_name'],$file['size'],$file['error'])||!is_string($file['tmp_name'])||$file['error']!==UPLOAD_ERR_OK||(int)$file['size']>2*1024*1024||!is_uploaded_file($file['tmp_name'])){throw new \DomainException('En fazla 2 MB geçerli bir görsel yükleyin.');}
  $mime=(new \finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);$types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];$size=@getimagesize($file['tmp_name']);
  if(!isset($types[$mime])||!$size||$size['mime']!==$mime||$size[0]>6000||$size[1]>6000){throw new \DomainException('JPEG, PNG veya WebP kullanın; en fazla 6000×6000 piksel.');}
  $path='uploads/'.bin2hex(random_bytes(20)).'.'.$types[$mime];
  if(!move_uploaded_file($file['tmp_name'],BASE_PATH.'/public/'.$path)){throw new \RuntimeException('Görsel kaydedilemedi.');}
  return $path;
 }
}
