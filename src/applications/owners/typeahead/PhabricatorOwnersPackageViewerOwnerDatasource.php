<?php

final class PhabricatorOwnersPackageOwnerDatasource
  extends PhabricatorTypeaheadDatasource {

  public function getBrowseTitle() {
    return pht('Browse Packages owned by viewer');
  }

  public function getPlaceholderText() {
    return pht('Type viewerpackages()...');
  }

  public function getDatasourceApplicationClass() {
    return PhabricatorOwnersApplication::class;
  }

  public function getDatasourceFunctions() {
    return array(
      'packages' => array(
        'name' => pht('Viewer Packages'),
        'summary' => pht("Find results in any of a viewer's packages."),
        'description' => pht(
          "This function allows you to find results associated with any ".
          "of the packages of the current viewer. ".
      ),
    );
  }

  public function loadResults() {
    if ($this->getViewer()->getPHID()) {
      $results = array($this->renderViewerFunctionToken());
    } else {
      $results = array();
    }

    return $this->filterResultsAgainstTokens($results);
  }

  protected function canEvaluateFunction($function) {
    if (!$this->getViewer()->getPHID()) {
      return false;
    }

    return parent::canEvaluateFunction($function);
  }

  protected function evaluateFunction($function, array $argv_list) {
    $phids = array();
    foreach ($argv_list as $argv) {
      $phids[] = $this->getViewer()->getPHID();
    }

    if ($phids) {
      $packages = id(new PhabricatorOwnersPackageQuery())
        ->setViewer($this->getViewer())
        ->withOwnerPHIDs($phids)
        ->execute();
      foreach ($packages as $package) {
        $phids[] = $package->getPHID();
      }
    }

    return $phids;
  }

  public function renderFunctionTokens($function, array $argv_list) {
    $tokens = array();
    foreach ($argv_list as $argv) {
      $tokens[] = PhabricatorTypeaheadTokenView::newFromTypeaheadResult(
        $this->renderViewerFunctionToken());
    }
    return $tokens;
  }

  private function renderViewerFunctionToken() {
    return $this->newFunctionResult()
      ->setName(pht('Current Viewer'))
      ->setPHID('viewerpackages()')
      ->setIcon('fa-user')
      ->setUnique(true);
  }
}
