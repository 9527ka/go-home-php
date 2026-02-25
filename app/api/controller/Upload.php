<?php
declare(strict_types=1);

namespace app\api\controller;

use app\common\service\UploadService;
use think\Response;

class Upload extends BaseApi
{
    /**
     * 上传图片
     * POST /api/upload/image
     *
     * @header Authorization Bearer <token>
     * @body   file  file 图片文件(jpg/png/gif/webp, ≤5MB)
     */
    public function image(): Response
    {
        $file = $this->request->file('file');

        if (!$file) {
            return $this->error(2001, '请选择要上传的图片');
        }

        $result = UploadService::uploadImage($file);

        return $this->success($result, '上传成功');
    }

    /**
     * 批量上传图片
     * POST /api/upload/images
     *
     * @body files[] file 图片文件列表(≤9张)
     */
    public function images(): Response
    {
        $files = $this->request->file('files');

        if (empty($files) || !is_array($files)) {
            return $this->error(2001, '请选择要上传的图片');
        }

        if (count($files) > 9) {
            return $this->error(2003, '最多上传9张图片');
        }

        $results = [];
        foreach ($files as $file) {
            $results[] = UploadService::uploadImage($file);
        }

        return $this->success($results, '上传成功');
    }

    /**
     * 上传视频
     * POST /api/upload/video
     *
     * @header Authorization Bearer <token>
     * @body   file  file 视频文件(mp4/mov, ≤50MB)
     */
    public function video(): Response
    {
        $file = $this->request->file('file');

        if (!$file) {
            return $this->error(2001, '请选择要上传的视频');
        }

        $result = UploadService::uploadVideo($file);

        return $this->success($result, '上传成功');
    }

    /**
     * 上传语音
     * POST /api/upload/voice
     *
     * @header Authorization Bearer <token>
     * @body   file  file 音频文件(m4a/aac/mp3, ≤10MB)
     */
    public function voice(): Response
    {
        $file = $this->request->file('file');

        if (!$file) {
            return $this->error(2001, '请选择要上传的语音');
        }

        $result = UploadService::uploadVoice($file);

        return $this->success($result, '上传成功');
    }
}
