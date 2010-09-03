<?php

/**
 * @defgroup author_form_submit
 */

/**
 * @file classes/author/form/submit/SubmissionSubmitForm.inc.php
 *
 * Copyright (c) 2003-2010 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class SubmissionSubmitForm
 * @ingroup author_form_submit
 *
 * @brief Base class for author submit forms.
 */


import('lib.pkp.classes.form.Form');

class SubmissionSubmitForm extends Form {

	/** @var int the ID of the monograph */
	var $monographId;

	/** @var Monograph current monograph */
	var $monograph;

	/** @var int the current step */
	var $step;

	/**
	 * Constructor.
	 * @param $monograph object
	 * @param $step int
	 */
	function SubmissionSubmitForm($monograph, $step) {
		parent::Form(sprintf('submission/form/submit/step%d.tpl', $step));
		$this->addCheck(new FormValidatorPost($this));
		$this->step = (int) $step;
		$this->monograph = $monograph;
		$this->monographId = $monograph ? $monograph->getId() : null;
	}

	/**
	 * Display the form.
	 */
	function display() {
		$templateMgr =& TemplateManager::getManager();
		$templateMgr->assign('validateId', 'submit');

		$templateMgr->assign('monographId', $this->monographId);
		$templateMgr->assign('submitStep', $this->step);

		if (isset($this->monograph)) {
			$templateMgr->assign('submissionProgress', $this->monograph->getSubmissionProgress());
		}

		switch($this->step) {
			case 3:
				$helpTopicId = 'submission.indexingAndMetadata';
				break;
			default:
				$helpTopicId = 'submission.index';
		}
		$templateMgr->assign('helpTopicId', $helpTopicId);

		$press =& Request::getPress();
		$settingsDao =& DAORegistry::getDAO('PressSettingsDAO');
		$templateMgr->assign_by_ref('pressSettings', $settingsDao->getPressSettings($press->getId()));

		parent::display();
	}

	/**
	 * Automatically assign Series Editors to new submissions.
	 * @param $monograph object
	 * @return array of series editors
	 */
	function assignEditors(&$monograph) {
		$seriesId = $monograph->getSeriesId();
		$press =& Request::getPress();

		$seriesEditorsDao =& DAORegistry::getDAO('SeriesEditorsDAO');
		$signoffDao =& DAORegistry::getDAO('SignoffDAO');
		$userGroupDao =& DAORegistry::getDAO('UserGroupDAO');
		$seriesEditors =& $seriesEditorsDao->getEditorsBySeriesId($press->getId(), $seriesId);

		$seriesEditorUserGroup = $userGroupDao->getDefaultByRoleId($press->getId(), ROLE_ID_SERIES_EDITOR);
		foreach ($seriesEditors as $seriesEditorEntry) {
			$signoffDao->build('SIGNOFF_STAGE', ASSOC_TYPE_MONOGRAPH, $monograph->getId(), WORKFLOW_STAGE_ID_SUBMISSION, $seriesEditorEntry['user']->getId(), $seriesEditorUserGroup->getId());
		}

		return $seriesEditors;
	}
}

?>