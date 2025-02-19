var interval = null;
var check_payment_interval = null;
var BASE_URL = "";
const ORDER_STATUS = {
	'pending': 0,
	'paid': 1,
	'under_paid': 2,
	'over_paid': 3,
	'expired': 4,
	'cancelled': 5,
}
$(document).ready(function () {
    ajaxCall();
    interval = setInterval(dateTimer, 1000);
    check_payment_interval = setInterval(ajaxCall, 10000);
    BASE_URL = $("#image_path").val();
});

function dateDiff(date) {
    var d2 = getUTCTime();
    // var d2 = new Date().getTime();
    var d1 = new Date(Date.parse(date)).getTime();
    var date_diff = d2 - d1;

    var years = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30 / 12);
    if (years > 0)
        return years + " year(s) ago";
    var months = Math.floor((date_diff) / 1000 / 60 / 60 / 24 / 30);
    if (months > 0)
        return months + " month(s) ago";
    var days = Math.floor((date_diff) / 1000 / 60 / 60 / 24);
    if (days > 0)
        return days + " day(s) ago";
    var hours = Math.floor((date_diff) / 1000 / 60 / 60);
    if (hours > 0)
        return hours + " hour(s) ago";
    var minutes = Math.floor((date_diff) / 1000 / 60);
    if (minutes > 0)
        return minutes + " minute(s) ago";
    var seconds = Math.floor((date_diff) / 1000);
    if (seconds > 0)
        return seconds + " second(s) ago";
    return "A moment ago";
}

function dateTimer() {
    if ($("#expire_on").val() != '') {

        var current = getUTCTime();
        var expire = new Date($("#expire_on").val()).getTime();
        var date_diff = expire - current;
        var hours = Math.floor(date_diff / (1000 * 60 * 60));
        var minutes = Math.floor((date_diff % (1000 * 60 * 60)) / (1000 * 60));
        var seconds = Math.floor((date_diff % (1000 * 60)) / 1000);
        console.log(hours, minutes, seconds);
        if (hours < 0 && minutes < 0 && seconds < 0) {
            var order_id = $("#order_id").val();
            window.open("index.php?controller=invoice&fc=module&module=coinremitter&order_id=" + order_id + "&action=cancel", "_SELF");
            return;
        } else {
            $("#ehours").html(('0' + hours).slice(-3));
            $("#eminutes").html(('0' + minutes).slice(-2));
            $("#eseconds").html(('0' + seconds).slice(-2));
        }
    }
}

function ajaxCall() {
    var address = $("#address").val();
    var coin = $("#coin").val();
    $.ajax({
        url: "index.php?controller=paymenthistory&fc=module&module=coinremitter",
        type: "POST",
        data: { address, coin },
    }).done(function (result) {
        result = JSON.parse(result);
        if (!result.flag) {
            clearInterval(interval);
            clearInterval(check_payment_interval);
            return;
        }
        const resData = result.data
        $("#paid-amt").text(resData.paid_amount + " " + resData.coin_symbol);
        $("#pending-amt").text(resData.pending_amount + " " + resData.coin_symbol);
        if (resData.status_code == ORDER_STATUS.paid || resData.status_code == ORDER_STATUS.over_paid) {
            clearInterval(interval);
            clearInterval(check_payment_interval);
            window.open("index.php?controller=invoice&fc=module&module=coinremitter&order_id=" + resData.order_id + "&action=success", "_SELF");
            return;
        }
        if (resData.status_code == ORDER_STATUS.expired) {
            clearInterval(interval);
            clearInterval(check_payment_interval);
            window.open("index.php?controller=invoice&fc=module&module=coinremitter&order_id=" + resData.order_id + "&action=cancel", "_SELF");
            return;
        }
        console.log("Inside");
        if (resData.status_code == ORDER_STATUS.pending) {
            
            if (Object.keys(resData.transactions).length) {
                clearInterval(interval);
                $("#paymentStatus").empty();
                $("#timerStatus").html("<span>Payment Status : " + resData.status + "</span>");
            } else {
                $("#expire_on").val(resData.expire_on);
                $("#paymentStatus").html("<span style='margin-top: 5px;'>Awaiting Payment</span><div></div>");
                if($("#timerStatus").html() == ''){
                    $("#timerStatus").html('<span>Your order expired in</span><ul><li><span id="ehours">00</span></li><li><span id="eminutes">00</span></li><li><span id="eseconds">00</span></li></ul>');
                }
            }
        }else{
            $("#timerStatus").html("<span>Payment Status : " + resData.status + "</span>");
        }

        var paymenthistory = '<div class="cr-plugin-history-box no-history-found"><div class="cr-plugin-history"><div class="cr-plugin-history-des" style="text-align: center;"><p>No Transaction Found</p></div></div></div>';
        if (Object.keys(resData.transactions).length > 0) {
            paymenthistory = '';
            for (const key in resData.transactions) {
                if (Object.prototype.hasOwnProperty.call(resData.transactions, key)) {
                    const payment = resData.transactions[key];
                    var confirmations = '';
                    if (payment.status_code == 1)
                        confirmations = '<div class="cr-plugin-history-ico"><img src="' + BASE_URL + 'check.png" title="Payment Confirmed"/></div>';
                    else
                        confirmations = '<div class="cr-plugin-history-ico"><img src="' + BASE_URL + 'error.png" title="' + payment.confirmations + ' confirmation(s)"/></div>';
        
                    paymenthistory += '<div class="cr-plugin-history-box"><div class="cr-plugin-history">' + confirmations + '<div class="cr-plugin-history-des"><span><a href="' + payment.explorer_url + '" target="_blank">' + payment.txid.slice(0,16) + '...</a></span><p>' + payment.amount + ' ' + resData.coin_symbol + '</p></div><div class="cr-plugin-history-date"><span title="' + payment.date + ' (UTC)">' + dateDiff(payment.date) + '</span></div></div></div>';
                }
            }
        }
        $("#cr-plugin-history-list").html(paymenthistory);
        return true;
    });
}


$(".copyToClipboard").click(function () {
    $(".cr-plugin-copy").fadeIn(1000).delay(1500).fadeOut(1000);
    var value = $(this).attr("data-copy-detail");
    var $temp = $("<input>");
    $("body").append($temp);
    $temp.val(value).select();
    document.execCommand("copy");
    $temp.remove();
});

function getUTCTime() {
    var tmLoc = new Date();
    return tmLoc.getTime() + tmLoc.getTimezoneOffset() * 60000;
}