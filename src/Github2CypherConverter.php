<?php

namespace Ikwattro\Github2Cypher;

use Ikwattro\Github2Cypher\Converter\CreateEventConverter,
    Ikwattro\Github2Cypher\Converter\PushEventConverter,
    Ikwattro\Github2Cypher\Converter\PullRequestEventConverter;
use Ikwattro\GithubEvent\Event\BaseEvent;

class Github2CypherConverter
{
    /**
     * @var array The collections of converters instances
     */
    private $converters;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->converters = [
            'PullRequestEvent' => new PullRequestEventConverter(),
            'PushEvent' => new PushEventConverter(),
            'CreateEvent' => new CreateEventConverter()
        ];
    }

    /**
     * @param BaseEvent $event The Github Event to convert
     * @return array The collection of statements
     * @throw \InvalidArgumentException When no converter is found for the passed event
     */
    public function convert(BaseEvent $event)
    {
        if (!array_key_exists($event->getEventType(), $this->converters)) {

            throw new \InvalidArgumentException(sprintf('No converter found for %s', $event->getEventType()));
        }

        return $this->converters[$event->getEventType()]->convert($event);
    }

    /**
     * Returns the collection of indexes to create as <code>label => property</code>
     *
     * @return array
     */
    public function getInitialSchemaIndexes()
    {
        return array(
            array('label' => 'Repository', 'property' => 'name'),
            array('label' => 'Branch', 'property' => 'name'),
            array('label' => 'GithubEvent', 'property' => 'type'),
            array('label' => 'EventType', 'property' => 'type'),
            array('label' => 'Tag', 'property' => 'tag_name')
        );
    }

    /**
     * @return array the collection of unique constraints to create as <code>label => property</code>
     */
    public function getInitialSchemaConstraints()
    {
        return array(
            array('label' => 'User', 'property' => 'login'),
            array('label' => 'User', 'property' => 'id'),
            array('label' => 'Repository', 'property' => 'id'),
            array('label' => 'Branch', 'property' => 'reference'),
            array('label' => 'GithubEvent', 'property' => 'id'),
            array('label' => 'PullRequest', 'property' => 'id'),
            array('label' => 'Push', 'property' => 'id'),
            array('label' => 'Tag', 'property' => 'reference')
        );
    }
}