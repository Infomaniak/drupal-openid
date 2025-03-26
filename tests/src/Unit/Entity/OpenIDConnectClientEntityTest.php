<?php

declare(strict_types=1);

namespace Drupal\Tests\infomaniak_connect\Unit\Entity;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Config\ImmutableConfig;
use Drupal\Core\DependencyInjection\ContainerBuilder;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\externalauth\AuthmapInterface;
use Drupal\infomaniak_connect\Plugin\OpenIDConnectClient\OpenIDConnectInfomaniakClient;
use Drupal\openid_connect\Entity\OpenIDConnectClientEntity;
use Drupal\openid_connect\Plugin\OpenIDConnectClientManager;
use Drupal\Tests\UnitTestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Tests for updating the user's email.
 *
 * @group infomaniak_connect
 */
class OpenIDConnectClientEntityTest extends UnitTestCase {

  const CLIENT_ID = 'test_client_id';

  const CLIENT_SECRET = 'test_client_secret';

  const PLUGIN_ID = 'infomaniak';

  const INFOMANIAK_PLUGIN_VALUES = [
    'id' => 'infomaniak',
    'label' => 'Infomaniak OAuth 2.0',
    'plugin' => self::PLUGIN_ID,
    'settings' => [
      'client_id' => self::CLIENT_ID,
      'client_secret' => self::CLIENT_SECRET,
      'issuer_url' => 'https://example.com',
      'authorization_endpoint' => 'https://example.com/oauth2/authorize',
      'token_endpoint' => 'https://example.com/oauth2/token',
      'userinfo_endpoint' => 'https://example.com/oauth2/userinfo',
      'end_session_endpoint' => '',
      'scopes' => ['openid', 'email'],
      'emails_filter' => "admin@example.com\n*@infomaniak.com",
      'emails_filter_type' => OpenIDConnectInfomaniakClient::FILTER_TYPE_DENY,
    ],
  ];

  const KEY_OVERRIDES = [
    'client_id' => 'CLIENT_ID_OVERRIDE',
    'client_secret' => 'CLIENT_SECRET_OVERRIDE',
    'issuer_url' => 'https://login.infomaniak.com',
  ];

  const ENTITY_TYPE = 'openid_connect_client';

  /**
   * Mock the plugin.manager.openid_connect_client service.
   *
   * @var \Drupal\openid_connect\Plugin\OpenIDConnectClientManager|\PHPUnit\Framework\MockObject\MockObject
   */
  protected OpenIDConnectClientManager|MockObject $pluginManager;

  /**
   * Mock the externalauth.authmap service.
   *
   * @var \Drupal\externalauth\AuthmapInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected AuthmapInterface|MockObject $authmap;

  /**
   * Mock the config.factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected ConfigFactoryInterface|MockObject $configFactory;

  /**
   * Mock the entity_type.manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface|\PHPUnit\Framework\MockObject\MockObject
   */
  protected EntityTypeManagerInterface|MockObject $entityTypeManager;

  /**
   * The entity class being tested.
   *
   * @var \Drupal\openid_connect\Entity\OpenIDConnectClientEntity
   */
  protected OpenIDConnectClientEntity $entity;

  /**
   * The OpenId Connect client plugin.
   *
   * @var \Drupal\infomaniak_connect\Plugin\OpenIDConnectClient\OpenIDConnectInfomaniakClient
   */
  protected OpenIDConnectInfomaniakClient $pluginInfomaniak;

  /**
   * Setup before each test.
   */
  protected function setUp(): void {
    parent::setUp();

    $this->pluginManager = $this->createMock(OpenIDConnectClientManager::class);
    $this->authmap = $this->createMock(AuthmapInterface::class);
    $this->configFactory = $this->createMock(ConfigFactoryInterface::class);
    $this->entityTypeManager = $this->createMock(EntityTypeManagerInterface::class);

    $container = new ContainerBuilder();
    $container->set('plugin.manager.openid_connect_client', $this->pluginManager);
    $container->set('externalauth.authmap', $this->authmap);
    $container->set('config.factory', $this->configFactory);
    $container->set('entity_type.manager', $this->entityTypeManager);
    \Drupal::setContainer($container);

    $this->entity = new OpenIDConnectClientEntity(self::INFOMANIAK_PLUGIN_VALUES, self::ENTITY_TYPE);
  }

  /**
   * Test the getPluginId() method.
   */
  public function testGetPluginId(): void {
    $this->assertEquals(self::PLUGIN_ID, $this->entity->getPluginId());
  }

  /**
   * Test the getPlugin() method.
   */
  public function testGetPlugin(): void {
    $entity_id = self::INFOMANIAK_PLUGIN_VALUES['id'];

    $immutableConfig = $this->createMock(ImmutableConfig::class);
    $immutableConfig->expects($this->once())
      ->method('get')
      ->with('settings')
      ->willReturn(self::KEY_OVERRIDES);

    $this->configFactory->expects($this->once())
      ->method('get')
      ->with("openid_connect.client.{$entity_id}")
      ->willReturn($immutableConfig);

    $collectionSettings = self::INFOMANIAK_PLUGIN_VALUES['settings'];
    $collectionSettings['client_id'] = self::KEY_OVERRIDES['client_id'];
    $collectionSettings['client_secret'] = self::KEY_OVERRIDES['client_secret'];
    $collectionSettings['issuer_url'] = self::KEY_OVERRIDES['issuer_url'];

    $pluginMock = $this->createMock(OpenIDConnectInfomaniakClient::class);
    $pluginMock->expects($this->once())
      ->method('getConfiguration')
      ->willReturn($collectionSettings);

    $this->pluginManager->expects($this->once())
      ->method('createInstance')
      ->with($entity_id, $collectionSettings)
      ->willReturn($pluginMock);

    $plugin = $this->entity->getPlugin();
    $config = $plugin->getConfiguration();

    $this->assertEquals(self::KEY_OVERRIDES['client_id'], $config['client_id']);
    $this->assertEquals(self::KEY_OVERRIDES['client_secret'], $config['client_secret']);
    $this->assertEquals(self::KEY_OVERRIDES['issuer_url'], $config['issuer_url']);
  }

}
