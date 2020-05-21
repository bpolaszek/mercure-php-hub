<?php

namespace BenTools\MercurePHP\Transport;

final class Message implements \JsonSerializable
{
    private string $id;
    private ?string $data;
    private bool $private;
    private ?string $event;
    private ?int $retry;

    public function __construct(
        string $id,
        string $data = null,
        bool $private = false,
        ?string $event = null,
        ?int $retry = null
    ) {
        $this->id = $id;
        $this->data = $data;
        $this->private = $private;
        $this->event = $event;
        $this->retry = $retry;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getData(): string
    {
        return $this->data;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function __toString(): string
    {
        $props = [
            'id',
            'data',
            'event',
            'retry',
        ];

        $output = '';
        foreach ($props as $prop) {
            if (null === $this->{$prop}) {
                continue;
            }
            $output .= $prop . ':' . $this->{$prop} . \PHP_EOL;
        }

        return $output . \PHP_EOL;
    }

    public function jsonSerialize(): array
    {
        return \array_filter(
            [
                'id' => $this->id,
                'data' => $this->data,
                'private' => $this->private,
                'event' => $this->event,
                'retry' => $this->retry,
            ],
            fn ($value) => null !== $value
        );
    }

    public static function fromArray(array $event): self
    {
        return new self(
            $event['id'],
            $event['data'],
            $event['private'] ?? false,
            $event['type'] ?? null,
            $event['retry'] ?? null,
        );
    }
}
