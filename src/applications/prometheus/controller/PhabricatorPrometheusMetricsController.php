<?php

use Prometheus\CollectorRegistry;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\InMemory as InMemoryStorage;

/**
 * @phutil-external-symbol class CollectorRegistry
 * @phutil-external-symbol class InMemoryStorage
 * @phutil-external-symbol class RenderTextFormat
 */
final class PhabricatorPrometheusMetricsController extends PhabricatorController {

  private $registry;

  public function shouldRequireLogin(): bool {
    return false;
  }

  public function willProcessRequest(array $uri_data): void {
    $adapter  = new InMemoryStorage();
    $registry = new CollectorRegistry($adapter);
    $metrics  = PhabricatorPrometheusMetric::getAllMetrics();

    foreach ($metrics as $metric) {
      $m = $metric->register($registry);
      $m->observeAll();
    }

    $this->registry = $registry;
  }

  public function processRequest(): AphrontResponse {
    $metrics  = $this->registry->getMetricFamilySamples();
    $renderer = new RenderTextFormat();

    return (new AphrontPlainTextResponse())
      ->setContent($renderer->render($metrics));
  }

}
