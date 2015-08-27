<?php
/* Copyright (c) 1998-2013 ILIAS open source, Extended GPL, see docs/LICENSE */

require_once './Modules/TestQuestionPool/classes/class.assQuestion.php';
require_once './Modules/Test/classes/inc.AssessmentConstants.php';
require_once './Modules/TestQuestionPool/interfaces/interface.ilObjQuestionScoringAdjustable.php';
require_once './Modules/TestQuestionPool/interfaces/interface.iQuestionCondition.php';
require_once './Modules/TestQuestionPool/classes/class.ilUserQuestionResult.php';


class assLongMenu extends assQuestion implements ilObjQuestionScoringAdjustable
{
	private $answerType, $long_menu_text, $answers, $correct_answers, $json_structure, $ilDB;
	private $specificFeedbackSetting;
	
	const ANSWER_TYPE_SELECT_VAL	= 0;
	const ANSWER_TYPE_TEXT_VAL		= 1;
	const GAP_PLACEHOLDER			= 'Longmenu';
	const MIN_LENGTH_AUTOCOMPLETE 	= 0;
	const MAX_INPUT_FIELDS 			= 500;

	function __construct(
		$title 		= "",
		$comment 	= "",
		$author 	= "",
		$owner 		= -1,
		$question 	= ""
	)
	{
		require_once 'Modules/TestQuestionPool/classes/feedback/class.ilAssConfigurableMultiOptionQuestionFeedback.php';
		$this->specificFeedbackSetting = ilAssConfigurableMultiOptionQuestionFeedback::FEEDBACK_SETTING_ALL;
		parent::__construct($title, $comment, $author, $owner, $question);
		global $ilDB;
		$this->ilDB = $ilDB;
	}
	
	/**
	 * @return mixed
	 */
	public function getAnswerType()
	{
		return $this->answerType;
	}

	/**
	 * @param mixed $answerType
	 */
	public function setAnswerType($answerType)
	{
		$this->answerType = $answerType;
	}
	
	/**
	 * @return mixed
	 */
	public function getCorrectAnswers()
	{
		return $this->correct_answers;
	}


	public function setCorrectAnswers($correct_answers)
	{
		$this->correct_answers = $correct_answers;
	}

	private function buildFolderName()
	{
		return ilUtil::getDataDir() . '/assessment/longMenuQuestion/' . $this->getId() . '/' ;
	}

	public function getAnswerTableName()
	{
		return "qpl_a_lome";
	}
	
	private function buildFileName($gap_id)
	{
		try
		{
			$this->assertDirExists();
			return $this->buildFolderName() . $gap_id . '.txt';
		}
		catch (ilException $e) {
			
		}
	}

	function setLongMenuTextValue($long_menu_text = "")
	{
		$this->long_menu_text = $long_menu_text;
	}

	function getLongMenuTextValue()
	{
		return $this->long_menu_text;
	}
		
	public function setAnswers($answers)
	{
		$this->answers = $answers;
	}

	public function getAnswers()
	{
		return $this->answers;
	}

	/**
	 * @return mixed
	 */
	public function getJsonStructure()
	{
		return $this->json_structure;
	}

	/**
	 * @param mixed $json_structure
	 */
	public function setJsonStructure($json_structure)
	{
		$this->json_structure = $json_structure;
	}
	
	public function setSpecificFeedbackSetting($specificFeedbackSetting)
	{
		$this->specificFeedbackSetting = $specificFeedbackSetting;
	}

	public function getSpecificFeedbackSetting()
	{
		return $this->specificFeedbackSetting;
	}
	
	public function isComplete()
	{
		if (strlen($this->title)
			&& $this->author
			&& $this->question
			&& sizeof($this->answers) > 0
			&& sizeof($this->correct_answers) > 0	
			&& $this->getPoints() > 0
		)
		{
			return true;
		}
		return false;
	}
	
