<?php

final class PhabricatorClusterEngineSyncPrometheusMetric extends PhabricatorPrometheusMetricCounter {

  public function getName(): string {
    return 'repo_sync_total';
  }

  public function getHelp(): string {
    return 'The number of git sync operations';
  }

  public function getLabels(): array {
    return ['repo', 'host', 'stage', 'category'];
  }

  public function getValues(): array {
    return [];
  }
}

