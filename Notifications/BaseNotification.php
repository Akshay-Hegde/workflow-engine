<?php

abstract class BaseNotification implements Persistent
{

    protected $status;
    protected $system;
    protected $message;
    protected $subject;
    protected $body;
    protected $objMysql;
    protected $recipient;
    protected $triggeringStatus;
    protected $id;
    protected $cc;
    protected $bcc;
    protected $from;
    protected $sendToAll;
    protected $defaultFrom = "bluetiger_uan@yahoo.com";
    protected $fromName;
    private $arrFieldMapping = array(
        "notificationId" => array("accessor" => "getId", "mutator" => "setId"),
        "step" => array("accessor" => "getTriggeringStatus", "mutator" => "setTriggeringStatus"),
        "subject" => array("accessor" => "getSubject", "mutator" => "setSubject"),
        "recipient" => array("accessor" => "getRecipient", "mutator" => "setRecipient"),
        "body" => array("accessor" => "getBody", "mutator" => "setBody"),
        "sentByUser" => array("accessor" => "getSentByUser", "mutator" => "setSentByUser"),
        "parentId" => array("accessor" => "getParentId", "mutator" => "setParentId"),
        "step_data" => array("accessor" => "getStepData", "mutator" => "setStepData"),
        "step_name" => array("accessor" => "getStepName", "mutator" => "setStepName"),
        "CC" => array("accessor" => "getCc", "mutator" => "setCc"),
        "BCC" => array("accessor" => "getBcc", "mutator" => "setBcc"),
        "from" => array("accessor" => "getFrom", "mutator" => "setFrom"),
        "fromName" => array("accessor" => "getFromName", "mutator" => "setFromName"),
        "sendToAll" => array("accessor" => "getSendToAll", "mutator" => "setSendToAll")
    );
    public $arrNotificationData = array();
    public $arrMessages = array();

    public function __construct ()
    {
        $this->objMysql = new Mysql2();
    }

    /**
     * 
     * @param type $arrNotification
     * @return boolean
     */
    public function loadObject (array $arrData)
    {
        foreach ($arrData as $formField => $formValue) {

            if ( isset ($this->arrFieldMapping[$formField]) )
            {
                $mutator = $this->arrFieldMapping[$formField]['mutator'];

                if ( method_exists ($this, $mutator) && is_callable (array($this, $mutator)) )
                {
                    if ( isset ($this->arrFieldMapping[$formField]) && trim ($formValue) != "" )
                    {
                        call_user_func (array($this, $mutator), $formValue);
                    }
                }
            }
        }

        return true;
    }

    public function validate ()
    {
        ;
    }

    /**
     * 
     * @param type $status
     */
    public function setStatus ($status)
    {
        $this->status = $status;
    }

    /**
     * 
     * @param type $system
     */
    public function setSystem ($system)
    {
        $this->system = $system;
    }

    /**
     * 
     * @param type $message
     */
    public function logInfo2 ($message)
    {
        $file = $_SERVER['DOCUMENT_ROOT'] . "/FormBuilder/app/logs/mail.log";

        $date = date ("Y-m-d h:m:s");
        $level = "info";

        $message = "[" . $date . "] [" . $level . "] " . $message . "]";

        $message .= file_get_contents ($file);

        // log to our default location
        file_put_contents ($file, $message);
    }

    /**
     * DEPRECATED
     * @return boolean
     */
    /*public function setMessage ()
    {
        $objMysql = new Mysql2();
        $arrResult = $objMysql->_select ("auto_notifications", array(), array("triggering_status" => $this->status, "system" => $this->system));

        if ( !isset ($arrResult[0]) || empty ($arrResult[0]) )
        {
            return false;
        }

        $this->message = $arrResult[0];

        $this->fromName = trim ($arrResult[0]['from_name']) !== "" ? $arrResult[0]['from_name'] : '';
        $this->from = trim ($arrResult[0]['from_mail']) !== "" ? $arrResult[0]['from_mail'] : '';
        $this->cc = trim ($arrResult[0]['cc']) !== "" ? $arrResult[0]['cc'] : '';
        $this->bcc = trim ($arrResult[0]['bcc']) !== "" ? $arrResult[0]['bcc'] : '';
        $this->sendToAll = $arrResult[0]['send_to_all'];
    }*/

