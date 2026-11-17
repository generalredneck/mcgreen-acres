<?php

namespace Drupal\mcgreen_cut_sheet\Controller;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Drupal\mcgreen_cut_sheet\CutSheetNarrative;
use Drupal\webform\WebformInterface;
use Drupal\webform\WebformSubmissionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves a cut sheet as a bare, print-ready HTML page.
 *
 * The page is a complete document with no site header, admin toolbar or
 * theme styles, so printing it (or saving it as a PDF from the browser) gives
 * one clean sheet to hand the processor.
 */
class CutSheetPrintController extends ControllerBase {

  /**
   * The narrative builder.
   *
   * @var \Drupal\mcgreen_cut_sheet\CutSheetNarrative
   */
  protected $narrative;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = new static();
    $instance->narrative = $container->get('mcgreen_cut_sheet.narrative');
    $instance->renderer = $container->get('renderer');
    return $instance;
  }

  /**
   * Only submissions of the cut sheet webform have a cut sheet.
   *
   * Also hides the "Cut sheet" tab on every other webform's submissions.
   */
  public function access(WebformInterface $webform, WebformSubmissionInterface $webform_submission): AccessResultInterface {
    return AccessResult::allowedIf(
      $webform->id() === CutSheetNarrative::WEBFORM_ID
      && $webform_submission->getWebform()->id() === $webform->id()
    )->addCacheableDependency($webform)->addCacheableDependency($webform_submission);
  }

  /**
   * Renders the sheet.
   */
  public function sheet(WebformInterface $webform, WebformSubmissionInterface $webform_submission): Response {
    $build = [
      '#theme' => 'mcgreen_cut_sheet_print',
      '#title' => $webform->label(),
      '#sections' => $this->narrative->build($webform_submission),
      '#back_url' => Url::fromRoute('entity.webform_submission.canonical', [
        'webform' => $webform->id(),
        'webform_submission' => $webform_submission->id(),
      ])->toString(),
    ];

    $response = new Response((string) $this->renderer->renderInIsolation($build));
    // Names and phone numbers: never cache, never index.
    $response->headers->set('Cache-Control', 'private, no-store');
    $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
    return $response;
  }

}
