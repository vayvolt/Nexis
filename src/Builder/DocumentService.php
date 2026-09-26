<?php

declare(strict_types=1);

namespace Nexis\Builder;

use Nexis\Auth\UserId;
use Nexis\Content\PageId;
use Nexis\Support\Clock;
use Nexis\Support\Uuid;

final class DocumentService
{
    public function __construct(
        private RevisionRepository $revisions,
        private DocumentValidator $validator,
        private Clock $clock,
    ) {
    }

    /**
     * @param array<string, mixed> $document
     */
    public function save(PageId $pageId, array $document, ?string $expectedHash, ?UserId $actor, ?string $message = null): PageRevision
    {
        $this->validator->assertValid($document);
        $latest = $this->revisions->latestForPage($pageId);
        if ($latest !== null) {
            if ($expectedHash === null || $expectedHash !== $latest->documentHash) {
                throw new DocumentConflictException('Dokument wurde zwischenzeitlich geändert.');
            }
        } elseif ($expectedHash !== null && $expectedHash !== '') {
            throw new DocumentConflictException('Dokument wurde zwischenzeitlich geändert.');
        }

        $doc = BlockDocument::fromArray($document);
        $revision = new PageRevision(
            new RevisionId(Uuid::v7()),
            $pageId,
            $doc->schemaVersion,
            $doc->toArray(),
            $doc->hash(),
            $message,
            $actor,
            $this->clock->now(),
        );
        $this->revisions->save($revision);

        return $revision;
    }

    public function ensureDraft(PageId $pageId, ?UserId $actor): PageRevision
    {
        $latest = $this->revisions->latestForPage($pageId);
        if ($latest !== null) {
            return $latest;
        }

        $empty = BlockDocument::empty()->toArray();

        return $this->save($pageId, $empty, null, $actor, 'Initiales Dokument');
    }

    /**
     * Sync transitional page.body_text into a core/text block in the draft document.
     * Empty text removes the first core/text block (legacy field cleared).
     */
    public function applyLegacyBodyText(PageId $pageId, string $text, ?UserId $actor): PageRevision
    {
        $latest = $this->ensureDraft($pageId, $actor);
        $document = $latest->document;
        $root = $document['root'] ?? null;
        if (!is_array($root)) {
            $root = BlockDocument::empty()->root;
        }
        $children = $root['children'] ?? [];
        if (!is_array($children)) {
            $children = [];
        }

        if (trim($text) === '') {
            $filtered = [];
            $removed = false;
            foreach ($children as $child) {
                if (!$removed && is_array($child) && ($child['type'] ?? '') === 'core/text') {
                    $removed = true;
                    continue;
                }
                $filtered[] = $child;
            }
            $children = $filtered;
        } else {
            $updated = false;
            foreach ($children as $index => $child) {
                if (!is_array($child) || ($child['type'] ?? '') !== 'core/text') {
                    continue;
                }
                $props = is_array($child['props'] ?? null) ? $child['props'] : [];
                $props['text'] = $text;
                $child['props'] = $props;
                $children[$index] = $child;
                $updated = true;
                break;
            }
            if (!$updated) {
                $children[] = [
                    'id' => Uuid::v7(),
                    'type' => 'core/text',
                    'props' => ['text' => $text],
                ];
            }
        }

        $root['children'] = array_values($children);
        $document['root'] = $root;
        if (!isset($document['schemaVersion']) || !is_int($document['schemaVersion'])) {
            $document['schemaVersion'] = BlockDocument::SCHEMA_VERSION;
        }

        return $this->save($pageId, $document, $latest->documentHash, $actor, 'Legacy-Text synchronisiert');
    }

    /**
     * @param array<string, mixed> $document
     */
    public function copyDocument(PageId $targetPageId, array $document, ?UserId $actor): PageRevision
    {
        $copied = $this->regenerateIds($document);

        return $this->save($targetPageId, $copied, null, $actor, 'Übersetzung kopiert');
    }

    /**
     * Deep-clone a block subtree with fresh UUIDs (for patterns / copy-paste).
     *
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    public function cloneSubtree(array $node): array
    {
        return $this->regenerateNodeIds($node);
    }

    /**
     * @param array<string, mixed> $document
     * @return array<string, mixed>
     */
    private function regenerateIds(array $document): array
    {
        $doc = BlockDocument::fromArray($document);
        $root = $this->regenerateNodeIds($doc->root);

        return (new BlockDocument($doc->schemaVersion, $root))->toArray();
    }

    /**
     * @param array<string, mixed> $node
     * @return array<string, mixed>
     */
    private function regenerateNodeIds(array $node): array
    {
        $node['id'] = Uuid::v7();
        $children = $node['children'] ?? null;
        if (is_array($children)) {
            $node['children'] = array_map(
                function (mixed $child): mixed {
                    return is_array($child) ? $this->regenerateNodeIds($child) : $child;
                },
                $children,
            );
        }

        return $node;
    }
}
