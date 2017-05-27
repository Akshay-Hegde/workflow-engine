<?php

class FormBuilder
{

    protected $_elements = array();
    protected $_prefix = "http";
    protected $_values = array();
    protected $_attributes = array();
    protected $html;
    protected $label;
    protected $key;
    private $attachmentHtml;
    private $documentHTML;
    private $arrUploadedFiles = array();

    public function __construct ($id = "BaseData")
    {

        $this->configure (array(
            "action" => basename ($_SERVER["SCRIPT_NAME"]),
            "id" => preg_replace ("/\W/", "-", $id),
            "method" => "post"
        ));

        $this->setForm ();
    }

    public function buildForm ($arrFormFields)
    {
        if ( !empty ($arrFormFields) )
        {
            foreach ($arrFormFields as $objFormField) {

                if ( !$objFormField instanceof StepField )
                {
                    throw new Exception ("Invalid field format");
                }

                $this->addElement (
                        array(
                            "type" => $objFormField->getFieldType (),
                            "label" => $objFormField->getLabel (),
                            "name" => $objFormField->getFieldName (),
                            "id" => $objFormField->getFieldId (),
                            "options" => $objFormField->getOptions (),
                            "field_conditions" => $objFormField->getFieldConditions (),
                            "custom_javascript" => $objFormField->getCustomJavascript (),
                            "value" => $objFormField->getValue (),
                            "is_disabled" => $objFormField->getIsDisabled ()
                        )
                );
            }
        }
        else
        {
            throw new Exception ("No form fields were given");
        }
    }

    public function buildDocHTML ($arrDocs)
    {
        $this->documentHTML = '<div class="col-lg-12 pull-left m-t-sm m-b-sm" style="border: 1px dotted #CCC"></div>';

        $this->documentHTML .= '<div class="form-group">
            <label class="col-lg-2 control-label">Document Type</label>
           <div class="col-lg-10">';



        $this->documentHTML .= '<select class="form-control" name="document_type" id="document_type">';

        foreach ($arrDocs as $objDocument) {

            if ( !$objDocument instanceof InputDocument )
            {
                throw new Exception ("Invalid document format given.");
            }

            $this->documentHTML .= '<option value="' . $objDocument->getId () . '">' . $objDocument->getTitle () . '</option>';
        }

        $this->documentHTML .= '</select>';

        $this->documentHTML .= '</div></div>';
    }

    public function buildAttachments ($arrAttachments)
    {
        
        $this->attachmentHtml = '<div class="col-lg-12 pull-left">';
        foreach ($arrAttachments as $objAttachment) {

            if ( !$objAttachment instanceof ProcessFiles )
            {
                throw new Exception ("Invalid attachment format given.");
            }

            if ( !in_array ($objAttachment->getId (), $this->arrUploadedFiles) )
            {
                
                $this->attachmentHtml .= '<div class="file-box">
                                        <div class="file">
                                            <a href="/attachments/download/' . $objAttachment->getPrfPath () . '">
                                                <div class="icon">
                                                    <i class="fa fa-file"></i>
                                                </div>
                                                <div class="file-name">
                                                   ' . $objAttachment->getPrfFielname () . '
                                                    <br>
                                                    <small>Added:' . $objAttachment->getUsrUid () . ' <br> ' . date ("M d, Y", strtotime ($objAttachment->getPrfCreateDate ())) . '</small>
                                                </div>
                                            </a>
                                        </div>
                                    </div>';
            }




            $this->arrUploadedFiles[] = $objAttachment->getId ();
        }

        $this->attachmentHtml .= '</div>';
    }

    private function configure (array $properties = null)
    {
        if ( !empty ($properties) )
        {
            foreach ($properties as $property => $value) {
                $property = strtolower ($property);

                if ( $property[0] != "_" )
                {
                    /* If the appropriate class has a "set" method for the property provided, then
                      it is called instead or setting the property directly. */
                    if ( isset ($method_reference["set" . $property]) )
                        $this->$method_reference["set" . $property] ($value);
                    elseif ( isset ($property_reference[$property]) )
                        $this->$property_reference[$property] = $value;

                    /* Entries that don't match an available class property are stored in the attributes
                      property if applicable.  Typically, these entries will be element attributes such as
                      class, value, onkeyup, etc. */
                    else
                        $this->setAttribute ($property, $value);
                }
            }
        }

        return $this;
    }

