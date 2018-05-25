<?php

final class DiffusionBranchQueryConduitAPIMethod
  extends DiffusionQueryConduitAPIMethod {

  public function getAPIMethodName() {
    return 'diffusion.branchquery';
  }

  public function getMethodDescription() {
    return pht('Determine what branches exist for a repository.');
  }

  protected function defineReturnType() {
    return 'list<dict>';
  }

  protected function defineCustomParamTypes() {
    return array(
      'closed' => 'optional bool',
      'limit' => 'optional int',
      'offset' => 'optional int',
      'contains' => 'optional string',
    );
  }

  protected function getGitResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      // TM CHANGES START
      // Work around for https://discourse.phabricator-community.org/t/poor-performance-fetching-git-branches-in-repo-with-lots-of-branches/1490
      // NOTE: This won't work for regexps, but we don't use them.
      $branch_filter = $repository->getDetail('branch-filter');
      $value = array_keys($branch_filter);
      // NOTE: We can't use DiffusionLowLevelGitRefQuery here because
      // `git for-each-ref` does not support `--contains`.
      if ($repository->isWorkingCopyBare()) {
        $branches = implode(' ', $value);
        list($stdout) = $repository->execxLocalCommand(
          'branch --verbose --no-abbrev --contains %s %s --',
          $contains,
          $branches);
        $ref_map = DiffusionGitBranch::parseLocalBranchOutput(
          $stdout);
      } else {
        $value = array_map(function ($val) {
          return 'origin/'.$val;
        }, $value);
        $branches = implode(' ', $value);
        list($stdout) = $repository->execxLocalCommand(
          'branch -r --verbose --no-abbrev --contains %s %s --',
          $contains,
          $branches);
        $ref_map = DiffusionGitBranch::parseRemoteBranchOutput(
          $stdout,
          DiffusionGitBranch::DEFAULT_GIT_REMOTE);
      }
      // TM CHANGES END

      $refs = array();
      foreach ($ref_map as $ref => $commit) {
        $refs[] = id(new DiffusionRepositoryRef())
          ->setShortName($ref)
          ->setCommitIdentifier($commit);
      }
    } else {
      $refs = id(new DiffusionLowLevelGitRefQuery())
        ->setRepository($repository)
        ->withRefTypes(
          array(
            PhabricatorRepositoryRefCursor::TYPE_BRANCH,
          ))
        ->execute();
    }

    return $this->processBranchRefs($request, $refs);
  }

  protected function getMercurialResult(ConduitAPIRequest $request) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();

    $query = id(new DiffusionLowLevelMercurialBranchesQuery())
      ->setRepository($repository);

    $contains = $request->getValue('contains');
    if (strlen($contains)) {
      $query->withContainsCommit($contains);
    }

    $refs = $query->execute();

    return $this->processBranchRefs($request, $refs);
  }

  protected function getSVNResult(ConduitAPIRequest $request) {
    // Since SVN doesn't have meaningful branches, just return nothing for all
    // queries.
    return array();
  }

  private function processBranchRefs(ConduitAPIRequest $request, array $refs) {
    $drequest = $this->getDiffusionRequest();
    $repository = $drequest->getRepository();
    $offset = $request->getValue('offset');
    $limit = $request->getValue('limit');

    foreach ($refs as $key => $ref) {
      if (!$repository->shouldTrackBranch($ref->getShortName())) {
        unset($refs[$key]);
      }
    }

    $with_closed = $request->getValue('closed');
    if ($with_closed !== null) {
      foreach ($refs as $key => $ref) {
        $fields = $ref->getRawFields();
        if (idx($fields, 'closed') != $with_closed) {
          unset($refs[$key]);
        }
      }
    }

    // NOTE: We can't apply the offset or limit until here, because we may have
    // filtered untrackable branches out of the result set.

    if ($offset) {
      $refs = array_slice($refs, $offset);
    }

    if ($limit) {
      $refs = array_slice($refs, 0, $limit);
    }

    return mpull($refs, 'toDictionary');
  }

}
