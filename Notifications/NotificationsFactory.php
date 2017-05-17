<?php

class NotificationsFactory
{

    private $objMysql;

    public function __construct ()
    {
        $this->objMysql = new Mysql2();
    }

    /**
     * 
     * @param type $arrParameters
     * @param type $strOrderBy
     * @param type $strOrderDir
     * @return type
     */
    public function countNotifications ($arrParameters, $strOrderBy, $strOrderDir)
    {
        $arrWhere = array();

        $query = "SELECT ns.*, s.step_name FROM workflow.`notifications_sent` ns
                    LEFT JOIN workflow.steps s ON s.step_id = ns.`step_id`
                    WHERE 1=1
                    ";

        if ( isset ($arrParameters['user']) && $arrParameters['user'] !== null )
        {
            $query .= " AND recipient = ?";
            $arrWhere[] = $arrParameters['user'];
        }

        if ( isset ($arrParameters['status']) && $arrParameters['status'] != null )
        {
            $query .= " AND status = ?";
            $arrWhere[] = $arrParameters['status'];
        }

        if ( isset ($arrParameters['is_important']) && $arrParameters['is_important'] !== null && $arrParameters['is_important'] != 0 )
        {
            $query .= " AND is_important = 1";
        }

        if ( isset ($arrParameters['has_read']) && $arrParameters['has_read'] !== null && $arrParameters['has_read'] != 0 )
        {
            $query .= " AND has_read = 1";
        }

        if ( isset ($arrParameters['id']) && $arrParameters['id'] !== null )
        {
            $query .= " AND id = ?";
            $arrWhere[] = $arrParameters['id'];
        }

        if ( isset ($arrParameters['parent_id']) && $arrParameters['parent_id'] !== null )
        {
            $query .= " AND parent_id = ?";
            $arrWhere[] = $arrParameters['parent_id'];
        }

        if ( isset ($arrParameters['searchText']) && !empty ($arrParameters['searchText']) )
        {
            $query .= " AND (recipient LIKE ? OR subject LIKE ?)";
            $arrWhere[] = "%" . $arrParameters['searchText'] . "%";
            $arrWhere[] = "%" . $arrParameters['searchText'] . "%";
        }

        $query .= " ORDER BY " . $strOrderBy . " " . $strOrderDir;

        $arrResults = $this->objMysql->_query ($query, $arrWhere);

        return $arrResults[0]['totalCount'];
    }

    /**
     * 
     * @param type $arrParameters
     * @param type $pageLimit
     * @param type $page
     * @param type $strOrderBy
     * @param type $strOrderDir
     * @return \Notifications
     */
    public function getNotifications ($arrParameters, $pageLimit = 10, $page = 0, $strOrderBy = "ns.date_sent", $strOrderDir = "DESC")
    {

        $totalRows = $this->countNotifications ($arrParameters, $strOrderBy, $strOrderDir);

        $arrWhere = array();

        $query = "SELECT ns.*, s.step_name FROM workflow.`notifications_sent` ns
                    LEFT JOIN workflow.steps s ON s.step_id = ns.`step_id`
                    WHERE 1=1
                    ";

        if ( isset ($arrParameters['user']) && $arrParameters['user'] !== null )
        {
            $query .= " AND recipient = ?";
            $arrWhere[] = $arrParameters['user'];
        }

        if ( isset ($arrParameters['status']) && $arrParameters['status'] != null )
        {
            $query .= " AND status = ?";
            $arrWhere[] = $arrParameters['status'];
        }

        if ( isset ($arrParameters['is_important']) && $arrParameters['is_important'] !== null && $arrParameters['is_important'] != 0 )
        {
            $query .= " AND is_important = 1";
        }

        if ( isset ($arrParameters['has_read']) && $arrParameters['has_read'] !== null && $arrParameters['has_read'] != 0 )
        {
            $query .= " AND has_read = 1";
        }

        if ( isset ($arrParameters['id']) && $arrParameters['id'] !== null )
        {
            $query .= " AND id = ?";
            $arrWhere[] = $arrParameters['id'];
        }

        if ( isset ($arrParameters['parent_id']) && $arrParameters['parent_id'] !== null )
        {
            $query .= " AND parent_id = ?";
            $arrWhere[] = $arrParameters['parent_id'];
        }

        if ( isset ($arrParameters['searchText']) && !empty ($arrParameters['searchText']) )
        {
            $query .= " AND (recipient LIKE ? OR subject LIKE ?)";
            $arrWhere[] = "%" . $arrParameters['searchText'] . "%";
            $arrWhere[] = "%" . $arrParameters['searchText'] . "%";
        }

        $query .= " ORDER BY " . $strOrderBy . " " . $strOrderDir;

        ///////////////////////////////////////////////////////////////////////////////////////////////
        //
        //      Pagination
        //

        
        //all rows
        $_SESSION["pagination"]["total_counter"] = $totalRows;

        $current_page = $page;
        $startwith = $pageLimit * $page;
        $total_pages = $totalRows / $pageLimit;
        $_SESSION["pagination"]["current_page"] = $current_page;

        // calculating displaying pages
        $_SESSION["pagination"]["total_pages"] = (int) ($totalRows / $pageLimit);
        if ( fmod ($totalRows, $pageLimit) > 0 )
            $_SESSION["pagination"]["total_pages"] ++;

        $query .= " LIMIT " . $page . ", " . $pageLimit;

        $arrResults = $this->objMysql->_query ($query, $arrWhere);

//        $arrResults = $this->objMysql->_select ("workflow.notifications_sent", array(), $arrWhere, array("date_sent" => "DESC"));
        $arrAllMessages = array();

        foreach ($arrResults as $key => $arrResult) {

            $objNotifications = new Notifications();
            $objNotifications->setRecipient ($arrResult['recipient']);
            $objNotifications->setBody ($arrResult['message']);
            $objNotifications->setSubject ($arrResult['subject']);
            $objNotifications->setHasRead ($arrResult['has_read']);
            $objNotifications->setDateSent ($arrResult['date_sent']);
            $objNotifications->setId ($arrResult['id']);
            // $objNotifications->setStepData($arrResult['step_data']);
            $objNotifications->setStepName ($arrResult['step_name']);

            if ( !empty ($arrResult['case_id']) )
            {
                $objElements = new Elements ($arrResult['project_id'], $arrResult['case_id']);
                $arrAllMessages[$key]['project'] = $objElements;

                $arrAllMessages[$key]['notifications'] = $objNotifications;
            }
//            elseif ( !empty ($arrResult['project_id']) )
//            {
//                $objSave = new Save ($arrResult['project_id']);
//                $arrAllMessages[$key]['project'] = $objSave;
//            }
        }

        return $arrAllMessages;
    }

    /**
     * 
     * @param type $step
     * @return boolean
     */
    public function getTemplateForStep ($step)
    {
        $arrResult = $this->objMysql->_select ("auto_notifications", array(), array("triggering_status" => $step));

        if ( empty ($arrResult) )
        {
            return false;
        }

        return $arrResult;
    }

}
