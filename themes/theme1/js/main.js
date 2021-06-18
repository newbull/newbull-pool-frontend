function search_block(url_path) {
	var input_search_block = $('#input_search_block').val();
	if (!input_search_block) {
		return;
	}
	// console.log(url_path);
	location.href = url_path + input_search_block;
}

function get_nextdiff() {
	$.ajax({
		url: ajax_url,
		type: 'POST',
		dataType: 'json',
		data: {
			action: "get_nextdiff"
		},
		success: function (data) {
			// console.log(data);
			if (data.s) {
				if (data.d) {
					$('#nextdiff').text(data.d);
					$('.counter').countUp({
						delay: 10,
						time: 1000
					});
				}
			} else {
				// console.log(data);
				$('#nextdiff').text(data.e);
			};
		},
		error: function (XMLHttpRequest, textStatus, errorThrown) {
			console.log(XMLHttpRequest);
			console.log(textStatus);
			console.log(errorThrown);
			$('#nextdiff').text(textStatus);
		},
		complete: function (XMLHttpRequest, textStatus) {
			//console.log(XMLHttpRequest);
			//console.log(textStatus);
		}
	});
}

function get_memory_pool() {
	$.ajax({
		url: ajax_url,
		type: 'POST',
		dataType: 'json',
		data: {
			action: "get_memory_pool"
		},
		success: function (data) {
			// console.log(data);
			if (data.s) {
				if (data.d) {
					$('#memory_pool_list_tbody').html("");
					var html = "";
					$.each(data.d, function (index, obj) {
						html = '<tr><td class="text-start">' +
							'<a class="text-info" href="' + explore_url_tx + obj.tx + '">' + obj.tx + '</a>' +
							'</td>' +
							'<td class="text-start" colspan="2">' +
							'<table class="table table-borderless table-sm"><tbody>';
						$.each(obj.vout, function (index, vout) {
							html += '<tr><td class="text-start" style="width:40%">' + vout.value + ' ' + symbol + '</td><td class="text-start">';
							$.each(vout.addresses, function (index, address) {
								html += address + '<br>';
							});
							html += '</td></tr>';
						});
						html += '</tbody></table>' +
							'</td></tr>';
						$('#memory_pool_list_tbody').append(html);
					});
				} else {
					$('#memory_pool_list_tbody').html('<tr><td class="text-start" colspan="3">Memory pool is currently empty.</td></tr>');
				}
			} else {
				// console.log(data);
				$('#memory_pool_list_tbody').html('<tr><td class="text-start" colspan="3">' + data.e + '</td></tr>');
			};
		},
		error: function (XMLHttpRequest, textStatus, errorThrown) {
			console.log(XMLHttpRequest);
			console.log(textStatus);
			console.log(errorThrown);
			$('#memory_pool_list_tbody').html('<tr><td class="text-start" colspan="3">' + textStatus + '</td></tr>');
		},
		complete: function (XMLHttpRequest, textStatus) {
			//console.log(XMLHttpRequest);
			//console.log(textStatus);
		}
	});
}

var clocktimeinterval_nextdiffremaining = null;

function get_baseinfo() {
	$('#rpcstatus').text('');
	$.ajax({
		url: ajax_url,
		type: 'POST',
		dataType: 'json',
		data: {
			action: "get_baseinfo"
		},
		success: function (data) {
			// console.log(data);
			if (data.s) {
				if (data.d) {
					$('#hashrate').text(data.d.hashrate);
					$('#difficulty').text(data.d.difficulty + ' (' + data.d.nextdiff_blocks + ')');
					$('#chain').text(data.d.chain);
					$('#version').text(data.d.version);
					if (data.d.healthy == "Yes") {
						$('#healthy').html('<span class="px-2 py-1 bg-success text-white rounded-1">' + data.d.healthy + '</span>');
					} else {
						$('#healthy').html('<span class="px-2 py-1 bg-danger text-white rounded-1">' + data.d.healthy + '</span>');
					}
					$('#blocks').text(data.d.blocks);
					// $('#clockdiv').data('start', data.d.nextdiff_timeline);
					run_clock('clockdiv', data.d.nextdiff_timeline);
				} else {
					$('#rpcstatus').text('fail');
				}
			} else {
				// console.log(data);
				$('#rpcstatus').text(data.e);
			};
		},
		error: function (XMLHttpRequest, textStatus, errorThrown) {
			console.log(XMLHttpRequest);
			console.log(textStatus);
			console.log(errorThrown);
			$('#rpcstatus').text(textStatus);
		},
		complete: function (XMLHttpRequest, textStatus) {
			//console.log(XMLHttpRequest);
			//console.log(textStatus);
		}
	});
}

