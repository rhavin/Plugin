<?php

namespace Q\Tools;

class StringStore
{
    private string $value = '';

    public function set(string $value): void
    {
        $this->value = $value;
    }

    public function get(): string
    {
        return $this->value;
    }
}
