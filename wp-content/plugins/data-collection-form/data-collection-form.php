<?php
/**
 * @package Data Collection Form
 * @author Faaiq Ahmed
 * @version 1.0
 */
/*
Plugin Name: Data Collection Form
Description: Build all type of forms and save data in any type of post.
Author: faaiq , nfaaiq@gmail.com
Version: 1.0
*/

global $ugf_db_version;	
$ugf_db_version = "2.5";


if(!defined('UGF_PATH')) {
	define( 'UGF_PATH', plugin_dir_path(__FILE__) );	
}

include_once(UGF_PATH . 'data-collection-form-base.php');



class wpFormPlugin extends wpFormPluginFormBase { 
    
    
    
	function __construct() {
        
        add_action('wp', array($this,'start_session'));

        add_action('admin_menu', array($this,'ugf_menu'));
        
        add_action('wp_head', array($this,'front_head'));
        
                
        add_action('wp_ajax_build_order', array($this,'build_order_callback'));
        
        add_action('admin_head', array($this,'admin_head_load'));
        
        add_action( 'wp_ajax_form_action', array($this,'ugf_form_load'));
        
        add_action( 'wp_ajax_form_delete_action', array($this,'form_delete_action'));
        
        add_action( 'wp_ajax_form_save_action', array($this,'ugf_form_save'));
        
        add_action( 'wp_ajax_ugf_manage_field', array($this,'ugf_manage_fields'));
        
        //show fields form
        add_action( 'wp_ajax_ugf_fields_form', array($this,'ugf_fields_form'));
        add_action( 'wp_ajax_ugf_fields_form_save', array($this,'ugf_fields_form_save'));
        add_action( 'wp_ajax_ugf_fields_form_save_step2', array($this,'ugf_fields_form_save_step2'));
        add_action( 'wp_ajax_ugf_fields_attribute_save', array($this,'ugf_fields_attribute_save'));
        
        add_action( 'wp_ajax_ugf_fields_attribute_del', array($this,'ugf_fields_attribute_del'));
        
        add_action( 'wp_ajax_ugf_extra_attr_save', array($this,'ugf_extra_attr_save'));
        
        add_action( 'wp_ajax_get_short_code', array($this,'get_short_code'));
        
        
        add_action( 'wp_ajax_get_extra_form', array($this,'get_extra_form'));
        
        add_action( 'wp_ajax_ugf_delete_field', array($this,'ugf_delete_field'));
        
        add_action( 'wp_ajax_ugf_build_field_order', array($this,'ugf_build_field_order'));
        
        add_filter( 'template_redirect', array($this,'ugf_page_template'), 99 );
        
        add_action( 'phpmailer_init', array($this,'configure_smtp' ));
        
        add_filter( 'wp_mail_content_type', array($this,'set_html_content_type'));
        
        register_activation_hook(__FILE__, array($this,'install'));
        register_deactivation_hook(__FILE__, array($this,'uninstall'));
	}
    
    function start_session() {
        if ( !session_id() ) {
            session_start();
        }
    }

    function set_html_content_type() {
        return 'text/html';
    }
    
        
    
    
    
    function front_head() {
        
		$url = plugins_url() . '/' . basename(dirname(__FILE__));
        wp_enqueue_style( 'jquery-ui', $url . '/css/jquery-ui.min.css', array(), '1.11.0', false );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        
        print '<link rel="stylesheet" type="text/css" href="'.$url.'/css/data-collection-form.css" />';
        
		print '<script language="javascript" src="'.$url.'/js/data-collection-form.js" /></script>';
		
        print '<script/>var admin_url = "' . home_url("/wp-admin/") . '"</script>';
	}
    
