<?php

/**
 * @file pages/workflow/WorkflowHandler.inc.php
 *
 * Copyright (c) 2003-2013 John Willinsky
 * Distributed under the GNU GPL v2. For full terms see the file docs/COPYING.
 *
 * @class WorkflowHandler
 * @ingroup pages_reviewer
 *
 * @brief Handle requests for the submssion workflow.
 */

import('lib.pkp.pages.workflow.PKPWorkflowHandler');

// Access decision actions constants.
import('classes.workflow.EditorDecisionActionsManager');

class WorkflowHandler extends PKPWorkflowHandler {
	/**
	 * Constructor
	 */
	function WorkflowHandler() {
		parent::PKPWorkflowHandler();

		$this->addRoleAssignment(
			array(ROLE_ID_SUB_EDITOR, ROLE_ID_MANAGER, ROLE_ID_ASSISTANT),
			array(
				'access', 'submission',
				'editorDecisionActions', // Submission & review
				'internalReview', // Internal review
				'externalReview', // External review
				'editorial',
				'production', 'productionFormatsTab', // Production
				'submissionProgressBar',
				'expedite'
			)
		);
	}


	//
	// Public handler methods
	//
	/**
	 * Show the internal review stage.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function internalReview($args, $request) {
		// Use different ops so we can identify stage by op.
		$templateMgr = TemplateManager::getManager($request);
		$templateMgr->assign('reviewRoundOp', 'internalReviewRound');
		return $this->_review($args, $request);
	}

	/**
	 * Show the production stage
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function production(&$args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$monograph =& $this->getAuthorizedContextObject(ASSOC_TYPE_MONOGRAPH);
		$notificationRequestOptions = array(
			NOTIFICATION_LEVEL_NORMAL => array(
				NOTIFICATION_TYPE_VISIT_CATALOG => array(ASSOC_TYPE_MONOGRAPH, $monograph->getId()),
				NOTIFICATION_TYPE_APPROVE_SUBMISSION => array(ASSOC_TYPE_MONOGRAPH, $monograph->getId()),
			),
			NOTIFICATION_LEVEL_TRIVIAL => array()
		);

		$publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publicationFormats = $publicationFormatDao->getBySubmissionId($submission->getId());
		$templateMgr->assign('publicationFormats', $publicationFormats->toAssociativeArray());

		$templateMgr->assign('productionNotificationRequestOptions', $notificationRequestOptions);
		$templateMgr->display('workflow/production.tpl');
	}

	/**
	 * Show the production stage accordion contents
	 * @param $request PKPRequest
	 * @param $args array
	 */
	function productionFormatsTab(&$args, $request) {
		$templateMgr = TemplateManager::getManager($request);
		$publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		$publicationFormats = $publicationFormatDao->getBySubmissionId($submission->getId());
		$templateMgr->assign('submission', $submission);
		$templateMgr->assign('publicationFormats', $publicationFormats->toAssociativeArray());
		$templateMgr->assign('currentFormatTabId', (int) $request->getUserVar('currentFormatTabId'));

		return $templateMgr->fetchJson('workflow/productionFormatsTab.tpl');
	}

	/**
	 * Fetch the JSON-encoded submission progress bar.
	 * @param $args array
	 * @param $request Request
	 */
	function submissionProgressBar($args, $request) {
		// Assign the actions to the template.
		$templateMgr = TemplateManager::getManager($request);
		$press = $request->getPress();

		$userGroupDao = DAORegistry::getDAO('UserGroupDAO');
		$workflowStages = $userGroupDao->getWorkflowStageKeysAndPaths();
		$stageNotifications = array();
		foreach (array_keys($workflowStages) as $stageId) {
			$stageNotifications[$stageId] = $this->_notificationOptionsByStage($request->getUser(), $stageId, $press->getId());
		}

		$templateMgr->assign('stageNotifications', $stageNotifications);

		$monograph = $this->getAuthorizedContextObject(ASSOC_TYPE_MONOGRAPH);
		$publishedMonographDao = DAORegistry::getDAO('PublishedMonographDAO');
		$publishedMonograph = $publishedMonographDao->getById($monograph->getId());
		if ($publishedMonograph) { // first check, there's a published monograph
			$publicationFormats = $publishedMonograph->getPublicationFormats(true);
			$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
			import('classes.monograph.MonographFile'); // constants

			foreach ($publicationFormats as $format) { // there is at least one publication format.
				if ($format->getIsApproved()) { // it's ready to be included in the catalog

					$monographFiles = $submissionFileDao->getLatestRevisionsByAssocId(
							ASSOC_TYPE_PUBLICATION_FORMAT, $format->getId(),
							$publishedMonograph->getId()
					);

					foreach ($monographFiles as $file) {
						if ($file->getViewable() && !is_null($file->getDirectSalesPrice())) { // at least one file has a price set.
							$templateMgr->assign('submissionIsReady', true);
						}
					}
				}
			}
		}
		return $templateMgr->fetchJson('workflow/submissionProgressBar.tpl');
	}

