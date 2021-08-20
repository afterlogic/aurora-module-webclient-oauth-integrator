<?php

namespace Aurora\Modules\OAuthIntegratorWebclient\Models;

use Aurora\System\Classes\Model;
use Aurora\Modules\Core\Models\User;

class OauthAccount extends Model
{

	protected $foreignModel = User::class;
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