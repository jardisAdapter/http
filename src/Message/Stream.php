<?php

declare(strict_types=1);

namespace JardisAdapter\Http\Message;

use Psr\Http\Message\StreamInterface;
use RuntimeException;

/** PSR-7 Stream implementation backed by php://temp. */
final class Stream implements StreamInterface
{
    /** @var resource|null */
    private $resource;

    public function __construct(string $content = '')
    {
        $resource = fopen('php://temp', 'r+');
        if ($resource === false) {
            throw new RuntimeException('Failed to open temp stream');
        }
        $this->resource = $resource;

        if ($content !== '') {
            fwrite($this->resource, $content);
            rewind($this->resource);
        }
    }

    public function __toString(): string
    {
        if ($this->resource === null) {
            return '';
        }

        $this->rewind();
        return $this->getContents();
    }

    public function close(): void
    {
        if ($this->resource !== null) {
            fclose($this->resource);
            $this->resource = null;
        }
    }

    /** @return resource|null */
    public function detach()
    {
        $resource = $this->resource;
        $this->resource = null;
        return $resource;
    }

    public function getSize(): ?int
    {
        if ($this->resource === null) {
            return null;
        }

        $stats = fstat($this->resource);
        return $stats !== false ? $stats['size'] : null;
    }

    public function tell(): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $position = ftell($this->resource);
        if ($position === false) {
            throw new RuntimeException('Unable to determine stream position');
        }

        return $position;
    }

    public function eof(): bool
    {
        return $this->resource === null || feof($this->resource);
    }

    public function isSeekable(): bool
    {
        return $this->resource !== null;
    }

    public function seek(int $offset, int $whence = SEEK_SET): void
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        fseek($this->resource, $offset, $whence);
    }

    public function rewind(): void
    {
        $this->seek(0);
    }

    public function isWritable(): bool
    {
        return $this->resource !== null;
    }

    public function write(string $string): int
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $result = fwrite($this->resource, $string);
        if ($result === false) {
            throw new RuntimeException('Unable to write to stream');
        }

        return $result;
    }

    public function isReadable(): bool
    {
        return $this->resource !== null;
    }

    public function read(int $length): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $result = fread($this->resource, max(1, $length));
        if ($result === false) {
            throw new RuntimeException('Unable to read from stream');
        }

        return $result;
    }

    public function getContents(): string
    {
        if ($this->resource === null) {
            throw new RuntimeException('Stream is detached');
        }

        $contents = stream_get_contents($this->resource);
        if ($contents === false) {
            throw new RuntimeException('Unable to read stream contents');
        }

        return $contents;
    }

    public function getMetadata(?string $key = null): mixed
    {
        if ($this->resource === null) {
            return $key !== null ? null : [];
        }

        $meta = stream_get_meta_data($this->resource);
        return $key !== null ? ($meta[$key] ?? null) : $meta;
    }
}
