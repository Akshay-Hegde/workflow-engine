<?php

class WorkflowStep
{

    private $_workflowStepId;
    private $_stepId;
    private $objWorkflow;
    private $objAudit;
    private $objMike;
    private $nextStep;
    private $workflowId;
    private $fieldValidation;
    private $collectionId;
    private $parentId;
    private $elementId;
    private $workflowName;
    private $_systemName;
    private $_stepName;
    private $objMysql;
    private $objectId;
    private $currentStep;
    private $nextTask;
    private $currentTask;
    private $blReview = false;

    public function __construct ($intWorkflowStepId = null, $objMike = null)
    {
        $this->objMysql = new Mysql2();
        if ( $intWorkflowStepId !== null )
        {
            $this->_workflowStepId = $intWorkflowStepId;
            if ( !$this->setStepInformation () )
            {
                return false;
            }
        }
        if ( $objMike !== null )
        {
            if ( $this->setWorkflowStepFromObject ($objMike) === false )
            {
                return false;
            }
            if ( !$this->setStepInformation () )
            {
                return false;
            }
        }
        $this->objMike = $objMike;
    }

    public function getWorkflowStepId ()
    {
        return $this->_workflowStepId;
    }

    public function getNextStepId ()
    {
        return $this->nextStep;
    }

    public function getWorkflowId ()
    {
        return $this->workflowId;
    }

    public function getStepId ()
    {
        return $this->_stepId;
    }

    public function getFieldValidation ()
    {
        return $this->fieldValidation;
    }

    public function getNextTask ()
    {
        return $this->nextTask;
    }

    public function getCurrentTask ()
    {
        return $this->currentTask;
    }

    public function setStepInformation ()
    {
        $sql = "SELECT
                    sy.system_name,
                    r.request_id,
                    m.workflow_id,
                    m.step_from AS current_step_id,
                    m.id AS current_step,
                    m.TAS_UID AS current_task,
                    t.step_name AS current_step_name,
                    m2.id AS next_step_id,
                    s2.step_name AS  next_step_name,
                    s2.TAS_UID,
                    w.workflow_name
                   FROM workflow.status_mapping m
                    INNER JOIN workflow.workflows w ON w.workflow_id = m.workflow_id
                    INNER JOIN workflow.task t ON t.TAS_UID = m.step_from
                    INNER JOIN workflow.request_types r ON r.request_id = w.request_id
                    INNER JOIN workflow.workflow_systems sy ON sy.system_id = r.system_id
                    LEFT JOIN workflow.status_mapping m2 ON m2.step_from = m.step_to AND m2.workflow_id = m.workflow_id
                    LEFT JOIN workflow.task s2 ON s2.TAS_UID = m2.TAS_UID
                   WHERE m.id = ?";

        $arrResult = $this->objMysql->_query ($sql, array($this->_workflowStepId));
        if ( empty ($arrResult) )
        {
            throw new Exception ("Fsiled to get workflow data");
            return false;
        }
        $this->_stepId = $arrResult[0]['current_step_id'];
        $this->nextStep = $arrResult[0]['next_step_id'];
        $this->workflowId = $arrResult[0]['workflow_id'];
        $this->_systemName = $arrResult[0]['system_name'];
        $this->collectionId = $arrResult[0]['request_id'];
        $this->_stepName = $arrResult[0]['current_step_name'];
        $this->workflowName = $arrResult[0]['workflow_name'];
        $this->currentStep = $arrResult[0]['current_step'];
        $this->currentTask = $arrResult[0]['current_task'];
        $this->nextTask = $arrResult[0]['TAS_UID'];
        return true;
    }

    private function setWorkflowStepFromObject ($objMike)
    {
        $id = $objMike->getId ();
        if ( method_exists ($objMike, "getSource_id") && $objMike->getSource_id () != "" )
        {
            $parentId = $objMike->getSource_id ();
        }
        else
        {
            $parentId = $id;
        }
        $result = $this->objMysql->_select ("workflow.workflow_data", array("workflow_data"), array("object_id" => $parentId));
        if ( !isset ($result[0]) )
        {
            //return false;
        }
        $workflowData = json_decode ($result[0]['workflow_data'], true);
        if ( empty ($workflowData) )
        {
            return FALSE;
        }
        if ( isset ($workflowData['elements'][$id]) )
        {
            $this->_workflowStepId = $workflowData['elements'][$id]['current_step'];
        }
        else
        {
            $this->_workflowStepId = $workflowData['current_step'];
        }
        return $workflowData;
    }

