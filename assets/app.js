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
   Typeahead address finder for delivery_add.php.

   As the user types, results appear in a dropdown after a short
   pause. Picking a result fills the address, postcode, lat/lng
   and drops a pin on the preview map.
   ---------------------------------------------------------- */
function initDeliveryForm(opts) {
    var searchEl         = document.getElementById(opts.searchId);
    var addressEl        = document.getElementById(opts.addressId);
    var postcodeEl       = document.getElementById(opts.postcodeId);
    var latEl            = document.getElementById(opts.latId);
    var lngEl            = document.getElementById(opts.lngId);
    var mapDiv           = document.getElementById(opts.previewMapId);
    var searchWrap       = document.getElementById(opts.searchWrapId);
    var confirmedDiv     = document.getElementById(opts.confirmedId);
    var confirmedText    = document.getElementById(opts.confirmedTextId);
    var clearBtn         = document.getElementById(opts.clearBtnId);
    var postcodeWrap     = document.getElementById(opts.postcodeWrapId);
    var postcodeDisplay  = document.getElementById(opts.postcodeDisplayId);

    if (!searchEl) return;

    var previewMap    = null;
    var previewMarker = null;
    var debounceTimer = null;
    var currentQuery  = '';

    // ----------------------------------------------------------
    // Listen for typing — search after 500ms pause
    // ----------------------------------------------------------
    searchEl.addEventListener('input', function() {
        var q = searchEl.value.trim();

        clearDropdown();

        if (q.length < 3) return; // Don't search on very short strings

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function() {
            if (q !== currentQuery) {
                currentQuery = q;
                doSearch(q);
            }
        }, 500);
    });

    // Close dropdown if user taps elsewhere
    document.addEventListener('click', function(e) {
        if (!searchWrap.contains(e.target)) clearDropdown();
    });

    // Keep postcode hidden field in sync with the editable display field
    if (postcodeDisplay) {
        postcodeDisplay.addEventListener('input', function() {
            postcodeEl.value = postcodeDisplay.value.toUpperCase();
        });
    }

    // "Change" button — clears the selection and shows the search box again
    if (clearBtn) {
        clearBtn.addEventListener('click', function() {
            addressEl.value  = '';
            postcodeEl.value = '';
            latEl.value      = '';
            lngEl.value      = '';
            searchEl.value   = '';
            if (postcodeDisplay) postcodeDisplay.value = '';

            confirmedDiv.style.display  = 'none';
            postcodeWrap.style.display  = 'none';
            searchWrap.style.display    = 'block';
            mapDiv.style.display        = 'none';

            searchEl.focus();
        });
    }

    // ----------------------------------------------------------
    // Fire a geocode request
    // ----------------------------------------------------------
    function doSearch(q) {
        showDropdownItem('Searching…', null, true);

        fetch('/tracker/api/geocode.php?q=' + encodeURIComponent(q))
            .then(function(res) { return res.json(); })
            .then(function(data) {
                clearDropdown();

                if (!Array.isArray(data) || data.length === 0) {
                    showDropdownItem('No results found', null, true);
                    return;
                }

                // Map API results to {label, full, postcode, lat, lng}
                var results = data.map(function(item) {
                    var addr     = item.address || {};
                    var street   = [addr.house_number, addr.road].filter(Boolean).join(' ')
                                   || item.display_name.split(',')[0].trim();
                    var locality = [
                        addr.suburb || addr.quarter || addr.neighbourhood || '',
                        addr.city   || addr.town    || addr.village        || '',
                        addr.postcode || ''
                    ].filter(Boolean).join(', ');
                    return {
                        label:    street,
                        full:     locality || item.display_name.split(',').slice(1, 3).join(',').trim(),
                        postcode: (addr.postcode || '').toUpperCase(),
                        lat:      item.lat,
                        lng:      item.lng
                    };
                });

                showResults(results);
            })
            .catch(function() {
                clearDropdown();
                showDropdownItem('Request failed — check connection', null, true);
            });
    }

    // ----------------------------------------------------------
    // Render the results dropdown
    // ----------------------------------------------------------
    function showResults(results) {
        var list = document.createElement('ul');
        list.className = 'geocode-results';
        list.id = 'geocode-dropdown';

        results.forEach(function(r) {
            var li = document.createElement('li');
            li.className = 'geocode-result-item';

            var labelEl = document.createElement('span');
            labelEl.className = 'result-label';
            labelEl.textContent = r.label;

            var fullEl = document.createElement('span');
            fullEl.className = 'result-full';
            fullEl.textContent = r.full;

            li.appendChild(labelEl);
            li.appendChild(fullEl);

            // Use mousedown so it fires before the input loses focus
            li.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectResult(r);
            });
            // Touch support
            li.addEventListener('touchend', function(e) {
                e.preventDefault();
                selectResult(r);
            });

            list.appendChild(li);
        });

        searchWrap.appendChild(list);
    }

    function showDropdownItem(text, cls, disabled) {
        clearDropdown();
        var list = document.createElement('ul');
        list.className = 'geocode-results';
        list.id = 'geocode-dropdown';
        var li = document.createElement('li');
        li.className = 'geocode-result-item' + (disabled ? ' result-disabled' : '');
        li.textContent = text;
        list.appendChild(li);
        searchWrap.appendChild(list);
    }

    function clearDropdown() {
        var el = document.getElementById('geocode-dropdown');
        if (el) el.remove();
    }

    // ----------------------------------------------------------
    // User picked a result
    // ----------------------------------------------------------
    function selectResult(r) {
        var lat = parseFloat(r.lat);
        var lng = parseFloat(r.lng);

        // Fill the hidden fields that get submitted
        addressEl.value  = r.label;
        postcodeEl.value = r.postcode;
        latEl.value      = lat;
        lngEl.value      = lng;

        clearDropdown();

        // Show the confirmed address strip, hide the search box
        confirmedText.textContent = r.label + (r.postcode ? '  ·  ' + r.postcode : '');
        confirmedDiv.style.display = 'flex';
        searchWrap.style.display   = 'none';

        // Show editable postcode field (in case it's wrong)
        if (r.postcode) {
            postcodeDisplay.value      = r.postcode;
            postcodeWrap.style.display = 'block';
        }

        // Show / update the preview map
        mapDiv.style.display = 'block';

        if (!previewMap) {
            previewMap = L.map(opts.previewMapId, {
                center: [lat, lng],
                zoom: 16,
                zoomControl: true
            });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(previewMap);
        } else {
            previewMap.setView([lat, lng], 16);
        }

        if (previewMarker) previewMap.removeLayer(previewMarker);

        var icon = L.divIcon({
            className: '',
            html: '<div style="width:22px;height:22px;border-radius:50%;'
                + 'background:#2980b9;border:3px solid #fff;'
                + 'box-shadow:0 2px 6px rgba(0,0,0,0.5)"></div>',
            iconSize: [22, 22],
            iconAnchor: [11, 11]
        });

        previewMarker = L.marker([lat, lng], { icon: icon }).addTo(previewMap);
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
