<?php

namespace Drupal\lightnet_custom_api\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\lightnet_custom_api\Service\NodeDataService;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Provides a Node Data REST API.
 *
 * @RestResource(
 *   id = "lightnet_node_data_resource",
 *   label = @Translation("Lightnet Node Data API"),
 *   uri_paths = {
 *     "canonical" = "/api/v1/node-data/{identifier}",
 *     "create" = "/api/v1/node-data"
 *   }
 * )
 */
class NodeDataResource extends ResourceBase {

  protected NodeDataService $nodeDataService;
  protected AccountProxyInterface $currentUser;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    array $serializer_formats,
    $logger,
    NodeDataService $nodeDataService,
    AccountProxyInterface $currentUser
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->nodeDataService = $nodeDataService;
    $this->currentUser = $currentUser;
  }

  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('lightnet_custom_api'),
      $container->get('lightnet_custom_api.node_data_service'),
      $container->get('current_user')
    );
  }

  /**
   * GET: Fetch node by NID or Alias.
   */
  public function get($identifier) {

    if (!$this->currentUser->hasPermission('access lightnet node api')) {
      throw new AccessDeniedHttpException();
    }

    $result = $this->nodeDataService->getNodeDataByIdentifier($identifier);

    if (!$result) {
      return new ResourceResponse([
        'status' => 'error',
        'code' => 404,
        'message' => 'Node not found',
      ], 404);
    }

    $response = new ResourceResponse([
      'status' => 'success',
      'code' => 200,
      'message' => 'Node fetched successfully',
      'data' => $result['data'],
    ], 200);

    // 🔥 Drupal-level cache metadata
    $response->addCacheableDependency($result['node']);
    $response->getCacheableMetadata()
      ->setCacheContexts(['url', 'user.permissions'])
      ->setCacheMaxAge(3600);

    return $response;
  }

  /**
   * POST: Create node.
   */
  public function post(Request $request) {

    if (!$this->currentUser->hasPermission('access lightnet node api')) {
      throw new AccessDeniedHttpException();
    }

    try {

      $payload = json_decode($request->getContent(), TRUE);
      $result = $this->nodeDataService->createNode($payload);

      $response = new ResourceResponse([
        'status' => 'success',
        'code' => 201,
        'message' => 'Node created successfully',
        'data' => $result['data'],
      ], 201);

      // Invalidate cache for this node
      $response->addCacheableDependency($result['node']);

      return $response;

    } catch (\Exception $e) {

      return new ResourceResponse([
        'status' => 'error',
        'code' => 400,
        'message' => $e->getMessage(),
      ], 400);
    }
  }

}