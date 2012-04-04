$(document).ready(function() {
	// get location
	navigator.geolocation.getCurrentPosition(uploadCoords,
	                                         geoError,
	                                         {maximumAge:30000});

});

function uploadCoords(position) {
	var latitude = position.coords.latitude;
	var longitude = position.coords.longitude;

	$('#latitude').val(latitude);
	$('#longitude').val(longitude);

	// if location data age is not stale in this app
    $.ajax({
        url: 'async/post_checkin.php',
		type: 'POST',
		data: {
			latitude: latitude,
			longitude: longitude
		},
        success: handleCheckInResponse
    });
}

function geoError(error) {
	// sadface
}

function handleCheckInResponse(data) {
  if (data == '1') {
    // refresh
		window.location = './clues.php';
	}
}
