<?php

final class PhabricatorProjectBoardViewController
  extends PhabricatorProjectBoardController {

  const BATCH_EDIT_ALL = 'all';

  private $id;
  private $slug;
  private $queryKey;
  private $sortKey;
  private $showHidden;

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();

    $response = $this->loadProject();
    if ($response) {
      return $response;
    }

    $project = $this->getProject();

    $this->readRequestState();

    $board_uri = $this->getApplicationURI('board/'.$project->getID().'/');

    $search_engine = id(new ManiphestTaskSearchEngine())
      ->setViewer($viewer)
      ->setBaseURI($board_uri)
      ->setIsBoardView(true);

    if ($request->isFormPost()
      && !$request->getBool('initialize')
      && !$request->getStr('move')
      && !$request->getStr('queryColumnID')) {
      $saved = $search_engine->buildSavedQueryFromRequest($request);
      $search_engine->saveQuery($saved);
      $filter_form = id(new AphrontFormView())
        ->setUser($viewer);
      $search_engine->buildSearchForm($filter_form, $saved);
      if ($search_engine->getErrors()) {
        return $this->newDialog()
          ->setWidth(AphrontDialogView::WIDTH_FULL)
          ->setTitle(pht('Advanced Filter'))
          ->appendChild($filter_form->buildLayoutView())
          ->setErrors($search_engine->getErrors())
          ->setSubmitURI($board_uri)
          ->addSubmitButton(pht('Apply Filter'))
          ->addCancelButton($board_uri);
      }
      return id(new AphrontRedirectResponse())->setURI(
        $this->getURIWithState(
          $search_engine->getQueryResultsPageURI($saved->getQueryKey())));
    }

    $query_key = $this->getDefaultFilter($project);

    $request_query = $request->getStr('filter');
    if (strlen($request_query)) {
      $query_key = $request_query;
    }

    $uri_query = $request->getURIData('queryKey');
    if (strlen($uri_query)) {
      $query_key = $uri_query;
    }

    $this->queryKey = $query_key;

    $custom_query = null;
    if ($search_engine->isBuiltinQuery($query_key)) {
      $saved = $search_engine->buildSavedQueryFromBuiltin($query_key);
    } else {
      $saved = id(new PhabricatorSavedQueryQuery())
        ->setViewer($viewer)
        ->withQueryKeys(array($query_key))
        ->executeOne();

      if (!$saved) {
        return new Aphront404Response();
      }

      $custom_query = $saved;
    }

    if ($request->getURIData('filter')) {
      $filter_form = id(new AphrontFormView())
        ->setUser($viewer);
      $search_engine->buildSearchForm($filter_form, $saved);

      return $this->newDialog()
        ->setWidth(AphrontDialogView::WIDTH_FULL)
        ->setTitle(pht('Advanced Filter'))
        ->appendChild($filter_form->buildLayoutView())
        ->setSubmitURI($board_uri)
        ->addSubmitButton(pht('Apply Filter'))
        ->addCancelButton($board_uri);
    }

    $task_query = $search_engine->buildQueryFromSavedQuery($saved);

    $select_phids = array($project->getPHID());
    if ($project->getHasSubprojects() || $project->getHasMilestones()) {
      $descendants = id(new PhabricatorProjectQuery())
        ->setViewer($viewer)
        ->withAncestorProjectPHIDs($select_phids)
        ->execute();
      foreach ($descendants as $descendant) {
        $select_phids[] = $descendant->getPHID();
      }
    }

    $tasks = $task_query
      ->withEdgeLogicPHIDs(
        PhabricatorProjectObjectHasProjectEdgeType::EDGECONST,
        PhabricatorQueryConstraint::OPERATOR_ANCESTOR,
        array($select_phids))
      ->setOrder(ManiphestTaskQuery::ORDER_PRIORITY)
      ->setViewer($viewer)
      ->execute();
    $tasks = mpull($tasks, null, 'getPHID');

    $board_phid = $project->getPHID();

    // Regardless of display order, pass tasks to the layout engine in ID order
    // so layout is consistent.
    $board_tasks = msort($tasks, 'getID');

    $layout_engine = id(new PhabricatorBoardLayoutEngine())
      ->setViewer($viewer)
      ->setBoardPHIDs(array($board_phid))
      ->setObjectPHIDs(array_keys($board_tasks))
      ->setFetchAllBoards(true)
      ->executeLayout();

    $columns = $layout_engine->getColumns($board_phid);
    if (!$columns || !$project->getHasWorkboard()) {
      $has_normal_columns = false;

      foreach ($columns as $column) {
        if (!$column->getProxyPHID()) {
          $has_normal_columns = true;
          break;
        }
      }

      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $viewer,
        $project,
        PhabricatorPolicyCapability::CAN_EDIT);

      if (!$has_normal_columns) {
        if (!$can_edit) {
          $content = $this->buildNoAccessContent($project);
        } else {
          $content = $this->buildInitializeContent($project);
        }
      } else {
        if (!$can_edit) {
          $content = $this->buildDisabledContent($project);
        } else {
          $content = $this->buildEnableContent($project);
        }
      }

      if ($content instanceof AphrontResponse) {
        return $content;
      }

      $nav = $this->newNavigation(
        $project,
        PhabricatorProject::ITEM_WORKBOARD);

      $crumbs = $this->buildApplicationCrumbs();
      $crumbs->addTextCrumb(pht('Workboard'));

      return $this->newPage()
        ->setTitle(
          array(
            $project->getDisplayName(),
            pht('Workboard'),
          ))
        // TM CHANGES BEGIN: Allow external sites to embed this page
        ->setFrameable(true)
        // TM CHANGES END
        ->setNavigation($nav)
        ->setCrumbs($crumbs)
        ->appendChild($content);
    }

    // If the user wants to turn a particular column into a query, build an
    // apropriate filter and redirect them to the query results page.
    $query_column_id = $request->getInt('queryColumnID');
    if ($query_column_id) {
      $column_id_map = mpull($columns, null, 'getID');
      $query_column = idx($column_id_map, $query_column_id);
      if (!$query_column) {
        return new Aphront404Response();
      }

      // Create a saved query to combine the active filter on the workboard
      // with the column filter. If the user currently has constraints on the
      // board, we want to add a new column or project constraint, not
      // completely replace the constraints.
      $saved_query = $saved->newCopy();

      if ($query_column->getProxyPHID()) {
        $project_phids = $saved_query->getParameter('projectPHIDs');
        if (!$project_phids) {
          $project_phids = array();
        }
        $project_phids[] = $query_column->getProxyPHID();
        $saved_query->setParameter('projectPHIDs', $project_phids);
      } else {
        $saved_query->setParameter(
          'columnPHIDs',
          array($query_column->getPHID()));
      }

      $search_engine = id(new ManiphestTaskSearchEngine())
        ->setViewer($viewer);
      $search_engine->saveQuery($saved_query);

      $query_key = $saved_query->getQueryKey();
      $query_uri = new PhutilURI("/maniphest/query/{$query_key}/#R");

      return id(new AphrontRedirectResponse())
        ->setURI($query_uri);
    }

    $task_can_edit_map = id(new PhabricatorPolicyFilter())
      ->setViewer($viewer)
      ->requireCapabilities(array(PhabricatorPolicyCapability::CAN_EDIT))
      ->apply($tasks);

    // If this is a batch edit, select the editable tasks in the chosen column
    // and ship the user into the batch editor.
    $batch_edit = $request->getStr('batch');
    if ($batch_edit) {
      if ($batch_edit !== self::BATCH_EDIT_ALL) {
        $column_id_map = mpull($columns, null, 'getID');
        $batch_column = idx($column_id_map, $batch_edit);
        if (!$batch_column) {
          return new Aphront404Response();
        }

        $batch_task_phids = $layout_engine->getColumnObjectPHIDs(
          $board_phid,
          $batch_column->getPHID());

        foreach ($batch_task_phids as $key => $batch_task_phid) {
          if (empty($task_can_edit_map[$batch_task_phid])) {
            unset($batch_task_phids[$key]);
          }
        }

        $batch_tasks = array_select_keys($tasks, $batch_task_phids);
      } else {
        $batch_tasks = $task_can_edit_map;
      }

      if (!$batch_tasks) {
        $cancel_uri = $this->getURIWithState($board_uri);
        return $this->newDialog()
          ->setTitle(pht('No Editable Tasks'))
          ->appendParagraph(
            pht(
              'The selected column contains no visible tasks which you '.
              'have permission to edit.'))
          ->addCancelButton($board_uri);
      }

      // Create a saved query to hold the working set. This allows us to get
      // around URI length limitations with a long "?ids=..." query string.
      // For details, see T10268.
      $search_engine = id(new ManiphestTaskSearchEngine())
        ->setViewer($viewer);

      $saved_query = $search_engine->newSavedQuery();
      $saved_query->setParameter('ids', mpull($batch_tasks, 'getID'));
      $search_engine->saveQuery($saved_query);

      $query_key = $saved_query->getQueryKey();

      $bulk_uri = new PhutilURI("/maniphest/bulk/query/{$query_key}/");
      $bulk_uri->replaceQueryParam('board', $this->id);

      return id(new AphrontRedirectResponse())
        ->setURI($bulk_uri);
    }

    $move_id = $request->getStr('move');
    if (strlen($move_id)) {
      $column_id_map = mpull($columns, null, 'getID');
      $move_column = idx($column_id_map, $move_id);
      if (!$move_column) {
        return new Aphront404Response();
      }

      $move_task_phids = $layout_engine->getColumnObjectPHIDs(
        $board_phid,
        $move_column->getPHID());

      foreach ($move_task_phids as $key => $move_task_phid) {
        if (empty($task_can_edit_map[$move_task_phid])) {
          unset($move_task_phids[$key]);
        }
      }

      $move_tasks = array_select_keys($tasks, $move_task_phids);
      $cancel_uri = $this->getURIWithState($board_uri);

      if (!$move_tasks) {
        return $this->newDialog()
          ->setTitle(pht('No Movable Tasks'))
          ->appendParagraph(
            pht(
              'The selected column contains no visible tasks which you '.
              'have permission to move.'))
          ->addCancelButton($cancel_uri);
      }

      $move_project_phid = $project->getPHID();
      $move_column_phid = null;
      $move_project = null;
      $move_column = null;
      $columns = null;
      $errors = array();

      if ($request->isFormOrHiSecPost()) {
        $move_project_phid = head($request->getArr('moveProjectPHID'));
        if (!$move_project_phid) {
          $move_project_phid = $request->getStr('moveProjectPHID');
        }

        if (!$move_project_phid) {
          if ($request->getBool('hasProject')) {
            $errors[] = pht('Choose a project to move tasks to.');
          }
        } else {
          $target_project = id(new PhabricatorProjectQuery())
            ->setViewer($viewer)
            ->withPHIDs(array($move_project_phid))
            ->executeOne();
          if (!$target_project) {
            $errors[] = pht('You must choose a valid project.');
          } else if (!$project->getHasWorkboard()) {
            $errors[] = pht(
              'You must choose a project with a workboard.');
          } else {
            $move_project = $target_project;
          }
        }

        if ($move_project) {
          $move_engine = id(new PhabricatorBoardLayoutEngine())
            ->setViewer($viewer)
            ->setBoardPHIDs(array($move_project->getPHID()))
            ->setFetchAllBoards(true)
            ->executeLayout();

          $columns = $move_engine->getColumns($move_project->getPHID());
          $columns = mpull($columns, null, 'getPHID');

          foreach ($columns as $key => $column) {
            if ($column->isHidden()) {
              unset($columns[$key]);
            }
          }

          $move_column_phid = $request->getStr('moveColumnPHID');
          if (!$move_column_phid) {
            if ($request->getBool('hasColumn')) {
              $errors[] = pht('Choose a column to move tasks to.');
            }
          } else {
            if (empty($columns[$move_column_phid])) {
              $errors[] = pht(
                'Choose a valid column on the target workboard to move '.
                'tasks to.');
            } else if ($columns[$move_column_phid]->getID() == $move_id) {
              $errors[] = pht(
                'You can not move tasks from a column to itself.');
            } else {
              $move_column = $columns[$move_column_phid];
            }
          }
        }
      }

      if ($move_column && $move_project) {
        foreach ($move_tasks as $move_task) {
          $xactions = array();

          // If we're switching projects, get out of the old project first
          // and move to the new project.
          if ($move_project->getID() != $project->getID()) {
            $xactions[] = id(new ManiphestTransaction())
              ->setTransactionType(PhabricatorTransactions::TYPE_EDGE)
              ->setMetadataValue(
                'edge:type',
                PhabricatorProjectObjectHasProjectEdgeType::EDGECONST)
              ->setNewValue(
                array(
                  '-' => array(
                    $project->getPHID() => $project->getPHID(),
                  ),
                  '+' => array(
                    $move_project->getPHID() => $move_project->getPHID(),
                  ),
                ));
          }

          $xactions[] = id(new ManiphestTransaction())
            ->setTransactionType(PhabricatorTransactions::TYPE_COLUMNS)
            ->setNewValue(
              array(
                array(
                  'columnPHID' => $move_column->getPHID(),
                ),
              ));

          $editor = id(new ManiphestTransactionEditor())
            ->setActor($viewer)
            ->setContinueOnMissingFields(true)
            ->setContinueOnNoEffect(true)
            ->setContentSourceFromRequest($request)
            ->setCancelURI($cancel_uri);

          $editor->applyTransactions($move_task, $xactions);
        }

        return id(new AphrontRedirectResponse())
          ->setURI($cancel_uri);
      }

      if ($move_project) {
        $column_form = id(new AphrontFormView())
          ->setViewer($viewer)
          ->appendControl(
            id(new AphrontFormSelectControl())
              ->setName('moveColumnPHID')
              ->setLabel(pht('Move to Column'))
              ->setValue($move_column_phid)
              ->setOptions(mpull($columns, 'getDisplayName', 'getPHID')));

        return $this->newDialog()
          ->setTitle(pht('Move Tasks'))
          ->setWidth(AphrontDialogView::WIDTH_FORM)
          ->setErrors($errors)
          ->addHiddenInput('move', $move_id)
          ->addHiddenInput('moveProjectPHID', $move_project->getPHID())
          ->addHiddenInput('hasColumn', true)
          ->addHiddenInput('hasProject', true)
          ->appendParagraph(
            pht(
              'Choose a column on the %s workboard to move tasks to:',
              $viewer->renderHandle($move_project->getPHID())))
          ->appendForm($column_form)
          ->addSubmitButton(pht('Move Tasks'))
          ->addCancelButton($cancel_uri);
      }

      if ($move_project_phid) {
        $move_project_phid_value = array($move_project_phid);
      } else {
        $move_project_phid_value = array();
      }

      $project_form = id(new AphrontFormView())
        ->setViewer($viewer)
        ->appendControl(
          id(new AphrontFormTokenizerControl())
            ->setName('moveProjectPHID')
            ->setLimit(1)
            ->setLabel(pht('Move to Project'))
            ->setValue($move_project_phid_value)
            ->setDatasource(new PhabricatorProjectDatasource()));

      return $this->newDialog()
        ->setTitle(pht('Move Tasks'))
        ->setWidth(AphrontDialogView::WIDTH_FORM)
        ->setErrors($errors)
        ->addHiddenInput('move', $move_id)
        ->addHiddenInput('hasProject', true)
        ->appendForm($project_form)
        ->addSubmitButton(pht('Continue'))
        ->addCancelButton($cancel_uri);
    }


    $board_id = celerity_generate_unique_node_id();

    $board = id(new PHUIWorkboardView())
      ->setUser($viewer)
      ->setID($board_id)
      ->addSigil('jx-workboard')
      ->setMetadata(
        array(
          'boardPHID' => $project->getPHID(),
        ));

    $visible_columns = array();
    $column_phids = array();
    $visible_phids = array();
    foreach ($columns as $column) {
      if (!$this->showHidden) {
        if ($column->isHidden()) {
          continue;
        }
      }

      $proxy = $column->getProxy();
      if ($proxy && !$proxy->isMilestone()) {
        // TODO: For now, don't show subproject columns because we can't
        // handle tasks with multiple positions yet.
        continue;
      }

      $task_phids = $layout_engine->getColumnObjectPHIDs(
        $board_phid,
        $column->getPHID());

      $column_tasks = array_select_keys($tasks, $task_phids);
      $column_phid = $column->getPHID();

      $visible_columns[$column_phid] = $column;
      $column_phids[$column_phid] = $column_tasks;

      foreach ($column_tasks as $phid => $task) {
        $visible_phids[$phid] = $phid;
      }
    }

    $rendering_engine = id(new PhabricatorBoardRenderingEngine())
      ->setViewer($viewer)
      ->setObjects(array_select_keys($tasks, $visible_phids))
      ->setEditMap($task_can_edit_map)
      ->setExcludedProjectPHIDs($select_phids);

    $templates = array();
    $all_tasks = array();
    $column_templates = array();
    $sounds = array();
    foreach ($visible_columns as $column_phid => $column) {
      $column_tasks = $column_phids[$column_phid];

      $panel = id(new PHUIWorkpanelView())
        ->setHeader($column->getDisplayName())
        ->setSubHeader($column->getDisplayType())
        ->addSigil('workpanel');

      $proxy = $column->getProxy();
      if ($proxy) {
        $proxy_id = $proxy->getID();
        $href = $this->getApplicationURI("view/{$proxy_id}/");
        $panel->setHref($href);
      }

      $header_icon = $column->getHeaderIcon();
      if ($header_icon) {
        $panel->setHeaderIcon($header_icon);
      }

      $display_class = $column->getDisplayClass();
      if ($display_class) {
        $panel->addClass($display_class);
      }

      if ($column->isHidden()) {
        $panel->addClass('project-panel-hidden');
      }

      $column_menu = $this->buildColumnMenu($project, $column);
      $panel->addHeaderAction($column_menu);

      if ($column->canHaveTrigger()) {
        $trigger = $column->getTrigger();
        if ($trigger) {
          $trigger->setViewer($viewer);
        }

        $trigger_menu = $this->buildTriggerMenu($column);
        $panel->addHeaderAction($trigger_menu);
      }

      $count_tag = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setColor(PHUITagView::COLOR_BLUE)
        ->addSigil('column-points')
        ->setName(
          javelin_tag(
            'span',
            array(
              'sigil' => 'column-points-content',
            ),
            pht('-')))
        ->setStyle('display: none');

      $panel->setHeaderTag($count_tag);

      $cards = id(new PHUIObjectItemListView())
        ->setUser($viewer)
        ->setFlush(true)
        ->setAllowEmptyList(true)
        ->addSigil('project-column')
        ->setItemClass('phui-workcard')
        ->setMetadata(
          array(
            'columnPHID' => $column->getPHID(),
            'pointLimit' => $column->getPointLimit(),
          ));

      $card_phids = array();
      foreach ($column_tasks as $task) {
        $object_phid = $task->getPHID();

        $card = $rendering_engine->renderCard($object_phid);
        $templates[$object_phid] = hsprintf('%s', $card->getItem());
        $card_phids[] = $object_phid;

        $all_tasks[$object_phid] = $task;
      }

      $panel->setCards($cards);
      $board->addPanel($panel);

      $drop_effects = $column->getDropEffects();
      $drop_effects = mpull($drop_effects, 'toDictionary');

      $preview_effect = null;
      if ($column->canHaveTrigger()) {
        $trigger = $column->getTrigger();
        if ($trigger) {
          $preview_effect = $trigger->getPreviewEffect()
            ->toDictionary();

          foreach ($trigger->getSoundEffects() as $sound) {
            $sounds[] = $sound;
          }
        }
      }

      $column_templates[] = array(
        'columnPHID' => $column_phid,
        'effects' => $drop_effects,
        'cardPHIDs' => $card_phids,
        'triggerPreviewEffect' => $preview_effect,
      );
    }

    $order_key = $this->sortKey;

    $ordering_map = PhabricatorProjectColumnOrder::getEnabledOrders();
    $ordering = id(clone $ordering_map[$order_key])
      ->setViewer($viewer);

    $headers = $ordering->getHeadersForObjects($all_tasks);
    $headers = mpull($headers, 'toDictionary');

    $vectors = $ordering->getSortVectorsForObjects($all_tasks);
    $vector_map = array();
    foreach ($vectors as $task_phid => $vector) {
      $vector_map[$task_phid][$order_key] = $vector;
    }

    $header_keys = $ordering->getHeaderKeysForObjects($all_tasks);

    $order_maps = array();
    $order_maps[] = $ordering->toDictionary();

    $properties = array();
    foreach ($all_tasks as $task) {
      $properties[$task->getPHID()] =
        PhabricatorBoardResponseEngine::newTaskProperties($task);
    }

    $behavior_config = array(
      'moveURI' => $this->getApplicationURI('move/'.$project->getID().'/'),
      'uploadURI' => '/file/dropupload/',
      'coverURI' => $this->getApplicationURI('cover/'),
      'chunkThreshold' => PhabricatorFileStorageEngine::getChunkThreshold(),
      'pointsEnabled' => ManiphestTaskPoints::getIsEnabled(),

      'boardPHID' => $project->getPHID(),
      'order' => $this->sortKey,
      'orders' => $order_maps,
      'headers' => $headers,
      'headerKeys' => $header_keys,
      'templateMap' => $templates,
      'orderMaps' => $vector_map,
      'propertyMaps' => $properties,
      'columnTemplates' => $column_templates,

      'boardID' => $board_id,
      'projectPHID' => $project->getPHID(),
      'preloadSounds' => $sounds,
    );
    $this->initBehavior('project-boards', $behavior_config);

    $sort_menu = $this->buildSortMenu(
      $viewer,
      $project,
      $this->sortKey,
      $ordering_map);

    $filter_menu = $this->buildFilterMenu(
      $viewer,
      $project,
      $custom_query,
      $search_engine,
      $query_key);

    $manage_menu = $this->buildManageMenu($project, $this->showHidden);

    $header_link = phutil_tag(
      'a',
      array(
        'href' => $this->getApplicationURI('profile/'.$project->getID().'/'),
      ),
      $project->getName());

    $board_box = id(new PHUIBoxView())
      ->appendChild($board)
      ->addClass('project-board-wrapper');

    $nav = $this->newNavigation(
      $project,
      PhabricatorProject::ITEM_WORKBOARD);

    $divider = id(new PHUIListItemView())
      ->setType(PHUIListItemView::TYPE_DIVIDER);
    $fullscreen = $this->buildFullscreenMenu();

    $crumbs = $this->newWorkboardCrumbs();
    $crumbs->addTextCrumb(pht('Workboard'));
    $crumbs->setBorder(true);

    $crumbs->addAction($sort_menu);
    $crumbs->addAction($filter_menu);
    $crumbs->addAction($divider);
    $crumbs->addAction($manage_menu);
    $crumbs->addAction($fullscreen);

    $page = $this->newPage()
      ->setTitle(
        array(
          $project->getDisplayName(),
          pht('Workboard'),
        ))
      ->setPageObjectPHIDs(array($project->getPHID()))
      ->setShowFooter(false)
      ->setNavigation($nav)
      ->setCrumbs($crumbs)
      ->addQuicksandConfig(
        array(
          'boardConfig' => $behavior_config,
        ))
      ->appendChild(
        array(
          $board_box,
        ));

    $background = $project->getDisplayWorkboardBackgroundColor();
    require_celerity_resource('phui-workboard-color-css');
    if ($background !== null) {
      $background_color_class = "phui-workboard-{$background}";

      $page->addClass('phui-workboard-color');
      $page->addClass($background_color_class);
    } else {
      $page->addClass('phui-workboard-no-color');
    }

    return $page;
  }

  private function readRequestState() {
    $request = $this->getRequest();
    $project = $this->getProject();

    $this->showHidden = $request->getBool('hidden');
    $this->id = $project->getID();

    $sort_key = $this->getDefaultSort($project);

    $request_sort = $request->getStr('order');
    if ($this->isValidSort($request_sort)) {
      $sort_key = $request_sort;
    }

    $this->sortKey = $sort_key;
  }

  private function getDefaultSort(PhabricatorProject $project) {
    $default_sort = $project->getDefaultWorkboardSort();

    if ($this->isValidSort($default_sort)) {
      return $default_sort;
    }

    return PhabricatorProjectColumnNaturalOrder::ORDERKEY;
  }

  private function getDefaultFilter(PhabricatorProject $project) {
    $default_filter = $project->getDefaultWorkboardFilter();

    if (strlen($default_filter)) {
      return $default_filter;
    }

    return 'open';
  }

  private function isValidSort($sort) {
    $map = PhabricatorProjectColumnOrder::getEnabledOrders();
    return isset($map[$sort]);
  }

  private function buildSortMenu(
    PhabricatorUser $viewer,
    PhabricatorProject $project,
    $sort_key,
    array $ordering_map) {

    $base_uri = $this->getURIWithState();

    $items = array();
    foreach ($ordering_map as $key => $ordering) {
      // TODO: It would be desirable to build a real "PHUIIconView" here, but
      // the pathway for threading that through all the view classes ends up
      // being fairly complex, since some callers read the icon out of other
      // views. For now, just stick with a string.
      $ordering_icon = $ordering->getMenuIconIcon();
      $ordering_name = $ordering->getDisplayName();

      $is_selected = ($key === $sort_key);
      if ($is_selected) {
        $active_name = $ordering_name;
        $active_icon = $ordering_icon;
      }

      $item = id(new PhabricatorActionView())
        ->setIcon($ordering_icon)
        ->setSelected($is_selected)
        ->setName($ordering_name);

      $uri = $base_uri->alter('order', $key);
      $item->setHref($uri);

      $items[] = $item;
    }

    $id = $project->getID();

    $save_uri = "default/{$id}/sort/";
    $save_uri = $this->getApplicationURI($save_uri);
    $save_uri = $this->getURIWithState($save_uri, $force = true);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-floppy-o')
      ->setName(pht('Save as Default'))
      ->setHref($save_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $sort_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($items as $item) {
      $sort_menu->addAction($item);
    }

    $sort_button = id(new PHUIListItemView())
      ->setName($active_name)
      ->setIcon($active_icon)
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $sort_menu),
        ));

    return $sort_button;
  }

  private function buildFilterMenu(
    PhabricatorUser $viewer,
    PhabricatorProject $project,
    $custom_query,
    PhabricatorApplicationSearchEngine $engine,
    $query_key) {

    $named = array(
      'open' => pht('Open Tasks'),
      'all' => pht('All Tasks'),
    );

    if ($viewer->isLoggedIn()) {
      $named['assigned'] = pht('Assigned to Me');
    }

    if ($custom_query) {
      $named[$custom_query->getQueryKey()] = pht('Custom Filter');
    }

    $items = array();
    foreach ($named as $key => $name) {
      $is_selected = ($key == $query_key);
      if ($is_selected) {
        $active_filter = $name;
      }

      $is_custom = false;
      if ($custom_query) {
        $is_custom = ($key == $custom_query->getQueryKey());
      }

      $item = id(new PhabricatorActionView())
        ->setIcon('fa-search')
        ->setSelected($is_selected)
        ->setName($name);

      if ($is_custom) {
        $uri = $this->getApplicationURI(
          'board/'.$this->id.'/filter/query/'.$key.'/');
        $item->setWorkflow(true);
      } else {
        $uri = $engine->getQueryResultsPageURI($key);
      }

      $uri = $this->getURIWithState($uri)
        ->removeQueryParam('filter');
      $item->setHref($uri);

      $items[] = $item;
    }

    $id = $project->getID();

    $filter_uri = $this->getApplicationURI("board/{$id}/filter/");
    $filter_uri = $this->getURIWithState($filter_uri, $force = true);

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-cog')
      ->setHref($filter_uri)
      ->setWorkflow(true)
      ->setName(pht('Advanced Filter...'));

    $save_uri = "default/{$id}/filter/";
    $save_uri = $this->getApplicationURI($save_uri);
    $save_uri = $this->getURIWithState($save_uri, $force = true);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $items[] = id(new PhabricatorActionView())
      ->setIcon('fa-floppy-o')
      ->setName(pht('Save as Default'))
      ->setHref($save_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit);

    $filter_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($items as $item) {
      $filter_menu->addAction($item);
    }

    $filter_button = id(new PHUIListItemView())
      ->setName($active_filter)
      ->setIcon('fa-search')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $filter_menu),
        ));

    return $filter_button;
  }

  private function buildManageMenu(
    PhabricatorProject $project,
    $show_hidden) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $project->getID();

    $manage_uri = $this->getApplicationURI("board/{$id}/manage/");
    $add_uri = $this->getApplicationURI("board/{$id}/edit/");

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $manage_items = array();

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-plus')
      ->setName(pht('Add Column'))
      ->setHref($add_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    $reorder_uri = $this->getApplicationURI("board/{$id}/reorder/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-exchange')
      ->setName(pht('Reorder Columns'))
      ->setHref($reorder_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    if ($show_hidden) {
      $hidden_uri = $this->getURIWithState()
        ->removeQueryParam('hidden');
      $hidden_icon = 'fa-eye-slash';
      $hidden_text = pht('Hide Hidden Columns');
    } else {
      $hidden_uri = $this->getURIWithState()
        ->replaceQueryParam('hidden', 'true');
      $hidden_icon = 'fa-eye';
      $hidden_text = pht('Show Hidden Columns');
    }

    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon($hidden_icon)
      ->setName($hidden_text)
      ->setHref($hidden_uri);

    $manage_items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $background_uri = $this->getApplicationURI("board/{$id}/background/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-paint-brush')
      ->setName(pht('Change Background Color'))
      ->setHref($background_uri)
      ->setDisabled(!$can_edit)
      ->setWorkflow(false);

    $manage_uri = $this->getApplicationURI("board/{$id}/manage/");
    $manage_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-gear')
      ->setName(pht('Manage Workboard'))
      ->setHref($manage_uri);

    $batch_edit_uri = $request->getRequestURI();
    $batch_edit_uri->replaceQueryParam('batch', self::BATCH_EDIT_ALL);
    $can_batch_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorManiphestApplication'),
      ManiphestBulkEditCapability::CAPABILITY);

    $manage_menu = id(new PhabricatorActionListView())
        ->setUser($viewer);
    foreach ($manage_items as $item) {
      $manage_menu->addAction($item);
    }

    $manage_button = id(new PHUIListItemView())
      ->setIcon('fa-cog')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->addSigil('has-tooltip')
      ->setMetadata(
        array(
          'tip' => pht('Manage'),
          'align' => 'S',
          'items' => hsprintf('%s', $manage_menu),
        ));

    return $manage_button;
  }

  private function buildFullscreenMenu() {

    $up = id(new PHUIListItemView())
      ->setIcon('fa-arrows-alt')
      ->setHref('#')
      ->addClass('phui-workboard-expand-icon')
      ->addSigil('jx-toggle-class')
      ->addSigil('has-tooltip')
      ->setMetaData(array(
        'tip' => pht('Fullscreen'),
        'map' => array(
          'phabricator-standard-page' => 'phui-workboard-fullscreen',
        ),
      ));

    return $up;
  }

  private function buildColumnMenu(
    PhabricatorProject $project,
    PhabricatorProjectColumn $column) {

    $request = $this->getRequest();
    $viewer = $request->getUser();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $project,
      PhabricatorPolicyCapability::CAN_EDIT);

    $column_items = array();

    if ($column->getProxyPHID()) {
      $default_phid = $column->getProxyPHID();
    } else {
      $default_phid = $column->getProjectPHID();
    }

    $specs = id(new ManiphestEditEngine())
      ->setViewer($viewer)
      ->newCreateActionSpecifications(array());

    foreach ($specs as $spec) {
      $column_items[] = id(new PhabricatorActionView())
        ->setIcon($spec['icon'])
        ->setName($spec['name'])
        ->setHref($spec['uri'])
        ->setDisabled($spec['disabled'])
        ->addSigil('column-add-task')
        ->setMetadata(
          array(
            'createURI' => $spec['uri'],
            'columnPHID' => $column->getPHID(),
            'boardPHID' => $project->getPHID(),
            'projectPHID' => $default_phid,
          ));
    }

    $column_items[] = id(new PhabricatorActionView())
      ->setType(PhabricatorActionView::TYPE_DIVIDER);

    $batch_edit_uri = $request->getRequestURI();
    $batch_edit_uri->replaceQueryParam('batch', $column->getID());
    $can_batch_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      PhabricatorApplication::getByClass('PhabricatorManiphestApplication'),
      ManiphestBulkEditCapability::CAPABILITY);

    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-list-ul')
      ->setName(pht('Bulk Edit Tasks...'))
      ->setHref($batch_edit_uri)
      ->setDisabled(!$can_batch_edit);

    $batch_move_uri = $request->getRequestURI();
    $batch_move_uri->replaceQueryParam('move', $column->getID());
    $column_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-arrow-right')
      ->setName(pht('Move Tasks to Column...'))
      ->setHref($batch_move_uri)
      ->setWorkflow(true);

    $query_uri = $request->getRequestURI();
    $query_uri->replaceQueryParam('queryColumnID', $column->getID());

    $column_items[] = id(new PhabricatorActionView())
      ->setName(pht('View as Query'))
      ->setIcon('fa-search')
      ->setHref($query_uri);

    $edit_uri = 'board/'.$this->id.'/edit/'.$column->getID().'/';
    $column_items[] = id(new PhabricatorActionView())
      ->setName(pht('Edit Column'))
      ->setIcon('fa-pencil')
      ->setHref($this->getApplicationURI($edit_uri))
      ->setDisabled(!$can_edit)
      ->setWorkflow(true);

    $can_hide = ($can_edit && !$column->isDefaultColumn());
    $hide_uri = 'board/'.$this->id.'/hide/'.$column->getID().'/';
    $hide_uri = $this->getApplicationURI($hide_uri);
    $hide_uri = $this->getURIWithState($hide_uri);

    if (!$column->isHidden()) {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Hide Column'))
        ->setIcon('fa-eye-slash')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    } else {
      $column_items[] = id(new PhabricatorActionView())
        ->setName(pht('Show Column'))
        ->setIcon('fa-eye')
        ->setHref($hide_uri)
        ->setDisabled(!$can_hide)
        ->setWorkflow(true);
    }

    $column_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($column_items as $item) {
      $column_menu->addAction($item);
    }

    $column_button = id(new PHUIIconView())
      ->setIcon('fa-pencil')
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $column_menu),
        ));

    return $column_button;
  }

  private function buildTriggerMenu(PhabricatorProjectColumn $column) {
    $viewer = $this->getViewer();
    $trigger = $column->getTrigger();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $column,
      PhabricatorPolicyCapability::CAN_EDIT);

    $trigger_items = array();
    if (!$trigger) {
      $set_uri = $this->getApplicationURI(
        new PhutilURI(
          'trigger/edit/',
          array(
            'columnPHID' => $column->getPHID(),
          )));

      $trigger_items[] = id(new PhabricatorActionView())
        ->setIcon('fa-cogs')
        ->setName(pht('New Trigger...'))
        ->setHref($set_uri)
        ->setDisabled(!$can_edit);
    } else {
      $trigger_items[] = id(new PhabricatorActionView())
        ->setIcon('fa-cogs')
        ->setName(pht('View Trigger'))
        ->setHref($trigger->getURI())
        ->setDisabled(!$can_edit);
    }

    $remove_uri = $this->getApplicationURI(
      new PhutilURI(
        urisprintf(
          'column/remove/%d/',
          $column->getID())));

    $trigger_items[] = id(new PhabricatorActionView())
      ->setIcon('fa-times')
      ->setName(pht('Remove Trigger'))
      ->setHref($remove_uri)
      ->setWorkflow(true)
      ->setDisabled(!$can_edit || !$trigger);

    $trigger_menu = id(new PhabricatorActionListView())
      ->setUser($viewer);
    foreach ($trigger_items as $item) {
      $trigger_menu->addAction($item);
    }

    if ($trigger) {
      $trigger_icon = 'fa-cogs';
    } else {
      $trigger_icon = 'fa-cogs grey';
    }

    $trigger_button = id(new PHUIIconView())
      ->setIcon($trigger_icon)
      ->setHref('#')
      ->addSigil('boards-dropdown-menu')
      ->addSigil('trigger-preview')
      ->setMetadata(
        array(
          'items' => hsprintf('%s', $trigger_menu),
          'columnPHID' => $column->getPHID(),
        ));

    return $trigger_button;
  }

  /**
   * Add current state parameters (like order and the visibility of hidden
   * columns) to a URI.
   *
   * This allows actions which toggle or adjust one piece of state to keep
   * the rest of the board state persistent. If no URI is provided, this method
   * starts with the request URI.
   *
   * @param string|null URI to add state parameters to.
   * @param bool True to explicitly include all state.
   * @return PhutilURI URI with state parameters.
   */
  private function getURIWithState($base = null, $force = false) {
    $project = $this->getProject();

    if ($base === null) {
      $base = $this->getRequest()->getPath();
    }

    $base = new PhutilURI($base);

    if ($force || ($this->sortKey != $this->getDefaultSort($project))) {
      if ($this->sortKey !== null) {
        $base->replaceQueryParam('order', $this->sortKey);
      } else {
        $base->removeQueryParam('order');
      }
    } else {
      $base->removeQueryParam('order');
    }

    if ($force || ($this->queryKey != $this->getDefaultFilter($project))) {
      if ($this->queryKey !== null) {
        $base->replaceQueryParam('filter', $this->queryKey);
      } else {
        $base->removeQueryParam('filter');
      }
    } else {
      $base->removeQueryParam('filter');
    }

    if ($this->showHidden) {
      $base->replaceQueryParam('hidden', 'true');
    } else {
      $base->removeQueryParam('hidden');
    }

    return $base;
  }

  private function buildInitializeContent(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $type = $request->getStr('initialize-type');

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");
    $board_uri = $this->getApplicationURI("board/{$id}/");
    $import_uri = $this->getApplicationURI("board/{$id}/import/");

    $set_default = $request->getBool('default');
    if ($set_default) {
      $this
        ->getProfileMenuEngine()
        ->adjustDefault(PhabricatorProject::ITEM_WORKBOARD);
    }

    if ($request->isFormPost()) {
      if ($type == 'backlog-only') {
        $column = PhabricatorProjectColumn::initializeNewColumn($viewer)
          ->setSequence(0)
          ->setProperty('isDefault', true)
          ->setProjectPHID($project->getPHID())
          ->save();

          $xactions = array();
          $xactions[] = id(new PhabricatorProjectTransaction())
            ->setTransactionType(
                PhabricatorProjectWorkboardTransaction::TRANSACTIONTYPE)
            ->setNewValue(1);

          id(new PhabricatorProjectTransactionEditor())
            ->setActor($viewer)
            ->setContentSourceFromRequest($request)
            ->setContinueOnNoEffect(true)
            ->setContinueOnMissingFields(true)
            ->applyTransactions($project, $xactions);

        return id(new AphrontRedirectResponse())
          ->setURI($board_uri);
      } else {
        return id(new AphrontRedirectResponse())
          ->setURI($import_uri);
      }
    }

    // TODO: Tailor this UI if the project is already a parent project. We
    // should not offer options for creating a parent project workboard, since
    // they can't have their own columns.

    $new_selector = id(new AphrontFormRadioButtonControl())
      ->setLabel(pht('Columns'))
      ->setName('initialize-type')
      ->setValue('backlog-only')
      ->addButton(
        'backlog-only',
        pht('New Empty Board'),
        pht('Create a new board with just a backlog column.'))
      ->addButton(
        'import',
        pht('Import Columns'),
        pht('Import board columns from another project.'));

    $default_checkbox = id(new AphrontFormCheckboxControl())
      ->setLabel(pht('Make Default'))
      ->addCheckbox(
        'default',
        1,
        pht('Make the workboard the default view for this project.'),
        true);

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->addHiddenInput('initialize', 1)
      ->appendRemarkupInstructions(
        pht('The workboard for this project has not been created yet.'))
      ->appendControl($new_selector)
      ->appendControl($default_checkbox)
      ->appendControl(
        id(new AphrontFormSubmitControl())
          ->addCancelButton($profile_uri)
          ->setValue(pht('Create Workboard')));

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Create Workboard'))
      ->setForm($form);

    return $box;
  }

  private function buildNoAccessContent(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");

    return $this->newDialog()
      ->setTitle(pht('Unable to Create Workboard'))
      ->appendParagraph(
        pht(
          'The workboard for this project has not been created yet, '.
          'but you do not have permission to create it. Only users '.
          'who can edit this project can create a workboard for it.'))
      ->addCancelButton($profile_uri);
  }


  private function buildEnableContent(PhabricatorProject $project) {
    $request = $this->getRequest();
    $viewer = $this->getViewer();

    $id = $project->getID();
    $profile_uri = $this->getApplicationURI("profile/{$id}/");
    $board_uri = $this->getApplicationURI("board/{$id}/");

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhabricatorProjectTransaction())
        ->setTransactionType(
            PhabricatorProjectWorkboardTransaction::TRANSACTIONTYPE)
        ->setNewValue(1);

      id(new PhabricatorProjectTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($project, $xactions);

      return id(new AphrontRedirectResponse())
        ->setURI($board_uri);
    }

    return $this->newDialog()
      ->setTitle(pht('Workboard Disabled'))
      ->addHiddenInput('initialize', 1)
      ->appendParagraph(
        pht(
          'This workboard has been disabled, but can be restored to its '.
          'former glory.'))
      ->addCancelButton($profile_uri)
      ->addSubmitButton(pht('Enable Workboard'));
  }

  private function buildDisabledContent(PhabricatorProject $project) {
    $viewer = $this->getViewer();

    $id = $project->getID();

    $profile_uri = $this->getApplicationURI("profile/{$id}/");

    return $this->newDialog()
      ->setTitle(pht('Workboard Disabled'))
      ->appendParagraph(
        pht(
          'This workboard has been disabled, and you do not have permission '.
          'to enable it. Only users who can edit this project can restore '.
          'the workboard.'))
      ->addCancelButton($profile_uri);
  }

}
