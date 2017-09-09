<?php

class SendNotification
{

    private $arrEmailAddresses = array();
    private $taskId;
    private $subject;
    private $body;
    private $from;
    private $cc;
    private $bcc;
    private $status;
    private $system;
    private $to;
    private $sendToAll;
    private $fromName;
    private $template;

    public function getSystem ()
    {
        return $this->system;
    }

    public function setSystem ($system)
    {
        $this->system = $system;
    }

    public function getStatus ()
    {
        return $this->status;
    }

    public function setStatus ($status)
    {
        $this->status = $status;
    }
    
    public function setTemplate($template)
    {
        $this->template($template);
    }

    /**
     *
     * @param type $status
     * @param type $system
     */
    public function setVariables ($status)
    {
        $objNotification = new \BusinessModel\Notification();
        $arrResult = $objNotification->getEmailEventData ($status);

        if ( !isset ($arrResult[0]) || empty ($arrResult[0]) )
        {
            // default subject and body if none has been set by user
            $this->sendToAll = 1;
            $this->subject = '[WORKFLOW_NAME] has been moved to [STEP_NAME] by [USER]';
            $this->body = 'PROJECT <span style="font-weight: bold; text-decoration-line: underline;">DETAILS </span>id: [PROJECT_ID] project name: [PROJECT_NAME] status [PROJECT_STATUS]  Element Details Id: [ELEMENT_ID] Name: [ELEMENT_NAME] Status: [ELEMENT_STATUS] Step: [STEP_NAME]';
        }
        else
        {
            $this->fromName = trim ($arrResult[0]['from_name']) !== "" ? $arrResult[0]['from_name'] : '';
            $this->from = trim ($arrResult[0]['from_mail']) !== "" ? $arrResult[0]['from_mail'] : '';
            $this->cc = trim ($arrResult[0]['cc']) !== "" ? $arrResult[0]['cc'] : '';
            $this->bcc = trim ($arrResult[0]['bcc']) !== "" ? $arrResult[0]['bcc'] : '';
            $this->sendToAll = $arrResult[0]['send_to_all'];
            $this->body = $arrResult[0]['message_body'];
            $this->subject = $arrResult[0]['message_subject'];
            $this->to = trim ($arrResult[0]['to']) !== "" ? $arrResult[0]['to'] : '';
        }

        $this->setStatus ($status);
        $this->setSystem ("task_manager");
    }

    /**
     *
     * @param type $projectId
     */
    public function setProjectId ($projectId)
    {
        $this->projectId = (int) $projectId;
    }

    public function getArrEmailAddresses ()
    {
        return $this->arrEmailAddresses;
    }

    public function setArrEmailAddresses ($arrEmailAddresses)
    {
        $this->arrEmailAddresses = $arrEmailAddresses;
    }

    /**
     *
     * @param type $elementId
     */
    public function setElementId ($elementId)
    {
        $this->elementId = (int) $elementId;
    }

    public function getTaskUsers ()
    {
        $case = new \BusinessModel\Cases();
        $p = $case->getUsersParticipatedInCase ($this->projectId);

        if ( !empty ($p) )
        {
            $noteRecipientsList = [];

            foreach ($p as $userParticipated) {
                if ( $userParticipated != '' )
                {
                    $objUsers = new \BusinessModel\UsersFactory();
                    $arrUser = $objUsers->getUsers (array("filter" => "mike", "filterOption" => trim ($userParticipated)));

                    if ( isset ($arrUser['data'][0]) && !empty ($arrUser['data'][0]) )
                    {
                        $objUser = $arrUser['data'][0];
                    }

                    if ( is_object ($objUser) && get_class ($objUser) === "Users" )
                    {
                        $emailAddress = $objUser->getUser_email ();
                        $noteRecipientsList[] = $emailAddress;
                    }
                }
            }

            return $noteRecipientsList;
        }

        return false;
    }

