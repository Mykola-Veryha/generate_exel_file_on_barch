<?php

namespace Drupal\c_taxonomy\Controller;

use Drupal\c_taxonomy\Form\ExportSkillsAndTagsForm;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

class ExportController {

  public function downloadSkillsAndTags() {
    $response = new BinaryFileResponse(\Drupal::service('file_system')
      ->realpath(ExportSkillsAndTagsForm::TEMP_EXEL_FILE_PATH));
    $response->deleteFileAfterSend(TRUE);
    $response->setContentDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      'skills_and_tags.xlsx'
    );

    return $response;
  }


}
