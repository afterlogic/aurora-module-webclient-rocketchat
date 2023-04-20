<?php
/**
 * This code is licensed under AGPLv3 license or Afterlogic Software License
 * if commercial version of the product was purchased.
 * For full statements of the licenses see LICENSE-AFTERLOGIC and LICENSE-AGPL3 files.
 */

namespace Aurora\Modules\RocketChatWebclient;

use Aurora\System\SettingsProperty;
use Aurora\Modules\RocketChatWebclient\Enums;

/**
 * @property bool $Disabled
 * @property string $ChatUrl
 * @property string $AdminUsername
 * @property string $AdminPassword
 * @property bool $EnableLogging
 * @property bool $AllowAddMeetingLinkToEvent
 * @property string $MeetingLinkUrl
 * @property int $ChatUsernameFormat
 */

class Settings extends \Aurora\System\Module\Settings
{
    protected function initDefaults()
    {
        $this->aContainer = [
            "Disabled" => new SettingsProperty(
                false,
                "bool",
                null,
                "Setting to true disables the module",
            ),
            "ChatUrl" => new SettingsProperty(
                "",
                "string",
                null,
                "RocketChat installation URL",
            ),
            "AdminUsername" => new SettingsProperty(
                "",
                "string",
                null,
                "Username of RocketChat admin account",
            ),
            "AdminPassword" => new SettingsProperty(
                "",
                "string",
                null,
                "Password of RocketChat admin account",
            ),
            "EnableLogging" => new SettingsProperty(
                false,
                "bool",
                null,
                "Enables logging of API calls and responses to a separate logfile for troubleshooting purposes",
            ),
            "AllowAddMeetingLinkToEvent" => new SettingsProperty(
                false,
                "bool",
                null,
                "Allows for adding Jitsi Meet link when editing an event in calendar",
            ),
            "MeetingLinkUrl" => new SettingsProperty(
                "",
                "string",
                null,
                "Jitsi Meet link base URL",
            ),
            "ChatUsernameFormat" => new SettingsProperty(
                Enums\UsernameFormat::UsernameAndDomain,
                "spec",
                Enums\UsernameFormat::class,
                "Defines how chat username is generated from email address: 0 - username, 1 - username.domain (without TLD), 2 - username.domain.tld",
            ),
        ];
    }
}
