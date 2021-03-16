<?php

/**
 * This file is automatically generated. Use 'arc liberate' to rebuild it.
 *
 * @generated
 * @phutil-library-version 2
 */
phutil_register_library_map(array(
  '__library_version__' => 2,
  'class' => array(
    'DifferentialRevisionViewController' => 'applications/differential/controller/DifferentialRevisionViewController.php',
    'DiffusionGitUploadArchiveSSHWorkflow' => 'applications/diffusion/ssh/DiffusionGitUploadArchiveSSHWorkflow.php',
    'PhabricatorAphlictManagementForegroundWorkflow' => 'applications/aphlict/management/PhabricatorAphlictManagementForegroundWorkflow.php',
    'PhabricatorAuthProvider' => 'applications/auth/provider/PhabricatorAuthProvider.php',
    'PhabricatorAuthStartController' => 'applications/auth/controller/PhabricatorAuthStartController.php',
    'PhabricatorDaemonsPrometheusMetric' => 'applications/daemon/metric/PhabricatorDaemonsPrometheusMetric.php',
    'PhabricatorGoogleAuthProvider' => 'applications/auth/provider/PhabricatorGoogleAuthProvider.php',
    'PhabricatorProjectBoardViewController' => 'applications/project/controller/PhabricatorProjectBoardViewController.php',
    'PhabricatorPrometheusApplication' => 'applications/prometheus/application/PhabricatorPrometheusApplication.php',
    'PhabricatorPrometheusMetric' => 'applications/prometheus/metrics/PhabricatorPrometheusMetric.php',
    'PhabricatorPrometheusMetricGauge' => 'applications/prometheus/metrics/PhabricatorPrometheusMetricGauge.php',
    'PhabricatorPrometheusMetricsController' => 'applications/prometheus/controller/PhabricatorPrometheusMetricsController.php',
    'PhabricatorUpPrometheusMetric' => 'applications/prometheus/metrics/PhabricatorUpPrometheusMetric.php',
    'PhutilGoogleAuthAdapter' => 'applications/auth/adapter/PhutilGoogleAuthAdapter.php',
    'ProjectBoardTaskCard' => 'applications/project/view/ProjectBoardTaskCard.php',
    'TMCelerityResources' => 'applications/celerity/resources/TMCelerityResources.php',
    'UserQueryConduitAPIMethod' => 'applications/people/conduit/UserQueryConduitAPIMethod.php',
  ),
  'function' => array(),
  'xmap' => array(
    'DifferentialRevisionViewController' => 'DifferentialController',
    'DiffusionGitUploadArchiveSSHWorkflow' => 'DiffusionGitSSHWorkflow',
    'PhabricatorAphlictManagementForegroundWorkflow' => 'PhabricatorAphlictManagementWorkflow',
    'PhabricatorAuthProvider' => 'Phobject',
    'PhabricatorAuthStartController' => 'PhabricatorAuthController',
    'PhabricatorDaemonsPrometheusMetric' => 'PhabricatorPrometheusMetric',
    'PhabricatorGoogleAuthProvider' => 'PhabricatorOAuth2AuthProvider',
    'PhabricatorProjectBoardViewController' => 'PhabricatorProjectBoardController',
    'PhabricatorPrometheusApplication' => 'PhabricatorApplication',
    'PhabricatorPrometheusMetric' => 'Phobject',
    'PhabricatorPrometheusMetricGauge' => 'PhabricatorPrometheusMetric',
    'PhabricatorPrometheusMetricsController' => 'PhabricatorController',
    'PhabricatorUpPrometheusMetric' => 'PhabricatorPrometheusMetricGauge',
    'PhutilGoogleAuthAdapter' => 'PhutilOAuthAuthAdapter',
    'ProjectBoardTaskCard' => 'Phobject',
    'TMCelerityResources' => 'CelerityResourcesOnDisk',
    'UserQueryConduitAPIMethod' => 'UserConduitAPIMethod',
  ),
));
