<?php

use Prometheus\CollectorRegistry;

/**
 * @phutil-external-symbol class CollectorRegistry
 */
abstract class PhabricatorPrometheusMetricGauge extends PhabricatorPrometueusMetric {
	
  final public function register(CollectorRegistry $registry): void {
    $gauge = $registry->registerGauge(
      self::METRIC_NAMESPACE,
      $this->getName(),
      $this->getHelp(),
      $this->getLabels());

    foreach ($this->getValues() as $data) {
      if (!is_array($data) || count($data) === 1) {
        $value  = $data;
        $labels = [];
      } else if (count($data) === 2) {
        list($value, $labels) = $data;
      } else {
        throw new Exception(
          pht(
            'Value for "%s" metric is malformed.',
            $this->getName()));
      }

      $missing_labels    = array_diff($this->getLabels(), array_keys($labels));
      $unexpected_labels = array_diff(array_keys($labels), $this->getLabels());

      // Ensure that all predefined labels exist for this value.
      if (count($missing_labels) > 0) {
        throw new Exception(
          pht(
            'Value for "%s" metric is missing expected labels: %s',
            $this->getName(),
            implode(', ', $missing_labels)));
      }

      // Ensure that the data point doesn't contain any labels that weren't
      // predefined (returned from @{method:getLabels}).
      if (count($unexpected_labels) > 0) {
        throw new Exception(
          pht(
            'Value for "%s" metric has unexpected labels: %s',
            $this->getName(),
            implode(', ', $unexpected_labels)));
      }

      // We need to ensure that label values are passed to `$gauge->set` in the
      // same order as was returned by @{method:getLabels}.
      $labels = array_map(
        function (string $name) use ($labels) {
          return $labels[$name];
        },
        $this->getLabels());

      $gauge->set($value, $labels);
    }
  }
}
