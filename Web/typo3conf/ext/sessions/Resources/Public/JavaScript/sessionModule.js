/**
 * Created by aschmid on 03.02.2016.
 */
/*
 TODO: [TASK] Import icons for Rooms and Sessions
 */


define(['jquery', 'TYPO3/CMS/Sessions/Contrib/fullcalendar', 'TYPO3/CMS/Sessions/Contrib/scheduler', 'TYPO3/CMS/Sessions/Calendar/Utility', 'SessionConfig', 'TYPO3/CMS/Backend/Notification', 'moment', 'TYPO3/CMS/Sessions/Contrib/rivets'],
    function ($, fullcalendar, scheduler, utility, SessionConfig, Notification, moment, rivets) {

    var calendar = {
        instance: $('#calendar'),
        initialize: function () {
            // Generate Buttons dynamically (one for each day of DD based on TS Config)
            var btnTextConfig = [];
            var btnDynConfig = {};
            $.each(SessionConfig.days, function (i, day) {
                var identifier = 'dynDaySel' + i;
                var dayOfEvent = i + 1;
                btnDynConfig[identifier] = {
                    text: 'Day ' + dayOfEvent,
                    click: function () {
                        calendar.instance.fullCalendar('gotoDate', day);
                    }
                };
                btnTextConfig[i] = identifier;
            });
            btnTextConfig = btnTextConfig.join(',');
            /**
             * Configuration
             * @see {@link http://fullcalendar.io/docs/|Fullcalendar Documentation}
             * @see {@link http://fullcalendar.io/scheduler/|Scheduler Plugin}
             */
            this.instance.fullCalendar({
                schedulerLicenseKey: 'CC-Attribution-NonCommercial-NoDerivatives',
                /**
                 * General Display
                 * @see {@link http://fullcalendar.io/docs/display/}
                 */
                header: {
                    left: btnTextConfig + ' prev,next reloadBtn',
                    center: 'title',
                    right: 'agendaDay,timelineDay,agendaAllDays,timelineAllDays'
                },
                customButtons: $.extend({}, btnDynConfig, {
                    reloadBtn: {
                        text: 'Reload',
                        click: function () {
                            calendar.instance.fullCalendar('refetchEvents');
                        }
                    }
                }),
                businessHours: {
                    start: '08:00',
                    end: '20:00',
                    dow: [0, 1, 2, 3, 4, 5, 6, 7]
                },
                aspectRatio: 2.7,
                /**
                 * Timezone
                 * @see {@link http://fullcalendar.io/docs/timezone/}
                 */
                timezoneParam: 'UTC',
                /**
                 * Views
                 * @see {@link http://fullcalendar.io/docs/views/}
                 */
                defaultView: 'agendaDay',
                views: {
                    agendaDay: {
                        buttonText: 'Day Calendar',
                        type: 'agendaDay',
                        snapDuration: '00:15'
                    },
                    timelineDay: {
                        buttonText: 'Day Schedule',
                        type: 'agendaDay',
                        snapDuration: '00:15'
                    },
                    /*agendaAllDays: {
                        buttonText: 'Full Calendar',
                        type: 'agenda',
                        duration: {days: SessionConfig.days.length}
                    },*/
                    timelineAllDays: {
                        buttonText: 'Full Schedule',
                        type: 'timeline',
                        duration: {days: SessionConfig.days.length}
                    }
                },
                /**
                 * Agenda Options
                 * @see {@link http://fullcalendar.io/docs/agenda/}
                 */
                allDaySlot: false,
                minTime: '05:00',
                maxTime: '22:00',
                slotEventOverlap: false,
                slotDuration: moment.duration(15, 'minutes'),
                /**
                 * Current Date
                 * @see {@link http://fullcalendar.io/docs/current_date/}
                 */
                defaultDate: SessionConfig.days[0],
                nowIndicator: true,
                /**
                 * Clicking & Hovering
                 * @see {@link http://fullcalendar.io/docs/mouse/}
                 */
                dayClick: function (date, jsEvent, view, resourceObj) {
                    // clicked on a day -> basically not an event
                    callModule('dayClick', arguments);
                },
                eventClick: function () {
                    callModule('eventClick', arguments);
                },
                /**
                 * Selection
                 * @see {@link http://fullcalendar.io/docs/selection/}
                 */
                selectable: true,
                select: function (start, end, jsEvent, view, resource) {
                    callModule('select', arguments);
                },
                unselect: function(view, jsEvent) {
                    callModule('unselect', arguments);
                },
                /**
                 * Event Data
                 * @see {@link http://fullcalendar.io/docs/event_data/}
                 */
                events: SessionConfig.links.getsessions,
                startParam: 'tx_sessions_web_sessionssession[start]',
                endParam: 'tx_sessions_web_sessionssession[end]',
                // changed made here have to be done in externalDrop function as well... for now
                defaultTimedEventDuration: moment.duration({ hours:1, minutes:30 }),
                /**
                 * Event Rendering
                 * @see {@link http://fullcalendar.io/docs/event_rendering/}
                 */
                eventRender: function (event, element) {
                    eventRender(event, element);
                },
                /**
                 * Event Dragging & Resizing
                 * @see {@link http://fullcalendar.io/docs/event_ui/}
                 */
                editable: true,
                eventDurationEditable: true,
                eventOverlap: false,
                eventDragStart: function(event, jsEvent, ui, view) {
                    callModule('eventDragStart', arguments);
                },
                eventDragStop: function(event, jsEvent, ui, view) {
                    callModule('eventDragStop', arguments);
                },
                eventDrop: function(event, delta, revertFunc, jsEvent, ui, view) {
                    // session was dragged to another position -> start and end changed
                    callModule('eventDrop', arguments);
                },
                eventResize: function(event, delta, revertFunc, jsEvent, ui, view) {
                    // session was resized --> start or end changed
                    callModule('eventResize', arguments);
                },
                /**
                 * Dropping External Elements
                 * @see {@link http://fullcalendar.io/docs/dropping/}
                 */
                droppable: true,
                dropAccept: '.unscheduled-event',
                drop: function(date, jsEvent, ui, resourceId) {
                    callModule('drop', arguments, this);
                },
                /**
                 * Timeline View
                 * @see {@link http://fullcalendar.io/docs/timeline/}
                 */
                resourceAreaWidth: '15%',
                resourceLabelText: 'Rooms',
                /**
                 * Resource Data
                 * @see {@link http://fullcalendar.io/docs/resource_data/}
                 */
                resources: SessionConfig.links.getrooms
            });

            /**
             * Initialize rivets {@link http://rivetsjs.com/}
             * Used as mini MVC inside calendar modules :)
             */
            rivets.formatters.date = function(value) {
                if($.type(value) === 'undefined' || value === null) {
                    return 'undefined';
                }
                value = (moment.isMoment(value)) ? value : moment(value);
                return value.format('DD.MM.YYYY HH:mm');
            };
            // really didn't get rivets completely yet... sometimes you need a formatter if you want the values showing up...
            rivets.formatters.passthru = function(value) {
                return value;
            };

        }
    };

    var modules = [];

    function callModule(method, params, ctx) {
        $.each(modules, function(index, module) {
            var localCtx = ctx || module;
            module[method].apply(localCtx, params);
        });
    }

    /**
     * Helperfunction which removes the event with the given id from the current calendar.
     * This happens locally in memory and does not persist.
     * ATM used for removing dropped events that can not be scheduled.
     * @param id
     */
    function removeEvent(id)
    {
        if($.isNumeric(id)) {
            calendar.instance.fullCalendar( 'removeEvents', id);
        }
    }

    /**
     * Render more information in an event than time and title.
     * @param event
     * @param element
     */
    function eventRender(event, element)
    {
        if($.type(element) !== 'undefined' && $.type(event.speakers) !== 'undefined') {
            var title = element.find('.fc-title').text();
            element.find('.fc-title').html("<strong>"+title+"</strong><br/><br/>" + event.speakers);
        }
    }

    return {
        initialize: function() {
            calendar.initialize();
        },
        addModule: function(module) {
            modules.push(new module(utility, {
                reload: function() {
                    calendar.instance.fullCalendar('refetchEvents');
                },
                removeEvent: function(id) {
                    removeEvent(id);
                },
                getOption: function(name) {
                    return calendar.instance.fullCalendar('option', name);
                },
                unselect: function() {
                    calendar.instance.fullCalendar('unselect');
                }
            }));
        }
    };
});