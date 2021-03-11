<?php

use Prometheus\CollectorRegistry;

/**
 * @phutil-external-symbol class CollectorRegistry
 */
abstract class PhabricatorPrometheusMetric extends Phobject {
  const METRIC_NAMESPACE = 'phabricator';

  abstract public function getName(): string;

  public function getHelp(): ?string {
    return null;
  }

  public function getLabels(): array {
    return [];
  }

  abstract public function getValues(): array;

  abstract public function register(CollectorRegistry $registry): void;

  final public static function getAllMetrics(): array {
    return (new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getName')
      ->execute();
  }
}
