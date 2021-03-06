<?php

abstract class BaseVariable
{

    private $variableName;
    private $fieldId;
    private $validationType;
    private $objMysql;
    private $VarDbconnection;
    private $VarSql;
    private $id;
    private $validationErrors = array();
    private $rowExists;

    /**
     * 
     * @param type $fieldId
     */
    public function __construct ($fieldId)
    {
        $this->fieldId = $fieldId;
        $this->objMysql = new Mysql2();
    }

    /**
     * @return mixed
     */
    public function getVariableName ()
    {
        return $this->variableName;
    }

    /**
     * @param mixed $variableName
     */
    public function setVariableName ($variableName)
    {
        $this->variableName = $variableName;
    }

    /**
     * @return mixed
     */
    public function getFieldId ()
    {
        return $this->fieldId;
    }

    /**
     * @param mixed $fieldId
     */
    public function setFieldId ($fieldId)
    {
        $this->fieldId = $fieldId;
    }

    /**
     * @return mixed
     */
    public function getValidationType ()
    {
        return $this->validationType;
    }

    /**
     * @param mixed $validationType
     */
    public function setValidationType ($validationType)
    {
        $this->validationType = $validationType;
    }

    /**
     * 
     * @return type
     */
    public function getVarDbconnection ()
    {
        return $this->VarDbconnection;
    }

    /**
     * 
     * @param type $VarDbconnection
     */
    public function setVarDbconnection ($VarDbconnection)
    {
        $this->VarDbconnection = $VarDbconnection;
    }

    /**
     * 
     * @return type
     */
    public function getVarSql ()
    {
        return $this->VarSql;
    }

    /**
     * 
     * @param type $VarSql
     */
    public function setVarSql ($VarSql)
    {
        $this->VarSql = $VarSql;
    }

    /**
     * 
     * @return type
     */
    public function getId ()
    {
        return $this->id;
    }

    /**
     * 
     * @param type $id
     */
    public function setId ($id)
    {
        $this->id = $id;
    }

    public function getRowExists ()
    {
        return $this->rowExists;
    }

    public function setRowExists ($rowExists)
    {
        $this->rowExists = $rowExists;
    }

    /**
     * 
     */
    public function save ()
    {
        if ( !$this->rowExists )
        {
            $this->objMysql->_insert ("workflow.workflow_variables", array(
                "variable_name" => $this->variableName,
                "validation_type" => $this->validationType,
                "variation_sql" => $this->VarSql,
                "db_connection" => $this->VarDbconnection,
                "field_id" => $this->fieldId
                    )
            );
        }
        else
        {
            $this->objMysql->_update (
                    "workflow.workflow_variables", array(
                "variable_name" => $this->variableName,
                "validation_type" => $this->validationType,
                "variation_sql" => $this->VarSql,
                "db_connection" => $this->VarDbconnection,
                    ), array(
                "field_id" => $this->fieldId
                    )
            );
        }
    }

    public function getValidationErrors ()
    {
        return $this->validationErrors;
    }

    /**
     * 
     * @param type $validationErrors
     */
    public function setValidationErrors ($validationErrors)
    {
        $this->validationErrors = $validationErrors;
    }

    /**
     * 
     * @return boolean
     */
    public function validate ()
    {

        $errorCounter = 0;

        if ( trim ($this->fieldId) == "" )
        {
            $this->validationErrors[] = "FIELD ID IS MISSING";
            $errorCounter++;
            //$this->
        }


        if ( trim ($this->variableName) == "" )
        {
            $this->validationErrors[] = "VARIABLE NAME IS MISSING";
            $errorCounter++;
        }

        if ( preg_match ('#^[a-zA-Z0-9_]{4,10}$D#', $this->variableName) )
        {
            $this->validationErrors[] = "INCORRECT FORMAT FOR VARIABLE NAME";
            $errorCounter++;
        }

        if ( trim ($this->validationType) == "" )
        {
            $this->validationErrors[] = "VALIDATION TYPE IS MISSING";
            $errorCounter++;
        }

        if ( $errorCounter > 0 )
        {
            return false;
        }

        return true;
    }

}
