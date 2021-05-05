<?php
/**
 * VauSecurityManager provides functions to encrypt and decrypt data based on VauID 2.0 protocol.
 *
 * For usage refer to {@link VauLoginAction}
 *
 * @link http://www.ra.ee/apps/vauid/
 * @author Erik Uus <erik.uus@gmail.com>
 * @version 1.0
 */

namespace ra\vauid;

use yii\base\Component;
use yii\base\InvalidConfigException;

class VauSecurityManager extends Component
{
    private $_key;

    /**
     * @param string $value the key used to decrypt VAU response
     * @throws InvalidConfigException if the key is empty
     */
    public function setValidationKey($value)
    {
        if (!empty($value)) {
            $this->_key = $value;
        } else {
            throw new InvalidConfigException('VauSecurityManager configuration must have "validationKey" value!');
        }
    }

    /**
     * Encrypts data that VAU posts to remote site after successful login.
     * @param string $data the data to be encrypted
     * @return string encrypted data
     */
    public function encrypt($data)
    {
        return bin2hex($this->linencrypt($data));
    }

    /**
     * Decryptes data posted by VAU after successful login.
     * @param string $postedData the encrypted data
     * @return string decrypted data
     */
    public function decrypt($postedData)
    {
        return $this->lindecrypt(hex2bin($postedData));
    }

    /**
     * @param string $data the data to be crypted
     * @return string encrypted data
     */
    public function linencrypt($data)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->_key, $data, MCRYPT_MODE_ECB, $iv);
        return $encrypted;
    }

    /**
     * @param string $encrypted the encrypted data
     * @return string decrypted data
     */
    protected function lindecrypt($encrypted)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->_key, $encrypted, MCRYPT_MODE_ECB, $iv);
        return rtrim($decrypted);
    }
}
