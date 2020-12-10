<?php

namespace Drupal\c_taxonomy\Form;

use Drupal;
use Drupal\Core\Batch\BatchBuilder;
use Drupal\Core\DependencyInjection\ClassResolverInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Render\Markup;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Form controller for the export Skills And Tags.
 */
class ExportSkillsAndTagsForm extends FormBase {

  /**
   * We generate an exel file there. After downloading we remove this file.
   */
  const TEMP_EXEL_FILE_PATH = 'public://export_skills_and_tags/skills_and_tags.xlsx';

  /**
   * Batch Builder.
   *
   * @var \Drupal\Core\Batch\BatchBuilder
   */
  private $batchBuilder;

  /**
   * The class resolver.
   *
   * @var \Drupal\Core\DependencyInjection\ClassResolverInterface
   */
  private $classResolver;

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * The sbr tags header storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $tagsHeaderStorage;

  /**
   * ExportSkillsAndTagsForm constructor.
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ClassResolverInterface $class_resolver
  ) {
    $this->classResolver = $class_resolver;
    $this->batchBuilder = new BatchBuilder();
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->tagsHeaderStorage = $entity_type_manager->getStorage('sbr_tags_header');
  }

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   * @noinspection PhpParamsInspection
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('class_resolver')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'project_export_form';
  }

  /**
   * Form constructor.
   *
   * @param array $form
   *   An associative array containing the structure of the form.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   The current state of the form.
   *
   * @return array
   *   The form structure.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $to_download_file = Drupal::requestStack()
      ->getCurrentRequest()->query->get('to_download_file');
    if (!empty($to_download_file) && file_exists(self::TEMP_EXEL_FILE_PATH)) {
      $form['download_file'] = $this->downloadFileWithJavaScript();
    }
    $form['export'] = [
      '#type' => 'submit',
      '#value' => $this->t('Export'),
    ];

    return $form;
  }

  private function downloadFileWithJavaScript() {
    $download_url = Url::fromRoute('c_taxonomy.download_skills_and_tags')
      ->setAbsolute(TRUE)
      ->toString();
    $html = "<a id='download_url' href='$download_url' style='display: none;'></a>";
    // Refactor this shit :).
    // Write to me if you know better solution to dowload file after batch.
    // mykola.veryha@gmail.com
    $script = '<script>';
    $script .= 'document.addEventListener("DOMContentLoaded", function(event) {';
    $script .= 'document.getElementById("download_url").click();';
    $script .= '});  </script>';
    $html .= $script;

    return [
      '#markup' => Markup::create($html),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $methods_to_execute = $this->batchMethodsToExecute();

    $this->batchBuilder
      ->setTitle($this->t('Generating file'))
      ->setInitMessage($this->t('Initializing.'))
      ->setProgressMessage($this->t('Completed @current of @total.'))
      ->setErrorMessage($this->t('An error has occurred.'))
      ->setFinishCallback([$this, 'finishCallback']);

    foreach ($methods_to_execute as $callback) {
      $this->batchBuilder->addOperation(
        [$this, 'processItem'],
        [$callback]
      );
    }

    batch_set($this->batchBuilder->toArray());
  }

  public function batchMethodsToExecute() {
    $operations = [];
    $operations[] = [
      'callback' => ['c_taxonomy.exel_generator', 'prepareFile'],
      'arguments' => [],
    ];
    $operations[] = [
      'callback' => ['c_taxonomy.exel_generator', 'writeHeaders'],
      'arguments' => [],
    ];
    $or_condition = $this->tagsHeaderStorage->getQuery()
      ->orConditionGroup();
    $or_condition->condition('type', 'tags');
    $or_condition->condition('type', 'skills');
    $tag_headers_ids = $this->tagsHeaderStorage->getQuery()
      ->condition($or_condition)
      ->execute();
    foreach ($tag_headers_ids as $tag_header_id) {
      $operations[] = [
        'callback' => ['c_taxonomy.exel_generator', 'writeRowByTagHeader'],
        'arguments' => [$tag_header_id],
      ];
    }

    return $operations;
  }

  public static function finishCallback() {
    $redirect_url = Url::fromRoute('taxonomy.export_skills_and_tags')
      ->setOption('query', [
        'to_download_file' => 1,
      ])
      ->setAbsolute(TRUE)
      ->toString();

    return new RedirectResponse($redirect_url);
  }

  /**
   * Process single item.
   *
   * @param int|string $callback
   *   The cache type callback.
   * @param array $context
   *   The batch context.
   */
  public function processItem($callback, array &$context) {
    $class = $callback['callback'][0] ?? '';
    $method = $callback['callback'][1] ?? '';
    if (!empty($class) && !empty($method)) {
      $context['message'] = $this->t('Now processing :method with arguments: :args', [
        ':method' => $method,
        ':args' => implode(', ', $callback['arguments']),
      ]);
      if (is_object($class)) {
        $class->{$method}(...$callback['arguments']);
      }
      else {
        $object = Drupal::service('class_resolver')->getInstanceFromDefinition($class);
        $object->{$method}(...$callback['arguments']);
      }
    }
  }

}