	/**
	 * Expedites a submission through the submission process, if the submitter is a manager or editor.
	 * @param $args array
	 * @param $request PKPRequest
	 */
	function expedite($args, $request) {

		$submission = $this->getAuthorizedContextObject(ASSOC_TYPE_SUBMISSION);
		import('controllers.modals.submissionMetadata.form.CatalogEntrySubmissionReviewForm');
		$user = $request->getUser();
		$form = new CatalogEntrySubmissionReviewForm($submission->getId(), null, array('expeditedSubmission' => true));
		if ($submission && $request->getUserVar('confirm') != '') {

			// Process our submitted form in order to create the catalog entry.
			$form->readInputData();
			if($form->validate()) {
				$form->execute($request);
				// Create trivial notification in place on the form.
				$notificationManager = new NotificationManager();
				$user = $request->getUser();
				$notificationManager->createTrivialNotification($user->getId(), NOTIFICATION_TYPE_SUCCESS, array('contents' => __('notification.savedSubmissionMetadata')));

				// Now, create a publication format for this submission.  Assume PDF, digital, and set to 'available'.
				$publicationFormatDao = DAORegistry::getDAO('PublicationFormatDAO');
				$publicationFormat = $publicationFormatDao->newDataObject();
				$publicationFormat->setPhysicalFormat(false);
				$publicationFormat->setIsApproved(true);
				$publicationFormat->setIsAvailable(true);
				$publicationFormat->setSubmissionId($submission->getId());
				$publicationFormat->setProductAvailabilityCode('20'); // ONIX code for Available.
				$publicationFormat->setEntryKey('DA'); // ONIX code for Digital
				$publicationFormat->setData('name', 'PDF', $submission->getLocale());
				$publicationFormat->setSeq(REALLY_BIG_NUMBER);
				$publicationFormatId = $publicationFormatDao->insertObject($publicationFormat);

				// Next, create a galley PROOF file out of the submission file uploaded.
				$submissionFileDao = DAORegistry::getDAO('SubmissionFileDAO');
				import('lib.pkp.classes.submission.SubmissionFile'); // constants.
				$submissionFiles = $submissionFileDao->getLatestRevisions($submission->getId(), SUBMISSION_FILE_SUBMISSION);
				// Assume a single file was uploaded, but check for something that's PDF anyway.
				foreach ($submissionFiles as $submissionFile) {
					// test both mime type and file extension in case the mime type isn't correct after uploading.
					if ($submissionFile->getFileType() == 'application/pdf' || preg_match('/\.pdf$/', $submissionFile->getOriginalFileName())) {

						// Get the path of the current file because we change the file stage in a bit.
						$currentFilePath = $submissionFile->getFilePath();

						// this will be a new file based on the old one.
						$submissionFile->setFileId(null);
						$submissionFile->setRevision(1);
						$submissionFile->setViewable(true);
						$submissionFile->setFileStage(SUBMISSION_FILE_PROOF);
						$submissionFile->setAssocType(ASSOC_TYPE_REPRESENTATION);
						$submissionFile->setAssocId($publicationFormatId);

						// Assign the sales type and price for the submission file.
						switch ($request->getUserVar('salesType')) {
							case 'notAvailable':
								$submissionFile->setDirectSalesPrice(null);
								$submissionFile->setSalesType('notAvailable');
								break;
							case 'openAccess':
								$submissionFile->setDirectSalesPrice(0);
								$submissionFile->setSalesType('openAccess');
								break;
							default:
								$submissionFile->setDirectSalesPrice($request->getUserVar('price'));
								$submissionFile->setSalesType('directSales');
						}

						$submissionFileDao->insertObject($submissionFile, $currentFilePath);
						break;
					}
				}

				// no errors, close the modal.
				$json = new JSONMessage(true);
				return $json->getString();
			} else {
				$json = new JSONMessage(true, $form->fetch($request));
				return $json->getString();
			}
		} else {
			$json = new JSONMessage(true, $form->fetch($request));
			return $json->getString();
		}
	}

