<?php
namespace Barberry\Plugin\Ffmpeg;

class FfmpegDefaultParams
{
    private $sourceFile;
    private $targetContentType;
    private $metaData;

    public function __construct($source, $targetContentType)
    {
        $this->sourceFile = $source;
        $this->targetContentType = $targetContentType;
        $this->metaData = $this->fileMetaData();
    }

    private function fileMetaData()
    {
        $data = array();

        $out = shell_exec('ffmpeg -i ' . $this->sourceFile . ' 2>&1 | grep -E "Duration|rotate"');
        if (preg_match('/Duration:(\d+):(\d+):(\d+)/', str_replace(' ', '', $out), $time)) {
            $data['duration'] = ($time[1]*3600) + ($time[2]*60) + $time[3];
        }
        if (preg_match('/rotate:([\d]+)$/', str_replace(' ', '', $out), $matches)) {
            $data['rotate'] = intval($matches[1]/90);
        }

        return $data;
    }

    public function rotationIndex()
    {
        return isset($this->metaData['rotate']) ? $this->metaData['rotate'] : null;
    }

    public function screenshotTime()
    {
        return isset($this->metaData['duration']) ? rand(1, $this->metaData['duration']) : 1;
    }

    public function audioCodec()
    {
        return self::defaultAudioCodec($this->targetContentType->standardExtension());
    }

    public function videoCodec()
    {
        return self::defaultVideoCodec($this->targetContentType->standardExtension());
    }

    private function defaultAudioCodec($ext)
    {
        $codecs = array(
            'flv' => 'aac',
            'webm' => 'libvorbis',
            'wmv' => 'adpcm_ima_wav',
            'mpeg' => 'aac',
            'avi' => 'ac3',
            'mkv' => 'libvorbis',
            'mov' => 'aac',
            'mp4' => 'aac',
            'mpg' => 'ac3',
            'ogv' => 'libvorbis',
            '3gp' => 'aac'
        );
        return isset($codecs[$ext]) ? $codecs[$ext] : 'copy';
    }

    private function defaultVideoCodec($ext)
    {
        $codecs = array(
            'flv' => 'libx264',
            'webm' => 'libvpx',
            'wmv' => 'wmv1',
            'mpeg' => 'mpeg4',
            'avi' => 'mpeg4',
            'mkv' => 'libtheora',
            'mov' => 'mpeg4',
            'mp4' => 'libx264',
            'mpg' => 'mpeg1video',
            'ogv' => 'libtheora',
            '3gp' => 'mpeg4'
        );
        return isset($codecs[$ext]) ? $codecs[$ext] : 'copy';
    }
}
