<?php

final class PhabricatorDaemonsPrometheusMetric extends PhabricatorPrometheusMetricCounter {

  public function getName(): string {
    return 'write_interupt_total';
  }

  public function getHelp(): string {
    return 'The number of git write interupt errors';
  }

  public function getLabels(): array {
    return ['host'];
  }

  public function getValues(): array {
    return [];
  }
}

