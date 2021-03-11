<?php

use Prometheus\CollectorRegistry;

/**
 * @phutil-external-symbol class CollectorRegistry
 */
abstract class PhabricatorPrometheusMetricGauge extends PhabricatorPrometueusMetric {

  final private function observe(float $value, array $labels): void {
    $metric->set($value, $labels);
  }
}
