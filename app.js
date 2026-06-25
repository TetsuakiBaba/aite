(function () {
    var pad = function (n) { return String(n).padStart(2, '0'); };
    var ymd = function (d) { return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()); };
    var weekdays = ['日', '月', '火', '水', '木', '金', '土'];
    var timeText = function (index) {
        var minutes = index * 30;
        return pad(Math.floor(minutes / 60)) + ':' + pad(minutes % 60);
    };
    var slotText = function (date, start, end) {
        return date + ' ' + timeText(start) + '-' + timeText(end);
    };
    var dateLabel = function (dateText) {
        var m = String(dateText || '').match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (!m) return dateText;
        var d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
        return m[1] + '/' + m[2] + '/' + m[3] + '（' + weekdays[d.getDay()] + '）';
    };
    var slotLabel = function (text) {
        var parsed = parseSlot(text);
        if (!parsed) return text;
        return dateLabel(parsed.date) + ' ' + timeText(parsed.start) + '-' + timeText(parsed.end);
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
        return {
            date: m[1],
            start: (Number(m[2]) * 60 + Number(m[3])) / 30,
            end: (Number(m[4]) * 60 + Number(m[5])) / 30
        };
    };
    var timeIndex = function (text) {
        var m = String(text || '').match(/^(\d{1,2}):(\d{2})$/);
        if (!m) return null;
        var index = (Number(m[1]) * 60 + Number(m[2])) / 30;
        if (index < 0 || index > 48 || Math.floor(index) !== index) return null;
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
        var slots = [];
        var today = new Date();
        var viewDate = new Date(today.getFullYear(), today.getMonth(), 1);
        var activeDate = null;
        var dragStart = null;
        var dragEnd = null;
        var dragging = false;
        var resizeSlot = null;
        var suppressTimeClick = false;
        var labelStep = labelStepForWidth(timeline ? timeline.clientWidth : window.innerWidth);
        var frameStart = 12;
        var frameUnits = 48;

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
                    left.textContent = '‹';
                    left.setAttribute('aria-label', '開始時間を変更');
                    var text = document.createElement('span');
                    text.className = 'time-text';
                    text.textContent = label;
                    var right = document.createElement('span');
                    right.className = 'time-handle right';
                    right.textContent = '›';
                    right.setAttribute('aria-label', '終了時間を変更');
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
            return slots.map(parseSlot).filter(function (s) { return s && s.date === date; });
        }

        function hasDate(date) {
            return slots.some(function (text) {
                var parsed = parseSlot(text);
                return parsed && parsed.date === date;
            });
        }

        function renderCalendar() {
            var year = viewDate.getFullYear();
            var month = viewDate.getMonth();
            monthLabel.textContent = year + '年 ' + (month + 1) + '月';
            calendar.innerHTML = '';

            ['日', '月', '火', '水', '木', '金', '土'].forEach(function (day) {
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
                button.textContent = d;
                button.addEventListener('click', function (value) {
                    return function () { openTimeline(value); };
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
                empty.textContent = 'まだ候補日時がありません。';
                selectedSlots.appendChild(empty);
                return;
            }
            slots.forEach(function (text) {
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'slot-chip';
                chip.textContent = slotLabel(text) + '  ×';
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
            track.appendChild(selectRow);
            for (var i = 0; i < 48; i++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'time-cell';
                cell.dataset.index = String(i);
                cell.setAttribute('aria-label', timeText(visualToAbs(i)));
                selectRow.appendChild(cell);
            }

            rangesFor(activeDate).forEach(function (range) {
                appendTimeBlock(track, 'time-block', timeText(range.start) + '-' + timeText(range.end), range.start, range.end, function (e) {
                    if (e.target.classList.contains('time-handle') || suppressTimeClick) {
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
            activeDate = dateText;
            timelineWrap.hidden = false;
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
            var cellWidth = rect.width / 48;
            var index = Math.floor((e.clientX - rect.left) / cellWidth);
            if (index < 0) index = 0;
            if (index > 47) index = 47;
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
        document.getElementById('manualToggle').addEventListener('click', function () {
            manualPanel.hidden = !manualPanel.hidden;
        });
        document.getElementById('mergeManual').addEventListener('click', function () {
            parseLines(manualSlots.value).forEach(addSlot);
        });
        form.addEventListener('submit', function (e) {
            parseLines(manualSlots.value).forEach(function (line) {
                if (slots.indexOf(line) === -1) slots.push(line);
            });
            slots.sort();
            slotsInput.value = slots.join('\n');
            if (!slots.length) {
                e.preventDefault();
                alert('候補日時を1つ以上入力してください。');
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

        renderAll();
    }

    function initAvailabilityResponse() {
        var form = document.getElementById('responseForm');
        var hidden = document.getElementById('availabilityInput');
        if (!form || !hidden) return;

        var cards = Array.prototype.slice.call(document.querySelectorAll('.availability-card'));
        var selections = {};
        var busyEvents = {};
        var drag = null;
        var resize = null;
        var frameStart = 12;
        var frameUnits = 48;
        var frameStartMinutes = 360;
        var frameMinutes = 1440;
        var labelSteps = {};

        cards.forEach(function (card) {
            var slotId = card.dataset.slotId;
            if (slotId) {
                selections[slotId] = [];
                busyEvents[slotId] = [];
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

        function isAllowedVisual(info, index) {
            var absolute = visualToAbs(index);
            return absolute >= info.start && absolute < info.end;
        }

        function visualHourLabel(index, step) {
            var hour = (6 + index) % 24;
            if (index % step !== 0 && index !== 18) return '';
            if (index === 18) return '24';
            return pad(hour);
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

        function appendVisualBlock(track, className, label, absStart, absEnd, onClick) {
            visualParts(absStart, absEnd).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement(onClick ? 'button' : 'div');
                if (onClick) block.type = 'button';
                block.className = className;
                block.style.left = (part.start / frameUnits * 100) + '%';
                block.style.width = ((part.end - part.start) / frameUnits * 100) + '%';
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

        function appendBusyBlock(track, event) {
            var startMinute = timeMinutes(event.start);
            var endMinute = timeMinutes(event.end);
            if (startMinute === null || endMinute === null || endMinute <= startMinute) return;
            visualMinuteParts(startMinute, endMinute).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement('div');
                block.className = 'avail-busy-block';
                block.style.left = (part.start / frameMinutes * 100) + '%';
                block.style.width = ((part.end - part.start) / frameMinutes * 100) + '%';
                block.dataset.tooltip = eventTitle(event);
                block.title = eventTitle(event) + ' ' + event.start + '-' + event.end;
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
            visualParts(info.start + range.start, info.start + range.end).forEach(function (part) {
                if (part.end <= part.start) return;
                var block = document.createElement('button');
                block.type = 'button';
                block.className = 'avail-block';
                block.style.left = (part.start / frameUnits * 100) + '%';
                block.style.width = ((part.end - part.start) / frameUnits * 100) + '%';

                var left = document.createElement('span');
                left.className = 'range-handle left';
                left.textContent = '‹';
                left.setAttribute('aria-label', '開始時間を変更');

                var text = document.createElement('span');
                text.className = 'range-text';
                text.textContent = timeText(info.start + range.start) + '-' + timeText(info.start + range.end);

                var right = document.createElement('span');
                right.className = 'range-handle right';
                right.textContent = '›';
                right.setAttribute('aria-label', '終了時間を変更');

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
                    if (e.target.classList.contains('range-handle')) return;
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
            return String(event.title || event.name || event.summary || event.schedule_name || event.scheduleName || '予定あり');
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
            title.textContent = 'AIが確認した予定（保存されません）';
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
            var list = card.querySelector('.range-list');
            if (!info || !track || !list) return;

            track.innerHTML = '';
            labelSteps[info.slotId] = labelSteps[info.slotId] || labelStepForWidth(track.clientWidth || window.innerWidth);
            var labelRow = document.createElement('div');
            labelRow.className = 'avail-label-row';
            labelRow.style.gridTemplateColumns = 'repeat(24, minmax(0, 1fr))';
            track.appendChild(labelRow);
            for (var l = 0; l < 24; l++) {
                var label = document.createElement('div');
                label.className = 'avail-label';
                label.textContent = visualHourLabel(l, labelSteps[info.slotId]);
                labelRow.appendChild(label);
            }

            var selectRow = document.createElement('div');
            selectRow.className = 'avail-select-row';
            selectRow.style.gridTemplateColumns = 'repeat(' + frameUnits + ', minmax(0, 1fr))';
            track.appendChild(selectRow);
            for (var i = 0; i < frameUnits; i++) {
                var cell = document.createElement('button');
                cell.type = 'button';
                cell.className = 'avail-cell';
                cell.dataset.index = String(i);
                if (!isAllowedVisual(info, i)) {
                    cell.classList.add('disabled');
                    cell.disabled = true;
                }
                cell.setAttribute('aria-label', timeText(visualToAbs(i)));
                selectRow.appendChild(cell);
            }

            (busyEvents[info.slotId] || []).forEach(function (event) {
                appendBusyBlock(track, event);
            });

            (selections[info.slotId] || []).forEach(function (range, index) {
                appendRangeBlock(track, info, range, index);
            });

            if (drag && drag.slotId === info.slotId) {
                var visualStart = Math.min(drag.start, drag.end);
                var visualEnd = Math.max(drag.start, drag.end) + 1;
                var absValues = [];
                for (var v = visualStart; v < visualEnd; v++) {
                    if (isAllowedVisual(info, v)) absValues.push(visualToAbs(v));
                }
                if (absValues.length) {
                    var absStart = Math.min.apply(null, absValues);
                    var absEnd = Math.max.apply(null, absValues) + 1;
                    appendVisualBlock(track, 'avail-block preview', timeText(absStart) + '-' + timeText(absEnd), absStart, absEnd);
                }
            }

            list.innerHTML = '';
            if (!(selections[info.slotId] || []).length) {
                var empty = document.createElement('p');
                empty.className = 'muted';
                empty.textContent = 'OK範囲は未選択です。';
                list.appendChild(empty);
            } else {
                selections[info.slotId].forEach(function (range) {
                    var chip = document.createElement('button');
                    chip.type = 'button';
                    chip.className = 'slot-chip';
                    chip.textContent = timeText(info.start + range.start) + '-' + timeText(info.start + range.end) + '  ×';
                    chip.addEventListener('click', function () {
                        removeRange(info.slotId, range.start, range.end);
                    });
                    list.appendChild(chip);
                });
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
                    var nextStep = labelStepForWidth(entries[0].contentRect.width);
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
                    var nextStep = labelStepForWidth(track.clientWidth);
                    if (nextStep !== labelSteps[info.slotId]) {
                        labelSteps[info.slotId] = nextStep;
                        renderCard(card);
                    }
                });
            });
        }

        document.addEventListener('pointermove', function (e) {
            if (resize) {
                var resizeInfo = cardInfo(resize.card);
                var resizeTrack = resize.card.querySelector('.avail-select-row');
                var ranges = selections[resize.slotId] || [];
                var current = ranges[resize.index];
                if (!resizeInfo || !resizeTrack || !current) return;
                var visual = pointerIndex(resizeTrack, e, frameUnits);
                if (!isAllowedVisual(resizeInfo, visual)) return;
                var absolute = visualToAbs(visual);
                var next = { start: current.start, end: current.end };
                if (resize.edge === 'start') {
                    next.start = Math.max(0, Math.min(absolute - resizeInfo.start, current.end - 1));
                } else {
                    next.end = Math.min(resizeInfo.units, Math.max(absolute - resizeInfo.start + 1, current.start + 1));
                }
                setRange(resize.slotId, resize.index, next, false);
                return;
            }
            if (!drag) return;
            var track = drag.card.querySelector('.avail-select-row');
            var info = cardInfo(drag.card);
            if (!track || !info) return;
            var next = pointerIndex(track, e, frameUnits);
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
                if (isAllowedVisual(info, v)) absValues.push(visualToAbs(v));
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
                        return start < info.end * 30 && end > info.start * 30;
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
                    if (message) message.textContent = '名前と編集用パスワードを入力してください。';
                    return;
                }

                var body = new URLSearchParams();
                body.set('event_id', eventId);
                body.set('name', nameInput.value.trim());
                body.set('edit_password', passwordInput.value);
                fetch('api.php?action=load_response', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
                    body: body.toString()
                }).then(function (response) {
                    return response.json().then(function (json) {
                        if (!response.ok || !json.ok) throw new Error(json.error || '読み込みに失敗しました。');
                        return json;
                    });
                }).then(function (json) {
                    if (window.aiteSetAvailability) window.aiteSetAvailability(json.ranges || []);
                    if (message) message.textContent = '前回回答を読み込みました。';
                }).catch(function (error) {
                    if (message) message.textContent = error.message;
                });
            });
        }
    }

    function initAi() {
        var copyButton = document.getElementById('copyPrompt');
        var textarea = document.getElementById('aiJson');
        if (!copyButton && !textarea) return;

        var message = document.getElementById('aiMessage');
        var slots = [];
        if (copyButton) {
            try {
                slots = JSON.parse(copyButton.getAttribute('data-slots') || '[]');
            } catch (e) {
                slots = [];
            }
            copyButton.addEventListener('click', function () {
                var rangeMode = !!document.getElementById('availabilityInput');
                var example = slots.slice(0, 2).map(function (slot) {
                    var parsed = parseSlot(slot.text);
                    if (rangeMode && parsed) {
                        var busyEnd = Math.min(parsed.start + 1, parsed.end);
                        var okRanges = busyEnd < parsed.end ? '[{"start":"' + timeText(busyEnd) + '","end":"' + timeText(parsed.end) + '"}]' : '[]';
                        return '  {"slot_id":"' + slot.id + '","ok_ranges":' + okRanges + ',"busy_events":[{"title":"予定名","start":"' + timeText(parsed.start) + '","end":"' + timeText(busyEnd) + '"}]}';
                    }
                    return '  {"slot_id":"' + slot.id + '","status":"o"}';
                }).join(',\n');
                var prompt = [
                    '以下の候補日時について、私の予定表を確認してください。',
                    rangeMode ? '参加可能な時間帯だけを ok_ranges に入れてください。' : '参加可能なら o',
                    rangeMode ? '候補時間の一部だけ参加可能な場合は、その範囲だけを返してください。' : '未定なら maybe',
                    rangeMode ? '候補時間内に入っている参加できない予定は、すべて busy_events に予定名 title、開始 start、終了 end を入れてください。' : '参加できないなら x',
                    rangeMode ? 'start と end は必ず HH:MM 形式にしてください。' : '',
                    rangeMode ? '参加可能な時間がなければ ok_ranges は空配列にしてください。予定がなければ busy_events は空配列にしてください。' : '',
                    '必ずJSONだけを返してください。',
                    '[',
                    example || (rangeMode ? '  {"slot_id":"slot_xxx","ok_ranges":[{"start":"13:00","end":"14:00"}],"busy_events":[{"title":"予定名","start":"14:00","end":"15:00"}]}' : '  {"slot_id":"slot_xxx","status":"o"}'),
                    ']',
                    '候補日時'
                ].filter(Boolean).concat(slots.map(function (slot) {
                    return slot.id + ': ' + (slot.label || slotLabel(slot.text));
                })).join('\n');

                if (navigator.clipboard && navigator.clipboard.writeText) {
                    navigator.clipboard.writeText(prompt).then(function () {
                        if (message) message.textContent = 'コピーしました。';
                    }).catch(function () {
                        fallbackCopy(prompt, message);
                    });
                } else {
                    fallbackCopy(prompt, message);
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
                if (!Array.isArray(items)) throw new Error('配列JSONではありません。');
                var applied = 0;
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
                    if (!result.ranges && !result.busy) throw new Error('反映できる候補がありませんでした。');
                    if (message) message.textContent = result.ranges + '件のOK範囲、' + result.busy + '件の予定を自動反映しました。';
                    return;
                }
                if (!applied) throw new Error('反映できる候補がありませんでした。');
                if (message) message.textContent = applied + '件自動反映しました。';
            } catch (e) {
                if (showErrors && message) message.textContent = 'JSONを確認してください: ' + e.message;
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
        if (message) message.textContent = 'コピーしました。';
    }

    document.addEventListener('DOMContentLoaded', function () {
        initCreate();
        initAvailabilityResponse();
        initResponseEdit();
        initAi();
    });
}());
