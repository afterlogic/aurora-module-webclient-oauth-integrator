<?php

namespace Aurora\Modules\OAuthIntegratorWebclient\Models;

use Aurora\System\Classes\Model;

class OauthAccount extends Model
{
    protected $fillable = [
		'Id',
		'IdUser',
		'IdSocial',
		'Type',
		'Name',
		'Email',
		'AccessToken',
		'RefreshToken',
		'Scopes',
		'Disabled',
		'AccountType'
    ];
}