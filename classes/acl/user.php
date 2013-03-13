<?php

namespace Sesame;

trait ACL_User
{
	public function can_access($url)
	{
		return \Sesame\ACL::check_user_access($this, $url);
	}
}
