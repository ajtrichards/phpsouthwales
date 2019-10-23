<?php

namespace Drupal\event_pull\Plugin\AdvancedQueue\JobType;

use Drupal\advancedqueue\Job;
use Drupal\advancedqueue\JobResult;
use Drupal\advancedqueue\Plugin\AdvancedQueue\JobType\JobTypeBase;
use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\event_pull\Controller\EventController;
use Drupal\event_pull\Model\Event;
use Drupal\event_pull\Service\Repository\EventRepository;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\TermInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * A queue job type for pulled events.
 *
 * Creates event nodes for external events that have been pulled in.
 *
 * @AdvancedQueueJobType(
 *   id = "event_pull_create_update_node",
 *   label = @Translation("Create or update a node for an exernal event"),
 * )
 */
class CreateOrUpdateNodeForExternalEvent extends JobTypeBase implements ContainerFactoryPluginInterface {

  /**
   * The node entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $nodeStorage;

  /**
   * The taxonomy term entity storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $termStorage;

  /**
   * @var \Drupal\event_pull\Service\Repository\EventRepository
   */
  private $eventRepository;

  /**
   * The event controller.
   *
   * @var \Drupal\event_pull\Controller\EventController
   */
  private $eventController;

  /**
   * PulledEvent constructor.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The entity type manager.
   * @param \Drupal\event_pull\Service\Repository\EventRepository $eventRepository
   *   The event repository.
   * @param \Drupal\event_pull\Controller\EventController $eventController
   *   The event controller.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    array $configuration,
    string $pluginId,
    $pluginDefinition,
    EntityTypeManagerInterface $entityTypeManager,
    EventRepository $eventRepository,
    EventController $eventController
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);

    $this->nodeStorage = $entityTypeManager->getStorage('node');
    $this->termStorage = $entityTypeManager->getStorage('taxonomy_term');
    $this->eventController = $eventController;
    $this->eventRepository = $eventRepository;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $pluginId,
    $pluginDefinition
  ) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('entity_type.manager'),
      $container->get(EventRepository::class),
      $container->get(EventController::class)
    );
  }

  /**
   * {@inheritdoc}
   */
  public function process(Job $job) {
    try {
      $event = new Event((object) $job->getPayload());

      $venue = $this->findOrCreateVenue($event);
      $this->findOrCreateEvent($event, $venue);

      return JobResult::success();
    }
    catch (\Exception $e) {
      return JobResult::failure($e->getMessage());
    }
  }

  /**
   * Create an event term for the event.
   *
   * @param \Drupal\event_pull\Model\Event $event
   *   The event model.
   *
   * @return \Drupal\taxonomy\TermInterface
   *   The venue term.
   */
  private function findOrCreateVenue(Event $event): TermInterface {
    $remoteId = $event->getVenue()->getRemoteId();
    $properties = ['field_venue_id' => $remoteId, 'vid' => 'venues'];

    if ($terms = $this->termStorage->loadByProperties($properties)) {
      return collect($terms)->first();
    }

    $values = [
      'field_venue_id' => $event->getVenue()->getRemoteId(),
      'name' => $event->getVenue()->getName(),
      'vid' => 'venues',
    ];

    $venue = Term::create($values);
    $venue->save();

    return $venue;
  }

  /**
   * Create the event node.
   *
   * @param \Drupal\event_pull\Model\Event $event
   *   The event model.
   * @param \Drupal\taxonomy\TermInterface $venue
   *   The venue term.
   *
   * @return \Drupal\Core\Entity\EntityInterface
   *   The created node.
   *
   * @throws \Exception
   */
  private function findOrCreateEvent(Event $event, TermInterface $venue): EntityInterface {
    $remoteId = $event->getRemoteId();
    $events = $this->eventRepository->findByRemoteId($remoteId);

    if ($events->isEmpty()) {
      return $this->eventController->create($event, $venue, $remoteId);
    }

    return $this->eventController->update($events->first(), $event);
  }

}
