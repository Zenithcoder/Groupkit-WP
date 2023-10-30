<?php

namespace App\Http\Controllers\Admin\Traits;

use Illuminate\Encryption\Encrypter;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * Defines the generic behavior which is special and globally shared
 * by all Admin controllers
 *
 * @package App\Http\Controllers
 */
trait AdminControllerBehavior
{
    /**
     * Decrypts provided $encryptedData
     *
     * @param string $encryptedData to be decrypted
     *
     * @return string json with request data
     */
    private function decrypt(string $encryptedData)
    {
        return $this->getEncryptor()->decrypt($encryptedData);
    }

    /**
     * Encrypts provided data
     *
     * @param array $data for encryption
     *
     * @return string for encrypt string
     */
    public function encrypt(array $data): string
    {
        return $this->getEncryptor()->encrypt(json_encode($data));
    }

    /**
     * Gets decrypted data from the provided request
     *
     * @param Request $request that contains encrypted data
     *
     * @return ParameterBag instance containing request data
     */
    private function getDecryptedRequest(Request $request)
    {
        $decryptedData = $this->decrypt($request->getContent());

        return new ParameterBag(json_decode($decryptedData, true));
    }

    /**
     * Gets encryptor to protect Admin endpoint request/response
     *
     * @return Encrypter with specified key and cipher
     */
    private function getEncryptor()
    {
        return new Encrypter(config('app.admin_secret_key'), config('app.cipher'));
    }
}