    public function getRecipient ()
    {
        return $this->recipient;
    }

    public function getMessage ()
    {
        return $this->message;
    }

    public function getSubject ()
    {
        return $this->subject;
    }

    public function getBody ()
    {
        return $this->body;
    }

    public function getTriggeringStatus ()
    {
        return $this->triggeringStatus;
    }

    public function getId ()
    {
        return $this->id;
    }

    /**
     * 
     * @param type $triggeringStatus
     */
    public function setTriggeringStatus ($triggeringStatus)
    {
        $this->triggeringStatus = $triggeringStatus;
    }

    /**
     * 
     * @param type $id
     */
    public function setId ($id)
    {
        $this->id = $id;
    }

    /**
     * 
     * @param type $subject
     */
    public function setSubject ($subject)
    {
        $this->subject = $subject;
    }

    /**
     * 
     * @param type $body
     */
    public function setBody ($body)
    {
        $this->body = $body;
    }

    /**
     * 
     * @param type $recipient
     */
    public function setRecipient ($recipient)
    {
        $this->recipient = $recipient;
    }
    
    public function getCc ()
    {
        return $this->cc;
    }

    public function getBcc ()
    {
        return $this->bcc;
    }

    public function getFrom ()
    {
        return $this->from;
    }

    public function getSendToAll ()
    {
        return $this->sendToAll;
    }

    public function getFromName ()
    {
        return $this->fromName;
    }

    public function setCc ($cc)
    {
        $this->cc = $cc;
    }

    public function setBcc ($bcc)
    {
        $this->bcc = $bcc;
    }

    public function setFrom ($from)
    {
        $this->from = $from;
    }

    public function setSendToAll ($sendToAll)
    {
        $this->sendToAll = $sendToAll;
    }

    public function setFromName ($fromName)
    {
        $this->fromName = $fromName;
    }

    /**
     * 
     */
    public function save ()
    {
        if ( isset ($this->id) && is_numeric ($this->id) )
        {
            $this->objMysql->_update ("task_manager.auto_notifications", [
                "system" => "task_manager",
                "message_subject" => $this->subject,
                "message_body" => $this->body,
                "to" => $this->recipient,
                "from_name" => $this->fromName,
                "from_mail" => $this->from,
                "cc" => $this->cc,
                "bcc" => $this->bcc,
                "send_to_all" => $this->sendToAll
                    ], array(
                "id" => $this->id
                    )
            );
        }
        else
        {
            $this->objMysql->_insert ("task_manager.auto_notifications", [
                "triggering_status" => $this->triggeringStatus,
                "`system`" => "task_manager",
                "message_subject" => $this->subject,
                "message_body" => $this->body,
                "`to`" => $this->recipient,
                "from_name" => $this->fromName,
                "from_mail" => $this->from,
                "cc" => $this->cc,
                "bcc" => $this->bcc,
                "send_to_all" => $this->sendToAll
            ]);
        }
    }

    /**
     * 
     * @param type $arrUpdate
     * @param type $id
     */
    public function update ($arrUpdate, $id)
    {
        $this->objMysql->_update ("workflow.APP_MESSAGE", $arrUpdate, array("APP_MSG_UID" => $id));
    }

    /*public function saveNewMessage ()
    {
        $this->arrMessages['date_sent'] = date ("Y-m-d H:i:s");
        $this->arrMessages['status'] = 1;
        $this->objMysql->_insert ("workflow.notifications_sent", $this->arrMessages);
    }*/

    /**
     * DEPRECATED
     */
    /*public function sendMessage ()
    {        
        $this->objMysql->_query ("UPDATE workflow.notifications_sent
                                SET status = 2
                                WHERE case_id = ?
                                AND project_id = ?
                                AND status != 3", [$this->elementId, $this->projectId]
        );

        $id = $this->objMysql->_insert (
                "workflow.notifications_sent", array(
            "subject" => $this->subject,
            "message" => $this->body,
            "recipient" => $this->recipient,
            "date_sent" => date ("Y-m-d H:i:s"),
            "project_id" => $this->projectId,
            "case_id" => $this->elementId,
            "status" => 1,
            "step_id" => $this->status
                )
        );

        return $id;
    }*/

}
