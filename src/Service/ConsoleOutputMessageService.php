<?php

namespace App\Service;

class ConsoleOutputMessageService
{
    public const DIRECTORIES_NOT_FOUND_TYPE = 0;
    public const SOME_DIRECTORIES_MISSING_TYPE = 1;
    public const ALL_DIRECTORIES_FOUND_TYPE = 2;
    public const INVALID_ROOT_PATH_TYPE = 3;

    private $missingDirectories = [];
    private $foundDirectories = [];
    private $path;

    private const MESSAGE_TEXT_PATTERN_AND_TYPE = [
        0 => '<error>Directories %s was not found in root directory %s. Please, check configuration</error>',
        1 => '<info>Directories %s was not found in root directory %s.</info>',
        2 => '<info>All directories %s was not found in root directory %s.</info>',
        3 => '<error>Can not found path %s. Please, check it and try again.</error>',
    ];

    public function createMessageResponse(string $messageType): ConsoleMessagePayload
    {
        $messagePattern = self::MESSAGE_TEXT_PATTERN_AND_TYPE[$messageType];
        $manualLinks = [];

        switch ($messageType) {
            case self::INVALID_ROOT_PATH_TYPE:
                $message = sprintf($messagePattern, $this->path);
                break;
            default:
                $message = sprintf($messagePattern, implode(', ', $this->missingDirectories), $this->path);

                if (!empty($this->foundDirectories)) {
                    foreach ($this->foundDirectories as $foundDirectory) {
                        $manualLinks[] = $this->path . DIRECTORY_SEPARATOR . $foundDirectory;
                    }
                }

                break;
        }

        return (new ConsoleMessagePayload())
            ->setMessage($message)
            ->setManualLinks($manualLinks)
            ;
    }

    public function getMissingDirectories(): array
    {
        return $this->missingDirectories;
    }

    public function setMissingDirectories(array $missingDirectories): self
    {
        $this->missingDirectories = $missingDirectories;
        return $this;
    }

    public function getFoundDirectories(): array
    {
        return $this->foundDirectories;
    }

    public function setFoundDirectories(array $foundDirectories): self
    {
        $this->foundDirectories = $foundDirectories;
        return $this;
    }

    public function getPath()
    {
        return $this->path;
    }

    public function setPath($path): self
    {
        $this->path = $path;
        return $this;
    }
}
