<?php

declare(strict_types=1);

namespace PhpEasyHttp\Http\Message;

use InvalidArgumentException;
use PhpEasyHttp\Http\Message\Interfaces\StreamInterface;
use RuntimeException;
use Throwable;

class Stream implements StreamInterface
{
    /** @var resource|null */
    private $stream;

    private ?int $size = null;

    private ?bool $seekable = null;

    private ?bool $writable = null;

    private ?bool $readable = null;

    private const READ_WRITE_MODE = [
        'read' => ['r', 'r+', 'w+', 'a+', 'x+', 'c+'],
        'write' => ['r', 'r+', 'w', 'w+', 'a', 'a+', 'x', 'x+', 'c', 'c+'],
    ];

    public function __construct(mixed $body = null)
    {
        if (! is_string($body) && ! is_resource($body) && $body !== null) {
            throw new InvalidArgumentException('Invalid stream body provided.');
        }

        if ($body === null) {
            $resource = fopen('php://temp', 'w+');
            if ($resource === false) {
                throw new RuntimeException('Unable to create temporary stream.');
            }
            $body = $resource;
        } elseif (is_string($body)) {
            $resource = fopen('php://temp', 'w+');
            if ($resource === false) {
                throw new RuntimeException('Unable to create temporary stream.');
            }
            fwrite($resource, $body);
            $body = $resource;
        }

        $this->stream = $body;
        if ($this->isSeekable()) {
            fseek($this->stream, 0, SEEK_CUR);
        }
    }

    public function close(): void
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
        $this->detach();
        $this->size = null;
    }
    
    public function detach(): mixed
    {
        $resource = $this->stream;
        unset($this->stream);
        $this->size = null;
        return $resource;
    }
    
    public function getSize(): ?int
    {
        if ($this->size !== null) {
            return $this->size;
        }

        if ($this->stream === null) {
            return null;
        }

        $status = fstat($this->stream);
        if ($status === false) {
            return null;
        }

        $this->size = $status['size'] ?? null;
        return $this->size;
    }
    
    public function tell(): int
    {
        if ($this->stream === null) {
            throw new RuntimeException("Unable to get current possition!");
        }
        $possition = ftell($this->stream);

        if ($possition === false) {
            throw new RuntimeException("Unable to get current possition!");
        }

        return $possition;
    }
    
    public function eof(): bool
    {
        return $this->stream !== null && feof($this->stream);
    }
    
    public function isSeekable(): bool
    {
        if ($this->seekable === null) {
            $this->seekable = $this->getMetadata('seekable') ?? false;
        }

        return $this->seekable;
    }
    
    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if (! $this->isSeekable()) {
            throw new RuntimeException("Stream is not seekable!");
        }
        if (fseek($this->stream, $offset, $whence) === -1) {
            throw new RuntimeException("Unable to seek stream position {$offset}!");
        }
    }
    
    public function rewind(): void
    {
        $this->seek(0);
    }
    
    public function isWritable(): bool
    {
        if (! is_resource($this->stream)) {
            return false;
        }
        if ($this->writable === null) {
            $mode = $this->getMetadata('mode');
            $this->writable = is_string($mode) && in_array($mode, self::READ_WRITE_MODE['write'], true);
        }
        return $this->writable;
    }
    
    public function write(string $string): int
    {
        if (! $this->isWritable()) {
            throw new RuntimeException('Stream is not writable');
        }
        $result = fwrite($this->stream, $string);
        if ($result === false) {
            throw new RuntimeException("Unable to write to stream!");
        }
        $this->size = null;
        return $result;
    }
    
    public function isReadable(): bool
    {
        if (! is_resource($this->stream)) {
            return false;
        }
        if ($this->readable === null) {
            $mode = $this->getMetadata('mode');
            $this->readable = is_string($mode) && in_array($mode, self::READ_WRITE_MODE['read'], true);
        }
        return $this->readable;
    }
    
    public function read(int $length): string
    {
        if (! $this->isReadable()) {
            throw new RuntimeException('Stream is not readable');
        }
        $result = fread($this->stream, $length);
        if ($result === false) {
            throw new RuntimeException("Unable to read the stream!");
        }
        return $result;
    }
    
    public function getContents(): string
    {
        if (! is_resource($this->stream)) {
            throw new RuntimeException("Unable to read stream contents!");
        }
        $contents = stream_get_contents($this->stream);
        if ($contents === false) {
            throw new RuntimeException("Unable to read stream contents!");
        }
        return $contents;
    }
    
    public function getMetadata(?string $key = null): mixed
    {
        if (! is_resource($this->stream)) {
            return $key === null ? [] : null;
        }

        $meta = stream_get_meta_data($this->stream);
        if ($key === null) {
            return $meta;
        }

        return $meta[$key] ?? null;
    }
    public function __toString(): string
    {
        try {
            if ($this->isSeekable()) {
                $this->rewind();
            }
            return $this->getContents();
        } catch (Throwable $th) {
            return '';
        }
    }
}
