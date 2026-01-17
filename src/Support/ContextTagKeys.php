<?php

namespace Sormagec\AppInsightsLaravel\Support;

/**
 * Application Insights Context Tag Keys
 * 
 * Based on official Microsoft SDK: KnownContextTagKeys
 * @see https://github.com/microsoft/ApplicationInsights-node.js/blob/main/src/declarations/generated/models/index.ts
 */
class ContextTagKeys
{
    // =========================================================================
    // Application Context
    // =========================================================================

    /**
     * Application version. Information in the application context fields is 
     * always about the application that is sending the telemetry.
     */
    public const APPLICATION_VERSION = 'ai.application.ver';

    // =========================================================================
    // Device Context
    // =========================================================================

    /**
     * Unique client device id. Computer name in most cases.
     */
    public const DEVICE_ID = 'ai.device.id';

    /**
     * Device locale using <language>-<REGION> pattern, following RFC 5646. Example 'en-US'.
     */
    public const DEVICE_LOCALE = 'ai.device.locale';

    /**
     * Model of the device the end user of the application is using.
     */
    public const DEVICE_MODEL = 'ai.device.model';

    /**
     * Client device OEM name taken from the browser.
     */
    public const DEVICE_OEM_NAME = 'ai.device.oemName';

    /**
     * Operating system name and version of the device.
     */
    public const DEVICE_OS_VERSION = 'ai.device.osVersion';

    /**
     * The type of the device. Examples: 'PC', 'Phone', 'Browser'. 'PC' is the default value.
     */
    public const DEVICE_TYPE = 'ai.device.type';

    // =========================================================================
    // Location Context
    // =========================================================================

    /**
     * The IP address of the client device. IPv4 and IPv6 are supported.
     */
    public const LOCATION_IP = 'ai.location.ip';

    /**
     * The country of the client.
     */
    public const LOCATION_COUNTRY = 'ai.location.country';

    /**
     * The province/state of the client.
     */
    public const LOCATION_PROVINCE = 'ai.location.province';

    /**
     * The city of the client.
     */
    public const LOCATION_CITY = 'ai.location.city';

    // =========================================================================
    // Operation Context (Correlation)
    // =========================================================================

    /**
     * A unique identifier for the operation instance. The operation.id is created by 
     * either a request or a page view. All other telemetry sets this to the value 
     * for the containing request or page view.
     * 
     * MUST be W3C Trace Context format: 32-character lowercase hex string.
     * Example: "0af7651916cd43dd8448eb211c80319c"
     */
    public const OPERATION_ID = 'ai.operation.id';

    /**
     * The name (group) of the operation. The operation.name is created by either 
     * a request or a page view. All other telemetry items set this to the value 
     * for the containing request or page view.
     * 
     * This is what appears in the "Operation Name" column in the Performance blade.
     * Example: "GET /users/{id}"
     */
    public const OPERATION_NAME = 'ai.operation.name';

    /**
     * The unique identifier of the telemetry item's immediate parent.
     * 
     * MUST be W3C Trace Context format: 16-character lowercase hex string.
     * Example: "b7ad6b7169203331"
     */
    public const OPERATION_PARENT_ID = 'ai.operation.parentId';

    /**
     * Name of synthetic source. Some telemetry from the application may represent 
     * synthetic traffic (bots, availability tests, etc.).
     * Examples: "Bot", "HealthCheck", "Availability"
     */
    public const OPERATION_SYNTHETIC_SOURCE = 'ai.operation.syntheticSource';

    /**
     * The correlation vector is a light weight vector clock which can be used to 
     * identify and order related events across clients and services.
     */
    public const OPERATION_CORRELATION_VECTOR = 'ai.operation.correlationVector';

    // =========================================================================
    // Session Context
    // =========================================================================

    /**
     * Session ID - the instance of the user's interaction with the app.
     */
    public const SESSION_ID = 'ai.session.id';

    /**
     * Boolean value indicating whether the session is first for the user or not.
     */
    public const SESSION_IS_FIRST = 'ai.session.isFirst';

    // =========================================================================
    // User Context
    // =========================================================================

    /**
     * In multi-tenant applications this is the account ID or name which the user is acting with.
     */
    public const USER_ACCOUNT_ID = 'ai.user.accountId';

    /**
     * Anonymous user id. Represents the end user of the application.
     */
    public const USER_ID = 'ai.user.id';

    /**
     * Authenticated user id. The opposite of ai.user.id, this represents the user 
     * with a friendly name. Since it's PII information it is not collected by default.
     */
    public const USER_AUTH_USER_ID = 'ai.user.authUserId';

    // =========================================================================
    // Cloud Context (Application Map)
    // =========================================================================

    /**
     * Name of the role the application is a part of.
     * This is what appears as the node name in the Application Map.
     * Example: "TRACS-AI", "API-Gateway", "WebFrontend"
     */
    public const CLOUD_ROLE = 'ai.cloud.role';

    /**
     * Version of the cloud role.
     */
    public const CLOUD_ROLE_VER = 'ai.cloud.roleVer';

    /**
     * Name of the instance where the application is running.
     * Use this to differentiate between deployment slots (production, staging).
     * Example: "n301-easi-tracs-app-production", "server-1"
     */
    public const CLOUD_ROLE_INSTANCE = 'ai.cloud.roleInstance';

    /**
     * Location where the application is running.
     */
    public const CLOUD_LOCATION = 'ai.cloud.location';

    // =========================================================================
    // Internal Context
    // =========================================================================

    /**
     * SDK version. Format: "php:2.0.0"
     * @see https://github.com/microsoft/ApplicationInsights-Home/blob/master/SDK-AUTHORING.md#sdk-version-specification
     */
    public const INTERNAL_SDK_VERSION = 'ai.internal.sdkVersion';

    /**
     * Agent version. Used to indicate the version of StatusMonitor installed on 
     * the computer if it is used for data collection.
     */
    public const INTERNAL_AGENT_VERSION = 'ai.internal.agentVersion';

    /**
     * This is the node name used for billing purposes.
     */
    public const INTERNAL_NODE_NAME = 'ai.internal.nodeName';
}
