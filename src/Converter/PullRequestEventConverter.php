<?php

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
        $statements[] = $this->buildForks($event);

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
        MERGE (branch:Branch {label: {br_label}})
        ON CREATE SET branch.ref = {br_ref}
        MERGE (pr)-[:HEAD]->(branch)
        MERGE (branch)-[:FROM_REPOSITORY]->(repo)
        MERGE (repo)-[:OWNED_BY]->(user)';

        $p = [
            'pr_id' => $event->getPullRequest()->getId(),
            'repo_id' => $event->getPullRequest()->getHead()->getRepository()->getId(),
            'repo_name' => $event->getPullRequest()->getHead()->getRepository()->getName(),
            'owner_id' => $event->getPullRequest()->getHead()->getRepository()->getOwner()->getId(),
            'owner_login' => $event->getPullRequest()->getHead()->getRepository()->getOwner()->getLogin(),
            'br_label' => $event->getPullRequest()->getHead()->getLabel(),
            'br_ref' => $event->getPullRequest()->getHead()->getReferenceName()
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
        MERGE (branch:Branch {label: {br_label}})
        ON CREATE SET branch.ref = {br_ref}
        MERGE (pr)-[:BASE]->(branch)
        MERGE (branch)-[:FROM_REPOSITORY]->(repo)
        MERGE (repo)-[:OWNED_BY]->(user)';

        $p = [
            'pr_id' => $event->getPullRequest()->getId(),
            'repo_id' => $event->getPullRequest()->getBase()->getRepository()->getId(),
            'repo_name' => $event->getPullRequest()->getBase()->getRepository()->getName(),
            'owner_id' => $event->getPullRequest()->getBase()->getRepository()->getOwner()->getId(),
            'owner_login' => $event->getPullRequest()->getBase()->getRepository()->getOwner()->getLogin(),
            'br_label' => $event->getPullRequest()->getBase()->getLabel(),
            'br_ref' => $event->getPullRequest()->getBase()->getReferenceName()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildForks(PullRequestEvent $event)
    {
        $q = 'MATCH (head:Repository {id: {head_id}})
        MATCH (repo:Repository {id: {repo_id}})
        MERGE (head)-[:FORK_OF]->(repo)';

        $p = [
            'head_id' => $event->getPullRequest()->getHead()->getRepository()->getId(),
            'repo_id' => $event->getPullRequest()->getBase()->getRepository()->getId()
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

}