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
use Ikwattro\GithubEvent\Event\WatchEvent;

class WatchEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildWatchQuery($event);

        return $statements;

    }

    private function buildWatchQuery(WatchEvent $event)
    {
        $p = [];
        $q = 'MATCH (event:GithubEvent {id: {event_id}})
        MERGE (repo:Repository {id: {repo_id}})
        ON CREATE SET repo.name = {repo_name}
        MERGE (event)-[:WATCHED_REPOSITORY]->(repo)
        MERGE (actor:User {id: {user_id}})
        ON CREATE SET actor.login = {user_login}
        MERGE (actor)-[:WATCH]->(repo)';

        $p['event_id'] = $event->getEventId();
        $p['repo_id'] = $event->getRepository()->getId();
        $p['repo_name'] = $this->getRepoName($event->getRepository());
        $p['user_id'] = $event->getActor()->getId();
        $p['user_login'] = $event->getActor()->getLogin();

        if ($event->hasBaseOrg()) {
            $q .= '
            MERGE (user:User {id: {owner_id}})
            ON CREATE SET user.login = {owner_name}
            SET user :Organisation
            MERGE (repo)-[:OWNED_BY]->(user)';

            $p['owner_id'] = $event->getBaseOrg()->getId();
            $p['owner_name'] = $event->getBaseOrg()->getName();
        }

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}