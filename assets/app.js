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
   initDeliveryForm(opts)
   Typeahead address search on delivery_add.php.

   Searches via the server-side geocode proxy. The proxy runs a
   fallback search without the house number if needed, and sorts
   results by distance from the store — so "107 Belper Road" finds
   the Belper one even if OSM only has the road mapped (not the
   individual house).
   ---------------------------------------------------------- */
function initDeliveryForm(opts) {
    var searchEl     = document.getElementById(opts.searchId);
    var addressEl    = document.getElementById(opts.addressId);
    var postcodeEl   = document.getElementById(opts.postcodeId);
    var latEl        = document.getElementById(opts.latId);
    var lngEl        = document.getElementById(opts.lngId);
    var mapDiv       = document.getElementById(opts.previewMapId);
    var searchWrap   = document.getElementById(opts.searchWrapId);
    var confirmedDiv = document.getElementById(opts.confirmedId);
    var confirmedText= document.getElementById(opts.confirmedTextId);
    var clearBtn     = document.getElementById(opts.clearBtnId);

    if (!searchEl) return;

    var previewMap    = null;
    var previewMarker = null;
    var debounceTimer = null;
    var currentQuery  = '';

    searchEl.addEventListener('input', function() {
        var q = searchEl.value.trim();
        clearDropdown();
        if (q.length < 3) return;

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            if (q !== currentQuery) {
                currentQuery = q;
                doSearch(q);
            }
        }, 500);
    });

    document.addEventListener('click', function(e) {
        if (!searchWrap.contains(e.target)) clearDropdown();
    });

    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            addressEl.value  = '';
            postcodeEl.value = '';
            latEl.value      = '';
            lngEl.value      = '';
            searchEl.value   = '';
            currentQuery     = '';

            confirmedDiv.style.display = 'none';
            searchWrap.style.display   = 'block';
            mapDiv.style.display       = 'none';

            searchEl.focus();
        });
    }

    function doSearch(q) {
        showMessage('Searching…');

        fetch('/tracker/api/geocode.php?q=' + encodeURIComponent(q))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                clearDropdown();
                if (!Array.isArray(data) || data.length === 0) {
                    showMessage('No results found');
                    return;
                }
                renderResults(data);
            })
            .catch(function() {
                clearDropdown();
                showMessage('Request failed — check connection');
            });
    }

    function renderResults(results) {
        var list = document.createElement('ul');
        list.className = 'geocode-results';
        list.id = 'geocode-dropdown';

        results.forEach(function(item) {
            var addr = item.address || {};

            // Use the house number from the query if OSM didn't return one
            var houseNum = addr.house_number || item.house_number || '';
            var road     = addr.road || addr.pedestrian || addr.footway || '';
            var street   = [houseNum, road].filter(Boolean).join(' ')
                           || item.display_name.split(',')[0].trim();

            var locality = [
                addr.suburb || addr.quarter || addr.neighbourhood || '',
                addr.city   || addr.town    || addr.village        || '',
                addr.postcode || ''
            ].filter(Boolean).join(', ')
             || item.display_name.split(',').slice(1, 3).join(',').trim();

            var postcode = (addr.postcode || '').toUpperCase();

            var li = document.createElement('li');
            li.className = 'geocode-result-item';
            li.innerHTML = '<span class="result-label">' + escHtml(street) + '</span>'
                         + '<span class="result-full">'  + escHtml(locality) + '</span>';

            li.addEventListener('mousedown', function(e) { e.preventDefault(); });
            li.addEventListener('click', function() {
                selectResult(street, postcode, parseFloat(item.lat), parseFloat(item.lng));
            });
            li.addEventListener('touchend', function(e) {
                e.preventDefault();
                selectResult(street, postcode, parseFloat(item.lat), parseFloat(item.lng));
            });

            list.appendChild(li);
        });

        searchWrap.appendChild(list);
    }

    function selectResult(street, postcode, lat, lng) {
        addressEl.value  = street;
        postcodeEl.value = postcode;
        latEl.value      = lat;
        lngEl.value      = lng;

        clearDropdown();

        confirmedText.textContent = street + (postcode ? '  ·  ' + postcode : '');
        confirmedDiv.style.display = 'flex';
        searchWrap.style.display   = 'none';

        mapDiv.style.display = 'block';
        if (!previewMap) {
            previewMap = L.map(opts.previewMapId, { center: [lat, lng], zoom: 16 });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(previewMap);
        } else {
            previewMap.setView([lat, lng], 16);
        }

        if (previewMarker) previewMap.removeLayer(previewMarker);
        previewMarker = L.marker([lat, lng], {
            icon: L.divIcon({
                className: '',
                html: '<div style="width:22px;height:22px;border-radius:50%;background:#2980b9;border:3px solid #fff;box-shadow:0 2px 6px rgba(0,0,0,0.5)"></div>',
                iconSize: [22, 22],
                iconAnchor: [11, 11]
            })
        }).addTo(previewMap);
    }

    function showMessage(msg) {
        clearDropdown();
        var list = document.createElement('ul');
        list.className = 'geocode-results';
        list.id = 'geocode-dropdown';
        var li = document.createElement('li');
        li.className = 'geocode-result-item result-disabled';
        li.textContent = msg;
        list.appendChild(li);
        searchWrap.appendChild(list);
    }

    function clearDropdown() {
        var el = document.getElementById('geocode-dropdown');
        if (el) el.remove();
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
