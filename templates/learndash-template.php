<?php

/**
 * A helper template to overwrite a LearnDash template
 * Must be used this way, because LearnDash only allows filtering of template path not of output
 * but TemplateService should be used to be extendible
 * therefore use this as a helper template/file
 */

use BizExaminer\LearnDashExtension\Core\TemplateService;
use BizExaminer\LearnDashExtension\Plugin;

$plugin = Plugin::getInstance();
/** @var TemplateService */
$templateService = $plugin->getContainer()->get('templates');

$templateService->render('learndash/' . $name, $args, true);