    /**
     *
     * @param type $status
     * @param type $arrData
     * @param type $system
     * @return boolean
     */
    public function buildEmail (Task $objTask, Users $objUser, $system = "task_manager")
    {
        $htmlContent = '';

        try {
            if($system === "task_manager") {
                $this->setVariables ($objTask->getStepId (), $system);
            } else {
                $htmlContent = $this->emailActions($objTask, $system);
            }

            $this->taskId = $objTask->getTasUid ();

            $noteRecipientsList = array();
            
            if ( !empty ($this->arrEmailAddresses) )
            {
                $noteRecipientsList[] = $this->arrEmailAddresses;
            }

            if ( !in_array ($this->to, $noteRecipientsList) && trim ($this->to) !== '' )
            {
                $noteRecipientsList[] = $this->to;
            }

            $noteRecipients = implode (",", $noteRecipientsList);

            if ( (int) $this->sendToAll === 1 )
            {
                $objTask = (new Task())->retrieveByPk ($this->taskId);
                $participants = $this->getTo ($objTask);
            }

            $this->cc = isset ($participants['cc']) && trim ($participants['cc']) !== "" ? trim ($participants['cc']) . "," . $noteRecipients : $noteRecipients;

            if ( isset ($participants['to']) )
            {
                $this->recipient = $participants['to'];
            }
            else
            {
                //trigger_error ("NO RECIPIENTS FOUND FOR TASK NOTIFICATION " . $objTask->getTasUid ());
                $this->recipient = "bluetiger_uan@yahoo.com";
            }

            $objCases = new \BusinessModel\Cases();

            $Fields = $objCases->getCaseVariables ((int) $this->elementId, (int) $this->projectId, (int) $objTask->getStepId ());

            $this->subject = $objCases->replaceDataField ($this->subject, $Fields);
            
            if(trim($this->template) !== '') {
                if(file_exists($this->template)) {
                $body = file_get_contents($this->template);
                    
                if(trim($htmlContent) !== '') {
                    $body .= $htmlContent;
                }
                    
                $this->body = $objCases->replaceDataField ($this->body, $Fields);
                } else {
                }
            } else {
                $this->body = $objCases->replaceDataField ($this->body, $Fields);
                
                if(trim($htmlContent) !== '') {
                    $this->body .= $htmlContent;
                }
            }

            //	sending email notification
            $this->notificationEmail ($objUser);

            return true;
        } catch (Exception $ex) {
            throw $ex;
        }
    }
    
    private function emailActions($objTask, $type)
    {
        switch($type) {
            case "accept":
                
                break;
                
            case "reject":
                
                break;
                
            case "sendForm":
            case "sendFormLink":
                $dynaForm = new Form ($objTask);
                $arrayDynaFormData = $dynaForm->getFields ();
                 
                //Creating the first file
                //$weTitle = $this->sanitizeFilename ($arrayWebEntryData["WE_TITLE"]);
                //$fileName = $weTitle;
                $header = "<?php\n";
                $header .= "global \$_DBArray;\n";
                $header .= "if (!isset(\$_DBArray)) {\n";
                $header .= "  \$_DBArray = array();\n";
                $header .= "}\n";
                $header .= "\$_SESSION[\"PROCESS\"] = \"" . $processUid . "\";\n";
                $header .= "\$_SESSION[\"CURRENT_DYN_UID\"] = \"" . $objTask->getTasUid() . "\";\n";
                $header .= "?>";
                $header .= '<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.4/jquery.min.js"></script>';
                   
                //Creating the second file, the  post file who receive the post form.
                $pluginTpl = WEB_ENTRY_TEMPLATES;
                $objFormBuilder = new FormBuilder ("AddNewForm");
                $objFormBuilder->buildForm ($arrayDynaFormData);
                $html = $objFormBuilder->render ();
                $html .= '<input type="hidden" id="workflowid" name="workflowid" value="' . $processUid . '">';
                $html .= '<input type="hidden" id="stepId" name="stepId" value="' . $dynaFormUid . '">';
                $fileTemplate = file_get_contents ($pluginTpl);
                $fileTemplate = str_replace ("<!-- CONTENT -->", $html, $fileTemplate);
                $fileContent = $header . $fileTemplate;
                  
                if($type === 'sendFormLink') {
                    file_put_contents ($pathDataPublicProcess . PATH_SEP . $fileName . ".php", $fileContent);
                      return "<a href='".$pathDataPublicProcess . PATH_SEP . $fileName . ".php'>link</a>";
                } else {
                    return $fileContent;
                }
    
           break;
        }
    }

