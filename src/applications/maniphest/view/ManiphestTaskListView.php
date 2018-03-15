<?php

final class ManiphestTaskListView extends ManiphestView {

  private $tasks;
  private $handles;
  private $showBatchControls;
  private $showSubpriorityControls;
  private $noDataString;

  public function setTasks(array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');
    $this->tasks = $tasks;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setShowBatchControls($show_batch_controls) {
    $this->showBatchControls = $show_batch_controls;
    return $this;
  }

  public function setShowSubpriorityControls($show_subpriority_controls) {
    $this->showSubpriorityControls = $show_subpriority_controls;
    return $this;
  }

  public function setNoDataString($text) {
    $this->noDataString = $text;
    return $this;
  }

  public function render() {
    $handles = $this->handles;

    require_celerity_resource('maniphest-task-summary-css');

    $list = new PHUIObjectItemListView();

    if ($this->noDataString) {
      $list->setNoDataString($this->noDataString);
    } else {
      $list->setNoDataString(pht('No tasks.'));
    }

    $status_map = ManiphestTaskStatus::getTaskStatusMap();
    $priority_map = ManiphestTaskPriority::getTaskPriorityMap();

    if ($this->showBatchControls) {
      Javelin::initBehavior('maniphest-list-editor');
    }

    $subtype_map = id(new ManiphestTask())
      ->newEditEngineSubtypeMap();

    foreach ($this->tasks as $task) {
      $item = id(new PHUIObjectItemView())
        ->setUser($this->getUser())
        ->setObject($task)
        ->setObjectName('T'.$task->getID())
        ->setHeader($task->getTitle())
        ->setHref('/T'.$task->getID());

      if ($task->getOwnerPHID()) {
        $owner = $handles[$task->getOwnerPHID()];
        $item->addByline(pht('Assigned: %s', $owner->renderLink()));
      }

      $status = $task->getStatus();
      $pri = idx($priority_map, $task->getPriority());
      $status_name = idx($status_map, $task->getStatus());
      $tooltip = pht('%s, %s', $status_name, $pri);

      // TM CHANGES BEGIN: Fetch custom fields for the given task.
      $field_list = PhabricatorCustomField::getObjectFields(
        $task,
        PhabricatorCustomField::ROLE_VIEW);
      $field_list->readFieldsFromStorage($task);

      $icon = self::getIcon($task, $field_list);
      $category = self::getCategory($task, $field_list);
      $color = self::getColor($task);
      // TM CHANGES END
      if ($task->isClosed()) {
        $item->setDisabled(true);
        $color = 'grey';
      }

      $item->setStatusIcon($icon.' '.$color, $tooltip);

      if ($task->isClosed()) {
        $closed_epoch = $task->getClosedEpoch();

        // We don't expect a task to be closed without a closed epoch, but
        // recover if we find one. This can happen with older objects or with
        // lipsum test data.
        if (!$closed_epoch) {
          $closed_epoch = $task->getDateModified();
        }

        $item->addIcon(
          'fa-check-square-o grey',
          phabricator_datetime($closed_epoch, $this->getUser()));
      } else {
        // TM CHANGES BEGIN: Show task creation date
        $item->addIcon(
          'none',
          phabricator_datetime($task->getDateCreated(), $this->getUser()));
        // TM CHANGES END
        $item->addIcon(
          'none',
          phabricator_datetime($task->getDateModified(), $this->getUser()));
      }

      if ($this->showSubpriorityControls) {
        $item->setGrippable(true);
      }
      if ($this->showSubpriorityControls || $this->showBatchControls) {
        $item->addSigil('maniphest-task');
      }

      $subtype = $task->newSubtypeObject();
      if ($subtype && $subtype->hasTagView()) {
        $subtype_tag = $subtype->newTagView()
          ->setSlimShady(true);
        $item->addAttribute($subtype_tag);
      }

      $project_handles = array_select_keys(
        $handles,
        array_reverse($task->getProjectPHIDs()));

      $item->addAttribute(
        id(new PHUIHandleTagListView())
          ->setLimit(4)
          ->setNoDataString(pht('No Projects'))
          ->setSlim(true)
          ->setHandles($project_handles));

      // TM CHANGES BEGIN:
      $item->addAttribute('Status: '.$status_name);
      if ($category != 'Unclassified') {
        $item->addAttribute($category);
      }
      // TM CHANGES END

      $item->setMetadata(
        array(
          'taskID' => $task->getID(),
        ));

      if ($this->showBatchControls) {
        $href = new PhutilURI('/maniphest/task/edit/'.$task->getID().'/');
        if (!$this->showSubpriorityControls) {
          $href->setQueryParam('ungrippable', 'true');
        }
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-pencil')
            ->addSigil('maniphest-edit-task')
            ->setHref($href));
      }

      $list->addItem($item);
    }

    return $list;
  }

  public static function loadTaskHandles(
    PhabricatorUser $viewer,
    array $tasks) {
    assert_instances_of($tasks, 'ManiphestTask');

    $phids = array();
    foreach ($tasks as $task) {
      $assigned_phid = $task->getOwnerPHID();
      if ($assigned_phid) {
        $phids[] = $assigned_phid;
      }
      foreach ($task->getProjectPHIDs() as $project_phid) {
        $phids[] = $project_phid;
      }
    }

    if (!$phids) {
      return array();
    }

    return id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();
  }

  // TM CHANGES BEGIN
  /**
   * Get a task's corresponding icon color as a function of its priority.
   */
  public static function getColor($task) {
    $color_map = ManiphestTaskPriority::getColorMap();
    return idx($color_map, $task->getPriority(), 'grey');
  }

  /**
   * Get a task's corresponding category.
   */
  public static function getCategory($task, $field_list) {
    foreach ($field_list->getFields() as $val) {
      if ($val->getFieldName() == 'Category') {
        $cat = $val->getOldValueForApplicationTransactions();
        if ($cat == '') {
          return 'Unclassified';
        } else {
          return $cat;
        }
      }
    }
    return 'Unclassified';
  }

  /**
   * Get a task's corresponding icon depending on whether it is a bug or a
   * feature.
   */
  public static function getIcon($task, $field_list) {
    // Figure out whether the task is a bug or a feature (i.e. look at the
    // classification, which is a custom fields). The icon will change
    // depending on this.
    foreach ($field_list->getFields() as $val) {
      if ($val->getFieldName() == 'Classification') {
        $bug_or_feature = $val->getOldValueForApplicationTransactions();
        if ($bug_or_feature == 'tasktype:feature') {
          return 'fa-star';
        } else if ($bug_or_feature == 'tasktype:bug') {
          return 'fa-bug';
        } else if ($bug_or_feature == 'tasktype:design') {
          return 'fa-paint-brush';
        } else if ($bug_or_feature == 'tasktype:tdebt') {
          return 'fa-puzzle-piece';
        } else if ($bug_or_feature == 'tasktype:releases') {
          return 'fa-code-fork';
        } else if ($bug_or_feature == 'tasktype:Research') {
          return 'fa-flask';
        } else if ($bug_or_feature == 'tasktype:hr') {
          return 'fa-users';
        } else if ($bug_or_feature == 'tasktype:Vulnerability') {
          return 'fa-bullseye';
        } else if ($bug_or_feature == 'tasktype:epic') {
          return 'fa-cubes';
        } else if ($bug_or_feature == 'tasktype:Security') {
          return 'fa-shield';
        }
      }
    }
    return 'fa-question';
  }
  // TM CHANGES END
}
