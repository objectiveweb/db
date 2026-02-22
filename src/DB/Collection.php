<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

class Collection implements \JsonSerializable, \ArrayAccess, \Countable, \IteratorAggregate
{
    /** @var list<mixed> */
    private array $data;
    private int $startIndex;
    private int $endIndex;
    private int $total;

    /** @param list<mixed> $data */
    public function __construct(array $data, int $startIndex = 0, ?int $endIndex = null, ?int $total = null)
    {
        $this->data = $data;
        $this->startIndex = $startIndex;
        $this->endIndex = $endIndex ?? (count($data) - 1);
        $this->total = $total ?? count($data);
    }

    /** @return list<mixed>|mixed */
    public function data(?int $key = null): mixed
    {
        if ($key !== null) {
            return $this->data[$key];
        }

        return $this->data;
    }

    public function total(): int
    {
        return $this->total;
    }

    public function contentRange(): string
    {
        return sprintf('items %d-%d/%d', $this->startIndex, $this->endIndex, $this->total);
    }

    public function render(string $contentType = 'application/json'): string
    {
        unset($contentType);
        return json_encode($this->data, JSON_THROW_ON_ERROR);
    }

    /** @return list<mixed> */
    public function jsonSerialize(): array
    {
        return $this->data;
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists((int) $offset, $this->data);
    }

    public function &offsetGet(mixed $offset): mixed
    {
        return $this->data[(int) $offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->data[] = $value;
            return;
        }

        $this->data[(int) $offset] = $value;
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->data[(int) $offset]);
    }

    public function count(): int
    {
        return count($this->data);
    }

    public function &getIterator(): \Traversable
    {
        foreach ($this->data as $key => &$val) {
            yield $key => $val;
        }
    }
}
