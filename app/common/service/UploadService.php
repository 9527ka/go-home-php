<?php
declare(strict_types=1);

namespace app\common\service;

use app\common\enum\ErrorCode;
use app\common\exception\BusinessException;
use think\facade\Log;

class UploadService
{
    /**
     * 允许的图片类型
     */
    const ALLOWED_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    /**
     * 允许的视频类型
     */
    const ALLOWED_VIDEO_TYPES = ['mp4', 'mov'];

    /**
     * 允许的音频类型
     */
    const ALLOWED_AUDIO_TYPES = ['m4a', 'aac', 'mp3'];

    /**
     * 最大文件大小 (5MB)
     */
    const MAX_SIZE = 5 * 1024 * 1024;

    /**
     * 最大视频文件大小 (50MB)
     */
    const MAX_VIDEO_SIZE = 50 * 1024 * 1024;

    /**
     * 最大音频文件大小 (10MB)
     */
    const MAX_AUDIO_SIZE = 10 * 1024 * 1024;

    /**
     * 缩略图最大宽度
     */
    const THUMB_MAX_WIDTH = 400;

    /**
     * 上传图片
     *
     * @param \think\file\UploadedFile $file
     * @return array ['url' => '...', 'thumb_url' => '...']
     */
    public static function uploadImage($file): array
    {
        // 校验文件大小
        if ($file->getSize() > self::MAX_SIZE) {
            throw new BusinessException(ErrorCode::PARAM_IMAGE_TOO_LARGE);
        }

        // 校验文件扩展名
        $ext = strtolower($file->extension());
        if (!in_array($ext, self::ALLOWED_TYPES)) {
            throw new BusinessException(ErrorCode::PARAM_IMAGE_TYPE_ERR);
        }

        // ⚠️ 安全加固：校验文件实际 MIME 类型（magic bytes）
        // 防止攻击者修改扩展名上传恶意文件
        self::verifyMimeType($file->getPathname(), $ext);

        try {
            // 按日期分目录存储
            $dir = date('Ymd');
            $filename = md5(uniqid((string)mt_rand(), true)) . '.' . $ext;
            $savePath = 'uploads/' . $dir;

            // 确保目录存在
            $fullDir = public_path() . $savePath;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            // 移动文件
            $file->move($fullDir, $filename);

            $url = '/' . $savePath . '/' . $filename;

            // ⚠️ 安全处理：剥离 EXIF 信息（防止泄露 GPS 位置）
            $fullPath = public_path() . $url;
            self::stripExif($fullPath);

            // 生成缩略图
            $thumbUrl = self::generateThumbnail($fullPath, $dir, $filename);

            Log::info("Image uploaded: {$url}");

            return [
                'url'       => $url,
                'thumb_url' => $thumbUrl,
            ];

        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Upload failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::UPLOAD_FAIL);
        }
    }

    /**
     * ⚠️ 校验文件实际 MIME 类型
     * 通过读取文件头 magic bytes 判断真实类型
     */
    protected static function verifyMimeType(string $filePath, string $ext): void
    {
        $allowedMimes = [
            'jpg'  => ['image/jpeg'],
            'jpeg' => ['image/jpeg'],
            'png'  => ['image/png'],
            'gif'  => ['image/gif'],
            'webp' => ['image/webp'],
        ];

        // 使用 finfo 检测实际 MIME
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $realMime = $finfo->file($filePath);

        $expectedMimes = $allowedMimes[$ext] ?? [];
        if (!in_array($realMime, $expectedMimes, true)) {
            Log::warning("MIME type mismatch: ext={$ext}, real_mime={$realMime}, file={$filePath}");
            throw new BusinessException(
                ErrorCode::PARAM_IMAGE_TYPE_ERR,
                '文件类型与扩展名不匹配'
            );
        }
    }

    /**
     * 剥离 EXIF 信息（隐私保护）
     * 重新压缩图片以移除所有元数据
     */
    protected static function stripExif(string $filePath): void
    {
        try {
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if (in_array($ext, ['jpg', 'jpeg'])) {
                $image = imagecreatefromjpeg($filePath);
                if ($image) {
                    imagejpeg($image, $filePath, 85);
                    imagedestroy($image);
                }
            } elseif ($ext === 'png') {
                $image = imagecreatefrompng($filePath);
                if ($image) {
                    imagepng($image, $filePath, 8);
                    imagedestroy($image);
                }
            }
        } catch (\Exception $e) {
            // EXIF 剥离失败不影响主流程
            Log::warning("Strip EXIF failed: " . $e->getMessage());
        }
    }

    /**
     * 生成缩略图
     */
    protected static function generateThumbnail(string $fullPath, string $dir, string $filename): string
    {
        try {
            $ext = strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));
            $thumbFilename = pathinfo($filename, PATHINFO_FILENAME) . '_thumb.' . $ext;
            $thumbDir = public_path() . 'uploads/' . $dir;
            $thumbPath = $thumbDir . '/' . $thumbFilename;

            // 读取原图
            list($width, $height) = getimagesize($fullPath);

            if ($width <= self::THUMB_MAX_WIDTH) {
                // 原图够小，直接复制
                copy($fullPath, $thumbPath);
            } else {
                // 等比缩放
                $ratio = self::THUMB_MAX_WIDTH / $width;
                $newWidth = self::THUMB_MAX_WIDTH;
                $newHeight = (int)($height * $ratio);

                $thumb = imagecreatetruecolor($newWidth, $newHeight);

                switch ($ext) {
                    case 'jpg':
                    case 'jpeg':
                        $source = imagecreatefromjpeg($fullPath);
                        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagejpeg($thumb, $thumbPath, 75);
                        break;
                    case 'png':
                        $source = imagecreatefrompng($fullPath);
                        imagealphablending($thumb, false);
                        imagesavealpha($thumb, true);
                        imagecopyresampled($thumb, $source, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
                        imagepng($thumb, $thumbPath, 8);
                        break;
                    default:
                        copy($fullPath, $thumbPath);
                        break;
                }

                if (isset($source)) imagedestroy($source);
                imagedestroy($thumb);
            }

            return '/uploads/' . $dir . '/' . $thumbFilename;

        } catch (\Exception $e) {
            Log::warning("Thumbnail generation failed: " . $e->getMessage());
            // 缩略图失败则返回原图
            return '/uploads/' . $dir . '/' . $filename;
        }
    }

    /**
     * 上传视频
     *
     * @param \think\file\UploadedFile $file
     * @return array ['url' => '...']
     */
    public static function uploadVideo($file): array
    {
        if ($file->getSize() > self::MAX_VIDEO_SIZE) {
            throw new BusinessException(ErrorCode::UPLOAD_FAIL, '视频文件不能超过50MB');
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, self::ALLOWED_VIDEO_TYPES)) {
            throw new BusinessException(ErrorCode::UPLOAD_FAIL, '仅支持 mp4/mov 格式视频');
        }

        try {
            $dir = date('Ymd');
            $filename = md5(uniqid((string)mt_rand(), true)) . '.' . $ext;
            $savePath = 'uploads/' . $dir;

            $fullDir = public_path() . $savePath;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $file->move($fullDir, $filename);
            $url = '/' . $savePath . '/' . $filename;

            Log::info("Video uploaded: {$url}");

            return [
                'url' => $url,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Video upload failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::UPLOAD_FAIL);
        }
    }

    /**
     * 上传语音
     *
     * @param \think\file\UploadedFile $file
     * @return array ['url' => '...']
     */
    public static function uploadVoice($file): array
    {
        if ($file->getSize() > self::MAX_AUDIO_SIZE) {
            throw new BusinessException(ErrorCode::UPLOAD_FAIL, '语音文件不能超过10MB');
        }

        $ext = strtolower($file->extension());
        if (!in_array($ext, self::ALLOWED_AUDIO_TYPES)) {
            throw new BusinessException(ErrorCode::UPLOAD_FAIL, '仅支持 m4a/aac/mp3 格式音频');
        }

        try {
            $dir = date('Ymd');
            $filename = md5(uniqid((string)mt_rand(), true)) . '.' . $ext;
            $savePath = 'uploads/' . $dir;

            $fullDir = public_path() . $savePath;
            if (!is_dir($fullDir)) {
                mkdir($fullDir, 0755, true);
            }

            $file->move($fullDir, $filename);
            $url = '/' . $savePath . '/' . $filename;

            Log::info("Voice uploaded: {$url}");

            return [
                'url' => $url,
            ];
        } catch (BusinessException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error("Voice upload failed: " . $e->getMessage());
            throw new BusinessException(ErrorCode::UPLOAD_FAIL);
        }
    }
}
