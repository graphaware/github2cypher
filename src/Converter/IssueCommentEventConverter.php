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
    Ikwattro\GithubEvent\Event\IssueCommentEvent;

class IssueCommentEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildRepoOwnedByActorOrOrg($event);
        $statements[] = $this->buildIssueQuery($event);
        $statements[] = $this->buildCommentToIssueQuery($event);

        return $statements;
    }

    private function buildIssueQuery(IssueCommentEvent $event)
    {
        $q = 'MERGE (issue:Issue {id: {issue_id}})
        ON CREATE SET issue.number = {issue_number}, issue.title = {issue_title}
        WITH issue
        MATCH (repo:Repository {id: {repo_id}})
        MERGE (issue)-[:ISSUE_ON_REPOSITORY]->(repo)';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'issue_number' => $event->getIssue()->getNumber(),
            'issue_title' => $event->getIssue()->getTitle(),
            'repo_id' => $event->getRepository()->getId()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildCommentToIssueQuery(IssueCommentEvent $event)
    {
        $q = 'MATCH (issue:Issue {id: {issue_id}})
        MATCH (event:GithubEvent {id: {event_id}})
        WITH issue, event
        MERGE (comment:IssueComment {id: {comment_id}})
        ON CREATE SET comment.time = event.time
        MERGE (event)-[:WROTE_COMMENT]->(comment)
        MERGE (comment)<-[:ISSUE_COMMENT]-(issue)';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'comment_id' => $event->getCommentId(),
            'event_id' => $event->getEventId()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}