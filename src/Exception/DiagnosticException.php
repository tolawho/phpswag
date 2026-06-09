<?php

namespace PhpSwag\Exception;

class DiagnosticException extends \RuntimeException
{
    private ?string $filePath = null;
    private ?int $lineNumber = null;

    public function __construct(
        string $message,
        int $code = 0,
        ?\Throwable $previous = null,
        ?string $filePath = null,
        ?int $lineNumber = null
    ) {
        if ($filePath !== null || $lineNumber !== null) {
            $suffix = '';
            if ($filePath !== null) {
                $suffix .= " in " . $filePath;
            }
            if ($lineNumber !== null) {
                $suffix .= " on line " . $lineNumber;
            }
            if (!str_contains($message, ' in ') && !str_contains($message, ' on line ')) {
                $message .= $suffix;
            }
        }
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->lineNumber = $lineNumber;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): void
    {
        $this->filePath = $filePath;
    }

    public function getLineNumber(): ?int
    {
        return $this->lineNumber;
    }

    public function setLineNumber(?int $lineNumber): void
    {
        $this->lineNumber = $lineNumber;
    }
}
