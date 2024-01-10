<?php

namespace craftyfm\craftfeedmefilemaker\models;

use Craft;
use craft\base\Model;

/**
 * feedme-filemaker settings
 */
class Settings extends Model
{
    public $user = 'admin';
    public $pass = 'passw0rd123';
    public $authURL = 'https://filemaker.com/';

    public function defineRules(): array
    {
        return [
            [['user', 'pass', 'authURL'], 'required'],
            // ...
        ];
    }
}