	public function saveToDb($original_id = "")
	{
		$this->saveQuestionDataToDb($original_id);
		$this->saveAdditionalQuestionDataToDb();
		$this->saveAnswerSpecificDataToDb();
		parent::saveToDb($original_id);
	}

	public function checkQuestionCustomPart()
	{
		$hidden_text_files 	= $this->getAnswers();
		$correct_answers	= $this->getCorrectAnswers();
		$points 			= array();
		if( sizeof($correct_answers) == 0 || sizeof($hidden_text_files) == 0 )
		{
			return false;
		}
		if(sizeof($correct_answers) != sizeof($hidden_text_files))
		{
			return false;
		}
		foreach($correct_answers as $key => $correct_answers_row)
		{
			if($this->correctAnswerDoesNotExistInAnswerOptions($correct_answers_row, $hidden_text_files[$key]))
			{
				return false;
			}
			if($correct_answers_row[1] > 0)
			{
				array_push($points, $correct_answers_row[1]);
			}
		}
		if(sizeof($correct_answers) != sizeof($points))
		{
			return false;
		}
		
		foreach($points as $row)
		{
			if($row <= 0)
			{
				return false;
			}
		}
		return true;
	}
	
	private function correctAnswerDoesNotExistInAnswerOptions($answers, $answer_options)
	{
		foreach($answers[0] as $key => $answer)
		{
			if(!in_array($answer, $answer_options))
			{
				return true;
			}
		}
		return false;
	}


	/**
	 * Returns the maximum points, a learner can reach answering the question
	 *
	 * @access public
	 * @see $points
	 */
	function getMaximumPoints()
	{
		$sum 		= 0;
		$points = $this->getCorrectAnswers();
		if($points)
		{
			foreach($points as $add)
			{
				$sum += $add[1];
			}
		}
		return $sum;
	}
	
	public function saveAdditionalQuestionDataToDb()
	{
		// save additional data
		$this->ilDB->manipulateF( "DELETE FROM " . $this->getAdditionalTableName() . " WHERE question_fi = %s",
			array( "integer" ),
			array( $this->getId() )
		);
		$this->ilDB->manipulateF( "INSERT INTO " . $this->getAdditionalTableName(
			) . " (question_fi, long_menu_text, feedback_setting) VALUES (%s, %s, %s)",
			array( "integer", "text", "integer"),
			array(
				$this->getId(),
				$this->getLongMenuTextValue(),
				(int)$this->getSpecificFeedbackSetting()
			)
		);
		$this->createFileFromArray();
	}

	public function saveAnswerSpecificDataToDb()
	{
		$this->clearAnswerSpecificDataFromDb($this->getId());
		$type_array = $this->getAnswerType();
		foreach($this->getCorrectAnswers() as $gap_number => $gap)
		{
			foreach($gap[0] as $position => $answer)
			{
				if($type_array == null)
				{
					$type = $gap[2];
				}
				else
				{
					$type = $type_array[$gap_number];
				}
				$this->db->replace(
						$this->getAnswerTableName(),
						array(
								'question_fi' => array('integer', (int)$this->getId()),
								'gap_number'  => array('integer', (int)$gap_number),
								'position'    => array('integer', (int)$position)
						),
						array(
								'answer_text' => array('text', $answer),
								'points'      => array('float', $gap[1]),
								'type'        => array('integer', (int)  $type)
						)
				);
			}
		}
	}
	
	private function createFileFromArray()
	{
		$array = $this->getAnswers();
		$this->clearFolder();
		foreach($array as $gap => $values)
		{
			$file_content = '';
			if(is_array($values))
			{
				foreach($values as $key => $value)
				{
					$file_content .= $value . '\n';
				}
				$file_content = rtrim($file_content, '\n'); 
				$file = fopen($this->buildFileName($gap), "w");
				fwrite($file, $file_content);
				fclose($file);
			}
		}
	}

