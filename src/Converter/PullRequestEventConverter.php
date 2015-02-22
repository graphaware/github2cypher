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
use Ikwattro\GithubEvent\Event\PullRequestEvent;

class PullRequestEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildPrQuery($event);
        $statements[] = $this->buildHeadBranch($event);
        $statements[] = $this->buildBaseBranch($event);
        $forksQuery = $this->buildForks($event);
        if (null !== $forksQuery) {
            $statements[] = $forksQuery;
        }

        return $statements;
    }

    private function buildPrQuery(PullRequestEvent $event)
    {
        $action = $event->isCloseAction() ? 'CLOSED_PR' : 'OPENED_PR';
        $pr = $event->getPullRequest();
        $q = 'MATCH (event:GithubEvent {id: {event_id}})
        MERGE (pr:PullRequest {id: {pr_id}})
        ON CREATE SET pr.number = {pr_number},
        pr.title = {pr_title},
        pr.body = {pr_body}
        MERGE (event)-[:'.$action.']->(pr)';

        if ($event->getPullRequest()->isMerged() && $event->isCloseAction()) {
            $q .= '
            MERGE (event)-[:MERGED_PR]->(pr)';
        }

        if ($event->getPullRequest()->getUser()->isOrg()) {
            $q .= '
            SET user :Organisation';
        }
        $p = [
            'pr_id' => $pr->getId(),
            'pr_number' => $pr->getNumber(),
            'pr_title' => $pr->getTitle(),
            'pr_body' => $pr->getBody(),
            'event_id' => $event->getEventId()
        ];

        return array('query' => $q, 'params' => $p);
    }

    private function buildHeadBranch(PullRequestEvent $event)
    {
        $q = 'MATCH (pr:PullRequest {id: {pr_id}})
        MERGE (repo:Repository {id: {repo_id}})
        ON CREATE SET repo.name = {repo_name}
        MERGE (user:User {id: {owner_id}})
        ON CREATE SET user.login = {owner_login}';

        if ($event->getPullRequest()->getHead()->getRepository()->getOwner()->isOrg()) {
            $q .= '
            SET user :Organisation';
        }

        $q .= '
        MERGE (branch:Branch {reference: {br_ref}})
        ON CREATE SET branch.name = {br_name}
        MERGE (pr)-[:HEAD]->(branch)
        MERGE (branch)-[:BRANCH_OF]->(repo)
        MERGE (repo)-[:OWNED_BY]->(user)';

        $p = [
            'pr_id' => $event->getPullRequest()->getId(),
            'repo_id' => $event->getPullRequest()->getHead()->getRepository()->getId(),
            'repo_name' => $event->getPullRequest()->getHead()->getRepository()->getName(),
            'owner_id' => $event->getPullRequest()->getHead()->getRepository()->getOwner()->getId(),
            'owner_login' => $event->getPullRequest()->getHead()->getRepository()->getOwner()->getLogin(),
            'br_ref' => $event->getPullRequest()->getHead()->getRepository()->getId() . ':' . $event->getPullRequest()->getHead()->getReferenceName(),
            'br_name' => $event->getPullRequest()->getHead()->getReferenceName()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildBaseBranch(PullRequestEvent $event)
    {
        $q = 'MATCH (pr:PullRequest {id: {pr_id}})
        MERGE (repo:Repository {id: {repo_id}})
        ON CREATE SET repo.name = {repo_name}
        MERGE (user:User {id: {owner_id}})
        ON CREATE SET user.login = {owner_login}';

        if ($event->getPullRequest()->getBase()->getRepository()->getOwner()->isOrg()) {
            $q .= '
            SET user :Organisation';
        }

        $q .= '
        MERGE (branch:Branch {reference: {br_ref}})
        ON CREATE SET branch.name = {br_name}
        MERGE (pr)-[:BASE]->(branch)
        MERGE (branch)-[:BRANCH_OF]->(repo)
        MERGE (repo)-[:OWNED_BY]->(user)';

        $p = [
            'pr_id' => $event->getPullRequest()->getId(),
            'repo_id' => $event->getPullRequest()->getBase()->getRepository()->getId(),
            'repo_name' => $event->getPullRequest()->getBase()->getRepository()->getName(),
            'owner_id' => $event->getPullRequest()->getBase()->getRepository()->getOwner()->getId(),
            'owner_login' => $event->getPullRequest()->getBase()->getRepository()->getOwner()->getLogin(),
            'br_ref' => $event->getPullRequest()->getBase()->getRepository()->getId() . ':' . $event->getPullRequest()->getBase()->getReferenceName(),
            'br_name' => $event->getPullRequest()->getBase()->getReferenceName()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildForks(PullRequestEvent $event)
    {
        $repo_id = $event->getPullRequest()->getBase()->getRepository()->getId();
        $head_id = $event->getPullRequest()->getHead()->getRepository()->getId();
        if ($repo_id === $head_id) {
            return null;
        }
        $q = 'MATCH (head:Repository {id: {head_id}})
        MATCH (repo:Repository {id: {repo_id}})
        MERGE (head)-[:FORK_OF]->(repo)';

        $p = [
            'head_id' => $head_id,
            'repo_id' => $repo_id
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

}