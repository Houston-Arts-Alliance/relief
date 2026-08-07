(function () {
    'use strict';

    var STORAGE_KEY = 'haa_drs_draft';
    var TOTAL_STEPS = 4;
    var currentStep = 1;
    var form = document.getElementById('haa-drs-form');
    if (!form) return;

    function showStep(n) {
        currentStep = n;

        var ineligible = document.getElementById('haa-drs-ineligible');
        if (ineligible) ineligible.hidden = true;

        var prog = document.querySelector('.haa-drs-progress-wrap');
        if (prog) prog.style.display = '';

        var steps = form.querySelectorAll('.haa-drs-step');
        steps.forEach(function (el) {
            el.hidden = true;
            el.classList.remove('is-active');
        });
        var target = document.getElementById('haa-drs-step-' + n);
        if (target) {
            target.hidden = false;
            target.classList.add('is-active');
        }
        updateProgress();
        updateStepIndicators();
        window.scrollTo({ top: 0, behavior: 'smooth' });
        saveDraft();
    }

    function showIneligibleScreen() {
        form.querySelectorAll('.haa-drs-step').forEach(function (el) {
            el.hidden = true;
            el.classList.remove('is-active');
        });

        var prog = document.querySelector('.haa-drs-progress-wrap');
        if (prog) prog.style.display = 'none';

        var countySelect = form.querySelector('[name="county"]');
        var countyLink   = document.getElementById('haa-drs-county-oem-link');
        var oemMap       = (window.haaDrs && window.haaDrs.countyOem) ? window.haaDrs.countyOem : {};
        if (countySelect && countyLink && oemMap[countySelect.value]) {
            var oem = oemMap[countySelect.value];
            countyLink.href = oem.url;
            countyLink.childNodes.forEach(function (n) {
                if (n.nodeType === 3) n.nodeValue = oem.name;
            });
            if (!Array.prototype.some.call(countyLink.childNodes, function (n) { return n.nodeType === 3; })) {
                countyLink.insertBefore(document.createTextNode(oem.name), countyLink.firstChild);
            }
        }

        var ineligible = document.getElementById('haa-drs-ineligible');
        if (ineligible) {
            ineligible.hidden = false;
            ineligible.classList.add('is-active');
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    function updateProgress() {
        var pct = (currentStep / TOTAL_STEPS) * 100;
        var fill = document.querySelector('.haa-drs-progress-fill');
        if (fill) {
            fill.style.width = pct + '%';
            var bar = fill.parentElement;
            if (bar) bar.setAttribute('aria-valuenow', Math.round(pct));
        }
    }

    function updateStepIndicators() {
        var indicators = document.querySelectorAll('.haa-drs-step-indicator');
        indicators.forEach(function (el) {
            var step = parseInt(el.dataset.step, 10);
            el.classList.remove('is-active', 'is-completed');
            el.removeAttribute('aria-current');
            if (step === currentStep) {
                el.classList.add('is-active');
                el.setAttribute('aria-current', 'step');
            }
            else if (step < currentStep) el.classList.add('is-completed');
        });
    }

    function checkEligibility() {
        var county = form.querySelector('[name="county"]');
        var artist = form.querySelector('[name="is_artist"]:checked');
        var age = form.querySelector('[name="age_18_plus"]:checked');

        if (!county || !artist || !age) return true; // not fully answered yet, let validation handle it

        var isIneligible = (county.value === 'none') || (artist.value === 'no') || (age.value === 'no');
        return !isIneligible;
    }

    /* ═══════════ validation ═══════════ */

    function clearErrors(container) {
        container.querySelectorAll('.haa-drs-error').forEach(function (el) {
            el.textContent = '';
            el.classList.remove('is-visible');
        });
        container.querySelectorAll('.has-error').forEach(function (el) {
            el.classList.remove('has-error');
        });
    }

    function showFieldError(field, msg) {
        var wrap = field.closest('.haa-drs-field') || field.closest('.haa-drs-group') || field.closest('.haa-drs-card');
        if (wrap) wrap.classList.add('has-error');
        var errEl = wrap ? wrap.querySelector('.haa-drs-error') : null;
        if (errEl) {
            errEl.textContent = msg;
            errEl.classList.add('is-visible');
        }
    }

    function validateStep(n) {
        var step = document.getElementById('haa-drs-step-' + n);
        if (!step) return true;
        clearErrors(step);
        var valid = true;
        var firstError = null;

        function fail(field, msg) {
            valid = false;
            showFieldError(field, msg);
            if (!firstError) firstError = field;
        }

        /* ── step 2 ── */
        if (n === 2) {
            var county = form.querySelector('[name="county"]');
            if (county && !county.value) fail(county, 'Please select your county.');

            var artist = form.querySelector('[name="is_artist"]:checked');
            if (!artist) {
                fail(form.querySelector('[name="is_artist"]'), 'Please indicate if you are an artist or creative.');
            }

            var age = form.querySelector('[name="age_18_plus"]:checked');
            if (!age) {
                fail(form.querySelector('[name="age_18_plus"]'), 'Please confirm your age.');
            }
        }

        /* ── step 3 ── */
        if (n === 3) {
            // Required text fields
            var reqFields = [
                { name: 'first_name', msg: 'First name is required.' },
                { name: 'last_name',  msg: 'Last name is required.' },
                { name: 'address_1',  msg: 'Address is required.' },
                { name: 'city',       msg: 'City is required.' },
                { name: 'zip',        msg: 'ZIP code is required.' },
                { name: 'phone',      msg: 'Phone number is required.' },
                { name: 'email',      msg: 'Email is required.' },
                { name: 'dob',        msg: 'Date of birth is required.' },
            ];
            reqFields.forEach(function (f) {
                var el = form.querySelector('[name="' + f.name + '"]');
                if (el && !el.value.trim()) fail(el, f.msg);
            });

            // email format
            var emailEl = form.querySelector('[name="email"]');
            if (emailEl && emailEl.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(emailEl.value.trim())) {
                fail(emailEl, 'Please enter a valid email address.');
            }

            // zip format + greater houston 9-county eligibility check
            var zipEl = form.querySelector('[name="zip"]');
            if (zipEl && zipEl.value.trim()) {
                var zipVal = zipEl.value.trim();
                if (!/^\d{5}$/.test(zipVal)) {
                    fail(zipEl, 'ZIP code must be 5 digits.');
                } else {
                    var eligibleZips = (window.haaDrs && window.haaDrs.eligibleZips) ? window.haaDrs.eligibleZips : [];
                    if (eligibleZips.length && eligibleZips.indexOf(zipVal) === -1) {
                        fail(zipEl, 'The ZIP code you entered does not appear to be in the eligible 9-county Greater Houston area. Please verify your address. If you believe this is an error, contact us at info@haatx.com.');
                    }
                }
            }

            // dob age must be 18+
            var dobEl = form.querySelector('[name="dob"]');
            if (dobEl && dobEl.value.trim()) {
                var dob = new Date(dobEl.value);
                if (!isNaN(dob.getTime())) {
                    var today = new Date();
                    var age = today.getFullYear() - dob.getFullYear();
                    var monthDiff = today.getMonth() - dob.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }
                    if (age < 18) {
                        fail(dobEl, 'Based on the date of birth entered, you are under 18 years old and do not meet an eligibility requirement for this program.');
                    }
                }
            }

            var lang = form.querySelector('[name="preferred_language"]');
            if (lang && !lang.value.trim()) fail(lang, 'Preferred language is required.');

            if (lang && lang.value === 'Other') {
                var langOther = form.querySelector('[name="preferred_language_other"]');
                if (langOther && !langOther.value.trim()) fail(langOther, 'Please specify your preferred language.');
            }

            var raceChecked = form.querySelectorAll('[name="race_ethnicity[]"]:checked');
            if (raceChecked.length === 0) {
                var raceFirst = form.querySelector('[name="race_ethnicity[]"]');
                if (raceFirst) {
                    valid = false;
                    var raceErr = document.getElementById('haa-race-error');
                    if (raceErr) {
                        raceErr.textContent = 'Please select at least one race or ethnicity.';
                        raceErr.classList.add('is-visible');
                    }
                    if (!firstError) firstError = raceFirst;
                }
            }
            var needsDescribe = false;
            raceChecked.forEach(function (cb) {
                if (cb.value === 'American Indian or Alaska Native' || cb.value === 'A race or ethnicity not listed here') {
                    needsDescribe = true;
                }
            });
            if (needsDescribe) {
                var raceOther = form.querySelector('[name="race_ethnicity_other"]');
                if (raceOther && !raceOther.value.trim()) fail(raceOther, 'Please describe your race or ethnicity.');
            }

            var w = form.querySelector('[name="website"]');
            var s = form.querySelector('[name="social_media"]');
            var c = form.querySelector('[name="cv_link"]');
            if (w && s && c && !w.value.trim() && !s.value.trim() && !c.value.trim()) {
                var errEl = document.getElementById('haa-online-presence-error');
                if (errEl) {
                    errEl.textContent = 'Please provide at least one link.';
                    errEl.classList.add('is-visible');
                    valid = false;
                    if (!firstError) firstError = w;
                }
            }

            var artistChecked = form.querySelector('[name="is_artist"]:checked');
            if (artistChecked && artistChecked.value === 'yes') {
                var disc = form.querySelector('[name="artistic_discipline"]');
                if (disc && !disc.value) fail(disc, 'Please select your discipline.');
            }

            var hs = form.querySelector('[name="household_size"]:checked');
            if (!hs) {
                var hsGroup = step.querySelector('[name="household_size"]');
                if (hsGroup) fail(hsGroup, 'Please select your household size.');
            }

            var income = form.querySelector('[name="household_income"]');
            if (income && income.value === '') fail(income, 'Please select your household income.');

            if (hs) {
                var adults = parseInt(form.querySelector('[name="adults_count"]').value, 10) || 0;
                var seniors = parseInt(form.querySelector('[name="seniors_count"]').value, 10) || 0;
                var children = parseInt(form.querySelector('[name="children_count"]').value, 10) || 0;
                var total = adults + seniors + children;

                if (total > 0) {
                    var maxPeople = hs.value === '1_2' ? 2 : (hs.value === '3_4' ? 4 : 99);
                    var minPeople = hs.value === '3_4' ? 3 : (hs.value === '5_plus' ? 5 : 1);

                    if (total > maxPeople) {
                        var bErr = document.getElementById('haa-breakdown-error');
                        if (bErr) {
                            bErr.textContent = 'The breakdown total (' + total + ') exceeds your selected household size.';
                            bErr.classList.add('is-visible');
                        }
                        valid = false;
                        if (!firstError) firstError = form.querySelector('[name="adults_count"]');
                    } else if (total < minPeople) {
                        var bErr = document.getElementById('haa-breakdown-error');
                        if (bErr) {
                            bErr.textContent = 'The breakdown total (' + total + ') is less than your selected household size.';
                            bErr.classList.add('is-visible');
                        }
                        valid = false;
                        if (!firstError) firstError = form.querySelector('[name="adults_count"]');
                    }
                }
            }

            var svi = form.querySelector('[name="svi_score"]');
            var sviError = document.getElementById('haa-svi-score-error');
            if (svi && (!svi.value || svi.value.trim() === '')) {
                if (sviError) {
                    sviError.textContent = 'Please look up your Social Vulnerability Index before continuing. Enter your ZIP code above and click the Look Up Score button.';
                    sviError.classList.add('is-visible');
                }
                valid = false;
                if (!firstError) firstError = document.getElementById('haa_svi_zip');
            } else if (sviError) {
                sviError.textContent = '';
                sviError.classList.remove('is-visible');
            }
        }

        /* ── step 4 ── */
        if (n === 4) {
            var vulnOtherCb = form.querySelector('.haa-vuln-describe');
            if (vulnOtherCb && vulnOtherCb.checked) {
                var vulnOther = form.querySelector('[name="vulnerability_factors_other"]');
                if (vulnOther && !vulnOther.value.trim()) fail(vulnOther, 'Please specify your other vulnerability.');
            }

            var assistReceived = form.querySelector('[name="received_assistance"]:checked');
            if (!assistReceived) {
                fail(form.querySelector('[name="received_assistance"]'), 'Please indicate whether you have received assistance.');
            }

            var fema = form.querySelector('[name="fema_applied"]');
            if (fema && !fema.value) fail(fema, 'Please indicate whether you applied for FEMA assistance.');

            var sba = form.querySelector('[name="sba_applied"]');
            if (sba && !sba.value) fail(sba, 'Please indicate whether you applied for SBA assistance.');

            var housing = form.querySelector('[name="housing_status"]');
            if (housing && housing.value === '') fail(housing, 'Please select your housing situation.');

            var need = form.querySelector('[name="need_level"]');
            if (need && need.value === '') fail(need, 'Please select your need level.');

            var loss = form.querySelector('[name="belongings_loss"]');
            if (loss && loss.value === '') fail(loss, 'Please select your property loss level.');

            var needsChecked = form.querySelectorAll('[name="current_needs[]"]:checked');
            if (needsChecked.length === 0) {
                var needsFirst = form.querySelector('[name="current_needs[]"]');
                if (needsFirst) fail(needsFirst, 'Please select at least one current need.');
            }

            var somethingElseCb = form.querySelector('.haa-need-describe');
            if (somethingElseCb && somethingElseCb.checked) {
                var needsOther = form.querySelector('[name="current_needs_other"]');
                if (needsOther && !needsOther.value.trim()) fail(needsOther, 'Please describe what you need.');
            }

            // impact statement
            var impact = form.querySelector('[name="impact_statement"]');
            if (impact && !impact.value.trim()) fail(impact, 'Please provide an impact statement.');

            // consent
            var consent = form.querySelector('[name="consent_agreed"]');
            if (consent && !consent.checked) {
                fail(consent, 'You must agree to the consent statement.');
            }

            // signature
            var sig = form.querySelector('[name="signature_name"]');
            if (sig && !sig.value.trim()) fail(sig, 'Your signature is required.');
        }

        if (firstError) {
            firstError.focus();
        }

        return valid;
    }

    function setupConditionals() {
        // Household breakdown
        form.querySelectorAll('[name="household_size"]').forEach(function (r) {
            r.addEventListener('change', function () {
                var el = document.getElementById('haa-household-breakdown');
                if (el) el.hidden = false;
            });
        });

        form.querySelectorAll('[name="is_artist"]').forEach(function (r) {
            r.addEventListener('change', function () {
                var el = document.getElementById('haa-discipline-question');
                if (el) el.hidden = (this.value !== 'yes');
            });
        });

        var langSelect = form.querySelector('[name="preferred_language"]');
        if (langSelect) {
            langSelect.addEventListener('change', function () {
                var el = document.getElementById('haa-language-other-wrap');
                if (el) el.hidden = (this.value !== 'Other');
            });
        }

        var raceChecks = form.querySelectorAll('.haa-race-check');
        var preferNotEl = document.getElementById('haa-race-prefer-not');

        raceChecks.forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this === preferNotEl && this.checked) {
                    raceChecks.forEach(function (other) {
                        if (other !== preferNotEl) other.checked = false;
                    });
                    var el = document.getElementById('haa-ethnicity-other-wrap');
                    if (el) el.hidden = true;
                } else if (this !== preferNotEl && this.checked) {
                    if (preferNotEl) preferNotEl.checked = false;
                }

                if (this !== preferNotEl) {
                    var anyDescribe = false;
                    form.querySelectorAll('.haa-race-describe').forEach(function (c) {
                        if (c.checked) anyDescribe = true;
                    });
                    var el = document.getElementById('haa-ethnicity-other-wrap');
                    if (el) el.hidden = !anyDescribe;
                }
            });
        });

        form.querySelectorAll('[name="received_assistance"]').forEach(function (r) {
            r.addEventListener('change', function () {
                var el = document.getElementById('haa-assistance-details');
                if (el) el.hidden = (this.value !== 'yes');
            });
        });

        form.querySelectorAll('[name="assistance_types[]"]').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (this.value === 'Other') {
                    var el = document.getElementById('haa-assist-other-wrap');
                    if (el) el.hidden = !this.checked;
                }
            });
        });

        form.querySelectorAll('.haa-vuln-describe').forEach(function (cb) {
            cb.addEventListener('change', function () {
                var wrap = document.getElementById('haa-vuln-other-wrap');
                if (wrap) wrap.hidden = !this.checked;
            });
        });

        var needChecks = form.querySelectorAll('.haa-need-item');
        var noNeedsEl = document.getElementById('haa-no-needs');

        needChecks.forEach(function (cb) {
            cb.addEventListener('change', function () {
                // Mutual exclusion: "None of these right now" vs all others
                if (this === noNeedsEl && this.checked) {
                    needChecks.forEach(function (other) {
                        if (other !== noNeedsEl) other.checked = false;
                    });
                    // Hide "Other" describe box
                    var wrap = document.getElementById('haa-needs-other-wrap');
                    if (wrap) wrap.hidden = true;
                } else if (this !== noNeedsEl && this.checked) {
                    if (noNeedsEl) noNeedsEl.checked = false;
                }

                var describeCb = form.querySelector('.haa-need-describe');
                var describeWrap = document.getElementById('haa-needs-other-wrap');
                if (describeCb && describeWrap) {
                    describeWrap.hidden = !describeCb.checked;
                }
            });
        });
    }

    function updateSubmitState() {
        var consent = form.querySelector('[name="consent_agreed"]');
        var sig = form.querySelector('[name="signature_name"]');
        var btn = document.getElementById('haa-drs-submit-btn');
        if (!btn || !consent || !sig) return;
        btn.disabled = !(consent.checked && sig.value.trim().length > 0);
    }

    function setupWordCount() {
        var ta = document.getElementById('haa_impact_statement');
        var counter = document.getElementById('haa-word-count');
        if (!ta || !counter) return;

        ta.addEventListener('input', function () {
            var text = ta.value.trim();
            var words = text.length > 0 ? text.split(/\s+/).filter(function (w) { return w.length > 0; }) : [];
            counter.textContent = words.length;
            var wrap = counter.closest('.haa-drs-word-count');
            if (wrap) {
                wrap.classList.toggle('is-over', words.length > 250);
            }
        });
    }

    function sviLevelText(level) {
        var map = {
            'low':              'Low vulnerability',
            'low-to-moderate':  'Low‑to‑moderate vulnerability',
            'moderate':         'Moderate vulnerability',
            'moderate-to-high': 'Moderate‑to‑high vulnerability',
            'high':             'High vulnerability'
        };
        return map[level] || level;
    }

    function renderSviResult(payload) {
        if (!payload || typeof payload.overall === 'undefined') return;

        var resultEl   = document.getElementById('haa-svi-result');
        var scoreInput = document.getElementById('haa_svi_score');
        if (!resultEl) return;

        var overall = Number(payload.overall);

        var scoreEl = document.getElementById('haa-svi-result-score');
        if (scoreEl) scoreEl.textContent = overall.toFixed(4);

        var levelEl = document.getElementById('haa-svi-result-level');
        if (levelEl) levelEl.textContent = sviLevelText(payload.level);

        var themesEl = document.getElementById('haa-svi-themes');
        if (themesEl) {
            themesEl.innerHTML = '';
            if (payload.themes && payload.themes.length) {
                payload.themes.forEach(function (t) {
                    var div = document.createElement('div');
                    div.className = 'haa-drs-svi-theme';
                    div.innerHTML =
                        '<span class="haa-drs-svi-theme-label">' + t.label + '</span>' +
                        '<span class="haa-drs-svi-theme-score">' + Number(t.score).toFixed(4) + '</span>';
                    themesEl.appendChild(div);
                });
            }
        }

        if (scoreInput) scoreInput.value = overall.toFixed(2);

        var scoreError = document.getElementById('haa-svi-score-error');
        if (scoreError) {
            scoreError.textContent = '';
            scoreError.classList.remove('is-visible');
        }

        resultEl.style.display = '';
    }

    function saveDraft() {
        try {
            var data = new FormData(form);
            var obj = {};
            data.forEach(function (val, key) {
                if (key === 'haa_drs_website_url') return; // skip honeypot
                if (obj[key]) {
                    if (!Array.isArray(obj[key])) obj[key] = [obj[key]];
                    obj[key].push(val);
                } else {
                    obj[key] = val;
                }
            });
            obj._step = currentStep;
            localStorage.setItem(STORAGE_KEY, JSON.stringify(obj));
        } catch (e) { /* localStorage unavailable */ }
    }

    function restoreDraft() {
        try {
            var raw = localStorage.getItem(STORAGE_KEY);
            if (!raw) return;
            var obj = JSON.parse(raw);
            if (!obj || typeof obj !== 'object') return;

            Object.keys(obj).forEach(function (key) {
                if (key === '_step' || key === 'haa_drs_website_url') return;

                var fields = form.querySelectorAll('[name="' + key + '"]');
                if (fields.length === 0) {
                    fields = form.querySelectorAll('[name="' + key + '[]"]');
                }

                fields.forEach(function (field) {
                    if (field.type === 'radio') {
                        field.checked = (field.value === obj[key]);
                    } else if (field.type === 'checkbox') {
                        var vals = Array.isArray(obj[key]) ? obj[key] : [obj[key]];
                        field.checked = vals.indexOf(field.value) !== -1;
                    } else {
                        field.value = obj[key];
                    }
                });
            });

            ['household_size', 'is_artist', 'received_assistance'].forEach(function (name) {
                var checked = form.querySelector('[name="' + name + '"]:checked');
                if (checked) checked.dispatchEvent(new Event('change', { bubbles: true }));
            });

            var langSelect = form.querySelector('[name="preferred_language"]');
            if (langSelect) langSelect.dispatchEvent(new Event('change', { bubbles: true }));

            form.querySelectorAll('.haa-race-check').forEach(function (cb) {
                if (cb.checked) cb.dispatchEvent(new Event('change', { bubbles: true }));
            });

            form.querySelectorAll('[name="assistance_types[]"]').forEach(function (cb) {
                if (cb.checked) cb.dispatchEvent(new Event('change', { bubbles: true }));
            });

            form.querySelectorAll('.haa-vuln-describe').forEach(function (cb) {
                if (cb.checked) cb.dispatchEvent(new Event('change', { bubbles: true }));
            });

            form.querySelectorAll('.haa-need-item').forEach(function (cb) {
                if (cb.checked) cb.dispatchEvent(new Event('change', { bubbles: true }));
            });

            var impactTa = document.getElementById('haa_impact_statement');
            if (impactTa && impactTa.value) {
                impactTa.dispatchEvent(new Event('input', { bubbles: true }));
            }

            var sviPayloadEl = document.getElementById('haa_svi_payload');
            if (sviPayloadEl && sviPayloadEl.value) {
                try {
                    renderSviResult(JSON.parse(sviPayloadEl.value));
                } catch (e) { /* ignore corrupt payload */ }
            }

            updateSubmitState();
        } catch (e) { /* ignore corrupted data */ }
    }

    function clearDraft() {
        try { localStorage.removeItem(STORAGE_KEY); } catch (e) {}
    }

    function submitForm() {
        if (!validateStep(4)) return;

        var btn = document.getElementById('haa-drs-submit-btn');
        var btnText = btn.querySelector('.haa-drs-btn-text');
        var btnLoad = btn.querySelector('.haa-drs-btn-loading');
        btn.disabled = true;
        if (btnText) btnText.hidden = true;
        if (btnLoad) btnLoad.hidden = false;

        var data = new FormData(form);
        data.append('action', 'haa_drs_submit');
        data.append('nonce', haaDrs.nonce);

        fetch(haaDrs.ajaxUrl, {
            method: 'POST',
            body: data,
            credentials: 'same-origin',
        })
        .then(function (res) { return res.json(); })
        .then(function (res) {
            // expired/invalid nonce, WP's check_ajax_referer() returns literal -1 with HTTP 403 that res.json() parses as number -1 (or '-1' on some configs), surface recovery message then re-enable sub.
            if (res === -1 || res === '-1') {
                alert('Your session has expired. Please refresh the page and try again. Your progress has been saved.');
                btn.disabled = false;
                if (btnText) btnText.hidden = false;
                if (btnLoad) btnLoad.hidden = true;
                return;
            }
            if (res && res.success) {
                var appIdEl = document.getElementById('haa-drs-app-id');
                if (appIdEl) appIdEl.textContent = res.data.application_id;

                form.querySelectorAll('.haa-drs-step').forEach(function (el) {
                    el.hidden = true;
                    el.classList.remove('is-active');
                });
                var successEl = document.getElementById('haa-drs-success');
                if (successEl) {
                    successEl.hidden = false;
                    successEl.classList.add('is-active');
                }

                var prog = document.querySelector('.haa-drs-progress-wrap');
                if (prog) prog.style.display = 'none';

                clearDraft();
                window.scrollTo({ top: 0, behavior: 'smooth' });
            } else {
                var msg = (res && res.data && res.data.message) ? res.data.message : 'Something went wrong. Please try again.';
                alert(msg);

                if (res && res.data && res.data.errors) {
                    Object.keys(res.data.errors).forEach(function (fieldName) {
                        var field = form.querySelector('[name="' + fieldName + '"]');
                        if (field) showFieldError(field, res.data.errors[fieldName]);
                    });
                }

                btn.disabled = false;
                if (btnText) btnText.hidden = false;
                if (btnLoad) btnLoad.hidden = true;
            }
        })
        .catch(function () {
            alert('A network error occurred. Please check your connection and try again.');
            btn.disabled = false;
            if (btnText) btnText.hidden = false;
            if (btnLoad) btnLoad.hidden = true;
        });
    }

    form.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action]');
        if (!btn) return;
        var action = btn.dataset.action;

        if (action === 'next') {
            if (validateStep(currentStep)) {
                // After step 2, check eligibility
                if (currentStep === 2 && !checkEligibility()) {
                    showIneligibleScreen();
                    return;
                }
                showStep(currentStep + 1);
            }
        } else if (action === 'prev') {
            if (currentStep > 1) showStep(currentStep - 1);
        }
    });

    var ineligibleBackBtn = document.getElementById('haa-drs-ineligible-back');
    if (ineligibleBackBtn) {
        ineligibleBackBtn.addEventListener('click', function () {
            showStep(2);
        });
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        submitForm();
    });

    var consentEl = form.querySelector('[name="consent_agreed"]');
    var sigEl = form.querySelector('[name="signature_name"]');
    if (consentEl) consentEl.addEventListener('change', updateSubmitState);
    if (sigEl) sigEl.addEventListener('input', updateSubmitState);

    form.addEventListener('change', saveDraft);
    form.addEventListener('input', function (e) {
        if (e.target.matches('input[type="text"], input[type="email"], input[type="tel"], input[type="url"], input[type="number"], input[type="date"], textarea')) {
            saveDraft();
        }
    });

    (function () {
        var triggers = document.querySelectorAll('.haa-drs-modal-trigger');
        if (!triggers.length) return;

        var lastTrigger = null;

        function openModal(modal, trigger) {
            if (!modal) return;
            lastTrigger = trigger || null;
            modal.hidden = false;
            document.body.classList.add('haa-drs-modal-open');
            var closeBtn = modal.querySelector('.haa-drs-modal-close');
            if (closeBtn) closeBtn.focus();
        }

        function closeModal(modal) {
            if (!modal) return;
            modal.hidden = true;
            document.body.classList.remove('haa-drs-modal-open');
            if (lastTrigger && typeof lastTrigger.focus === 'function') {
                lastTrigger.focus();
            }
            lastTrigger = null;
        }

        triggers.forEach(function (trigger) {
            trigger.addEventListener('click', function (e) {
                e.preventDefault();
                var id = trigger.getAttribute('data-modal');
                if (!id) return;
                openModal(document.getElementById(id), trigger);
            });
        });

        document.querySelectorAll('.haa-drs-modal').forEach(function (modal) {
            modal.addEventListener('click', function (e) {
                if (e.target.closest('[data-modal-close]')) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', function (e) {
            if (e.key !== 'Escape' && e.key !== 'Esc') return;
            document.querySelectorAll('.haa-drs-modal').forEach(function (modal) {
                if (!modal.hidden) closeModal(modal);
            });
        });
    })();

    (function () {
        var btn       = document.getElementById('haa-svi-lookup-btn');
        var zipInput  = document.getElementById('haa_svi_zip');
        var scoreInput = document.getElementById('haa_svi_score');
        var resultEl  = document.getElementById('haa-svi-result');
        var errorEl   = document.getElementById('haa-svi-lookup-error');
        if (!btn || !zipInput || !scoreInput) return;

        var addrZip = form.querySelector('[name="zip"]');
        if (addrZip && addrZip.value && /^\d{5}$/.test(addrZip.value) && !zipInput.value) {
            zipInput.value = addrZip.value;
        }

        function showError(msg) {
            if (errorEl) {
                errorEl.textContent = msg;
                errorEl.classList.add('is-visible');
            }
        }

        function clearError() {
            if (errorEl) {
                errorEl.textContent = '';
                errorEl.classList.remove('is-visible');
            }
        }

        function setLoading(on) {
            btn.disabled = on;
            btn.querySelector('.haa-svi-btn-text').style.display  = on ? 'none' : '';
            btn.querySelector('.haa-svi-btn-loading').style.display = on ? 'inline-flex' : 'none';
        }

        function renderResult(data) {
            var payload = {
                overall: data.overall,
                level:   data.level,
                themes:  data.themes,
                zip:     zipInput.value.trim()
            };
            var payloadInput = document.getElementById('haa_svi_payload');
            if (payloadInput) payloadInput.value = JSON.stringify(payload);

            renderSviResult(payload); // shared renderer (outer scope)
            saveDraft();              // persists svi_score + svi_payload to localStorage
        }

        btn.addEventListener('click', function () {
            var zip = zipInput.value.trim();
            clearError();

            if (!/^\d{5}$/.test(zip)) {
                showError('Please enter a valid 5-digit ZIP code.');
                zipInput.focus();
                return;
            }

            setLoading(true);

            var fd = new FormData();
            fd.append('action', 'haa_drs_svi_lookup');
            fd.append('nonce', haaDrs.nonce);
            fd.append('zip', zip);

            fetch(haaDrs.ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    setLoading(false);
                    if (res === -1 || res === '-1') {
                        showError('Your session has expired. Please refresh the page and try again. Your progress has been saved.');
                        return;
                    }
                    if (res && res.success) {
                        renderResult(res.data);
                    } else {
                        showError(res && res.data && res.data.message ? res.data.message : 'Lookup failed. Please try again.');
                    }
                })
                .catch(function () {
                    setLoading(false);
                    showError('A network error occurred. Please check your connection and try again.');
                });
        });

        zipInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                btn.click();
            }
        });
    })();

    setupConditionals();
    setupWordCount();
    restoreDraft();
    updateProgress();
    updateStepIndicators();
    updateSubmitState();

})();
