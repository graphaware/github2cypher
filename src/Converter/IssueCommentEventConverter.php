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
        ON CREATE SET issue.number = {issue_number}, issue.title = {issue_title}, issue.body = {issue_body}
        WITH issue
        MATCH (repo:Repository {id: {repo_id}})
        MERGE (issue)-[:ISSUE_ON_REPOSITORY]->(repo)';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'issue_number' => $event->getIssue()->getNumber(),
            'issue_body' => $event->getIssue()->getBody(),
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
        OPTIONAL MATCH (issue)-[r:LAST_COMMENT]->(lastComment)
        DELETE r
        WITH issue, collect(lastComment) as lastComments, event
        CREATE (comment:IssueComment {id: {comment_id}})
        SET comment.body = {comment_body}
        MERGE (event)-[:WROTE_COMMENT]->(comment)
        MERGE (issue)-[:LAST_COMMENT]->(comment)
        FOREACH (x IN lastComments | CREATE (comment)-[:PREVIOUS_COMMENT]->(x))';

        $p = [
            'issue_id' => $event->getIssue()->getId(),
            'comment_id' => $event->getCommentId(),
            'comment_body' => $event->getComment(),
            'event_id' => $event->getEventId()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}