i<?php

final class DifferentialDiffOntoField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.refs.onto';

  public function getHeraldFieldName() {
    return pht('Onto branch');
  }

  public function getHeraldFieldValue($object) {
    $onto = $object->loadTargetBranch();
    if (phutil_nonempty_string($onto)) {
      return $onto;
    }
    return '';
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }
}
