<form action="/local/templates/newtemplate/ajax/reviews.php" class="quickorder-form" method="POST">
    <input type="hidden" name="action" value="saveOrder">
    <input type="hidden" name="ENTITTY" value="reviews">
    <input type="hidden" name="tab" value="review">
    <input type="hidden" name="MARK" value="#MARK#">
    <input type="hidden" name="productID" value="#productID#">
    <div class="quickorder-row rating">
        <div class="star-block">
            <span class="quickorder-name required">Оцените товар </span>
            <span class="" style="display: flex;width: 140px;margin-left: 10px;">
                <span class="star-item" data-rate="0.5">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="1">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="1.5">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="2">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="2.5">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="3">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="3.5">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="4">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="4.5">
                    <i class="fa fa-star empty"></i>
                </span>
                <span class="star-item" data-rate="5">
                    <i class="fa fa-star empty"></i>
                </span>
            </span>
        </div>
        #MARK_ERROR#
    </div>
    <div class="quickorder-row">
        <div class="quickorder-name required">Ваше имя</div>
        <div class="quickorder-value">
            <input type="text" class="f-required" placeholder="Представьтесь, пожалуйста" name="fields[firstname]" value="#fields[firstname]#" data-pattern="[a-zA-Zа-яА-ЯЁё\'][a-zA-Zа-яА-Я-Ёё\' ]+[a-zA-Zа-яА-ЯЁё\']?">
            <span></span>
        </div>
    </div>

    <div class="quickorder-row">
        <div class="quickorder-name required">Комментарий</div>
        <div class="quickorder-value">
            <textarea data-pattern="[a-zA-Zа-яА-ЯЁё\w\d\s!?#@$%&*()-_+]+?" class="f-required text" name="fields[comment]" placeholder="Поделитесь вашими впечатлениями о товаре. Ваш отзыв поможет сделать выбор другим покупателям">#fields[comment]#</textarea>
            <span></span>
        </div>
    </div>

    <div class="quickorder-row">
        <div class="quickorder-name">Достоинства товара</div>
        <div class="quickorder-value">
            <textarea class="text smallarea" name="fields[advantages]" placeholder="Что вам понравилось в товаре">#fields[advantages]#</textarea>
            <span></span>
        </div>
    </div>

    <div class="quickorder-row">
        <div class="quickorder-name">Недостатки товара</div>
        <div class="quickorder-value">
            <textarea class="text smallarea" name="fields[disadvantages]" placeholder="Что вам не понравилось в товаре">#fields[disadvantages]#</textarea>
            <span></span>
        </div>
    </div>

    <em class="errormsg quickorder-value"></em>
    <div class="quickorder-submit">
        <div class="quickorder-button--wrap">
            <input type="submit" value="Оставить отзыв" class="quickorder-button" disabled="disabled">
            <span></span>
        </div>
    </div>
    <div class="quickorder-row" style="font-size: 12px;">
        <div class="quickorder-value quickorder-checkbox">
            <input type="checkbox" class="f-required agree" name="fields[agree]" checked>
            <label>
                <span class="checkbox"></span>
                Нажимая на кнопку «Оставить отзыв», вы соглашаетесь с условиями <a href="/o-kompanii/polzovatelskoe-soglashenie/" target="_blank">пользовательского соглашения</a> и <a href="/o-kompanii/popd/" target="_blank">политикой конфиденциальности</a>.
            </label>
        </div>
    </div>
    <div class="quickorder-row" style="font-size: 12px;">
        <span style="color: #46bfbf;">*</span> Поле обязательно для заполнения
    </div>
</form>

<script>
    $(document).ready(function(){
        let oneClickForm = new OneClickForm(".quickorder-body .reviews");
        $('.quickorder-form input, .quickorder-form textarea').each(function(){
            if($(this).hasClass('f-required') && $(this).val().length > 0){
                oneClickForm.check($(this));
            }
        });

        $('.quickorder-row.rating .star-item').hover(
            function(){
                let index = $(this).index();
                $('.quickorder-row.rating .star-item').each(function(){
                    if($(this).index() <= index){
                        $(this).find('.fa-star').removeClass('empty');
                    }
                });
                $(this).removeClass('empty');
            },
            function(){
                $('.quickorder-row.rating .star-item').each(function(){
                    if(!$(this).hasClass('checked')){
                        $(this).find('.fa-star').addClass('empty');
                    }
                });
            }
        );

        $(document).on('click', '.quickorder-row.rating .star-item', function(){
            let index = $(this).index(),
                collection = $('.quickorder-row.rating .star-item');

            $(this).closest('form').find('.f-required').each(function(){
                oneClickForm.check($(this));
            });


            $('[name=MARK]').val($(this).attr('data-rate'));
            collection.removeClass('checked').find('.fa-star').addClass('empty');
            collection.each(function(){
                if($(this).index() <= index){
                    $(this).addClass('checked').find('.fa-star').removeClass('empty');
                }
            });
        });
    })
</script>
