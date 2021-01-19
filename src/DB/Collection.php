<?php

namespace Objectiveweb\DB;

class Collection implements \JsonSerializable, \ArrayAccess, \Countable, \IteratorAggregate
{
    private $data;
    private $startIndex;
    private $endIndex;
    private $total;

    function __construct($data, $startIndex = 0, $endIndex = null, $total = null)
    {
        $this->data = $data;

        $this->startIndex = $startIndex;
        $this->endIndex = $endIndex === null ? count($data) - 1 : $endIndex;
        $this->total = $total === null ? count($data) : $total;

    }

    function data($key = null)
    {
        if ($key !== null) {
            return $this->data[$key];
        } else {
            return $this->data;
        }
    }

    public function total()
    {
        return $this->total;
    }

    function render($content_type = "")
    {
        switch ($content_type) {
            default:
                header(sprintf("Content-Range: items %d-%d/%d", $this->startIndex, $this->endIndex, $this->total));
                return json_encode($this->data);
        }
    }

    public function jsonSerialize()
    {
        return $this->data;
    }

    public function offsetExists($offset)
    {
        return array_key_exists($offset, $this->data);
    }

    public function &offsetGet($offset)
    {
        return $this->data[$offset];
    }

    public function offsetSet($offset, $value)
    {
        $this->data[$offset] = $value;
    }

    public function offsetUnset($offset)
    {
        unset($this->data[$offset]);
    }

    public function count()
    {
        return count($this->data);
    }

    public function &getIterator()
    {
        foreach($this->data as $key => &$val) {
            yield $key => $val;
        }
    }
}
