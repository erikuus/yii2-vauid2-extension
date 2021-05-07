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
    /**
     * @var string JSON posted back by VAU after successful login.
     * {
     *   "id":3,
     *   "type":1,
     *   "firstname":"Erik",
     *   "lastname":"Uus",
     *   "fullname":"Erik Uus",
     *   "birthday":"1973-07-30",
     *   "email":"erik.uus@ra.ee",
     *   "phone":"53225399",
     *   "lang":"et",
     *   "country":"EE",
     *   "warning":false,
     *   "safelogin":false,
     *   "safehost":true,
     *   "timestamp":"2020-01-27T14:42:31+02:00",
     *   "roles":["ClientManager","EnquiryManager"]
     * }
     */
    public $jsonData;
    /**
     * @var string a prefix for the name of the session variables storing user session data.
     */
    private $_keyPrefix;
    /**
     * @var Component|null the user model.
     */
    private $_user;

    /**
     * {@inheritdoc}
     */
    public function getId()
    {
        return $this->getState('__id');
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentity($id)
    {
        $selfInstance = new self();
        $data = $selfInstance->getState('__data', []);
        return new static(['vauData' => $data]);
    }

    /**
     * {@inheritdoc}
     */
    public function getAuthKey()
    {
    }

    /**
     * {@inheritdoc}
     */
    public function validateAuthKey($authKey)
    {
    }

    /**
     * {@inheritdoc}
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
    }

    /**
     * @return User|null
     */
    public function getUser()
    {
        return $this->_user;
    }

    /**
     * Authenticates VAU user.
     * @param array $options the authentication options. The array keys are
     * 'accessRules' and 'dataMapping' and the array values are subarrays
     * with keys as follows:
     * <ul>
     *     <li>accessRules</li>
     *     <ul>
     *         <li>safelogin: whether access is allowed only if user logged into
     *         VAU using ID-card or Mobile-ID.</li>
     *         <li>safehost: whether access is allowed only if user logged into
     *         VAU from host that is recognized as safe in VauID 2.0 protocol.</li>
     *         <li>safe: whether access is allowed only if at least one of the above conditions
     *         are met, i.e. user logged into VAU using ID-card or Mobile-ID or from the safe host.</li>
     *         <li>employee: whether access is allowed only if VAU user type is employee.</li>
     *         <li>roles: the list of VAU role names; access is allowed only if user has at
     *         least one role in VAU that is present in this list.</li>
     *     </ul>
     *     <li>dataMapping</li>
     *     <ul>
     *         <li>model: the name of the model that stores user data in the application.</li>
     *         <li>scenario: the name of the scenario that is used to save VAU user data.</li>
     *         <li>id: the name of the model attribute that stores VAU user id in the application.</li>
     *         <li>create: whether new user should be created in application based on VAU user data
     *         if there is no user with given VAU user id.</li>
     *         <li>update: whether user data in application database should be overwritten with
     *         VAU user data every time user is authenticated.</li>
     *         <li>attributes: the list of mapping VauID 2.0 user data elements onto user model
     *         attributes in the application.</li>
     *     </ul>
     * </ul>
     * <pre>
     * [
     *     'accessRules' => [
     *         'safelogin' => true,
     *         'safehost' => true,
     *         'safe' => true,
     *         'employee' => true,
     *         'roles' => [
     *             'ClientManager',
     *             'EnquiryManager'
     *         ]
     *     ],
     *     'dataMapping' => [
     *         'model' => 'User',
     *         'scenario' => 'vauid',
     *         'id' => 'vau_id',
     *         'create' => false,
     *         'update' => false,
     *         'attributes' => [
     *             'firstname' => 'first_name',
     *             'lastname' => 'last_name'
     *         ]
     *     ]
     * ]
     * </pre>
     * @return boolean whether authentication succeeds.
     */
    public function authenticate($options = [])
    {
        // decode json into array
        $vauUserData = Json::decode($this->jsonData);

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
                        $userNameAttribute = ArrayHelper::getValue($options, 'dataMapping.name');
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
                        $this->_user = new static(['id'=>$vauUserData['id']]);
                        $this->setState('__id', $vauUserData['id']);
                        $this->setState('__data', $vauUserData);
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

    /**
     * Sets the data for the user.
     *
     * @param mixed $value the unique identifier for the user
     */
    protected function setId($value)
    {
        $this->setState('__id', $value);
    }

    /**
     * Sets the data for the user.
     *
     * @param string $data the user data in json format
     */
    protected function setData($data)
    {
        $this->setState('__data', $data);
    }

    /**
     * Returns the value of a variable that is stored in user session.
     *
     * @param string $key variable name
     * @param mixed $defaultValue default value
     * @return mixed the value of the variable. If it doesn't exist in the session,
     * the provided default value will be returned
     * @see setState
     */
    protected function getState($key, $defaultValue = null)
    {
        $key=$this->getStateKeyPrefix() . $key;
        return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
    }

    /**
     * Stores a variable in user session.
     *
     * @param string $key variable name
     * @param mixed $value variable value
     * @param mixed $defaultValue default value. If $value===$defaultValue, the variable will be
     * removed from the session
     * @see getState
     */
    protected function setState($key, $value, $defaultValue = null)
    {
        $key=$this->getStateKeyPrefix() . $key;
        if ($value===$defaultValue) {
            unset($_SESSION[$key]);
        } else {
            $_SESSION[$key]=$value;
        }
    }

    /**
     * @return string a prefix for the name of the session variables storing user session data.
     */
    protected function getStateKeyPrefix()
    {
        if ($this->_keyPrefix!==null) {
            return $this->_keyPrefix;
        } else {
            return $this->_keyPrefix=md5('Yii.' . get_class($this) . '.' . Yii::$app->id);
        }
    }

    /**
     * @param string $value a prefix for the name of the session variables storing user session data.
     */
    protected function setStateKeyPrefix($value)
    {
        $this->_keyPrefix=$value;
    }
}
