<?php

class BaseProcessUser
{

    private $objMysql;
    private $pu_type;
    private $usr_uid;
    private $pro_uid;
    private $pu_id;
    private $validationFailures;
    private $arrayFieldDefinition = array(
        "PRO_UID" => array("type" => "int", "required" => true, "empty" => false, "accessor" => "getPro_uid", "mutator" => "setPro_uid"),
        "USR_UID" => array("type" => "int", "required" => true, "empty" => false, "accessor" => "getUsr_uid", "mutator" => "setUsr_uid"),
        "PU_TYPE" => array("type" => "string", "required" => true, "empty" => true, "accessor" => "getPu_type", "mutator" => "setPu_type")
    );

    public function __construct ()
    {
        $this->objMysql = new Mysql2();
    }

    public function loadObject ($arrUser)
    {
        foreach ($arrUser as $formField => $formValue) {

            if ( isset ($this->arrayFieldDefinition[$formField]) )
            {
                $mutator = $this->arrayFieldDefinition[$formField]['mutator'];

                if ( method_exists ($this, $mutator) && is_callable (array($this, $mutator)) )
                {
                    if ( isset ($this->arrayFieldDefinition[$formField]) && trim ($formValue) != "" )
                    {
                        call_user_func (array($this, $mutator), $formValue);
                    }
                }
            }
        }

        return true;
    }

    public function getPu_type ()
    {
        return $this->pu_type;
    }

    public function getUsr_uid ()
    {
        return $this->usr_uid;
    }

    public function getPro_uid ()
    {
        return $this->pro_uid;
    }

    public function setPu_type ($pu_type)
    {
        $this->pu_type = $pu_type;
    }

    public function setUsr_uid ($usr_uid)
    {
        $this->usr_uid = $usr_uid;
    }

    public function setPro_uid ($pro_uid)
    {
        $this->pro_uid = $pro_uid;
    }

    public function getValidationFailures ()
    {
        return $this->validationFailures;
    }

    public function setValidationFailures ($validationFailures)
    {
        $this->validationFailures = $validationFailures;
    }
    
    public function getPu_id ()
    {
        return $this->pu_id;
    }

    public function setPu_id ($pu_id)
    {
        $this->pu_id = $pu_id;
    }

    public function validate ()
    {
        $intErrorCount = 0;

        if ( empty ($this->pro_uid) )
        {
            $this->validationFailures[] = "PROCESS ID MISSING";
            $intErrorCount++;
        }

        if ( empty ($this->usr_uid) )
        {
            $this->validationFailures[] = "USER ID MISSING";
            $intErrorCount++;
        }

        if ( empty ($this->pu_type) )
        {
            $this->validationFailures[] = "PU TYPE MISSING";
            $intErrorCount++;
        }

        if ( $intErrorCount > 0 )
        {
            return false;
        }

        return true;
    }

    public function delete ()
    {
        $this->objMysql->_delete ("workflow.process_supervisors", array("id" => $this->pu_id));

        return true;
    }

    public function save ()
    {
        $this->objMysql->_insert ("workflow.process_supervisors", array(
            "workflow_id" => $this->pro_uid,
            "user_id" => $this->usr_uid,
            "pu_type" => $this->pu_type
                )
        );
        
        return true;
    }

}
