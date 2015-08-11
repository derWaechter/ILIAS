<?php
/**
 * @author		Björn Heyser <bheyser@databay.de>
 * @version		$Id$
 *
 * @package     Modules/TestQuestionPool
 *
 * @ilCtrl_Calls assLongMenuGUI: ilPropertyFormGUI
 */

require_once './Modules/TestQuestionPool/classes/class.assQuestionGUI.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilGuiQuestionScoringAdjustable.php';
include_once './Modules/Test/classes/inc.AssessmentConstants.php';


class assLongMenuGUI extends assQuestionGUI implements ilGuiQuestionScoringAdjustable
{
	private $rbacsystem, $ilTabs;
	public $lng;

	function __construct($id = -1)
	{
		parent::__construct();
		include_once './Modules/TestQuestionPool/classes/class.assLongMenu.php';
		$this->object = new assLongMenu();
		if ($id >= 0)
		{
			$this->object->loadFromDb($id);
		}
		global $rbacsystem, $ilTabs, $lng;
		$this->rbacsystem 	= $rbacsystem;
		$this->ilTabs		= $ilTabs;
		$this->lng			= $lng;
	}

	/**
	 * @param $active_id
	 * @param $pass
	 * @return array
	 */
	protected function getUserSolution($active_id, $pass)
	{
		$user_solution = array();
		if($active_id)
		{
			$solutions = NULL;
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			if(!ilObjTest::_getUsePreviousAnswers($active_id, true))
			{
				if(is_null($pass)) $pass = ilObjTest::_getPass($active_id);
			}
			$solutions =& $this->object->getSolutionValues($active_id, $pass);
			foreach($solutions as $idx => $solution_value)
			{
				$user_solution[$solution_value["value1"]] = $solution_value["value2"];
			}
			return $user_solution;
		}
		return $user_solution;
	}

	function getCommand($cmd)
	{
		return $cmd;
	}

	/**
	 * Evaluates a posted edit form and writes the form data in the question object
	 *
	 * @param bool $always
	 *
	 * @return integer A positive value, if one of the required fields wasn't set, else 0
	 *
	 */
	public function writePostData($always = false)
	{
		$form = $this->buildEditForm();
		$form->setValuesByPost();
		$custom_check = $this->object->checkQuestionCustomPart();
		if( !$form->checkInput() ||  !$custom_check)
		{
			if(!$custom_check)
			{
				ilUtil::sendFailure($this->lng->txt("form_input_not_valid"));
			}
			$this->editQuestion($form);
			return 1;
		}
		$this->writeQuestionGenericPostData();
		$this->writeQuestionSpecificPostData($form);
		$this->saveTaxonomyAssignments();
		return 0;
	}

	public function writeQuestionSpecificPostData(ilPropertyFormGUI $form)
	{
			$longmenu_text = ilUtil::stripSlashesRecursive($_POST['longmenu_text']);
			$_POST['longmenu_text'] = $longmenu_text;
			//Todo change question to question_text after merge
			$this->object->setQuestion($_POST['question']);
			$this->object->setLongMenuTextValue($_POST["longmenu_text"]);
			$this->saveTaxonomyAssignments();
	}

