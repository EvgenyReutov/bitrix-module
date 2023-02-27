function JSArtSelect(config) {
    this.config = config || {};

    this.params = {
        saveBtn: '#coment_save',
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
                if(data.status){
                    _this.modal.CloseDialog();
                    document.location.href = '/bitrix/admin/sale_discount_edit.php?lang=ru&'+_this.modal.PARAMS.content_post+'&sale_discount_active_tab=edit3';
                }else{
                    _this.modal.ShowError('Ошибка сохранения');
                }

                BX.closeWait();
            }
        });
    },
}