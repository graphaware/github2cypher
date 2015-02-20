<?php

namespace Ikwattro\Github2Cypher\Converter;

use Ikwattro\GithubEvent\Event\BaseEvent;
use Ikwattro\GithubEvent\Event\CreateEvent;

class CreateEventConverter extends BaseConverter
{
    public function convert(BaseEvent $event)
    {
        $statements = [];
        $statements[] = $this->buildEventQuery($event);
        $statements[] = $this->buildCreateQuery($event);

        return $statements;

    }

    private function buildCreateQuery(CreateEvent $event)
    {
        $explodeRepo = explode('/', $event->getRepository()->getName());
        $repoName = $explodeRepo[1];

        $q = 'MATCH (event:GithubEvent {id: {event_id}})
         MERGE (repo:Repository {id: {repo_id}})
         ON CREATE SET repo.name = {repo_name}';
        $p = [
            'event_id' => $event->getEventId(),
            'repo_id' => $event->getRepository()->getId(),
            'repo_name' => $repoName
        ];
        if ($event->isNewRepository()) {
            $q .= '
            MERGE (event)-[:CREATED_REPOSITORY]->(repo)
            ';
        } elseif ($event->isNewBranch()) {
            $q .= '
            MERGE (branch:Branch {reference: {branch_ref}})
            MERGE (event)-[:CREATED_BRANCH]->(branch)
            MERGE (branch)-[:BRANCH_OF]->(repo)
            ';
            $p['branch_ref'] = $event->getRepository()->getId() . ':' . $event->getNewBranchName();
        } elseif ($event->isNewTag()) {
            $q .= '
            MERGE (tag:Tag {reference: {tag_ref}})
            ON CREATE SET tag.tag_name = {tag_name}
            MERGE (event)-[:CREATED_TAG]->(tag)
            MERGE (tag)-[:TAGS_REPOSITORY]->(repo)
            ';
            $p['tag_ref'] = $event->getRepository()->getId() . ':' . $event->getNewTagName();
            $p['tag_name'] = $event->getNewTagName();
        }

        return [
            'query' => $q,
            'params' => $p
        ];
    }
}