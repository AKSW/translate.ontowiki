<?php 

header('Content-type: text/javascript; charset=UTF-8');

?>

RDFauthor.registerWidget({
    init: function () {
        this.disclosureID = 'disclosure-' + RDFauthor.nextID();
        this.languages    = languages;
        this.datatypes    = RDFauthor.literalDatatypes();
        this.namespaces   = RDFauthor.namespaces();

        //this.languages.unshift('');
    }, 
    
    ready: function () {
        var widget = this;
        var widID  = this.ID;
        
        // set field language
        if( typeof this.statement.objectLang() == 'undefined' ){
            google.language.detect($("#translate-value-"+widID).val(), function(result) {
                $("#translate-lang-" + widID + " option[value='" + result.language + "']").attr("selected", true);
            });    
        }else{
            $("#translate-lang-" + widID + " option[value='" + this.statement.objectLang() + "']").attr("selected", true);
        }
        
        // set field text
        
        if( typeof baseValues[this.statement.predicateURI()] == "undefined" ){
            baseValues[this.statement.predicateURI()] = {};
        }
        if(baseValues[this.statement.predicateURI()]["set"] != true) {
            if( baseLanguage.length > 0 ){    
                var elem = $("#rdfAuthorView select option:selected[value='" + baseLanguage + "']")[0];
                if( typeof elem != "undefined" ){
                    var id = $(elem).parent().attr("id");
                    id = "#"+id.replace("lang", "value");
                    baseValues[this.statement.predicateURI()]["value"] = $(id).val();
                    baseValues[this.statement.predicateURI()]["set"] = true;
                }else{
                    if($("#translate-value-"+widID).val().length > 0){
                        baseValues[this.statement.predicateURI()]["value"] = $("#translate-value-"+widID).val();
                        baseValues[this.statement.predicateURI()]["set"] = false;
                    }
                }
            }else{    
                baseValues[this.statement.predicateURI()]["value"] = $("#translate-value-"+widID).val();
                baseValues[this.statement.predicateURI()]["set"] = true;
            }
        }
        
        if( $("#translate-value-"+widID).val().length == 0 ){
            $("#translate-value-"+widID).val(baseValues[this.statement.predicateURI()]["value"]);
        }

        // disclosure button
        jQuery('#' + widget.disclosureID).click(function () {
            var close = $(this).hasClass('open') ? true : false;

            // update UI accordingly
            var button = this;
            if (close) {
                if (this.animate) {
                    $('.' + widget.disclosureID).fadeIn(250, function() {
                        $(button).removeClass('open').addClass('closed');
                    });
                } else {
                    $('.' + widget.disclosureID).show();
                    $(button).removeClass('open').addClass('closed');
                }
            } else {
                if (this.animate) {
                    $('.' + widget.disclosureID).fadeOut(250, function() {
                        $(button).removeClass('cosed').addClass('open');
                    });
                } else {
                    $('.' + widget.disclosureID).hide();
                    $(button).removeClass('cosed').addClass('open');
                }
            }
        });
        
        // literal options
        $('.translate-type .radio').click(function() {
            var jDatatypeSelect = $('#' + $(this).attr('name').replace('translate-type', 'translate-datatype')).eq(0);
            var jLangSelect     = $('#' + $(this).attr('name').replace('translate-type', 'translate-lang')).eq(0);

            if ($(this).val() == 'plain') {
                jDatatypeSelect.closest('div').hide();
                jLangSelect.closest('div').show();
                // clear datatype
                jDatatypeSelect.val('');
            } else {
                jDatatypeSelect.closest('div').show();
                jLangSelect.closest('div').hide();
                // clear lang
                jLangSelect.val('');
            }
        });
        
        // translate function
        $("#translate-lang-"+widID).change(function(){
            var toLang = $(this).val();
            var element = $("#translate-value-" + widID);
            var text = element.val();$("#translate-lang-"+widID)
            element.val("Translating..");
            google.language.detect(text, function(result) {
                google.language.translate( text , result.language, toLang, function(result){
                    element.val(result.translation);
                });
            });    
            return false;
        });
    }, 
    
    isLarge: function () {
        if (this.statement.hasObject()) {
            var objectValue = this.statement.objectValue();
            if (objectValue.search) {
                return ((objectValue.length >= 50) || 0 <= objectValue.search(/\n/));
            }
        }

        return false;
    }, 
    
    makeOptionString: function(options) {
        var optionString = '';

        for (var i = 0; i < options.length; i++) {            
            optionString += '<option value="' + options[i] + '"' + '>' + options[i] + '</option>';
        }

        return optionString;
    }, 
    
    element: function () {
        return jQuery('#translate-value-' + this.ID);
    }, 
    
    markup: function () {
        var areaConfig = {
            rows: (this.isLarge() ? '3' : '1'), 
            style: (this.isLarge() ? 'width:100%' : 'width:50%;height:1.3em;padding-top:0.2em'), 
            buttonClass: (this.isLarge()) ? 'disclosure-button-horizontal' : 'disclosure-button-vertical'
        }

        var areaMarkup = '\
            <div class="container translate-value" style="width:' + this.availableWidth() + 'px">\
                <nobr><textarea rows="' + String(areaConfig.rows) + '" cols="20" style="' + areaConfig.style + '" id="translate-value-' + 
                    this.ID + '">' + (this.statement.hasObject() ? this.statement.objectValue() : '') + '</textarea>\
                    &nbsp;<label for="translate-lang-' + this.ID + '">Language:\
                        <select id="translate-lang-' + this.ID + '" name="translate-lang-' + this.ID + '" class="">\
                            ' + this.makeOptionString(this.languages) + '\
                        </select>\
                    </label></nobr>\
            </div>';

        var markup = '\
            ' + areaMarkup + '\
            <div class="container translate-type util ' + this.disclosureID + '" style="display:none">\
                <label><input type="radio" class="radio" name="translate-type-' + this.ID + '"' 
                        + (this.statement.objectDatatype() ? '' : ' checked="checked"') + ' value="plain" />Plain</label>\
            </div>';

        return markup;
    }, 
    
    submit: function () {
        if (this.shouldProcessSubmit()) {
            // get databank
            var databank = RDFauthor.databankForGraph(this.statement.graphURI());
            
            // /* 
            var v = this.value();
            // */
            
            var somethingChanged = (
                this.statement.hasObject() && (
                    // existing statement should have been edited
                    this.statement.objectValue() !== this.value() || 
                    this.statement.objectLang() !== this.lang() || 
                    this.statement.objectDatatype() !== this.datatype()
                )
            );
            
            // new statement must not be empty
            var isNew = !this.statement.hasObject() && (null !== this.value());
            
            if (somethingChanged || this.removeOnSubmit) {
                databank.remove(this.statement.asRdfQueryTriple());
            }
            
            if ((null !== this.value()) && !this.removeOnSubmit && (somethingChanged || isNew)) {
                try {
                    var objectOptions = {};
                    if (null !== this.lang()) {
                        objectOptions.lang = this.lang();
                    } else if (null !== this.datatype()) {
                        objectOptions.datatype = this.datatype();
                    }
                    var newStatement = this.statement.copyWithObject({
                        value: this.value(), 
                        options: objectOptions, 
                        type: 'literal'
                    });
                    databank.add(newStatement.asRdfQueryTriple());
                } catch (e) {
                    var msg = e.message ? e.message : e;
                    alert('Could not save literal for the following reason: \n' + msg);
                    return false;
                }
            }
        }
        
        return true;
    }, 
    
    shouldProcessSubmit: function () {
        var t1 = !this.statement.hasObject();
        var t2 = null === this.value();
        var t3 = this.removeOnSubmit;
        
        return (!(t1 && t2) || t3);
    },
    
    type: function () {
        var type = $('input[name=translate-type-' + this.ID + ']:checked').eq(0).val();
        
        if ('' !== type) {
            return type;
        }
        
        return null;
    }, 
    
    lang: function () {
        var lang = $('#translate-lang-' + this.ID + ' option:selected').eq(0).val();
        if ((this.type() == 'plain') && ('' !== lang)) {
            return lang;
        }
        
        return null;
    }, 
    
    datatype: function () {
        var datatype = $('#translate-datatype-' + this.ID + ' option:selected').eq(0).val();
        if ((this.type() == 'typed') && ('' !== datatype)) {
            return datatype;
        }
        
        return null;
    }, 
    
    value: function () {
        var value = $('#translate-value-' + this.ID).val();
        if (String(value).length > 0) {
            return value;
        }
        
        return null;
    }
},  [{
        type: 'ObjectProperty', 
        name: 'property',
        values: schemas
    }]
);
