<?php

namespace Instrum\Main;

use Bitrix\Main\Loader;

class ProductSubscribe
{
    const SUBSCRIBE_FORM_ID = 2;
    const COUNT_RECORDS_ONCE = 200;
    const ITERATION_LIMIT = 50;

    public $productId = null;
    public $inactiveFieldId = null;
    public $subscrFilter = null;

    public function __construct()
    {
        Loader::includeModule('iblock');
        Loader::includeModule('form');

        $this->setInactiveField();
    }

    public function setInactiveField()
    {
        if (\CForm::GetDataByID(self::SUBSCRIBE_FORM_ID,
            $form,
            $questions,
            $answers,
            $dropdown,
            $multiselect))
        {
            foreach ($questions as $key => $item) {
                if ($item['TITLE'] == 'active'){
                    $this->inactiveFieldId = $item['ID'];
                    break;
                }
            }
            if (!$this->inactiveFieldId){
                throw new \Exception('inactive form result field not defined');
            }
        }
    }

    public function sendUserMessage($email)
    {
        $product = $this->getProductById($this->productId);
        $result = \CEvent::Send(
            'SALE_SUBSCRIBE_PRODUCT',
            's1',
            [
                'EMAIL' => $email,
                'PAGE_URL' => $product['DETAIL_PAGE_URL'],
                'NAME' => $product['NAME']
            ]
        );
        return $result;
    }

    public function getProductById($productId)
    {
        $result = [];
        $product = \CIBlockElement::GetList(
            [],
            [
                'IBLOCK_ID' => CATALOG_IB,
                'ID' => $productId
            ])
            ->GetNext();

        if ($product) {
            $result = $product;
        }
        return $result;
    }

    public function getSubscribeFormResults()
    {
        $result = [
            'recordIds' => [],
            'emails' => [],
        ];

        $rsResults = \CFormResult::GetList(self::SUBSCRIBE_FORM_ID,
            ($by='s_timestamp'),
            ($order='desc'),
            $this->subscrFilter,
            $is_filtered,
            'N',
            self::COUNT_RECORDS_ONCE
        );

        while ($arResult = $rsResults->Fetch())
        {
            $arrColumns = $arrAnswers = $arrAnswersVarname = [];
            \CForm::GetResultAnswerArray(
                self::SUBSCRIBE_FORM_ID,
                $arrColumns,
                $arrAnswers,
                $arrAnswersVarname,
                ['RESULT_ID' => $arResult['ID']]);

            foreach ($arrAnswers as $answerId => $item) {
                foreach ($item as $fieldId => $field) {
                    if ($field[$fieldId]['TITLE'] == 'E-mail' && $field[$fieldId]['USER_TEXT']) {
                        $result['emails'][] = $field[$fieldId]['USER_TEXT'];
                        break;
                    }
                }
            }

            $result['recordIds'][] = $arResult['ID'];
        }
        $result['emails'] = array_unique($result['emails']);
        return $result;
    }

    public function makeFormResultInactive($resultId)
    {
        // обновим ответ на вопрос
        $arVALUE = [];
        $FIELD_SID = 'SIMPLE_QUESTION_2_ACTIVE'; // символьный идентификатор вопроса
        $ANSWER_ID = $this->inactiveFieldId; // ID поля ответа
        $arVALUE[$ANSWER_ID] = 0;
        \CFormResult::SetField($resultId, $FIELD_SID, $arVALUE);
    }

    public function makeSubscribeFilter()
    {
        // фильтр по вопросам
        $arFields = [];

        $arFields[] = [
            'CODE'              => 'SIMPLE_QUESTION_2_PRODUCT_ID',       // код поля по которому фильтруем
            'FILTER_TYPE'       => 'integer',       // фильтруем по числовому полю
            'VALUE'             => $this->productId,   // значение по которому фильтруем
            'PART'              => 0                // прямое совпадение со значением (не интервал)
        ];
        $arFields[] = [
            'CODE'              => 'SIMPLE_QUESTION_2_ACTIVE',       // код поля по которому фильтруем
            'FILTER_TYPE'       => 'integer',       // фильтруем по числовому полю
            'VALUE'             => 1,   // значение по которому фильтруем
            'PART'              => 0                // прямое совпадение со значением (не интервал)
        ];

        $arFilter = [
            'FIELDS' => $arFields
        ];

        $this->subscrFilter = $arFilter;
    }

    public function countRecords()
    {
        $rsResults = \CFormResult::GetList(self::SUBSCRIBE_FORM_ID,
            ($by='s_timestamp'),
            ($order='desc'),
            $this->subscrFilter,
            $is_filtered,
            'N',
            self::COUNT_RECORDS_ONCE
        );
        return $rsResults->SelectedRowsCount();
    }

    public function sendProductAvailableMessage($productId)
    {
        if (!$productId){
            throw new \Exception('productId not defined');
        }
        $this->productId = $productId;

        $this->makeSubscribeFilter();

        $j = 0;
        while ($j < self::ITERATION_LIMIT && $this->countRecords()) {
            $j++;
            $subscribeResults = $this->getSubscribeFormResults();

            if (count($subscribeResults['recordIds'])) {
                foreach ($subscribeResults['emails'] as $email) {
                    $this->sendUserMessage($email);
                }
                foreach ($subscribeResults['recordIds'] as $recordId) {
                    $this->makeFormResultInactive($recordId);
                }
            }
        }
    }
}