<?php
/**
 * VauUserIdentity class authenticates user based on VauID 2.0 protocol
 *
 * @link http://www.ra.ee/apps/vauid/
 * @link https://github.com/erikuus/yii2-vauid2-extension#readme
 * @author Erik Uus <erik.uus@gmail.com>
 * @version 1.0
 */

namespace rahvusarhiiv\vauid;

use Yii;
use yii\base\Exception;
use yii\helpers\Json;
use yii\helpers\ArrayHelper;

class VauUserIdentity extends \yii\base\BaseObject implements \yii\web\IdentityInterface
{
    const ERROR_NONE = 0;
    const ERROR_INVALID_DATA = 1;
    const ERROR_EXPIRED_DATA = 2;
    const ERROR_SYNC_DATA = 3;
    const ERROR_UNAUTHORIZED = 4;

    public $vauData = [];
    public $errorCode;

    private $_user;

    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Authenticates VAU user
     * @var string $data the data posted back by VAU after successful login
     * @param array $options the authentication options
     * @return boolean whether authentication succeeds
     */
    public function authenticate($data, $options = [])
    {
        // decode json into array
        $vauUserData = Json::decode($data);

        // validate json
        if (json_last_error() == JSON_ERROR_NONE) {
            // validate that data was posted within one minute
            if ((time()-strtotime($vauUserData['timestamp'])) < 60) {
                // validate access rules
                if ($this->checkAccess($vauUserData, $options)) {
                    // authenticate user in application database and
                    // sync VAU and application user data if required
                    if (ArrayHelper::getValue($options, 'dataMapping')) {
                        // set variables for convenience
                        $modelName = ArrayHelper::getValue($options, 'dataMapping.model');
                        $scenario = ArrayHelper::getValue($options, 'dataMapping.scenario');
                        $vauIdAttribute = ArrayHelper::getValue($options, 'dataMapping.id');
                        $enableCreate = ArrayHelper::getValue($options, 'dataMapping.create');
                        $enableUpdate = ArrayHelper::getValue($options, 'dataMapping.update');
                        $syncAttributes = ArrayHelper::getValue($options, 'dataMapping.attributes');

                        // check required
                        if (!$modelName || !$vauIdAttribute) {
                            throw new Exception('Model name and vauid have to be set in data mapping!');
                        }

                        $user = $modelName::findOne([
                            $vauIdAttribute => (int)$vauUserData['id']
                        ]);

                        // if there is no user with given vau id
                        // create new user if $enableCreate is true
                        // otherwise access is denied
                        if ($user === null) {
                            if ($enableCreate) {
                                $user = $scenario ? new $modelName(['scenario' => $scenario]) : new $modelName();
                                $user->{$vauIdAttribute} = $vauUserData['id'];

                                foreach ($syncAttributes as $key => $attribute) {
                                    $user->{$attribute} = ArrayHelper::getValue($vauUserData, $key);
                                }

                                if (!$user->save()) {
                                    $this->errorCode = self::ERROR_SYNC_DATA;
                                }
                            } else {
                                $this->errorCode = self::ERROR_UNAUTHORIZED;
                            }
                        } elseif ($enableUpdate) {
                            if ($scenario) {
                                $user->scenario = $scenario;
                            }

                            foreach ($syncAttributes as $key => $attribute) {
                                $user->{$attribute} = ArrayHelper::getValue($vauUserData, $key);
                            }

                            if (!$user->save()) {
                                $this->errorCode = self::ERROR_SYNC_DATA;
                            }
                        }

                        if (!in_array($this->errorCode, [self::ERROR_UNAUTHORIZED, self::ERROR_SYNC_DATA])) {
                            // assign identity
                            $this->_user = $user;
                            $this->errorCode = self::ERROR_NONE;
                        }
                    } else {
                        // assign identity
                        Yii::$app->session->set('__data', $vauUserData);
                        $this->_user = new static();
                        $this->errorCode = self::ERROR_NONE;
                    }
                } else {
                    $this->errorCode = self::ERROR_UNAUTHORIZED;
                }
            } else {
                $this->errorCode = self::ERROR_EXPIRED_DATA;
            }
        } else {
            $this->errorCode = self::ERROR_INVALID_DATA;
        }

        return !$this->errorCode;
    }

    /**
     * Check whether user can be authenticated by access rules
     * @param array $vauUserData the user data based on VauID 2.0 protocol
     * @param array $authOptions the authentication options
     * @return boolean whether access is granted
     * @see authenticate()
     */
    protected function checkAccess($vauUserData, $authOptions)
    {
        if (ArrayHelper::getValue($authOptions, 'accessRules.safelogin') === true && $vauUserData['safelogin'] !== true) {
            return false;
        }

        if (ArrayHelper::getValue($authOptions, 'accessRules.safehost') === true && $vauUserData['safehost'] !== true) {
            return false;
        }

        if (ArrayHelper::getValue($authOptions, 'accessRules.safe') === true && $vauUserData['safelogin'] !== true && $vauUserData['safehost'] !== true) {
            return false;
        }

        if (ArrayHelper::getValue($authOptions, 'accessRules.employee') === true && $vauUserData['type'] != 1) {
            return false;
        }

        $accessRulesRoles = ArrayHelper::getValue($authOptions, 'accessRules.roles', []);
        $vauUserDataRoles = ArrayHelper::getValue($vauUserData, 'roles', []);

        if ($accessRulesRoles !== [] && array_intersect($accessRulesRoles, $vauUserDataRoles) === []) {
            return false;
        }

        return true;
    }

    public function getId()
    {
        $data = Yii::$app->session->get('__data');
        return isset($data['id']) ? $data['id'] : null;
    }

    public static function findIdentity($id)
    {
        $selfInstance = new self();
        $data = Yii::$app->session->get('__data');
        return new static(['vauData' => $data]);
    }

    public function getAuthKey()
    {
    }

    public function validateAuthKey($authKey)
    {
    }

    public static function findIdentityByAccessToken($token, $type = null)
    {
    }
}