	private function createArrayFromFile()
	{
		$answers = array();
		foreach( glob( $this->buildFolderName() . '*.txt' ) as $file) 
		{
			$gap					= str_replace('.txt', '', basename($file));
			$answers[(int) $gap] 	= explode('\n', file_get_contents($file));
		}
		$this->setAnswers($answers);
		return $answers;
	}
	
	private function clearFolder($let_folder_exists = true)
	{
		ilUtil::delDir($this->buildFolderName(), $let_folder_exists);
	}
	
	private function assertDirExists()
	{
		$folder_name = $this->buildFolderName();
		if(!ilUtil::makeDirParents($folder_name))
		{
			throw new ilException('Cannot create export directory');
		}

		if(
			!is_dir($folder_name) ||
			!is_readable($folder_name) ||
			!is_writable($folder_name)
		)
		{
			throw new ilException('Cannot create export directory');
		}
	}
	
	public function loadFromDb($question_id)
	{
		$result = $this->ilDB->queryF("SELECT qpl_questions.*, " . $this->getAdditionalTableName() . ".* FROM qpl_questions LEFT JOIN " . $this->getAdditionalTableName() . " ON " . $this->getAdditionalTableName() . ".question_fi = qpl_questions.question_id WHERE qpl_questions.question_id = %s",
			array("integer"),
			array($question_id)
		);
		if ($result->numRows() == 1)
		{
			$data = $this->ilDB->fetchAssoc($result);
			$this->setId($question_id);
			$this->setObjId($data["obj_fi"]);
			$this->setNrOfTries($data['nr_of_tries']);
			$this->setTitle($data["title"]);
			$this->setComment($data["description"]);
			$this->setOriginalId($data["original_id"]);
			$this->setAuthor($data["author"]);
			$this->setPoints($data["points"]);
			$this->setOwner($data["owner"]);
			include_once("./Services/RTE/classes/class.ilRTE.php");
			$this->setQuestion(ilRTE::_replaceMediaObjectImageSrc($data['question_text'], 1));
			$this->setEstimatedWorkingTime(substr($data["working_time"], 0, 2), substr($data["working_time"], 3, 2), substr($data["working_time"], 6, 2));
			$this->setLongMenuTextValue(ilRTE::_replaceMediaObjectImageSrc($data['long_menu_text'], 1));
			$this->loadCorrectAnswerData($question_id);
			if( isset($data['feedback_setting']) )
			{
				$this->setSpecificFeedbackSetting((int)$data['feedback_setting']);
			}
			try
			{
				$this->setAdditionalContentEditingMode($data['add_cont_edit_mode']);
			}
			catch(ilTestQuestionPoolException $e)
			{
			}
		}

		$this->loadCorrectAnswerData($question_id);
		$this->createArrayFromFile();
		parent::loadFromDb($question_id);
	}

	private function loadCorrectAnswerData($question_id)
	{
		$res = $this->db->queryF(
			"SELECT * FROM {$this->getAnswerTableName()} WHERE question_fi = %s ORDER BY gap_number, position ASC",
			array('integer'), array($question_id)
		);
		
		$correct_answers	= array();
		while($data = $this->ilDB->fetchAssoc($res))
		{
			$correct_answers[$data['gap_number']][0][$data['position']]	= $data['answer_text'];
			$correct_answers[$data['gap_number']][1]					= $data['points'];
			$correct_answers[$data['gap_number']][2]					= $data['type'];
		}
		$this->setJsonStructure(json_encode($correct_answers));
		$this->setCorrectAnswers($correct_answers);
		
	}