	/**
	 * Determine if a particular stage has a notification pending.  If so, return true.
	 * This is used to set the CSS class of the submission progress bar.
	 * @param $user PKPUser
	 * @param $stageId int
	 * @param $contextId int
	 */
	function _notificationOptionsByStage($user, $stageId, $contextId) {

		$monograph =& $this->getAuthorizedContextObject(ASSOC_TYPE_MONOGRAPH);
		$notificationDao = DAORegistry::getDAO('NotificationDAO');

		$signOffNotificationType = $this->_getSignoffNotificationTypeByStageId($stageId);
		$editorAssignmentNotificationType = $this->_getEditorAssignmentNotificationTypeByStageId($stageId);

		$editorAssignments =& $notificationDao->getByAssoc(ASSOC_TYPE_MONOGRAPH, $monograph->getId(), null, $editorAssignmentNotificationType, $contextId);
		if (isset($signOffNotificationType)) {
			$signoffAssignments =& $notificationDao->getByAssoc(ASSOC_TYPE_MONOGRAPH, $monograph->getId(), $user->getId(), $signOffNotificationType, $contextId);
		}

		// if the User has assigned TASKs in this stage check, return true
		if (!$editorAssignments->wasEmpty() || (isset($signoffAssignments) && !$signoffAssignments->wasEmpty())) {
			return true;
		}

		// check for more specific notifications on those stages that have them.
		if ($stageId == WORKFLOW_STAGE_ID_PRODUCTION) {
			$submissionApprovalNotification =& $notificationDao->getByAssoc(ASSOC_TYPE_MONOGRAPH, $monograph->getId(), null, NOTIFICATION_TYPE_APPROVE_SUBMISSION, $contextId);
			if (!$submissionApprovalNotification->wasEmpty()) {
				return true;
			}
		}

		if ($stageId == WORKFLOW_STAGE_ID_INTERNAL_REVIEW || $stageId == WORKFLOW_STAGE_ID_EXTERNAL_REVIEW) {
			$reviewRoundDao = DAORegistry::getDAO('ReviewRoundDAO');
			$reviewRounds =& $reviewRoundDao->getBySubmissionId($monograph->getId(), $stageId);
			$notificationTypes = array(NOTIFICATION_TYPE_REVIEW_ROUND_STATUS, NOTIFICATION_TYPE_ALL_REVIEWS_IN);
			while ($reviewRound = $reviewRounds->next()) {
				foreach ($notificationTypes as $type) {
					$notifications = $notificationDao->getByAssoc(ASSOC_TYPE_REVIEW_ROUND, $reviewRound->getId(), null, $type, $contextId);
					if (!$notifications->wasEmpty()) {
						return true;
					}
				}
			}
		}

		return false;
	}

	//
	// Protected helper methods
	//
	/**
	 * Return the editor assignment notification type based on stage id.
	 * @param $stageId int
	 * @return int
	 */
	protected function _getEditorAssignmentNotificationTypeByStageId($stageId) {
		switch ($stageId) {
			case WORKFLOW_STAGE_ID_SUBMISSION:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_SUBMISSION;
			case WORKFLOW_STAGE_ID_INTERNAL_REVIEW:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_INTERNAL_REVIEW;
			case WORKFLOW_STAGE_ID_EXTERNAL_REVIEW:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EXTERNAL_REVIEW;
			case WORKFLOW_STAGE_ID_EDITING:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_EDITING;
			case WORKFLOW_STAGE_ID_PRODUCTION:
				return NOTIFICATION_TYPE_EDITOR_ASSIGNMENT_PRODUCTION;
		}
		return null;
	}
}

?>
