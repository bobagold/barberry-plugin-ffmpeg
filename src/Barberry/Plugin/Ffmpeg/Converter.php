<?php
namespace Barberry\Plugin\Ffmpeg;

use Barberry\Plugin;
use Barberry\ContentType;

class Converter implements Plugin\InterfaceConverter
{
    private $tempDir;

    public function configure(ContentType $targetContentType, $tempDir)
    {
        $this->targetContentType = $targetContentType;
        $this->tempDir = $tempDir;
        return $this;
    }

    public function convert($bin, Plugin\InterfaceCommand $command = null)
    {
        $sourceFile = $this->createTempFile($bin);
        $destinationFile = $sourceFile . '.' . $this->targetContentType->standartExtention();

        if ($this->targetContentType->isImage()) {
            $cmd = $this->getCommandStringForVideoToImageConversion($sourceFile, $destinationFile, $command);
        }
        if ($this->targetContentType->isVideo()) {
            $cmd = $this->getCommandStringForVideoToVideoConversion($sourceFile, $destinationFile, $command);
        }

        exec('nice -n 0 ' . $cmd);
        unlink($sourceFile);

        if (is_file($destinationFile)) {
            if (filesize($destinationFile)) {
                $bin = file_get_contents($destinationFile);
                unlink($destinationFile);
            } else {
                unlink($destinationFile);
                throw new FfmpegException('failed to convert to destination file');
            }
        }

        if ($command->outputDimension() && $this->targetContentType->isImage()) {
            $bin = $this->resizeWithImagemagick($bin, $command->outputDimension());
        }

        return $bin;
    }

    private function createTempFile($content)
    {
        $tempFile = tempnam($this->tempDir, 'ffmpeg_');
        chmod($tempFile, 0664);
        file_put_contents($tempFile, $content, FILE_APPEND);
        return $tempFile;
    }

    private function getCommandStringForVideoToImageConversion($source, $destination, $command)
    {
        $from = escapeshellarg($source);
        $rotation = $this->getRotation($source, $command);
        $time = $this->getScreenshotTime($from, $command);
        $to = escapeshellarg($destination);
        return "ffmpeg {$time} -vframes 1 -i {$from} {$rotation} -f image2 {$to} 2>&1";
    }

    private function getCommandStringForVideoToVideoConversion($source, $destination, $command)
    {
        $from = escapeshellarg($source);
        $outputDimension = $this->getOutputDimension($command);
        $audioParams = $this->getAudioParams($command);
        $videoParams = $this->getVideoParams($source, $command);
        $rotation = $this->getRotation($source, $command);
        $to = escapeshellarg($destination);
        return "ffmpeg -i {$from} {$outputDimension} {$audioParams} {$videoParams} {$rotation} -sn -strict experimental {$to} 2>&1";
    }

    private function getOutputDimension($command)
    {
        if ($command->outputDimension()) {
            return '-s ' . escapeshellarg($command->outputDimension());
        }
        return '';
    }

    private function getRotation($from, $command)
    {
        $rotation = '';
        if ($command->rotation()) {
            $rotation = '-vf transpose=' . escapeshellarg($command->rotation());
        } else {
            $out = shell_exec('ffmpeg -i ' . $from . ' 2>&1 | grep rotation:');
            if (preg_match('/^rotation: ([\d]+)$/', $out, $matches)) {
                $rotation = '-vf transpose=' . escapeshellarg($matches[1]/90);
            }
        }
        return $rotation;
    }

    private function getScreenshotTime($from, $command)
    {
        $seconds = 1;
        if ($command->screenshotTime()) {
            $seconds = $command->screenshotTime();
        } else {
            // get random time
            $out = shell_exec('ffmpeg -i ' . $from . ' 2>&1 | grep Duration');
            if (preg_match('/Duration: (\d+):(\d+):(\d+)/', $out, $time)) {
                $videoLength = ($time[1]*3600) + ($time[2]*60) + $time[3];
                $seconds = rand(1, $videoLength);
            }
        }
        return '-ss ' . escapeshellarg($seconds);
    }

    private function getAudioParams($command)
    {
        $bitrate = '';
        $codec = '-acodec copy';

        if ($command->audioBitrate()) {
            $bitrate = '-ab ' . escapeshellarg($command->audioBitrate() . 'k');
        }
        if ($command->audioCodec()) {
            $codec = '-acodec ' . escapeshellarg($command->audioCodec());
        }
        return $bitrate . ' ' . $codec;
    }

    private function getVideoParams($source, $command)
    {
        $bitrate = '';
        $codec = '-vcodec copy';

        if ($command->videoBitrate()) {
            $bitrate = '-b ' . escapeshellarg($command->videoBitrate() . 'k');
        } else {
            $out = shell_exec('ffmpeg -i ' . $source . ' 2>&1 | grep bitrate:');
            if (preg_match('/^bitrate: (\d+)$/', $out, $matches)) {
                $bitrate = '-b ' . escapeshellarg($matches[1] . 'k');
            }
        }
        if ($command->videoCodec()) {
            $codec = '-vcodec ' . escapeshellarg($command->videoCodec());
        }
        return $bitrate . ' ' . $codec;
    }

    protected function resizeWithImagemagick($bin, $commandString)
    {
        $from = ucfirst(ContentType::byString($bin)->standartExtention());
        $to = ucfirst($this->targetContentType->standartExtention());
        $directionClass = '\\Barberry\\Direction\\' . $from . 'To' . $to . 'Direction';
        $direction = new $directionClass($commandString);
        return $direction->convert($bin);
    }
}