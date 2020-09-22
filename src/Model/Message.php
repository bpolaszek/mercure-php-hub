<?php

namespace BenTools\MercurePHP\Model;

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

    public function getData(): ?string
    {
        return $this->data;
    }

    public function isPrivate(): bool
    {
        return $this->private;
    }

    public function __toString(): string
    {
        $output = 'id:' . $this->id . \PHP_EOL;

        if (null !== $this->event) {
            $output .= 'event:' . $this->event . \PHP_EOL;
        }

        if (null !== $this->retry) {
            $output .= 'retry:' . $this->retry . \PHP_EOL;
        }

        if (null !== $this->data) {
            // If $data contains line breaks, we have to serialize it in a different way
            if (false !== \strpos($this->data, \PHP_EOL)) {
                $lines = \explode(\PHP_EOL, $this->data);
                foreach ($lines as $line) {
                    $output .= 'data:' . $line . \PHP_EOL;
                }
            } else {
                $output .= 'data:' . $this->data . \PHP_EOL;
            }
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
            $event['data'] ?? null,
            $event['private'] ?? false,
            $event['type'] ?? null,
            $event['retry'] ?? null,
        );
    }
}
