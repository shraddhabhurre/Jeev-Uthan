var currentEvent;
var modelID = 0;
jQuery(document).ready(function() {
    
  jQuery(document).mousemove(function(event) {
        currentEvent = event
    });   
})



function showFormEdit() {
    var data = {
		'action': 'form_action',
		'form_id': 0
	};
    
	jQuery.post(ajaxurl, data, function(response) {
        var b = new buildModel('Add New Form',response);
        b.size = 'vb';
        b.show();
	});
}

function EditMainForm(id) {
    var data = {
		'action': 'form_action',
		'form_id': id
	};
	jQuery.post(ajaxurl, data, function(response) {
        var b = new buildModel('Edit Form',response);
        b.size = 'vb';
        b.show();
	});    
}

function DeleteMainForm(id) {
    if(!confirm('Do you realy want to delete this form and all its fields.')) {
        return;
    }
    
    if(!confirm('All Fields will be deleted too.')) {
        return;
    }
    var data = {
		'action': 'form_delete_action',
		'form_id': id
	};
	jQuery.post(ajaxurl, data, function(response) {
        location.href = admin_url + "admin.php?page=ugf";
	});    
}


function extraAttrSave(form_id,id) {
    var data = jQuery("#ugf_extra_attr").serialize();
    
	jQuery.post(ajaxurl, data, function(response) {
        var data = {
            'action': 'ugf_fields_form_save_step2',
            'form_id': form_id,
            'id': id
        };
       	jQuery.post(ajaxurl, data, function(response) {
            jQuery("#ugf_form_fields").html(response);

        }); 
	});        
}



function SubmitMainForm() {
    var data = jQuery("#gravity_form_main").serialize();
   jQuery.post(ajaxurl, data, function(response) {
       if(response == "Please enter form name") {
         jQuery("#status_msg").html(response);      
       }else {
        jQuery("#form_list_view").html(response);
        jQuery(".ugf-model-close").trigger('click');
       }
       
       
 	});
}

function ManageFields(form_id,bn) {
   var data = {
		'action': 'ugf_manage_field',
		'form_id': form_id
	};
    
	jQuery.post(ajaxurl, data, function(response) {
        
        if(bn == undefined || bn == '') {
            var b = new buildModel('Manage Fields',response);
            b.show();
             field_sortable(form_id);
        }else {
            jQuery("#ugf_form_fields").html(response);
            field_sortable(form_id);
        }
	}); 
}

function field_sortable(form_id) {
    jQuery( "#sortable" ).sortable({
        start: function (event, ui) {
        },
        sort: function (event, ui) {
        },
        stop: function (event, ui) {
           
        },						
        change:  function (event, ui) {
        },
        update: function(event, ui) {
             var newOrder = jQuery(this).sortable('toArray').toString();
             jQuery.post('admin-ajax.php', {order:newOrder, action:'ugf_build_field_order', form_id:form_id});
        }
    });
}

function ugfAddNewField(form_id) {
    var data = {
		'action': 'ugf_fields_form',
		'form_id': form_id
	};
	jQuery.post(ajaxurl, data, function(response) {
        jQuery("#ugf_form_fields").html(response);
        ShowExtraForm('text');
        //var b = new buildModel('Add New Field',response);
        //b.show();
	});    
}

function ugfEditField(form_id,id,field_type) {
    var data = {
		'action': 'ugf_fields_form',
		'form_id': form_id,
        'id': id
	};
	jQuery.post(ajaxurl, data, function(response) {
        jQuery("#ugf_form_fields").html(response);
        ShowExtraForm(field_type,id);
	});        
}

function ugfDeleteField(form_id,id,field_type) {
    if(!confirm('Do you realy want to delete this field.')) {
        return;
    }
    var data = {
		'action': 'ugf_delete_field',
		'form_id': form_id,
        'id': id
	};
	jQuery.post(ajaxurl, data, function(response) {
         ManageFields(form_id,1);
        
	});        
}

function ugfFieldsFormSave(form_id) {
    var data = jQuery("#ugf_fields").serialize();
    
	jQuery.post(ajaxurl, data, function(response) {
        var data = JSON.parse(response);
        if(data != undefined) {
            if(data.status == false) {
                var msgs = data.msg;
                for(key in msgs) {
                    jQuery("#"+key).after('<br>' +msgs[key]);
                    jQuery("#"+key).css('border-color','#FF0000');
                }
            }else {
                 var data = {
                    'action': 'ugf_fields_form_save_step2',
                    'form_id': data.form_id,
                    'id': data.id
                };
            	jQuery.post(ajaxurl, data, function(response) {
                    jQuery("#ugf_form_fields").html(response);
                });  
            }
        }
        
	});    
}

