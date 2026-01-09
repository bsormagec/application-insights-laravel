(function (window, document) {
    "use strict";

    // ===========================================
    // USER & SESSION TRACKING
    // ===========================================
    function generateId() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (c) {
            var r = Math.random() * 16 | 0, v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    function getUserId() {
        var userId = localStorage.getItem('ai_user_id');
        if (!userId) {
            userId = generateId();
            localStorage.setItem('ai_user_id', userId);
        }
        return userId;
    }

    function getSessionId() {
        var sessionId = sessionStorage.getItem('ai_session_id');
        if (!sessionId) {
            sessionId = generateId();
            sessionStorage.setItem('ai_session_id', sessionId);
            sessionStorage.setItem('ai_session_start', Date.now().toString());
        }
        return sessionId;
    }

    function getSessionDuration() {
        var start = sessionStorage.getItem('ai_session_start');
        return start ? Date.now() - parseInt(start, 10) : 0;
    }

    // ===========================================
    // MAIN SDK
    // ===========================================
    var ai = {
        queue: [],
        batchSize: 10,
        retryLimit: 3,
        retryDelay: 1000,
        collectEndpoint: window.AppInsightsConfig?.collectEndpoint || "/appinsights/collect",
        operationId: window.AppInsightsConfig?.operationId || generateId(),
        parentId: window.AppInsightsConfig?.parentId || null,
        pageViewSent: false,
        userId: getUserId(),
        sessionId: getSessionId(),

        /**
         * Get context to attach to all telemetry
         */
        getContext: function () {
            return {
                userId: this.userId,
                sessionId: this.sessionId,
                sessionDuration: getSessionDuration(),
                url: window.location.href,
                referrer: document.referrer || null,
                operationId: this.operationId,
                parentId: this.parentId
            };
        },

        /**
         * Track a custom event
         */
        trackEvent: function (name, properties) {
            properties = properties || {};
            properties = Object.assign({}, this.getContext(), properties);
            this.queue.push({ type: 'event', name: name, properties: properties });
            if (this.queue.length >= this.batchSize) this.flush();
        },

        /**
         * Track a page view with optional duration
         */
        trackPageView: function (name, url, properties, measurements) {
            properties = properties || {};
            measurements = measurements || {};
            properties = Object.assign({}, this.getContext(), properties);
            this.queue.push({
                type: 'pageView',
                name: name || document.title,
                url: url || window.location.href,
                properties: properties,
                measurements: measurements
            });
            this.flush();
        },

        /**
         * Track a metric
         */
        trackMetric: function (name, value, properties) {
            properties = properties || {};
            properties = Object.assign({}, this.getContext(), properties);
            this.queue.push({
                type: 'metric',
                name: name,
                value: value,
                properties: properties
            });
            if (this.queue.length >= this.batchSize) this.flush();
        },

        /**
         * Track a dependency (AJAX/fetch call)
         */
        trackDependency: function (name, url, duration, success, responseCode, properties) {
            properties = properties || {};
            properties = Object.assign({}, this.getContext(), properties);
            this.queue.push({
                type: 'dependency',
                name: name,
                url: url,
                duration: duration,
                success: success,
                responseCode: responseCode,
                properties: properties
            });
            if (this.queue.length >= this.batchSize) this.flush();
        },

        /**
         * Track browser timings (Navigation Timing API)
         */
        trackBrowserTimings: function () {
            var self = this;

            if (document.readyState === 'complete') {
                self._sendBrowserTimings();
            } else {
                window.addEventListener('load', function () {
                    setTimeout(function () {
                        self._sendBrowserTimings();
                    }, 100);
                });
            }
        },

        _sendBrowserTimings: function () {
            if (this.pageViewSent) return;
            this.pageViewSent = true;

            var timing = window.performance && window.performance.timing;
            var navigation = window.performance && window.performance.getEntriesByType
                ? window.performance.getEntriesByType('navigation')[0]
                : null;

            var measurements = {};
            var properties = Object.assign({}, this.getContext(), {
                userAgent: navigator.userAgent,
                screenResolution: screen.width + 'x' + screen.height,
                viewportSize: window.innerWidth + 'x' + window.innerHeight
            });

            if (navigation) {
                measurements.networkLatency = Math.round(navigation.responseStart - navigation.requestStart);
                measurements.serverResponseTime = Math.round(navigation.responseEnd - navigation.responseStart);
                measurements.domProcessingTime = Math.round(navigation.domContentLoadedEventEnd - navigation.responseEnd);
                measurements.pageLoadTime = Math.round(navigation.loadEventEnd - navigation.startTime);
                measurements.domContentLoadedTime = Math.round(navigation.domContentLoadedEventEnd - navigation.startTime);
                measurements.dnsLookupTime = Math.round(navigation.domainLookupEnd - navigation.domainLookupStart);
                measurements.tcpConnectTime = Math.round(navigation.connectEnd - navigation.connectStart);
                measurements.transferSize = navigation.transferSize || 0;
            } else if (timing) {
                var navStart = timing.navigationStart;
                measurements.networkLatency = timing.responseStart - timing.requestStart;
                measurements.serverResponseTime = timing.responseEnd - timing.responseStart;
                measurements.domProcessingTime = timing.domContentLoadedEventEnd - timing.responseEnd;
                measurements.pageLoadTime = timing.loadEventEnd - navStart;
                measurements.domContentLoadedTime = timing.domContentLoadedEventEnd - navStart;
                measurements.dnsLookupTime = timing.domainLookupEnd - timing.domainLookupStart;
                measurements.tcpConnectTime = timing.connectEnd - timing.connectStart;
            }

            // Filter out negative or zero values
            for (var key in measurements) {
                if (measurements[key] <= 0) delete measurements[key];
            }

            this.queue.push({
                type: 'pageView',
                name: document.title || 'Page View',
                url: window.location.href,
                properties: properties,
                measurements: measurements
            });

            if (Object.keys(measurements).length > 0) {
                this.queue.push({
                    type: 'browserTimings',
                    name: document.title || 'Page View',
                    url: window.location.href,
                    properties: properties,
                    measurements: measurements
                });
            }

            this.flush();
        },

        /**
         * Track an exception
         */
        trackException: function (error, properties) {
            properties = properties || {};
            properties = Object.assign({}, this.getContext(), properties);
            try {
                if (error instanceof Error) {
                    error = { message: error.message, stack: error.stack };
                }
                this.queue.push({ type: 'exception', error: error, properties: properties });
                if (this.queue.length >= this.batchSize) this.flush();
            } catch (e) {
                console.error("Failed to track exception:", e);
            }
        },

        /**
         * Flush the telemetry queue
         */
        flush: function () {
            if (!this.collectEndpoint || this.queue.length === 0) return;

            var batch = this.queue.splice(0, this.batchSize);
            this.sendBatch(batch, 0);
        },

        sendBatch: function (batch, attempt) {
            var self = this;
            try {
                fetch(this.collectEndpoint, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify(batch)
                }).then(function (res) {
                    if (!res.ok) throw new Error("HTTP error " + res.status);
                }).catch(function (err) {
                    if (attempt < self.retryLimit) {
                        setTimeout(function () {
                            self.sendBatch(batch, attempt + 1);
                        }, self.retryDelay * Math.pow(2, attempt));
                    }
                });
            } catch (e) {
                console.error("Failed to flush telemetry batch:", e);
            }
        }
    };

    window.appInsights = ai;

    // ===========================================
    // AJAX/FETCH TRACKING
    // ===========================================
    (function () {
        // Intercept XMLHttpRequest
        var originalXHROpen = XMLHttpRequest.prototype.open;
        var originalXHRSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method, url) {
            this._aiMethod = method;
            this._aiUrl = url;
            this._aiStartTime = null;
            return originalXHROpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            var xhr = this;
            xhr._aiStartTime = performance.now();

            xhr.addEventListener('loadend', function () {
                var duration = performance.now() - xhr._aiStartTime;
                var success = xhr.status >= 200 && xhr.status < 400;

                // Skip tracking our own telemetry endpoint
                if (xhr._aiUrl && xhr._aiUrl.indexOf('/appinsights/collect') === -1) {
                    ai.trackDependency(
                        xhr._aiMethod + ' ' + xhr._aiUrl,
                        xhr._aiUrl,
                        Math.round(duration),
                        success,
                        xhr.status,
                        { type: 'XHR' }
                    );
                }
            });

            return originalXHRSend.apply(this, arguments);
        };

        // Intercept Fetch
        var originalFetch = window.fetch;
        window.fetch = function (input, init) {
            var url = typeof input === 'string' ? input : input.url;
            var method = (init && init.method) || 'GET';
            var startTime = performance.now();

            return originalFetch.apply(this, arguments).then(function (response) {
                var duration = performance.now() - startTime;
                var success = response.ok;

                // Skip tracking our own telemetry endpoint
                if (url.indexOf('/appinsights/collect') === -1) {
                    ai.trackDependency(
                        method + ' ' + url,
                        url,
                        Math.round(duration),
                        success,
                        response.status,
                        { type: 'Fetch' }
                    );
                }
                return response;
            }).catch(function (error) {
                var duration = performance.now() - startTime;

                if (url.indexOf('/appinsights/collect') === -1) {
                    ai.trackDependency(
                        method + ' ' + url,
                        url,
                        Math.round(duration),
                        false,
                        0,
                        { type: 'Fetch', error: error.message }
                    );
                }
                throw error;
            });
        };
    })();

    // ===========================================
    // WEB VITALS (Core Web Vitals)
    // ===========================================
    (function () {
        if (!window.PerformanceObserver) return;

        // LCP - Largest Contentful Paint
        try {
            var lcpObserver = new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                var lastEntry = entries[entries.length - 1];
                ai.trackMetric('WebVital_LCP', Math.round(lastEntry.startTime), {
                    element: lastEntry.element ? lastEntry.element.tagName : 'unknown',
                    size: lastEntry.size
                });
            });
            lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
        } catch (e) { }

        // FID - First Input Delay
        try {
            var fidObserver = new PerformanceObserver(function (list) {
                var entries = list.getEntries();
                entries.forEach(function (entry) {
                    ai.trackMetric('WebVital_FID', Math.round(entry.processingStart - entry.startTime), {
                        name: entry.name
                    });
                });
            });
            fidObserver.observe({ type: 'first-input', buffered: true });
        } catch (e) { }

        // CLS - Cumulative Layout Shift
        try {
            var clsValue = 0;
            var clsObserver = new PerformanceObserver(function (list) {
                list.getEntries().forEach(function (entry) {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                });
            });
            clsObserver.observe({ type: 'layout-shift', buffered: true });

            // Report CLS on page hide/unload
            window.addEventListener('visibilitychange', function () {
                if (document.visibilityState === 'hidden' && clsValue > 0) {
                    ai.trackMetric('WebVital_CLS', Math.round(clsValue * 1000) / 1000);
                    ai.flush();
                }
            });
        } catch (e) { }

        // FP/FCP - First Paint / First Contentful Paint
        try {
            var paintObserver = new PerformanceObserver(function (list) {
                list.getEntries().forEach(function (entry) {
                    if (entry.name === 'first-paint') {
                        ai.trackMetric('WebVital_FP', Math.round(entry.startTime));
                    } else if (entry.name === 'first-contentful-paint') {
                        ai.trackMetric('WebVital_FCP', Math.round(entry.startTime));
                    }
                });
            });
            paintObserver.observe({ type: 'paint', buffered: true });
        } catch (e) { }

        // Long Tasks (50ms+)
        try {
            var longTaskObserver = new PerformanceObserver(function (list) {
                list.getEntries().forEach(function (entry) {
                    ai.trackMetric('LongTask', Math.round(entry.duration), {
                        attribution: entry.attribution ? entry.attribution[0]?.name : 'unknown'
                    });
                });
            });
            longTaskObserver.observe({ type: 'longtask', buffered: true });
        } catch (e) { }

        // INP - Interaction to Next Paint (replacing FID in Core Web Vitals)
        try {
            var inpObserver = new PerformanceObserver(function (list) {
                list.getEntries().forEach(function (entry) {
                    if (entry.interactionId) {
                        ai.trackMetric('WebVital_INP', Math.round(entry.duration), {
                            name: entry.name,
                            interactionId: entry.interactionId
                        });
                    }
                });
            });
            inpObserver.observe({ type: 'event', buffered: true, durationThreshold: 40 });
        } catch (e) { }
    })();

    // ===========================================
    // ERROR HANDLERS
    // ===========================================
    window.addEventListener("error", function (event) {
        ai.trackException({
            message: event.message,
            filename: event.filename,
            lineno: event.lineno,
            colno: event.colno,
            stack: event.error ? event.error.stack : null
        });
        ai.flush();
    });

    window.addEventListener("unhandledrejection", function (event) {
        ai.trackException({
            message: "Unhandled promise rejection",
            reason: String(event.reason)
        });
        ai.flush();
    });

    // Flush before page unload
    window.addEventListener("beforeunload", function () {
        ai.flush();
    });

    // Track session end on page hide
    window.addEventListener("visibilitychange", function () {
        if (document.visibilityState === 'hidden') {
            ai.trackEvent('SessionActivity', {
                duration: getSessionDuration()
            });
            ai.flush();
        }
    });

    // Automatically track page view and browser timings on load
    ai.trackBrowserTimings();

})(window, document);
