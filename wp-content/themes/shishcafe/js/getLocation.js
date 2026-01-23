document.addEventListener("DOMContentLoaded", function() {
    // Check if the Geolocation API is supported by the browser.
    if (navigator.geolocation) {
        // If supported, ask the browser for the user's current position.
        // This will trigger a permission prompt for the user.
        navigator.geolocation.getCurrentPosition(showPosition, showError);
    } else {
        // Log a message if Geolocation is not supported.
        console.log("Geolocation is not supported by this browser.");
    }
});

/**
 * Success callback function that runs when the user allows location access.
 * @param {GeolocationPosition} position - The position object returned by the browser.
 */
function showPosition(position) {
    // Create an object to hold the location data.
    const userLocation = {
        latitude: position.coords.latitude,
        longitude: position.coords.longitude
    };

    // Log the user's location to the console.
    console.log("User's location:", userLocation);

    // The 'userLocation' variable now holds the coordinates and can be used elsewhere in your scripts.
}

/**
 * Error callback function that runs when there's an error getting the location.
 * @param {GeolocationPositionError} error - The error object.
 */
function showError(error) {
    // Log a different message depending on the error.
    switch(error.code) {
        case error.PERMISSION_DENIED:
            console.error("User denied the request for Geolocation.");
            break;
        case error.POSITION_UNAVAILABLE:
            console.error("Location information is unavailable.");
            break;
        case error.TIMEOUT:
            console.error("The request to get user location timed out.");
            break;
        case error.UNKNOWN_ERROR:
            console.error("An unknown error occurred while getting location.");
            break;
    }
}
