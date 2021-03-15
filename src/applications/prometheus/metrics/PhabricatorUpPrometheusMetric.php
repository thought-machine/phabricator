<?php

final class PhabricatorUpPrometheusMetric extends PhabricatorPrometheusMetricGauge {
  public function getName(): string {
    return 'up';
  }

  public function getValues(): array {
    return [1];
  }
}