	protected function editQuestion(ilPropertyFormGUI $form = null)
	{
		if( $form === null )
		{
			$form = $this->buildEditForm();
		}

		$this->getQuestionTemplate();
		$this->tpl->addCss('Modules/Test/templates/default/ta.css');

		$this->tpl->setVariable("QUESTION_DATA", $this->ctrl->getHTML($form));
	}
	/**
	 * @return ilPropertyFormGUI
	 */
	private function buildEditForm()
	{
		$form = $this->buildBasicEditFormObject();

		$this->addQuestionFormCommandButtons($form);

		$this->addBasicQuestionFormProperties($form);

		$this->populateQuestionSpecificFormPart($form);
		$this->populateAnswerSpecificFormPart($form);

		$this->populateTaxonomyFormSection($form);

		return $form;
	}
	/**
	 * @param ilPropertyFormGUI $form
	 * @return ilPropertyFormGUI
	 */
	public function populateQuestionSpecificFormPart(ilPropertyFormGUI $form)
	{
		$long_menu_text = new ilTextAreaInputGUI($this->lng->txt("longmenu_text"), 'longmenu_text');
		$long_menu_text->setRequired(true);
		$long_menu_text->setInfo($this->lng->txt("longmenu_hint"));
		$long_menu_text->setRows( 10 );
		$long_menu_text->setCols( 80 );
		if (!$this->object->getSelfAssessmentEditingMode())
		{
			if( $this->object->getAdditionalContentEditingMode() == assQuestion::ADDITIONAL_CONTENT_EDITING_MODE_DEFAULT )
			{
				include_once "./Services/AdvancedEditing/classes/class.ilObjAdvancedEditing.php";
				$long_menu_text->setRteTags(ilObjAdvancedEditing::_getUsedHTMLTags("assessment"));
				$long_menu_text->addPlugin("latex");
				$long_menu_text->addButton("latex");
				$long_menu_text->addButton("pastelatex");
				$long_menu_text->setRTESupport($this->object->getId(), "qpl", "assessment");
			}
		}
		else
		{
			$long_menu_text->setRteTags(self::getSelfAssessmentTags());
			$long_menu_text->setUseTagsForRteOnly(false);
		}
		$long_menu_text->setUseRte(TRUE);
		$long_menu_text->setRTESupport($this->object->getId(), "qpl", "assessment");
		$long_menu_text->setValue($this->object->prepareTextareaOutput($this->object->getLongMenuTextValue()));
		$form->addItem($long_menu_text);
		
		$tpl = new ilTemplate("tpl.il_as_qpl_cloze_gap_button_code.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$tpl->setVariable('INSERT_GAP', $this->lng->txt('insert_gap'));
		$tpl->parseCurrentBlock();
		$button = new ilCustomInputGUI('&nbsp;','');
		$button->setHtml($tpl->get());
		$form->addItem($button);

		require_once("./Services/UIComponent/Modal/classes/class.ilModalGUI.php");
		$modal = ilModalGUI::getInstance();
		$modal->setHeading('');
		$modal->setId("ilGapModal");
		//$modal->setBackdrop(ilModalGUI::BACKDROP_OFF);
		$modal->setBody('');
		
		$hidden = new ilHiddenInputGUI('hidden_text_files');
		$form->addItem($hidden);

		$hidden2 = new ilHiddenInputGUI('hidden_correct_answers');
		$form->addItem($hidden2);
		
		$tpl = new ilTemplate("tpl.il_as_qpl_long_menu_gap.html", TRUE, TRUE, "Modules/TestQuestionPool");
		$tpl->setVariable('CORRECT_ANSWERS', 	$this->object->getJsonStructure());
		$tpl->setVariable('ALL_ANSWERS', 		$this->object->getAnswersObject());
		$tpl->setVariable('GAP_PLACEHOLDER', 	assLongMenu::GAP_PLACEHOLDER);
		$tpl->setVariable('SELECT_BOX', 		$this->lng->txt('insert_gap'));
		$tpl->setVariable("SELECT", 			$this->lng->txt('select'));
		$tpl->setVariable("TEXT", 				$this->lng->txt('text'));
		$tpl->setVariable("POINTS", 			$this->lng->txt('points'));
		$tpl->setVariable("INFO_TEXT_UPLOAD",	$this->lng->txt('INFO_TEXT_UPLOAD'));
		$tpl->setVariable("INFO_TEXT_GAP", 		$this->lng->txt('INFO_TEXT_GAP'));
		$tpl->setVariable("MANUAL_EDITING", 	$this->lng->txt('MANUAL_EDITING'));
		$tpl->setVariable("MY_MODAL", 			$modal->getHTML());
		$tpl->parseCurrentBlock();
		$button = new ilCustomInputGUI('&nbsp;','');
		$button->setHtml($tpl->get());
		$form->addItem($button);
		return $form;
	}
	
	/**
	 * @param ilPropertyFormGUI $form
	 * @return ilPropertyFormGUI
	 */
	public function populateAnswerSpecificFormPart(ilPropertyFormGUI $form)
	{
		return $form;
	}

	/**
	 * Get the question solution output
	 *
	 * @param integer $active_id The active user id
	 * @param integer $pass The test pass
	 * @param boolean $graphicalOutput Show visual feedback for right/wrong answers
	 * @param boolean $result_output Show the reached points for parts of the question
	 * @param boolean $show_question_only Show the question without the ILIAS content around
	 * @param boolean $show_feedback Show the question feedback
	 * @param boolean $show_correct_solution Show the correct solution instead of the user solution
	 * @param boolean $show_manual_scoring Show specific information for the manual scoring output
	 * @return The solution output of the question as HTML code
	 */
	function getSolutionOutput(
		$active_id,
		$pass = NULL,
		$graphicalOutput = FALSE,
		$result_output = FALSE,
		$show_question_only = TRUE,
		$show_feedback = FALSE,
		$show_correct_solution = FALSE,
		$show_manual_scoring = FALSE,
		$show_question_text = TRUE
	)
	{
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_lome_question_output_solution.html", TRUE, TRUE, "Modules/TestQuestionPool");
		
		if($show_question_text)
		{
			$question_text = $this->object->getQuestion();
			$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($question_text, TRUE));
		}
		if (($active_id > 0) && (!$show_correct_solution))
		{
			$correct_solution 	= $this->getUserSolution($active_id, $pass);
		}
		else
		{
			$correct_solution = $this->object->getCorrectAnswersForQuestionSolution($this->object->getId());
		}
		$template->setVariable('LONGMENU_TEXT_SOLUTION', $this->getLongMenuTextWithInputFieldsInsteadOfGaps($correct_solution, true));
		$solution_template = new ilTemplate("tpl.il_as_tst_solution_output.html",TRUE, TRUE, "Modules/TestQuestionPool");
		$question_output = $template->get();
		$feedback = '';
		if($show_feedback)
		{
			$fb = $this->getGenericFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';

			$fb = $this->getSpecificFeedbackOutput($active_id, $pass);
			$feedback .=  strlen($fb) ? $fb : '';
		}
		if (strlen($feedback)) $solution_template->setVariable("FEEDBACK", $feedback);

		$solution_template->setVariable("SOLUTION_OUTPUT", $question_output);

		$solution_output = $solution_template->get();

		if (!$show_question_only)
		{
			$solution_output = $this->getILIASPage($solution_output);
		}

		return $solution_output;
	}
	
