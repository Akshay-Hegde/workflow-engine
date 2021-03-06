<?php

class Workflow extends BaseProcess
{

    private $intWorkflowId;
    private $objMike;
    private $intCollectionId;
    private $objMysql;
    private $sort;
    private $dir;

    public function __construct ($workflowId = null, $objMike = null)
    {
        $this->intWorkflowId = $workflowId;
        $this->objMike = $objMike;
        $this->objMysql = new Mysql2();
        if ( $objMike !== null )
        {
            $this->getWorkflowObject ();
        }
    }

    public function getNextStep ()
    {
        if ( $this->objMike !== null )
        {
            $workflowCollection = $this->getWorkflowObject ();
            return new WorkflowStep ($workflowCollection['current_step']);
        }
        else
        {
            $result = $this->objMysql->_select ("workflow.status_mapping", array("id"), array("workflow_id" => $this->intWorkflowId, "first_step" => 1));
            if ( !empty ($result) )
            {
                $intStartingWorkflowStepId = $result[0]['id'];
            }
            else
            {
                return false;
            }
            return new WorkflowStep ($intStartingWorkflowStepId);
        }
    }

    public function getWorkflowObject ()
    {
        $parentId = $this->objMike->getId ();
        $id = $this->objMike->getId ();
        if ( method_exists ($this->objMike, "getParentId") && $this->objMike->getParentId () != "" )
        {
            $parentId = $this->objMike->getSource_id ();
        }
        $result = $this->objMysql->_select ("workflow.workflow_data", array("workflow_data"), array("object_id" => $parentId));
        if ( !isset ($result[0]) )
        {
            //return false;
        }
        $workflowData = json_decode ($result[0]['workflow_data'], true);
        if ( is_numeric ($id) )
        {
            if ( isset ($workflowData['elements'][$id]) )
            {
                $workflowData = $workflowData['elements'][$id];
            }
        }
        if ( empty ($workflowData) )
        {
            return FALSE;
        }
        $this->intWorkflowId = $workflowData['workflow_id'];
        $this->intCollectionId = $workflowData['request_id'];
        return $workflowData;
    }