    public function getConditions ()
    {
        $arrResult = $this->objMysql->_select ("workflow.status_mapping", array("step_condition"), array("id" => $this->_workflowStepId));
        if ( empty ($arrResult) )
        {
            return false;
        }
        $conditions = json_decode ($arrResult[0]['step_condition'], true);
        return $conditions;
    }

    public function getFields ()
    {
        $objFieldFactory = new \BusinessModel\FieldFactory();
        $this->arrFields = $objFieldFactory->getFieldsForStep (new Task ($this->_stepId));
        return $this->arrFields;
    }

    private function getWorkflowData ()
    {
        if ( is_numeric ($this->parentId) )
        {
            $result = $this->objMysql->_select ("workflow.workflow_data", array("workflow_data", "audit_data", "id"), array("object_id" => $this->parentId));
            if ( empty ($result) )
            {
                return false;
            }
            $this->objectId = $result[0]['id'];
            return $result;
        }
        else
        {
            //$result = $objMysql->_select ("task_manager.projects", array("workflow_data", "audit_data"), array("id" => $this->id));
        }
    }

    /*     * *********** Save Methods ************************ */

    public function save ($objMike, array $arrFormData, Users $objUser, $arrEmailAddresses = array())
    {
        $parentId = null;
        if ( method_exists ($objMike, "getParentId") )
        {
            $parentId = $objMike->getParentId ();
        }
        $elementId = $objMike->getId ();
        $objCases = new \BusinessModel\Cases();
        if ( !$objCases->hasPermission ($objUser, $parentId, $elementId) )
        {
            throw new Exception ("You do not have permission to do this");
        }

        if ( !$this->validateWorkflowStep ($arrFormData, $objUser, $objMike) )
        {
            return false;
        }

        if ( !$objMike->loadObject ($arrFormData) )
        {
            return false;
        }
        if ( $objMike->save ($objUser) === false )
        {
            return false;
        }

        // cannot move step if the task has been put in review;
        if ( $this->blReview === true )
        {
            return false;
        }

        if ( isset ($arrFormData['status']) )
        {
            if ( $this->completeWorkflowObject ($objMike, $objUser, $arrFormData, false, $arrEmailAddresses) === false )
            {
                return false;
            }
        }
        else
        {
            if ( $this->completeWorkflowObject ($objMike, $objUser, array(), false, $arrEmailAddresses) === false )
            {
                return false;
            }
        }
    }

    private function searchArray ($products, $field, $value)
    {
        foreach ($products as $key => $product) {
            if ( isset ($product[$field]) && $product[$field] === $value )
                return $key;
        }
        return false;
    }

    //completeAuditObject (Users $objUser, array $arrCompleteData = [])

    private function validateWorkflowStep ($arrFormData, Users $objUser, $objMike)
    {
        $objValidate = new FieldValidator ($this->_stepId);
        $arrErrorsCodes = $objValidate->validate ($arrFormData);

        if ( !empty ($arrErrorsCodes) )
        {
            // put into review if incorrect data

            if ( $this->searchArray ($arrErrorsCodes, "message", "incorrect_data") !== false )
            {

                $this->elementId = $objMike->getId ();

                if ( method_exists ($objMike, "getParentId") )
                {
                    $this->parentId = $objMike->getParentId ();
                }
                else
                {
                    $this->parentId = $objMike->getId ();
                }

                /*                 * ************** Determine next step if there is one else stay at current step ********************** */
                $arrWorkflowData = $this->getWorkflowData ();
                $this->objAudit = json_decode ($arrWorkflowData[0]['audit_data'], true);

                $this->completeAuditObject ($objUser, array("dateCompleted" => date ("Y-m-d H:i:s"), "status" => "IN REVIEW"));

                $strAudit = json_encode ($this->objAudit);

                if ( !empty ($arrWorkflowData) )
                {
                    $this->objMysql->_update ("workflow.workflow_data", ["audit_data" => $strAudit], ["id" => $this->objectId]);

                    $this->blReview = true;
                    $this->fieldValidation = $arrErrorsCodes;
                    return true;
                }
            }

            $this->fieldValidation = $arrErrorsCodes;
            return false;
        }
        return true;
    }