	function admin_head_load() {
		
        $url = plugins_url() . '/' . basename(dirname(__FILE__));
		
        wp_enqueue_style( 'jquery-ui', $url . '/css/jquery-ui.min.css', array(), '1.11.0', false );
        wp_enqueue_script( 'jquery-ui-datepicker' );
        wp_enqueue_script( 'jquery-ui-sortable');
        
        print '<link rel="stylesheet" type="text/css" href="'.$url.'/css/data-collection-form.css" />';
        
		print '<script language="javascript" src="'.$url.'/js/data-collection-form.js" /></script>';
		
        print '<script/>var admin_url = "' . home_url("/wp-admin/") . '"</script>';
        
        
	}
    
    
    
	
    function get_short_code() {
        global $wpdb;
        $form_id = $_POST['form_id'];
        $row = $wpdb->get_row("select * from " . $wpdb->prefix."ugf_forms where id = '$form_id'");
        if($row->post_title > 0) {
            print '<strong>Your Short Is: <br/> [UGF id="' . $row->id . '"]</strong><br><br>';
            print '<strong>Code: <br/>&lt;?php print ugf_form(' . $row->id . '); ?&gt;</strong>';
        }else {
            print '<strong>Error: You did not select title field for this form, please select, then try again.</strong><br><br>';
            
        }
        
        die(0);
        
    }
    
    function ugf_menu() {
        global $current_user, $wpdb;
        $role = $wpdb->prefix . 'capabilities';
        $current_user->role = array_keys($current_user->$role);
        $current_role = $current_user->role[0];
        $role = get_option( 'ccpo_order_manager', 'administrator' );
        
        add_menu_page('Wordpress Forms', 'Wordpress Forms', 'administrator', 'ugf', array($this,'ugf_main'));
        
        
    }
    
    
    function ugf_main() {
        global $wpdb;
        $sql ="select * from ". $wpdb->prefix . "ugf_forms order by form_name";
        $form_rows = $wpdb->get_results($sql);
        
        ?>
        <div class="wrap">
        <h2>WP Forms	
			<a class="hide-if-no-js add-new-h2" href="javascript:void(0);" onclick="showFormEdit();">Add New Form</a></h2>
         <div id="form_list_view"><?php $this->buildFormListView();?></div>

            
        </div>
        <?php
        
    }
    function ugf_settings() {
        ?>
         <div class="wrap">
         <h2>UGF Smtp Settings</h2>
         <div id="form_list_view"></div>
         <?php form_option( 'smtp' ) ?>
         <?php form_option( 'smtp' ) ?>
            
        </div>
        <?php
    }

    function ccpo_admin_right() {
        global $wp_roles;
        
        $role = $_POST['role'];
        if(isset($_POST) and $role != "") {
                
                update_option( "ccpo_order_manager", $role );
                print "Role Updated";
         
        }
        $role = get_option( 'ccpo_order_manager', 'administrator' );
        $roles = $wp_roles->get_names();
        $select  = "";
        foreach($roles as $key=> $label) {
                if($key == $role) {
                        $select .= '<option value="'.$key.'" selected>'.$label.'</option>';		
                }else {
                        $select .= '<option value="'.$key.'">'.$label.'</option>';		
                }
                
        }
        
        print '<div class="wrap">
        <h2>Who Can Arrange Post</h2>
        <form method="post">';
        wp_nonce_field('update-options');
    
        print '<table class="form-table">
        <tr valign="top">
        <th scope="row">Select Role:</th>
        <td><select name="role" id="row">'.$select.'</select></td>
        </tr>';
        print '<tr valign="top"><td>
        <input type="submit" class="button" value="Submit" />
        </td></tr>
        </table>';
    }

    function ccpo_get_post_type() {
        global $wpdb;
        $results = $wpdb->get_results("select post_type from ".$wpdb->prefix."posts where post_type not in ('attachment','revision') group by post_type ");
        $arr = array();
        for($i = 0; $i < count($results); ++$i) {
            $arr[$results[$i]->post_type] = $results[$i]->post_type;
        }
        
        return $arr;
    }


