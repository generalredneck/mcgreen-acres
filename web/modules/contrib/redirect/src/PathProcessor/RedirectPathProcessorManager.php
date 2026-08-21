<?php

namespace Drupal\redirect\PathProcessor;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\PathProcessor\PathProcessorManager;
use Drupal\path_alias\PathProcessor\AliasPathProcessor;
use Drupal\system\PathProcessor\PathProcessorFiles;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;

/**
 * Redirect path processor manager.
 *
 * Extends PathProcessorManager to customize the processInbound logic.
 *
 * Drupal 11.4 replaced the addInbound()/addOutbound() service-collector
 * registration with constructor-injected autowired iterators. This manager
 * follows the same pattern and filters the inbound processors at processing
 * time (skipping PathProcessorFiles, and optionally AliasPathProcessor).
 */
class RedirectPathProcessorManager extends PathProcessorManager implements RedirectPathProcessorManagerInterface {

  /**
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  /**
   * Constructs a RedirectPathProcessorManager object.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    #[AutowireIterator(tag: 'path_processor_inbound')]
    iterable $inboundProcessors = [],
    #[AutowireIterator(tag: 'path_processor_outbound')]
    iterable $outboundProcessors = [],
  ) {
    parent::__construct($inboundProcessors, $outboundProcessors);
    $this->config = $config_factory->get('redirect.settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getRedirectRequestPaths(Request $request) {
    $paths = [];

    // Do the inbound processing to get the source path.
    $path = $request->getPathInfo();
    $source_path = $this->processRedirectInbound($path, $request);
    $paths[] = trim($source_path, '/');

    // Add the aliased path.
    $allow_alias = $this->config->get('allow_from_alias') ?? 0;
    if ($allow_alias) {
      $alias_path = $this->processRedirectInbound($path, $request, TRUE);
      $paths[] = trim($alias_path, '/');
    }

    return array_filter(array_unique($paths));
  }

  /**
   * Processes the inbound path.
   *
   * @param string $path
   *   The path to process, with a leading slash.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param bool $skip_alias
   *   Whether to skip AliasPathProcessor::processInbound.
   *
   * @return string
   *   The processed path.
   */
  protected function processRedirectInbound($path, Request $request, bool $skip_alias = FALSE) {
    foreach ($this->inboundProcessors as $processor) {
      // Skip PathProcessorFiles::processInbound(): private files paths are
      // split by the inbound path processor and the relative file path is
      // moved to the 'file' query string parameter.
      if ($processor instanceof PathProcessorFiles) {
        continue;
      }
      // Skip AliasPathProcessor::processInbound if specified.
      if ($skip_alias === TRUE && $processor instanceof AliasPathProcessor) {
        continue;
      }
      $path = $processor->processInbound($path, $request);
    }
    return $path;
  }

}
