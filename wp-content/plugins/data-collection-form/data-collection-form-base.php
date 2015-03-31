<?php

class wpFormPluginFormBase {
    
    function ugf_page_template() {
        global $wpdb,$wp_query;
        
        if(isset($_POST['ugfform_id'])) {
            
            //match the form token
            $sesion_form_token = $_SESSION['form_token'];
            $post_form_token = $_POST['ugfform_token'];
            if($post_form_token != $sesion_form_token) {
                return;
            }
            $form_id = $_POST['ugfform_id'];
            if(!$form_id) {
                return;
            }
            
            
            $form_row = $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix . "ugf_forms where id = '%d'",$form_id));
            if(!$form_row) {
                return;
            }
            
            $form_id = $form_row->id;
            $email_template = $form_row->email_template;
            $email_address = $form_row->email_address;
            
            $post_title_field_id = $form_row->post_title;
            $post_type = $form_row->post_type;
            
            
            $form_url = $_POST['ugfform_url'];
            $error = array();
            if($form_row->captcha == 1) {
                if(trim($_POST['ugfcaptcha']) != trim($_SESSION['code'])) {
                  $error['ugfcaptcha'] = 'Captcha did not match';
                }
            }
            
            $fields_row = $wpdb->get_results($wpdb->prepare("select * from " . $wpdb->prefix . "ugf_fields where form_id = '%d' order by ordering",$form_id));
            