    private function setForm ()
    {
        $this->html = '<form class="form-horizontal" enctype="multipart/form-data" id="' . $this->_attributes['id'] . '">';
    }

    private function setAttribute ($attribute, $value)
    {
        if ( isset ($this->_attributes) )
            $this->_attributes[$attribute] = $value;
    }

    private function getAttribute ($attribute)
    {
        $value = "";
        if ( isset ($this->_attributes[$attribute]) )
            $value = $this->_attributes[$attribute];

        return $value;
    }

    public function addElement ($arrInput)
    {
        $this->_elements[count ($this->_elements) + 1] = $arrInput;
    }

    private function buildLabel ()
    {
        $this->html .= '<label class="col-lg-2 control-label">' . $this->label . '</label>';
        $this->html .= '<label style="display:none;" id="' . $this->_elements[$this->key]['id'] . '-error" class="error" for="' . $this->_elements[$this->key]['id'] . '">This field is required.</label>';
    }

    private function buildButton ()
    {
        $this->html .= '<button id="' . $this->_elements[$this->key]['id'] . '" type="button" class="' . $this->_elements[$this->key]['field_class'] . '">' . $this->_elements[$this->key]['default_value'] . '</button>';
    }

    private function buildCheckbox ()
    {
        $fieldClass = isset ($this->_elements[$this->key]['field_class']) && !empty ($this->_elements[$this->key]['field_class']) && $this->_elements[$this->key]['field_class'] != "form-control" ? $this->_elements[$this->key]['field_class'] : '';
        $disabled = isset ($this->_elements[$this->key]['is_disabled']) && $this->_elements[$this->key]['is_disabled'] == 1 ? 'disabled="disabled"' : '';

        $this->html .= '<input ' . $disabled . ' name="' . $this->_elements[$this->key]['name'] . '" id="' . $this->_elements[$this->key]['id'] . '" class="form-control ' . $fieldClass . '" type="checkbox" value="">';
    }

    private function buildDateField ()
    {
        $placeholder = isset ($this->_elements[$this->key]['placeholder']) && !empty ($this->_elements[$this->key]['placeholder']) ? $this->_elements[$this->key]['placeholder'] : '';
        $fieldClass = isset ($this->_elements[$this->key]['field_class']) && !empty ($this->_elements[$this->key]['field_class']) && $this->_elements[$this->key]['field_class'] != "form-control" ? $this->_elements[$this->key]['field_class'] : '';
        $disabled = isset ($this->_elements[$this->key]['is_disabled']) && $this->_elements[$this->key]['is_disabled'] == 1 ? 'disabled="disabled"' : '';

        $this->html .= '<div class="col-lg-10">
            <input placeholder="' . $placeholder . '" ' . $disabled . ' id = "' . $this->_elements[$this->key]['id'] . '" name="' . $this->_elements[$this->key]['name'] . '" type="text" placeholder="' . $this->label . '" class="form-control ' . $fieldClass . '" value="' . (isset ($this->_elements[$this->key]['value']) && !empty ($this->_elements[$this->key]['value']) ? date ("m-d-Y", strtotime ($this->_elements[$this->key]['value'])) : '') . '"> 
        </div>';

        $this->html .= '<div id="' . $this->_elements[$this->key]['id'] . 'Warning" class="formValidation">
            ' . $this->_elements[$this->key]['id'] . ' cannot be blank.
        </div>';
    }

    private function buildParagraph ()
    {
        $this->html .= '<p>' . $this->label . '</p>';
    }

