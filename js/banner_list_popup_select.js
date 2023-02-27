function JSArtSelect(config) {
    this.config = config || {};    
    this.params = {
        saveBtn: '#' + this.config.saveBtn,
        eventName: 'jSArtSelect'
    };
    this.init();
}

JSArtSelect.prototype = {
    constructor: JSArtSelect,
    init: function () {
        this.bindEvents();

        this.modal = new BX.CDialog(this.config);
        this.modal.Show();
    },
    unbind: function(){
        $(document).unbind('.' + this.params.eventName);
    },
    bindEvents: function () {
        this.unbind();
        $(document).on('click.' + this.params.eventName, this.params.saveBtn, this.save.bind(this));
        $(document).on('click.' + this.params.eventName, '.expand-group', function () {
            let _this = $(this);            
            let group = _this.parents('tr[data-group-id]').attr('data-group-id');
            $('[data-id="table_'  + group + '"]').toggleClass('active');        
        });
        $(document).on('change.' + this.params.eventName, "#tree :checkbox", function () {
            $(this).parents('.category_node').find(":checkbox:first").not(this).prop('checked', false);
            $(this).parent().find(":checkbox").not(this).prop('checked', false);
            let name = $(this).attr('name');
            if (name === 'CATEGORY[all]') {
                $(this).parents('tbody').find(":checkbox").not(this).prop('checked', false);
            } else {
                $(".all_categories").prop('checked', false);
            }
        });
    },
    save: function (e) {
        e.preventDefault();

        let _this = this,
            formData = $('#form_import_element').serialize();

        BX.showWait();
        $.ajax({
            url: _this.modal.PARAMS.content_url,
            data: _this.modal.PARAMS.content_post + '&save=Y&' + formData,
            dataType: 'json',
            type: 'post',
            success: function (data) {
                if(typeof data.error != 'undefined'){
                    _this.modal.ShowError(data.error);
                }else if(typeof data.dump != 'undefined'){
                    console.log(data.dump);
                }else{
                    _this.modal.CloseDialog();
                    $('#tab_cont_edit3').trigger('click');
                    $('[name='+(data.NOT ? "NOT_SHOW_PAGE" : "SHOW_PAGE")+']').text(data.urls.join("\n"));
                }
                BX.closeWait();
            }
        });
    },
}