	function getPreview($show_question_only = FALSE, $showInlineFeedback = false)
	{
		$user_solution = is_object($this->getPreviewSession()) ? (array)$this->getPreviewSession()->getParticipantsSolution() : array();
		$user_solution = array_values($user_solution);
		
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_longmenu_output.html", TRUE, TRUE, "Modules/TestQuestionPool");

		$question_text = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($question_text, TRUE));
		$template->setVariable("ANSWER_OPTIONS_JSON", json_encode($this->object->getAvailableAnswerOptions()));
		$template->setVariable('LONGMENU_TEXT', $this->getLongMenuTextWithInputFieldsInsteadOfGaps($user_solution));

		$question_output = $template->get();
		if (!$show_question_only)
		{
			$question_output = $this->getILIASPage($question_output);
		}
		return $question_output;
	}

	private function getLongMenuTextWithInputFieldsInsteadOfGaps($user_solution = array(), $solution = false)
	{
		if($solution)
		{
			$options = 'disabled';
		}
		else
		{
			$options = 'class="long_menu_input"  name="answer[${1}]"';
		}

		$return_value =  preg_replace("/\\[".assLongMenu::GAP_PLACEHOLDER." (\\d+)\\]/",
			'<input ' . $options . ' value="###${1}###">',
			$this->object->getLongMenuTextValue(), -1, $count);
		
		for($i = 0; $i <= $count; $i++)
		{
			$real_key = $i + 1;
			$value = '';
			if(array_key_exists($i,$user_solution))
			{
				$value = $user_solution[$i];
			}
			$return_value = preg_replace("/###". $real_key ."###/", $value , $return_value);
		}
		return $return_value;
	}
	
	function getTestOutput($active_id,
						   $pass = NULL,
						   $is_postponed = FALSE,
						   $use_post_solutions = FALSE,
						   $showInlineFeedback = FALSE
	)
	{
		//Todo: implement $use_post_solutions && $showInlineFeedback
		$user_solution = $this->getUserSolution($active_id, $pass);

		// generate the question output
		include_once "./Services/UICore/classes/class.ilTemplate.php";
		$template = new ilTemplate("tpl.il_as_qpl_longmenu_output.html", TRUE, TRUE, "Modules/TestQuestionPool");
		
		$question_text = $this->object->getQuestion();
		$template->setVariable("QUESTIONTEXT", $this->object->prepareTextareaOutput($question_text, TRUE));
		$template->setVariable("ANSWER_OPTIONS_JSON", json_encode($this->object->getAvailableAnswerOptions()));
		$template->setVariable('LONGMENU_TEXT', $this->getLongMenuTextWithInputFieldsInsteadOfGaps($user_solution));
		if( $showInlineFeedback )
		{
			//Todo: fix this
			$this->populateSpecificFeedbackInline($user_solution, $answer_id, $template);
		}
		$question_output = $template->get();
		$page_output = $this->outQuestionPage("", $is_postponed, $active_id, $question_output);
		return $pageoutput;
	}

	/**
	 * Sets the ILIAS tabs for this question type
	 *
	 * @access public
	 *
	 */
	function setQuestionTabs()
	{
		$this->ilTabs->clearTargets();

		$this->ctrl->setParameterByClass("ilAssQuestionPageGUI", "q_id", $_GET["q_id"]);
		include_once "./Modules/TestQuestionPool/classes/class.assQuestion.php";
		$q_type = $this->object->getQuestionType();

		if (strlen($q_type))
		{
			$classname = $q_type . "GUI";
			$this->ctrl->setParameterByClass(strtolower($classname), "sel_question_types", $q_type);
			$this->ctrl->setParameterByClass(strtolower($classname), "q_id", $_GET["q_id"]);
		}

		if ($_GET["q_id"])
		{
			if ($this->rbacsystem->checkAccess('write', $_GET["ref_id"]))
			{
				// edit page
				$this->ilTabs->addTarget("edit_page",
					$this->ctrl->getLinkTargetByClass("ilAssQuestionPageGUI", "edit"),
					array("edit", "insert", "exec_pg"),
					"", "", $force_active);
			}

			$this->addTab_QuestionPreview($this->ilTabs);
		}

		$force_active = false;
		if ($this->rbacsystem->checkAccess('write', $_GET["ref_id"]))
		{
			$url = "";
			if ($classname) $url = $this->ctrl->getLinkTargetByClass($classname, "editQuestion");
			$commands = $_POST["cmd"];
			if (is_array($commands))
			{
				foreach ($commands as $key => $value)
				{
					if (preg_match("/^delete_.*/", $key, $matches))
					{
						$force_active = true;
					}
				}
			}
			// edit question properties
			$this->ilTabs->addTarget("edit_question",
				$url,
				array("editQuestion", "save", "saveEdit", "addkvp", "removekvp", "originalSyncForm"),
				$classname, "", $force_active);
		}

		// add tab for question feedback within common class assQuestionGUI
		$this->addTab_QuestionFeedback($this->ilTabs);

		// add tab for question hint within common class assQuestionGUI
		$this->addTab_QuestionHints($this->ilTabs);

		// add tab for question's suggested solution within common class assQuestionGUI
		$this->addTab_SuggestedSolution($this->ilTabs, $classname);

		// Assessment of questions sub menu entry
		if ($_GET["q_id"])
		{
			$this->ilTabs->addTarget("statistics",
				$this->ctrl->getLinkTargetByClass($classname, "assessment"),
				array("assessment"),
				$classname, "");
		}

		$this->addBackTab($this->ilTabs);
	}

	function getSpecificFeedbackOutput($active_id, $pass)
	{
		$output = "";
		return $this->object->prepareTextareaOutput($output, TRUE);
	}

	private function populateSpecificFeedbackInline($user_solution, $answer_id, $template)
	{
		require_once 'Modules/TestQuestionPool/classes/feedback/class.ilAssConfigurableMultiOptionQuestionFeedback.php';

		if($this->object->getSpecificFeedbackSetting() == ilAssConfigurableMultiOptionQuestionFeedback::FEEDBACK_SETTING_CHECKED)
		{
			foreach($user_solution as $mc_solution)
			{
				if(strcmp($mc_solution, $answer_id) == 0)
				{
					$fb = $this->object->feedbackOBJ->getSpecificAnswerFeedbackTestPresentation($this->object->getId(), $answer_id);
					if(strlen($fb))
					{
						$template->setCurrentBlock("feedback");
						$template->setVariable("FEEDBACK", $this->object->prepareTextareaOutput($fb, true));
						$template->parseCurrentBlock();
					}
				}
			}
		}

		if($this->object->getSpecificFeedbackSetting() == ilAssConfigurableMultiOptionQuestionFeedback::FEEDBACK_SETTING_ALL)
		{
			$fb = $this->object->feedbackOBJ->getSpecificAnswerFeedbackTestPresentation($this->object->getId(), $answer_id);
			if(strlen($fb))
			{
				$template->setCurrentBlock("feedback");
				$template->setVariable("FEEDBACK", $this->object->prepareTextareaOutput($fb, true));
				$template->parseCurrentBlock();
			}
		}

		if($this->object->getSpecificFeedbackSetting() == ilAssConfigurableMultiOptionQuestionFeedback::FEEDBACK_SETTING_CORRECT)
		{
			$answer = $this->object->getAnswer($answer_id);

			if($answer->getPoints() > 0)
			{
				$fb = $this->object->feedbackOBJ->getSpecificAnswerFeedbackTestPresentation($this->object->getId(), $answer_id);
				if(strlen($fb))
				{
					$template->setCurrentBlock("feedback");
					$template->setVariable("FEEDBACK", $this->object->prepareTextareaOutput($fb, true));
					$template->parseCurrentBlock();
				}
			}
		}
	}
	/**
	 * Returns a list of postvars which will be suppressed in the form output when used in scoring adjustment.
	 * The form elements will be shown disabled, so the users see the usual form but can only edit the settings, which
	 * make sense in the given context.
	 *
	 * E.g. array('cloze_type', 'image_filename')
	 *
	 * @return string[]
	 */
	public function getAfterParticipationSuppressionQuestionPostVars()
	{
		return array();
	}

	/**
	 * Returns an html string containing a question specific representation of the answers so far
	 * given in the test for use in the right column in the scoring adjustment user interface.
	 *
	 * @param array $relevant_answers
	 *
	 * @return string
	 */
	public function getAggregatedAnswersView($relevant_answers)
	{
		// Empty implementation here since a feasible way to aggregate answer is not known.
		return ''; //print_r($relevant_answers,true);
	}
}