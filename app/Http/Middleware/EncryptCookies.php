<?php

namespace App\Http\Middleware;

use Illuminate\Cookie\Middleware\EncryptCookies as BaseEncrypter;

class EncryptCookies extends BaseEncrypter
{
    /**
     * The names of the cookies that should not be encrypted.
     *
     * @var array
     */
    protected $except = [
        // This is needed to allow modals to gracefully disappear after file downloads. 
        // Laravel automatically hashes cookie values. 
        // For jquery.fileDownload.js to work properly you need to return a cookie fileDownload=true.
        // You don't want 'true' encrypted. 
        // Without this the the cookie is fileDownload=a_very_long_jumble_of_characters
        // and the fileDownload successCallback will not trigger 
        // leaving your modal open after the download is complete. 
        'fileDownload',
    ];
}
