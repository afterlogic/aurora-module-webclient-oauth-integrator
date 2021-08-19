<?php

namespace Aurora\Modules\OAuthIntegratorWebclient\Models;

use Aurora\System\Classes\Model;

class OauthAccount extends Model
{

	protected $foreignModel = 'Aurora\Modules\Core\Models\User';
	protected $foreignModelIdColumn = 'IdUser'; // Column that refers to an external table

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