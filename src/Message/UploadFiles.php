<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\StreamInterface;
use PhpEasyHttp\Http\Message\Interfaces\UploadFileInterface;
use RuntimeException;

class UploadFiles implements UploadFileInterface
{
    private const ERRORS = [
        UPLOAD_ERR_OK => true,
        UPLOAD_ERR_INI_SIZE => true,
        UPLOAD_ERR_FORM_SIZE => true,
        UPLOAD_ERR_PARTIAL => true,
        UPLOAD_ERR_NO_FILE => true,
        UPLOAD_ERR_NO_TMP_DIR => true,
        UPLOAD_ERR_CANT_WRITE => true,
        UPLOAD_ERR_EXTENSION => true,
    ];

    private ?string $clientFilename;

    private ?string $clientMediaType;

    private int $error;

    private ?string $file = null;

    private bool $moved = false;

    private int $size;

    private ?StreamInterface $stream = null;

    public function __construct(mixed $streamOrFile, int $size, int $errorStatus, ?string $clientFilename = null, ?string $clientMediaType = null)
    {
        if (! isset(self::ERRORS[$errorStatus])) {
            throw new InvalidArgumentException('Upload file error status must be a valid "UPLOAD_ERR_*" constant.');
        }

        $this->error = $errorStatus;
        $this->size = $size;
        $this->clientFilename = $clientFilename;
        $this->clientMediaType = $clientMediaType;

        if (UPLOAD_ERR_OK === $this->error) {
            if (is_string($streamOrFile) && $streamOrFile !== '') {
                $this->file = $streamOrFile;
            } elseif (is_resource($streamOrFile)) {
                $this->stream = new Stream($streamOrFile);
            } elseif ($streamOrFile instanceof StreamInterface) {
                $this->stream = $streamOrFile;
            } else {
                throw new InvalidArgumentException('Invalid stream or file provided for UploadedFile');
            }
        }
    }

    private function validateActive(): void
    {
        if (UPLOAD_ERR_OK !== $this->error) {
            throw new RuntimeException('Cannot retrieve stream due to upload error');
        }

        if ($this->moved) {
            throw new RuntimeException('Cannot retrieve stream after it has already been moved');
        }
    }

    public function getStream(): StreamInterface
    {
        $this->validateActive();

        if ($this->stream instanceof StreamInterface) {
            return $this->stream;
        }

        if (false === $resource = fopen($this->file, 'r')) {
            throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $this->file, error_get_last()['message'] ?? ''));
        }

        return new Stream($resource);
    }

    public function moveTo(string $targetPath): void
    {
        $this->validateActive();

        if ($targetPath === '') {
            throw new InvalidArgumentException('Invalid path provided for move operation; must be a non-empty string');
        }

        if (null !== $this->file) {
            $this->moved = 'cli' === PHP_SAPI ? rename($this->file, $targetPath) : move_uploaded_file($this->file, $targetPath);
            if (false === $this->moved) {
                throw new RuntimeException(sprintf('Uploaded file could not be moved to "%s": %s', $targetPath, error_get_last()['message'] ?? ''));
            }
        } else {
            $stream = $this->getStream();
            if ($stream->isSeekable()) {
                $stream->rewind();
            }

            if (false === $resource = fopen($targetPath, 'w')) {
                throw new RuntimeException(sprintf('The file "%s" cannot be opened: %s', $targetPath, error_get_last()['message'] ?? ''));
            }

            $dest = new Stream($resource);

            while (! $stream->eof()) {
                if (! $dest->write($stream->read(1048576))) {
                    break;
                }
            }
            $this->moved = true;
        }
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function getError(): int
    {
        return $this->error;
    }

    public function getClientFilename(): ?string
    {
        return $this->clientFilename;
    }

    public function getClientMediaType(): ?string
    {
        return $this->clientMediaType;
    }
}
