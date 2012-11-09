<?php
namespace Barberry\Plugin\Ffmpeg;

use Barberry\Plugin;

class Monitor implements Plugin\InterfaceMonitor
{
    private $tempDir;

    public function configure($tempDir)
    {
        $this->tempDir = $tempDir;
        return $this;
    }

    /**
     * @return array of error messages
     */
    public function reportUnmetDependencies()
    {
        $errors = array();
        if (!$this->ffmpegIsInstalled()) {
            $errors[] = 'Please install ffmpeg';
        }
        return $errors;
    }

    /**
     * @return bool whether ffmpeg is installed
     */
    protected function ffmpegIsInstalled()
    {
        return preg_match('/^\/\w+/', exec("which ffmpeg")) ? true : false;
    }

    /**
     * @return array of error messages
     */
    public function reportMalfunction()
    {
        $report = $this->reportDirectoryIsWritable($this->tempDir);
        return (!is_null($report)) ? array($report) : array();
    }

    private function reportDirectoryIsWritable($dir)
    {
        return (is_writable($dir)) ? null : 'ERROR: Temporary directory is not writable (Ffmpeg plugin)';
    }
}