    function install() {
            global $wpdb;
            global $ugf_db_version;
                  
            
            $sql ="CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "ugf_fields` (
              `id` int(11) NOT NULL AUTO_INCREMENT,
              `form_id` int(11) DEFAULT NULL,
              `field_type` varchar(25) DEFAULT NULL,
              `field_label` varchar(250) DEFAULT 'Inline',
              `lbl_placement` varchar(10) NOT NULL DEFAULT 'inline',
              `field_class` varchar(100) NOT NULL DEFAULT 'ugf-form-control',
              `lbl_wrapper_class` varchar(250) DEFAULT NULL,
              `field_machine_name` varchar(250) DEFAULT NULL,
              `field_wrapper_class` varchar(250) DEFAULT NULL,
              `field_prefix_html` text,
              `field_postfix_html` text,
              `matched_post_fields` varchar(50) DEFAULT NULL,
              `label_prefix` text,
              `label_suffix` text,
              `ordering` int(11) NOT NULL DEFAULT '0',
              PRIMARY KEY (`id`)
            ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            $sql ="CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "ugf_fields_attributes` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `field_id` int(11) NOT NULL,
            `attribute_name` varchar(50) NOT NULL,
            `attribute_value` varchar(250) NOT NULL,
            `type` varchar(15) CHARACTER SET utf8 NOT NULL DEFAULT 'input',
            `form_id` int(11) NOT NULL,
            PRIMARY KEY (`id`)
          ) ENGINE=MyISAM  DEFAULT CHARSET=utf8 ;";

            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            
            $sql = "CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "ugf_fields_validations` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `field_id` int(11) NOT NULL,
                `validation_type` varchar(50) NOT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB  DEFAULT CHARSET=utf8;";

            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            
            $sql =" CREATE TABLE IF NOT EXISTS `" . $wpdb->prefix . "ugf_forms` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `form_name` varchar(50) DEFAULT NULL,
                `post_method` enum('POST','GET') DEFAULT NULL,
                `post_type` varchar(25) DEFAULT NULL,
                `form_short_code` varchar(250) DEFAULT NULL,
                `email_template` text,
                `form_prefix_html` text,
                `form_postfix_html` text,
                `form_classes` varchar(250) DEFAULT NULL,
                `form_start_date` date DEFAULT NULL,
                `form_end_date` date DEFAULT NULL,
                `captcha` int(1) NOT NULL DEFAULT '0',
                `captcha_msg` varchar(250) DEFAULT NULL,
                `form_sucess_msg` text,
                `enable_form` int(1) NOT NULL DEFAULT '1',
                `post_title` varchar(100) DEFAULT NULL,
                PRIMARY KEY (`id`)
              ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 ;";
            
            require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
            dbDelta($sql);
            add_option('ugf_db_version', $ugf_db_version);
            
    }	


	
    function uninstall() {
		global $wpdb;
		global $ugf_db_version;
		$table_name = $wpdb->prefix."ugf_forms";
		
		$sql = "DROP TABLE IF EXISTS $table_name";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		dbDelta($sql);
        
        
        $table_name = $wpdb->prefix."ugf_fields";
		
		$sql = "DROP TABLE IF EXISTS $table_name";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		dbDelta($sql);
        
        $table_name = $wpdb->prefix."ugf_fields_attributes";
		
		$sql = "DROP TABLE IF EXISTS $table_name";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		dbDelta($sql);
		
        $table_name = $wpdb->prefix."ugf_fields_validations";
		
		$sql = "DROP TABLE IF EXISTS $table_name";
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		
		dbDelta($sql);
        
		delete_option('ugf_db_version');
		
		$sql = "delete from ". $wpdb->prefix."options where option_name like 'ugf_db_version'";
		dbDelta($sql);
    }
    
    
    function ugf_form_load() {
        global $wpdb;
        
        $form_id = $_POST['form_id'];
        
        if($form_id > 0) {
            $sql = "select * from " . $wpdb->prefix . "ugf_forms where id = '%d'";
			$psql = $wpdb->prepare($sql,$form_id);
            $row = $wpdb->get_row($psql);
            foreach($row as $k => $v) {
                $$k = $v;
            }
            
        }
        
        $html = '<form id="gravity_form_main" method="post">';
        $html .= '<input type="hidden" name="action" value="form_save_action">';
        $html .= '<input type="hidden" name="form_id" value="'.$form_id.'">';
        
        $html .= '<table width="100%" cellspacing="0" cellpadding="4" border="0" class="table">';
        $html .= '<tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Form Name: <span class="ugf-required">*</span></label></td>';
        $html .= '<td><input type="text" name="form_name" size="30" class="ugf-input" value="'.$form_name.'">';
        $html .= '</td>';
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Post Method:</label></td>';
        $html .= '<td><select name="post_method" class="ugf-input">'.$this->buildSelect($this->get_post_method(),$post_method).'</select>';
        $html .= '</td>';
        

        
        
        $html .= '</tr><tr>';      
        $html .= '<td>';
        $html .= '<label class="ugf-label">Post Type Where will be saved:</label></td>';
        $html .= '<td><select name="post_type" class="ugf-input">' . $this->buildSelect($this->get_post_types(),$post_type) . '</select>';
        $html .= '</td>';
        

        
        $html .= '</tr><tr>';      
        $html .= '<td>';
        $html .= '<label class="ugf-label">Form Css Classes:</label></td>';
        $html .= '<td><input type="text" name="form_classes" size="30" class="ugf-input" value="'.$form_classes.'">';
        $html .= '<br/><small>Enter Class Name seperated by space</small>';    
        $html .= '</td>';
        
        $html .= '</tr><tr>';  
        $html .= '<td>';
        $html .= '<label class="ugf-label">Schedule Start Date:</label></td>';
        $html .= '<td><input type="text" value="' . $this->mysqlToDate($form_start_date) . '" name="form_start_date" id="form_start_date" size="15" class="ugf-input" placeholder="DD/MM/YYYY">';
        $html .= '<br/><small>DD/MM/YYYY</small>';    
        $html .= '</td>';
        $date_format   = 'mm/dd/yy';
        $html .= '<script>';
        $html .= 'jQuery(function() {';
        $html .= 'jQuery( "#form_start_date" ).datepicker({';
        $html .= 'changeMonth: true,';
        $html .= 'changeYear: true,';
        $html .= 'dateFormat: "' . $date_format . '"';
        $html .= '});';
        $html .= '});';
        $html .= '</script>';;
        $html .= '</tr><tr>';      
        $html .= '<td>';
        $html .= '<label class="ugf-label">Schedule End Date:</label></td>';
        $html .= '<td><input type="text" value="' . $this->mysqlToDate($form_end_date) . '" name="form_end_date" id="form_end_date" size="15" class="ugf-input" placeholder="DD/MM/YYYY">';
        $html .= '<br/><small>DD/MM/YYYY</small>';    
        $html .= '</td>';
         $html .= '<script>';
        $html .= 'jQuery(function() {';
        $html .= 'jQuery( "#form_end_date" ).datepicker({';
        $html .= 'changeMonth: true,';
        $html .= 'changeYear: true,';
        $html .= 'dateFormat: "' . $date_format . '"';
        $html .= '});';
        $html .= '});';
        $html .= '</script>';;
        
        $html .= '</tr><tr>';      
        $html .= '<td>';
        $html .= '<label class="ugf-label">Form Prefix HTML:</label></td>';
        $html .= '<td><textarea name="form_prefix_html" rows="6"  cols="40">' . stripslashes($form_prefix_html) . '</textarea>';
        $html .= '</td>';    
        
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Form Suffix HTML:</label></td>';
        $html .= '<td><textarea name="form_postfix_html" rows="6"  cols="40">' . stripslashes($form_postfix_html) . '</textarea>';
        $html .= '</td>';
        
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Captcha Enabled:</label></td>';
        $checked = '';
        if($captcha == 1) {
            $checked = 'checked';
        }
        $html .= '<td><input type="checkbox" name="captcha" id="captcha" value="1" '.$checked.'>';
        $html .= '</td>';
        
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Captcha Label:</label></td>';
        $html .= '<td><input type="text" name="captcha_msg" id="captcha_msg" size="40" value="' . stripslashes($captcha_msg) . '">';
        $html .= '</td>';
        
        
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Form Success Message:</label></td>';
        $html .= '<td><textarea name="form_sucess_msg" id="form_sucess_msg" cols="40" rows="5">' . stripslashes($form_sucess_msg) .'</textarea>';
        $html .= '</td>';
 
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<label class="ugf-label">Enable Form:</label></td>';
        $form_enable = '';
        if($row->enable_form == 1) {
            $form_enable = 'checked = "checked"';
        }
        $html .= '<td><input type="checkbox" name="enable_form" id="enable_form" value="1" '.$form_enable.'>';
        $html .= '</td>';       
        
        $html .= '</tr><tr>';
        $html .= '<td>';
        $html .= '<button type="button" class="button-primary btn-form-save" onclick="SubmitMainForm();">Save</button>';
        $html .= '</td>';
        $html .= '</tr>';
        
        
        $html .= '</tr><tr>';
        $html .= '<td id="status_msg">';
        $html .= '</td>';
        $html .= '</tr>';      
        
        $html .= '</table>';
        $html .= '</form>';
        
        print $html;
        die();
    }
    
    function form_delete_action() {
        global $wpdb;
        $form_id = $_POST['form_id'];
        $sql = "select id from  " .$wpdb->prefix . "ugf_fields where form_id = '%d'";
		$psql = $wpdb->prepare($sql,$form_id);
		$rows = $wpdb->get_results($psql);
		
		
        for($i = 0; $i < count($rows); ++$i) {
            $field_id = $rows[$i]->id;
            
            $sql = "delete from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d'";
			$psql = $wpdb->prepare($sql,$field_id);
			$wpdb->query($psql);
            
			
            $sql = "delete from " . $wpdb->prefix . "ugf_fields_validations where field_id = '%d'";
			$psql = $wpdb->prepare($sql,$field_id);
            $wpdb->query($psql);
            
            
        }
        
        $sql = "delete from " . $wpdb->prefix . "ugf_fields where form_id = '%d'";
		$psql = $wpdb->prepare($sql,$form_id);
        $wpdb->query($psql);
        
        $sql = "delete from " . $wpdb->prefix . "ugf_forms where id = '%d'";
		$psql = $wpdb->prepare($sql,$form_id);
        $wpdb->query($psql);
        print 1;
        die(0);
    }
    
    function ugf_form_save() {
        global $wpdb;
        $form_id = trim($_POST['form_id']);
        $form_name = stripslashes_deep(trim($_POST['form_name']));
        $post_method = stripslashes_deep(trim($_POST['post_method']));
       
        $post_type = trim($_POST['post_type']);
       
        $form_classes = stripslashes_deep(trim($_POST['form_classes']));
        $form_start_date = trim($_POST['form_start_date']);
        $form_end_date = trim($_POST['form_end_date']);
        $form_prefix_html = stripslashes(trim($_POST['form_prefix_html']));
        $form_postfix_html = stripslashes(trim($_POST['form_postfix_html']));
        $captcha = trim($_POST['captcha']);
        $captcha_msg = stripslashes_deep(trim($_POST['captcha_msg']));
        $form_sucess_msg = stripslashes_deep(trim($_POST['form_sucess_msg']));
        
        $enable_form = trim($_POST['enable_form']);
        
        $return = array();
        $err = 0;
        if($form_name == '') {
            $msg = 'Please enter form name';
            $err = 1;
        }
        
        if($err == 0) {
            $data = array();
            $data[] = "form_name = '%s'";
            $data[] = "post_method = '%s'";
        
            $data[] = "post_type = '%s'";
         
            $data[] = "form_prefix_html = '%s'";
            $data[] = "form_postfix_html = '%s'";
            $data[] = "form_classes = '%s'";
            $form_start_date = $this->dateToMysql($form_start_date);
            $data[] = "form_start_date = '%s'";
            $data[] = "captcha = '%s'";
            $data[] = "captcha_msg = '%s'";
            $data[] = "form_sucess_msg = '%s'";
            $data[] = "enable_form = '%s'";
            
            
            
            $form_end_date = $this->dateToMysql($form_end_date);
            $data[] = "form_end_date = '%s'";
            
            if($form_id > 0) {
                $sql = "update " .$wpdb->prefix . 'ugf_forms set ' . implode(",",$data) . " where id = '%d'";
				
				$psql = $wpdb->prepare($sql,$form_name,$post_method,$post_type,$form_prefix_html,$form_postfix_html,$form_classes,$form_start_date,$captcha,$captcha_msg,$form_sucess_msg,$enable_form,$form_end_date,$form_id);
				$msg = $wpdb->query($psql);
            }else {
                $sql = "insert into " .$wpdb->prefix . 'ugf_forms set ' . implode(",",$data);
				
				$psql = $wpdb->prepare($sql,$form_name,$post_method,$post_type,$form_prefix_html,$form_postfix_html,$form_classes,$form_start_date,$captcha,$captcha_msg,$form_sucess_msg,$enable_form,$form_end_date);
				$msg = $wpdb->query($psql);
            }
            
            $this->buildFormListView();
            die();
            
        }
        
        
        print $msg;
        die();
    }
    
}

new wpFormPlugin();

function GUID() {
    if (function_exists('com_create_guid') === true) {
        return trim(com_create_guid(), '{}');
    }

    return sprintf('%04X%04X-%04X-%04X-%04X-%04X%04X%04X', mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(16384, 20479), mt_rand(32768, 49151), mt_rand(0, 65535), mt_rand(0, 65535), mt_rand(0, 65535));
}
//[foobar]
function ugf_func( $atts ){
    //print_r($atts);
	print ugf_form($atts['id']);
}
add_shortcode( 'UGF', 'ugf_func' );


function ugf_form($form_id) {
    global $wpdb;
    
    $sql = "select * from " . $wpdb->prefix . "ugf_forms where id = '%d'";
	$psql = $wpdb->prepare($sql,$form_id);
    $row = $wpdb->get_row($psql);
    
    if(!$row->enable_form) {
        return;
    }
    $form_start_date = $row->form_start_date;
    $form_end_date = $row->form_end_date;
    $cdate = date('Y-m-d');
    $show = false;
    
    if($form_start_date == '0000-00-00' && $form_end_date == '0000-00-00') {
        $show = true;
    }else if($form_start_date != '0000-00-00' && $form_end_date != '0000-00-00') {
        if($form_start_date <= $cdate &&  $form_end_date >= $cdate) {
            $show = true;
        }
    }else if($form_start_date != '0000-00-00') {
        if($form_start_date <= $cdate) {
            $show = true;
        }
    }else if($form_end_date != '0000-00-00') {
        if($form_end_date >= $cdate) {
            $show = true;
        }
    }
    
    if($show == false){
        return;
    }
    
    $captcha_msg = $row->captcha_msg;
    if(!$captcha_msg) {
        $captcha_msg = 'Please Enter text shown in the image';
    }
    $sql = "select * from " . $wpdb->prefix . "ugf_fields where form_id = '%d' order by ordering asc";
	$psql = $wpdb->prepare($sql,$form_id);
    $fields_row = $wpdb->get_results($psql);

    
    /*
    $row['form_name'];
    $row['post_method'];
    $row['form_prefix_html'];
    $row['form_postfix_html'];
    $row['form_classes'];
    $row['form_start_date'];
    $row['form_end_date'];
    */
     
    $html = '';

    if($row->form_prefix_html) {
        $html .= $row->form_prefix_html;
    }
    
	if(isset($_SESSION['form_success'])){
        $html .= $_SESSION['form_success'];
        $_SESSION['form_success'] = '';
    }
    $html .= '<form action="' . $_SERVER['REQUEST_URI'] . '" id="' . $row->form_name . '"  method="' . $row->post_method . '" ';
    if($row->form_classes) {
        $html .= ' class="' . $row->form_classes .'" ';
    }
    
    $form_token = GUID();
    $_SESSION['form_token'] = $form_token;
    $html .= ' enctype="multipart/form-data">';
    
    $html .= '<input type="hidden" name="ugfform_id" value="'.$row->id.'">';
    $html .= '<input type="hidden" name="ugfform_token" value="'.$_SESSION['form_token'].'">';
    $html .= '<input type="hidden" name="ugfform_url" value="'.$_SERVER['REQUEST_URI'].'">';
    
    for($i =0; $i < count($fields_row); ++$i) {
        
        $field_type = $fields_row[$i]->field_type;
        $lbl_wrapper_class = $fields_row[$i]->lbl_wrapper_class;
        $field_wrapper_class = $fields_row[$i]->field_wrapper_class;
        $field_class = $fields_row[$i]->field_class;
        
        $field_id = $fields_row[$i]->id;
        
        $sql = "select * from " . $wpdb->prefix . "ugf_fields_attributes where field_id = '%d'";
		$psql = $wpdb->prepare($sql,$field_id);
        $attr_row = $wpdb->get_results($psql);
        
        $attr_array = array();
        for($k =0; $k< count($attr_row);++$k) {
            $attr_array[$attr_row[$k]->attribute_name] = $attr_row[$k]->attribute_value;
        }
    
        $sql = "select * from " . $wpdb->prefix . "ugf_fields_validations where field_id = '%d'";
        $psql = $wpdb->prepare($sql,$field_id);
		$validation_row = $wpdb->get_results($psql);
        
        
        
        if($field_type == 'text') {
        
            
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement.' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            $html .= '<input type="text" id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" name="ugf'.$fields_row[$i]->field_machine_name.'"';
            $html .= ' class="' . $field_class . '  fld-'.$fields_row[$i]->field_machine_name.'" ';
            
            if($attr_array['size'] > 0) {
                $html .= ' size="'.$attr_array['size'].'px" ';    
            }
            
            $html .= '>';
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            
            $html .= '</div>';
        }
        
        if($field_type == 'select') {
            
            
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement . ' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            $html .= '<select id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" name="ugf'.$fields_row[$i]->field_machine_name.'"';
            
            $html .= ' class="' . $field_class . '  fld-'.$fields_row[$i]->field_machine_name.'" ';
            for($k =0; $k < count($attr_row); ++$k) {
                
                if($attr_row[$k]->attribute_name == 'options') {
                    $options = $attr_row[$k]->attribute_value;
                }
            }
            $html .= '>';
            $options_arr = explode(chr(13),$options);
            
            if(isset($attr_array['first_row_blank']) && $attr_array['first_row_blank'] == 1) {
                $html .= '<option value="" ></option>';    
            }
            foreach($options_arr as $item) {
                $kv = explode("|",$item);
                if(trim($kv[1]) != '') {
                    if(trim($attr_array['default']) == trim($kv[0])) {
                        $html .= '<option value="' . trim($kv[0]) . '" selected="selected">' . trim($kv[1]) . '</option>';    
                    }else {
                        $html .= '<option value="' . trim($kv[0]) . '">' . trim($kv[1]) . '</option>';    
                    }
                    
                }
            }
            
            
            $html .= '</select>';
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            $html .= '</div>';

        }
        
        if($field_type == 'radio') {

            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement . ' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            
            
            for($k =0; $k < count($attr_row); ++$k) {

                if($attr_row[$k]->attribute_name == 'options') {
                    $options = $attr_row[$k]->attribute_value;
                }
            }
            
            
            $options_arr = explode(chr(13),$options);
            $default = '';
            
            if(isset($attr_array['default'])) {
                $default = $attr_array['default'];
            }
            foreach($options_arr as $item) {
                
                $kv = explode("|",$item);
                
                $html .= ' <div class="' .  $fields_row[$i]->field_machine_name . trim($kv[0])  . '">';
                
                $html .= '<input type="radio" id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . trim($kv[0]) . '" name="ugf' . $fields_row[$i]->field_machine_name . '"';
                
                $html .= ' class="' . $field_class . '  fld-'.$fields_row[$i]->field_machine_name . trim($kv[0]) . '" ';
                if($default == trim($kv[0])) {
                    $html .= ' checked="checked" ';
                }
                $html .= ' value="' . trim($kv[0]) . '">&nbsp;&nbsp;'  . trim($kv[1]) . '</div>';
            }
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            $html .= '</div>';            
        }
        if($field_type == 'checkbox') {
            
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement . ' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
    
            for($k =0; $k < count($attr_row); ++$k) {

                if($attr_row[$k]->attribute_name == 'options') {
                    $options = $attr_row[$k]->attribute_value;
                }
            }
            
            
            $options_arr = explode(chr(13),$options);
            
            foreach($options_arr as $item) {
                
                if($item != '') {
                    $kv = explode("|",$item);
                    
                    if(count($kv) > 0 && $kv[1] != '') {
                        $html .= ' <div class="' . $fields_row[$i]->field_machine_name .  trim($kv[0]) . '"> <input type="checkbox" id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . trim($kv[0]) . '" name="ugf' . $fields_row[$i]->field_machine_name . '[]"';
                        $html .= ' class="' . $field_class . '  fld-' . $fields_row[$i]->field_machine_name .  trim($kv[0]) . '" ';
                        $html .= 'value="' . trim($kv[0]) . '">&nbsp;&nbsp;' . trim($kv[1]) . '</div>';
                    }
                }
            }
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            $html .= '</div>';   
        }
        if($field_type == 'date') {
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement.' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            $html .= '<input type="text" id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" name="ugf'.$fields_row[$i]->field_machine_name.'"';
            
            $html .= ' class="'.$field_class.' fld-' . $fields_row[$i]->field_machine_name .'" ';
            
            $html .= '>';
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }

            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            $html .= '</div>';
            
            $date_format   = 'mm/dd/yy';
            
            if(isset($attr_array['date_format'])) {
                $date_format  = $attr_array['date_format'];
            }
            
            $html .= '<script>';
            $html .= 'jQuery(function() {';
            $html .= 'jQuery( "#ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" ).datepicker({';
            $html .= 'changeMonth: true,';
            $html .= 'changeYear: true,';
            $html .= 'dateFormat: "' . $date_format . '"';
            $html .= '});';
            $html .= '});';
            $html .= '</script>';;
        }
        
        if($field_type == 'file' || $field_type == 'image' ) {
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement.' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            $html .= '<input type="file" id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" name="ugf'.$fields_row[$i]->field_machine_name.'"';
            $html .= ' class="'.$field_class.'" ';
            for($k =0; $k < count($attr_row); ++$k) {

            }
            $html .= '>';
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            
            $html .= '</div>';
        }
                            
        if($field_type == 'textarea') {
            $rows = $attr_array['rows'];
            $cols = $attr_array['cols'];
            if(!$rows) {
                $rows = 3;
            }
            if(!$cols) {
                $cols = 30;
            }
            $html .= '<div class="ufg-control">';
            
            $html .= '<div class="ufg-control-label ugf-'.$fields_row[$i]->lbl_placement . ' ' . $lbl_wrapper_class . '">';
            $html .= '<label for="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" >';
            
            $html .= $fields_row[$i]->label_prefix;
            $html .= $fields_row[$i]->field_label;
            $html .= $fields_row[$i]->label_suffix;
            
            $html .= '</label>';
            $html .= '</div>';
            
            $html .= '<div class="ufg-control-input ' . $field_wrapper_class . '">';
            $html .= $fields_row[$i]->field_prefix_html;
            $html .= '<textarea id="ugf-' . $form_id . '-' . $fields_row[$i]->field_machine_name . '" name="ugf'.$fields_row[$i]->field_machine_name.'"';
            $html .= ' class="' . $field_class . ' ' . $fields_row[$i]->field_machine_name . '" ';
            $html .= ' rows="' . $rows . '" ';
            $html .= ' cols="' . $cols . '" ';
            
            for($k =0; $k < count($attr_row); ++$k) {

            }
            $html .= '>';
            $html .= '</textarea>';
            
            if(isset($_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error'][$fields_row[$i]->field_machine_name].'</div>';
            }
            
            $html .= $fields_row[$i]->field_postfix_html;
            $html .= '</div>';
            $html .= '</div>';
        }
    }
    
    $url = plugins_url() . '/data-collection-form';
    
    if($row->captcha == 1) {
        $html .= '<div class="ufg-control">';
        
        $html .= '<div class="captch-msg">'.$captcha_msg.'</div>';
        
        
        $html .= '<div class="captch-input">';
        $html .= '<input name="ugfcaptcha" type="text">';
        if(isset($_SESSION['ugform']['error']['ugfcaptcha'])) {
                $html .= '<div class="ugf-error">'.$_SESSION['ugform']['error']['ugfcaptcha'].'</div>';
        }
            
        $html .= '</div>';
        
        $html .= '<div class="captch-img">';
        $html .= '<img src="' . $url . '/captcha.php" />';
        $html .= '</div>';
        
        $html .= '</div>';
    }
    
    $html .= '<div class="ufg-control"><button type="submit" >Submit</button></div>';
    $html .= '</form>';
    
    if($row->form_postfix_html) {
        $html .= $row->form_postfix_html;
    }
    $_SESSION['ugform']['error'] = '';
    return $html;
}   
