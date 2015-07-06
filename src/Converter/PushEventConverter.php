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

use Ikwattro\GithubEvent\Event\PushEvent,
    Ikwattro\GithubEvent\Event\BaseEvent;

class PushEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildBranchAndRepoInfo($event);
        $statements[] = $this->buildPushLinkedList($event);
        $statements[] = $this->buildRepoOwnedByActorOrOrg($event);
        foreach ($this->buildCommits($event) as $commitQuery) {
            $statements[] = $commitQuery;
        }

        return $statements;
    }

    private function buildBranchAndRepoInfo(PushEvent $event)
    {
        $q = 'MERGE (branch:Branch {reference: {branch_ref}})
        ON CREATE SET branch.name = {branch_name}
        MERGE (repo:Repository {id: {repo_id}})
        ON CREATE SET repo.name = {repo_name}
        MERGE (branch)-[:BRANCH_OF]->(repo)';

        $p = [
            'event_id' => $event->getEventId(),
            'branch_ref' => $this->getBranchReference($event),
            'branch_name' => $this->getBranchName($event),
            'repo_id' => $event->getRepository()->getId(),
            'repo_name' => $this->getRepoName($event->getRepository())
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildPushLinkedList(PushEvent $event)
    {
        $q = 'MATCH (branch:Branch {reference: {branch_ref}})
        MERGE (push:Push {id: {push_id}})
        ON CREATE SET push.time = {push_time}
        MERGE (push)-[:PUSH_TO]->(branch)
        WITH push
        MATCH (ev:GithubEvent {id: {event_id}})
        MERGE (ev)-[:PUSHED]->(push)';

        $p = [
            'branch_ref' => $this->getBranchReference($event),
            'push_id' => $event->getPushId(),
            'event_id' => $event->getEventId(),
            'push_time' => ($event->getCreatedTime()->getTimestamp() * 1000)
        ];

        return [
            'query' => $q,
            'params' => $p
        ];
    }

    private function buildCommits(PushEvent $event)
    {
        $sts = [];
        $commits = $event->getCommits();
        array_reverse($commits);
        foreach ($commits as $commit) {
            $q = 'MATCH (push:Push {id: {push_id}})
            MERGE (commit:Commit {sha: {sha} }, ref: {commit_ref})
            ON CREATE set commit.message = {message}
            MERGE (push)-[:COMMIT]->(commit)';

            $p = [
                'push_id' => $event->getPushId(),
                'sha' => $commit->getSha(),
                'message' => $commit->getMessage(),
                'ref' => $event->getPushId() . ':' . $commit->getSha()
            ];

            $sts[] = [
                'query' => $q,
                'params' => $p
            ];
        }

        return $sts;
    }

    private function getBranchName(PushEvent $event)
    {
        $ref = $event->getReference();
        $expl = explode('/', $ref);
        $branch_name = $expl[count($expl) -1];

        return $branch_name;
    }

    private function getBranchReference(PushEvent $event)
    {
        return $event->getRepository()->getId() . ':' . $this->getBranchName($event);
    }
}