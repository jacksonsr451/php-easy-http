<?php

namespace PhpEasyHttp\Http\Message\Interfaces;

interface StreamInterface
{
    public function __toString(): string;

    public function close(): void;

    public function detach();

    public function getSize(): int|null;

    public function tell(): int;

    public function eof(): bool;

    public function isSeekable(): bool;

    public function seek($offset, $whence = SEEK_SET): void;

    public function rewind(): void;

    public function isWritable(): bool;

    public function write($string): int;

    public function isReadable(): bool;

    public function read($length): string;

    public function getContents(): string;

    public function getMetadata($key = null): null|array;
}
