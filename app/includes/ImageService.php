<?php

declare(strict_types=1);

/**
 * Image Service
 * จัดการการอัปโหลด, resize, และ optimize รูปภาพ
 */
class ImageService
{
  private const MAX_WIDTH = 1920;
  private const MAX_HEIGHT = 1080;
  private const THUMBNAIL_WIDTH = 400;
  private const THUMBNAIL_HEIGHT = 300;
  private const QUALITY = 85;

  /**
   * Resize และ optimize รูปภาพ
   */
  public static function processImage(string $sourcePath, string $destinationPath, ?int $maxWidth = null, ?int $maxHeight = null): bool
  {
    if (!file_exists($sourcePath)) {
      return false;
    }

    $maxWidth = $maxWidth ?? self::MAX_WIDTH;
    $maxHeight = $maxHeight ?? self::MAX_HEIGHT;

    $imageInfo = @getimagesize($sourcePath);
    if ($imageInfo === false) {
      return false;
    }

    [$originalWidth, $originalHeight, $imageType] = $imageInfo;

    // คำนวณขนาดใหม่
    $ratio = min($maxWidth / $originalWidth, $maxHeight / $originalHeight, 1);
    $newWidth = (int)($originalWidth * $ratio);
    $newHeight = (int)($originalHeight * $ratio);

    // สร้างรูปภาพจาก source
    $source = match ($imageType) {
      IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
      IMAGETYPE_PNG => @imagecreatefrompng($sourcePath),
      IMAGETYPE_GIF => @imagecreatefromgif($sourcePath),
      IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
      default => false,
    };

    if ($source === false) {
      return false;
    }

    // สร้างรูปภาพใหม่
    $destination = imagecreatetruecolor($newWidth, $newHeight);

    // รองรับ PNG และ WebP ที่มี transparency
    if ($imageType === IMAGETYPE_PNG || $imageType === IMAGETYPE_WEBP) {
      imagealphablending($destination, false);
      imagesavealpha($destination, true);
      $transparent = imagecolorallocatealpha($destination, 255, 255, 255, 127);
      imagefilledrectangle($destination, 0, 0, $newWidth, $newHeight, $transparent);
    }

    imagecopyresampled($destination, $source, 0, 0, 0, 0, $newWidth, $newHeight, $originalWidth, $originalHeight);

    // บันทึกไฟล์
    $success = match ($imageType) {
      IMAGETYPE_JPEG => imagejpeg($destination, $destinationPath, self::QUALITY),
      IMAGETYPE_PNG => imagepng($destination, $destinationPath, 9),
      IMAGETYPE_GIF => imagegif($destination, $destinationPath),
      IMAGETYPE_WEBP => imagewebp($destination, $destinationPath, self::QUALITY),
      default => false,
    };

    imagedestroy($source);
    imagedestroy($destination);

    return $success !== false;
  }

  /**
   * สร้าง thumbnail
   */
  public static function createThumbnail(string $sourcePath, string $thumbnailPath): bool
  {
    return self::processImage($sourcePath, $thumbnailPath, self::THUMBNAIL_WIDTH, self::THUMBNAIL_HEIGHT);
  }

  /**
   * อัปโหลดและ process รูปภาพ
   */
  public static function uploadAndProcess(array $file, string $uploadDir, string $filename): ?string
  {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
      return null;
    }

    if (!is_dir($uploadDir)) {
      if (!mkdir($uploadDir, 0755, true)) {
        return null;
      }
    }

    $destinationPath = $uploadDir . '/' . $filename;

    // Process และ resize รูปภาพ
    if (!self::processImage($file['tmp_name'], $destinationPath)) {
      return null;
    }

    return $destinationPath;
  }

  /**
   * ลบไฟล์รูปภาพ
   */
  public static function deleteImage(string $imagePath): bool
  {
    if (file_exists($imagePath)) {
      return @unlink($imagePath);
    }
    return true;
  }

  /**
   * ลบรูปภาพหลายไฟล์
   */
  public static function deleteImages(array $imagePaths): int
  {
    $deleted = 0;
    foreach ($imagePaths as $path) {
      if (self::deleteImage($path)) {
        $deleted++;
      }
    }
    return $deleted;
  }
}
