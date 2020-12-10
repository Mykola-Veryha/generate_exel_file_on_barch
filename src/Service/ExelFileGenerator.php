<?php

namespace Drupal\c_taxonomy\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\File\FileSystemInterface;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class ExelFileGenerator {

  /**
   * We generate an exel file there. After downloading we remove this file.
   */
  const TEMP_EXEL_FILE_PATH = 'public://export_skills_and_tags/skills_and_tags.xlsx';

  /**
   * The taxonomy term storage.
   *
   * @var \Drupal\taxonomy\TermStorageInterface
   */
  private $termStorage;

  /**
   * The file storage service.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $fileStorage;

  /**
   * The node storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $nodeStorage;

  /**
   * The file system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  private $fileSystem;

  /**
   * The sbr tags header storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  private $tagsHeaderStorage;

  /**
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    $file_system
  ) {
    $this->termStorage = $entity_type_manager->getStorage('taxonomy_term');
    $this->nodeStorage = $entity_type_manager->getStorage('node');
    $this->fileStorage = $entity_type_manager->getStorage('file');
    $this->fileSystem = $file_system;
    $this->tagsHeaderStorage = $entity_type_manager->getStorage('sbr_tags_header');
  }

  public function writeHeaders() {
    $row = [
      'entity' => 'Taxonomy / Entity',
      'id' => 'ID',
      'name' => 'Name',
      'icon' => 'Icon(file name)',
      'description' => 'Description',
    ];

    $this->writeRow($row, $this->fileSystem->realpath(self::TEMP_EXEL_FILE_PATH));
  }

  public function writeRow(array $row, string $file_path): bool {
    try {
      $spreadsheet = IOFactory::load($file_path);
      $sheet = $spreadsheet->getActiveSheet();
      $row_index = $sheet->getHighestDataRow();
      // Avoid to rewrite the first line.
      // We need to add the checking because when we hav an empty file
      // the $row_index will be 1. It happened beause the Exel index starts
      // from 1. It can't be less than 1.
      // So when there 0 lines we have row_index=1
      // and whne we have 1 line we also have row_index=1.
      if ($row_index == 1) {
        $cell_value = $sheet->getCell("A$row_index")->getValue();
        if ($cell_value !== NULL && $cell_value != '') {
          $row_index++;
        }
      }
      $sheet->insertNewRowBefore($row_index + 1);
      $sheet->fromArray([$row], NULL, "A$row_index");
      $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
      $writer->save($file_path);

      return TRUE;
    }
    catch (Exception $e) {
      watchdog_exception('c_taxonomy', $e);

      return FALSE;
    }
  }

  public function writeRowByTagHeader($tag_header_d) {

    $row = [
      'entity' => 'Tag header',
      'id' => 'test',
      'name' => 'test',
      'icon' => 'test',
      'description' => 'test',
    ];

    $this->writeRow($row, $this->fileSystem->realpath(self::TEMP_EXEL_FILE_PATH));
  }

  /**
   * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
   */
  public function prepareFile() {
    $directory = dirname(self::TEMP_EXEL_FILE_PATH);
    $this->fileSystem->prepareDirectory(
      $directory, FileSystemInterface::CREATE_DIRECTORY
    );
    $spreadsheet = new Spreadsheet();
    $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save($this->fileSystem->realpath(self::TEMP_EXEL_FILE_PATH));
  }

}