            for($i = 0; $i< count($fields_row);++$i) {
                $field_machine_name	 = $fields_row[$i]->field_machine_name;
                $field_label	 = $fields_row[$i]->field_label;
                
                
                $field_id = $fields_row[$i]->id;
                $field_value = $_POST['ugf'.$field_machine_name];
                
                $sql  = "select * from ". $wpdb->prefix. "ugf_fields_validations where field_id = '%d'";
                $psql = $wpdb->prepare($sql,$field_id);
                $validation_rows = $wpdb->get_results($psql);
                
                for($v =0; $v < count($validation_rows); ++$v) {
                    $func_name = $validation_rows[$v]->validation_type;
                    if($this->{$func_name}($field_value) == false) {
                        $error[$field_machine_name] = $field_label .' is required.';
                    }
                }
            }
            if(count($error) > 0) {
                $_SESSION['ugform']['error'] = $error;
                
            }
            else {
                $my_post = array(
                    'post_status'   => 'pending',
                    'post_author'   => 1,
                    'post_type' => $post_type
                );

                for($i = 0; $i< count($fields_row);++$i) {
                    $field_machine_name	 = $fields_row[$i]->field_machine_name;
                    $field_id = $fields_row[$i]->id;
                    $field_value = $_POST['ugf'.$field_machine_name];
                    
                    
                    if($post_title_field_id == $field_id) {
                        $my_post['post_title']  = $field_value;
                    }
                }
                $post_id = wp_insert_post( $my_post );
                
                for($i = 0; $i< count($fields_row);++$i) {
                    $field_machine_name	 = $fields_row[$i]->field_machine_name;
                    $field_label	 = $fields_row[$i]->field_label;
                    $field_type = $fields_row[$i]->field_type;

                    $field_id = $fields_row[$i]->id;
                    $field_value = $_POST['ugf'.$field_machine_name];
                    if(is_array($field_value)) {
                        $field_value  = implode(",",$field_value);
                    }
                    if($field_type == 'file') {
                        $this->upload_fiie('ugf' . $field_machine_name, $post_id);
                    }else if($field_type == 'image') {
                        $this->upload_attached_file('ugf' . $field_machine_name,$post_id);
                    }else {
                        if($post_title_field_id != $field_id) {
                            update_post_meta($post_id, $field_machine_name, $field_value);
                        }
                    }
                }
            }
            if(count($error) == 0) {
                $_SESSION['form_success']  = $form_row->form_sucess_msg;
            }
            wp_redirect($form_url);
            exit;
        }
    }
    function upload_attached_file($filename,$post_id) {
        // These files need to be included as dependencies when on the front end.
	require_once( ABSPATH . 'wp-admin/includes/image.php' );
	require_once( ABSPATH . 'wp-admin/includes/file.php' );
	require_once( ABSPATH . 'wp-admin/includes/media.php' );
        
        $attachment_id = media_handle_upload( $filename, $post_id);        
    }
    
    function upload_fiie($filename, $post_id) {
        if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $uploadedfile = $_FILES[$filename];
        $upload_overrides = array( 'test_form' => false );
        $movefile = wp_handle_upload( $uploadedfile, $upload_overrides );
        
        $filename = $movefile['file'];
        
        $parent_post_id = $post_id;
        
        $filetype = wp_check_filetype( basename( $filename ), null );
        
        $wp_upload_dir = wp_upload_dir();
        
        // Prepare an array of post data for the attachment.
        $attachment = array(
                'guid'           => $wp_upload_dir['url'] . '/' . basename( $filename ), 
                'post_mime_type' => $filetype['type'],
                'post_title'     => preg_replace( '/\.[^.]+$/', '', basename( $filename ) ),
                'post_content'   => '',
                'post_status'    => 'inherit'
        );
        
        // Insert the attachment.
        $attach_id = wp_insert_attachment( $attachment, $filename, $parent_post_id );
        
        // Make sure that this file is included, as wp_generate_attachment_metadata() depends on it.
        require_once( ABSPATH . 'wp-admin/includes/image.php' );
        
        // Generate the metadata for the attachment, and update the database record.
        $attach_data = wp_generate_attachment_metadata( $attach_id, $filename );
        wp_update_attachment_metadata( $attach_id, $attach_data );
        

        
    }
    
    function required($field_value) {
        if($field_value == '') {
            return false;
        }
        return true;    
    }
    
    function ugf_manage_fields() {
        global $wpdb;
        $form_id = $_POST['form_id'];
        $sql = "select * from ". $wpdb->prefix . "ugf_forms where id = '%d'";
        $psql = $wpdb->prepare($sql,$form_id);
        
        $form_row = $wpdb->get_row($psql);
        $html = '<div id="ugf_form_fields">';
        $html .= $this->get_form_fields_list($form_id);
        $html .= '</div>';
        print $html;
        die(0);
    }
    
    function ugf_delete_field() {
        global $wpdb;
        $form_id = $_POST['form_id'];
        $id = $_POST['id'];
        
        $sql = "delete from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d'";
        $psql = $wpdb->prepare($sql,$id);
        $wpdb->query($psql);
        
        $sql = "delete from " . $wpdb->prefix . "ugf_fields_validations where field_id = '%d'";
        $psql = $wpdb->prepare($sql,$id);
        $wpdb->query($psql);
        
        
        $sql = "delete from " . $wpdb->prefix . "ugf_fields where id = '%d'";
        $psql = $wpdb->prepare($sql,$id);
        $wpdb->query($psql);
        
    }
    
    function ugf_fields_form() {
        global $wpdb;
        
        $form_id = $_POST['form_id'];
        $id = $_POST['id'];
        
        $field_row =  $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix . "ugf_fields where id = '%d'",$id));
        
        if($field_row) {
            foreach($field_row as $k => $v) {
                $$k = $v;
            }
        }
        $html = '<div style="float:left;padding:10px;"><input type="button" value="Back To Manage Fields" class="button-primary" onclick="ManageFields(\''.$form_id.'\',1);"></div>';
        $html .= '<div style="float:left;padding:10px;"><button type="button" class="button-primary" onclick="ugfFieldsFormSaveOnly(\''.$form_id.'\');">Save</button></div>';
        
        
        $html .= '<div style="clear:both"></div>';
        $html .= '<form method="post" id="ugf_fields">';
        $html .= '<input type="hidden" name="form_id" value="'.$form_id.'">';
        $html .= '<input type="hidden" name="id" value="'.$id.'">';
        $html .= '<input type="hidden" name="action" value="ugf_fields_form_save">';
        
        $html .= '<table class="table" width="100%" cellspacing="0" cellpadding="4" border="0">';

        $html .= '<tr><td>';
        $html .= '<label>Field Type</label>';
        $html .= '</td>';
        $html .= '<td>';
        if($id > 0) {
             $html .=  $this->get_field_type($field_type);
            
        }else {
            $html .= '<select name="field_type" id="field_type" onchange="ShowExtraForm(this.value);">';
            $html .= $this->buildSelect($this->get_field_types(),$field_type);
            $html .= '</select>';
        }
        $html .= '</td></tr>';
        
        $html .= '<tr><td>';
        $html .= '<label>Field Label</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<input type="text" name="field_label" id="field_label" class="ugf-input" size="40" value="' . $field_label . '">';
        $html .= '<td>';
        $html .= '</td></tr>';

        $html .= '<tr><td>';
        $html .= '<label>Machine Name</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<input type="text" name="field_machine_name" id="field_machine_name" class="ugf-input" size="40" value="' . $field_machine_name . '">';
        $html .= '</td></tr>';


        $html .= '<tr id="extra_form_id"><td>';
        $html .= '<label>Label Placement</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<select name="lbl_placement" id="lbl_placement" class="ugf-input">';
        $html .= $this->buildSelect($this->get_lbl_places(),$lbl_placement);
        $html .= '</select>';
        $html .= '<td>';
        $html .= '</td></tr>';
        
        $html .= '<tr><td>';
        $html .= '<label>Field Css Class</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<input type="text" name="field_class" id="field_class" class="ugf-input" size="40" value="' . $field_class . '">';
        $html .= '</td></tr>';
        
        
        
        
        $html .= '<tr><td>';
        $html .= '<label>Label Wrapper Css Class</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<input type="text" name="lbl_wrapper_class" id="lbl_wrapper_class" class="ugf-input" size="40" value="' . $lbl_wrapper_class . '">';
        $html .= '</td></tr>';
        
        $html .= '<tr><td>';
        $html .= '<label>Field Wrapper Css Class</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<input type="text" name="field_wrapper_class" id="field_wrapper_class" class="ugf-input" size="40" value="' . $field_wrapper_class . '">';
        $html .= '</td></tr>';

        
        $html .= '<tr><td>';
        $html .= '<label>Field Prefix Html</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<textarea name="field_prefix_html" id="field_prefix_html" rows="4" cols="40">';
        $html .= $field_prefix_html;
        $html .= '</textarea>';
        $html .= '</td></tr>';
        
        
        $html .= '<tr><td>';
        $html .= '<label>Field Suffix Html</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<textarea name="field_postfix_html" id="field_postfix_html" rows="4" cols="40">';
        $html .= $field_postfix_html;
        $html .= '</textarea>';
        $html .= '</td></tr>';        
        
        
        $html .= '<tr><td>';
        $html .= '<label>Label Prefix Html</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<textarea name="label_prefix" id="label_prefix" rows="4" cols="40">';
        $html .= $label_prefix;
        $html .= '</textarea>';
        $html .= '</td></tr>';
        
        
        $html .= '<tr><td>';
        $html .= '<label>Label Suffix Html</label>';
        $html .= '</td>';
        $html .= '<td>';
        $html .= '<textarea name="label_suffix" id="label_suffix" rows="4" cols="40">';
        $html .= $label_suffix;
        $html .= '</textarea>';
        $html .= '</td></tr>';
        
        
        $html .= '<tr><td colspan="2">';
        
        $html .= '<div style="float:left;padding:10px;"><input type="button" value="Back To Manage Fields" class="button-primary" onclick="ManageFields(\''.$form_id.'\',1);"></div>';
        $html .= '<div style="float:left;padding:10px;"><button type="button" class="button-primary" onclick="ugfFieldsFormSaveOnly(\''.$form_id.'\');">Save</button></div>';
        
        
        $html .= '<div style="clear:both"></div>';


        $html .= '</td>';
        $html .= '<td>';
        $html .= '</td></tr>';
        
        $html .= '</table>';
        $html .= '</form>';
        print $html;
        die();
    }
    
    function get_lbl_places() {
        $array = array();
        $array['Inline'] = 'Inline';
        $array['above'] = 'Above';
        return $array;
    }
    
    function ugf_fields_form_save_step2() {
        global $wpdb;
        
        $form_id = $_POST['form_id'];
        
        $id = $_POST['id'];
        
        $form_row =  $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix . "ugf_forms where id ='%d'",$form_id));
        
        $field_row =  $wpdb->get_row($wpdb->prepare("select * from " . $wpdb->prefix . "ugf_fields where id ='%d'",$id));
        
        $html = '<div style="float:right;"><input type="button" value="Back To Manage Fields" class="button-primary" onclick="ManageFields(\''.$form_id.'\',1);"></div>';
        $html .= '<div style="clear:both;"></div>';
        $html .= '<fieldset class="step2">';
        $html .= '<legend>Manage Attributes-<em>This Attributes will be appended to the input control at the time on form generation.</em></legend>';
        
        $html .= '<div id="ugf_attr_lists">';
        $html .= $this->ugf_fields_attribute_list($id);
        $html .= '</div>';
        $html .= '</fieldset>';
        $html .= '<form id="ugf_extra_attr">';
        $html .= '<input type="hidden" name="action" value="ugf_extra_attr_save">';
        $html .= '<input type="hidden" name="form_id" value="'.$form_id.'">';
        $html .= '<input type="hidden" name="field_id" value="'.$id.'">';
        
        $html .= '<table width="100%" cellspacing="0" cellpadding="2" border="0">';
        
        if($field_row->field_type == 'text') {
            $sql = "select id from " .$wpdb->prefix."ugf_fields_attributes where type='validation' and attribute_name = 'notempty' and field_id = '%d'";
            $psql = $wpdb->prepare($sql,$id);
            $row = $wpdb->get_row($psql);
            
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<label>Required:</label>';
            $html .= '</td></tr>';
            $html .= '<tr><td>';
            $html .= '<input type="hidden" name="id" value="' . $row->id . '">';
            $html .= '<input type="hidden" name="type" value="validation">';
            $html .= '<input type="hidden" name="attribute_name" value="notempty">';
            $html .= '<select name="attribute_value">';
            $html .= $this->buildSelect($this->get_yn(),$row->attribute_value);
            $html .= '</select>';
            $html .= '</td>';
            $html .= '</tr>';

        }
        
        if($field_row->field_type == 'textarea') {
                
        }
        
        if($field_row->field_type == 'select' || $field_row->field_type == 'radio' || $field_row->field_type == 'checkbox') {
            $sql = "select * from " .$wpdb->prefix."ugf_fields_attributes where type='option' and attribute_name = 'options' and field_id = '%d'";
            $psql = $wpdb->prepare($sql,$id);
            $row = $wpdb->get_row($psql);
            
            $html .= '<input type="hidden" name="id" value="'. $row->id . '">';
            $html .= '<input type="hidden" name="type" value="option">';
            $html .= '<input type="hidden" name="attribute_name" value="options">';
            
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<label>Enter Option (key|value)</label>';
            $html .= '</td></tr>';
            $html .= '<tr><td>';
            $html .= '<textarea name="attribute_value" rows="5" cols="40">'.$row->attribute_value.'</textarea>';
            $html .= '</td>';
            $html .= '</tr>';    
        }
        
        if($field_row->field_type == 'radio') {
                
        }
        
        if($field_row->field_type == 'checkbox') {
                
        }
        
        if($field_row->field_type == 'date') {
                
        }
        
        if($field_row->field_type == 'file') {
                
        }
        
        if($field_row->field_type == 'image') {
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<label>Resize Image</label>';
            $html .= '</td></tr>';
            $html .= '<tr><td>';
            
            $sql = "select * from " .$wpdb->prefix."ugf_fields_attributes where type='option' and attribute_name = 'resize' and field_id = '%d'";
            $row = $wpdb->get_row($wpdb->prepare($sql,$form_id));
            
            
            $html .= '<input type="hidden" name="id[]" value="'.$row->id.'">';
            $html .= '<input type="hidden" name="type[]" value="option">';
            $html .= '<input type="hidden" name="attribute_name[]" value="resize">';
            
            $html .= '<select name="attribute_value[]">';
            $html .= $this->buildSelect($this->get_yn(),$row->attribute_value);
            $html .= '</select>';
            $html .= '</td>';
            $html .= '</tr>';
            
            $html .= '<tr>';
            $html .= '<td>';
            
            $sql = "select * from " .$wpdb->prefix."ugf_fields_attributes where type='option' and attribute_name = 'width' and field_id = '%d'";
            $row = $wpdb->get_row($wpdb->prepare($sql,$form_id));
            
            $html .= '<input type="hidden" name="id[]" value="'.$row->id.'">';
            $html .= '<input type="hidden" name="type[]" value="option">';
            $html .= '<input type="hidden" name="attribute_name[]" value="width">';
            
            $html .= '<label>Width</label>';
            $html .= '</td></tr>';
            $html .= '<tr><td>';
            $html .= '<input type="text" name="attribute_value[]" value="'.$row->attribute_value.'">';
            $html .= '</td>';
            $html .= '</tr>';
            
            
            $html .= '<tr>';
            $html .= '<td>';
            $html .= '<label>Height</label>';
            $html .= '</td></tr>';
            $html .= '<tr><td>';
            
            $sql = "select *  from " .$wpdb->prefix."ugf_fields_attributes where type='option' and attribute_name = 'height' and field_id = '%d'";
            $row = $wpdb->get_row($wpdb->prepare($sql,$form_id));
             
            $html .= '<input type="hidden" name="id[]" value="' . $row->id .'">';
            
            $html .= '<input type="hidden" name="type[]" value="option">';
            $html .= '<input type="hidden" name="attribute_name[]" value="height">';
            
            $html .= '<input type="text" name="attribute_value[]"  value="'.$row->attribute_value.'">';
            $html .= '</td>';
            $html .= '</tr>';
        }
        
        
        $html .= '<tr>';
        $html .= '<td>';

        $html .= '</td></tr>';
        $html .= '<tr><td>';
        $html .= '<input type="button" value="Save" class="button-primary" onclick="extraAttrSave(\''. $form_id .'\',\''. $id.'\');">';
        $html .= '</td>';
        $html .= '</tr>';
            
        $html .= '</table>';
        
        $html .= '</form><br>';
        
        
        print $html;
        
        die(0);
                                     
    }
    
    function get_extra_form() {
        global $wpdb;
        $type = $_POST['field_type'];
        $field_id = $_POST['field_id'];
        
        $checked = ' ';
        if($field_id > 0) {
            $sql = "select validation_type from " . $wpdb->prefix . "ugf_fields_validations where field_id = '%d' and validation_type = 'required'";
            $psql = $wpdb->prepare($sql,$field_id);
            $required_row = $wpdb->get_row($psql);
            if($required_row->validation_type == 'required') {
                $checked = ' checked ="checked" ';
            }
        }

        
        if($type == 'text') {
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Make It Required</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $html .= '<input type="checkbox" name="validation[required]" value="required" ' . $checked . '>';
            $html .= '</td></tr>';
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Size</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '$field_id' and attribute_name='size'";
            $attribute_value = $wpdb->get_var($sql);
            $html .= '<input type="text" name="attributes[size]" value="'.$attribute_value.'" size="10">px';
            $html .= '</td></tr>';
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Store It in the Title</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%s' and attribute_name='title'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            if($attribute_value == 1) {
                $attribute_value_checked = 'checked="checked"';
            }
            $html .= '<input type="checkbox" name="attributes[title]" value="1" ' . $attribute_value_checked . '>';
            $html .= '</td></tr>';
         
        }
        
        if($type == 'textarea') {

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Store It in the Title</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='title'";
            $attribute_value = $wpdb->get_var($wpdb->prepare($sql,$field_id));
            
            if($attribute_value == 1) {
                $attribute_value_checked = 'checked="checked"';
            }
            $html .= '<input type="checkbox" name="attributes[title]" value="1" ' . $attribute_value_checked . '>';
            $html .= '</td></tr>';


            $html .= '<tr  class="extra_fields"><td>';
            $html .= '<label>Make It Required</label>';
            $html .= '</td>';
            $html .= '<td>';
            $html .= '<input type="checkbox" name="validation[required]" value="required" '.$checked.'>';
            $html .= '</td></tr>';
        
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Rows</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='rows'";
            $attribute_value = $wpdb->get_var($wpdb->prepare($sql,$field_id));
            
            $html .= '<input type="text" name="attributes[rows]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Cols</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='cols'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<input type="text" name="attributes[cols]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';

        }
        
        if($type == 'select') {

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Option</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='options'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<textarea name="attributes[options]" rows="5" cols="40">'.$attribute_value.'</textarea>';
            $html .= '<br/><em>Enter options in key|value format new line seperated. ex(US|united States)</em>';
            $html .= '</td></tr>';
            

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Default Value</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='default'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<input type="text" name="attributes[default]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';


            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>First Row Blank</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='first_row_blank'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $first_row_blank_checked = '';
            if($attribute_value == 1) {
                $first_row_blank_checked = 'checked';    
            }
            $html .= '<input type="checkbox" name="attributes[first_row_blank]" value="1" '.$first_row_blank_checked.'>';
            $html .= '</td></tr>';
            

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Required</label>';
            $html .= '</td>';
            $html .= '<td>';
            $html .= '<input type="checkbox" name="validation[required]" value="required" ' . $checked . '>';
            $html .= '</td></tr>';

        }
        
        if($type == 'radio') {

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Option</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='options'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<textarea name="attributes[options]" rows="5" cols="40">'.$attribute_value.'</textarea>';
            $html .= '<br/><em>Enter options in key|value format new line seperated. ex. (US|united States)</em>';
            $html .= '</td></tr>';
            
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Default Value</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='default'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<input type="text" name="attributes[default]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';

            
        }
           
        if($type == 'checkbox') {

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Option</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='options'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<textarea name="attributes[options]" rows="5" cols="40">'.$attribute_value.'</textarea>';
            $html .= '<br/><em>Enter options in key|value format with new line seperated. ex(US|united States)</em>';
            $html .= '</td></tr>';

        }
        if($type == 'date') {
            

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Required</label>';
            $html .= '</td>';
            $html .= '<td>';
            $html .= '<input type="checkbox" name="validation[required]" value="required" ' . $checked . '>';
            $html .= '</td></tr>';
            
            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Date Format</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='date_format'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            if(!$attribute_value) {
                $attribute_value = 'mm/dd/yy';
            }
            $html .= '<input type="text" name="attributes[date_format]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';



        }
        if($type == 'file') {
            
        }
        if($type == 'image') {

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Resize</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='resize'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            $checked = '';
            if($attribute_value == 1) {
                $checked = 'checked';
            }
            
            $html .= '<input type="checkbox" name="attributes[resize]" value="1" '.$checked.'>';
            $html .= '</td></tr>';

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Width</label>';
            $html .= '</td>';
            $html .= '<td>';
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='width'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<input type="text" name="attributes[width]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';

            $html .= '<tr class="extra_fields"><td>';
            $html .= '<label>Height</label>';
            $html .= '</td>';
            $html .= '<td>';
            
            $sql = "select attribute_value from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d' and attribute_name='height'";
            $psql = $wpdb->prepare($sql,$field_id);
            $attribute_value = $wpdb->get_var($psql);
            
            $html .= '<input type="text" name="attributes[height]" value="'.$attribute_value.'">';
            $html .= '</td></tr>';
        }
        
        
        print $html;
        exit;
    }
    
    function ugf_extra_attr_save() {
        global $wpdb;
        
        
        $form_id = $_POST['form_id'];
        $field_id = $_POST['field_id'];
        
        $type = $_POST['type'];
        $attribute_name = $_POST['attribute_name'];
        $attribute_value = $_POST['attribute_value'];
        $ids = $_POST['id'];
        
        
        if(is_array($type)) {
            for($j =0; $j < count($type); ++$j) {
                
                $data = array();
                $data[] =   "field_id='%d'";
                $data[] =   "form_id='%d'";
                $data[] =   "type='%s'";
                $data[] =   "attribute_name='%s'";
                $data[] =   "attribute_value= '%s'";
                
                if($ids[$j] > 0) {
                    $sql = "update " .$wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data) . " where id = '%d'";
                    $psql = $wpdb->prepare($sql,$field_id,$form_id,$type[$j],$attribute_name[$j],$attribute_value[$j],$ids[$j]);
                    $wpdb->query($psql);                    
                }else {
                    $sql = "insert into " .$wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data);
                    $psql = $wpdb->prepare($sql,$field_id,$form_id,$type[$j],$attribute_name[$j],$attribute_value[$j]);
                    $wpdb->query($psql);                    
                }
            }
        }else {
            $data = array();
            $data[] =   "field_id='%d'";
            $data[] =   "form_id='%d'";
            $data[] =   "type='%s'";
            $data[] =   "attribute_name='%s'";
            $data[] =   "attribute_value= '%s'";
            if($ids > 0) {
                $sql = "update " .$wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data) . " where id = '%d'";
                $wpdb->query($sql,$field_id,$form_id,$type,$attribute_name,$attribute_value,$ids);
            }else {
                $sql = "insert into " .$wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data);
                $wpdb->query($sql);
            }
        }
        exit;
        
    }
    
    function ugf_fields_attribute_list($field_id) {
        global $wpdb;
        
        $field_attributes =  $wpdb->get_results($wpdb->prefix("select * from " . $wpdb->prefix . "ugf_fields_attributes where field_id ='%d'",$field_id));
        
        $html .= '<table width="500px" cellspacing="0" cellpadding="0" border="0">';
        $html .= '<tr>';
        $html .= '<th align="left">Attribute Name</th>';
        $html .= '<th align="left">Attribute Value</th>';
        $html .= '<th>Delete</th>';
        $html .= '</tr>';
        
        for($a = 0; $a < count($field_attributes); ++$a) {
            
            $html .= '<tr>';
            $html .= '<td>' . $field_attributes[$a]->attribute_name . '</td>';
            $html .= '<td>' . $field_attributes[$a]->attribute_value . '</td>';
            $html .= '<th><a href="javascript:void(0);" onclick="UgfDelFieldAttr(\''.$field_id.'\',\''.$field_attributes[$a]->id.'\')">Delete</a></th>';
            $html .= '</tr>';            
        }
        $html .= '<tr>';
        $html .= '<td><input type="text" id="ugf_attribute_name"></td>';
        $html .= '<td><input type="text" id="ugf_attribute_value"></td>';
        $html .= '<td><input type="button" name="add_attribute_value" value="Add" onclick="UgfAddFieldAttr(\''.$field_id.'\')"></td>';
        $html .= '</tr>';            
        $html .= '</table>';
        return $html;
        
    }
    
    function ugf_fields_attribute_save() {
        global $wpdb;
        
        $field_id = $_POST['field_id'];
        $attr_id = $_POST['attr_id'];
        $ugf_attribute_name = $_POST['ugf_attribute_name'];
        $ugf_attribute_value = $_POST['ugf_attribute_value'];
        
        $data = array();
        $data[] = "id = '%d'";
        $data[] = "field_id = '%d'";
        $data[] = "attribute_name = '%s'";
        $data[] = "attribute_value = '%s'";
        
        if($attr_id > 0) {
            $psql = $wpdb->prepare("update " . $wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data) . " where id ='%d'",$attr_id,$field_id,$ugf_attribute_name,$ugf_attribute_value,$attr_id);
            $wpdb->query($psql);
        }else {
            $psql = $wpdb->prepare("insert into  " . $wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data),$attr_id,$field_id,$ugf_attribute_name,$ugf_attribute_value);
            $wpdb->query($psql);
        }
        
        print $this->ugf_fields_attribute_list($field_id);
        die(0);
    }
    
    function ugf_fields_attribute_del() {
         global $wpdb;
        
        $field_id = $_POST['field_id'];
        $attr_id = $_POST['attr_id'];
        $psql = $wpdb->prepare("delete from  " . $wpdb->prefix . "ugf_fields_attributes where id ='%d'",$attr_id);
        $wpdb->query($psql);
        
        print $this->ugf_fields_attribute_list($field_id);
        die(0);
    }
    
    function ugf_fields_form_save() {
        global $wpdb;
        $form_id = trim($_POST['form_id']);
        $id = trim($_POST['id']);
        $field_label = trim($_POST['field_label']);
        $field_machine_name = trim($_POST['field_machine_name']);
        
        $attributes = $_POST['attributes'];
        
        $validation = $_POST['validation'];
                
        if(!$id) {
            $field_type = trim($_POST['field_type']);    
        }
        
        $lbl_placement = trim($_POST['lbl_placement']);
        
        $lbl_wrapper_class = trim($_POST['lbl_wrapper_class']);
        $field_wrapper_class = trim($_POST['field_wrapper_class']);
        
        $field_class = trim($_POST['field_class']);
        
        
        $field_prefix_html = stripslashes(trim($_POST['field_prefix_html']));
        $field_postfix_html = stripslashes(trim($_POST['field_postfix_html']));
        
        $label_prefix = stripslashes(trim($_POST['label_prefix']));
        $label_suffix = stripslashes(trim($_POST['label_suffix']));
        
        
        $err = 0;
        
        $output = array('status' => true,'msg' => '', 'form_id' => $form_id);
        if($field_label == '') {
            $err = 1;
            $output['status'] = false;
            $output['msg']['field_label'] = 'Please enter field label';
        }
        
        if($field_machine_name == '') {
            $err = 1;
            $output['status'] = false;
            $output['msg']['field_machine_name'] = 'Please enter field Machine Name';
        }
        if(!$id) {
            if($field_type == '' ) {
                $err = 1;
                $output['status'] = false;
                $output['msg']['field_type'] = 'Please select field type';
            }        
        }
        if($err == 0) {
            $data = array();
            
            $data[] = "form_id = '%d'";
            $data[] = "field_label = '%s'";
            $data[] = "field_machine_name = '%s'";
            if(!$id) {
                $data[] = "field_type = '%s'";
            }
            $data[] = "lbl_placement = '%s'";
            $data[] = "field_class = '%s'";
            
            
            $data[] = "lbl_wrapper_class = '%s'";
            
            $data[] = "field_wrapper_class = '%s'";
            
            $data[] = "field_prefix_html = '%s'";
            $data[] = "field_postfix_html = '%s'";
            $data[] = "label_prefix = '%s'";
            $data[] = "label_suffix = '%s'";
            
            if($id > 0) {
                $sql = "update " . $wpdb->prefix . "ugf_fields set " .implode(",",$data) . " where id = '%d'";
                $psql = $wpdb->prepare($sql,
                    $form_id,
                    $field_label,
                    $field_machine_name,
                    $lbl_placement,
                    $field_class,
                    $lbl_wrapper_class,
                    $field_wrapper_class,
                    $field_prefix_html,
                    $field_postfix_html,
                    $label_prefix,
                    $label_suffix,
                    $id);
                
                $wpdb->query($psql);
                $output['id'] = $id;
                
            }else {
                $sql = "insert into " . $wpdb->prefix . "ugf_fields set " .implode(",",$data);
                $psql = $wpdb->prepare($sql,
                    $form_id,
                    $field_label,
                    $field_machine_name,
                    $field_type,
                    $lbl_placement,
                    $field_class,
                    $lbl_wrapper_class,
                    $field_wrapper_class,
                    $field_prefix_html,
                    $field_postfix_html,
                    $label_prefix,
                    $label_suffix);
                
                $wpdb->query($psql);
                
                $output['id'] = $wpdb->insert_id;
            }
            
            $sql = "delete from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d'";
            $psql = $wpdb->prepare($sql,$id);
            $wpdb->query($psql);
            

            $sql = "delete from " . $wpdb->prefix . "ugf_fields_validations where field_id = '%d'";
            $psql = $wpdb->prepare($sql,$id);
            $wpdb->query($psql);
            
            if(isset($attributes)) {
                foreach($attributes as $k => $v) {
                    if($k == 'title') {
                        $sql = "update " . $wpdb->prefix. "ugf_forms set post_title = '%s' where id = '%d'";
                        $psql = $wpdb->prepare($sql,$output['id'],$form_id);
                        $wpdb->query($psql);
                    }
                    $data = array();
                    $data[] = "field_id='%s'";
                    $data[] = "attribute_name='%s'";
                    $data[] = "attribute_value='%s'";
                    $data[] = "form_id='%s'";
                    $sql = "insert into " . $wpdb->prefix . "ugf_fields_attributes set " . implode(",",$data);
                    $psql = $wpdb->prepare($sql,$output['id'],$k,$v,$form_id);
                    $wpdb->query($psql);
                    
                }
            }

            if(isset($validation)) {
                foreach($validation as $k => $v) {
                    $data = array();
                    $data[] = "field_id='%s'";
                    $data[] = "validation_type='%s'";
                    $sql = "insert into " . $wpdb->prefix . "ugf_fields_validations set " . implode(",",$data);
                    $psql = $wpdb->prepare($sql,$output['id'],$v);
                    $wpdb->query($psql);
                }
            }            
            
            
        }
        
        print json_encode($output);
        die();
    }
    
    function get_form_fields_list($form_id) {
        global $wpdb;
        $url = plugins_url() . '/data-collection-form';
        
        $sql = "select * from ". $wpdb->prefix . "ugf_fields where  form_id = '%d' order by ordering asc";
        $psql = $wpdb->prepare($sql,$form_id);
        $fields_rows = $wpdb->get_results($psql);
        
        $html = '<div class="ugf-main">';
        $html .= '<a class="ugf-main-a" href="javascript:void(0);" onclick="ugfAddNewField(\''.$form_id.'\');">Add New Field</a>';
        $html .= '</div>';
        
        $html .= '<table width="100%" class="ugf-table table" cellpadding="4" cellspacing="0" border="0">';
        $html .= '    <tr>';
        $html .= '        <th align="left">&nbsp;</th>';
        $html .= '        <th align="left">Field Label</th>';
        $html .= '        <th align="left">Field Machine Name</th>';
        $html .= '        <th align="left">Field Type</th>';
        $html .= '        <th align="left"></th>';
        $html .= '        <th align="left"></th>';
        
        $html .= '    </tr><tbody id="sortable">';
        $arr = array('#FFFFFF','#F1F1F1');
        $odd = 0;
        for($i = 0; $i < count($fields_rows); ++$i) {
            $odd = ($odd == 1) ? 0 : 1;
            $id = $fields_rows[$i]->id;
            $bg = $arr[$odd];
            $html .= '    <tr id="'.$fields_rows[$i]->id.'" style="background:'.$bg.';">';
            
            $html .= '        <td align="left"><img src="'.$url.'/drag-icon.png" width="16px;"></td>';
            $html .= '        <td align="left">' . $fields_rows[$i]->field_label . '</td>';
            $html .= '        <td align="left">' . $fields_rows[$i]->field_machine_name . '</td>';
            $html .= '        <td align="left">' . $fields_rows[$i]->field_type . '</td>' ;
            $html .= '        <td align="left"><a href="javascript:void(0);" onclick="ugfEditField(\'' . $form_id . '\',\'' . $id . '\',\'' . $fields_rows[$i]->field_type .'\')">Edit</a></td>';
            $html .= '        <td align="left"><a href="javascript:void(0);" onclick="ugfDeleteField(\'' . $form_id . '\',\'' . $id . '\',\'' . $fields_rows[$i]->field_type .'\')">Delete</a></td>';
            $html .= '    </tr>';
        }
        
        $html .= '</tbody></table>';
        
        return $html;
    }
    
    function ugf_build_field_order() {
        global $wpdb;
        $form_id = $_POST['form_id'];
        $order = $_POST['order'];
        $order_arr  = explode(",",$order);
        $o = 0;
        for($i = 0; $i < count($order_arr); ++$i) {
            $id = $order_arr[$i];
            ++$o;
            $sql = "update " . $wpdb->prefix . "ugf_fields set ordering = '%s' where id = '%d' and form_id='%d'";
            $psql = $wpdb->prepare($sql,$o,$id,$form_id);
            $wpdb->query($psql);
        }
        
    }
        
        

    
    function buildSelect($array,$sel = '') {
        foreach($array as $k => $v) {
            if($sel == $k) {
                $html .= '<option value="'.$k.'" selected = "selected">' . $v . '</option>';    
            }else {
                $html .= '<option value="'.$k.'" >' . $v . '</option>';    
            }
            
        }
        return $html;
    }
    
    function get_field_type($name) {
        $arr = $this->get_field_types();
        return $arr[$name];
    }
    
    function get_field_types() {
        $array = array();
        $array['text'] = 'Input Box';
        $array['textarea'] = 'Big Text Box';
        $array['select'] = 'Select List';
        $array['radio'] = 'Radio Button';
        $array['checkbox'] = 'Checkboxes';
        $array['date'] = 'Date Picker';
        $array['file'] = 'File Upload';
        $array['image'] = 'Image Upload';
        return $array;
    }
    
    
    
    function get_post_method() {
        $array = array();
        $array['POST'] = 'POST';
        $array['GET'] = 'GET';
        return $array;
    }
    
    function get_yn() {
        $array = array();
        $array[0] = 'No';
        $array[1] = 'Yes';
        return $array;
    }

    function get_post_types() {
        $post_types = get_post_types( '', 'names' );
        $array = array();
        $r = array('attachment','revision','nav_menu_item');
        foreach ( $post_types as $post_type ) {
            if(!in_array($post_type,$r)) {
                $array[$post_type] = $post_type;    
            }
            
        }
        return $array;
    }
    
    function buildFormListView() {
        global $wpdb;
        $sql ="select * from ". $wpdb->prefix . "ugf_forms order by form_name";
        $form_rows = $wpdb->get_results($sql);
    ?>
        <table width="100%" class="ugf-table" cellpadding="4" cellspacing="0" border="0">
                <tr>
                    <th align="left">Name</th>
                    <th align="left">Post Method</th>
                    <th align="left">Post Type</th>
                    <th align="left"></th>
                    <th align="left"></th>
                    <th align="left"></th>
                    <th align="left"></th>
                </tr>
            <?php
                for($i = 0; $i < count($form_rows); ++$i) {
                    ?>
                    <tr>
                        <td><?php print $form_rows[$i]->form_name ;?></td>
                        <td><?php print $form_rows[$i]->post_method ;?></td>
                        <td><?php print $form_rows[$i]->post_type ;?></td>
                        <td><a href="javascript:void(0);" onclick="EditMainForm('<?php print $form_rows[$i]->id;?>');">Edit</a></td>
                        <td><a href="javascript:void(0);"  onclick="DeleteMainForm('<?php print $form_rows[$i]->id;?>');">Delete</a></td>
                        <td><a href="javascript:void(0);" onclick="ManageFields('<?php print $form_rows[$i]->id;?>');">Manage Fields</a></td>
                        <td><a href="javascript:void(0);" onclick="getShortCode('<?php print $form_rows[$i]->id;?>');">Get Short Code</a></td>
                    </tr>
                    <?php
                }
            
            ?>
                
            </table>
        <?php
    }
    
    
    function dateToMysql($date) {
        $date_arr = explode("/",$date);
        $y = $date_arr[2];
        $m = $date_arr[0];
        $d = $date_arr[1];
        return $y . "-" . $m . "-" . $d;
    }
    
    function mysqlToDate($date) {
        $date_arr = explode("-",$date);
        $y = $date_arr[0];
        $m = $date_arr[1];
        $d = $date_arr[2];
        return $m . "/" . $d . "/" . $y;
    }
    
}
