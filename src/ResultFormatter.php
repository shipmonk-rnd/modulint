<?php declare(strict_types = 1);

namespace ShipMonk\Modulint;

interface ResultFormatter
{

    public function format(Result $result): void;

}
