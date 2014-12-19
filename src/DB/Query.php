<?php

namespace Bravado\DB;

class Query {

    private $command;

    private $params = [];

    public function get($key) {
        return $this->params[$key];
    }

    public function set($key, $value) {
        $this->params[$key] = $value;
    }

    public function append($key, $value) {
        $this->params[$key][] = $value;
    }

    public function __toString() {
        return $this->command.'...';
    }
}