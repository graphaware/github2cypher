<?php

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
        OPTIONAL MATCH (branch)-[r:LAST_PUSH]->(lastPush)
        DELETE r
        CREATE (push:Push {id: {push_id}})
        MERGE (branch)-[:LAST_PUSH]->(push)
        MERGE (push)-[:PUSH_TO]->(branch)
        WITH push, collect(lastPush) as last
        FOREACH (x in last | CREATE (push)-[:PREVIOUS_PUSH]->(x))
        WITH push
        MATCH (ev:GithubEvent {id: {event_id}})
        MERGE (ev)-[:PUSHED]->(push)';

        $p = [
            'branch_ref' => $this->getBranchReference($event),
            'push_id' => $event->getPushId(),
            'event_id' => $event->getEventId()
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
            OPTIONAL MATCH (push)-[r:LAST_COMMIT]->(com)
            DELETE r
            WITH push, collect(com) as lastCommits
            CREATE (commit:Commit {sha: {sha}, message: {message} })
            MERGE (push)-[:LAST_COMMIT]->(commit)
            FOREACH (x IN lastCommits | CREATE (commit)-[:PREVIOUS_COMMIT]->(x))';

            $p = [
                'push_id' => $event->getPushId(),
                'sha' => $commit->getSha(),
                'message' => $commit->getMessage()
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