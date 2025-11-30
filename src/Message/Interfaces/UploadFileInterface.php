<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message\Interfaces;

interface UploadFileInterface
{
    public function getStream(): StreamInterface;

    public function moveTo(string $targetPath): void;

    public function getSize(): ?int;

    public function getError(): int;

    public function getClientFilename(): ?string;

    public function getClientMediaType(): ?string;
}