    public function getStepsForWorkflow ()
    {
        $arrResult = $this->objMysql->_query ("SELECT t.*, t.TAS_UID AS step_id, m.step_condition, m.first_step, m.step_from, m.order_id, m.step_to, m.id FROM workflow.status_mapping m
                                                INNER JOIN workflow.task t ON t.TAS_UID = m.TAS_UID
                                                WHERE m.workflow_id = ?
                                                ORDER BY m.order_id ASC", [0 => $this->intWorkflowId]);
        $arrSteps = array();
        foreach ($arrResult as $result) {
            $arrSteps[$result['id']] = $result;
        }
        return $arrSteps;
    }

    public function getWorkflowId ()
    {
        return $this->intWorkflowId;
    }

    public function getPreviousStatus ()
    {
        $arrResult = $this->objMysql->_select ("workflow.status_mapping", array(), array("step_to" => $this->status, "workflow_id" => $this->workflow));
        if ( !empty ($arrResult) )
        {
            return $arrResult;
        }
        else
        {
            $this->objMysql->_select ("workflow.status_mapping", array(), array("step_to" => $this->status));
        }
    }

    public function deleteWorkflow ()
    {
        $arrResult = $this->objMysql->_select ("workflow.status_mapping", array("workflow_id"), array("workflow_id" => $this->intWorkflowId));
        if ( empty ($arrResult) )
        {
            $this->objMysql->_delete ("workflow.status_mapping", array("workflow_id" => $this->intWorkflowId));
            $this->objMysql->_delete ("workflow.workflows", array("workflow_id" => $this->intWorkflowId));
            $this->objMysql->_delete ("workflow.workflow_mapping", array("workflow.from" => $this->intWorkflowId));
            $this->objMysql->_delete ("workflow.workflow_mapping", array("workflow_to" => $this->intWorkflowId));
        }
    }

    public function getAllProcesses ($start = 0, $limit = 25, $sort = 'request_id', $dir = 'ASC', $category = null, $processName = null, $counters = true, $userLogged = "")
    {
        $this->sort = $sort;
        $this->dir = $dir;
        $aProcesses = array();
        $arrParameters = array();
        $sql = "SELECT w.workflow_id,
                        w.workflow_name,
                        w.description,
                        r.request_id,
                        w.created_by,
                        w.date_created,
                        w.parent_id,
                        u.firstName,
                        u.lastName,
                        u.username,
                        r.request_type
                FROM workflow.workflows w
                LEFT JOIN user_management.poms_users u ON u.username = created_by
                INNER JOIN workflow.request_types r ON r.request_id = w.request_id
                WHERE w.PRO_SUBPROCESS != 1";
        if ( isset ($category) )
        {
            $sql .= " AND r.request_id = ?";
            $arrParameters[] = $category;
        }
        if ( $userLogged != "" )
        {
            $sql .= " AND w.created_by = ?";
            $arrParameters[] = $userLogged;
        }
        $sql .= " GROUP BY w.workflow_id";
        if ( $sort == "PRO_CREATE_DATE" )
        {
            if ( $dir == "DESC" )
            {
                $sql .= " ORDER BY w.date_created DESC";
            }
            else
            {
                $sql .= " ORDER BY w.date_created ASC";
            }
        }
        //execute a query to obtain numbers, how many cases there are by process
        if ( $counters )
        {
            $casesCnt = $this->getCasesCountInAllProcesses ();
        }

        //execute the query
        $results = $this->objMysql->_query ($sql, $arrParameters);

        $processes = array();
        $uids = array();

        foreach ($results as $row) {
            $processes[] = $row;
            $uids[] = $processes[sizeof ($processes) - 1]['workflow_id'];
        }

        foreach ($processes as $process) {
            $proTitle = isset ($process['workflow_name']) ? $process['workflow_name'] : '';
            $proDescription = isset ($process['description']) ? htmlspecialchars ($process['description']) : '';
            //filtering by $processName
            if ( isset ($processName) && $processName != '' && stripos ($proTitle, $processName) === false )
            {
                continue;
            }
            if ( $counters )
            {
                $casesCountTotal = 0;
                if ( isset ($casesCnt[$process['workflow_id']]) )
                {
                    $casesCountTotal += $casesCnt[$process['workflow_id']];
                }
            }
            $userOwner = $process['firstName'] . ' ' . $process['lastName'];
            $process['PRO_CATEGORY_LABEL'] = trim ($process['request_type']) != '' ? $process['request_type'] : '- ' . "No Category" . ' -';
            $process['PRO_TITLE'] = $proTitle;
            $process['PRO_DESCRIPTION'] = $proDescription;
            $process['PRO_STATUS_LABEL'] = "ACTIVE";
            $process['PRO_CREATE_USER_LABEL'] = $userOwner;

            /* if ( $counters )
              {
              $process['CASES_COUNT_TO_DO'] = (isset ($casesCnt[$process['PRO_UID']]['TO_DO']) ? $casesCnt[$process['PRO_UID']]['TO_DO'] : 0);
              $process['CASES_COUNT_COMPLETED'] = (isset ($casesCnt[$process['PRO_UID']]['COMPLETED']) ? $casesCnt[$process['PRO_UID']]['COMPLETED'] : 0);
              $process['CASES_COUNT_DRAFT'] = (isset ($casesCnt[$process['PRO_UID']]['DRAFT']) ? $casesCnt[$process['PRO_UID']]['DRAFT'] : 0);
              $process['CASES_COUNT_CANCELLED'] = (isset ($casesCnt[$process['PRO_UID']]['CANCELLED']) ? $casesCnt[$process['PRO_UID']]['CANCELLED'] : 0);
              $process['CASES_COUNT'] = $casesCountTotal;
              } */

            $aProcesses[] = $process;
        }

        if ( $limit == '' )
        {
            $limit = count ($aProcesses);
        }
        if ( $sort != "PRO_CREATE_DATE" )
        {
            if ( $dir == "ASC" )
            {
                usort ($aProcesses, array($this, "ordProcessAsc"));
            }
            else
            {
                usort ($aProcesses, array($this, "ordProcessDesc"));
            }
        }

        if ( is_numeric ($start) && is_numeric ($limit) )
        {
            $aProcesses = $this->paginate ($aProcesses, $limit, $start);
        }


        $arrWorkflows = [];
        foreach ($aProcesses['data'] as $aProcess) {
            $objProcess = new Workflow();
            $objProcess->setId ($aProcess['workflow_id']);
            $objProcess->setWorkflowName ($aProcess['PRO_TITLE']);
            $objProcess->setDescription ($aProcess['description']);
            $objProcess->setRequestId ($aProcess['request_id']);
            $objProcess->setProCreateDate ($aProcess['date_created']);
            $objProcess->setParentId ($aProcess['parent_id']);
            $objProcess->setCategoryName ($aProcess['PRO_CATEGORY_LABEL']);
            $objProcess->setProStatus ($aProcess['PRO_STATUS_LABEL']);
            $objProcess->setProCreateUser ($aProcess['PRO_CREATE_USER_LABEL']);
            $arrWorkflows[$aProcess['workflow_id']] = $objProcess;
        }

        $aProcesses['data'] = $arrWorkflows;

        return $aProcesses;
    }

    private function paginate ($array, $intPageLimit, $page = 1)
    {
        $arrData = [];
        $intPageLimit = (int) $intPageLimit;
        $page = (int) $page;
        $totalRows = (int) count ($array);

        $arrData['count']['page'] = $page;
        $arrData['count']['total_pages'] = (int) ceil (($totalRows / $intPageLimit));
        $arrData['count']['total'] = $totalRows;

        $page = $page < 1 ? 1 : $page + 1;
        $start = ($page - 1) * $intPageLimit;

        $arrData['data'] = array_slice ($array, $start, $intPageLimit);

        return $arrData;
    }

    public function ordProcessAsc ($a, $b)
    {
        if ( ($this->sort) == '' )
        {
            $this->sort = 'PRO_TITLE';
        }
        if ( strtolower ($a[$this->sort]) > strtolower ($b[$this->sort]) )
        {
            return 1;
        }
        elseif ( strtolower ($a[$this->sort]) < strtolower ($b[$this->sort]) )
        {
            return -1;
        }
        else
        {
            return 0;
        }
    }

    public function ordProcessDesc ($a, $b)
    {
        if ( ($this->sort) == '' )
        {
            $this->sort = 'PRO_TITLE';
        }
        if ( strtolower ($a[$this->sort]) > strtolower ($b[$this->sort]) )
        {
            return -1;
        }
        elseif ( strtolower ($a[$this->sort]) < strtolower ($b[$this->sort]) )
        {
            return 1;
        }
        else
        {
            return 0;
        }
    }

    public function getCasesCountInAllProcesses ()
    {
        $results = $this->objMysql->_select ("workflow.workflow_data");
        $arrCounts = array();
        foreach ($results as $result) {
            $workflowData = json_decode ($result['workflow_data'], true);
            if ( isset ($workflowData['elements']) && !empty ($workflowData['elements']) )
            {
                foreach ($workflowData['elements'] as $element) {
                    $arrCounts[$element['workflow_id']] = isset ($arrCounts[$element['workflow_id']]) ? $arrCounts[$element['workflow_id']] ++ : 1;
                }
            }
        }
        return $arrCounts;
    }

    public function getCasesCountForProcess ($pro_uid)
    {
        if ( trim ($pro_uid) === "" )
        {
            return false;
        }

        /* $oCriteria = new Criteria ('workflow');
          $oCriteria->addSelectColumn ('COUNT(*) AS TOTAL_CASES');
          $oCriteria->add (ApplicationPeer::PRO_UID, $pro_uid);
          $oDataset = ApplicationPeer::doSelectRS ($oCriteria, Propel::getDbConnection ('workflow_ro'));
          $oDataset->setFetchmode (ResultSet::FETCHMODE_ASSOC);
          $oDataset->next ();
          $cases = $oDataset->getRow ();
          return (int) $cases['TOTAL_CASES']; */
    }

    public function getAllProcessesByCategory ()
    {
        $results = $this->objMysql->_query ("SELECT request_id, COUNT(*) AS CNT FROM workflow.workflows GROUP BY request_id");
        $aProc = array();
        foreach ($results as $row) {
            $aProc[$row['request_id']] = $row['CNT'];
        }
        return $aProc;
    }

    /**
     * Get data of a Process
     *
     * @param string $processUid Unique id of Process
     *
     * return array Return an array with data of a Process
     */
    public function getProcess ()
    {
        try {
            $process = new \BusinessModel\Process();
            $process->throwExceptionIfNotExistsProcess ($this->intWorkflowId);
            $result = $this->objMysql->_select ("workflow.workflows", array(), array("workflow_id" => $this->intWorkflowId));
            if ( !isset ($result[0]) || empty ($result[0]) )
            {
                return [];
            }
            return $result;
        } catch (Exception $ex) {
            
        }
    }

    public function setWorkflowId ($workflowId)
    {
        $this->intWorkflowId = $workflowId;
    }

    /**
     * Load the Process row specified in [pro_id] column value.
     *
     * @param string $ProUid the uid of the Prolication
     * @return array $Fields the fields
     */
    public function load ($ProUid)
    {
        try {
            $this->setWorkflowId ($ProUid);
            $aProcess = $this->getProcess ();
            if ( !empty ($aProcess) && $aProcess !== false )
            {
                $objWorkflow = new Workflow ($ProUid);
                $objWorkflow->setDescription ($aProcess[0]['description']);
                $objWorkflow->setParentId ($aProcess[0]['parent_id']);
                $objWorkflow->setProCreateDate ($aProcess[0]['date_created']);
                $objWorkflow->setProCreateUser ($aProcess[0]['created_by']);
                $objWorkflow->setRequestId ($aProcess[0]['request_id']);
                $objWorkflow->setWorkflowName ($aProcess[0]['workflow_name']);
                $objWorkflow->setId ($ProUid);
                $objWorkflow->setSystemId ($aProcess[0]['system_id']);
                $objWorkflow->setProDynaforms ($aProcess[0]['PRO_DYNAFORMS']);
                $objWorkflow->setProTriCanceled ($aProcess[0]['PRO_TRI_CANCELED']);
                $objWorkflow->setProTriDeleted ($aProcess[0]['PRO_TRI_DELETED']);
                $objWorkflow->setProTriCreate ($aProcess[0]['PRO_TRI_CREATE']);
                $objWorkflow->setProTriOpen ($aProcess[0]['PRO_TRI_OPEN']);
                $objWorkflow->setProTriPaused ($aProcess[0]['PRO_TRI_PAUSED']);
                $objWorkflow->setProTriReassigned ($aProcess[0]['PRO_TRI_REASSIGNED']);
                $objWorkflow->setProTriUnpaused ($aProcess[0]['PRO_TRI_UNPAUSED']);
                return $objWorkflow;
            }
        } catch (Exception $oError) {
            throw ($oError);
        }
    }

    /**
     * Creates the Process
     *
     * @param array $aData Fields with :
     * $aData['PRO_UID'] the process id
     * $aData['USR_UID'] the userid
     * $aData['PRO_CATEGORY'] the id category
     * @return string
     */
    public function create ($aData)
    {
        try {
            $this->loadObject ($aData);
            if ( $this->validate () )
            {
                $id = $this->save ();
                return $id;
            }
            else
            {
                $msg = '';
                foreach ($this->getArrValidationErrors () as $strMessage) {
                    $msg .= $strMessage . "<br/>";
                }
                throw (new Exception ('The row cannot be created! ' . $msg));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function update ($arrData)
    {
        try {
            $this->loadObject ($arrData);
            if ( $this->validate () )
            {
                $this->save ();
            }
            else
            {
                $msg = '';
                foreach ($this->getArrValidationErrors () as $strMessage) {
                    $msg .= $strMessage . "<br/>";
                }
                throw (new Exception ('The row cannot be created! ' . $msg));
            }
        } catch (Exception $e) {
            throw $e;
        }
    }

    public function delete ()
    {
        $this->deleteWorkflow ();
    }

}