    private function sendNotification ($objMike, array $arrCompleteData = [], $arrEmailAddresses = [])
    {
        $objNotifications = new SendNotification();
        $objNotifications->setVariables ($this->_stepId, $this->_systemName);
        $objNotifications->setProjectId ($this->parentId);
        $objNotifications->setElementId ($this->elementId);
        if ( !empty ($arrEmailAddresses) )
        {
            $objNotifications->setArrEmailAddresses ($arrEmailAddresses);
        }

        $objStep = new Task ($this->_stepId);
        $objStep->setStepId ($this->_stepId);
        $objNotifications->buildEmail ($objStep);
    }

    private function completeAuditObject (Users $objUser, array $arrCompleteData = [], $isParallel = false)
    {
        $claimed = trim ($objUser->getUsername ()) !== "" ? $objUser->getUsername () : $arrCompleteData['claimed'];

        if ( $isParallel === true && !isset ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers']) )
        {
            $arrUsers = (new \BusinessModel\Task())->getTaskAssigneesAll ($this->workflowId, $this->_stepId, '', 0, 100, "user");

            $parallelUsers = [];

            foreach ($arrUsers as $key => $user) {
                $parallelUsers[$key]['username'] = $user['aas_username'];
            }

            $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers'] = $parallelUsers;
        }

        if ( is_numeric ($this->parentId) && is_numeric ($this->elementId) )
        {
            $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['claimed'] = $claimed;
            $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['dateCompleted'] = $arrCompleteData['dateCompleted'];
            $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['status'] = $arrCompleteData['status'];

            if ( isset ($arrCompleteData['due_date']) )
            {
                $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['due_date'] = $arrCompleteData['due_date'];
            }
        }
        else
        {
            $this->objAudit['claimed'] = $claimed;
            $this->objAudit['dateCompleted'] = $arrCompleteData['dateCompleted'];
            $this->objAudit['status'] = $arrCompleteData['status'];

            if ( isset ($arrCompleteData['due_date']) )
            {
                $this->objAudit['due_date'] = $arrCompleteData['due_date'];
            }
        }

        if ( $isParallel === true && isset ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers']) )
        {
            if ( isset ($arrCompleteData['claimed']) && trim ($arrCompleteData['claimed']) !== "" )
            {
                foreach ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers'] as $key => $parallelUser) {
                    if ( trim ($parallelUser['username']) === trim ($arrCompleteData['claimed']) )
                    {
                        $this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers'][$key]['dateCompleted'] = date ("Y-m-d H:i:s");
                    }
                }
            }
        }

        return true;
    }

    private function completeWorkflowObject ($objMike, Users $objUser, $arrCompleteData, $complete = false, $arrEmailAddresses = array())
    {
        $this->elementId = $objMike->getId ();
        $arrWorkflow = array();
        if ( method_exists ($objMike, "getParentId") )
        {
            $this->parentId = $objMike->getParentId ();
        }
        else
        {
            $this->parentId = $objMike->getId ();
        }

        /*         * ************** Determine next step if there is one else stay at current step ********************** */
        $arrWorkflowData = $this->getWorkflowData ();
        $arrWorkflowObject = json_decode ($arrWorkflowData[0]['workflow_data'], true);
        $blHasTrigger = false;
        $arrWorkflow['request_id'] = $this->collectionId;
        $objTrigger = new \BusinessModel\StepTrigger ($this->_workflowStepId, $this->nextStep);

        if ( !isset ($arrCompleteData['status']) || trim ($arrCompleteData['status']) !== "REJECT" )
        {
            $blHasTrigger = $objTrigger->checkTriggers ($objMike, $objUser);
        }

        // reload
        if ( $objTrigger->blAddedCase )
        {
            $arrWorkflowData = $this->getWorkflowData ();
            $arrWorkflowObject = json_decode ($arrWorkflowData[0]['workflow_data'], true);
        }

        $this->objAudit = json_decode ($arrWorkflowData[0]['audit_data'], true);

        if ( $complete === true && $this->nextStep !== 0 && $this->nextStep != "" )
        {

            if ( $blHasTrigger === true )
            {
                $arrWorkflowObject = $objTrigger->arrWorkflowObject;
            }

            if ( $objTrigger->blMove === true || $blHasTrigger === false )
            {
                $blHasTrigger = false;
                $step = $this->nextTask;
                $step2 = $this->nextStep;
                $arrWorkflow['current_step'] = $this->nextStep;

                (new \Log (LOG_FILE))->log (
                        array(
                    "message" => "STEP COMPLETED",
                    'case_id' => $this->elementId,
                    'project_id' => $this->parentId,
                    'user' => $objUser->getUsername (),
                    'workflow_id' => $this->workflowId,
                    'step_id' => $this->nextStep
                        ), \Log::NOTICE);
            }
            $arrWorkflow['status'] = "STEP COMPLETED";
        }
        else
        {
            $step = $this->currentTask;
            $step2 = $this->_workflowStepId;
            $arrWorkflow['current_step'] = $this->_workflowStepId;
            if ( $this->nextStep == 0 || $this->nextStep == "" )
            {
                $arrWorkflow['status'] = "WORKFLOW COMPLETE";
            }
            else
            {
                $arrWorkflow['status'] = "SAVED";
            }
        }

        if ( !isset ($step) || !isset ($step2) )
        {
            $step = $this->currentTask;
            $step2 = $this->_stepId;
        }

        /*         * ******************** Get due date for Task ********************** */
        $objAppDelegation = new AppDelegation();
        $objTask = new Task();
        $objTask->setTasUid ($step);
        $objTask->setStepId ($step2);

        try {
            if ( !isset ($this->objAudit['elements'][$this->elementId]['steps'][$step]) )
            {
                $arrCompleteData['due_date'] = $objAppDelegation->calculateDueDate ((new Task ($this->_stepId))->retrieveByPk ($step));
            }
            else
            {
                if ( isset ($this->objAudit['elements'][$this->elementId]['steps'][$step]['due_date']) )
                {
                    $arrCompleteData['due_date'] = $this->objAudit['elements'][$this->elementId]['steps'][$step]['due_date'];
                }
                else
                {
                    $arrCompleteData['due_date'] = "";
                }
            }
        } catch (Exception $ex) {
            
        }

        if ( isset ($arrCompleteData['dateCompleted']) && isset ($arrCompleteData['claimed']) )
        {
            $objTask = $objTask->retrieveByPk ($this->_stepId);
            $blIsParralelTask = false;

            if ( in_array ($objTask->getTasAssignType (), array("MULTIPLE_INSTANCE", "MULTIPLE_INSTANCE_VALUE_BASED")) )
            {
                $taskType = "PARALLEL";
                $blIsParralelTask = true;
            }
            else
            {
                $taskType = isset ($arrCompleteData['status']) ? $arrCompleteData['status'] : '';
            }


            $this->completeAuditObject ($objUser, $arrCompleteData, $blIsParralelTask);
            if ( $complete === true || in_array ($taskType, array("HELD", "ABANDONED", "CLAIMED")) )
            {
                if ( trim ($taskType) === "" )
                {
                    throw new Exception ("No task type given");
                }

                if ( !isset ($arrCompleteData['claimed']) || trim ($arrCompleteData['claimed']) === "" )
                {
                    throw new Exception ("No user given");
                }

                $arrUsers = (new \BusinessModel\Task())->getTaskAssigneesAll ($this->workflowId, $this->_stepId, '', 0, 100, "user");

                //      die($arrCompleteData['status']);

                switch ($taskType) {
                    case "PARALLEL":
                        $blTaskUsersCompleted = 0;

                        foreach ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers'] as $key => $parallelUser) {
                            if ( isset ($parallelUser['dateCompleted']) && trim ($parallelUser['dateCompleted']) !== "" )
                            {
                                $blTaskUsersCompleted++;
                            }
                        }

                        if ( count ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]['parallelUsers']) !== $blTaskUsersCompleted )
                        {
                            $arrWorkflow['current_step'] = $this->_workflowStepId;
                        }
                        break;

                    case "AUTO_ASSIGN":
                    case "COMPLETE":
                    case "HELD";
                    case "ABANDONED":
                        $blHasValidUser = false;

                        foreach ($arrUsers as $arrUser) {
                            if ( trim ($arrUser['aas_username']) === trim ($arrCompleteData['claimed']) )
                            {
                                $blHasValidUser = true;
                            }
                        }

                        if ( $blHasValidUser !== true )
                        {
                            $arrWorkflow['current_step'] = $this->_workflowStepId;
                            throw new Exception ("Invalid task user");
                        }

                        break;

                    case "CLAIMED":
                        foreach ($arrUsers as $arrUser) {
                            if ( trim ($arrUser['aas_username']) === trim ($arrCompleteData['claimed']) )
                            {
                                $blHasValidUser = true;
                            }
                        }

                        if ( $blHasValidUser !== true )
                        {
                            unset ($this->objAudit['elements'][$this->elementId]['steps'][$this->_workflowStepId]);
                        }

                        $arrWorkflow['current_step'] = $this->_workflowStepId;

                        break;
                }
            }
        }

        if ( isset ($arrCompleteData['status']) && $arrCompleteData['status'] === "AUTO_ASSIGN" )
        {

            if ( !isset ($arrCompleteData['claimed']) )
                $arrUsers = (new \BusinessModel\Task())->getTaskAssigneesAll ($this->workflowId, $this->_stepId, '', 0, 100, "user");
        }

        $arrWorkflow['workflow_id'] = $this->workflowId;
        if ( !empty ($arrWorkflowData) )
        {
            $this->objWorkflow = $arrWorkflowObject;
        }
        else
        {
            $this->objWorkflow = $arrWorkflow;
        }

        if ( is_numeric ($this->parentId) && is_numeric ($this->elementId) && $blHasTrigger !== true )
        {
            $this->objWorkflow['elements'][$this->elementId] = $arrWorkflow;
        }

        /*         * ***************** Check events for task ************************** */
        $hasEvent = isset ($arrCompleteData['hasEvent']) ? 'true' : 'false';
        $this->objWorkflow['elements'][$this->parentId]['hasEvent'] = $hasEvent;
        $this->objWorkflow['elements'][$this->elementId]['hasEvent'] = $hasEvent;

        if ( $hasEvent !== 'true' )
        {
            $this->checkEvents ($objUser);
        }

        if ( ($this->nextStep == 0 || $this->nextStep == "") && $complete === true && $arrCompleteData['status'] == "COMPLETE" )
        {
            $arrCompleteData['status'] = "COMPLETE";
            $this->objWorkflow['elements'][$this->elementId]['status'] = "WORKFLOW COMPLETE";
        }

        /*         * ****************** Validate User ****************** */

        if ( isset ($arrCompleteData['status']) && trim ($arrCompleteData['status']) === "CLAIMED" )
        {
            $claimFlag = true;
        }
        else
        {
            $claimFlag = false;
        }

        // check permissions
        $objCase = new \BusinessModel\Cases();
        $isValidUser = $objCase->doPostReassign (
                $objTask, array(
            "cases" => array(
                0 => array(
                    "elementId" => $this->elementId,
                    "parentId" => $this->parentId,
                    "user" => $objUser
                )
            )
                ), $claimFlag
        );

        if ( $isValidUser === false )
        {
            throw new Exception ("Invalid user given. Cannot complete workflow step " . $step . " - " . $this->workflowId);
        }

        // Update workflow and audit object
        $strAudit = json_encode ($this->objAudit);

        $objectId = isset ($this->parentId) && is_numeric ($this->parentId) ? $this->parentId : $this->elementId;

        $strWorkflow = json_encode ($this->objWorkflow);

        if ( !empty ($arrWorkflowData) )
        {
            $this->objMysql->_update ("workflow.workflow_data", array(
                "workflow_data" => $strWorkflow,
                "audit_data" => $strAudit), array(
                "object_id" => $objectId
                    ), array(
                "id" => $this->objectId
                    )
            );
        }
        else
        {
            $this->objMysql->_insert ("workflow.workflow_data", array(
                "workflow_data" => $strWorkflow,
                "audit_data" => $strAudit,
                "object_id" => $objectId)
            );
        }

        $this->sendNotification ($objMike, $arrCompleteData, $arrEmailAddresses);

        $this->nextTask = $step;
    }

    public function complete ($objMike, $arrCompleteData, Users $objUser, $arrEmailAddresses = array())
    {
        $parentId = null;
        if ( method_exists ($objMike, "getParentId") )
        {
            $parentId = $objMike->getParentId ();
        }
        $elementId = $objMike->getId ();
        $objCases = new \BusinessModel\Cases();
        if ( !$objCases->hasPermission ($objUser, $parentId, $elementId) )
        {
            throw new Exception ("You do not have permission to do this");
        }

        $this->completeWorkflowObject ($objMike, $objUser, $arrCompleteData, true, $arrEmailAddresses);

        if ( method_exists ($objMike, "updateTitle") )
        {
            $objMike->updateTitle ($objUser, $this);
        }

        if ( isset ($this->nextStep) && $this->nextStep !== 0 )
        {
            $this->checkEvents ($objUser);
            $this->_workflowStepId = $this->nextStep;
            $this->currentTask = $this->nextTask;
            return new WorkflowStep ($this->_workflowStepId, $objMike);
        }
        return true;
    }

    public function getFirstStepForWorkflow ()
    {
        $result = $this->objMysql->_select ("workflow.status_mapping", array(), array("workflow_id" => $this->workflowId, "first_step" => 1));
        if ( isset ($result[0]) && !empty ($result[0]) )
        {
            return $result;
        }
        return [];
    }

    public function stepExists ($stepId)
    {
        $result = $this->objMysql->_select ("workflow.task", [], ["TAS_UID" => $stepId]);

        if ( isset ($result[0]) && !empty ($result[0]) )
        {
            return true;
        }
        return false;
    }

    /**
     * Get the assigned groups of a task
     *
     * @param integer $sTaskUID
     * @param integer $iType
     * @return array
     */
    public function getGroupsOfTask ($sTaskUID, $iType)
    {
        try {

            $aGroups = [];

            $sql = "SELECT sp.* FROM workflow.step_permission sp "
                    . "INNER JOIN user_management.teams t ON t.team_id = sp.permission "
                    . "LEFT JOIN user_management.poms_users u ON u.team_id = t.team_id "
                    . "WHERE sp.permission_type = ? "
                    . "AND sp.step_id = ? "
                    . "AND t.status = 1";

            $results = $this->objMysql->_query ($sql, [$iType, $sTaskUID]);

            foreach ($results as $aRow) {
                $aGroups[] = $aRow;
            }

            return $aGroups;
        } catch (Exception $oError) {
            throw ($oError);
        }
    }

    public function checkEvents (Users $objUser)
    {
        $objEvent = new \BusinessModel\Event();
        $arrEvents = $objEvent->getEvent ($this->_workflowStepId);

        /*         * *************** MESSAGES ****************************** */
        if ( isset ($arrEvents[0]['step_condition']) && !empty ($arrEvents[0]['step_condition']) )
        {
            $arrConditions = json_decode ($arrEvents[0]['step_condition'], true);

            if ( isset ($arrConditions['sendNotification']) && trim (strtolower ($arrConditions['sendNotification'])) == "yes" )
            {
                $objFlow = new Flow();
                $objFlow->setWorkflowId ($this->workflowId);
                $objFlow->setStepFrom ($this->_workflowStepId);

                $objMessageApplication = new \BusinessModel\MessageApplication();
                $objMessageApplication->create ($objFlow, $this->elementId, $this->parentId, $this->objWorkflow);
            }

            if ( isset ($arrConditions['receiveNotification']) && trim (strtolower ($arrConditions['receiveNotification'])) == "yes" )
            {
                $objMessageApplication = new \BusinessModel\MessageApplication();
                $objMessageApplication->catchMessageEvent ($objUser);
            }
        }
    }

}
