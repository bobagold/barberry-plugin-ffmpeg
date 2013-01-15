<?php
namespace Barberry\Plugin\Ffmpeg;

use Barberry\Exception;

class VideoToVideoProcessor implements VideoProcessorInterface
{
    private $source;
    private $destination;
    private $command;
    private $targetContentType;
    private $defaultParams;

    public function __construct(\Barberry\ContentType $targetContentType)
    {
        $this->targetContentType = $targetContentType;
    }

    public function init($source, $destination, $command)
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->command = $command;
        $this->defaultParams = new FfmpegDefaultParams($this->source, $this->targetContentType);
    }

    public function process()
    {
        $from = escapeshellarg($this->source);
        $to = escapeshellarg($this->destination);

        $convert = "ffmpeg -y -i {$from} {$this->outputDimension()} {$this->audioCodec()} {$this->videoCodec()} {$this->rotationIndex()} -sn -strict experimental {$to} 2>&1";
        $cmd = "{$convert}";

        return exec('nice -n 0 ' . $cmd);
    }

    private function outputDimension()
    {
        if ($this->command->outputDimension()) {
            if (preg_match('/^([\d]+)x([\d]+)$/', $this->command->outputDimension())) {
                return '-s ' . $this->command->outputDimension();
            } else {
                throw new Exception\ConversionNotPossible('dimesions for video conversion is incorrect');
            }
        }
        return null;
    }

    private function audioCodec()
    {
        if ($this->command->audioCodec()) {
             return '-acodec ' . $this->command->audioCodec();
        } else {
            return '-acodec ' . $this->defaultParams->audioCodec();
        }
    }

    private function videoCodec()
    {
        if ($this->command->videoCodec()) {
            return '-vcodec ' . $this->command->videoCodec();
        } else {
            return '-vcodec ' . $this->defaultParams->videoCodec();
        }
    }

    private function rotationIndex()
    {
        if ($this->command->rotation()) {
            return '-vf transpose=' . $this->command->rotation();
        } else if ($this->defaultParams->rotationIndex()) {
            return '-vf transpose=' . $this->defaultParams->rotationIndex();
        }
        return null;
    }
}
