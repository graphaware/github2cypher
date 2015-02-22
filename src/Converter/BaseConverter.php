<?php

namespace Ikwattro\Github2Cypher\Converter;

use Ikwattro\GithubEvent\Event\BaseEvent;
use Ikwattro\GithubEvent\Event\Repository;

abstract class BaseConverter implements EventConverterInterface
{
    protected function buildEventQuery(BaseEvent $event)
    {
        $q = 'MERGE (user:User {id: {user_id}})
        ON CREATE SET user.login = {user_login}
        CREATE (event:GithubEvent {id: {event_id}})
        SET event.type = {event_type}
        SET event :'.$event->getEventType().'
        MERGE (et:EventType {type:{event_type}})
        MERGE (event)-[:EVENT_TYPE]->(et)
        WITH user, event
        OPTIONAL MATCH (user)-[r:LAST_EVENT]->(lastEvent)
        DELETE r
        MERGE (user)-[:LAST_EVENT]->(event)
        WITH user, event, collect(lastEvent) as lastEvents
        FOREACH (x in lastEvents | CREATE (event)-[:NEXT]->(x))
        RETURN event';

        $p = [
            'user_id' => $event->getActor()->getId(),
            'user_login' => $event->getActor()->getLogin(),
            'event_id' => $event->getEventId(),
            'event_time' => $event->getCreatedTime()->getTimestamp(),
            'event_type' => $event->getEventType()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    protected function getRepoName(Repository $repository)
    {
        $explRepoName = explode('/', $repository->getName());
        $repo_name = $explRepoName[count($explRepoName) -1];

        return $repo_name;
    }

    protected function buildRepoOwnedByActorOrOrg(BaseEvent $event)
    {
        if ($event->hasBaseOrg()) {
            $owner_login = $event->getBaseOrg()->getName();
            $owner_id = $event->getBaseOrg()->getId();
        } else {
            $owner_login = $event->getActor()->getLogin();
            $owner_id = $event->getActor()->getId();
        }

        $q = 'MERGE (repo:Repository {id: {repo_id}})
        ON CREATE SET repo.name = {repo_name}
        MERGE (user:User {id: {user_id}})
        ON CREATE SET user.login = {user_login}';

        if ($event->hasBaseOrg()) {
            $q .= '
            SET user :Organisation';
        }

        $q .= '
        MERGE (repo)-[:OWNED_BY]->(user)';

        $p = [
            'repo_id' => $event->getRepository()->getId(),
            'repo_name' => $this->getRepoName($event->getRepository()),
            'user_id' => $owner_id,
            'user_login' => $owner_login
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}