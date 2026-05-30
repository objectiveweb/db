<?php

declare(strict_types=1);

namespace Objectiveweb\DB;

final class Expr
{
    public function __construct(private string $sql)
    {
    }

    public static function raw(string $sql): self
    {
        return new self($sql);
    }

    public function toSql(): string
    {
        return $this->sql;
    }
}