    private function getEmailConfiguration ()
    {
        $emailServer = new \BusinessModel\EmailServer();

        if ( trim ($this->from) !== '' )
        {
            $record = $emailServer->getRecordByName ($this->from);
            $arrayEmailServerDefault = $record[0];
        }
        else
        {
            $arrayEmailServerDefault = $emailServer->getEmailServerDefault ();
        }

        if ( count ($arrayEmailServerDefault) > 0 )
        {
            $arrayDataEmailServerConfig = array(
                "MESS_ENGINE" => $arrayEmailServerDefault["MESS_ENGINE"],
                "MESS_SERVER" => $arrayEmailServerDefault["MESS_SERVER"],
                "MESS_PORT" => (int) ($arrayEmailServerDefault["MESS_PORT"]),
                "MESS_RAUTH" => (int) ($arrayEmailServerDefault["MESS_RAUTH"]),
                "MESS_ACCOUNT" => $arrayEmailServerDefault["MESS_ACCOUNT"],
                "MESS_PASSWORD" => $arrayEmailServerDefault["MESS_PASSWORD"],
                "MESS_FROM_MAIL" => $arrayEmailServerDefault["MESS_FROM_MAIL"],
                "MESS_FROM_NAME" => $arrayEmailServerDefault["MESS_FROM_NAME"],
                "SMTPSecure" => $arrayEmailServerDefault["SMTPSECURE"],
                "MESS_TRY_SEND_INMEDIATLY" => isset ($arrayEmailServerDefault["MESS_TRY_SEND_INMEDIATLY"]) ? (int) ($arrayEmailServerDefault["MESS_TRY_SEND_INMEDIATLY"]) : 1,
                "MAIL_TO" => isset ($arrayEmailServerDefault["MAIL_TO"]) ? $arrayEmailServerDefault["MAIL_TO"] : 'bluetiger_uan@yahoo.com',
                "MESS_DEFAULT" => (int) ($arrayEmailServerDefault["MESS_DEFAULT"]),
                "MESS_ENABLED" => 1,
                "MESS_BACKGROUND" => "",
                "MESS_PASSWORD_HIDDEN" => "",
                "MESS_EXECUTE_EVERY" => "",
                "MESS_SEND_MAX" => ""
            );

            //Return
            return $arrayDataEmailServerConfig;
        }
    }

    private function getTo (Task $objTask)
    {
        $arrayResp = array();

        $group = new TeamFunctions ();

        $oUser = new Users ();

        /**
         * If task is a self service task we only send to users whi have participated in the case which has been done previously (to already populated)
         * else we get the assigned task users and put them into the array if they arent already there
         */
        $taskType = trim ($objTask->getTasSelfserviceTimeUnit ()) !== "" ? 'SELF-SERVICE' : 'NORMAL';
        $blParallel = in_array ($objTask->getTasAssignType (), array("MULTIPLE_INSTANCE", "MULTIPLE_INSTANCE_VALUE_BASED")) ? true : false;

        if ( $taskType === "SELF-SERVICE" )
        {
            if ( trim ($objTask->getTasUid ()) === "" )
            {

                $arrayTaskUser = array();

                $arrayAux1 = $objTask->getGroupsOfTask ($objTask->getTasUid (), 1);

                $arrDone = [];

                foreach ($arrayAux1 as $arrayGroup) {

                    if ( !in_array ($arrayGroup['team_id'], $arrDone) )
                    {
                        $arrayAux2 = $group->getUsersOfGroup ($arrayGroup ["team_id"]);

                        foreach ($arrayAux2 as $arrayUser) {

                            $arrayTaskUser [] = $arrayUser ["usrid"];
                        }
                    }

                    $arrDone[] = $arrayGroup['team_id'];
                }

                $arrayAux1 = $objTask->getUsersOfTask ($objTask->getTasUid (), 1);

                foreach ($arrayAux1 as $arrayUser) {

                    $arrayTaskUser [] = $arrayUser ["USR_UID"];
                }

                $objMysql = new Mysql2();

                $results2 = $objMysql->_query ("SELECT usrid, username, firstName, lastName, user_email FROM user_management.poms_users WHERE usrid IN(" . implode (",", $arrayTaskUser) . ")");

                $to = null;

                $cc = null;

                $sw = 1;

                foreach ($results2 as $row) {

                    $toAux = ((($row ["firstName"] != "") || ($row ["lastName"] != "")) ? $row ["firstName"] . " " . $row ["lastName"] . " " : "") . "<" . $row ["user_email"] . ">";

                    if ( $sw == 1 )
                    {

                        $to = $toAux;

                        $sw = 0;
                    }
                    else
                    {

                        $cc = $cc . (($cc != null) ? "," : null) . $toAux;
                    }
                }

                $arrayResp ['to'] = $to;

                $arrayResp ['cc'] = $cc;
            }
        }
        elseif ( $blParallel === true )
        {
            $to = null;

            $cc = null;

            $sw = 1;

            $oDerivation = new WorkflowStep ();

            $userFields = $oDerivation->getUsersFullNameFromArray ($oDerivation->getAllUsersFromAnyTask ($objTask->getTasUid ()));

            if ( isset ($userFields) )
            {

                foreach ($userFields as $row) {

                    $toAux = ((($row ["USR_FIRSTNAME"] != "") || ($row ["USR_LASTNAME"] != "")) ? $row ["USR_FIRSTNAME"] . " " . $row ["USR_LASTNAME"] . " " : "") . "<" . $row ["USR_EMAIL"] . ">";

                    if ( $sw == 1 )
                    {

                        $to = $toAux;

                        $sw = 0;
                    }
                    else
                    {

                        $cc = $cc . (($cc != null) ? "," : null) . $toAux;
                    }
                }

                $arrayResp ['to'] = $to;
                $arrayResp ['cc'] = $cc;
            }
        }
        else
        {
            $oDerivation = new WorkflowStep ();

            $taskUsers = $oDerivation->getUsersFullNameFromArray ($oDerivation->getAllUsersFromAnyTask ($objTask->getTasUid ()));

            $sw = 1;
            $to = null;
            $cc = null;

            foreach ($taskUsers as $row) {

                $toAux = ((($row ["USR_FIRSTNAME"] != "") || ($row ["USR_LASTNAME"] != "")) ? $row ["USR_FIRSTNAME"] . " " . $row ["USR_LASTNAME"] . " " : "") . "<" . $row ["USR_EMAIL"] . ">";

                if ( $sw == 1 )
                {

                    $to = $toAux;

                    $sw = 0;
                }
                else
                {

                    $cc = $cc . (($cc != null) ? "," : null) . $toAux;
                }
            }

            $arrayResp ['to'] = $to;
            $arrayResp ['cc'] = $cc;


            // $arrayResp ['to'] = 
        }


        return $arrayResp;
    }

