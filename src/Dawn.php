<?php

namespace Dawn;

use Closure;
use Illuminate\Support\Facades\Route;

class Dawn
{
    /**
     * The callback that should be used to authenticate Dawn users.
     */
    public static ?Closure $authUsing = null;

    /**
     * The email address for mail notifications.
     */
    public static ?string $email = null;

    /**
     * The Slack webhook URL and channel for notifications.
     */
    public static ?string $slackWebhookUrl = null;
    public static ?string $slackChannel = null;

    /**
     * The SMS number for notifications.
     */
    public static ?string $smsNumber = null;

    /**
     * Determine if the given request can access the Dawn dashboard.
     */
    public static function check(mixed $request): bool
    {
        return (static::$authUsing ?: function () {
            return app()->environment('local');
        })($request);
    }

    /**
     * Set the callback that should be used to authenticate Dawn users.
     */
    public static function auth(Closure $callback): static
    {
        static::$authUsing = $callback;

        return new static;
    }

    /**
     * Route mail notifications to the given address.
     */
    public static function routeMailNotificationsTo(string $email): static
    {
        static::$email = $email;

        return new static;
    }

    /**
     * Route Slack notifications to the given webhook URL and channel.
     */
    public static function routeSlackNotificationsTo(string $url, ?string $channel = null): static
    {
        static::$slackWebhookUrl = $url;
        static::$slackChannel = $channel;

        return new static;
    }

    /**
     * Route SMS notifications to the given number.
     */
    public static function routeSmsNotificationsTo(string $number): static
    {
        static::$smsNumber = $number;

        return new static;
    }

}
