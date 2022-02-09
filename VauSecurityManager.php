<?php
/**
 * VauSecurityManager provides functions to encrypt and decrypt data based on VauID 2.0 protocol.
 *
 * @link http://www.ra.ee/apps/vauid/
 * @link https://github.com/erikuus/yii2-vauid2-extension#readme
 * @author Erik Uus <erik.uus@gmail.com>
 * @version 1.0
 */

namespace rahvusarhiiv\vauid;

use yii\base\Component;
use yii\base\InvalidConfigException;
use yii\base\Security;

class VauSecurityManager extends Component
{
    public $version = 2.0;

    private $_security;
    private $_key;

    public function init()
    {
        if ($this->version > 2.0) {
            $this->_security = new Security;
        }
    }

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
        if ($this->_security) {
            $data = $this->_security->encryptByKey($data, $this->_key);
        } else {
            $data = $this->linencrypt($data);
        }
        return bin2hex($data);
    }

    protected function linencrypt($data)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $encrypted = mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $this->_key, $data, MCRYPT_MODE_ECB, $iv);
        return $encrypted;
    }

    /**
     * Decryptes data posted by VAU after successful login.
     * @param string $postedData the encrypted data
     * @return string decrypted data
     */
    public function decrypt($postedData)
    {
        $binData=hex2bin($postedData);

        if ($this->_security) {
            $data = $this->_security->decryptByKey($binData, $this->_key);
        } else {
            $data = $this->lindecrypt($binData);
        }
        return $data;
    }

    protected function lindecrypt($encrypted)
    {
        $iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB);
        $iv = mcrypt_create_iv($iv_size, MCRYPT_RAND);
        $decrypted = mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $this->_key, $encrypted, MCRYPT_MODE_ECB, $iv);
        return rtrim($decrypted);
    }
}