// CountDown Js
function time_remaining(endtime) {
	var t = Date.parse(endtime) - Date.parse(new Date(Date.UTC()));
	if (t < 0) {
		t = 0;
	}
	var seconds = Math.floor((t / 1000) % 60);
	var minutes = Math.floor((t / 1000 / 60) % 60);
	var hours = Math.floor((t / (1000 * 60 * 60)) % 24);
	var days = Math.floor(t / (1000 * 60 * 60 * 24));
	return {
		'total': t,
		'days': days,
		'hours': hours,
		'minutes': minutes,
		'seconds': seconds
	};
}

function time_remaining2(remaining_seconds) {
	// var t = Date.parse(endtime) - Date.parse(new Date(Date.UTC()));
	var t = remaining_seconds;
	if (t < 0) {
		t = 0;
	}
	var seconds = Math.floor(t % 60);
	var minutes = Math.floor((t / 60) % 60);
	var hours = Math.floor((t / (60 * 60)) % 24);
	var days = Math.floor(t / (60 * 60 * 24));
	return {
		'total': t,
		'days': days,
		'hours': hours,
		'minutes': minutes,
		'seconds': seconds
	};
}

function run_clock(id, endtime) {
	// console.log(endtime);
	clearInterval(clocktimeinterval_nextdiffremaining);
	clocktimeinterval_nextdiffremaining = null;
	var clock = document.getElementById(id);
	if (clock == null) {
		return;
	}

	// var start = $(clock).data("start");
	// var end = $(clock).data("end");
	// var endtime = start;

	// get spans where our clock numbers are held
	var days_span = clock.querySelector('.days');
	var hours_span = clock.querySelector('.hours');
	var minutes_span = clock.querySelector('.minutes');
	var seconds_span = clock.querySelector('.seconds');

	function update_clock() {
		// if (start && Date.parse(start) >= Date.parse(new Date())) {
		// 	$('#clock_status').html('<span style="color:yellow;">Start In</span>');
		// 	endtime = start;
		// } else if (end && Date.parse(end) >= Date.parse(new Date())) {
		// 	$('#clock_status').html('<span style="color:orange;">End In</span>');
		// 	endtime = end;
		// } else {
		// 	$('#clock_status').html('<span style="color:greenyellow;">Ended Successfully</span>');
		// 	$('#round_balance').html("");
		// 	endtime = new Date();
		// }
		// if (Date.parse(endtime) < Date.parse(new Date())) {
		// 	endtime = new Date();
		// }

		var t = time_remaining2(endtime--);
		// console.log(t);

		if (t.total <= 0) {
			// clearInterval(timeinterval);
			t = {
				'total': 0,
				'days': 0,
				'hours': 0,
				'minutes': 0,
				'seconds': 0
			};
		}

		// update the numbers in each part of the clock
		days_span.innerHTML = t.days;
		hours_span.innerHTML = ('0' + t.hours).slice(-2);
		minutes_span.innerHTML = ('0' + t.minutes).slice(-2);
		seconds_span.innerHTML = ('0' + t.seconds).slice(-2);
	}
	update_clock();
	clocktimeinterval_nextdiffremaining = setInterval(update_clock, 1000);
}