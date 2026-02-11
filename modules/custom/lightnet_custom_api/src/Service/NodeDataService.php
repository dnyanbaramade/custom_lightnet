<?php

namespace Drupal\lightnet_custom_api\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\node\NodeInterface;

class NodeDataService {

  protected EntityTypeManagerInterface $entityTypeManager;
  protected AliasManagerInterface $aliasManager;
  protected CacheTagsInvalidatorInterface $cacheTagsInvalidator;

  public function __construct(
    EntityTypeManagerInterface $entityTypeManager,
    AliasManagerInterface $aliasManager,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator
  ) {
    $this->entityTypeManager = $entityTypeManager;
    $this->aliasManager = $aliasManager;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  /**
   * Get node by NID or Alias.
   */
  public function getNodeDataByIdentifier(string $identifier): ?array {

    $node = NULL;

    if (is_numeric($identifier)) {
      $node = $this->entityTypeManager
        ->getStorage('node')
        ->load((int) $identifier);
    }
    else {
      $internal_path = $this->aliasManager
        ->getPathByAlias('/' . ltrim($identifier, '/'));

      if (preg_match('/^\/node\/(\d+)$/', $internal_path, $matches)) {
        $node = $this->entityTypeManager
          ->getStorage('node')
          ->load((int) $matches[1]);
      }
    }

    if (!$node instanceof NodeInterface) {
      return NULL;
    }

    return [
      'node' => $node,
      'data' => $this->buildNodeResponse($node),
    ];
  }

  /**
   * Create node.
   */
  public function createNode(array $data): array {

    if (empty($data['type']) || empty($data['title'])) {
      throw new \InvalidArgumentException('Content type and title are required.');
    }

    $storage = $this->entityTypeManager->getStorage('node');

    $node = $storage->create([
      'type' => $data['type'],
      'title' => $data['title'],
      'body' => [
        'value' => $data['body'] ?? '',
        'format' => 'basic_html',
      ],
      'status' => $data['status'] ?? 0,
    ]);

    $node->save();

    // 🔥 Proper cache invalidation
    $this->cacheTagsInvalidator->invalidateTags([
      'node:' . $node->id(),
      'node_list',
    ]);

    return [
      'node' => $node,
      'data' => $this->buildNodeResponse($node),
    ];
  }

  protected function buildNodeResponse(NodeInterface $node): array {

    $alias = $this->aliasManager
      ->getAliasByPath('/node/' . $node->id());

    return [
      'id' => (int) $node->id(),
      'uuid' => $node->uuid(),
      'title' => $node->label(),
      'content_type' => $node->bundle(),
      'published' => $node->isPublished(),
      'language' => $node->language()->getId(),
      'url_alias' => $alias,
      'created' => date(DATE_ATOM, $node->getCreatedTime()),
      'changed' => date(DATE_ATOM, $node->getChangedTime()),
    ];
  }

}