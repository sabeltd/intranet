function showTime()
{
	time = new Date();
	hrs = time.getHours();
	mins = time.getMinutes();
	while (hrs.toString().length<2) hrs="0"+hrs;
	while (mins.toString().length<2) mins="0"+mins;
	str_time = hrs + ":" + mins;
	$("#clock").html(str_time);
	$("#time").val(str_time);
	setTimeout("showTime()", 1000);
}

$(document).ready(function() {
	showTime();

	if ($('.v-pin').length>0){
		if (!$('.v-pin').hasClass('chooser')) {
			digits = "0123456789";
	    	plus = "0123456789";
	    	for (i=0; i<6; i++){
	       		d= Math.floor(Math.random()*1000)%plus.length;
		        digits+=plus.substring(d, d+1);
	    	    plus = plus.substring(0, d)+plus.substring(d+1, plus.length);
		    }
		    arr_digits = digits.split('').sort(function(){ return Math.floor((Math.random()*50)%2)-1; });
		    digits = arr_digits.join('');
		    $('input[name="digits"]').val(digits);
		    $('table.v-pin td').each(function(){
		        $(this).html(arr_digits.shift());
		    });
		} else {
			// set up for choosing
			digits = "0123456789ABCDEF";
			i=0;
			$('table.v-pin td').each(function(){
		        $(this).html("&nbsp;");
		        digit = digits.substring(i,i+++1);
		        $(this).attr('data-value', digit);
		    });
			$('table.v-pin.chooser td').click(function(e){
				if ($(this).hasClass('selected')){
					return;
				}
				value = $('input[name="response"]').val()+$(this).attr('data-value');

				if (value.length>4){
					alert("You can only choose 4. Press 'Enter' to save your pin, or 'Clear' to change it.");
				} else {
					$(this).addClass('selected');
					$('input[name="response"]').val(value);
					$(this).html(value.length);
				}
			});
		}
	}
	if ($('.keypad').length>0){
	    $('table.keypad td, a.button').click(function(e){
			v = $(this).html();
			if (v == "Clear"){
				$('input[name="response"]').val("");
				$('.v-pin.chooser tr td').removeClass('selected').html('&nbsp;');
				return;
			}
			if (v == "Enter"){
				$('input[name="response"]').removeAttr('disabled');
				$('#login').submit();
				return;
			}
			if (v == "Cancel"){
				logOut();
				return;
			}
			
			v1 = $('input[name="response"]').val();
			$('input[name="response"]').val(v1+v);
	    });
	}
});

function logOut(){
	$('input[name="response"]').removeAttr('disabled')
	$('input[name="response"]').val("cancel");
	$('#login').submit();
}

var current_content, popup;

var close_callback = function(){ return false; }

function showDialog(dlgname, param){
	if (!popup){
		popup = document.createElement('div');
		$(popup).addClass('popup');
		$(popup).html('<a class="close-button">X</a><div class="popup-content"></div>');
		$('body').append(popup);
		$('.popup').fadeIn('fast');
		$('.close-button').click(function(e){
			closeDialog();
		});
		$(window).resize(resizePopup);
	}

	$('.popup-content').html('<div class="spinner"></div>');
	resizePopup();
	$.ajax({
        url: dlgname+'-dialogs.php?'+dlgname+'&param='+param+'&nocache='+ (new Date()).getTime(),
        success: function(data){
            $('.popup-content').html('');
            $('.popup-content').append(data);
        },
        error: function(data){
            $('.popup-content').html('');
            $('.popup-content').append(data);
        }
    });
}

function closeDialog(){
	if (close_callback() == true) return;
	$(popup).fadeOut('fast', function(){
		popup = null;
		$(this).remove();
	});
}

function resizePopup(){
	wh = $(window).innerHeight();
	$(popup).css({'height':Math.floor(wh *0.9), 'margin-top':Math.floor(wh*0.05)});
	$(popup).find('.popup-content, .spinner').css({'height':Math.floor(wh *0.9)});
}