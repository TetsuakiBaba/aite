(function () {
    var lang = window.AITE_LANG || 'ja';
    var i18n = window.AITE_I18N || {};
    var tr = function (key) {
        var text = i18n[key] || key;
        Array.prototype.slice.call(arguments, 1).forEach(function (value) {
            text = text.replace('%d', value).replace('%s', value);
        });
        return text;
    };
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var weekdays = tr('js.weekdays_short').split(',');
    var SLOT_MINUTES = 10;
    var DAY_UNITS = 24 * 60 / SLOT_MINUTES;
    var DAY_MINUTES = 24 * 60;
    var FRAME_START_MINUTES = 6 * 60;
    var FRAME_START_UNITS = FRAME_START_MINUTES / SLOT_MINUTES;
    var timeText = function (index) {
        var minutes = index * SLOT_MINUTES;
        return pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
    };
    var slotText = function (date, start, end) {
        return date + ' ' + timeText(start) + '-' + timeText(end);
    };
    var dateLabel = function (dateText) {
        var m = String(dateText || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) return dateText;
        var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        if (lang === 'en') return m[1] + '/' + m[2] + '/' + m[3] + ' (' + weekdays[d.getDay()] + ')';
        return m[1] + '/' + m[2] + '/' + m[3] + '（' + weekdays[d.getDay()] + '）';
    };
    var slotLabel = function (text) {
        var parsed = parseSlot(text);
        if (parsed) return dateLabel(parsed.date) + ' ' + timeText(parsed.start) + '-' + timeText(parsed.end);
        if (parseDateSlot(text)) return dateLabel(text);
        return text;
    };
    var monthLabelText = function (date, year, month) {
        if (lang === 'en') return date.toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
        return year + tr('js.year_suffix') + ' ' + (month + 1) + tr('js.month_suffix');
    };
    var labelStepForWidth = function (width) {
        if (width < 430) return 4;
        if (width < 680) return 2;
        return 1;
    };
    var parseLines = function (text) {
        var seen = {};
        return String(text || '').split(/\r?\n/).map(function (s) {
            return s.trim().replace(/\s+/g, ' ');
        }).filter(function (s) {
            if (!s || seen[s]) return false;
            seen[s] = true;
            return true;
        });
    };
    var parseSlot = function (text) {
        var m = String(text).match(/^(\d{4}-\d{2}-\d{2})\s+(\d{2}):(\d{2})-(\d{2}):(\d{2})$/);
        if (!m) return null;
        var start = (Number(m[2]) * 60 + Number(m[3])) / SLOT_MINUTES;
        var end = (Number(m[4]) * 60 + Number(m[5])) / SLOT_MINUTES;
        if (start < 0 || end > DAY_UNITS || end <= start || Math.floor(start) !== start || Math.floor(end) !== end) return null;
        return {
            date: m[1],
            start: start,
            end: end
        };
    };
    var parseDateSlot = function (text) {
        var value = String(text || '').trim();
        if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null;
        var m = value.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        if (ymd(d) !== value) return null;
        return { date: value };
    };
    var timeIndex = function (text) {
        var m = String(text || '').match(/^(\d{1,2}):(\d{2})$/);
        if (!m) return null;
        var index = (Number(m[1]) * 60 + Number(m[2])) / SLOT_MINUTES;
        if (index < 0 || index > DAY_UNITS || Math.floor(index) !== index) return null;
        return index;
    };
    var timeMinutes = function (text) {
        var m = String(text || '').match(/^(\d{1,2}):(\d{2})$/);
        if (!m) return null;
        var hour = Number(m[1]);
        var minute = Number(m[2]);
        if (hour < 0 || hour > 24 || minute < 0 || minute > 59) return null;
        if (hour === 24 && minute !== 0) return null;
        return hour * 60 + minute;
    };
    var normalizeStatus = function (value) {
        var v = String(value || '').trim().toLowerCase();
        if (['o', 'ok', 'yes', 'available', '○', '◯', '〇'].indexOf(v) >= 0) return 'o';
        if (['maybe', 'tentative', '△'].indexOf(v) >= 0) return 'maybe';
        if (['x', 'no', 'unavailable', '×', '✕', '✖'].indexOf(v) >= 0) return 'x';
        return null;
    };
    var icon = function (name) {
        var el = document.createElement('span');
        el.className = 'icon icon-' + name;
        el.setAttribute('aria-hidden', 'true');
        return el;
    };

    function initCreate() {
        var form = document.getElementById('createForm');
        if (!form) return;

        var calendar = document.getElementById('calendar');
        var monthLabel = document.getElementById('monthLabel');
        var timelineWrap = document.getElementById('timelineWrap');
        var timeline = document.getElementById('timeline');
        var timelineTitle = document.getElementById('timelineTitle');
        var selectedSlots = document.getElementById('selectedSlots');
        var slotsInput = document.getElementById('slotsInput');
        var manualPanel = document.getElementById('manualPanel');
        var manualSlots = document.getElementById('manualSlots');
        var manualMessage = document.getElementById('manualMessage');
        var titleInput = document.getElementById('eventTitle');
        var slotSection = document.getElementById('slotSection');
        var submitButton = document.getElementById('createSubmit');
        var descriptionToggle = document.getElementById('descriptionToggle');
        var descriptionPanel = document.getElementById('descriptionPanel');
        var descriptionInput = descriptionPanel ? descriptionPanel.querySelector('textarea[name="description"]') : null;
        var minDurationToggle = document.getElementById('minDurationToggle');
        var minDurationPanel = document.getElementById('minDurationPanel');
        var minDurationInput = document.getElementById('minDurationMinutes');
        var dateOnlyToggle = document.getElementById('dateOnlyToggle');
        var dateOnlyHint = document.getElementById('dateOnlyHint');
        var timelineHint = document.getElementById('timelineHint');
        var manualLabel = document.getElementById('manualLabel');
        var slots = [];
        var today = new Date();
        var viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
        var activeDate = null;
        var activeDatePulse = null;
        var dragStart = null;
        var dragEnd = null;
        var dragging = false;
        var resizeSlot = null;
        var suppressTimeClick = false;
        var labelStep = labelStepForWidth(timeline ? timeline.clientWidth : window.innerWidth);
        var frameStart = FRAME_START_UNITS;
        var frameUnits = DAY_UNITS;

        function titleIsReady() {
            return !!(titleInput && titleInput.value.trim());
        }

        function setCreateStepState() {
            var ready = titleIsReady();
            if (slotSection) {
                slotSection.classList.toggle('is-active', ready);
                slotSection.setAttribute('aria-disabled', ready ? 'false' : 'true');
            }
            if (submitButton) submitButton.disabled = !ready;
        }

        function setDescriptionState() {
            var enabled = !!(descriptionToggle && descriptionToggle.checked);
            if (descriptionPanel) descriptionPanel.hidden = !enabled;
            if (descriptionInput) {
                descriptionInput.disabled = !enabled;
                if (!enabled) descriptionInput.value = '';
            }
        }

        function setMinDurationState() {
            var dateOnly = dateOnlyMode();
            if (dateOnly && minDurationToggle) minDurationToggle.checked = false;
            var enabled = !!(minDurationToggle && minDurationToggle.checked && !dateOnly);
            if (minDurationToggle) minDurationToggle.disabled = dateOnly;
            if (dateOnlyToggle) dateOnlyToggle.disabled = !!(minDurationToggle && minDurationToggle.checked);
            if (minDurationPanel) minDurationPanel.hidden = !enabled;
            if (minDurationInput) minDurationInput.disabled = !enabled;
        }

        function dateOnlyMode() {
            return !!(dateOnlyToggle && dateOnlyToggle.checked);
        }

        function setDateOnlyState() {
            var enabled = dateOnlyMode();
            if (dateOnlyHint) dateOnlyHint.hidden = !enabled;
            if (timelineHint) timelineHint.textContent = enabled ? timelineHint.dataset.dateHint : timelineHint.dataset.timeHint;
            if (manualLabel) manualLabel.textContent = enabled ? manualLabel.dataset.dateLabel : manualLabel.dataset.timeLabel;
            if (manualSlots) {
                manualSlots.placeholder = enabled ? '2026-07-01\n2026-07-02' : '2026-07-01 13:00-14:00\n2026-07-02 10:00-12:00';
            }
            if (timelineWrap && enabled) timelineWrap.hidden = true;
            if (enabled) activeDate = null;
            setMinDurationState();
            setManualMessage('');
            renderAll();
        }

        function minDurationUnits() {
            if (!minDurationToggle || !minDurationToggle.checked || !minDurationInput) return 0;
            var minutes = Number(minDurationInput.value || 0);
            if (!Number.isFinite(minutes) || minutes < SLOT_MINUTES || minutes % SLOT_MINUTES !== 0) return null;
            return minutes / SLOT_MINUTES;
        }

        function setManualMessage(message) {
            if (manualMessage) manualMessage.textContent = message || '';
        }

        function validateManualSlots() {
            var lines = parseLines(manualSlots.value);
            var minUnits = minDurationUnits();
            if (minUnits === null) {
                setManualMessage(tr('error.min_duration_invalid'));
                return null;
            }
            if (lines.some(function (line) { return !parseSlot(line); })) {
                if (dateOnlyMode()) {
                    if (lines.some(function (line) { return !parseDateSlot(line); })) {
                        setManualMessage(tr('js.date_required'));
                        return null;
                    }
                    setManualMessage('');
                    return lines;
                }
                setManualMessage(tr('js.slot_30_minute_required'));
                return null;
            }
            if (minUnits > 0 && lines.some(function (line) {
                var parsed = parseSlot(line);
                return parsed && parsed.end - parsed.start < minUnits;
            })) {
                setManualMessage(tr('js.slot_min_duration_required'));
                return null;
            }
            setManualMessage('');
            return lines;
        }

        function visualToAbs(index) {
            return (index + frameStart) % frameUnits;
        }

        function absToVisual(index) {
            return (index - frameStart + frameUnits) % frameUnits;
        }

        function visualHourLabel(index) {
            if (index % labelStep !== 0 && index !== 18) return '';
            if (index === 18) return '24';
            return pad((6 + index) % 24);
        }

        function visualParts(absStart, absEnd) {
            if (absEnd <= absStart) return [];
            var start = absToVisual(absStart);
            var end = absToVisual(absEnd);
            if (start < end) return [{ start: start, end: end }];
            var parts = [{ start: start, end: frameUnits }];
            if (end > 0) parts.push({ start: 0, end: end });
            return parts;
        }

        function selectedAbsRange(visualStart, visualEnd) {
            var values = [];
            for (var i = visualStart; i < visualEnd; i++) {
                values.push(visualToAbs(i));
            }
            var start = Math.min.apply(null, values);
            var end = Math.max.apply(null, values) + 1;
            if (end - start !== values.length) return null;
            return { start: start, end: end };
        }

        function appendTimeBlock(track, className, label, absStart, absEnd, onClick, resizeData) {
            visualParts(absStart, absEnd).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement(onClick ? 'button' : 'div');
                if (onClick) block.type = 'button';
                block.className = className;
                block.style.left = (part.start / frameUnits * 100) + '%';
                block.style.width = ((part.end - part.start) / frameUnits * 100) + '%';
                if (resizeData) {
                    var left = document.createElement('span');
                    left.className = 'time-handle left';
                    left.setAttribute('aria-label', tr('js.change_start'));
                    left.appendChild(icon('nav-arrow-left'));
                    var text = document.createElement('span');
                    text.className = 'time-text';
                    text.textContent = label;
                    var right = document.createElement('span');
                    right.className = 'time-handle right';
                    right.setAttribute('aria-label', tr('js.change_end'));
                    right.appendChild(icon('nav-arrow-right'));
                    block.appendChild(left);
                    block.appendChild(text);
                    block.appendChild(right);
                    left.addEventListener('pointerdown', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        timeline.setPointerCapture(e.pointerId);
                        suppressTimeClick = true;
                        resizeSlot = Object.assign({ edge: 'start' }, resizeData);
                    });
                    right.addEventListener('pointerdown', function (e) {
                        e.preventDefault();
                        e.stopPropagation();
                        timeline.setPointerCapture(e.pointerId);
                        suppressTimeClick = true;
                        resizeSlot = Object.assign({ edge: 'end' }, resizeData);
                    });
                } else {
                    block.textContent = label;
                }
                if (onClick) {
                    block.addEventListener('pointerdown', function (e) {
                        e.stopPropagation();
                    });
                    block.addEventListener('click', onClick);
                }
                track.appendChild(block);
            });
        }

        function addSlot(text) {
            if (dateOnlyMode()) {
                if (!parseDateSlot(text)) return;
                if (slots.indexOf(text) === -1) {
                    slots.push(text);
                    slots.sort();
                    renderAll();
                }
                return;
            }
            var parsed = parseSlot(text);
            var minUnits = minDurationUnits();
            if (!parsed || minUnits === null) return;
            if (minUnits > 0 && parsed.end - parsed.start < minUnits) {
                alert(tr('js.slot_min_duration_required'));
                return;
            }
            if (slots.indexOf(text) === -1) {
                slots.push(text);
                slots.sort();
                renderAll();
            }
        }

        function removeSlot(text) {
            slots = slots.filter(function (s) { return s !== text; });
            renderAll();
        }

        function replaceSlot(oldText, newText) {
            slots = slots.filter(function (s) { return s !== oldText && s !== newText; });
            slots.push(newText);
            slots.sort();
            renderAll();
        }

        function rangesFor(date) {
            if (dateOnlyMode()) return [];
            return slots.map(parseSlot).filter(function (s) { return s && s.date === date; });
        }

        function hasDate(date) {
            return slots.some(function (text) {
                if (dateOnlyMode()) return text === date;
                var parsed = parseSlot(text);
                return parsed && parsed.date === date;
            });
        }

        function renderCalendar() {
            var year = viewDate.getFullYear();
            var month = viewDate.getMonth();
            monthLabel.textContent = monthLabelText(viewDate, year, month);
            calendar.innerHTML = '';

            weekdays.forEach(function (day) {
                var el = document.createElement('div');
                el.className = 'cal-week';
                el.textContent = day;
                calendar.appendChild(el);
            });

            var first = new Date(year, month, 1);
            var last = new Date(year, month + 1, 0);
            for (var i = 0; i < first.getDay(); i++) {
                calendar.appendChild(document.createElement('div')).className = 'cal-empty';
            }
            for (var d = 1; d <= last.getDate(); d++) {
                var date = new Date(year, month, d);
                var dateText = ymd(date);
                var button = document.createElement('button');
                button.type = 'button';
                button.className = 'cal-day';
                if (dateText === ymd(today)) button.classList.add('today');
                if (hasDate(dateText)) button.classList.add('selected');
                if (dateText === activeDate) button.classList.add('active');
                if (dateText === activeDatePulse) button.classList.add('just-selected');
                button.textContent = d;
                button.addEventListener('click', function (value) {
                    return function () {
                        if (dateOnlyMode()) {
                            if (slots.indexOf(value) >= 0) removeSlot(value);
                            else addSlot(value);
                            return;
                        }
                        openTimeline(value);
                    };
                }(dateText));
                calendar.appendChild(button);
            }
        }

        function renderSelected() {
            slotsInput.value = slots.join('\n');
            selectedSlots.innerHTML = '';
            if (!slots.length) {
                var empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = tr('js.no_slots');
                selectedSlots.appendChild(empty);
                return;
            }
            slots.forEach(function (text) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'slot-chip';
                var label = document.createElement('span');
                label.textContent = slotLabel(text);
                chip.appendChild(label);
                chip.appendChild(icon('xmark'));
                chip.addEventListener('click', function () { removeSlot(text); });
                selectedSlots.appendChild(chip);
            });
        }

        function renderTimeline() {
            if (!activeDate) return;
            timelineTitle.textContent = dateLabel(activeDate);
            timeline.innerHTML = '';

            var track = document.createElement('div');
            track.className = 'time-track';
            timeline.appendChild(track);

            var labelRow = document.createElement('div');
            labelRow.className = 'time-label-row';
            track.appendChild(labelRow);
            for (var h = 0; h < 24; h++) {
                var label = document.createElement('div');
                label.className = 'time-label';
                label.textContent = visualHourLabel(h);
                labelRow.appendChild(label);
            }

            var selectRow = document.createElement('div');
            selectRow.className = 'time-select-row';
            selectRow.style.gridTemplateColumns = 'repeat(' + frameUnits + ', minmax(0, 1fr))';
            track.appendChild(selectRow);
            for (var i = 0; i < frameUnits; i++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'time-cell';
                cell.dataset.index = String(i);
                cell.setAttribute('aria-label', timeText(visualToAbs(i)));
                selectRow.appendChild(cell);
            }

            rangesFor(activeDate).forEach(function (range) {
                appendTimeBlock(track, 'time-block', timeText(range.start) + '-' + timeText(range.end), range.start, range.end, function (e) {
                    if (e.target.closest('.time-handle') || suppressTimeClick) {
                        suppressTimeClick = false;
                        return;
                    }
                    e.stopPropagation();
                    removeSlot(slotText(activeDate, range.start, range.end));
                }, {
                    date: activeDate,
                    original: slotText(activeDate, range.start, range.end),
                    start: range.start,
                    end: range.end
                });
            });

            if (dragging && dragStart !== null && dragEnd !== null) {
                var start = Math.min(dragStart, dragEnd);
                var end = Math.max(dragStart, dragEnd) + 1;
                var absRange = selectedAbsRange(start, end);
                if (absRange) {
                    appendTimeBlock(track, 'time-block preview', timeText(absRange.start) + '-' + timeText(absRange.end), absRange.start, absRange.end);
                }
            }
        }

        function openTimeline(dateText) {
            var shouldAnimate = activeDate !== dateText || timelineWrap.hidden;
            activeDate = dateText;
            timelineWrap.hidden = false;
            if (shouldAnimate) {
                activeDatePulse = dateText;
                renderCalendar();
                timelineWrap.classList.remove('is-opening');
                window.requestAnimationFrame(function () {
                    timelineWrap.classList.add('is-opening');
                });
                window.setTimeout(function () {
                    if (activeDatePulse === dateText) activeDatePulse = null;
                }, 500);
            }
            renderTimeline();
            timelineWrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }

        function renderAll() {
            renderCalendar();
            renderSelected();
            if (activeDate) renderTimeline();
        }

        function indexFromPointer(e) {
            var selectRow = timeline.querySelector('.time-select-row');
            if (!selectRow) return null;
            var rect = selectRow.getBoundingClientRect();
            var cellWidth = rect.width / frameUnits;
            var index = Math.floor((e.clientX - rect.left) / cellWidth);
            if (index < 0) index = 0;
            if (index > frameUnits - 1) index = frameUnits - 1;
            return index;
        }

        function finishDrag(shouldSave) {
            if (!dragging) return;
            dragging = false;
            var visualStart = Math.min(dragStart, dragEnd);
            var visualEnd = Math.max(dragStart, dragEnd) + 1;
            var absRange = selectedAbsRange(visualStart, visualEnd);
            dragStart = null;
            dragEnd = null;
            if (shouldSave && activeDate && absRange && absRange.end > absRange.start) {
                addSlot(slotText(activeDate, absRange.start, absRange.end));
            }
            renderTimeline();
        }

        timeline.addEventListener('pointerdown', function (e) {
            var cell = e.target.closest('.time-cell');
            if (!cell || !activeDate) return;
            e.preventDefault();
            dragging = true;
            dragStart = Number(cell.dataset.index);
            dragEnd = dragStart;
            timeline.setPointerCapture(e.pointerId);
            renderTimeline();
        });

        timeline.addEventListener('pointermove', function (e) {
            if (resizeSlot) {
                var resizeIndex = indexFromPointer(e);
                if (resizeIndex === null) return;
                var absolute = visualToAbs(resizeIndex);
                var nextStart = resizeSlot.start;
                var nextEnd = resizeSlot.end;
                if (resizeSlot.edge === 'start') {
                    nextStart = Math.max(0, Math.min(absolute, nextEnd - 1));
                } else {
                    nextEnd = Math.min(frameUnits, Math.max(absolute + 1, nextStart + 1));
                }
                replaceSlot(resizeSlot.original, slotText(resizeSlot.date, nextStart, nextEnd));
                resizeSlot = {
                    edge: resizeSlot.edge,
                    date: resizeSlot.date,
                    original: slotText(resizeSlot.date, nextStart, nextEnd),
                    start: nextStart,
                    end: nextEnd
                };
                return;
            }
            if (!dragging) return;
            var next = indexFromPointer(e);
            if (next !== null && next !== dragEnd) {
                dragEnd = next;
                renderTimeline();
            }
        });
        timeline.addEventListener('pointerup', function (e) {
            if (resizeSlot) {
                resizeSlot = null;
                if (timeline.hasPointerCapture(e.pointerId)) {
                    timeline.releasePointerCapture(e.pointerId);
                }
                return;
            }
            finishDrag(true);
            if (timeline.hasPointerCapture(e.pointerId)) {
                timeline.releasePointerCapture(e.pointerId);
            }
        });
        timeline.addEventListener('pointercancel', function () {
            resizeSlot = null;
            finishDrag(false);
        });
        timeline.addEventListener('lostpointercapture', function () {
            resizeSlot = null;
            finishDrag(false);
        });

        document.getElementById('prevMonth').addEventListener('click', function () {
            viewDate.setMonth(viewDate.getMonth() - 1);
            renderCalendar();
        });
        document.getElementById('nextMonth').addEventListener('click', function () {
            viewDate.setMonth(viewDate.getMonth() + 1);
            renderCalendar();
        });
        document.getElementById('closeTimeline').addEventListener('click', function () {
            activeDate = null;
            timelineWrap.hidden = true;
        });
        timelineWrap.addEventListener('animationend', function () {
            timelineWrap.classList.remove('is-opening');
        });
        document.getElementById('manualToggle').addEventListener('click', function () {
            manualPanel.hidden = !manualPanel.hidden;
        });
        document.getElementById('mergeManual').addEventListener('click', function () {
            var lines = validateManualSlots();
            if (lines === null) return;
            lines.forEach(addSlot);
        });
        if (manualSlots) {
            manualSlots.addEventListener('input', function () {
                if (manualSlots.value.trim() !== '') validateManualSlots();
                else setManualMessage('');
            });
        }
        if (titleInput) {
            titleInput.addEventListener('input', setCreateStepState);
        }
        if (descriptionToggle) {
            descriptionToggle.addEventListener('change', setDescriptionState);
        }
        if (minDurationToggle) {
            minDurationToggle.addEventListener('change', function () {
                setMinDurationState();
                if (manualSlots && manualSlots.value.trim() !== '') validateManualSlots();
            });
        }
        if (minDurationInput) {
            minDurationInput.addEventListener('input', function () {
                if (manualSlots && manualSlots.value.trim() !== '') validateManualSlots();
            });
        }
        if (dateOnlyToggle) {
            dateOnlyToggle.addEventListener('change', function () {
                slots = [];
                activeDate = null;
                if (timelineWrap) timelineWrap.hidden = true;
                if (manualSlots) manualSlots.value = '';
                setDateOnlyState();
            });
        }
        form.addEventListener('submit', function (e) {
            if (!titleIsReady()) {
                e.preventDefault();
                if (titleInput) titleInput.focus();
                return;
            }
            if (!dateOnlyMode() && minDurationUnits() === null) {
                e.preventDefault();
                alert(tr('error.min_duration_invalid'));
                if (minDurationInput) minDurationInput.focus();
                return;
            }
            var manualLines = validateManualSlots();
            if (manualLines === null) {
                e.preventDefault();
                if (manualSlots) manualSlots.focus();
                return;
            }
            manualLines.forEach(function (line) {
                if (slots.indexOf(line) === -1) slots.push(line);
            });
            slots.sort();
            var minUnits = minDurationUnits();
            if (!dateOnlyMode() && minUnits > 0 && slots.some(function (text) {
                var parsed = parseSlot(text);
                return parsed && parsed.end - parsed.start < minUnits;
            })) {
                e.preventDefault();
                alert(tr('js.slot_min_duration_required'));
                return;
            }
            slotsInput.value = slots.join('\n');
            if (!slots.length) {
                e.preventDefault();
                alert(tr('js.create_slot_required'));
            }
        });
        if (window.ResizeObserver) {
            var observer = new ResizeObserver(function (entries) {
                var nextStep = labelStepForWidth(entries[0].contentRect.width);
                if (nextStep !== labelStep) {
                    labelStep = nextStep;
                    if (activeDate) renderTimeline();
                }
            });
            observer.observe(timeline);
        } else {
            window.addEventListener('resize', function () {
                var nextStep = labelStepForWidth(timeline.clientWidth);
                if (nextStep !== labelStep) {
                    labelStep = nextStep;
                    if (activeDate) renderTimeline();
                }
            });
        }

        setCreateStepState();
        setDescriptionState();
        setMinDurationState();
        setDateOnlyState();
        renderAll();
    }

    function initAvailabilityResponse() {
        var form = document.getElementById('responseForm');
        var hidden = document.getElementById('availabilityInput');
        if (!form || !hidden) return;
        if (window.AITE_RESPONSE_CONFIG && window.AITE_RESPONSE_CONFIG.dateOnly) return;

        var cards = Array.prototype.slice.call(document.querySelectorAll('.availability-card'));
        var viewToggle = document.getElementById('toggleRangeView');
        var selections = {};
        var busyEvents = {};
        var drag = null;
        var resize = null;
        var rangeOnlyView = true;
        var frameStart = FRAME_START_UNITS;
        var frameUnits = DAY_UNITS;
        var frameStartMinutes = FRAME_START_MINUTES;
        var frameMinutes = DAY_MINUTES;
        var labelSteps = {};
        var responseConfig = window.AITE_RESPONSE_CONFIG || {};
        var minDurationUnits = Math.max(0, Number(responseConfig.minDurationUnits || 0));
        var minDurationMinutes = Math.max(0, Number(responseConfig.minDurationMinutes || minDurationUnits * SLOT_MINUTES));

        cards.forEach(function (card) {
            var slotId = card.dataset.slotId;
            if (slotId) {
                selections[slotId] = [];
                busyEvents[slotId] = [];
            }
            var selectAllButton = card.querySelector('.select-all-range');
            if (selectAllButton) {
                selectAllButton.addEventListener('click', function () {
                    var info = cardInfo(card);
                    if (!info) return;
                    drag = null;
                    resize = null;
                    addRange(info.slotId, 0, info.units);
                });
            }
        });

        function cardInfo(card) {
            var start = Number(card.dataset.start);
            var end = Number(card.dataset.end);
            if (!card.dataset.slotId || !Number.isFinite(start) || !Number.isFinite(end) || end <= start) {
                return null;
            }
            return { slotId: card.dataset.slotId, start: start, end: end, units: end - start };
        }

        function pointerIndex(track, e, units) {
            var rect = track.getBoundingClientRect();
            var width = rect.width / units;
            var index = Math.floor((e.clientX - rect.left) / width);
            if (index < 0) index = 0;
            if (index >= units) index = units - 1;
            return index;
        }

        function visualToAbs(index) {
            return (index + frameStart) % frameUnits;
        }

        function absToVisual(index) {
            return (index - frameStart + frameUnits) % frameUnits;
        }

        function displayUnits(info) {
            return rangeOnlyView ? info.units : frameUnits;
        }

        function displayMinuteUnits(info) {
            return rangeOnlyView ? info.units * SLOT_MINUTES : frameMinutes;
        }

        function visualToAbsForInfo(info, index) {
            return rangeOnlyView ? info.start + index : visualToAbs(index);
        }

        function isAllowedVisual(info, index) {
            if (rangeOnlyView) return index >= 0 && index < info.units;
            var absolute = visualToAbs(index);
            return absolute >= info.start && absolute < info.end;
        }

        function visualHourLabel(index, step) {
            var hour = (6 + index) % 24;
            if (index % step !== 0 && index !== 18) return '';
            if (index === 18) return '24';
            return pad(hour);
        }

        function rangeLabelStep(width, units) {
            var target = width < 430 ? 3 : (width < 680 ? 5 : 8);
            return Math.max(1, Math.ceil(units / target));
        }

        function rangeTimeLabel(info, index, step) {
            if (index !== 0 && index === info.units - 1) return '';
            if (index !== 0 && index % step !== 0) return '';
            return timeText(info.start + index);
        }

        function visualParts(absStart, absEnd) {
            absStart = Math.max(0, Math.min(frameUnits, absStart));
            absEnd = Math.max(0, Math.min(frameUnits, absEnd));
            if (absEnd <= absStart) return [];
            if (absEnd - absStart >= frameUnits) return [{ start: 0, end: frameUnits }];

            var start = absToVisual(absStart);
            var end = absToVisual(absEnd);
            if (start < end) return [{ start: start, end: end }];
            var parts = [{ start: start, end: frameUnits }];
            if (end > 0) parts.push({ start: 0, end: end });
            return parts;
        }

        function visualPartsForInfo(info, absStart, absEnd) {
            if (!rangeOnlyView) return visualParts(absStart, absEnd);
            absStart = Math.max(info.start, Math.min(info.end, absStart));
            absEnd = Math.max(info.start, Math.min(info.end, absEnd));
            if (absEnd <= absStart) return [];
            return [{ start: absStart - info.start, end: absEnd - info.start }];
        }

        function appendVisualBlock(track, info, className, label, absStart, absEnd, onClick) {
            var units = displayUnits(info);
            visualPartsForInfo(info, absStart, absEnd).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement(onClick ? 'button' : 'div');
                if (onClick) block.type = 'button';
                block.className = className;
                block.style.left = (part.start / units * 100) + '%';
                block.style.width = ((part.end - part.start) / units * 100) + '%';
                block.textContent = label;
                if (onClick) {
                    block.addEventListener('pointerdown', function (e) { e.stopPropagation(); });
                    block.addEventListener('click', onClick);
                }
                track.appendChild(block);
            });
        }

        function minuteToVisual(minute) {
            return (minute - frameStartMinutes + frameMinutes) % frameMinutes;
        }

        function visualMinuteParts(startMinute, endMinute) {
            startMinute = Math.max(0, Math.min(frameMinutes, startMinute));
            endMinute = Math.max(0, Math.min(frameMinutes, endMinute));
            if (endMinute <= startMinute) return [];
            if (endMinute - startMinute >= frameMinutes) return [{ start: 0, end: frameMinutes }];

            var start = minuteToVisual(startMinute);
            var end = minuteToVisual(endMinute);
            if (start < end) return [{ start: start, end: end }];
            var parts = [{ start: start, end: frameMinutes }];
            if (end > 0) parts.push({ start: 0, end: end });
            return parts;
        }

        function visualMinutePartsForInfo(info, startMinute, endMinute) {
            if (!rangeOnlyView) return visualMinuteParts(startMinute, endMinute);
            var rangeStart = info.start * SLOT_MINUTES;
            var rangeEnd = info.end * SLOT_MINUTES;
            startMinute = Math.max(rangeStart, Math.min(rangeEnd, startMinute));
            endMinute = Math.max(rangeStart, Math.min(rangeEnd, endMinute));
            if (endMinute <= startMinute) return [];
            return [{ start: startMinute - rangeStart, end: endMinute - rangeStart }];
        }

        function appendBusyBlock(track, info, event) {
            var startMinute = timeMinutes(event.start);
            var endMinute = timeMinutes(event.end);
            if (startMinute === null || endMinute === null || endMinute <= startMinute) return;
            var units = displayMinuteUnits(info);
            visualMinutePartsForInfo(info, startMinute, endMinute).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement('div');
                block.className = 'avail-busy-block';
                block.style.left = (part.start / units * 100) + '%';
                block.style.width = ((part.end - part.start) / units * 100) + '%';
                block.dataset.tooltip = eventTitle(event);
                block.tabIndex = 0;
                block.setAttribute('aria-label', eventTitle(event) + ' ' + event.start + '-' + event.end);
                var label = document.createElement('span');
                label.className = 'busy-label';
                label.textContent = eventTitle(event);
                block.appendChild(label);
                track.appendChild(block);
            });
        }

        function appendRangeBlock(track, info, range, index) {
            var units = displayUnits(info);
            visualPartsForInfo(info, info.start + range.start, info.start + range.end).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement('button');
                block.type = 'button';
                block.className = 'avail-block';
                block.dataset.tooltip = timeText(info.start + range.start) + '-' + timeText(info.start + range.end);
                block.style.left = (part.start / units * 100) + '%';
                block.style.width = ((part.end - part.start) / units * 100) + '%';

                var left = document.createElement('span');
                left.className = 'range-handle left';
                left.setAttribute('aria-label', tr('js.change_start'));
                left.appendChild(icon('nav-arrow-left'));

                var text = document.createElement('span');
                text.className = 'range-text';
                text.textContent = timeText(info.start + range.start) + '-' + timeText(info.start + range.end);

                var right = document.createElement('span');
                right.className = 'range-handle right';
                right.setAttribute('aria-label', tr('js.change_end'));
                right.appendChild(icon('nav-arrow-right'));

                block.appendChild(left);
                block.appendChild(text);
                block.appendChild(right);

                left.addEventListener('pointerdown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    resize = { card: track.closest('.availability-card'), slotId: info.slotId, index: index, edge: 'start' };
                });
                right.addEventListener('pointerdown', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    resize = { card: track.closest('.availability-card'), slotId: info.slotId, index: index, edge: 'end' };
                });
                block.addEventListener('pointerdown', function (e) {
                    e.stopPropagation();
                });
                block.addEventListener('click', function (e) {
                    if (e.target.closest('.range-handle')) return;
                    e.stopPropagation();
                    removeRange(info.slotId, range.start, range.end);
                });
                track.appendChild(block);
            });
        }

        function mergeRanges(ranges) {
            ranges.sort(function (a, b) { return a.start - b.start; });
            return ranges.reduce(function (merged, range) {
                var last = merged[merged.length - 1];
                if (last && range.start <= last.end) {
                    last.end = Math.max(last.end, range.end);
                } else {
                    merged.push({ start: range.start, end: range.end });
                }
                return merged;
            }, []);
        }

        function addRange(slotId, start, end) {
            if (end <= start) return;
            if (minDurationUnits > 0 && end - start < minDurationUnits) {
                var message = document.getElementById('editMessage');
                if (message) message.textContent = tr('js.min_duration_required', minDurationMinutes);
                return;
            }
            var message = document.getElementById('editMessage');
            if (message) message.textContent = '';
            selections[slotId] = mergeRanges((selections[slotId] || []).concat([{ start: start, end: end }]));
            renderAll();
        }

        function removeRange(slotId, start, end) {
            selections[slotId] = (selections[slotId] || []).filter(function (range) {
                return range.start !== start || range.end !== end;
            });
            renderAll();
        }

        function setRange(slotId, index, nextRange, shouldMerge) {
            var ranges = (selections[slotId] || []).slice();
            if (!ranges[index] || nextRange.end <= nextRange.start) return;
            ranges[index] = nextRange;
            selections[slotId] = shouldMerge ? mergeRanges(ranges) : ranges;
            renderAll();
        }

        function updateHidden() {
            var payload = [];
            Object.keys(selections).forEach(function (slotId) {
                selections[slotId].forEach(function (range) {
                    payload.push({ slot_id: slotId, start: range.start, end: range.end });
                });
            });
            hidden.value = JSON.stringify(payload);
        }

        function eventTitle(event) {
            return String(event.title || event.name || event.summary || event.schedule_name || event.scheduleName || tr('js.busy_default_title'));
        }

        function eventStart(event) {
            return event.start || event.from || event.start_time || event.startTime;
        }

        function eventEnd(event) {
            return event.end || event.to || event.end_time || event.endTime;
        }

        function renderBusyList(card, info) {
            var busyList = card.querySelector('.ai-busy-list');
            if (!busyList) return;

            var events = busyEvents[info.slotId] || [];
            busyList.innerHTML = '';
            busyList.hidden = events.length === 0;
            if (!events.length) return;

            var title = document.createElement('h4');
            title.textContent = tr('js.ai_busy_title');
            busyList.appendChild(title);

            events.forEach(function (event) {
                var row = document.createElement('div');
                row.className = 'ai-busy-item';
                var name = document.createElement('strong');
                name.textContent = eventTitle(event);
                var time = document.createElement('span');
                time.textContent = event.start + '-' + event.end;
                row.appendChild(name);
                row.appendChild(time);
                busyList.appendChild(row);
            });
        }

        function renderCard(card) {
            var info = cardInfo(card);
            var track = card.querySelector('.availability-track');
            if (!info || !track) return;

            track.innerHTML = '';
            var width = track.clientWidth || window.innerWidth;
            labelSteps[info.slotId] = rangeOnlyView ? rangeLabelStep(width, info.units) : labelStepForWidth(width);
            var units = displayUnits(info);
            var labelRow = document.createElement('div');
            labelRow.className = 'avail-label-row';
            labelRow.style.gridTemplateColumns = 'repeat(' + (rangeOnlyView ? units : 24) + ', minmax(0, 1fr))';
            track.appendChild(labelRow);
            for (var l = 0; l < (rangeOnlyView ? units : 24); l++) {
                var label = document.createElement('div');
                label.className = 'avail-label';
                label.textContent = rangeOnlyView ? rangeTimeLabel(info, l, labelSteps[info.slotId]) : visualHourLabel(l, labelSteps[info.slotId]);
                labelRow.appendChild(label);
            }

            var selectRow = document.createElement('div');
            selectRow.className = 'avail-select-row';
            selectRow.style.gridTemplateColumns = 'repeat(' + units + ', minmax(0, 1fr))';
            track.appendChild(selectRow);
            for (var i = 0; i < units; i++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'avail-cell';
                cell.dataset.index = String(i);
                if (!isAllowedVisual(info, i)) {
                    cell.classList.add('disabled');
                    cell.disabled = true;
                } else {
                    cell.dataset.tooltip = tr('js.select_available_range');
                }
                cell.setAttribute('aria-label', timeText(visualToAbsForInfo(info, i)));
                selectRow.appendChild(cell);
            }

            (busyEvents[info.slotId] || []).forEach(function (event) {
                appendBusyBlock(track, info, event);
            });

            (selections[info.slotId] || []).forEach(function (range, index) {
                appendRangeBlock(track, info, range, index);
            });

            if (drag && drag.slotId === info.slotId) {
                var visualStart = Math.min(drag.start, drag.end);
                var visualEnd = Math.max(drag.start, drag.end) + 1;
                var absValues = [];
                for (var v = visualStart; v < visualEnd; v++) {
                    if (isAllowedVisual(info, v)) absValues.push(visualToAbsForInfo(info, v));
                }
                if (absValues.length) {
                    var absStart = Math.min.apply(null, absValues);
                    var absEnd = Math.max.apply(null, absValues) + 1;
                    appendVisualBlock(track, info, 'avail-block preview', timeText(absStart) + '-' + timeText(absEnd), absStart, absEnd);
                }
            }

            renderBusyList(card, info);
        }

        function renderAll() {
            cards.forEach(renderCard);
            updateHidden();
        }

        cards.forEach(function (card) {
            var info = cardInfo(card);
            var track = card.querySelector('.availability-track');
            if (!info || !track) return;
            if (window.ResizeObserver) {
                var observer = new ResizeObserver(function (entries) {
                    var nextStep = rangeOnlyView ? rangeLabelStep(entries[0].contentRect.width, info.units) : labelStepForWidth(entries[0].contentRect.width);
                    if (nextStep !== labelSteps[info.slotId]) {
                        labelSteps[info.slotId] = nextStep;
                        renderCard(card);
                    }
                });
                observer.observe(track);
            }
            track.addEventListener('pointerdown', function (e) {
                var cell = e.target.closest('.avail-cell');
                if (!cell || cell.disabled) return;
                e.preventDefault();
                drag = {
                    slotId: info.slotId,
                    card: card,
                    start: Number(cell.dataset.index),
                    end: Number(cell.dataset.index)
                };
                renderCard(card);
            });
        });

        if (!window.ResizeObserver) {
            window.addEventListener('resize', function () {
                cards.forEach(function (card) {
                    var info = cardInfo(card);
                    var track = card.querySelector('.availability-track');
                    if (!info || !track) return;
                    var nextStep = rangeOnlyView ? rangeLabelStep(track.clientWidth, info.units) : labelStepForWidth(track.clientWidth);
                    if (nextStep !== labelSteps[info.slotId]) {
                        labelSteps[info.slotId] = nextStep;
                        renderCard(card);
                    }
                });
            });
        }

        if (viewToggle) {
            viewToggle.textContent = viewToggle.dataset.fullLabel;
            viewToggle.classList.add('active');
            viewToggle.addEventListener('click', function () {
                rangeOnlyView = !rangeOnlyView;
                drag = null;
                resize = null;
                labelSteps = {};
                viewToggle.textContent = rangeOnlyView ? viewToggle.dataset.fullLabel : viewToggle.dataset.rangeLabel;
                viewToggle.classList.toggle('active', rangeOnlyView);
                renderAll();
            });
        }

        document.addEventListener('pointermove', function (e) {
            if (resize) {
                var resizeInfo = cardInfo(resize.card);
                var resizeTrack = resize.card.querySelector('.avail-select-row');
                var ranges = selections[resize.slotId] || [];
                var current = ranges[resize.index];
                if (!resizeInfo || !resizeTrack || !current) return;
                var visual = pointerIndex(resizeTrack, e, displayUnits(resizeInfo));
                if (!isAllowedVisual(resizeInfo, visual)) return;
                var absolute = visualToAbsForInfo(resizeInfo, visual);
                var next = { start: current.start, end: current.end };
                if (resize.edge === 'start') {
                    next.start = Math.max(0, Math.min(absolute - resizeInfo.start, current.end - 1));
                    if (minDurationUnits > 0) next.start = Math.max(0, Math.min(next.start, current.end - minDurationUnits));
                } else {
                    next.end = Math.min(resizeInfo.units, Math.max(absolute - resizeInfo.start + 1, current.start + 1));
                    if (minDurationUnits > 0) next.end = Math.min(resizeInfo.units, Math.max(next.end, current.start + minDurationUnits));
                }
                setRange(resize.slotId, resize.index, next, false);
                return;
            }
            if (!drag) return;
            var track = drag.card.querySelector('.avail-select-row');
            var info = cardInfo(drag.card);
            if (!track || !info) return;
            var next = pointerIndex(track, e, displayUnits(info));
            if (!isAllowedVisual(info, next)) return;
            if (next !== drag.end) {
                drag.end = next;
                renderCard(drag.card);
            }
        });

        document.addEventListener('pointerup', function () {
            if (resize) {
                var resizeSlotId = resize.slotId;
                resize = null;
                selections[resizeSlotId] = mergeRanges(selections[resizeSlotId] || []);
                renderAll();
                return;
            }
            if (!drag) return;
            var current = drag;
            drag = null;
            var info = cardInfo(current.card);
            if (!info) return;
            var visualStart = Math.min(current.start, current.end);
            var visualEnd = Math.max(current.start, current.end) + 1;
            var absValues = [];
            for (var v = visualStart; v < visualEnd; v++) {
                if (isAllowedVisual(info, v)) absValues.push(visualToAbsForInfo(info, v));
            }
            if (!absValues.length) return;
            var absStart = Math.min.apply(null, absValues);
            var absEnd = Math.max.apply(null, absValues) + 1;
            addRange(current.slotId, absStart - info.start, absEnd - info.start);
        });

        window.aiteApplyAvailability = function (items) {
            var applied = 0;
            var busyApplied = 0;
            items.forEach(function (item) {
                var slotId = item.slot_id || item.id || item.slotId;
                var card = cards.find(function (candidate) { return candidate.dataset.slotId === slotId; });
                var info = card ? cardInfo(card) : null;
                if (!info) return;

                var ranges = item.ok_ranges || item.ranges || item.available || [];
                if (normalizeStatus(item.status || '') === 'o' && !ranges.length) {
                    ranges = [{ start: timeText(info.start), end: timeText(info.end) }];
                }
                ranges.forEach(function (range) {
                    var start = timeIndex(range.start || range.from);
                    var end = timeIndex(range.end || range.to);
                    if (start === null || end === null) return;
                    start -= info.start;
                    end -= info.start;
                    if (start < 0 || end > info.units || end <= start) return;
                    if (minDurationUnits > 0 && end - start < minDurationUnits) return;
                    selections[slotId] = mergeRanges((selections[slotId] || []).concat([{ start: start, end: end }]));
                    applied++;
                });

                var schedules = item.busy_events || item.busy || item.unavailable || item.unavailable_events || item.events || [];
                if (Array.isArray(schedules)) {
                    busyEvents[slotId] = schedules.map(function (event) {
                        return {
                            title: eventTitle(event),
                            start: eventStart(event),
                            end: eventEnd(event)
                        };
                    }).filter(function (event) {
                        var start = timeMinutes(event.start);
                        var end = timeMinutes(event.end);
                        if (start === null || end === null || end <= start) return false;
                        return start < info.end * SLOT_MINUTES && end > info.start * SLOT_MINUTES;
                    });
                    busyApplied += busyEvents[slotId].length;
                }
            });
            renderAll();
            return { ranges: applied, busy: busyApplied };
        };

        window.aiteSetAvailability = function (ranges) {
            Object.keys(selections).forEach(function (slotId) {
                selections[slotId] = [];
            });
            (ranges || []).forEach(function (range) {
                var slotId = range.slot_id || range.slotId;
                var card = cards.find(function (candidate) { return candidate.dataset.slotId === slotId; });
                var info = card ? cardInfo(card) : null;
                if (!info) return;
                var start = Number(range.start);
                var end = Number(range.end);
                if (!Number.isFinite(start) || !Number.isFinite(end) || start < 0 || end > info.units || end <= start) return;
                if (minDurationUnits > 0 && end - start < minDurationUnits) return;
                selections[slotId].push({ start: start, end: end });
            });
            Object.keys(selections).forEach(function (slotId) {
                selections[slotId] = mergeRanges(selections[slotId]);
            });
            renderAll();
        };

        form.addEventListener('submit', updateHidden);
        renderAll();
    }

    function initResponseEdit() {
        var form = document.getElementById('responseForm');
        var nameInput = document.getElementById('responseName');
        var passwordInput = document.getElementById('editPassword');
        var loadButton = document.getElementById('loadResponse');
        var message = document.getElementById('editMessage');
        if (!form || !nameInput || !passwordInput) return;

        var passwordTouched = false;
        nameInput.addEventListener('input', function () {
            if (!passwordTouched) passwordInput.value = nameInput.value;
        });
        passwordInput.addEventListener('input', function () {
            passwordTouched = true;
        });

        if (loadButton) {
            loadButton.addEventListener('click', function () {
                if (message) message.textContent = '';
                var eventId = form.querySelector('input[name="event_id"]').value;
                if (!nameInput.value.trim() || !passwordInput.value) {
                    if (message) message.textContent = tr('error.response_required');
                    return;
                }

                var body = new URLSearchParams();
                body.set('event_id', eventId);
                body.set('name', nameInput.value.trim());
                body.set('edit_password', passwordInput.value);
                body.set('lang', lang);
                fetch('api.php?action=load_response', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok || !json.ok) throw new Error(json.error || tr('js.load_failed'));
                        return json;
                    });
                }).then(function (json) {
                    if (window.aiteSetAvailability) window.aiteSetAvailability(json.ranges || []);
                    if (window.aiteSetDateOnlyAnswers) window.aiteSetDateOnlyAnswers(json.answers || []);
                    if (window.aiteShowResponseEditor) window.aiteShowResponseEditor();
                    if (message) message.textContent = tr('js.previous_loaded');
                }).catch(function (error) {
                    if (message) message.textContent = error.message;
                });
            });
        }
    }

    function initDateOnlyResponse() {
        if (!window.AITE_RESPONSE_CONFIG || !window.AITE_RESPONSE_CONFIG.dateOnly) return;
        var checkboxes = Array.prototype.slice.call(document.querySelectorAll('.date-answer-card input[type="checkbox"][data-slot-id]'));
        if (!checkboxes.length) return;

        function dateEventTitle(event) {
            return String(event.title || event.name || event.summary || event.schedule_name || event.scheduleName || tr('js.busy_default_title'));
        }

        function dateEventStart(event) {
            return event.start || event.from || event.start_time || event.startTime || '';
        }

        function dateEventEnd(event) {
            return event.end || event.to || event.end_time || event.endTime || '';
        }

        function renderDateBusyEvents(slotId, events) {
            var list = document.querySelector('.date-busy-list[data-slot-id="' + slotId + '"]');
            if (!list) return;
            list.innerHTML = '';
            list.hidden = !events.length;
            if (!events.length) return;

            var title = document.createElement('h4');
            title.textContent = tr('js.date_busy_title');
            list.appendChild(title);

            events.forEach(function (event) {
                var row = document.createElement('div');
                row.className = 'ai-busy-item';
                var name = document.createElement('strong');
                name.textContent = dateEventTitle(event);
                var time = document.createElement('span');
                var start = dateEventStart(event);
                var end = dateEventEnd(event);
                time.textContent = start && end ? start + '-' + end : (start || end || '');
                row.appendChild(name);
                if (time.textContent) row.appendChild(time);
                list.appendChild(row);
            });
        }

        window.aiteSetDateOnlyAnswers = function (answers) {
            var ok = {};
            (answers || []).forEach(function (answer) {
                var slotId = answer.slot_id || answer.slotId || answer.id;
                if (normalizeStatus(answer.status || answer.answer || answer.value) === 'o') ok[slotId] = true;
            });
            checkboxes.forEach(function (input) {
                input.checked = !!ok[input.dataset.slotId];
            });
        };

        window.aiteApplyDateOnlyAnswers = function (items) {
            var applied = 0;
            var bySlot = {};
            var busyBySlot = {};
            (items || []).forEach(function (item) {
                var slotId = item.slot_id || item.id || item.slotId;
                var status = normalizeStatus(item.status || item.answer || item.value);
                if (!slotId) return;
                if (status) bySlot[slotId] = status;
                var schedules = item.busy_events || item.busy || item.events || [];
                busyBySlot[slotId] = Array.isArray(schedules) ? schedules : [];
            });
            checkboxes.forEach(function (input) {
                var slotId = input.dataset.slotId;
                if (Object.prototype.hasOwnProperty.call(bySlot, slotId)) {
                    input.checked = bySlot[slotId] === 'o';
                    applied++;
                }
                renderDateBusyEvents(slotId, busyBySlot[slotId] || []);
            });
            return applied;
        };
    }

    function initAiModal() {
        var modal = document.getElementById('aiModal');
        var openButton = document.getElementById('openAiModal');
        var closeButton = document.getElementById('closeAiModal');
        if (!modal || !openButton) return;

        function openModal() {
            modal.hidden = false;
            var copyButton = document.getElementById('copyPrompt');
            if (copyButton) copyButton.focus();
        }

        function closeModal() {
            modal.hidden = true;
            openButton.focus();
        }

        openButton.addEventListener('click', openModal);
        if (closeButton) closeButton.addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
    }

    function initAi() {
        var copyButton = document.getElementById('copyPrompt');
        var textarea = document.getElementById('aiJson');
        if (!copyButton && !textarea) return;

        var message = document.getElementById('aiMessage');
        var slots = [];
        if (textarea && window.AITE_RESPONSE_CONFIG && window.AITE_RESPONSE_CONFIG.dateOnly) {
            textarea.placeholder = '[{"slot_id":"slot_xxx","status":"x","busy_events":[{"title":"' + tr('js.prompt_busy_title') + '","start":"13:00","end":"14:00"}]}]';
        }
        if (copyButton) {
            var copyButtonText = copyButton.textContent;
            var copyFeedbackTimer = null;
            function showCopyFeedback() {
                copyButton.textContent = tr('js.copied_to_clipboard');
                copyButton.classList.add('copied');
                clearTimeout(copyFeedbackTimer);
                copyFeedbackTimer = window.setTimeout(function () {
                    copyButton.textContent = copyButtonText;
                    copyButton.classList.remove('copied');
                }, 1800);
            }
            try {
                slots = JSON.parse(copyButton.getAttribute('data-slots') || '[]');
            } catch (e) {
                slots = [];
            }
            copyButton.addEventListener('click', function () {
                var dateOnlyMode = !!(window.AITE_RESPONSE_CONFIG && window.AITE_RESPONSE_CONFIG.dateOnly);
                var rangeMode = !!document.getElementById('availabilityInput') && !dateOnlyMode;
                var example = slots.slice(0, 2).map(function (slot) {
                    var parsed = parseSlot(slot.text);
                    if (dateOnlyMode) {
                        return '  {"slot_id":"' + slot.id + '","status":"x","busy_events":[{"title":"' + tr('js.prompt_busy_title') + '","start":"13:00","end":"14:00"}]}';
                    }
                    if (rangeMode && parsed) {
                        var minUnits = window.AITE_RESPONSE_CONFIG ? Number(window.AITE_RESPONSE_CONFIG.minDurationUnits || 0) : 0;
                        if (minUnits > 0 && parsed.end - parsed.start >= minUnits) {
                            return '  {"slot_id":"' + slot.id + '","ok_ranges":[{"start":"' + timeText(parsed.start) + '","end":"' + timeText(parsed.start + minUnits) + '"}],"busy_events":[]}';
                        }
                        var busyEnd = Math.min(parsed.start + 1, parsed.end);
                        var okRanges = busyEnd < parsed.end ? '[{"start":"' + timeText(busyEnd) + '","end":"' + timeText(parsed.end) + '"}]' : '[]';
                        return '  {"slot_id":"' + slot.id + '","ok_ranges":' + okRanges + ',"busy_events":[{"title":"' + tr('js.prompt_busy_title') + '","start":"' + timeText(parsed.start) + '","end":"' + timeText(busyEnd) + '"}]}';
                    }
                    return '  {"slot_id":"' + slot.id + '","status":"o"}';
                }).join(',\n');
                var prompt = [
                    tr('js.prompt_intro'),
                    dateOnlyMode ? tr('js.prompt_available_dates') : (rangeMode ? tr('js.prompt_ok_ranges') : tr('js.prompt_status_o')),
                    dateOnlyMode ? tr('js.prompt_date_busy_events') : '',
                    rangeMode && window.AITE_RESPONSE_CONFIG && Number(window.AITE_RESPONSE_CONFIG.minDurationMinutes || 0) > 0 ? tr('js.prompt_min_duration', Number(window.AITE_RESPONSE_CONFIG.minDurationMinutes || 0)) : '',
                    dateOnlyMode ? '' : (rangeMode ? tr('js.prompt_partial') : tr('js.prompt_status_maybe')),
                    dateOnlyMode ? '' : (rangeMode ? tr('js.prompt_busy_events') : tr('js.prompt_status_x')),
                    rangeMode ? tr('js.prompt_hhmm') : '',
                    rangeMode ? tr('js.prompt_empty') : '',
                    tr('js.prompt_json_only'),
                    tr('js.prompt_ascii_quotes'),
                    '[',
                    example || (rangeMode ? '  {"slot_id":"slot_xxx","ok_ranges":[{"start":"13:00","end":"14:00"}],"busy_events":[{"title":"' + tr('js.prompt_busy_title') + '","start":"14:00","end":"15:00"}]}' : '  {"slot_id":"slot_xxx","status":"o"}'),
                    ']',
                    tr('js.prompt_slots')
                ].filter(Boolean).concat(slots.map(function (slot) {
                    return slot.id + ': ' + (slot.label || slotLabel(slot.text));
                })).join('\n');

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(prompt).then(function () {
                        showCopyFeedback();
                    }).catch(function () {
                        fallbackCopy(prompt, null);
                        showCopyFeedback();
                    });
                } else {
                    fallbackCopy(prompt, null);
                    showCopyFeedback();
                }
            });
        }

        function applyAiText(showErrors) {
            if (!textarea || textarea.value.trim() === '') {
                if (message) message.textContent = '';
                return;
            }
            try {
                var parsed = JSON.parse(textarea.value);
                var items = Array.isArray(parsed) ? parsed : (parsed.answers || []);
                if (!Array.isArray(items)) throw new Error(tr('js.json_array_error'));
                var applied = 0;
                if (window.aiteApplyDateOnlyAnswers) {
                    applied = window.aiteApplyDateOnlyAnswers(items);
                    if (!applied) throw new Error(tr('js.no_applicable_items'));
                    if (message) message.textContent = tr('js.ai_applied_count', applied);
                    return;
                }
                items.forEach(function (item) {
                    if (window.aiteApplyAvailability) return;
                    var slotId = item.slot_id || item.id || item.slotId;
                    var status = normalizeStatus(item.status || item.answer || item.value);
                    if (!slotId || !status) return;
                    var input = null;
                    document.querySelectorAll('input[type="radio"]').forEach(function (candidate) {
                        if (candidate.name === 'answers[' + slotId + ']' && candidate.value === status) {
                            input = candidate;
                        }
                    });
                    if (input) {
                        input.checked = true;
                        applied++;
                    }
                });
                if (window.aiteApplyAvailability) {
                    var result = window.aiteApplyAvailability(items);
                    if (!result.ranges && !result.busy) throw new Error(tr('js.no_applicable_items'));
                    if (message) message.textContent = tr('js.ai_applied_ranges_busy', result.ranges, result.busy);
                    return;
                }
                if (!applied) throw new Error(tr('js.no_applicable_items'));
                if (message) message.textContent = tr('js.ai_applied_count', applied);
            } catch (e) {
                if (showErrors && message) message.textContent = tr('js.check_json', e.message);
            }
        }

        if (textarea) {
            var timer = null;
            textarea.addEventListener('input', function () {
                if (message) message.textContent = '';
                clearTimeout(timer);
                timer = setTimeout(function () {
                    applyAiText(false);
                }, 450);
            });
            textarea.addEventListener('blur', function () {
                applyAiText(true);
            });
        }
    }

    function fallbackCopy(text, message) {
        var area = document.createElement('textarea');
        area.value = text;
        document.body.appendChild(area);
        area.select();
        document.execCommand('copy');
        document.body.removeChild(area);
        if (message) message.textContent = tr('js.copied');
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(text);
        }
        return new Promise(function (resolve) {
            var area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            document.body.removeChild(area);
            resolve();
        });
    }

    function initCopyUrlButtons() {
        document.querySelectorAll('.copy-url-button').forEach(function (button) {
            var defaultText = button.textContent;
            button.addEventListener('click', function () {
                var value = button.getAttribute('data-copy-value') || '';
                if (!value) return;
                copyText(value).then(function () {
                    button.textContent = tr('js.copied');
                    button.classList.add('copied');
                    window.setTimeout(function () {
                        button.textContent = defaultText;
                        button.classList.remove('copied');
                    }, 1600);
                });
            });
        });
    }

    function historyItems() {
        try {
            var items = JSON.parse(localStorage.getItem('aiteRecentEvents') || '[]');
            return Array.isArray(items) ? items : [];
        } catch (e) {
            return [];
        }
    }

    function saveHistoryItems(items) {
        try {
            localStorage.setItem('aiteRecentEvents', JSON.stringify(items.slice(0, 12)));
        } catch (e) {
            // localStorage can be unavailable in restricted browser modes.
        }
    }

    function historyType(item) {
        if (item && (item.type === 'admin' || item.type === 'response')) return item.type;
        return item && String(item.url || '').indexOf('admin.php') >= 0 ? 'admin' : 'response';
    }

    function historyTypeLabel(type) {
        return type === 'admin' ? tr('js.history_admin') : tr('js.history_response');
    }

    function recordEventHistory() {
        var item = window.AITE_EVENT_HISTORY_ITEM;
        if (!item || !item.title || !item.url || !window.localStorage) return;
        var items = historyItems().filter(function (candidate) {
            return candidate.url !== item.url;
        });
        items.unshift({
            title: String(item.title),
            url: String(item.url),
            type: historyType(item),
            viewedAt: Date.now()
        });
        saveHistoryItems(items);
    }

    function renderRecentEvents() {
        var section = document.getElementById('recentEvents');
        var list = document.getElementById('recentEventList');
        if (!section || !list || !window.localStorage) return;

        var items = historyItems();
        section.hidden = items.length === 0;
        list.innerHTML = '';
        items.forEach(function (item) {
            if (!item || !item.title || !item.url) return;

            var card = document.createElement('article');
            card.className = 'recent-event-card';

            var link = document.createElement('a');
            link.href = item.url;
            link.className = 'recent-event-link';

            var title = document.createElement('strong');
            title.textContent = item.title;
            var type = document.createElement('span');
            type.className = 'recent-event-type';
            type.textContent = historyTypeLabel(historyType(item));
            link.appendChild(title);
            link.appendChild(type);

            var remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'icon-button recent-event-delete';
            remove.setAttribute('aria-label', tr('js.delete_history'));
            remove.title = tr('js.delete_history');
            remove.appendChild(icon('trash'));
            remove.addEventListener('click', function () {
                saveHistoryItems(historyItems().filter(function (candidate) {
                    return candidate.url !== item.url;
                }));
                renderRecentEvents();
            });

            card.appendChild(link);
            card.appendChild(remove);
            list.appendChild(card);
        });
        section.hidden = list.children.length === 0;
    }

    function answeredItems() {
        try {
            var items = JSON.parse(localStorage.getItem('aiteAnsweredResponses') || '[]');
            return Array.isArray(items) ? items : [];
        } catch (e) {
            return [];
        }
    }

    function saveAnsweredItems(items) {
        try {
            localStorage.setItem('aiteAnsweredResponses', JSON.stringify(items.slice(0, 50)));
        } catch (e) {
            // localStorage can be unavailable in restricted browser modes.
        }
    }

    function currentResponseState() {
        var state = window.AITE_RESPONSE_STATE || null;
        if (!state || !state.id || !state.url) return null;
        return state;
    }

    function recordAnsweredResponse() {
        var state = currentResponseState();
        if (!state || !state.saved || !window.localStorage) return;
        var items = answeredItems().filter(function (item) {
            return item.id !== state.id && item.url !== state.url;
        });
        items.unshift({
            id: String(state.id),
            title: String(state.title || ''),
            url: String(state.url),
            answeredAt: Date.now()
        });
        saveAnsweredItems(items);
    }

    function hasAnsweredCurrentResponse() {
        var state = currentResponseState();
        if (!state || !window.localStorage) return false;
        return answeredItems().some(function (item) {
            return item && (item.id === state.id || item.url === state.url);
        });
    }

    function initAnsweredResponseMode() {
        var form = document.getElementById('responseForm');
        var summary = document.getElementById('summaryCard');
        var notice = document.getElementById('answeredNotice');
        var answerList = document.getElementById('answerList');
        var submit = document.getElementById('responseSubmit');
        var aiButton = document.getElementById('openAiModal');
        if (!form || !answerList || !submit) return;

        function setEditing(enabled) {
            answerList.hidden = !enabled;
            submit.hidden = !enabled;
            if (aiButton) aiButton.hidden = !enabled;
            form.classList.toggle('response-edit-minimal', !enabled);
        }

        function setCardOrder(answered) {
            if (!summary || !form.parentNode) return;
            if (answered) form.parentNode.insertBefore(summary, form);
            else form.parentNode.insertBefore(form, summary);
        }

        window.aiteShowResponseEditor = function () {
            setEditing(true);
        };

        if (!hasAnsweredCurrentResponse()) {
            setCardOrder(false);
            setEditing(true);
            return;
        }

        setCardOrder(true);
        if (notice) notice.hidden = false;
        setEditing(false);
    }

    document.addEventListener('DOMContentLoaded', function () {
        recordEventHistory();
        recordAnsweredResponse();
        renderRecentEvents();
        initCopyUrlButtons();
        initCreate();
        initAnsweredResponseMode();
        initAvailabilityResponse();
        initDateOnlyResponse();
        initResponseEdit();
        initAiModal();
        initAi();
    });
}());