	public function getCorrectAnswersForQuestionSolution($question_id)
	{
		$correct_answers = array();
		$res = $this->db->queryF(
			'SELECT gap_number, answer_text FROM  ' . $this->getAnswerTableName() .' WHERE question_fi = %s',
			array('integer'), array($question_id)
		);
		while($data = $this->ilDB->fetchAssoc($res))
		{
			if(array_key_exists($data['gap_number'], $correct_answers))
			{
				$correct_answers[$data['gap_number']] .= ' ' . $this->lng->txtlng("assessment", "or", ilObjAssessmentFolder::_getLogLanguage()) . ' ';
				$correct_answers[$data['gap_number']] .= $data['answer_text'];
			}
			else
			{
				$correct_answers[$data['gap_number']] .= $data['answer_text'];
			}
		}
		return $correct_answers;
	}

	private function getCorrectAnswersForGap($question_id, $gap_id)
	{
		$correct_answers = array();
		$res = $this->db->queryF(
			'SELECT answer_text FROM  ' . $this->getAnswerTableName() .' WHERE question_fi = %s AND gap_number = %s',
			array('integer', 'integer'), array($question_id, $gap_id)
		);
		while($data = $this->ilDB->fetchAssoc($res))
		{
			$correct_answers[] = $data['answer_text'];
		}
		return $correct_answers;
	}
	
	private function getPointsForGap($question_id, $gap_id)
	{
		$points = 0;
		$res = $this->db->queryF(
			'SELECT points FROM  ' . $this->getAnswerTableName() .' WHERE question_fi = %s AND gap_number = %s GROUP BY gap_number, points',
			array('integer', 'integer'), array($question_id, $gap_id)
		);
		while($data = $this->ilDB->fetchAssoc($res))
		{
			$points = $data['points'];
		}
		return $points;
	}

	
	function getAnswersObject()
	{
			return json_encode($this->createArrayFromFile());
	}
	
	function getCorrectAnswersAsJson()
	{
		$this->loadCorrectAnswerData($this->getId());
		return $this->getJsonStructure();
	}
	
