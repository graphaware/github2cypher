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

use Ikwattro\GithubEvent\Event\BaseEvent,
    Ikwattro\GithubEvent\Event\IssuesEvent;

class IssuesEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildRepoOwnedByActorOrOrg($event);
        $statements[] = $this->buildIssueQuery($event);
        $statements[] = $this->buildEventToIssueQuery($event);

        return $statements;

    }

    private function buildIssueQuery(IssuesEvent $event)
    {
        $q = 'MERGE (issue:Issue {id: {issue_id}})
        ON CREATE SET issue.number = {issue_number}, issue.title = {issue_title}';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'issue_number' => $event->getIssue()->getNumber(),
            'issue_title' => $event->getIssue()->getTitle()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildEventToIssueQuery(IssuesEvent $event)
    {
        $action = $event->getAction() . '_ISSUE';
        $q = 'MATCH (issue:Issue {id: {issue_id}})
        MATCH (repo:Repository {id: {repo_id}})
        MATCH (event:GithubEvent {id: {event_id}})
        MERGE (event)-[:'.$action.']->(issue)
        MERGE (issue)-[:ISSUE_ON_REPOSITORY]->(repo)';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'repo_id' => $event->getRepository()->getId(),
            'event_id' => $event->getEventId()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}