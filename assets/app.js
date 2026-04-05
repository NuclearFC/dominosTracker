/* ============================================================
   app.js — Vanilla JS for the delivery tracker
   ============================================================ */

/* ----------------------------------------------------------
   initShiftMap(elementId, data)
   Renders the full shift map on shift_view.php.

   data = {
     store: { lat, lng, name },
     deliveries: [{ lat, lng, address, postcode, tip, seq }, ...]
   }
   ---------------------------------------------------------- */
function initShiftMap(elementId, data) {
    var el = document.getElementById(elementId);
    if (!el) return;

    // Centre on store by default
    var map = L.map(elementId, {
        center: [data.store.lat, data.store.lng],
        zoom: 13
    });

    // OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);

    // Store marker — distinct orange colour
    var storeIcon = L.divIcon({
        className: '',
        html: '<div style="'
            + 'width:28px;height:28px;border-radius:50%;'
            + 'background:#f5821f;border:3px solid #fff;'
            + 'display:flex;align-items:center;justify-content:center;'
            + 'font-weight:700;font-size:12px;color:#fff;'
            + 'box-shadow:0 2px 6px rgba(0,0,0,0.5)'
            + '">S</div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14],
        popupAnchor: [0, -16]
    });

    L.marker([data.store.lat, data.store.lng], { icon: storeIcon })
        .addTo(map)
        .bindPopup('<strong>' + data.store.name + '</strong>');

    if (data.deliveries.length === 0) return;

    // Collect valid deliveries (those with lat/lng)
    var valid = data.deliveries.filter(function(d) {
        return d.lat && d.lng;
    });

    if (valid.length === 0) return;

    // Delivery markers — numbered
    valid.forEach(function(d) {
        var icon = L.divIcon({
            className: '',
            html: '<div style="'
                + 'width:28px;height:28px;border-radius:50%;'
                + 'background:#2980b9;border:3px solid #fff;'
                + 'display:flex;align-items:center;justify-content:center;'
                + 'font-weight:700;font-size:12px;color:#fff;'
                + 'box-shadow:0 2px 6px rgba(0,0,0,0.5)'
                + '">' + d.seq + '</div>',
            iconSize: [28, 28],
            iconAnchor: [14, 14],
            popupAnchor: [0, -16]
        });

        var popup = '<strong>' + escHtml(d.address) + '</strong>'
            + '<br><span style="color:#aaa">' + escHtml(d.postcode) + '</span>';
        if (d.tip > 0) {
            popup += '<br><span style="color:#2ecc71">Tip: £' + d.tip.toFixed(2) + '</span>';
        }

        L.marker([d.lat, d.lng], { icon: icon })
            .addTo(map)
            .bindPopup(popup);
    });

    // Polyline: store → d1 → d2 → ... → store
    var points = [[data.store.lat, data.store.lng]];
    valid.forEach(function(d) { points.push([d.lat, d.lng]); });
    points.push([data.store.lat, data.store.lng]);

    L.polyline(points, { color: '#f5821f', weight: 2, opacity: 0.7, dashArray: '6 4' })
        .addTo(map);

    // Fit map to show all markers
    var bounds = L.latLngBounds(points);
    map.fitBounds(bounds, { padding: [30, 30] });
}

/* ----------------------------------------------------------
   initDeliveryForm(...)
   Wires up the "Find on map" button on delivery_add.php.
   Calls the server-side geocode proxy and shows a preview map.
   ---------------------------------------------------------- */
function initDeliveryForm(geocodeBtnId, addressId, postcodeId, latId, lngId, previewMapId, statusId, store, storeTown) {
    var btn        = document.getElementById(geocodeBtnId);
    var addressEl  = document.getElementById(addressId);
    var postcodeEl = document.getElementById(postcodeId);
    var latEl      = document.getElementById(latId);
    var lngEl      = document.getElementById(lngId);
    var mapDiv     = document.getElementById(previewMapId);
    var statusEl   = document.getElementById(statusId);

    if (!btn) return;

    var previewMap    = null;
    var previewMarker = null;

    btn.addEventListener('click', function() {
        var address  = addressEl.value.trim();
        var postcode = postcodeEl.value.trim();

        if (!address) {
            showStatus('Please enter a street address first.', 'error');
            return;
        }

        // Build query: "42 Hartington Street Derby DE1 3GU"
        var q = address;
        if (postcode) q += ' ' + postcode;
        if (storeTown) q += ' ' + storeTown;

        btn.textContent = 'Searching…';
        btn.disabled = true;
        showStatus('Looking up address…', 'info');

        fetch('/tracker/api/geocode.php?q=' + encodeURIComponent(q))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                btn.textContent = 'Find on map';
                btn.disabled = false;

                if (data.error) {
                    showStatus('Not found: ' + data.error, 'error');
                    return;
                }

                var lat = parseFloat(data.lat);
                var lng = parseFloat(data.lng);

                // Store lat/lng in hidden fields
                latEl.value = lat;
                lngEl.value = lng;

                showStatus('Found: ' + data.display_name, 'success');

                // Show / update the preview map
                mapDiv.style.display = 'block';

                if (!previewMap) {
                    previewMap = L.map(previewMapId, {
                        center: [lat, lng],
                        zoom: 15,
                        zoomControl: true
                    });
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '© OpenStreetMap contributors',
                        maxZoom: 19
                    }).addTo(previewMap);
                } else {
                    previewMap.setView([lat, lng], 15);
                }

                // Remove old marker if any
                if (previewMarker) previewMap.removeLayer(previewMarker);

                var icon = L.divIcon({
                    className: '',
                    html: '<div style="'
                        + 'width:22px;height:22px;border-radius:50%;'
                        + 'background:#2980b9;border:3px solid #fff;'
                        + 'box-shadow:0 2px 6px rgba(0,0,0,0.5)'
                        + '"></div>',
                    iconSize: [22, 22],
                    iconAnchor: [11, 11]
                });

                previewMarker = L.marker([lat, lng], { icon: icon }).addTo(previewMap);
            })
            .catch(function() {
                btn.textContent = 'Find on map';
                btn.disabled = false;
                showStatus('Request failed. Check your connection.', 'error');
            });
    });

    function showStatus(msg, type) {
        statusEl.style.display = 'block';
        statusEl.textContent = msg;
        statusEl.style.color = type === 'error' ? '#e74c3c'
                             : type === 'success' ? '#2ecc71'
                             : '#aaa';
    }
}

/* ----------------------------------------------------------
   escHtml — Escape a string for safe insertion into HTML
   (used in popup content built in JS)
   ---------------------------------------------------------- */
function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
