<?php

declare(strict_types=1);

/**
 * ImageService.php (hardened)
 * - Upload + validate (size/ext/mime/dimensions)
 * - Resize/optimize (max 1920x1080)
 * - Thumbnail (400x300 แบบ contain)
 * - รองรับ JPG/PNG/GIF/WEBP + transparency
 *
 * หมายเหตุ:
 * - ใช้ move_uploaded_file แทน copy (ปลอดภัยกว่า/ถูก flow)
 * - validate MIME ด้วย finfo + getimagesize
 * - กัน filename แปลก ๆ (directory traversal)
 */

final class ImageService
{
  public const MAX_WIDTH = 1920;
  public const MAX_HEIGHT = 1080;

  public const THUMBNAIL_WIDTH = 400;
  public const THUMBNAIL_HEIGHT = 300;

  public const JPEG_QUALITY = 85;   // 0-100
  public const WEBP_QUALITY = 85;   // 0-100
  public const PNG_COMPRESSION = 9; // 0-9

  public const MAX_FILE_SIZE = 5_242_880; // 5MB

  /** @var array<string,string> ext => mime */
  private const ALLOWED = [
    'jpg'  => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png'  => 'image/png',
    'gif'  => 'image/gif',
    'webp' => 'image/webp',
  ];

  private static function ensureDirectory(string $path): bool
  {
    if (is_dir($path)) return true;
    
    // สร้างโฟลเดอร์ด้วยสิทธิ์ 775 เพื่อให้ web server เขียนได้
    if (!mkdir($path, 0775, true) && !is_dir($path)) {
      return false;
    }
    
    // ตั้งค่า group เป็น www-data ถ้าเป็นไปได้
    @chgrp($path, 'www-data');
    @chmod($path, 0775);
    
    return is_dir($path) && is_writable($path);
  }

  /** กันชื่อไฟล์แปลก ๆ */
  private static function sanitizeBasename(string $name): string
  {
    $name = basename($name);
    $name = preg_replace('/[^a-zA-Z0-9._-]/', '_', $name) ?? 'file';
    $name = trim($name, '._-');
    return $name !== '' ? $name : 'file';
  }

  /** คืน ext ที่ normalize แล้ว หรือ null */
  private static function normalizeExt(string $filename): ?string
  {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return array_key_exists($ext, self::ALLOWED) ? $ext : null;
  }

  /**
   * Validate upload file (size/ext/mime/image)
   * @return array{ok:bool,message?:string,ext?:string,mime?:string,width?:int,height?:int}
   */
  public static function validateUpload(array $file): array
  {
    if (!isset($file['error']) || (int)$file['error'] !== UPLOAD_ERR_OK) {
      return ['ok' => false, 'message' => 'อัปโหลดไฟล์ไม่สำเร็จ'];
    }

    $tmp = (string)($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      return ['ok' => false, 'message' => 'ไฟล์อัปโหลดไม่ถูกต้อง'];
    }

    $size = (int)($file['size'] ?? 0);
    if ($size <= 0 || $size > self::MAX_FILE_SIZE) {
      return ['ok' => false, 'message' => 'ไฟล์มีขนาดเกิน 5MB'];
    }

    $name = (string)($file['name'] ?? '');
    $ext = self::normalizeExt($name);
    if ($ext === null) {
      return ['ok' => false, 'message' => 'รองรับเฉพาะ jpg, jpeg, png, gif, webp'];
    }

    if (!class_exists('finfo')) {
      return ['ok' => false, 'message' => 'ระบบตรวจสอบไฟล์ไม่พร้อม (finfo)'];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string)($finfo->file($tmp) ?: '');
    if ($mime === '' || !in_array($mime, self::ALLOWED, true)) {
      return ['ok' => false, 'message' => 'ไฟล์ไม่ใช่รูปภาพที่รองรับ'];
    }

    $img = @getimagesize($tmp);
    if ($img === false) {
      return ['ok' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'];
    }

    [$w, $h, $type] = $img;
    if ((int)$w <= 0 || (int)$h <= 0) {
      return ['ok' => false, 'message' => 'ไฟล์รูปภาพไม่ถูกต้อง'];
    }

    // กันภาพโหดเกิน (กัน memory explode) ปรับได้
    $maxPixels = 24_000_000; // ~24MP
    if (((int)$w * (int)$h) > $maxPixels) {
      return ['ok' => false, 'message' => 'รูปมีความละเอียดสูงเกินไป'];
    }

    return ['ok' => true, 'ext' => $ext, 'mime' => $mime, 'width' => (int)$w, 'height' => (int)$h];
  }

  /**
   * Resize & optimize
   * - contain ไม่ crop
   */
  public static function processImage(
    string $sourcePath,
    string $destinationPath,
    ?int $maxWidth = null,
    ?int $maxHeight = null
  ): bool {
    if (!is_file($sourcePath)) return false;

    $maxWidth  = $maxWidth  ?? self::MAX_WIDTH;
    $maxHeight = $maxHeight ?? self::MAX_HEIGHT;

    $info = @getimagesize($sourcePath);
    if ($info === false) return false;

    [$ow, $oh, $type] = $info;
    if ((int)$ow <= 0 || (int)$oh <= 0) return false;

    $ratio = min($maxWidth / $ow, $maxHeight / $oh, 1);
    $nw = max(1, (int)round($ow * $ratio));
    $nh = max(1, (int)round($oh * $ratio));

    if (!extension_loaded('gd')) return false;

    $src = match ($type) {
      IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
      IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
      IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
      IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
      default        => false,
    };
    if ($src === false) return false;

    $dst = imagecreatetruecolor($nw, $nh);
    if ($dst === false) {
      return false;
    }

    // transparency
    if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_WEBP || $type === IMAGETYPE_GIF) {
      imagealphablending($dst, false);
      imagesavealpha($dst, true);
      $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
      imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    }

    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, (int)$ow, (int)$oh);

