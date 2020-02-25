<?php

namespace App\Exceptions;

use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

class GleanViewException extends UnexpectedValueException
{
    protected $codes;
    protected $messages;
    protected $response;

    public function __construct(array $codes = null, array $messages = null, ResponseInterface $response = null)
    {
        $this->codes    = $codes ?? [];
        $this->messages = $messages ?? [];
        $this->response = $response;
    }

    public function __toString(): string
    {
        $output = [];
        foreach ($this->messages as $index => $message) {
            $output[] = ($this->codes[$index] ?? '---' ) . ": {$message}";
        }
        if ($this->response) {
            $output[] = (string)$this->response->getBody();
        }
        return implode("\n", $output);
    }

    public function hasResponse(): bool
    {
        return !!$this->response;
    }

    public function getResponse(): ?ResponseInterface
    {
        return $this->response;
    }
}