    /**
     *
     * @param type $sendto
     * @param type $message_subject
     * @param type $message_body
     */
    public function notificationEmail (Users $objUser)
    {
        $aConfiguration = $this->getEmailConfiguration ();
        $oSpool = new EmailFunctions();

        if ( trim ($this->fromName) !== "" && trim ($this->from) !== "" )
        {
            $from = $this->fromName . ($this->from != "" ? " <" . $this->from . ">" : "");
        }
        else
        {
            $user = (new \BusinessModel\UsersFactory())->getUser ($objUser->getUserId ());
            $from = $user->getFirstName () . " " . $user->getLastName () . ($user->getUser_email () != "" ? " <" . $user->getUser_email () . ">" : "");
        }

        $from = (new BusinessModel\EmailServer())->buildFrom ($aConfiguration, $from);

        $msgError = "";

        if ( !isset ($aConfiguration['MESS_ENABLED']) || $aConfiguration['MESS_ENABLED'] != '1' )
        {

            $msgError = "The default configuration wasn't defined";

            $aConfiguration['MESS_ENGINE'] = '';
        }

        $aConfiguration['CASE_UID'] = $this->elementId;
        $aConfiguration['APP_UID'] = $this->projectId;

        $dataLastEmail['msgError'] = $msgError;
        $dataLastEmail['configuration'] = $aConfiguration;
        $dataLastEmail['subject'] = $this->subject;
        $dataLastEmail['pathEmail'] = '';
        $dataLastEmail['swtplDefault'] = 0;
        $dataLastEmail['body'] = $this->body;
        $dataLastEmail['from'] = $from;

        if ( trim ($this->recipient) !== "" )
        {
            $oSpool->setConfig ($dataLastEmail['configuration']);
            $oSpool->create (array(
                "msg_uid" => "",
                "case_id" => $this->elementId,
                'app_uid' => $this->projectId,
                'del_index' => $this->status,
                "app_msg_type" => "DERIVATION",
                "app_msg_subject" => $this->subject,
                'app_msg_from' => $from,
                "app_msg_to" => $this->recipient,
                'app_msg_body' => $this->body,
                "app_msg_cc" => $this->cc,
                "app_msg_bcc" => $this->bcc,
                "app_msg_attach" => "",
                "app_msg_template" => "",
                "app_msg_status" => "pending",
                "app_msg_error" => $dataLastEmail['msgError']
            ));
            if ( $dataLastEmail['msgError'] == '' )
            {
                if ( ($dataLastEmail['configuration']["MESS_BACKGROUND"] == "") ||
                        ($dataLastEmail['configuration']["MESS_TRY_SEND_INMEDIATLY"] == "1")
                )
                {
                    try {
                        $oSpool->sendMail ();
                    } catch (Exception $ex) {
                        //trigger_error ($ex, E_USER_WARNING);
                    }
                }
            }
        }
    }

}
