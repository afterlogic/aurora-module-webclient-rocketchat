<?php

namespace Aurora\Modules\RocketChatWebclient\Enums;

class UsernameFormat extends \Aurora\System\Enums\AbstractEnumeration
{
    public const Username = 0;
    public const UsernameAndDomain = 1;
    public const UsernameAndFullDomainName = 2;

    /**
     * @var array
     */
    protected $aConsts = array(
        'Username' => self::Username,
        'UsernameAndDomain' => self::UsernameAndDomain,
        'UsernameAndFullDomainName' => self::UsernameAndFullDomainName
    );
}
