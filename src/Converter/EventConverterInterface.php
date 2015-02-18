<?php

namespace Ikwattro\Github2Cypher\Converter;

use Ikwattro\GithubEvent\Event\BaseEvent;

interface EventConverterInterface
{
    public function convert(BaseEvent $event);
}