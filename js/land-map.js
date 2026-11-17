/*
|--------------------------------------------------------------------------
| ENERGYMATCH - LAND MAP
|--------------------------------------------------------------------------
|
| - Click / drag the pin  -> coordinates are filled in AND the
|                            "Land location" field is filled in
|                            automatically (reverse geocoding).
| - Type a land location  -> the map moves there and the pin is placed
|                            (forward geocoding). The user can then drag
|                            the pin to the exact spot of the field.
| - Type coordinates      -> the pin moves there (as before).
|
| Geocoding: OpenStreetMap Nominatim (free, no API key).
| Usage policy: max 1 request/second, so requests are debounced.
|
*/

document.addEventListener("DOMContentLoaded", function () {
    const mapElement = document.getElementById("landMap");
    const latitudeInput = document.getElementById("latitude");
    const longitudeInput = document.getElementById("longitude");
    const locationInput = document.getElementById("land_location");
    const selectedPoint = document.getElementById("mapSelectedPoint");
    const statusElement = document.getElementById("mapGeocodeStatus");

    if (!mapElement || !latitudeInput || !longitudeInput || typeof L === "undefined") return;

    const NOMINATIM_URL = "https://nominatim.openstreetmap.org";
    const SEARCH_DELAY_MS = 1200;   // wait until the user stops typing
    const MIN_SEARCH_LENGTH = 3;
    const language = mapElement.dataset.lang || "el";

    const messages = {
        searching: mapElement.dataset.msgSearching || "",
        found: mapElement.dataset.msgFound || "",
        notFound: mapElement.dataset.msgNotFound || "",
        addressFilled: mapElement.dataset.msgAddressFilled || "",
        error: mapElement.dataset.msgError || ""
    };

    const savedLat = parseFloat(latitudeInput.value);
    const savedLon = parseFloat(longitudeInput.value);
    const hasSavedCoordinates = Number.isFinite(savedLat) && Number.isFinite(savedLon);
    const map = L.map("landMap").setView(hasSavedCoordinates ? [savedLat, savedLon] : [39.0742, 21.8243], hasSavedCoordinates ? 15 : 6);

    L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
        maxZoom: 19,
        attribution: "&copy; OpenStreetMap contributors"
    }).addTo(map);

    let marker = null;
    let searchTimer = null;
    let reverseTimer = null;
    let lastSearchedText = "";
    let requestCounter = 0;   // used to ignore out-of-date responses

    /* =========================================
       STATUS MESSAGE UNDER THE MAP
    ========================================= */

    function showStatus(text, type) {
        if (!statusElement) return;
        statusElement.textContent = text || "";
        statusElement.className = "map-geocode-status" + (type ? " " + type : "");
    }

    /* =========================================
       PIN + COORDINATES
       fillAddress = true -> also fill "Land location"
    ========================================= */

    function updatePoint(lat, lon, moveMap, fillAddress) {
        const cleanLat = Number(lat).toFixed(7);
        const cleanLon = Number(lon).toFixed(7);
        latitudeInput.value = cleanLat;
        longitudeInput.value = cleanLon;

        if (!marker) {
            marker = L.marker([lat, lon], { draggable: true }).addTo(map);
            marker.on("dragend", function () {
                const point = marker.getLatLng();
                updatePoint(point.lat, point.lng, false, true);
            });
        } else {
            marker.setLatLng([lat, lon]);
        }

        if (moveMap) map.setView([lat, lon], Math.max(map.getZoom(), 15));
        selectedPoint.textContent = "Lat: " + cleanLat + " | Lon: " + cleanLon;

        if (fillAddress) scheduleReverseGeocode(lat, lon);
    }

    /* =========================================
       REVERSE GEOCODING: point -> "Village, Regional unit"
    ========================================= */

    function formatAddress(address) {
        if (!address) return "";

        const place =
            address.village ||
            address.town ||
            address.city ||
            address.hamlet ||
            address.suburb ||
            address.municipality ||
            "";

        let area =
            address.county ||
            address.state_district ||
            address.state ||
            "";

        /* "Περιφερειακή Ενότητα Αρκαδίας" -> "Αρκαδίας" */
        area = area
            .replace(/^Περιφερειακή Ενότητα\s+/i, "")
            .replace(/^Regional Unit of\s+/i, "")
            .replace(/\s+Regional Unit$/i, "");

        return [place, area]
            .filter(function (part, index, all) {
                return part && all.indexOf(part) === index;
            })
            .join(", ");
    }

    function scheduleReverseGeocode(lat, lon) {
        if (!locationInput) return;

        clearTimeout(reverseTimer);

        /* Small delay so fast clicks/drags send only one request */
        reverseTimer = setTimeout(function () {
            reverseGeocode(lat, lon);
        }, 400);
    }

    function reverseGeocode(lat, lon) {
        const requestId = ++requestCounter;
        const url =
            NOMINATIM_URL + "/reverse?format=jsonv2&zoom=14&addressdetails=1" +
            "&accept-language=" + encodeURIComponent(language) +
            "&lat=" + encodeURIComponent(lat) +
            "&lon=" + encodeURIComponent(lon);

        fetch(url, { headers: { "Accept": "application/json" } })
            .then(function (response) {
                if (!response.ok) throw new Error("HTTP " + response.status);
                return response.json();
            })
            .then(function (data) {
                if (requestId !== requestCounter) return;   // a newer request exists

                const text = formatAddress(data && data.address) || (data && data.display_name) || "";

                if (text) {
                    locationInput.value = text;
                    lastSearchedText = text;   // don't search again for what we just filled
                    showStatus(messages.addressFilled, "success");
                }
            })
            .catch(function () {
                if (requestId !== requestCounter) return;
                showStatus(messages.error, "error");
            });
    }

    /* =========================================
       FORWARD GEOCODING: "Τρίπολη, Αρκαδία" -> map + pin
    ========================================= */

    function searchLocation(text) {
        const query = text.trim();

        if (query.length < MIN_SEARCH_LENGTH || query === lastSearchedText) return;

        lastSearchedText = query;

        const requestId = ++requestCounter;
        const url =
            NOMINATIM_URL + "/search?format=jsonv2&limit=1&countrycodes=gr" +
            "&accept-language=" + encodeURIComponent(language) +
            "&q=" + encodeURIComponent(query);

        showStatus(messages.searching, "");

        fetch(url, { headers: { "Accept": "application/json" } })
            .then(function (response) {
                if (!response.ok) throw new Error("HTTP " + response.status);
                return response.json();
            })
            .then(function (results) {
                if (requestId !== requestCounter) return;

                if (!Array.isArray(results) || results.length === 0) {
                    showStatus(messages.notFound, "error");
                    return;
                }

                const lat = parseFloat(results[0].lat);
                const lon = parseFloat(results[0].lon);

                if (!Number.isFinite(lat) || !Number.isFinite(lon)) {
                    showStatus(messages.notFound, "error");
                    return;
                }

                /* Place the pin but keep the text the user typed */
                updatePoint(lat, lon, false, false);
                map.setView([lat, lon], 14);
                showStatus(messages.found, "success");
            })
            .catch(function () {
                if (requestId !== requestCounter) return;
                showStatus(messages.error, "error");
            });
    }

    if (locationInput) {
        /* Search when the user stops typing */
        locationInput.addEventListener("input", function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () {
                searchLocation(locationInput.value);
            }, SEARCH_DELAY_MS);
        });

        /* Enter = search now (and don't submit the whole profile form) */
        locationInput.addEventListener("keydown", function (event) {
            if (event.key === "Enter") {
                event.preventDefault();
                clearTimeout(searchTimer);
                searchLocation(locationInput.value);
            }
        });

        /* Leaving the field = search now */
        locationInput.addEventListener("change", function () {
            clearTimeout(searchTimer);
            searchLocation(locationInput.value);
        });

        lastSearchedText = locationInput.value.trim();
    }

    /* =========================================
       EXISTING BEHAVIOUR
    ========================================= */

    if (hasSavedCoordinates) updatePoint(savedLat, savedLon, false, false);

    /* Click on the map: move pin + fill the land location */
    map.on("click", function (event) {
        updatePoint(event.latlng.lat, event.latlng.lng, false, true);
    });

    /* Typed coordinates: move the pin (and fill the land location) */
    function showTypedCoordinates() {
        const lat = parseFloat(latitudeInput.value);
        const lon = parseFloat(longitudeInput.value);
        if (!Number.isFinite(lat) || !Number.isFinite(lon) || lat < -90 || lat > 90 || lon < -180 || lon > 180) return;
        updatePoint(lat, lon, true, true);
    }

    latitudeInput.addEventListener("change", showTypedCoordinates);
    longitudeInput.addEventListener("change", showTypedCoordinates);

    const landCheckbox = document.querySelector('input[name="contribution_type[]"][value="land"]');
    if (landCheckbox) landCheckbox.addEventListener("change", function () {
        if (this.checked) setTimeout(function () { map.invalidateSize(); }, 100);
    });
    setTimeout(function () { map.invalidateSize(); }, 150);
});