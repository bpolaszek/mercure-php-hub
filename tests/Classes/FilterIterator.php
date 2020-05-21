<?php

namespace BenTools\MercurePHP\Tests\Classes;

use Exception;
use Traversable;

final class FilterIterator implements \IteratorAggregate
{
    private iterable $items;

    /**
     * @var callable
     */
    private $filter;

    public function __construct(iterable $items, callable $filter = null)
    {
        $this->items = $items;
        $this->filter = $filter;
    }

    private function filtered()
    {
        if (null === $this->filter) {
            return $this->items;
        }

        if (\is_array($this->items)) {
            return \array_filter($this->items, $this->filter);
        }

        $iterator = $this->items;
        if (!$iterator instanceof \Iterator) {
            $iterator = new \IteratorIterator($iterator);
        }

        return new \CallbackFilterIterator($iterator, $this->filter);
    }

    public function getIterator()
    {
        $items = null === $this->filter ? $this->items : $this->filtered();

        foreach ($this->filtered() as $key => $value) {
            yield $key => $value;
        }
    }

    public function filter($filter): self
    {
        return new self($this, $filter);
    }

    public function asArray()
    {
        $filtered = $this->filtered();

        return \is_array($filtered) ? $filtered : \iterator_to_array($filtered);
    }
}
