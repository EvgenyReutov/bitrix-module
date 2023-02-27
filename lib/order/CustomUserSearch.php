<?php
/**
 * Created by PhpStorm.
 * User: aleks.omelich
 * Date: 25/11/2019
 * Time: 23:18
 */

namespace Instrum\Main\Order;
class CustomUserSearch
{

    function handleEvent()
    {
        return array("BLOCKSET" => "OrderEdit",
            "getScripts" => array('\Instrum\Main\Order\CustomUserSearch', "orderScripts"),
        );
    }

    function orderScripts()
    {
        return "<script>var oldfunc = BX.Sale.Admin.OrderBuyer.showChooseBuyerWindow;
            BX.Sale.Admin.OrderBuyer.showChooseBuyerWindow = function (languageId) {
            var currentWindow = window.open(
            '/local/tools/user_search.php?sessid='+BX.bitrix_sessid()+'&lang='+languageId+'&FN='
            +BX.Sale.Admin.OrderEditPage.formId+'&FC=USER_ID',
            '',
            'scrollbars=yes,resizable=yes,width=840,height=500,top='+Math.floor((screen.height - 840)/2-14)+',left='+Math.floor((screen.width - 760)/2-5)
            );
            currentWindow.onunload = function(){
            setTimeout(function() {
            BX.Sale.Admin.OrderAjaxer.sendRequest(
            BX.Sale.Admin.OrderEditPage.ajaxRequests.refreshOrderData(),true);
            }, 1000);
            }
            };</script>";
    }
}