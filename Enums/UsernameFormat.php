<?php

namespace Aurora\Modules\RocketChatWebclient\Enums;

class UsernameFormat extends \Aurora\System\Enums\AbstractEnumeration
{
	const Username = 0;
	const UsernameAndDomain = 1;
	const UsernameAndFullDomainName = 2;

	/**
	 * @var array
	 */
	protected $aConsts = array(
		'Username' => self::Username,
		'UsernameAndDomain' => self::UsernameAndDomain,
		'UsernameAndFullDomainName' => self::UsernameAndFullDomainName
	);
}