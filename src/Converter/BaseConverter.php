<?php

/*
 * This file is part of the Github2Cypher package.
 *
 * (c) Christophe Willemsen <willemsen.christophe@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Ikwattro\Github2Cypher\Converter;

use Ikwattro\GithubEvent\Event\BaseEvent;
use Ikwattro\GithubEvent\Event\Repository;

abstract class BaseConverter implements EventConverterInterface
{
    protected function buildEventQuery(BaseEvent $event)
    {
        $q = 'MERGE (user:User {id: {user_id}})
        ON CREATE SET user.login = {user_login}
        MERGE (event:GithubEvent {id: {event_id}})
        ON CREATE SET event.time = {event_time}, event.user_id = {user_id}, event:' . $event->getEventType();

        $p = [
            'user_id' => $event->getActor()->getId(),
            'user_login' => $event->getActor()->getLogin(),
            'event_id' => $event->getEventId(),
            'event_time' => ($event->getCreatedTime()->getTimestamp() * 1000),
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

    protected function getRepoOwner(Repository $repository)
    {
        $explRepoName = explode('/', $repository->getName());
        $repo_owner = $explRepoName[count($explRepoName) -2];

        return $repo_owner;
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
        SET user.login = {user_login}';

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