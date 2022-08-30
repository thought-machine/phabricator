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
    'DifferentialRevisionEditEngine' => 'applications/differential/editor/DifferentialRevisionEditEngine.php',
    'DifferentialRevisionViewController' => 'applications/differential/controller/DifferentialRevisionViewController.php',
    'DiffusionGitUploadArchiveSSHWorkflow' => 'applications/diffusion/ssh/DiffusionGitUploadArchiveSSHWorkflow.php',
    'DiffusionPushLogBuildableEngine' => 'applications/diffusion/harbormaster/DiffusionPushLogBuildableEngine.php',
    'DiffusionPushLogBuildableTransaction' => 'applications/diffusion/xaction/DiffusionPushLogBuildableTransaction.php',
    'DiffusionRepositoryClusterEngine' => 'applications/diffusion/protocol/DiffusionRepositoryClusterEngine.php',
    'HeraldPreCommitRefAdapter' => 'applications/diffusion/herald/HeraldPreCommitRefAdapter.php',
    'PhabricatorAphlictManagementForegroundWorkflow' => 'applications/aphlict/management/PhabricatorAphlictManagementForegroundWorkflow.php',
    'PhabricatorAuthProvider' => 'applications/auth/provider/PhabricatorAuthProvider.php',
    'PhabricatorAuthStartController' => 'applications/auth/controller/PhabricatorAuthStartController.php',
    'PhabricatorDaemonsPrometheusMetric' => 'applications/daemon/metric/PhabricatorDaemonsPrometheusMetric.php',
    'PhabricatorGoogleAuthProvider' => 'applications/auth/provider/PhabricatorGoogleAuthProvider.php',
    'PhabricatorProjectBoardViewController' => 'applications/project/controller/PhabricatorProjectBoardViewController.php',
    'PhabricatorPrometheusApplication' => 'applications/prometheus/application/PhabricatorPrometheusApplication.php',
    'PhabricatorPrometheusMetric' => 'applications/prometheus/metrics/PhabricatorPrometheusMetric.php',
    'PhabricatorPrometheusMetricCounter' => 'applications/prometheus/metrics/PhabricatorPrometheusMetricCounter.php',
    'PhabricatorPrometheusMetricGauge' => 'applications/prometheus/metrics/PhabricatorPrometheusMetricGauge.php',
    'PhabricatorPrometheusMetricsController' => 'applications/prometheus/controller/PhabricatorPrometheusMetricsController.php',
    'PhabricatorRepository' => 'applications/repository/storage/PhabricatorRepository.php',
    'PhabricatorRepositoryPushLog' => 'applications/repository/storage/PhabricatorRepositoryPushLog.php',
    'PhabricatorUpPrometheusMetric' => 'applications/prometheus/metrics/PhabricatorUpPrometheusMetric.php',
    'PhutilGoogleAuthAdapter' => 'applications/auth/adapter/PhutilGoogleAuthAdapter.php',
    'ProjectBoardTaskCard' => 'applications/project/view/ProjectBoardTaskCard.php',
    'TMCelerityResources' => 'applications/celerity/resources/TMCelerityResources.php',
    'UserQueryConduitAPIMethod' => 'applications/people/conduit/UserQueryConduitAPIMethod.php',
  ),
  'function' => array(),
  'xmap' => array(
    'DifferentialRevisionEditEngine' => 'PhabricatorEditEngine',
    'DifferentialRevisionViewController' => 'DifferentialController',
    'DiffusionGitUploadArchiveSSHWorkflow' => 'DiffusionGitSSHWorkflow',
    'DiffusionPushLogBuildableEngine' => 'HarbormasterBuildableEngine',
    'DiffusionPushLogBuildableTransaction' => 'PhabricatorModularTransactionType',
    'DiffusionRepositoryClusterEngine' => 'Phobject',
    'HeraldPreCommitRefAdapter' => array(
      'HeraldPreCommitAdapter',
      'HarbormasterBuildableAdapterInterface',
    ),
    'PhabricatorAphlictManagementForegroundWorkflow' => 'PhabricatorAphlictManagementWorkflow',
    'PhabricatorAuthProvider' => 'Phobject',
    'PhabricatorAuthStartController' => 'PhabricatorAuthController',
    'PhabricatorDaemonsPrometheusMetric' => 'PhabricatorPrometheusMetricGauge',
    'PhabricatorGoogleAuthProvider' => 'PhabricatorOAuth2AuthProvider',
    'PhabricatorProjectBoardViewController' => 'PhabricatorProjectBoardController',
    'PhabricatorPrometheusApplication' => 'PhabricatorApplication',
    'PhabricatorPrometheusMetric' => 'Phobject',
    'PhabricatorPrometheusMetricCounter' => 'PhabricatorPrometheusMetric',
    'PhabricatorPrometheusMetricGauge' => 'PhabricatorPrometheusMetric',
    'PhabricatorPrometheusMetricsController' => 'PhabricatorController',
    'PhabricatorRepository' => array(
      'PhabricatorRepositoryDAO',
      'PhabricatorApplicationTransactionInterface',
      'PhabricatorPolicyInterface',
      'PhabricatorFlaggableInterface',
      'PhabricatorMarkupInterface',
      'PhabricatorDestructibleInterface',
      'PhabricatorDestructibleCodexInterface',
      'PhabricatorProjectInterface',
      'PhabricatorSpacesInterface',
      'PhabricatorConduitResultInterface',
      'PhabricatorFulltextInterface',
      'PhabricatorFerretInterface',
    ),
    'PhabricatorRepositoryPushLog' => array(
      'PhabricatorRepositoryDAO',
      'HarbormasterBuildableInterface',
      'PhabricatorPolicyInterface',
    ),
    'PhabricatorUpPrometheusMetric' => 'PhabricatorPrometheusMetricGauge',
    'PhutilGoogleAuthAdapter' => 'PhutilOAuthAuthAdapter',
    'ProjectBoardTaskCard' => 'Phobject',
    'TMCelerityResources' => 'CelerityResourcesOnDisk',
    'UserQueryConduitAPIMethod' => 'UserConduitAPIMethod',
  ),
));
