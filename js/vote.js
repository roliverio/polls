var newUserDates = [];
var newUserTypes = [];

var maxVotes = 0;
var values_changed = false;

var tzOffset = new Date().getTimezoneOffset();

$.fn.switchClass = function(a, b) {
	this.removeClass(a);
	this.addClass(b);
	return this;
};

function updateCommentsCount(){
	//todo Update the Badgecounter
	$('#comment-counter').removeClass('no-comments');
	$('#comment-counter').text(parseInt($('#comment-counter').text()) +1);
	
}

function updateCounts(){
	maxVotes = 0;
	$('.column.total').each(function() {
		var yes = parseInt($(this).find('.yes').text());
		var no = parseInt($(this).find('.no').text());
		if(yes - no > maxVotes) {
			maxVotes = yes - no;
		}
	});
	var i = 0;
	$('.column.total').each(function() {
		var yes = parseInt($(this).find('.yes').text());
		var no = parseInt($(this).find('.no').text());
		$('#id_total_' + i++).toggleClass('icon-checkmark', yes - no === maxVotes);
	});
}


$(document).ready(function () {
	// count how many times in each date
	new Clipboard('.copy-link');
	
	$('.toggle-all').tooltip();
	$('.poll-cell').tooltip();
	
    $('.delete-poll').click(function(){
		deletePoll(this);
    });
 
    $('#switchDetails').click(function(){
		OC.Apps.showAppSidebar();
    });
	
    $('#closeDetails').click(function(){
		OC.Apps.hideAppSidebar();
    });
	
	
	
	$('.avatardiv').each(function(i, obj) {
		$(obj).avatar(obj.title, 32);
	});

	$('.time-slot').each(function() {
        var extendedDate = new Date($(this).attr("value").replace(/ /g,"T")+"Z"); //Fix display in Safari and IE

        $(this).children('.month').text(extendedDate.toLocaleString(window.navigator.language, {month: 'short'}));
        $(this).children('.day').text(extendedDate.toLocaleString(window.navigator.language, {day: 'numeric'}));
        $(this).children('.dayow').text(extendedDate.toLocaleString(window.navigator.language, {weekday: 'short'}));
        $(this).children('.time').text(extendedDate.toLocaleTimeString(window.navigator.language, {hour: 'numeric', minute:'2-digit', timeZoneName:'short'}));
        
 	});

	$('#submit_finish_vote').click(function() {
		var form = document.finish_vote;
		var ac = document.getElementById('user_name');
		if (ac !== null) {
			if(ac.value.length >= 3){
				form.elements.userId.value = ac.value;
			} else {
				alert(t('polls', 'You are not registered.\nPlease enter your name to vote\n(at least 3 characters).'));
				return;
			}
		}
		var check_notif = document.getElementById('check_notif');
		var newUserDates = [], newUserTypes = [];
		$(".cl_click").each(function() {
			if($(this).hasClass('no')) {
				newUserTypes.push(0);
			} else if ($(this).hasClass('yes')){
				newUserTypes.push(1);
			} else if($(this).hasClass('maybe')){
				newUserTypes.push(2);
			} else {
				newUserTypes.push(-1);
			}
			if (isNaN($(this).attr('data-value'))) {
				newUserDates.push($(this).attr('data-value'));
			} else {
				newUserDates.push(parseInt($(this).attr('data-value')));
			}
		});
		form.elements.dates.value = JSON.stringify(newUserDates);
		form.elements.types.value = JSON.stringify(newUserTypes);
		form.elements.receiveNotifications.value = (check_notif && check_notif.checked) ? 'true' : 'false';
		form.elements.changed.value = values_changed ? 'true' : 'false';
		form.submit();
	});

	$('#submit_send_comment').click(function(e) {
		e.preventDefault();
		var form = document.send_comment;
		var ac = document.getElementById('user_name_comm');
		if (ac !== null) {
			if(ac.value.length >= 3){
				form.elements.userId.value = ac.value;
			} else {
				alert(t('polls', 'You are not registered.\nPlease enter your name to vote\n(at least 3 characters).'));
				return;
			}
		}
		var comment = document.getElementById('commentBox');
		if(comment.value.trim().length <= 0) {
			alert(t('polls', 'Please add some text to your comment before submitting it.'));
			return;
		}
		var data = {
			pollId: form.elements.pollId.value,
			userId: form.elements.userId.value,
			commentBox: comment.value.trim()
		};
		$('.new-comment .icon-loading-small').show();
		$.post(form.action, data, function(data) {
			$('.comments .comment:first').after('<div class="comment"><div class="comment-header"><span class="comment-date">' + data.date + '</span>' + data.userName + '</div><div class="wordwrap comment-content">' + data.comment + '</div></div>');
			$('.new-comment textarea').val('').focus();
			$('.new-comment .icon-loading-small').hide();
			updateCommentsCount();
		}).error(function() {
			alert(t('polls', 'An error occurred, your comment was not posted...'));
			$('.new-comment .icon-loading-small').hide();
		});
	});

	$(".share input").click(function() {
		$(this).select();
	});
});

$(document).on('click', '.toggle-all, .cl_click', function() {
	values_changed = true;
	var $cl = "";
	var $toggle = "";
	if($(this).hasClass('yes')) {
		$cl = "no";
		$toggle= "yes";
	} else if($(this).hasClass('no')) {
		$cl = "maybe";
		$toggle= "no";
	} else if($(this).hasClass('maybe')) {
		$cl = "yes";
		$toggle= "maybe";
	} else {
		$cl = "yes";
		$toggle= "maybe";
	}
	if($(this).hasClass('toggle-all')) {
		$(".cl_click").attr('class', 'column cl_click poll-cell active ' + $toggle);
		$(this).attr('class', 'toggle-all ' + $cl);
	} else {
		$(this).attr('class', 'column cl_click poll-cell active ' + $cl);
	}
	$('.cl_click').each(function() {
		var yes_c = $('#id_y_' + $(this).attr('id'));
		var no_c = $('#id_n_' + $(this).attr('id'));
		$(yes_c).text(parseInt($(yes_c).attr('data-value')) + ($(this).hasClass('yes') ? 1 : 0));
		$(no_c).text(parseInt($(no_c).attr('data-value')) + ($(this).hasClass('no') ? 1 : 0));
	});
	updateCounts();
});

