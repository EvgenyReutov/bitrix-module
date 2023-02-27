<form action="/local/templates/newtemplate/ajax/reviews.php" class="quickorder-form" method="POST">
    <input type="hidden" name="action" value="saveOrder">
    <input type="hidden" name="ENTITTY" value="question">
    <input type="hidden" name="tab" value="question">
    <input type="hidden" name="productID" value="#productID#">
    <div class="quickorder-row rating">
        <span style="font-size: 14px;font-weight: 400;color: #333;">Если вы хотите узнать дополнительную информацию о товаре — задайте свой вопрос. Мы ответим на него в ближайшее время.</span>
    </div>
    <div class="quickorder-row">
        <div class="quickorder-name required">Ваше имя</div>
        <div class="quickorder-value">
            <input type="text" class="f-required" placeholder="Представьтесь, пожалуйста" name="fields[firstname]" value="#fields[firstname]#" data-pattern="[a-zA-Zа-яА-ЯЁё\'][a-zA-Zа-яА-Я-Ёё\' ]+[a-zA-Zа-яА-ЯЁё\']?">
            <span></span>
        </div>
    </div>
    <div class="quickorder-row">
        <div class="quickorder-name required">Email</div>
        <div class="quickorder-value">
            <input type="email" class="f-required" placeholder="example@mail.ru" name="fields[email]" value="#fields[email]#" data-pattern="^[-._a-z0-9]+@(?:[a-z0-9][-a-z0-9]+\.)+[a-z]{2,6}$">
            <span></span>
        </div>
    </div>
    <div class="quickorder-row">
        <div class="quickorder-name required">Вопрос</div>
        <div class="quickorder-value">
            <textarea data-pattern="[a-zA-Zа-яА-ЯЁё\w\d\s!?#@$%&*()-_+]+?" class="f-required text" placeholder="Оставьте интересующий вас вопрос" name="fields[question]">#fields[question]#</textarea>
            <span></span>
        </div>
    </div>
    <em class="errormsg quickorder-value"></em>
    <div class="quickorder-submit">
        <div class="quickorder-button--wrap">
            <input type="submit" value="Задать вопрос" class="quickorder-button" disabled="disabled">
            <span></span>
        </div>
    </div>
    <div class="quickorder-row" style="font-size: 12px;">
        <div class="quickorder-value quickorder-checkbox">
            <input type="checkbox" class="f-required agree" name="fields[agree]" checked>
            <label>
                <span class="checkbox"></span>
                Нажимая на кнопку «Задать вопрос», вы соглашаетесь с условиями <a href="/o-kompanii/polzovatelskoe-soglashenie/" target="_blank">пользовательского соглашения</a> и <a href="/o-kompanii/popd/" target="_blank">политикой конфиденциальности</a>.
            </label>
        </div>
    </div>
    <div class="quickorder-row" style="font-size: 12px;">
        <span style="color: #46bfbf;">*</span> Поле обязательно для заполнения
    </div>
</form>
<script>
    $(document).ready(function(){
        let questionForm = new OneClickForm(".quickorder-body .question");
        $('.quickorder-form input, .quickorder-form textarea').each(function(){
            if($(this).hasClass('f-required') && $(this).val().length > 0){
                questionForm.check($(this));
            }
        });
    })
</script>
