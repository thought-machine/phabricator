<?php

final class PhabricatorDaemonsPrometheusMetric extends PhabricatorPrometheusMetricGauge {

  public function getName(): string {
    return 'daemons_total';
  }

  public function getHelp(): string {
    return 'The current count of phabricator daemon processes';
  }

  public function getLabels(): array {
    return ['host', 'class', 'status'];
  }

  public function getValues(): array {
    $table = new PhabricatorDaemonLog();
    $conn_r = $table->establishConnection('r');

    $data = queryfx_all(
      $conn_r,
      'SELECT host, daemon AS class, status, COUNT(*) AS count FROM %T GROUP BY host, daemon, status',
      $table->getTableName());

    return array_map(
      function (array $row): array {
        return [
          $row['count'],
          [
            'class'  => $row['class'],
            'host'   => $row['host'],
            'status' => $row['status'],
          ],
        ];
      },
      $data);
  }

}
