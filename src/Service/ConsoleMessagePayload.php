<?php

namespace App\Service;

class ConsoleMessagePayload
{
    /** @var string */
    private $message;
    /** @var array */
    private $manualLinks;

    /**
     * @return string
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * @param string $message
     * @return ConsoleMessagePayload
     */
    public function setMessage(string $message): ConsoleMessagePayload
    {
        $this->message = $message;
        return $this;
    }

    /**
     * @return array
     */
    public function getManualLinks(): array
    {
        return $this->manualLinks;
    }

    /**
     * @param array $manualLinks
     * @return ConsoleMessagePayload
     */
    public function setManualLinks(array $manualLinks): ConsoleMessagePayload
    {
        $this->manualLinks = $manualLinks;
        return $this;
    }
}
