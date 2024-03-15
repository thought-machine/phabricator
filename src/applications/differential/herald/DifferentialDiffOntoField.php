i<?php

final class DifferentialDiffOntoField
  extends DifferentialDiffHeraldField {

  const FIELDCONST = 'differential.diff.refs.onto';

  public function getHeraldFieldName() {
    return pht('Onto branch');
  }

  public function getHeraldFieldValue($object) {
    return $object->loadTargetBranch();
  }

  protected function getHeraldFieldStandardType() {
    return self::STANDARD_TEXT;
  }
}