    // ensure destination dir exists
    $dir = dirname($destinationPath);
    if (!self::ensureDirectory($dir)) {
      return false;
    }

    $ok = match ($type) {
      IMAGETYPE_JPEG => imagejpeg($dst, $destinationPath, self::JPEG_QUALITY),
      IMAGETYPE_PNG  => imagepng($dst, $destinationPath, self::PNG_COMPRESSION),
      IMAGETYPE_GIF  => imagegif($dst, $destinationPath),
      IMAGETYPE_WEBP => function_exists('imagewebp') ? imagewebp($dst, $destinationPath, self::WEBP_QUALITY) : false,
      default        => false,
    };

    return $ok === true;
  }

  public static function createThumbnail(string $sourcePath, string $thumbnailPath): bool
  {
    return self::processImage($sourcePath, $thumbnailPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
  }

  /**
   * Upload + process + (optional) thumbnail
   *
   * @return array{ok:bool, message?:string, public_path?:string, thumb_public_path?:string}
   *
   * แนวคิด path:
   * - $publicDir: โฟลเดอร์จริงในเครื่อง เช่น /var/www/project/public/storage/uploads/areas
   * - $publicBaseUrl: url path ที่เสิร์ฟ เช่น /storage/uploads/areas
   */
  public static function uploadAndProcess(
    array $file,
    string $publicDir,
    string $publicBaseUrl,
    string $baseFilenameNoExt,
    bool $makeThumb = false,
    string $thumbSuffix = '_thumb'
  ): array {
    $v = self::validateUpload($file);
    if (!$v['ok']) return ['ok' => false, 'message' => (string)($v['message'] ?? 'อัปโหลดไม่สำเร็จ')];

    $ext = (string)$v['ext'];

    $safeBase = self::sanitizeBasename($baseFilenameNoExt);
    $safeBase = preg_replace('/\.[a-zA-Z0-9]+$/', '', $safeBase) ?? $safeBase; // กันมี .ext ซ้อน
    if ($safeBase === '') $safeBase = 'image';

    if (!self::ensureDirectory($publicDir)) {
      return ['ok' => false, 'message' => 'ไม่สามารถสร้างโฟลเดอร์อัปโหลดได้'];
    }

    $finalName = $safeBase . '.' . $ext;
    $destPath  = rtrim($publicDir, "/\\") . '/' . $finalName;

    $tmp = (string)($file['tmp_name'] ?? '');

    // ถ้า GD พร้อม -> resize/optimize
    $processed = false;
    if (extension_loaded('gd')) {
      $processed = self::processImage($tmp, $destPath);
    }

    // fallback: move_uploaded_file (ไม่ resize)
    if (!$processed) {
      if (!move_uploaded_file($tmp, $destPath)) {
        return ['ok' => false, 'message' => 'ย้ายไฟล์ไม่สำเร็จ'];
      }
    }

    $publicPath = rtrim($publicBaseUrl, "/") . '/' . $finalName;

    $out = [
      'ok' => true,
      'public_path' => $publicPath,
    ];

    if ($makeThumb) {
      $thumbName = $safeBase . $thumbSuffix . '.' . $ext;
      $thumbPath = rtrim($publicDir, "/\\") . '/' . $thumbName;

      // ถ้า thumbnail สร้างจากไฟล์ที่ process แล้ว (destPath) จะเสถียรกว่า
      $thumbOk = extension_loaded('gd') ? self::createThumbnail($destPath, $thumbPath) : false;
      if ($thumbOk) {
        $out['thumb_public_path'] = rtrim($publicBaseUrl, "/") . '/' . $thumbName;
      }
    }

    return $out;
  }

  public static function deleteImage(string $absolutePath): bool
  {
    if (is_file($absolutePath)) return @unlink($absolutePath);
    return true;
  }

  public static function deleteImages(array $absolutePaths): int
  {
    $deleted = 0;
    foreach ($absolutePaths as $p) {
      if (is_string($p) && self::deleteImage($p)) $deleted++;
    }
    return $deleted;
  }

  /**
   * ตรวจสอบว่ารูปภาพมีอยู่จริงหรือไม่
   */
  public static function imageExists(string $publicPath): bool
  {
    $root = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(__DIR__, 3);
    $absolutePath = $root . '/public' . $publicPath;
    return is_file($absolutePath);
  }

  /**
   * แปลง public path เป็น absolute path
   * เช่น /storage/uploads/areas/image.jpg -> /var/www/.../public/storage/uploads/areas/image.jpg
   */
  public static function publicPathToAbsolute(string $publicPath): string
  {
    $root = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(__DIR__, 3);
    return $root . '/public' . $publicPath;
  }

  /**
   * แปลง absolute path เป็น public URL
   * เช่น /var/www/.../public/storage/uploads/areas/image.jpg -> /storage/uploads/areas/image.jpg
   */
  public static function absolutePathToPublic(string $absolutePath): ?string
  {
    $root = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(__DIR__, 3);
    $publicDir = $root . '/public';
    
    if (strpos($absolutePath, $publicDir) === 0) {
      return substr($absolutePath, strlen($publicDir));
    }
    
    return null;
  }

  /**
   * ดึงข้อมูลรูปภาพ (ขนาด, ชนิด)
   * @return array{width:int,height:int,type:string,mime:string,size:int}|null
   */
  public static function getImageInfo(string $publicPath): ?array
  {
    $absolutePath = self::publicPathToAbsolute($publicPath);
    
    if (!is_file($absolutePath)) {
      return null;
    }
    
    $info = @getimagesize($absolutePath);
    if ($info === false) {
      return null;
    }
    
    [$width, $height, $type, $attr] = $info;
    $mime = $info['mime'] ?? '';
    $size = (int)@filesize($absolutePath);
    
    $typeNames = [
      IMAGETYPE_JPEG => 'jpeg',
      IMAGETYPE_PNG => 'png',
      IMAGETYPE_GIF => 'gif',
      IMAGETYPE_WEBP => 'webp',
    ];
    
    return [
      'width' => (int)$width,
      'height' => (int)$height,
      'type' => $typeNames[$type] ?? 'unknown',
      'mime' => (string)$mime,
      'size' => $size,
    ];
  }

  /**
   * ตรวจสอบและสร้างโฟลเดอร์อัพโหลดทั้งหมด
   */
  public static function initializeUploadDirectories(): array
  {
    $root = defined('BASE_PATH') ? rtrim((string)BASE_PATH, '/') : dirname(__DIR__, 3);
    $baseDir = $root . '/public/storage/uploads';
    
    $directories = [
      'base' => $baseDir,
      'areas' => $baseDir . '/areas',
      'contracts' => $baseDir . '/contracts',
      'slips' => $baseDir . '/slips',
    ];
    
    $results = [];
    foreach ($directories as $key => $dir) {
      $created = self::ensureDirectory($dir);
      $writable = is_writable($dir);
      $results[$key] = [
        'path' => $dir,
        'exists' => is_dir($dir),
        'writable' => $writable,
        'status' => $created && $writable ? 'ok' : 'error',
      ];
    }
    
    return $results;
  }

  /**
   * ทดสอบการอัพโหลดและดึงรูปภาพ
   * สำหรับใช้ในการ debug
   */
  public static function runDiagnostics(): array
  {
    $results = [
      'php_version' => PHP_VERSION,
      'gd_loaded' => extension_loaded('gd'),
      'finfo_available' => class_exists('finfo'),
      'directories' => self::initializeUploadDirectories(),
      'max_upload_size' => ini_get('upload_max_filesize'),
      'max_post_size' => ini_get('post_max_size'),
      'memory_limit' => ini_get('memory_limit'),
    ];
    
    return $results;
  }
}