    private function buildJavascript ()
    {
        if ( isset ($this->_elements[$this->key]['custom_javascript']) && !empty ($this->_elements[$this->key]['custom_javascript']) )
        {
            $this->html .= $this->_elements[$this->key]['custom_javascript'];
        }

        if ( isset ($this->_elements[$this->key]['field_conditions']) && !empty ($this->_elements[$this->key]['field_conditions']) )
        {
            $arrFieldConditions = json_decode ($this->_elements[$this->key]['field_conditions'], true);

            reset ($arrFieldConditions);
            $first_key = key ($arrFieldConditions);

            if ( $first_key == "displayField" )
            {

                $event = explode ("-", $arrFieldConditions['displayField']['event'])[1];

                $this->html .= '<script>
                    $("#' . $this->_elements[$this->key]['id'] . '").on("' . $event . '", function () {';

                if ( $arrFieldConditions['displayField']['action'] == "hide" )
                {
                    $this->html .= '$("#' . $arrFieldConditions['displayField']['field'] . '").hide();';
                    $this->html .= '$("#' . $arrFieldConditions['displayField']['field'] . '").parent().parent().find("label").hide();';
                }
                elseif ( $arrFieldConditions['displayField']['action'] == "remove" )
                {
                    $this->html .= '$("#' . $arrFieldConditions['displayField']['field'] . '").remove();';
                    $this->html .= '$("#' . $arrFieldConditions['displayField']['field'] . '").parent().parent().find("label").remove();';
                }
                else
                {
                    $this->html .= '$("#' . $arrFieldConditions['displayField']['field'] . '").show()';
                }

                $this->html .= '});  
                    </script>';
            }
        }
    }

    public function render ()
    {
        $this->setForm ();

        foreach ($this->_elements as $key => $arrElement) {

            $this->html .= '<div class="form-group">';
            $this->label = $arrElement['label'];
            $this->key = $key;
            $this->buildLabel ();

            switch ($arrElement['type']) {
                case "text":
                    $this->buildTextField ();
                    break;

                case "select":
                    $this->buildSelect ();
                    break;

                case "textarea":
                    $this->buildTextarea ();
                    break;

                case "file":
                    $this->buildFileInput ();
                    break;
                case "button":
                    $this->buildButton ();
                    break;

                case "checkbox":
                    $this->buildCheckbox ();
                    break;

                case "date":
                    $this->buildDateField ();
                    break;

                case "paragraph":
                    $this->buildParagraph ();
                    break;
            }

            $this->html .= '</div>';

          

            //$this->buildJavascript ();
        }
        
          if ( trim ($this->attachmentHtml) !== "" )
            {
                $this->html .= $this->attachmentHtml;
            }

        return $this->html;
    }

    public function _setForm (Form $form)
    {
        $this->_form = $form;
    }

    public function buildTextField ()
    {
        $disabled = isset ($this->_elements[$this->key]['is_disabled']) && $this->_elements[$this->key]['is_disabled'] == 1 ? 'disabled="disabled"' : '';
        $placeholder = isset ($this->_elements[$this->key]['placeholder']) && !empty ($this->_elements[$this->key]['placeholder']) ? $this->_elements[$this->key]['placeholder'] : '';
        $fieldClass = isset ($this->_elements[$this->key]['field_class']) && !empty ($this->_elements[$this->key]['field_class']) && $this->_elements[$this->key]['field_class'] != "form-control" ? $this->_elements[$this->key]['field_class'] : '';
        $maxLength = isset ($this->_elements[$this->key]['max_length']) && !empty ($this->_elements[$this->key]['max_length']) ? $this->_elements[$this->key]['max_length'] : '';

        $this->html .= '<div class="col-lg-10">
            <input maxlength="' . $maxLength . '" placeholder="' . $placeholder . '" ' . $disabled . ' id = "' . $this->_elements[$this->key]['id'] . '" name="' . $this->_elements[$this->key]['name'] . '" type="text" placeholder="' . $this->label . '" class="form-control ' . $fieldClass . '" value="' . (isset ($this->_elements[$this->key]['value']) && !empty ($this->_elements[$this->key]['value']) ? $this->_elements[$this->key]['value'] : '') . '"> 
        </div>';

        $this->html .= '<div id="' . $this->_elements[$this->key]['id'] . 'Warning" class="formValidation">
            ' . $this->_elements[$this->key]['id'] . ' cannot be blank.
        </div>';
    }

    public function buildTextarea ()
    {
        $placeholder = isset ($this->_elements[$this->key]['placeholder']) && !empty ($this->_elements[$this->key]['placeholder']) ? $this->_elements[$this->key]['placeholder'] : '';
        $fieldClass = isset ($this->_elements[$this->key]['field_class']) && !empty ($this->_elements[$this->key]['field_class']) && $this->_elements[$this->key]['field_class'] != "form-control" ? $this->_elements[$this->key]['field_class'] : '';
        $disabled = isset ($this->_elements[$this->key]['is_disabled']) && $this->_elements[$this->key]['is_disabled'] == 1 ? 'disabled="disabled"' : '';
        $maxLength = isset ($this->_elements[$this->key]['max_length']) && !empty ($this->_elements[$this->key]['max_length']) ? $this->_elements[$this->key]['max_length'] : '';

        $this->html .= '<div class="col-lg-10">
            <textarea maxlength="' . $maxLength . '" ' . $disabled . ' placeholder="' . $placeholder . '" id = "' . $this->_elements[$this->key]['id'] . '" name="' . $this->_elements[$this->key]['name'] . '" placeholder="' . $this->label . '" class="form-control ' . $fieldClass . '">' . $this->_elements[$this->key]['value'] . '</textarea>
        </div>';

        $this->html .= '<div id="' . $this->_elements[$this->key]['id'] . 'Warning" class="formValidation">
            ' . $this->_elements[$this->key]['id'] . ' cannot be blank.
        </div>';
    }

    public function buildSelect ()
    {

        if ( !empty ($this->_elements[$this->key]['options']) )
        {
            $this->_elements[$this->key]['options'] = json_decode ($this->_elements[$this->key]['options'], true);
        }

        $fieldClass = isset ($this->_elements[$this->key]['field_class']) && !empty ($this->_elements[$this->key]['field_class']) && $this->_elements[$this->key]['field_class'] != "form-control" ? $this->_elements[$this->key]['field_class'] : '';
        $disabled = isset ($this->_elements[$this->key]['is_disabled']) && $this->_elements[$this->key]['is_disabled'] == 1 ? 'disabled="disabled"' : '';


        $this->html .= '<div class="col-lg-10">
            <select ' . $disabled . ' class="form-control ' . $fieldClass . ' m-b" id="' . $this->_elements[$this->key]['id'] . '" name="' . $this->_elements[$this->key]['name'] . '">';
        $this->html .= '<option value="">Select One</option>';

        foreach ($this->_elements[$this->key]['options'] as $value => $name) {

            if ( is_array ($name) )
            {
                $value = $name['id'];
                $name = $name['value'];
            }

            if ( !empty ($this->_elements[$this->key]['value']) && $this->_elements[$this->key]['value'] == $value )
            {
                $strSelected = 'selected="selected"';
            }
            else
            {
                $strSelected = '';
            }
            $this->html .= '<option value="' . $value . '" ' . $strSelected . '>' . $name . '</option>';
        }
        $this->html .= '</select>
        </div>';

        $this->html .= '<div id="' . $this->_elements[$this->key]['id'] . 'Warning" class="formValidation">
            ' . $this->_elements[$this->key]['id'] . ' cannot be blank.
        </div>';
    }

    public function buildFileInput ()
    {
        if ( isset ($this->documentHTML) && trim ($this->documentHTML) !== "" )
        {
            $this->html .= $this->documentHTML;
        }

        $this->html .= '<button id="uploadButton" type="button" class="btn btn-primary">' . $this->label . '</button>';

        $this->html .= '<div id="hideUglyUpload" style="display:none;">
            <input multiple="multiple" type="file" name="' . $this->_elements[$this->key]['name'] . '[]" id="' . $this->_elements[$this->key]['id'] . '"/>
        </div>';

        $this->html .= '<div style=" display:table-row; text-align: center; width:100%; font-size: 16px;">
            <div style="float: left; text-align: center; width:100%; display:table-column;  ">
                <div id="filelist" name="filelist" style="width: 100%"></div>
            </div>		
	</div>
						
        <div style=" display:table-row; width:100%;">
            <div id="progress" style="float: left; width:100%; display:table-column;  "></div>		
	</div>';


        $this->html .= '<div id="filelist"></div>';

        $this->html .= '<script>
            $("#uploadButton").on("click",function(evt){
                evt.preventDefault();
                $("#' . $this->_elements[$this->key]['id'] . '").trigger("click");
            });
        </script>';
    }

}
