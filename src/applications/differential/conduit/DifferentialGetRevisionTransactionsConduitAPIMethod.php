<?php

final class DifferentialGetRevisionTransactionsConduitAPIMethod
  extends DifferentialConduitAPIMethod {

  public function getAPIMethodName() {
    return 'differential.getrevisiontransactions';
  }

  public function getMethodDescription() {
    return pht('Retrieve Differential revision transactions.');
  }

  protected function defineParamTypes() {
    return array(
      'ids' => 'required list<int>',
    );
  }

  protected function defineReturnType() {
    return 'nonempty list<dict<string, wild>>';
  }

  protected function execute(ConduitAPIRequest $request) {
    $results = array();
    $revision_ids = $request->getValue('ids');

    if (!$revision_ids) {
      return $results;
    }

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($request->getUser())
      ->withIDs($revision_ids)
      ->execute();
    $revisions = mpull($revisions, null, 'getPHID');

    $transactions = array();
    if ($revisions) {
      $transactions = id(new DifferentialTransactionQuery())
        ->setViewer($request->getUser())
        ->withObjectPHIDs(mpull($revisions, 'getPHID'))
        ->needComments(true)
        ->execute();
    }

    foreach ($transactions as $transaction) {
      $revision_phid = $transaction->getObjectPHID();
      if (empty($revisions[$revision_phid])) {
        continue;
      }

      $revision_id = $revisions[$revision_phid]->getID();

      $comments = null;
      if ($transaction->hasComment()) {
        $comments = $transaction->getComment()->getContent();
      }

      $results[$revision_id][] = array(
        'revisionID' => $revision_id,
        'transactionPHID' => $transaction->getPHID(),
        'transactionType' => $transaction->getTransactionType(),
        'oldValue' => $transaction->getOldValue(),
        'newValue' => $transaction->getNewValue(),
        'comments' => $comments,
        'authorPHID' => $transaction->getAuthorPHID(),
        'dateCreated' => $transaction->getDateCreated(),
      );
    }

    return $results;
  }

}
