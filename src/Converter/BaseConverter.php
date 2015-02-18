<?php

namespace Ikwattro\Github2Cypher\Converter;

use Ikwattro\GithubEvent\Event\BaseEvent;

abstract class BaseConverter implements EventConverterInterface
{
    protected function buildEventQuery(BaseEvent $event)
    {
        $q = 'MERGE (user:User {id: {user_id}})
        ON CREATE SET user.login = {user_login}
        MERGE (event:GithubEvent {id: {event_id}})
        ON CREATE SET event.time = {event_time}
        WITH user, event
        OPTIONAL MATCH (user)-[r:LAST_EVENT]->(lastEvent)
        DELETE r
        MERGE (user)-[:LAST_EVENT]->(event)
        WITH user, event, collect(lastEvent) as lastEvents
        FOREACH (x in lastEvents|MERGE (event)-[:NEXT]->(x))';

        $p = [
            'user_id' => $event->getActor()->getId(),
            'user_login' => $event->getActor()->getLogin(),
            'event_id' => $event->getEventId(),
            'event_time' => $event->getCreatedTime()->getTimestamp()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}