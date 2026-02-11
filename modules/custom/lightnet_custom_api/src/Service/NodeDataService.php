<?php

namespace Drupal\lightnet_custom_api\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Datetime\DrupalDateTime;

class NodeDataService {

  protected EntityTypeManagerInterface $entityTypeManager;

  public function __construct(EntityTypeManagerInterface $entityTypeManager) {
    $this->entityTypeManager = $entityTypeManager;
  }

  /**
   * Fetch node data.
   */
  public function getNodeData(int $nid): ?array {
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($nid);

    if (!$node) {
      return NULL;
    }

    return [
      'id' => (int) $node->id(),
      'uuid' => $node->uuid(),
      'title' => $node->label(),
      'content_type' => $node->bundle(),
      'published' => $node->isPublished(),
      'language' => $node->language()->getId(),
      'created' => date(DATE_ATOM, $node->getCreatedTime()),
      'changed' => date(DATE_ATOM, $node->getChangedTime()),
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
      'body' => $data['body'] ?? '',
      'status' => $data['status'] ?? 0,
    ]);

    $node->save();

    return [
      'id' => (int) $node->id(),
      'uuid' => $node->uuid(),
      'title' => $node->label(),
      'content_type' => $node->bundle(),
      'published' => $node->isPublished(),
      'created' => date(DATE_ATOM, $node->getCreatedTime()),
    ];
  }

}