function ugfFieldsFormSaveOnly(form_id) {
    var data = jQuery("#ugf_fields").serialize();
    
	jQuery.post(ajaxurl, data, function(response) {
        var data = JSON.parse(response);
        if(data != undefined) {
            if(data.status == false) {
                var msgs = data.msg;
                for(key in msgs) {
                    jQuery("#"+key).after('<br>' +msgs[key]);
                    jQuery("#"+key).css('border-color','#FF0000');
                }
            }else {
                ManageFields(form_id,1);
            }
        }
        
	});    
}


function UgfAddFieldAttr(field_id,id) {
    var ugf_attribute_name  = jQuery("#ugf_attribute_name").val();
    var ugf_attribute_value  = jQuery("#ugf_attribute_value").val();
    
    var data = {
        'action': 'ugf_fields_attribute_save',
        'field_id': field_id,
        'attr_id': id,
        'ugf_attribute_name':ugf_attribute_name,
        'ugf_attribute_value':ugf_attribute_value
    };
    jQuery.post(ajaxurl, data, function(response) {
        jQuery("#ugf_attr_lists").html(response);
    });  
}

function UgfDelFieldAttr(field_id,id) {
    var data = {
        'action': 'ugf_fields_attribute_del',
        'field_id': field_id,
        'attr_id': id,
    };
    jQuery.post(ajaxurl, data, function(response) {
        jQuery("#ugf_attr_lists").html(response);
    });    
}


function getShortCode(form_id) {
    var data = {
		'action': 'get_short_code',
		'form_id': form_id
	};
	jQuery.post(ajaxurl, data, function(response) {
        var b = new buildModel('Form Short Code',response);
        b.size = 's';
        b.show();
	});  
}

function ShowExtraForm(val,field_id) {
    var field_type = val;//jQuery(obj).val();
    var data = {
		'action': 'get_extra_form',
		'field_type': field_type,
        'field_id': field_id
	};
    
	jQuery.post(ajaxurl, data, function(response) {
        jQuery(".extra_fields").remove();
        jQuery("#extra_form_id").after(response);
	});  
}

function buildModel(header,body) {
    CloseModel(modelID);
    this.header = header;
    this.body = body;
    this.html = '';
    this.size = 'b';
    
    ++modelID;
    this.id = modelID;
   
}

buildModel.prototype.show = function() {
    
    var that = this;
    this.createHTML();
    jQuery('body').append(this.html);
    this.bindEvent();
    jQuery("#model-"+that.id).show('slow');
    
    var wh = parseFloat(jQuery(window).attr("innerHeight"));
    var ww = parseFloat(jQuery(window).attr("innerWidth"));
    var w = parseFloat(jQuery("#model-"+that.id).css("width"));
    var h = parseFloat(jQuery("#model-"+that.id).css("height"));
    if(that.size != 'vb') {
        var top = (wh - h)/2;
        var left = (ww - w)/2;
        jQuery("#model-"+that.id).css("top",top+'px');
        jQuery("#model-"+that.id).css("left",left+'px');
    }
    
    
    
    
}

function CloseModel(id) {
    jQuery("#model-"+id).remove();
}

buildModel.prototype.createHTML = function() {
    var that = this;
    
    
    that.html = '';
    if(that.size == 'b') {
        that.html += '<div class="ugf-model" id="model-'+that.id+'" style="display:none;">';
    }else if(that.size == 's') {
        that.html += '<div class="ugf-model-small"  id="model-'+that.id+'"  style="display:none;">';
    }else if(that.size == 'vb') {
        that.html += '<div class="ugf-model-vb" id="model-'+that.id+'"  style="display:none;">';
    }
    that.html += '<div class="ugf-model-close" onclick="CloseModel(\''+that.id+'\')">×</div>';
    that.html += '<div class="ugf-content">'
    that.html += '<div class="ugf-header">'
    that.html += this.header;
    that.html += '</div>';
    that.html += '<div class="ugf-body">'
    that.html += this.body;
    that.html += '</div>';
    
    that.html += '</div>';
    
    that.html += '</div>';
    
}

buildModel.prototype.bindEvent = function() {
    jQuery(".ugf-model-close").click(function() {
        //jQuery(".ugf-model").find('.ugf-content').html("");
        //jQuery(".ugf-model").remove()
        
    });
}