	function duplicate($for_test = true, $title = "", $author = "", $owner = "", $testObjId = null)
	{

		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		
		// duplicate the question in database
		$this_id = $this->getId();
		$thisObjId = $this->getObjId();

		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;

		if( (int)$testObjId > 0 )
		{
			$clone->setObjId($testObjId);
		}

		if ($title)
		{
			$clone->setTitle($title);
		}

		if ($author)
		{
			$clone->setAuthor($author);
		}
		if ($owner)
		{
			$clone->setOwner($owner);
		}

		if ($for_test)
		{
			$clone->saveToDb($original_id);
		}
		else
		{
			$clone->saveToDb();
		}
		
		$clone->copyPageOfQuestion($this_id);
		$clone->copyXHTMLMediaObjectsOfQuestion($this_id);
		$clone->onDuplicate($thisObjId, $this_id, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}

	function copyObject($target_questionpool_id, $title = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}
		// duplicate the question in database
		$clone = $this;
		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");
		$original_id = assQuestion::_getOriginalId($this->id);
		$clone->id = -1;
		$source_questionpool_id = $this->getObjId();
		$clone->setObjId($target_questionpool_id);
		if ($title)
		{
			$clone->setTitle($title);
		}
		$clone->saveToDb();
		
		$clone->copyPageOfQuestion($original_id);
		$clone->copyXHTMLMediaObjectsOfQuestion($original_id);

		$clone->onCopy($source_questionpool_id, $original_id, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}

	public function createNewOriginalFromThisDuplicate($targetParentId, $targetQuestionTitle = "")
	{
		if ($this->id <= 0)
		{
			// The question has not been saved. It cannot be duplicated
			return;
		}

		include_once ("./Modules/TestQuestionPool/classes/class.assQuestion.php");

		$sourceQuestionId = $this->id;
		$sourceParentId = $this->getObjId();

		// duplicate the question in database
		$clone = $this;
		$clone->id = -1;

		$clone->setObjId($targetParentId);

		if ($targetQuestionTitle)
		{
			$clone->setTitle($targetQuestionTitle);
		}

		$clone->saveToDb();
		$clone->copyPageOfQuestion($sourceQuestionId);
		$clone->copyXHTMLMediaObjectsOfQuestion($sourceQuestionId);

		$clone->onCopy($sourceParentId, $sourceQuestionId, $clone->getObjId(), $clone->getId());

		return $clone->id;
	}
	

	/**
	 * Returns the points, a learner has reached answering the question.
	 * The points are calculated from the given answers.
	 *
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $returndetails (deprecated !!)
	 *
	 * @throws ilTestException
	 * @return integer/array $points/$details (array $details is deprecated !!)
	 */
	public function calculateReachedPoints($active_id, $pass = NULL, $returndetails = FALSE)
	{
		if( $returndetails )
		{
			throw new ilTestException('return details not implemented for '.__METHOD__);
		}

		$found_values = array();
		if (is_null($pass))
		{
			$pass = $this->getSolutionMaxPass($active_id);
		}
		$result = $this->ilDB->queryF("SELECT * FROM tst_solutions WHERE active_fi = %s AND question_fi = %s AND pass = %s",
			array('integer','integer','integer'),
			array($active_id, $this->getId(), $pass)
		);
		while ($data =  $this->ilDB->fetchAssoc($result))
		{
			$found_values[(int)$data['value1']] = $data['value2'];
		}

		$points = $this->calculateReachedPointsForSolution($found_values, $active_id);

		return $points;
	}

	protected function calculateReachedPointsForSolution($found_values, $active_id = 0)
	{
		$points = 0;
		foreach($found_values as $key => $answer)
		{
			if($answer != '')
			{
				$correct_answers = $this->getCorrectAnswersForGap($this->id, $key);
				if(in_array($answer, $correct_answers))
				{
					$points += $this->getPointsForGap($this->id, $key);
				}
			}
		}
		return $points;
	}
	
	/**
	 * Saves the learners input of the question to the database.
	 *
	 * @access public
	 * @param integer $active_id Active id of the user
	 * @param integer $pass Test pass
	 * @return boolean $status
	 */
	public function saveWorkingData($active_id, $pass = NULL)
	{
		if (is_null($pass))
		{
			include_once "./Modules/Test/classes/class.ilObjTest.php";
			$pass = ilObjTest::_getPass($active_id);
		}
		$this->getProcessLocker()->requestUserSolutionUpdateLock();

		$entered_values = 0;
		$this->removeCurrentSolution($active_id, $pass);

		foreach($this->getSolutionSubmit() as $val1 => $val2)
		{
			$value = ilUtil::stripSlashes($val2, FALSE);
			if (strlen($value))
			{
				$this->saveCurrentSolution($active_id,$pass, $val1, $value);
				$entered_values++;
			}
		}
		$this->getProcessLocker()->releaseUserSolutionUpdateLock();
		
		if ($entered_values)
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		else
		{
			include_once ("./Modules/Test/classes/class.ilObjAssessmentFolder.php");
			if (ilObjAssessmentFolder::_enabledAssessmentLogging())
			{
				$this->logAction($this->lng->txtlng("assessment", "log_user_not_entered_values", ilObjAssessmentFolder::_getLogLanguage()), $active_id, $this->getId());
			}
		}
		return true;
	}

	public function getSolutionSubmit()
	{
		$solutionSubmit = array();
		$answer = ilUtil::stripSlashesRecursive($_POST['answer']);
		
		foreach($answer as $key => $value)
		{
			$solutionSubmit[$key] = $value;
		}

		return $solutionSubmit;
	}
	
	protected function savePreviewData(ilAssQuestionPreviewSession $previewSession)
	{
		if( array_key_exists('answer',$_POST) )
		{
			$previewSession->setParticipantsSolution($_POST['answer']);
		}
		else
		{
			$previewSession->setParticipantsSolution(null);
		}
	}

	/**
	 * Reworks the allready saved working data if neccessary
	 *
	 * @param integer $active_id
	 * @param integer $pass
	 * @param boolean $obligationsAnswered
	 */
	protected function reworkWorkingData($active_id, $pass, $obligationsAnswered)
	{
		// nothing to rework!
	}
	
	/**
	 * Returns the question type of the question
	 *
	 * @return integer The question type of the question
	 */
	public function getQuestionType()
	{
		return "assLongMenu";
	}

	/**
	 * Returns the name of the additional question data table in the database
	 *
	 * @return string The additional table name
	 */
	public function getAdditionalTableName()
	{
		return 'qpl_qst_lome';
	}

	/**
	 * Collects all text in the question which could contain media objects
	 * which were created with the Rich Text Editor
	 */
	function getRTETextWithMediaObjects()
	{
		return parent::getRTETextWithMediaObjects() . $this->getLongMenuTextValue();
	}
	/**
	 * Creates an Excel worksheet for the detailed cumulated results of this question
	 *
	 * @param object $worksheet    Reference to the parent excel worksheet
	 * @param object $startrow     Startrow of the output in the excel worksheet
	 * @param object $active_id    Active id of the participant
	 * @param object $pass         Test pass
	 * @param object $format_title Excel title format
	 * @param object $format_bold  Excel bold format
	 *
	 * @return object
	 */
	public function setExportDetailsXLS(&$worksheet, $startrow, $active_id, $pass, &$format_title, &$format_bold)
	{

	}
	
	/**
	 * Get the user solution for a question by active_id and the test pass
	 *
	 * @param int $active_id
	 * @param int $pass
	 *
	 * @return ilUserQuestionResult
	 */
	public function getUserQuestionResult($active_id, $pass)
	{
		$result = new ilUserQuestionResult($this, $active_id, $pass);

		$points = $this->calculateReachedPoints($active_id, $pass);
		$max_points = $this->getMaximumPoints();

		$result->setReachedPercentage(($points/$max_points) * 100);

		return $result;
	}

	/**
	 * If index is null, the function returns an array with all anwser options
	 * Else it returns the specific answer option
	 *
	 * @param null|int $index
	 *
	 * @return array|ASS_AnswerSimple
	 */
	public function getAvailableAnswerOptions($index = null)
	{
		return $this->createArrayFromFile();
	}
	
	public function isShuffleAnswersEnabled()
	{
		return false;
	}

	public function clearAnswerSpecificDataFromDb($question_id)
	{
		$this->ilDB->manipulateF( 'DELETE FROM ' . $this->getAnswerTableName() .' WHERE question_fi = %s',
			array( 'integer' ),
			array( $question_id )
		);
	}

	public function delete($original_id)
	{
		parent::delete($original_id);
		$this->clearFolder(false);
	}

	/**
	 * Returns a JSON representation of the question
	 */
	public function toJSON()
	{
		include_once("./Services/RTE/classes/class.ilRTE.php");
		$result = array();
		$result['id'] = (int) $this->getId();
		$result['type'] = (string) $this->getQuestionType();
		$result['title'] = (string) $this->getTitle();
		$replaced_quesiton_text =  $this->getLongMenuTextValue();
		$result['question'] =  $this->formatSAQuestion($this->getQuestion()) . '<br/>' .$replaced_quesiton_text;
		$result['nr_of_tries'] = (int) $this->getNrOfTries();
		$result['shuffle'] = (bool) $this->getShuffle();
		$result['feedback'] = array(
			"onenotcorrect" => $this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), false),
			"allcorrect" => $this->feedbackOBJ->getGenericFeedbackTestPresentation($this->getId(), true)
		);
		
		$mobs = ilObjMediaObject::_getMobsOfObject("qpl:html", $this->getId());
		$result['answers'] = $this->getAnswers();
		$result['correct_answers'] = $this->getCorrectAnswers();
		$result['mobs'] = $mobs;
		return json_encode($result);
	}
}